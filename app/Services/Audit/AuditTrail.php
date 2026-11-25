<?php

namespace App\Services\Audit;

use App\Models\AiRun;
use App\Models\AiTool;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Collection as SourceCollection;
use App\Models\Document;
use App\Models\DocumentGeneration;
use App\Models\DocumentPromptTemplate;
use App\Models\DocumentTemplate;
use App\Models\GlossaryTerm;
use App\Models\PromptTemplate;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/*
 * Log Aktivitas: kueri, agregasi, dan penyajian audit_logs. Hanya MEMBACA. Tidak ada operasi tulis, ubah, atau hapus pada audit_logs.
 * Semua jumlah dihitung di database (GROUP BY), tabel tidak pernah dimuat utuh ke memori; daftar memakai paginasi sisi server.
 */
class AuditTrail
{
    public const PER_PAGE = 25;

    public const TZ = 'Asia/Jakarta';

    private const OFFSET_HOURS = 7; // WIB tidak memakai DST; created_at disimpan UTC

    public const PERIODS = ['hari-ini' => 'Hari ini', '7-hari' => '7 hari', '30-hari' => '30 hari', 'bulan-ini' => 'Bulan ini', 'kustom' => 'Rentang kustom'];

    public const SORTS = ['terbaru' => 'Terbaru', 'terlama' => 'Terlama', 'jenis' => 'Jenis aksi'];

    /** tipe objek -> [label, kolom nama untuk pencarian objek hidup] */
    private const ENTITIES = [
        AiTool::class => ['Alat AI', 'name'], PromptTemplate::class => ['Instruksi', 'name'], DocumentPromptTemplate::class => ['Prompt Dokumen', 'name'],
        DocumentTemplate::class => ['Template format cetak', 'name'], Document::class => ['Sumber Pustaka', 'title'], Category::class => ['Kategori', 'name'],
        SourceCollection::class => ['Koleksi', 'name'], GlossaryTerm::class => ['Glosarium', 'term'], AiRun::class => ['Riwayat Riset', null],
        DocumentGeneration::class => ['Dokumen pengguna', 'title'], User::class => ['Pengguna', 'name'],
        'ai_provider' => ['Provider AI', null], 'research_settings' => ['Pengaturan runtime', null], 'admin_session' => ['Sesi admin', null],
        'App\Models\SourceSyncRun' => ['Pemindaian sumber', null], 'App\Models\SourceSyncItem' => ['Item pindaian sumber', null],
    ];

    private const LABELS = [
        'version' => 'Versi', 'layout_version' => 'Versi format', 'previous_version' => 'Versi sebelumnya', 'from_version' => 'Dibuat dari versi', 'status' => 'Status',
        'name' => 'Nama', 'code' => 'Kode', 'changed' => 'Bagian yang diubah', 'changed_keys' => 'Pengaturan yang diubah', 'provider' => 'Provider', 'model' => 'Model',
        'before_provider' => 'Provider sebelumnya', 'before_model' => 'Model sebelumnya', 'document_type' => 'Jenis dokumen', 'section' => 'Bagian', 'document_id' => 'ID sumber',
        'document_file_id' => 'ID berkas', 'ai_run_id' => 'ID riset', 'conversation_id' => 'ID percakapan', 'prompt_template_id' => 'ID instruksi', 'prompt_version' => 'Versi instruksi',
        'error' => 'Pesan galat', 'message' => 'Pesan', 'email' => 'Email', 'to' => 'Menjadi', 'term' => 'Istilah', 'title' => 'Judul', 'reason' => 'Alasan', 'ok' => 'Berhasil',
        'is_default' => 'Default', 'source' => 'Asal', 'preset' => 'Preset', 'rule' => 'ID aturan', 'group' => 'Kelompok', 'scope' => 'Cakupan', 'chunks' => 'Jumlah potongan',
        'ai_tool_id' => 'ID alat AI', 'previous_prompt_id' => 'ID instruksi sebelumnya', 'previous_name' => 'Nama instruksi sebelumnya', 'note' => 'Catatan', 'connector' => 'Konektor', 'external_id' => 'ID eksternal', 'format' => 'Format', 'citation_style' => 'Gaya sitasi', 'output_format' => 'Format keluaran',
    ];

    /*
    |--------------------------------------------------------------------------
    | Filter
    |--------------------------------------------------------------------------
    */

    /** @return array{f: array<string, mixed>, notice: ?string} */
    public function filters(Request $request): array
    {
        $in = $request->query();
        $v = Validator::make($in, [
            'periode' => ['nullable', 'in:'.implode(',', array_keys(self::PERIODS))],
            'dari' => ['nullable', 'date_format:Y-m-d'], 'sampai' => ['nullable', 'date_format:Y-m-d'],
            'q' => ['nullable', 'string', 'max:160'], 'aktor' => ['nullable', 'integer', 'min:1'],
            'tipe' => ['nullable', 'in:'.implode(',', array_keys(AuditEvents::ACTOR_KINDS))],
            'aksi' => ['nullable', 'string', 'max:180'], 'entitas' => ['nullable', 'string', 'max:80'],
            'kategori' => ['nullable', 'in:gagal,'.implode(',', array_keys(AuditEvents::CATEGORIES))],
            'urut' => ['nullable', 'in:'.implode(',', array_keys(self::SORTS))],
            'page' => ['nullable', 'integer', 'min:1'], 'log' => ['nullable', 'integer', 'min:1'],
        ]);

        $bad = array_keys($v->errors()->toArray());
        $clean = collect($in)->except($bad)->all();
        $notice = $bad === [] ? null : 'Sebagian filter tidak valid dan diabaikan.';
        $f = [
            'periode' => $clean['periode'] ?? '30-hari', 'dari' => $clean['dari'] ?? null, 'sampai' => $clean['sampai'] ?? null,
            'q' => trim((string) ($clean['q'] ?? '')), 'aktor' => isset($clean['aktor']) ? (int) $clean['aktor'] : null, 'tipe' => $clean['tipe'] ?? null,
            'aksi' => $clean['aksi'] ?? null, 'entitas' => $clean['entitas'] ?? null, 'kategori' => $clean['kategori'] ?? null,
            'urut' => $clean['urut'] ?? 'terbaru', 'log' => isset($clean['log']) ? (int) $clean['log'] : null,
        ];

        if ($f['periode'] === 'kustom') {
            $from = $f['dari'] ? CarbonImmutable::createFromFormat('Y-m-d', $f['dari'], self::TZ) : null;
            $to = $f['sampai'] ? CarbonImmutable::createFromFormat('Y-m-d', $f['sampai'], self::TZ) : null;

            if (! $from || ! $to || $from->gt($to) || $from->diffInDays($to) > 365) {
                $notice = 'Rentang tanggal kustom tidak valid (mulai harus sebelum selesai, maksimal 366 hari). Menampilkan 30 hari terakhir.';
                $f['periode'] = '30-hari';
                $f['dari'] = $f['sampai'] = null;
            }
        } else {
            $f['dari'] = $f['sampai'] = null;
        }

        return ['f' => $f, 'notice' => $notice];
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: string} awal, akhir (WIB), granularitas hour|day */
    public function range(array $f): array
    {
        $now = CarbonImmutable::now(self::TZ);

        return match ($f['periode']) {
            'hari-ini' => [$now->startOfDay(), $now->endOfDay(), 'hour'],
            '7-hari' => [$now->subDays(6)->startOfDay(), $now->endOfDay(), 'day'],
            'bulan-ini' => [$now->startOfMonth(), $now->endOfDay(), 'day'],
            'kustom' => [CarbonImmutable::createFromFormat('Y-m-d', $f['dari'], self::TZ)->startOfDay(), CarbonImmutable::createFromFormat('Y-m-d', $f['sampai'], self::TZ)->endOfDay(), 'day'],
            default => [$now->subDays(29)->startOfDay(), $now->endOfDay(), 'day'],
        };
    }

    public function base(array $f, bool $withCategory = true, bool $withOthers = true): Builder
    {
        [$from, $to] = $this->range($f);
        $q = AuditLog::query()->whereBetween('created_at', [$from->utc()->format('Y-m-d H:i:s'), $to->utc()->format('Y-m-d H:i:s')]);

        if (! $withOthers) {
            return $q;
        }

        if ($f['q'] !== '') {
            $this->search($q, $f['q']);
        }

        if ($f['aktor']) {
            $q->where('user_id', $f['aktor']);
        }

        if ($f['aksi']) {
            $q->where('action', $f['aksi']);
        }

        if ($f['entitas'] && ($type = $this->entityType($f['entitas']))) {
            $q->where('auditable_type', $type);
        }

        if ($f['tipe']) {
            $this->actorKind($q, $f['tipe']);
        }

        if ($withCategory && $f['kategori']) {
            $this->category($q, $f['kategori']);
        }

        return $q;
    }

    private function like(string $s): string
    {
        return '%'.addcslashes($s, '%_\\').'%';
    }

    private function search(Builder $q, string $term): void
    {
        $q->where(function (Builder $w) use ($term): void {
            if (preg_match('/^#?(\d{1,18})$/', $term, $m)) {
                $w->orWhere('id', (int) $m[1])->orWhere('auditable_id', (int) $m[1]);
            }

            $like = $this->like($term);
            $w->orWhere('action', 'like', $like)->orWhere('auditable_type', 'like', $like)->orWhere('ip_address', $term)
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $like)->orWhere('email', 'like', $like));

            if ($codes = AuditEvents::matchingLabel($term)) {
                $w->orWhereIn('action', $codes);
            }

            // Nama objek hanya tersimpan di metadata (name/title/term/code); pola yang menyerupai rahasia tidak dicocokkan.
            if (mb_strlen($term) >= 3 && ! preg_match('/^(sk|pk|rk)-|^eyJ|^Bearer\b/i', $term)) {
                $w->orWhere('metadata_json', 'like', $like);
            }
        });
    }

    private function category(Builder $q, string $category): void
    {
        if ($category === 'gagal') {
            $q->where('action', 'like', '%failed%');

            return;
        }

        $q->where(function (Builder $w) use ($category): void {
            foreach (AuditEvents::prefixesOf($category) as $prefix) {
                $w->orWhere('action', 'like', $prefix.'%');
            }
        });
    }

    private function isSystem(Builder $q): void
    {
        $q->where(function (Builder $w): void {
            foreach (AuditEvents::systemPrefixes() as $prefix) {
                $w->orWhere('action', 'like', $prefix.'%');
            }

            $w->orWhereIn('action', AuditEvents::systemExact());
        });
    }

    private function actorKind(Builder $q, string $kind): void
    {
        match ($kind) {
            'sistem' => $this->isSystem($q),
            'pengguna' => $q->whereNotNull('user_id')->where(function (Builder $w): void {
                foreach (AuditEvents::userPrefixes() as $prefix) {
                    $w->orWhere('action', 'like', $prefix.'%');
                }
            }),
            'admin' => $q->whereNotNull('user_id')->where(function (Builder $w): void {
                $w->whereNot(fn ($x) => $this->isSystem($x))->whereNot(function ($x): void {
                    foreach (AuditEvents::userPrefixes() as $prefix) {
                        $x->orWhere('action', 'like', $prefix.'%');
                    }
                });
            }),
            default => $q->whereNull('user_id')->whereNot(fn ($x) => $this->isSystem($x)),
        };
    }

    /** Tipe objek tersimpan untuk kunci filter (nama kelas tanpa namespace, atau nilai mentah). */
    public function entityType(string $key): ?string
    {
        foreach (array_keys(self::ENTITIES) as $type) {
            if ($this->entityKey($type) === $key) {
                return $type;
            }
        }

        return null;
    }

    public function entityKey(string $type): string
    {
        return class_exists($type) ? class_basename($type) : $type;
    }

    public function entityLabel(?string $type): string
    {
        return $type ? (self::ENTITIES[$type][0] ?? (class_exists($type) ? class_basename($type) : $type)) : 'Tanpa objek';
    }

    /*
    |--------------------------------------------------------------------------
    | Ringkasan, tren, distribusi
    |--------------------------------------------------------------------------
    */

    private function bucketSql(string $gran): string
    {
        $fmt = $gran === 'hour' ? '%Y-%m-%d %H' : '%Y-%m-%d';

        return DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('{$fmt}', created_at, '+".self::OFFSET_HOURS." hours')"
            : "DATE_FORMAT(DATE_ADD(created_at, INTERVAL ".self::OFFSET_HOURS." HOUR), '{$fmt}')";
    }

    /** @return Collection<int, object{bucket: string, action: string, anon: int, n: int}> */
    private function aggregate(Builder $q, string $gran): Collection
    {
        return $q->reorder()->toBase()
            ->selectRaw($this->bucketSql($gran).' as bucket, action, (user_id IS NULL) as anon, count(*) as n')
            ->groupBy(DB::raw($this->bucketSql($gran)), 'action', DB::raw('(user_id IS NULL)'))
            ->get();
    }

    /**
     * @return array{total: int, today: int, config: int, failed: int, admin: int, system: int, series: array<string, array<string, mixed>>, labels: list<string>, buckets: list<string>, verbs: array<string, int>, gran: string}
     */
    public function overview(array $f): array
    {
        [$from, $to, $gran] = $this->range($f);
        $rows = $this->aggregate($this->base($f), $gran);
        $config = array_flip(AuditEvents::configActions());
        $weekly = $gran === 'day' && $from->diffInDays($to) > 62;
        $keyOf = fn (string $bucket) => $weekly ? CarbonImmutable::createFromFormat('Y-m-d', $bucket, self::TZ)->startOfWeek()->format('Y-m-d') : $bucket;

        // Seluruh bucket dibuat dulu agar hari tanpa aktivitas tampil sebagai nol, bukan hilang.
        $buckets = [];

        for ($cursor = $weekly ? $from->startOfWeek() : $from; $cursor->lte($to); $cursor = $gran === 'hour' ? $cursor->addHour() : ($weekly ? $cursor->addWeek() : $cursor->addDay())) {
            $buckets[$gran === 'hour' ? $cursor->format('Y-m-d H') : $cursor->format('Y-m-d')] = 0;
        }

        $series = ['all' => $buckets, 'config' => $buckets, 'admin' => $buckets, 'failed' => $buckets];
        $verbs = [];
        $t = ['total' => 0, 'config' => 0, 'failed' => 0, 'admin' => 0, 'system' => 0];

        foreach ($rows as $r) {
            $key = $keyOf($r->bucket);
            $n = (int) $r->n;
            $t['total'] += $n;
            $series['all'][$key] = ($series['all'][$key] ?? 0) + $n;
            $d = AuditEvents::describe($r->action);
            $verbs[$d['verb']] = ($verbs[$d['verb']] ?? 0) + $n;

            if (isset($config[$r->action])) {
                $t['config'] += $n;
                $series['config'][$key] = ($series['config'][$key] ?? 0) + $n;
            }

            if (AuditEvents::isFailure($r->action)) {
                $t['failed'] += $n;
                $series['failed'][$key] = ($series['failed'][$key] ?? 0) + $n;
            }

            if (AuditEvents::producer($r->action) === 'sistem') {
                $t['system'] += $n;
            } elseif (AuditEvents::producer($r->action) === 'admin' && ! (int) $r->anon) {
                $t['admin'] += $n;
                $series['admin'][$key] = ($series['admin'][$key] ?? 0) + $n;
            }
        }

        arsort($verbs);
        $names = ['all' => 'Semua aktivitas', 'config' => 'Perubahan konfigurasi', 'admin' => 'Aktivitas admin', 'failed' => 'Event gagal'];
        $out = [];

        foreach ($series as $key => $points) {
            $sum = array_sum($points);

            if ($key === 'all' || $sum > 0) {
                $out[$key] = ['label' => $names[$key], 'total' => $sum, 'points' => array_values($points), 'max' => $points ? max($points) : 0];
            }
        }

        return $t + [
            'today' => AuditLog::query()->where('created_at', '>=', CarbonImmutable::now(self::TZ)->startOfDay()->utc()->format('Y-m-d H:i:s'))->count(),
            'series' => $out, 'verbs' => $verbs, 'gran' => $weekly ? 'week' : $gran,
            'buckets' => array_keys($buckets),
            'labels' => array_map(fn ($b) => $this->bucketLabel($b, $gran, $weekly), array_keys($buckets)),
        ];
    }

    private function bucketLabel(string $bucket, string $gran, bool $weekly): string
    {
        if ($gran === 'hour') {
            return CarbonImmutable::createFromFormat('Y-m-d H', $bucket, self::TZ)->format('H.00');
        }

        $d = CarbonImmutable::createFromFormat('Y-m-d', $bucket, self::TZ)->locale('id');

        return $weekly ? 'minggu '.$d->translatedFormat('j M') : $d->translatedFormat('j M');
    }

    /** Hitungan per kategori untuk periode (tanpa filter lain) agar kategori tanpa data tidak ditawarkan. @return array<string, int> */
    public function categoryCounts(array $f): array
    {
        $counts = array_fill_keys(array_keys(AuditEvents::CATEGORIES), 0) + ['gagal' => 0, 'sistem' => 0];

        foreach ($this->base($f, false, false)->reorder()->toBase()->selectRaw('action, count(*) as n')->groupBy('action')->get() as $r) {
            if ($c = AuditEvents::category($r->action)) {
                $counts[$c] += (int) $r->n;
            }

            if (AuditEvents::isFailure($r->action)) {
                $counts['gagal'] += (int) $r->n;
            }

            if (AuditEvents::producer($r->action) === 'sistem') {
                $counts['sistem'] += (int) $r->n;
            }
        }

        return $counts;
    }

    /** @return array{actors: array<int, string>, actions: array<string, string>, entities: array<string, string>} */
    public function options(array $f): array
    {
        $period = $this->base($f, false, false);
        $actors = (clone $period)->reorder()->toBase()->whereNotNull('user_id')->selectRaw('user_id, count(*) as n')->groupBy('user_id')->orderByDesc('n')->limit(100)->pluck('user_id')->all();
        $names = User::query()->whereIn('id', $actors)->pluck('name', 'id');
        $actions = (clone $period)->reorder()->toBase()->distinct()->orderBy('action')->pluck('action')->all();
        $types = (clone $period)->reorder()->toBase()->whereNotNull('auditable_type')->distinct()->pluck('auditable_type')->all();

        $actionOptions = [];

        foreach ($actions as $code) {
            $actionOptions[$code] = ucfirst(AuditEvents::describe($code)['phrase']).' · '.$code;
        }

        asort($actionOptions);
        $entityOptions = [];

        foreach ($types as $type) {
            $entityOptions[$this->entityKey($type)] = $this->entityLabel($type);
        }

        asort($entityOptions);

        return [
            'actors' => collect($actors)->mapWithKeys(fn ($id) => [$id => $names[$id] ?? 'Pengguna #'.$id])->all(),
            'actions' => $actionOptions, 'entities' => $entityOptions,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Daftar
    |--------------------------------------------------------------------------
    */

    public function paginate(array $f): LengthAwarePaginator
    {
        $q = $this->base($f)->with('user:id,name,email');

        match ($f['urut']) {
            'terlama' => $q->orderBy('id'),
            'jenis' => $q->orderBy('action')->orderByDesc('id'),
            default => $q->orderByDesc('id'),
        };

        return $q->paginate(self::PER_PAGE)->withQueryString();
    }

    /**
     * @param  iterable<AuditLog>  $logs
     * @return list<array<string, mixed>>
     */
    public function present(iterable $logs): array
    {
        $logs = collect($logs);
        $live = $this->liveEntities($logs);
        $rows = [];

        foreach ($logs as $log) {
            $rows[] = $this->row($log, $live);
        }

        return $rows;
    }

    /** @return array<string, array<int, string|null>> tipe => [id => nama saat ini | null] untuk objek yang masih ada */
    private function liveEntities(Collection $logs): array
    {
        $live = [];

        foreach ($logs->whereNotNull('auditable_type')->whereNotNull('auditable_id')->groupBy('auditable_type') as $type => $group) {
            if (! class_exists($type) || ! is_subclass_of($type, Model::class)) {
                continue;
            }

            $col = self::ENTITIES[$type][1] ?? null;
            $ids = $group->pluck('auditable_id')->unique()->all();
            $found = $type::query()->whereIn('id', $ids)->get($col ? ['id', $col] : ['id']);
            $live[$type] = $found->mapWithKeys(fn ($m) => [(int) $m->id => $col ? (string) $m->{$col} : null])->all();
        }

        return $live;
    }

    /** @return array<string, mixed> */
    public function row(AuditLog $log, array $live): array
    {
        $meta = is_array($log->metadata_json) ? $log->metadata_json : [];
        $d = AuditEvents::describe($log->action);
        $kind = AuditEvents::actorKind($log->action, $log->user_id);
        $time = CarbonImmutable::parse($log->created_at)->utc()->setTimezone(self::TZ);
        $type = $log->auditable_type;
        $exists = $type && $log->auditable_id && array_key_exists((int) $log->auditable_id, $live[$type] ?? []);
        $name = $this->objectName(AuditEvents::known($log->action) ? $meta : [], $exists ? ($live[$type][(int) $log->auditable_id] ?? null) : null, $log);
        $user = $log->user;
        $actorName = match ($kind) {
            'sistem' => 'Sistem',
            'tidak-diketahui' => 'Tidak diketahui',
            default => $user?->name ?: ($user?->email ?: 'Pengguna #'.$log->user_id),
        };
        $version = $meta['layout_version'] ?? $meta['version'] ?? null;
        $objText = null;

        if ($d['object']) {
            $objText = $name ?: ($type && $log->auditable_id ? $this->entityLabel($type).' #'.$log->auditable_id : null);

            if ($objText && $version && is_numeric($version) && ! str_contains((string) $objText, ' v'.$version)) {
                $objText .= ' v'.(int) $version;
            }
        }

        $failed = AuditEvents::isFailure($log->action);
        $blocked = $log->action === 'source.duplicate_blocked';

        return [
            'id' => $log->id, 'time' => $time, 'timeLabel' => $time->locale('id')->translatedFormat('j M Y, H.i').' WIB',
            'actor' => [
                'name' => $actorName, 'kind' => $kind, 'kindLabel' => AuditEvents::ACTOR_KINDS[$kind], 'email' => $kind === 'admin' || $kind === 'pengguna' ? $user?->email : null,
                'onBehalf' => $kind === 'sistem' && $user ? $user->name : null,
                'url' => $user && in_array($kind, ['admin', 'pengguna'], true) ? route('admin.users.show', $user->id) : null,
                'initials' => $kind === 'sistem' ? 'SI' : ($kind === 'tidak-diketahui' ? '?' : mb_strtoupper(mb_substr($actorName, 0, 2))),
            ],
            'action' => $log->action, 'phrase' => $d['phrase'], 'known' => $d['known'], 'verb' => $d['verb'], 'config' => $d['config'],
            'verbLabel' => AuditEvents::VERBS[$d['verb']],
            'object' => $objText,
            'category' => AuditEvents::category($log->action), 'categoryLabel' => AuditEvents::CATEGORIES[AuditEvents::category($log->action)] ?? 'Lainnya',
            'outcome' => $failed ? 'failed' : ($blocked ? 'blocked' : 'ok'),
            'entity' => [
                'type' => $type, 'label' => $this->entityLabel($type), 'id' => $log->auditable_id, 'name' => $name, 'exists' => $exists, 'isModel' => (bool) ($type && class_exists($type)),
                'url' => $exists ? $this->entityUrl($log, $meta) : ($type && ! $log->auditable_id ? $this->entityUrl($log, $meta) : null),
            ],
            'meta' => $meta,
        ];
    }

    /** Nama objek: nama saat ini bila objek masih ada, jika tidak nama yang tersimpan di metadata saat event. */
    private function objectName(array $meta, ?string $live, AuditLog $log): ?string
    {
        if (filled($live)) {
            return $live;
        }

        foreach (['name', 'title', 'term', 'template', 'original_name', 'code', 'source_name'] as $k) {
            if (isset($meta[$k]) && is_string($meta[$k]) && $meta[$k] !== '') {
                return (new AuditRedactor)->text($meta[$k]);
            }
        }

        return null;
    }

    /** URL halaman admin yang sudah ada untuk objek event; null bila tidak ada halaman untuk objek itu. */
    private function entityUrl(AuditLog $log, array $meta): ?string
    {
        $id = (int) $log->auditable_id;

        return match ($log->auditable_type) {
            AiTool::class => route('admin.tools.edit', $id),
            PromptTemplate::class => str_starts_with($log->action, 'answer_behavior.') ? (filled($meta['rule'] ?? null) ? route('admin.answer-behavior.show', ['rule' => (string) $meta['rule']]) : route('admin.answer-behavior.index')) : route('admin.prompts.edit', $id),
            DocumentPromptTemplate::class => route('admin.prompt-documents.show', $id),
            DocumentTemplate::class => route('admin.document-templates.edit', $id),
            Document::class => route('admin.library.edit', $id),
            Category::class => route('admin.categories.edit', $id),
            SourceCollection::class => route('admin.collections.edit', $id),
            GlossaryTerm::class => route('admin.glossary.edit', $id),
            AiRun::class => route('admin.ai-runs.show', $id),
            User::class => route('admin.users.index', ['pengguna' => $id]),
            'ai_provider' => route('admin.providers.index'),
            'research_settings' => route('admin.settings.index'),
            'App\Models\SourceSyncRun', 'App\Models\SourceSyncItem' => route('admin.source-sync.index'),
            default => null,
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Detail
    |--------------------------------------------------------------------------
    */

    /** @return array<string, mixed> */
    public function detail(AuditLog $log): array
    {
        $log->loadMissing('user:id,name,email');
        $row = $this->present([$log])[0];
        $meta = $row['meta'];
        $red = new AuditRedactor;
        $safe = $red->redact($meta);

        // Email percobaan masuk yang gagal bisa berupa salah ketik password; ditampilkan sebagian saja.
        if ($log->action === 'admin.login.failed' && isset($safe['email'])) {
            $safe['email'] = AuditRedactor::maskEmail((string) $meta['email']);
        }

        $raw = null;
        $rawNote = null;

        if (! AuditEvents::known($log->action)) {
            $rawNote = 'Kode event ini tidak dikenal sistem, sehingga isi metadata mentah tidak ditampilkan agar tidak ada data sensitif yang terbuka.';
        } elseif ($safe !== []) {
            $raw = json_encode($safe, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);

            if (mb_strlen($raw) > 6000) {
                $raw = mb_substr($raw, 0, 6000)."\n… (dipotong)";
                $rawNote = 'Metadata panjang; hanya 6.000 karakter pertama yang ditampilkan.';
            }
        }

        return $row + [
            'timeUtc' => CarbonImmutable::parse($log->created_at)->utc()->format('Y-m-d H:i:s').' UTC',
            'ip' => AuditRedactor::maskIp($log->ip_address),
            'changes' => $this->changes($log->action, $meta, $safe),
            'fields' => AuditEvents::known($log->action) ? $this->fields($safe) : [],
            'metaHidden' => ! AuditEvents::known($log->action) && $meta !== [],
            'raw' => $raw, 'rawNote' => $rawNote,
            'redacted' => $red->redacted,
            'relations' => $this->relations($log, $meta, $row),
            'noMeta' => $meta === [],
        ];
    }

    /** Hanya nilai yang benar-benar tersimpan; sisi yang tidak tercatat ditandai null (ditampilkan sebagai "Tidak dicatat"). */
    private function changes(string $action, array $meta, array $safe): array
    {
        $out = [];
        $label = fn (string $k) => self::LABELS[$k] ?? ucfirst(str_replace('_', ' ', $k));
        $show = fn ($v) => is_bool($v) ? ($v ? 'Ya' : 'Tidak') : (is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : (string) $v);

        if ($action === 'ai.config.updated') {
            foreach ([['Provider', 'before_provider', 'provider'], ['Model', 'before_model', 'model']] as [$field, $b, $a]) {
                if (array_key_exists($b, $meta) && array_key_exists($a, $meta)) {
                    $out[] = ['field' => $field, 'before' => $show($meta[$b]), 'after' => $show($meta[$a]), 'same' => $meta[$b] === $meta[$a]];
                }
            }
        }

        if (str_ends_with($action, '.activated') || $action === 'document_template.layout_activated') {
            $new = $meta['layout_version'] ?? $meta['version'] ?? null;

            if ($new !== null) {
                $prev = $meta['previous_version'] ?? null;
                $out[] = ['field' => 'Versi aktif', 'before' => $prev !== null ? 'v'.$prev : null, 'after' => 'v'.$new, 'same' => false, 'beforeNote' => $prev === null ? 'Tidak ada versi aktif sebelumnya' : null];
            }
        }

        if ($action === 'document_prompt.template.status' && isset($meta['to'])) {
            $out[] = ['field' => 'Status', 'before' => null, 'after' => $show($meta['to']), 'same' => false];
        }

        if ($action === 'settings.updated' && is_array($meta['changed_keys'] ?? null)) {
            foreach ($meta['changed_keys'] as $k) {
                $out[] = ['field' => (string) $k, 'before' => null, 'after' => array_key_exists($k, $safe['settings'] ?? []) ? $show($safe['settings'][$k]) : null, 'same' => false];
            }
        }

        $list = $meta['changed'] ?? null;

        if (is_array($list) && $list !== [] && $action !== 'ai.config.updated') {
            $out[] = ['field' => $label('changed'), 'fields' => array_map('strval', array_values($list)), 'note' => 'Event ini hanya mencatat nama bagian yang berubah; nilai sebelum dan sesudahnya tidak disimpan.'];
        } elseif ($action === 'ai.config.updated' && is_array($list) && $list !== []) {
            $out[] = ['field' => $label('changed'), 'fields' => array_map('strval', array_values($list)), 'note' => 'Hanya provider dan model yang tersimpan nilai sebelumnya; kunci API tidak pernah dicatat.'];
        }

        return $out;
    }

    /** @return list<array{key: string, label: string, value: string}> */
    private function fields(array $safe): array
    {
        $out = [];

        foreach ($safe as $k => $v) {
            if ($k === 'settings') {
                $out[] = ['key' => $k, 'label' => 'Snapshot pengaturan', 'value' => count((array) $v).' nilai (lihat metadata teknis)'];

                continue;
            }

            $text = is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (is_bool($v) ? ($v ? 'Ya' : 'Tidak') : (string) ($v ?? '—'));
            $out[] = ['key' => (string) $k, 'label' => self::LABELS[$k] ?? ucfirst(str_replace('_', ' ', (string) $k)), 'value' => mb_strlen($text) > 300 ? mb_substr($text, 0, 300).'…' : $text];
        }

        return $out;
    }

    /** Relasi hanya dari id yang tersimpan di metadata dan masih ada di database; link memakai route admin yang sudah ada. */
    private function relations(AuditLog $log, array $meta, array $row): array
    {
        $out = [];
        $candidates = [
            ['ai_run_id', AiRun::class, 'Riwayat Riset', fn ($id) => route('admin.ai-runs.show', $id)],
            ['prompt_template_id', PromptTemplate::class, 'Instruksi', fn ($id) => route('admin.prompts.edit', $id)],
            ['previous_prompt_id', PromptTemplate::class, 'Instruksi sebelumnya', fn ($id) => route('admin.prompts.edit', $id)],
            ['ai_tool_id', AiTool::class, 'Alat AI', fn ($id) => route('admin.tools.edit', $id)],
            ['document_id', Document::class, 'Sumber Pustaka', fn ($id) => route('admin.library.edit', $id)],
            ['source_template_id', DocumentPromptTemplate::class, 'Template Prompt Dokumen sumber', fn ($id) => route('admin.prompt-documents.show', $id)],
        ];

        foreach ($candidates as [$key, $model, $label, $url]) {
            if (! isset($meta[$key]) || ! is_numeric($meta[$key])) {
                continue;
            }

            $id = (int) $meta[$key];
            $out[] = ['label' => $label, 'text' => '#'.$id, 'url' => $model::query()->whereKey($id)->exists() ? $url($id) : null];
        }

        if ($log->action === 'ai.config.updated' || $log->action === 'ai.provider.tested') {
            $out[] = ['label' => 'Provider AI', 'text' => 'Provider & Analitik AI', 'url' => route('admin.providers.index')];
        }

        return $out;
    }
}
