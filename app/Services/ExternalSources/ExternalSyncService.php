<?php

namespace App\Services\ExternalSources;

use App\Jobs\ProcessExternalSyncRun;
use App\Models\Category;
use App\Models\ExternalPublication;
use App\Models\ExternalSyncRun;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/*
 * Orkestrator kampanye sinkronisasi. Satu run = satu kampanye yang terdiri dari banyak batch (halaman); beberapa run dapat
 * dikelompokkan (scope.group) dan dijalankan BERURUTAN. Prinsip:
 *   - batas keras ditegakkan di sini (backend), bukan di formulir;
 *   - setiap batch menyimpan checkpoint, jadi crash/pause/budget tidak mengulang dari awal;
 *   - anggaran (rekaman, permintaan, menit, disk, memori) habis = "sebagian" yang bisa dilanjutkan, bukan gagal;
 *   - perubahan status dari loop selalu bersyarat (hanya dari status aktif), sehingga Pause/Cancel admin tidak tertimpa;
 *   - rantai job dijaga token dispatch: job usang (mis. Resume ganda) keluar sendiri tanpa memproses.
 */
class ExternalSyncService
{
    public function __construct(
        private ExternalSourceRegistry $registry,
        private ExternalSourceSettings $settings,
        private PublicationUpserter $upserter,
    ) {}

    /** Alasan sinkronisasi tidak boleh jalan, atau null bila boleh. */
    public function blocker(string $provider): ?string
    {
        if (! $this->registry->has($provider)) {
            return 'Konektor ini belum memiliki adapter yang berfungsi.';
        }
        if (! $this->settings->get($provider)['enabled']) {
            return 'Konektor nonaktif. Aktifkan lebih dulu di Pengaturan.';
        }

        return null;
    }

    /** Alasan mode tidak tersedia untuk penyedia ini (jujur, dari deklarasi adapter), atau null. */
    public function modeBlocker(string $provider, string $mode): ?string
    {
        if (! $this->registry->has($provider)) {
            return 'Konektor ini belum memiliki adapter yang berfungsi.';
        }

        $info = $this->registry->adapter($provider)->info();

        return in_array($mode, $info['modes'] ?? ['quick', 'category', 'all_mapped'], true) ? null : ($info['mode_notes'][$mode] ?? "{$info['name']} tidak mendukung mode ini.");
    }

    /** Pratinjau: satu halaman kecil dari putaran pertama. TIDAK menulis ke database. */
    public function preview(SyncScope $scope): array
    {
        $this->assertScope($scope);
        $size = (int) config('external-sources.limits.preview_size', 15);
        $page = $this->fetch($scope, 0, null, $size);
        $dois = collect($page->records)->pluck('doi')->filter()->all();
        $known = $dois ? ExternalPublication::query()->whereIn('doi', $dois)->pluck('doi')->all() : [];

        return [
            'total' => $page->total,
            'term' => $scope->runTerms()[0] !== '' ? $scope->runTerms()[0] : ($scope->setLabel ?: 'subjek terpilih'),
            'records' => collect($page->records)->take($size)->map(fn ($r) => $r + ['already_saved' => $r['doi'] && in_array($r['doi'], $known, true)])->all(),
        ];
    }

    /** Membuat satu run (status queued). Penjadwalan job dilakukan pemanggil lewat schedule(). */
    public function start(SyncScope $scope, ?int $userId): ExternalSyncRun
    {
        return $this->startGroup([$scope], $userId)[0];
    }

    /**
     * Membuat grup run berurutan: run pertama 'queued' (siap dijadwalkan), sisanya 'waiting' (menunggu giliran).
     *
     * @param  array<int, SyncScope>  $scopes
     * @return array<int, ExternalSyncRun>
     */
    public function startGroup(array $scopes, ?int $userId): array
    {
        $group = count($scopes) > 1 ? (string) Str::uuid() : null;
        $total = count($scopes);
        $scopes = array_values(array_map(fn (SyncScope $s, int $i) => SyncScope::fromArray([...$s->toArray(), 'group' => $group, 'group_index' => $i, 'group_total' => $total]), $scopes, array_keys($scopes)));

        foreach ($scopes as $s) {
            $this->assertRunnable($s);
        }

        $lock = Cache::lock('external-sync:start', 10);

        return $lock->block(5, function () use ($scopes, $userId) {
            foreach ($scopes as $s) {
                $open = ExternalSyncRun::query()->where('provider', $s->provider)->where('category_id', $s->categoryId)->whereIn('status', ExternalSyncRun::OPEN)->first();
                if ($open) {
                    throw new ExternalSourceException('Sinkronisasi untuk penyedia dan kategori ini masih terbuka (#'.$open->id.', '.$open->status.'). Batalkan atau selesaikan lebih dulu.', 'bad_request');
                }
            }
            if (ExternalSyncRun::query()->whereIn('status', ExternalSyncRun::ACTIVE)->count() >= (int) config('external-sources.budget.max_active_runs', 2)) {
                throw new ExternalSourceException('Batas run aktif bersamaan tercapai ('.config('external-sources.budget.max_active_runs', 2).'). Tunggu salah satu selesai atau jeda.', 'bad_request');
            }

            return array_map(function (SyncScope $s, int $i) use ($userId) {
                $s = $this->withBudget($s);

                return ExternalSyncRun::create([
                    'uuid' => (string) Str::uuid(), 'provider' => $s->provider, 'category_id' => $s->categoryId, 'status' => $i === 0 ? 'queued' : 'waiting',
                    'scope_json' => $s->toArray(), 'checkpoint_json' => $this->freshCheckpoint(), 'initiated_by' => $userId,
                ]);
            }, $scopes, array_keys($scopes));
        });
    }

    /** Menjadwalkan job untuk run (dengan token baru; job lama yang masih beredar otomatis usang). */
    public function schedule(ExternalSyncRun $run, int $delay = 0): void
    {
        $run = $run->fresh();
        if (! $run) {
            return;
        }

        $cp = $this->checkpoint($run);
        $cp['token'] = (string) Str::uuid();
        $run->update(['checkpoint_json' => $cp, 'status' => $run->status === 'waiting' || $run->status === 'paused' ? 'queued' : $run->status]);

        try {
            $job = ProcessExternalSyncRun::dispatch($run->id, $cp['token']);
            if ($delay > 0) {
                $job->delay(now()->addSeconds($delay));
            }
        } catch (Throwable) {
            // Pada antrean sync, kegagalan sudah tercatat di run oleh service; permintaan admin tidak boleh 500.
        }
    }

    /**
     * Memproses sampai $batchBudget batch. @return int|null detik hingga job harus dijadwalkan lagi; null = selesai/berhenti.
     */
    public function process(ExternalSyncRun $run, int $batchBudget): ?int
    {
        $lock = Cache::lock("external-sync:run:{$run->id}", 300);
        if (! $lock->get()) {
            return null;
        }

        try {
            return $this->loop($run->fresh(), $batchBudget);
        } finally {
            $lock->release();
        }
    }

    /* ----------------------------------------------------------------------------------- aksi admin */

    public function pause(ExternalSyncRun $run): bool
    {
        $from = $run->status;
        $cp = $this->checkpoint($run);
        $cp['stop_reason'] = 'paused';
        $cp['paused_from'] = $from === 'waiting' ? 'waiting' : 'active';

        return ExternalSyncRun::query()->whereKey($run->id)->whereIn('status', [...ExternalSyncRun::ACTIVE, 'waiting'])
            ->update(['status' => 'paused', 'checkpoint_json' => json_encode($cp)]) > 0;
    }

    /** Melanjutkan run yang jeda/sebagian/gagal/dibatasi dari checkpoint, dengan jendela anggaran baru. */
    public function resume(ExternalSyncRun $run): void
    {
        $this->assertMayRun($run->provider);

        if (ExternalSyncRun::query()->where('id', '!=', $run->id)->whereIn('status', ExternalSyncRun::ACTIVE)->count() >= (int) config('external-sources.budget.max_active_runs', 2)) {
            throw new ExternalSourceException('Batas run aktif bersamaan tercapai. Tunggu salah satu selesai atau jeda.', 'bad_request');
        }

        $cp = $this->checkpoint($run);
        if ($run->isStale()) {
            // Dilanjutkan oleh admin karena worker berhenti tanpa jejak; dicatat agar transparan.
            $run->errors_json = array_slice([...($run->errors_json ?? []), ['at' => now()->toIso8601String(), 'message' => 'Tidak ada kemajuan lebih dari '.config('external-sources.budget.stale_minutes', 10).' menit (worker kemungkinan berhenti). Dilanjutkan dari checkpoint oleh admin.']], -20);
            $run->save();
        }
        // Pesan henti-karena-anggaran milik jendela sebelumnya tidak lagi berlaku setelah dilanjutkan.
        $run->errors_json = array_values(array_filter($run->errors_json ?? [], fn ($e) => ($e['type'] ?? null) !== 'budget')) ?: null;
        $run->save();
        $wasWaiting = $run->status === 'paused' && ($cp['paused_from'] ?? null) === 'waiting';
        $cp = $this->newWindow($cp);
        $run->update(['status' => $wasWaiting ? 'waiting' : 'queued', 'finished_at' => null, 'checkpoint_json' => $cp]);

        if (! $wasWaiting) {
            $this->schedule($run);
        } elseif (ExternalSyncRun::query()->where('scope_json->group', $run->scope_json['group'] ?? '-')->whereIn('status', ExternalSyncRun::ACTIVE)->doesntExist()) {
            $this->schedule($run);
        }
    }

    public function cancel(ExternalSyncRun $run): bool
    {
        $done = ExternalSyncRun::query()->whereKey($run->id)->whereIn('status', ExternalSyncRun::OPEN)->update(['status' => 'cancelled', 'finished_at' => now()]) > 0;
        $done && $this->advanceGroup($run->fresh());

        return $done;
    }

    /** @return array<int, ExternalSyncRun> */
    public function groupRuns(string $group)
    {
        return ExternalSyncRun::query()->where('scope_json->group', $group)->orderBy('id')->get();
    }

    public function pauseGroup(string $group): int
    {
        return $this->groupRuns($group)->filter(fn ($r) => $this->pause($r))->count();
    }

    public function resumeGroup(string $group): void
    {
        $runs = $this->groupRuns($group)->filter(fn ($r) => in_array($r->status, ['paused', 'partial', 'rate_limited'], true));
        // Yang sudah berjalan lebih dulu (paused_from=active), lalu menunggu; satu per satu sesuai urutan grup.
        $first = $runs->first(fn ($r) => ($this->checkpoint($r)['paused_from'] ?? null) === 'active') ?? $runs->first();
        foreach ($runs as $r) {
            $r->id === $first?->id ? $this->resume($r) : $this->markWaiting($r);
        }
    }

    public function cancelGroup(string $group): int
    {
        $n = 0;
        foreach ($this->groupRuns($group) as $r) {
            ExternalSyncRun::query()->whereKey($r->id)->whereIn('status', ExternalSyncRun::OPEN)->update(['status' => 'cancelled', 'finished_at' => now()]) && $n++;
        }

        return $n;
    }

    /** Henti darurat: semua penyedia berhenti segera; run aktif dijeda (resumable); tidak ada yang bisa dimulai sampai dilepas. */
    public function emergencyStop(?int $userId): int
    {
        $this->settings->setEmergency(true, $userId);
        $n = 0;
        foreach (ExternalSyncRun::query()->whereIn('status', [...ExternalSyncRun::ACTIVE, 'waiting'])->get() as $r) {
            $cp = $this->checkpoint($r);
            $cp['stop_reason'] = 'emergency';
            $cp['paused_from'] = $r->status === 'waiting' ? 'waiting' : 'active';
            ExternalSyncRun::query()->whereKey($r->id)->whereIn('status', [...ExternalSyncRun::ACTIVE, 'waiting'])->update(['status' => 'paused', 'checkpoint_json' => json_encode($cp)]) && $n++;
        }

        return $n;
    }

    public function emergencyRelease(?int $userId): void
    {
        $this->settings->setEmergency(false, $userId);
    }

    /** Tanggal (Y-m-d) saran untuk incremental: akhir sinkronisasi terakhir yang selesai pada cakupan yang sama. */
    public function suggestedSince(string $provider, ?int $categoryId, ?string $set): ?string
    {
        $q = ExternalSyncRun::query()->where('provider', $provider)->whereIn('status', ['completed', 'partial'])->whereNotNull('finished_at');
        $categoryId ? $q->where('category_id', $categoryId) : $q->whereNull('category_id');
        $run = $q->latest('finished_at')->get()->first(fn ($r) => ($r->scope_json['set'] ?? null) === $set);

        return $run?->finished_at?->toDateString();
    }

    /** Dipanggil job yang crash: run tidak boleh tertahan "Antre"; checkpoint dipertahankan untuk Lanjutkan. */
    public function markCrashed(ExternalSyncRun $run): void
    {
        $partial = $run->processed_count > 0;
        $updated = ExternalSyncRun::query()->whereKey($run->id)->whereIn('status', ExternalSyncRun::ACTIVE)->update([
            'status' => $partial ? 'partial' : 'failed', 'finished_at' => now(),
            'errors_json' => json_encode(array_slice(array_merge($run->errors_json ?? [], [['at' => now()->toIso8601String(), 'message' => 'Pekerja antrean berhenti tak terduga. Gunakan Lanjutkan untuk meneruskan dari checkpoint.']]), -20)),
        ]);
        $updated && $this->advanceGroup($run->fresh());
    }

    /** Menjalankan run berikutnya dalam grup (berurutan, tidak serentak). */
    public function advanceGroup(?ExternalSyncRun $finished): void
    {
        $group = $finished?->scope_json['group'] ?? null;
        if (! $group || $this->settings->emergencyStopped()) {
            return;
        }

        $next = ExternalSyncRun::query()->where('scope_json->group', $group)->where('status', 'waiting')->orderBy('id')->first();
        if ($next && ExternalSyncRun::query()->where('scope_json->group', $group)->whereIn('status', ExternalSyncRun::ACTIVE)->doesntExist()) {
            $next->update(['status' => 'queued', 'checkpoint_json' => ($this->checkpoint($next)['batches'] ?? 0) > 0 ? $this->newWindow($this->checkpoint($next)) : $this->checkpoint($next)]);
            $this->schedule($next);
        }
    }

    /* ----------------------------------------------------------------------------------- inti loop */

    private function loop(?ExternalSyncRun $run, int $batchBudget): ?int
    {
        if (! $run || ! in_array($run->status, ExternalSyncRun::ACTIVE, true)) {
            return null;
        }

        $scope = SyncScope::fromArray($run->scope_json);
        $cp = $this->checkpoint($run);
        $info = $this->registry->adapter($scope->provider)->info();
        $terms = $scope->runTerms();

        if ($this->settings->emergencyStopped()) {
            return $this->halt($run, $cp, 'emergency');
        }
        if (($wait = RateGate::cooldownRemaining($scope->provider)) > 0) {
            $this->transition($run, ['status' => 'rate_limited']);

            return $wait;
        }

        $this->transition($run, ['status' => 'running', 'started_at' => $run->started_at ?? now()]);
        $started = microtime(true);
        $pageSize = (int) ($info['page_size'] ?? 100);

        for ($i = 0; $i < $batchBudget; $i++) {
            $run->refresh();
            if (! in_array($run->status, ExternalSyncRun::ACTIVE, true)) {
                $this->persistTime($run, $cp, $started);

                return null; // dijeda/dibatalkan oleh admin di sela batch.
            }

            $remaining = $this->remaining($scope, $run);
            if ($remaining <= 0) {
                return $this->finish($run, $cp, $started, 'records');
            }
            if (($cp['t'] ?? 0) >= count($terms)) {
                return $this->finish($run, $cp, $started, 'exhausted');
            }
            if ($this->settings->emergencyStopped()) {
                return $this->halt($run, $cp, 'emergency', $started);
            }
            if (($stop = $this->budgetStop($scope, $cp, $started)) !== null) {
                return $this->stopBudget($run, $cp, $stop, $started);
            }

            RateGate::wait($scope->provider, (int) ($info['min_interval_ms'] ?? 0));
            $t0 = microtime(true);

            try {
                $page = $this->fetch($scope, (int) $cp['t'], $cp['c'], min($pageSize, $remaining));
            } catch (ExternalSourceException $e) {
                $cp['requests'] = ($cp['requests'] ?? 0) + 1;

                return $this->failure($run, $cp, $e, $started);
            } catch (Throwable) {
                $cp['requests'] = ($cp['requests'] ?? 0) + 1;

                return $this->failure($run, $cp, new ExternalSourceException('Galat tak terduga saat membaca penyedia.', 'failed'), $started);
            }

            $cp['requests'] = ($cp['requests'] ?? 0) + $page->requests;
            $cp['batches'] = ($cp['batches'] ?? 0) + 1;
            $counts = $this->store($run, $scope, $page, $remaining, $terms[(int) $cp['t']] !== '' ? $terms[(int) $cp['t']] : ($scope->setLabel ?: 'set:'.$scope->set), (int) $cp['t'], $cp);
            $cp = $counts['cp'];
            $cp['timeline'] = array_slice([...($cp['timeline'] ?? []), [
                'at' => now()->toIso8601String(), 'n' => array_sum($counts['n']) - $counts['n']['skipped'], 'skip' => $counts['n']['skipped'], 'new' => $counts['n']['new'], 'upd' => $counts['n']['updated'],
                'dup' => $counts['n']['duplicate'], 'fail' => $counts['n']['failed'], 'ms' => (int) round((microtime(true) - $t0) * 1000),
            ]], -(int) config('external-sources.budget.max_timeline', 300));

            $cp['attempts'] = 0;
            if ($page->nextCursor === null || ($page->records === [] && $page->skipped === 0)) {
                $cp['t'] = ((int) $cp['t']) + 1;
                $cp['c'] = null;
            } else {
                $cp['c'] = $page->nextCursor;
            }
            $this->persistTime($run, $cp, $started);
            $started = microtime(true); // persistTime menambah selisih sejak $started; tanpa reset, waktu batch sebelumnya terhitung berulang.
            // Jeda/henti darurat yang masuk saat batch berjalan menulis penandanya di checkpoint; jangan ditimpa salinan lokal.
            $live = ExternalSyncRun::query()->whereKey($run->id)->whereNotIn('status', ExternalSyncRun::ACTIVE)->first(['checkpoint_json']);
            $live && $cp = [...$cp, ...array_intersect_key((array) $live->checkpoint_json, ['stop_reason' => 1, 'paused_from' => 1])];
            $run->update(['checkpoint_json' => $cp]);
        }

        $run->refresh();
        if ($this->remaining($scope, $run) <= 0) {
            return $this->finish($run, $cp, $started, 'records');
        }
        if (($cp['t'] ?? 0) >= count($terms)) {
            return $this->finish($run, $cp, $started, 'exhausted');
        }

        $this->transition($run, ['status' => 'queued'], ['running']);

        return 1;
    }

    /**
     * Sisa rekaman yang boleh diproses: batas run itu sendiri DAN, bila ada, sisa batas total kampanye (seluruh run dalam grup).
     * Dihitung ulang dari database tiap batch, jadi total tidak bisa terlampaui walau batas per run lebih longgar.
     */
    private function remaining(SyncScope $scope, ExternalSyncRun $run): int
    {
        $own = $scope->maxRecords - (int) $run->processed_count;
        if ($scope->groupCap === null) {
            return $own;
        }
        $used = $scope->group ? (int) ExternalSyncRun::query()->where('scope_json->group', $scope->group)->sum('processed_count') : (int) $run->processed_count;

        return min($own, $scope->groupCap - $used);
    }

    /** @return string|null alasan berhenti karena anggaran/sumber daya, atau null bila boleh lanjut */
    private function budgetStop(SyncScope $scope, array $cp, float $started): ?string
    {
        if (($cp['requests'] ?? 0) >= ($scope->maxRequests ?? PHP_INT_MAX)) {
            return 'requests';
        }
        if ((($cp['active_seconds'] ?? 0) + (microtime(true) - $started)) >= ($scope->maxMinutes ?? PHP_INT_MAX / 60) * 60) {
            return 'minutes';
        }

        return ResourceGuard::breach();
    }

    private function store(ExternalSyncRun $run, SyncScope $scope, ExternalPage $page, int $remaining, string $term, int $termIndex, array $cp): array
    {
        $n = ['new' => 0, 'updated' => 0, 'duplicate' => 0, 'skipped' => $page->skipped, 'failed' => 0];
        $errors = $run->errors_json ?? [];

        // Satu transaksi per batch (bukan per rekaman): throughput jauh lebih baik dan batch atomik; tiap rekaman tetap punya savepoint sendiri.
        DB::transaction(function () use ($page, $remaining, $scope, $term, &$n, &$errors) {
            foreach (array_slice($page->records, 0, $remaining) as $record) {
                try {
                    $n[$this->upserter->upsert($record, $scope->categoryId, $term)]++;
                } catch (Throwable) {
                    $n['failed']++;
                    if (count($errors) < 20) {
                        $errors[] = ['at' => now()->toIso8601String(), 'message' => 'Rekaman '.Str::limit((string) ($record['external_id'] ?? '?'), 80, '').' gagal disimpan.'];
                    }
                }
            }
        });

        $totals = $cp['totals'] ?? [];
        if ($page->total !== null) {
            $totals[$termIndex] = $page->total;
        }
        $cp['totals'] = $totals;
        // Target nyata hanya bila total penyedia diketahui untuk run satu-putaran; selain itu tidak ada persentase.
        $cp['target'] = count($scope->runTerms()) === 1 && isset($totals[0]) ? min($scope->maxRecords, (int) $totals[0]) : null;

        $processed = $run->processed_count + $n['new'] + $n['updated'] + $n['duplicate'] + $n['failed'];
        $run->update([
            'found_count' => min($scope->maxRecords, max($run->found_count, $cp['target'] ?? ($run->found_count + count($page->records)))),
            'processed_count' => $processed,
            'new_count' => $run->new_count + $n['new'], 'updated_count' => $run->updated_count + $n['updated'],
            'duplicate_count' => $run->duplicate_count + $n['duplicate'], 'skipped_count' => $run->skipped_count + $n['skipped'],
            'failed_count' => $run->failed_count + $n['failed'], 'errors_json' => $errors ?: null,
        ]);

        return ['n' => $n, 'cp' => $cp];
    }

    private function finish(ExternalSyncRun $run, array $cp, float $started, string $reason): ?int
    {
        $run->refresh();
        $cp['stop_reason'] = $reason;
        $this->persistTime($run, $cp, $started);
        $moved = $this->transition($run, [
            'status' => $run->failed_count > 0 ? ($run->processed_count > $run->failed_count ? 'partial' : 'failed') : 'completed',
            'finished_at' => now(), 'checkpoint_json' => $cp,
        ]);
        $moved && $this->advanceGroup($run->fresh());

        return null;
    }

    /** Anggaran habis: berhenti "sebagian", checkpoint utuh, bisa dilanjutkan dengan jendela anggaran baru. */
    private function stopBudget(ExternalSyncRun $run, array $cp, string $reason, float $started): ?int
    {
        $labels = ['requests' => 'batas permintaan', 'minutes' => 'batas durasi', 'disk' => 'ruang disk bebas di bawah ambang', 'memory' => 'penggunaan memori di atas ambang'];
        $cp['stop_reason'] = $reason;
        $this->persistTime($run, $cp, $started);
        $moved = $this->transition($run, [
            'status' => 'partial', 'finished_at' => now(), 'checkpoint_json' => $cp,
            'errors_json' => array_slice([...($run->errors_json ?? []), ['at' => now()->toIso8601String(), 'type' => 'budget', 'message' => 'Berhenti: '.($labels[$reason] ?? $reason).' tercapai. Tinjau lalu Lanjutkan untuk jendela anggaran baru.']], -20),
        ]);
        // Grup: kampanye berikutnya tetap berjalan bila sumber daya lokal masih aman; untuk disk/memori seluruh grup dijeda.
        $moved && in_array($reason, ['requests', 'minutes'], true) && $this->advanceGroup($run->fresh());

        return null;
    }

    private function halt(ExternalSyncRun $run, array $cp, string $reason, ?float $started = null): ?int
    {
        $cp['stop_reason'] = $reason;
        $cp['paused_from'] = 'active';
        $started && $this->persistTime($run, $cp, $started);
        $this->transition($run, ['status' => 'paused', 'checkpoint_json' => $cp]);

        return null;
    }

    private function failure(ExternalSyncRun $run, array $cp, ExternalSourceException $e, float $started): ?int
    {
        $max = (int) config('external-sources.limits.max_attempts', 3);
        $cp['attempts'] = ((int) ($cp['attempts'] ?? 0)) + 1;
        $this->persistTime($run, $cp, $started);
        $errors = array_slice(array_merge($run->errors_json ?? [], [['at' => now()->toIso8601String(), 'message' => $e->getMessage()]]), -20);
        $base = ['checkpoint_json' => $cp, 'errors_json' => $errors];

        if ($e->kind === 'rate_limited') {
            RateGate::setCooldown($run->provider, $e->retryAfter);
            $this->transition($run, $base + ['status' => 'rate_limited']);

            return $e->retryAfter;
        }

        $final = in_array($e->kind, ['auth', 'bad_request'], true) || $cp['attempts'] >= $max;
        if (! $final) {
            $this->transition($run, $base + ['status' => 'queued']);

            // Backoff eksponensial: 30, 60, 120 ... detik, dibatasi.
            return (int) min((int) config('external-sources.limits.backoff_max_seconds', 900), (int) config('external-sources.limits.backoff_base_seconds', 30) * (2 ** ($cp['attempts'] - 1)));
        }

        $moved = $this->transition($run, $base + ['status' => $run->processed_count > 0 ? 'partial' : 'failed', 'finished_at' => now(), 'failed_count' => $run->failed_count + 1]);
        $moved && $this->advanceGroup($run->fresh());

        return null;
    }

    /** Ubah status HANYA bila run masih berada di salah satu status asal (default: aktif). Mengembalikan apakah berhasil. */
    private function transition(ExternalSyncRun $run, array $data, ?array $from = null): bool
    {
        foreach (['checkpoint_json', 'errors_json', 'scope_json'] as $json) {
            if (isset($data[$json]) && is_array($data[$json])) {
                $data[$json] = json_encode($data[$json], JSON_UNESCAPED_UNICODE);
            }
        }

        return ExternalSyncRun::query()->whereKey($run->id)->whereIn('status', $from ?? ExternalSyncRun::ACTIVE)->update($data + ['updated_at' => now()]) > 0;
    }

    private function persistTime(ExternalSyncRun $run, array &$cp, float $started): void
    {
        $cp['active_seconds'] = round(($cp['active_seconds'] ?? 0) + (microtime(true) - $started), 2);
    }

    /** Jendela anggaran baru (permintaan & durasi direset); batas rekaman total kampanye TIDAK direset. */
    private function newWindow(array $cp): array
    {
        return array_merge($cp, ['window' => ($cp['window'] ?? 1) + 1, 'requests' => 0, 'active_seconds' => 0, 'attempts' => 0, 'stop_reason' => null, 'paused_from' => null]);
    }

    private function markWaiting(ExternalSyncRun $run): void
    {
        $cp = $this->checkpoint($run);
        $run->update(['status' => 'waiting', 'checkpoint_json' => array_merge($cp, ['paused_from' => null, 'stop_reason' => null])]);
    }

    private function freshCheckpoint(): array
    {
        return ['t' => 0, 'c' => null, 'attempts' => 0, 'requests' => 0, 'batches' => 0, 'active_seconds' => 0, 'window' => 1, 'timeline' => [], 'totals' => [], 'target' => null, 'stop_reason' => null, 'token' => null, 'paused_from' => null];
    }

    private function checkpoint(ExternalSyncRun $run): array
    {
        return array_merge($this->freshCheckpoint(), $run->checkpoint_json ?? []);
    }

    private function fetch(SyncScope $scope, int $termIndex, ?string $cursor, int $size): ExternalPage
    {
        $terms = $scope->runTerms();
        $term = $terms[$termIndex] ?? throw new ExternalSourceException('Tidak ada istilah pencarian.', 'bad_request');

        return $this->registry->adapter($scope->provider)->fetchPage($term, $scope->toArray(), $cursor, $size, $this->settings->forAdapter($scope->provider));
    }

    /** Anggaran bawaan bila tidak diberikan: cukup untuk target rekaman dengan ruang retry, tidak pernah melebihi batas konfigurasi. */
    private function withBudget(SyncScope $s): SyncScope
    {
        $pageSize = max(1, (int) ($this->registry->adapter($s->provider)->info()['page_size'] ?? 100));
        $cfg = (int) config('external-sources.budget.max_requests', 2000);

        return SyncScope::fromArray([...$s->toArray(),
            'max_requests' => $s->maxRequests ?? min($cfg, (int) ceil($s->maxRecords / $pageSize) * 2 + 20),
            'max_minutes' => $s->maxMinutes ?? (int) config('external-sources.budget.max_minutes', 180),
        ]);
    }

    /* ----------------------------------------------------------------------------------- validasi */

    /** Validasi cakupan (dipakai preview dan start): penyedia, mode, istilah/set/tanggal, kategori. */
    private function assertScope(SyncScope $scope): void
    {
        if ($reason = $this->blocker($scope->provider)) {
            throw new ExternalSourceException($reason, 'bad_request');
        }
        if ($reason = $this->modeBlocker($scope->provider, $scope->mode)) {
            throw new ExternalSourceException($reason, 'bad_request');
        }
        if ($scope->terms === [] && ! $scope->isTermless()) {
            throw new ExternalSourceException('Isi minimal satu istilah pencarian.', 'bad_request');
        }
        if ($scope->mode === 'corpus' && $scope->provider === 'doaj' && ! $scope->set) {
            throw new ExternalSourceException('Korpus DOAJ wajib memilih subjek (set). Seluruh DOAJ tidak diambil otomatis.', 'bad_request');
        }
        if ($scope->mode === 'incremental' && ! $scope->since) {
            throw new ExternalSourceException('Incremental membutuhkan tanggal awal (sejak).', 'bad_request');
        }
        if ($scope->mode === 'incremental' && $scope->provider === 'doaj' && ! $scope->set && $scope->terms === []) {
            throw new ExternalSourceException('Incremental DOAJ wajib memilih subjek (set).', 'bad_request');
        }
        if ($scope->categoryId !== null && ! Category::query()->whereKey($scope->categoryId)->exists()) {
            throw new ExternalSourceException('Kategori tidak ditemukan.', 'bad_request');
        }
    }

    /** Validasi untuk membuat run: batas keras backend + sumber daya lokal. */
    private function assertRunnable(SyncScope $scope): void
    {
        $this->assertMayRun($scope->provider);
        $this->assertScope($scope);

        $limits = (array) config('external-sources.limits');
        $info = $this->registry->adapter($scope->provider)->info();
        $cap = $scope->mode === 'quick' ? (int) $limits['quick_max_records'] : (int) $limits['campaign_max_records'];

        if ($scope->maxRecords > $cap) {
            throw new ExternalSourceException("Batas rekaman untuk mode ini {$cap}. Jenjang berikutnya (100.000+) menunggu audit kapasitas dan penyesuaian konfigurasi server; tidak ada mode tanpa batas.", 'bad_request');
        }

        $termless = $scope->isTermless();
        $searchCap = $info['search_cap'] ?? null;
        if ($searchCap === null && $scope->provider === 'openalex' && ! $this->settings->get('openalex')['has_key']) {
            $searchCap = 10000; // tanpa API key paging OpenAlex dibatasi 10.000 hasil
        }
        if (! $termless && $searchCap !== null && $scope->maxRecords > $searchCap) {
            throw new ExternalSourceException("{$info['name']} membatasi pencarian hingga {$searchCap} hasil per run pada mode ini.", 'bad_request');
        }
        if ($scope->maxRequests !== null && $scope->maxRequests > (int) config('external-sources.budget.max_requests', 2000)) {
            throw new ExternalSourceException('Batas permintaan melebihi anggaran maksimum server ('.config('external-sources.budget.max_requests', 2000).').', 'bad_request');
        }
        if ($scope->maxMinutes !== null && $scope->maxMinutes > (int) config('external-sources.budget.max_minutes', 180)) {
            throw new ExternalSourceException('Batas durasi melebihi anggaran maksimum server ('.config('external-sources.budget.max_minutes', 180).' menit).', 'bad_request');
        }
        if (ResourceGuard::breach() === 'disk') {
            throw new ExternalSourceException('Ruang disk bebas di bawah ambang aman ('.config('external-sources.budget.min_free_disk_mb', 2048).' MB). Sinkronisasi tidak dimulai.', 'bad_request');
        }
    }

    private function assertMayRun(string $provider): void
    {
        if ($this->settings->emergencyStopped()) {
            throw new ExternalSourceException('Henti darurat aktif. Lepaskan henti darurat lebih dulu.', 'bad_request');
        }
        if ($reason = $this->blocker($provider)) {
            throw new ExternalSourceException($reason, 'bad_request');
        }
    }
}
