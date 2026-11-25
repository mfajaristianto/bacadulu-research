<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\ExternalCategoryMapping;
use App\Models\ExternalPublication;
use App\Models\ExternalPublicationCategory;
use App\Models\ExternalSyncRun;
use App\Services\ExternalSources\CapacityReport;
use App\Services\ExternalSources\DoiResolutionCheck;
use App\Services\ExternalSources\ExternalSourceException;
use App\Services\ExternalSources\ExternalSourceRegistry;
use App\Services\ExternalSources\ExternalSourceSettings;
use App\Services\ExternalSources\ExternalSyncService;
use App\Services\ExternalSources\PublicationSearch;
use App\Services\ExternalSources\SyncScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/*
 * Sumber Akademik Eksternal. Hanya HTTP, validasi, audit, dan data tampilan. Aturan sinkronisasi ada di
 * ExternalSyncService, komunikasi penyedia di adapter, penyimpanan/dedup di PublicationUpserter. Halaman tetap
 * terbuka (read-only) bila tabel belum dimigrasi; Sync dinonaktifkan dengan alasan.
 */
class ExternalSourceController extends Controller
{
    private const STATUS_LABELS = [
        'queued' => 'Antre', 'running' => 'Berjalan', 'rate_limited' => 'Dibatasi laju', 'waiting' => 'Menunggu giliran', 'paused' => 'Dijeda',
        'completed' => 'Selesai', 'partial' => 'Sebagian', 'failed' => 'Gagal', 'cancelled' => 'Dibatalkan',
    ];

    private const MODE_LABELS = [
        'quick' => 'Quick', 'category' => 'Per kategori', 'all_mapped' => 'Semua kategori terpetakan', 'corpus' => 'Korpus penyedia', 'incremental' => 'Incremental',
    ];

    private const STOP_LABELS = [
        'records' => 'Target rekaman tercapai', 'exhausted' => 'Seluruh hasil penyedia sudah dibaca', 'requests' => 'Batas permintaan tercapai', 'minutes' => 'Batas durasi tercapai',
        'disk' => 'Ruang disk di bawah ambang', 'memory' => 'Memori di atas ambang', 'paused' => 'Dijeda admin', 'emergency' => 'Henti darurat',
    ];

    public function __construct(
        private ExternalSourceRegistry $registry,
        private ExternalSourceSettings $settings,
        private ExternalSyncService $sync,
        private PublicationSearch $search,
    ) {}

    public function index(Request $request): View
    {
        $f = $request->validate([
            'q' => ['nullable', 'string', 'max:160'], 'provider' => ['nullable', 'string', 'max:40'],
            'category' => ['nullable', 'integer'], 'year' => ['nullable', 'integer', 'between:1000,2200'],
            'type' => ['nullable', Rule::in([...SyncScope::TYPES, 'other'])], 'language' => ['nullable', 'string', 'max:12'],
            'oa' => ['nullable', Rule::in(['open', 'closed', 'unknown'])],
            'availability' => ['nullable', Rule::in(['METADATA_ONLY', 'ABSTRACT_AVAILABLE', 'OPEN_ACCESS_LINK', 'RESTRICTED', 'UNKNOWN'])],
            'review' => ['nullable', Rule::in(['suggested', 'verified', 'none'])], 'history' => ['nullable', 'string', 'max:40'],
            'quality' => ['nullable', Rule::in(['missing_authors', 'missing_year', 'missing_doi', 'missing_abstract'])],
        ]);

        $ready = $this->ready();
        $categories = Category::query()->orderBy('name')->get(['id', 'name']);

        $directory = collect($this->registry->directory())->map(function (array $c) use ($ready) {
            $s = $c['implemented'] ? $this->settings->get($c['code']) : null;
            $runs = $ready && $c['implemented'] ? ExternalSyncRun::query()->where('provider', $c['code']) : null;
            $c['settings'] = $s ? collect($s)->except('api_key')->all() : null;
            $c['connection'] = $c['implemented'] ? $this->settings->lastConnection($c['code']) : null;
            $c['records'] = $ready && $c['implemented'] ? DB::table('external_publication_sources')->where('provider', $c['code'])->count() : 0;
            $c['last_ok'] = $runs ? (clone $runs)->whereIn('status', ['completed', 'partial'])->latest('finished_at')->first() : null;
            $c['last_failed'] = $runs ? (clone $runs)->whereIn('status', ['failed', 'partial'])->latest('id')->first() : null;
            $c['active'] = $runs ? (clone $runs)->whereIn('status', ExternalSyncRun::OPEN)->latest('id')->first() : null;
            $c['preview_blocker'] = $c['implemented'] ? $this->sync->blocker($c['code']) : 'Adapter belum dibangun untuk sumber ini.';
            $c['blocker'] = ! $ready ? 'Tabel penyimpanan belum dimigrasi (menunggu persetujuan).' : ($c['implemented'] ? $this->sync->blocker($c['code']) : 'Adapter belum dibangun untuk sumber ini.');
            $c['mode_blockers'] = $c['implemented'] ? collect(SyncScope::MODES)->mapWithKeys(fn ($m) => [$m => $this->sync->modeBlocker($c['code'], $m)])->filter()->all() : [];

            return $c;
        })->all();

        $data = [
            'ready' => $ready, 'directory' => $directory, 'categories' => $categories, 'statusLabels' => self::STATUS_LABELS,
            'modeLabels' => self::MODE_LABELS, 'stopLabels' => self::STOP_LABELS,
            'filters' => $f, 'types' => SyncScope::TYPES, 'emergency' => $this->settings->emergencyStopped(),
            'limits' => ['quick' => (int) config('external-sources.limits.quick_max_records'), 'campaign' => (int) config('external-sources.limits.campaign_max_records'),
                'requests' => (int) config('external-sources.budget.max_requests'), 'minutes' => (int) config('external-sources.budget.max_minutes')],
        ] + ($ready ? $this->dataset($f) : $this->emptyDataset());

        return view('admin.external-sources.index', $data);
    }

    public function settings(Request $request, string $provider): RedirectResponse|JsonResponse
    {
        $this->adapterOrFail($provider);
        $data = $request->validate([
            'enabled' => ['nullable', 'boolean'], 'contact' => ['nullable', 'email', 'max:120'],
            'api_key' => ['nullable', 'string', 'max:200', 'regex:/^[\w\-\.]+$/'], 'clear_api_key' => ['nullable', 'boolean'],
        ]);

        $this->settings->save($provider, $data, $this->adminId($request));
        // Kunci tidak pernah masuk audit; hanya fakta bahwa ia diubah.
        $this->audit($request, 'external_source.settings_updated', null, ['provider' => $provider, 'enabled' => (bool) ($data['enabled'] ?? false), 'api_key_changed' => filled($data['api_key'] ?? null) || ! empty($data['clear_api_key'])]);

        return $this->done($request, 'Pengaturan '.$this->registry->adapter($provider)->info()['name'].' disimpan.');
    }

    public function test(Request $request, string $provider): JsonResponse
    {
        $adapter = $this->adapterOrFail($provider);
        $started = microtime(true);

        try {
            $result = $adapter->ping($this->settings->forAdapter($provider));
        } catch (Throwable) {
            $result = ['ok' => false, 'message' => 'Tes koneksi gagal karena galat tak terduga.'];
        }

        $ms = (int) round((microtime(true) - $started) * 1000);
        $this->settings->recordConnection($provider, $result['ok'], $result['message'], $ms);
        $this->audit($request, 'external_source.connection_tested', null, ['provider' => $provider, 'ok' => $result['ok']]);

        return response()->json($result + ['latency_ms' => $ms]);
    }

    public function preview(Request $request, string $provider): JsonResponse
    {
        $this->adapterOrFail($provider);

        try {
            $scope = $this->scopeFrom($request, $provider);
            $result = $this->sync->preview($scope);
        } catch (ExternalSourceException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], $e->kind === 'rate_limited' ? 429 : 422);
        }

        $this->audit($request, 'external_source.previewed', null, ['provider' => $provider, 'category_id' => $scope->categoryId, 'mode' => $scope->mode, 'count' => count($result['records'])]);

        return response()->json(['ok' => true, 'html' => view('admin.external-sources.partials.preview', $result + ['provider' => $provider])->render()]);
    }

    /** Memulai satu kampanye (mode quick / category / corpus / incremental) untuk satu penyedia. */
    public function startSync(Request $request, string $provider): RedirectResponse|JsonResponse
    {
        $this->adapterOrFail($provider);
        $this->requireReady();

        try {
            $scope = $this->scopeFrom($request, $provider, forRun: true);
            $run = $this->sync->start($scope, $this->adminId($request));
            $this->maybeSaveMapping($request, $scope);
        } catch (ExternalSourceException $e) {
            return $this->fail($request, $e->getMessage());
        }

        $this->sync->schedule($run);
        $this->audit($request, 'external_source.sync_started', $run->id, ['provider' => $provider] + $scope->toArray());

        return $this->done($request, 'Sinkronisasi #'.$run->id.' ('.self::MODE_LABELS[$scope->mode].') dimasukkan ke antrean.', ['run' => $run->id]);
    }

    /**
     * Mode "semua kategori terpetakan": satu run per pemetaan yang valid, dijalankan BERURUTAN dalam satu grup (bukan serentak),
     * dengan total rekaman seluruh grup dibatasi oleh batas kampanye.
     */
    public function syncAll(Request $request): RedirectResponse|JsonResponse
    {
        $this->requireReady();
        $data = $request->validate([
            'category_id' => ['nullable', 'integer', 'exists:categories,id'], 'provider' => ['nullable', Rule::in(['all', ...array_keys($this->registry->adapters())])],
            'max_records' => ['nullable', 'integer', 'min:1', 'max:'.config('external-sources.limits.campaign_max_records')],
            // Batas GLOBAL seluruh kampanye: WAJIB. Dicek di sini (jumlah batas semua run) dan dipaksakan lagi tiap batch saat eksekusi.
            'max_total' => ['required', 'integer', 'min:1', 'max:'.config('external-sources.limits.campaign_max_records')],
            'max_requests' => ['nullable', 'integer', 'min:1', 'max:'.config('external-sources.budget.max_requests')],
            'max_minutes' => ['nullable', 'integer', 'min:1', 'max:'.config('external-sources.budget.max_minutes')],
            'year_from' => ['nullable', 'integer', 'between:1000,2200'], 'year_to' => ['nullable', 'integer', 'between:1000,2200', 'gte:year_from'],
        ]);

        $providers = ($data['provider'] ?? 'all') === 'all' ? array_keys($this->registry->adapters()) : [$data['provider']];
        $scopes = [];
        $skipped = [];

        foreach ($providers as $code) {
            $mappings = ExternalCategoryMapping::query()->where('provider', $code)->where('enabled', true)
                ->when(filled($data['category_id'] ?? null), fn ($q) => $q->where('category_id', $data['category_id']))->orderBy('category_id')->get();

            if ($reason = $this->sync->blocker($code)) {
                $skipped[$code] = $reason;
            } elseif ($mappings->isEmpty()) {
                $skipped[$code] = 'Belum ada pemetaan istilah untuk kategori ini.';
            } else {
                foreach ($mappings as $m) {
                    $scopes[] = SyncScope::fromArray(['provider' => $code, 'category_id' => $m->category_id, 'terms' => $m->terms_json, 'mode' => 'all_mapped',
                        'max_records' => $data['max_records'] ?? $m->max_records, 'year_from' => $data['year_from'] ?? null, 'year_to' => $data['year_to'] ?? null,
                        'max_requests' => $data['max_requests'] ?? null, 'max_minutes' => $data['max_minutes'] ?? null, 'group_cap' => $data['max_total']]
                        + collect($m->filters_json ?? [])->only(['language', 'type', 'open_access_only'])->all());
                }
            }
        }

        // Tiap run dibatasi max_records-nya sendiri (dipaksakan di loop), jadi total kampanye <= jumlah batas run. Batas global operator
        // (max_total) diperiksa terhadap jumlah itu SEBELUM run dibuat; tanpa itu hanya batas kampanye keras yang berlaku.
        $cap = (int) config('external-sources.limits.campaign_max_records');
        $sum = array_sum(array_map(fn (SyncScope $s) => $s->maxRecords, $scopes));
        if ($sum > (int) $data['max_total']) {
            return $this->fail($request, "Total rekaman seluruh kampanye ({$sum}) melebihi batas global {$data['max_total']}. Isi 'Batas per run' atau turunkan pemetaan; tidak ada run yang dibuat.");
        }
        if ($sum > $cap) {
            return $this->fail($request, "Total rekaman seluruh kampanye ({$sum}) melebihi batas kampanye {$cap}. Kurangi batas per run atau jumlah pemetaan.");
        }

        try {
            $runs = $scopes === [] ? [] : $this->sync->startGroup($scopes, $this->adminId($request));
        } catch (ExternalSourceException $e) {
            return $this->fail($request, $e->getMessage());
        }

        if ($runs) {
            $this->sync->schedule($runs[0]);
        }

        $ids = array_map(fn ($r) => $r->id, $runs);
        $this->audit($request, 'external_source.sync_all_started', null, ['category_id' => $data['category_id'] ?? null, 'provider' => $data['provider'] ?? 'all', 'runs' => $ids, 'skipped' => array_keys($skipped), 'total_records' => $sum, 'max_total' => $data['max_total'], 'max_requests' => $data['max_requests'] ?? null, 'max_minutes' => $data['max_minutes'] ?? null]);
        $message = count($ids).' sinkronisasi dibuat (berurutan)'.($skipped ? ', '.count($skipped).' dilewati ('.implode(', ', array_keys($skipped)).')' : '').'.';

        return $ids ? $this->done($request, $message, ['runs' => $ids, 'skipped' => $skipped]) : $this->fail($request, 'Tidak ada sumber yang bisa dijalankan. '.collect($skipped)->map(fn ($r, $c) => "{$c}: {$r}")->implode(' '));
    }

    public function pause(Request $request, ExternalSyncRun $run): RedirectResponse|JsonResponse
    {
        $this->sync->pause($run) && $this->audit($request, 'external_source.sync_paused', $run->id, ['provider' => $run->provider]);

        return $this->done($request, 'Sinkronisasi #'.$run->id.' dijeda. Checkpoint tersimpan.');
    }

    public function cancel(Request $request, ExternalSyncRun $run): RedirectResponse|JsonResponse
    {
        $this->sync->cancel($run) && $this->audit($request, 'external_source.sync_cancelled', $run->id, ['provider' => $run->provider]);

        return $this->done($request, 'Sinkronisasi #'.$run->id.' dibatalkan.');
    }

    public function resume(Request $request, ExternalSyncRun $run): RedirectResponse|JsonResponse
    {
        if ((! in_array($run->status, ['paused', 'rate_limited', 'partial', 'failed'], true) && ! $run->isStale()) || ! $run->checkpoint_json) {
            return $this->fail($request, 'Run ini tidak bisa dilanjutkan.');
        }

        try {
            $this->sync->resume($run);
        } catch (ExternalSourceException $e) {
            return $this->fail($request, $e->getMessage());
        }

        $this->audit($request, 'external_source.sync_resumed', $run->id, ['provider' => $run->provider]);

        return $this->done($request, 'Sinkronisasi #'.$run->id.' dilanjutkan dari checkpoint (jendela anggaran baru).');
    }

    /** Aksi atas seluruh grup kampanye (semua kategori terpetakan). */
    public function groupAction(Request $request, string $group, string $action): RedirectResponse|JsonResponse
    {
        abort_unless(in_array($action, ['pause', 'resume', 'cancel'], true) && ExternalSyncRun::query()->where('scope_json->group', $group)->exists(), 404);

        try {
            match ($action) {
                'pause' => $this->sync->pauseGroup($group),
                'resume' => $this->sync->resumeGroup($group),
                'cancel' => $this->sync->cancelGroup($group),
            };
        } catch (ExternalSourceException $e) {
            return $this->fail($request, $e->getMessage());
        }

        $this->audit($request, match ($action) {
            'pause' => 'external_source.group_paused',
            'resume' => 'external_source.group_resumed',
            'cancel' => 'external_source.group_cancelled',
        }, null, ['group' => $group]);

        return $this->done($request, ['pause' => 'Kampanye dijeda.', 'resume' => 'Kampanye dilanjutkan.', 'cancel' => 'Kampanye dibatalkan.'][$action]);
    }

    public function emergencyStop(Request $request): RedirectResponse|JsonResponse
    {
        $n = $this->sync->emergencyStop($this->adminId($request));
        $this->audit($request, 'external_source.emergency_stop', null, ['paused_runs' => $n]);

        return $this->done($request, "Henti darurat aktif: {$n} run dijeda. Tidak ada sinkronisasi baru sampai dilepas.");
    }

    public function emergencyRelease(Request $request): RedirectResponse|JsonResponse
    {
        $this->sync->emergencyRelease($this->adminId($request));
        $this->audit($request, 'external_source.emergency_release', null);

        return $this->done($request, 'Henti darurat dilepas. Run yang dijeda tetap menunggu Lanjutkan manual.');
    }

    /** Subjek (set LCC) DOAJ untuk korpus/incremental; dicari berdasarkan nama, maks 25. */
    public function sets(Request $request, string $provider): JsonResponse
    {
        $adapter = $this->adapterOrFail($provider);
        abort_unless(method_exists($adapter, 'listSets'), 404);
        $q = mb_strtolower(trim((string) $request->query('q', '')));

        try {
            $sets = collect($adapter->listSets($this->settings->forAdapter($provider)))
                ->filter(fn ($s) => $q === '' || str_contains(mb_strtolower($s['name']), $q))->take(25)->values();
        } catch (ExternalSourceException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'sets' => $sets]);
    }

    public function since(Request $request, string $provider): JsonResponse
    {
        $this->adapterOrFail($provider);

        return response()->json(['since' => $this->ready() ? $this->sync->suggestedSince($provider, $request->integer('category_id') ?: null, $request->query('set')) : null]);
    }

    /** Polling progres ringan: hanya run terbuka dan yang baru berubah; angka dari worker nyata. */
    public function status(): JsonResponse
    {
        if (! $this->ready()) {
            return response()->json(['runs' => [], 'active' => false, 'emergency' => false]);
        }

        $runs = ExternalSyncRun::query()->where(fn ($q) => $q->whereIn('status', ExternalSyncRun::OPEN)->orWhere('updated_at', '>=', now()->subMinutes(2)))
            ->latest('id')->limit(30)->get();

        return response()->json([
            'runs' => $runs->map(fn ($r) => $this->runPayload($r))->values(),
            'active' => $runs->contains(fn ($r) => $r->isActive()),
            'emergency' => $this->settings->emergencyStopped(),
        ]);
    }

    public function saveMapping(Request $request): RedirectResponse|JsonResponse
    {
        $this->requireReady();
        $data = $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'], 'provider' => ['required', Rule::in(array_keys($this->registry->adapters()))],
            'terms' => ['required', 'string', 'max:1000'], 'max_records' => ['nullable', 'integer', 'min:1', 'max:'.config('external-sources.limits.campaign_max_records')],
        ]);

        $terms = $this->termsFrom($data['terms']);
        if ($terms === []) {
            return $this->fail($request, 'Isi minimal satu istilah yang valid.');
        }

        ExternalCategoryMapping::query()->updateOrCreate(
            ['category_id' => $data['category_id'], 'provider' => $data['provider']],
            ['terms_json' => $terms, 'max_records' => $data['max_records'] ?? config('external-sources.limits.default_max_records'), 'enabled' => true]
        );
        $this->audit($request, 'external_source.mapping_saved', null, ['category_id' => $data['category_id'], 'provider' => $data['provider'], 'terms' => $terms]);

        return $this->done($request, 'Pemetaan kategori disimpan.');
    }

    public function deleteMapping(Request $request, ExternalCategoryMapping $mapping): RedirectResponse|JsonResponse
    {
        $this->audit($request, 'external_source.mapping_deleted', $mapping->id, ['category_id' => $mapping->category_id, 'provider' => $mapping->provider]);
        $mapping->delete();

        return $this->done($request, 'Pemetaan dihapus.');
    }

    public function show(ExternalPublication $publication, DoiResolutionCheck $doi): View
    {
        return view('admin.external-sources.partials.record', [
            'p' => $publication->load(['sources', 'categoryLinks.category']), 'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'doiCheck' => $publication->doi ? $doi->cached($publication->doi) : null,
        ]);
    }

    /** Memeriksa SATU DOI ke doi.org (dibatasi kuota, di-cache): membedakan format valid dari benar-benar terdaftar. */
    public function doiCheck(Request $request, ExternalPublication $publication, DoiResolutionCheck $doi): JsonResponse
    {
        abort_unless($publication->doi, 422, 'Rekaman ini tidak punya DOI.');
        $result = $doi->check($publication->doi, $request->boolean('force'));
        $this->audit($request, 'external_source.doi_checked', $publication->id, ['status' => $result['status'], 'cached' => $result['cached']]);

        return response()->json(['ok' => true] + $result + ['label' => ['registered' => 'DOI terdaftar di doi.org', 'not_found' => 'DOI TIDAK terdaftar di doi.org', 'unknown' => 'Belum bisa dipastikan (coba lagi nanti)', 'throttled' => 'Kuota pemeriksaan per jam habis'][$result['status']]]);
    }

    /** Verifikasi atau pindahkan kategori sebuah publikasi (keputusan admin, bukan AI). */
    public function classify(Request $request, ExternalPublication $publication): RedirectResponse|JsonResponse
    {
        $data = $request->validate(['category_id' => ['required', 'integer', 'exists:categories,id'], 'from_category_id' => ['nullable', 'integer']]);

        DB::transaction(function () use ($publication, $data, $request): void {
            if (filled($data['from_category_id'] ?? null) && (int) $data['from_category_id'] !== (int) $data['category_id']) {
                ExternalPublicationCategory::query()->where('publication_id', $publication->id)->where('category_id', $data['from_category_id'])->delete();
            }
            ExternalPublicationCategory::query()->updateOrCreate(
                ['publication_id' => $publication->id, 'category_id' => $data['category_id']],
                ['status' => 'verified', 'verified_by' => $this->adminId($request), 'reason' => 'Diverifikasi admin.']
            );
        });
        $this->audit($request, 'external_source.category_verified', $publication->id, ['category_id' => $data['category_id'], 'from' => $data['from_category_id'] ?? null]);

        return $this->done($request, 'Kategori publikasi diverifikasi.');
    }

    /* ----------------------------------------------------------------------------------------- helpers */

    private function scopeFrom(Request $request, string $provider, bool $forRun = false): SyncScope
    {
        $supported = $this->registry->adapter($provider)->info();
        $mode = $request->input('mode', 'category');
        $bulk = in_array($mode, ['corpus', 'incremental'], true);
        $cap = (int) config('external-sources.limits.campaign_max_records');

        $data = $request->validate([
            'mode' => ['nullable', Rule::in(['quick', 'category', 'corpus', 'incremental'])],
            'category_id' => [$forRun && ! $bulk ? 'required' : 'nullable', 'integer', 'exists:categories,id'],
            'terms' => [$bulk ? 'nullable' : 'required', 'string', 'max:1000'],
            'set' => ['nullable', 'string', 'max:200'], 'set_label' => ['nullable', 'string', 'max:160'],
            'since' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'year_from' => ['nullable', 'integer', 'between:1000,2200'], 'year_to' => ['nullable', 'integer', 'between:1000,2200', 'gte:year_from'],
            'language' => ['nullable', 'string', 'regex:/^[a-zA-Z]{2,3}$/'],
            'type' => ['nullable', Rule::in($supported['types'])],
            'open_access_only' => ['nullable', 'boolean'],
            'max_records' => ['nullable', 'integer', 'min:1', "max:{$cap}"],
            'max_requests' => ['nullable', 'integer', 'min:1', 'max:'.config('external-sources.budget.max_requests')],
            'max_minutes' => ['nullable', 'integer', 'min:1', 'max:'.config('external-sources.budget.max_minutes')],
        ]);

        // Filter yang tidak didukung penyedia/mode tidak pernah diteruskan: ditolak jelas, bukan diabaikan diam-diam.
        foreach (['language' => 'language', 'open_access_only' => 'oa'] as $field => $capability) {
            if (filled($data[$field] ?? null) && $data[$field] !== '0' && ! in_array($capability, $supported['filters'], true)) {
                throw new ExternalSourceException("{$supported['name']} tidak mendukung filter ".($capability === 'oa' ? 'open access' : 'bahasa').'.', 'bad_request');
            }
        }
        if ($mode === 'corpus' && collect(['year_from', 'year_to', 'language', 'type', 'open_access_only'])->contains(fn ($k) => filled($data[$k] ?? null) && $data[$k] !== '0')) {
            throw new ExternalSourceException('Mode korpus hanya mendukung subjek (set); filter tahun, bahasa, tipe, dan open access tidak tersedia pada jalur OAI-PMH.', 'bad_request');
        }

        return SyncScope::fromArray(['provider' => $provider, 'mode' => $mode, 'terms' => $this->termsFrom($data['terms'] ?? '')] + $data);
    }

    /** @return array<int, string> */
    private function termsFrom(string $raw): array
    {
        return collect(preg_split('/[\r\n,;]+/', $raw) ?: [])->map(fn ($t) => SyncScope::cleanTerm($t))->filter()->unique()->take(10)->values()->all();
    }

    private function maybeSaveMapping(Request $request, SyncScope $scope): void
    {
        if ($request->boolean('save_mapping') && $scope->categoryId && $scope->terms !== [] && $scope->mode !== 'incremental') {
            ExternalCategoryMapping::query()->updateOrCreate(
                ['category_id' => $scope->categoryId, 'provider' => $scope->provider],
                ['terms_json' => $scope->terms, 'max_records' => $scope->maxRecords, 'enabled' => true,
                    'filters_json' => array_filter(['language' => $scope->language, 'type' => $scope->type, 'open_access_only' => $scope->openAccessOnly ?: null])]
            );
        }
    }

    private function adapterOrFail(string $provider)
    {
        abort_unless($this->registry->has($provider), 404);

        return $this->registry->adapter($provider);
    }

    private function adminId(Request $request): ?int
    {
        return $request->session()->get('research_admin_user_id');
    }

    private function ready(): bool
    {
        try {
            return Schema::hasTable('external_publications') && Schema::hasTable('external_sync_runs') && Schema::hasTable('external_category_mappings');
        } catch (Throwable) {
            return false;
        }
    }

    private function requireReady(): void
    {
        abort_unless($this->ready(), 503, 'Tabel sumber eksternal belum dimigrasi.');
    }

    /** Payload progres satu run. Persentase/ETA HANYA bila total penyedia diketahui (target) dan ada laju nyata; selain itu null. */
    private function runPayload(ExternalSyncRun $r): array
    {
        $cp = $r->checkpoint_json ?? [];
        $sc = $r->scope_json ?? [];
        $timeline = $cp['timeline'] ?? [];
        $target = $cp['target'] ?? null;
        $percent = $target ? (int) min(100, round($r->processed_count / max(1, $target) * 100)) : null;

        $eta = null;
        if ($target && $r->isActive() && count($timeline) >= 4) {
            $win = array_slice($timeline, -10);
            $secs = strtotime(end($win)['at']) - strtotime($win[0]['at']);
            $recs = array_sum(array_column(array_slice($win, 1), 'n'));
            $remaining = max(0, $target - $r->processed_count);
            $eta = $secs > 0 && $recs > 0 ? (int) ceil($remaining / ($recs / $secs) / 60) : null;
        }

        return [
            'id' => $r->id, 'provider' => $r->provider, 'status' => $r->status, 'stale' => $r->isStale(), 'label' => $r->isStale() ? 'Tampak macet' : (self::STATUS_LABELS[$r->status] ?? $r->status), 'active' => $r->isActive(),
            'open' => $r->isOpen(), 'mode' => $sc['mode'] ?? 'category', 'group' => $sc['group'] ?? null,
            'found' => $r->found_count, 'processed' => $r->processed_count, 'new' => $r->new_count, 'updated' => $r->updated_count,
            'duplicate' => $r->duplicate_count, 'skipped' => $r->skipped_count, 'failed' => $r->failed_count,
            'max' => $sc['max_records'] ?? null, 'target' => $target, 'percent' => $percent, 'eta_minutes' => $eta,
            'requests' => $cp['requests'] ?? 0, 'max_requests' => $sc['max_requests'] ?? null, 'batches' => $cp['batches'] ?? 0,
            'minutes' => round(($cp['active_seconds'] ?? 0) / 60, 1), 'max_minutes' => $sc['max_minutes'] ?? null,
            'stop_reason' => $cp['stop_reason'] ?? null, 'stop_label' => self::STOP_LABELS[$cp['stop_reason'] ?? ''] ?? null,
            'spark' => array_values(array_map(fn ($b) => (int) $b['n'], array_slice($timeline, -24))),
            // Run yang sudah selesai tidak menampilkan pesan lama (anggaran/percobaan ulang) yang menyesatkan.
            'error' => $r->status === 'completed' ? null : (collect($r->errors_json ?? [])->last()['message'] ?? null),
        ];
    }

    private function done(Request $request, string $message, array $extra = []): RedirectResponse|JsonResponse
    {
        return $request->expectsJson() ? response()->json(['ok' => true, 'message' => $message] + $extra) : back()->with('success', $message);
    }

    private function fail(Request $request, string $message): RedirectResponse|JsonResponse
    {
        return $request->expectsJson() ? response()->json(['ok' => false, 'message' => $message], 422) : back()->withErrors(['external' => $message]);
    }

    private function audit(Request $request, string $action, ?int $id, array $metadata = []): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        AuditLog::create([
            'user_id' => $this->adminId($request), 'action' => $action,
            'auditable_type' => 'external_source', 'auditable_id' => $id, 'metadata_json' => $metadata, 'ip_address' => $request->ip(),
        ]);
    }

    private function emptyDataset(): array
    {
        return ['kpi' => ['publications' => 0, 'sources' => 0, 'with_doi' => 0, 'runs' => 0, 'active' => 0, 'categories' => 0], 'charts' => ['provider' => [], 'category' => [], 'growth' => [], 'years' => [], 'status' => [], 'flow' => [], 'ingest' => []],
            'records' => null, 'runs' => null, 'mappings' => collect(), 'activeRuns' => collect(), 'campaigns' => [], 'classification' => null, 'quality' => null, 'capacity' => null];
    }

    private function dataset(array $f): array
    {
        $growth = ExternalPublication::query()->where('created_at', '>=', now()->subDays(29)->startOfDay())->get(['created_at'])
            ->groupBy(fn ($p) => $p->created_at->format('Y-m-d'))->map->count();
        $days = collect(range(29, 0))->map(fn ($i) => now()->subDays($i)->format('Y-m-d'));
        $running = ExternalPublication::query()->where('created_at', '<', now()->subDays(29)->startOfDay())->count();
        $series = [];
        foreach ($days as $d) {
            // Kumulatif: arrow fn menangkap variabel per nilai, jadi dikerjakan dengan loop biasa.
            $running += (int) ($growth[$d] ?? 0);
            $series[] = ['date' => $d, 'value' => $running];
        }

        $runs = ExternalSyncRun::query()->with('category:id,name', 'initiator:id,name')->when(filled($f['history'] ?? null), fn ($q) => $q->where('provider', $f['history']))
            ->latest('id')->paginate(8, pageName: 'runs_page')->withQueryString();

        return [
            'kpi' => [
                'publications' => ExternalPublication::query()->count(),
                'sources' => DB::table('external_publication_sources')->count(),
                'with_doi' => ExternalPublication::query()->whereNotNull('doi')->count(),
                'runs' => ExternalSyncRun::query()->count(),
                'active' => ExternalSyncRun::query()->whereIn('status', ExternalSyncRun::ACTIVE)->count(),
                'categories' => DB::table('external_publication_categories')->distinct()->count('category_id'),
            ],
            'charts' => [
                'provider' => DB::table('external_publication_sources')->select('provider', DB::raw('count(*) as total'))->groupBy('provider')->orderByDesc('total')->pluck('total', 'provider')->all(),
                'category' => DB::table('external_publication_categories as c')->join('categories as k', 'k.id', '=', 'c.category_id')->select('k.name', DB::raw('count(*) as total'))
                    ->groupBy('k.name')->orderByDesc('total')->limit(8)->pluck('total', 'name')->all(),
                'growth' => $series,
                'years' => ExternalPublication::query()->whereNotNull('publication_year')->select('publication_year', DB::raw('count(*) as total'))->groupBy('publication_year')
                    ->orderByDesc('publication_year')->limit(12)->pluck('total', 'publication_year')->sortKeys()->all(),
                'status' => ExternalSyncRun::query()->select('status', DB::raw('count(*) as total'))->groupBy('status')->pluck('total', 'status')->all(),
                'flow' => ExternalSyncRun::query()->select('provider', DB::raw('sum(new_count) as new_total'), DB::raw('sum(updated_count) as updated_total'), DB::raw('sum(duplicate_count) as duplicate_total'))
                    ->groupBy('provider')->get()->keyBy('provider')->map(fn ($r) => ['new' => (int) $r->new_total, 'updated' => (int) $r->updated_total, 'duplicate' => (int) $r->duplicate_total])->all(),
                'ingest' => $this->ingestSeries(),
            ],
            'records' => $this->search->query($f)->with(['sources:id,publication_id,provider', 'categoryLinks.category:id,name'])->latest('id')->paginate(10, pageName: 'records_page')->withQueryString(),
            'runs' => $runs,
            'mappings' => ExternalCategoryMapping::query()->with('category:id,name')->orderBy('category_id')->get(),
            'activeRuns' => ExternalSyncRun::query()->whereIn('status', ExternalSyncRun::ACTIVE)->latest('id')->get(),
            'campaigns' => $this->campaigns(),
            'classification' => $this->classification(),
            'quality' => $this->qualityReport(),
            'capacity' => app(CapacityReport::class)->report(),
        ];
    }

    /** Rekaman per jam (24 jam terakhir) dari timeline batch nyata. Kosong bila belum ada batch tercatat. */
    private function ingestSeries(): array
    {
        $buckets = [];
        foreach (ExternalSyncRun::query()->where('updated_at', '>=', now()->subHours(24))->get(['checkpoint_json']) as $run) {
            foreach (($run->checkpoint_json['timeline'] ?? []) as $b) {
                $h = substr((string) ($b['at'] ?? ''), 0, 13);
                $buckets[$h] = ($buckets[$h] ?? 0) + (int) ($b['n'] ?? 0);
            }
        }
        ksort($buckets);

        return collect($buckets)->map(fn ($n, $h) => ['hour' => $h, 'value' => $n])->values()->all();
    }

    /** Klasifikasi: sudah punya kategori, disarankan (belum ada yang diverifikasi), diverifikasi admin, belum diklasifikasi. */
    private function classification(): array
    {
        $total = ExternalPublication::query()->count();
        $classified = ExternalPublication::query()->whereHas('categoryLinks')->count();
        $verified = ExternalPublication::query()->whereHas('categoryLinks', fn ($q) => $q->where('status', 'verified'))->count();

        return ['total' => $total, 'classified' => $classified, 'verified' => $verified, 'suggested' => $classified - $verified, 'unclassified' => $total - $classified];
    }

    /** Pelaporan kualitas data tersimpan (sinyal untuk ditinjau admin, tidak ada yang dibuang). */
    private function qualityReport(): array
    {
        $q = fn () => ExternalPublication::query();

        return [
            'total' => $q()->count(),
            'missing_authors' => $q()->where(fn ($w) => $w->whereNull('authors_json')->orWhere('authors_json', '[]'))->count(),
            'missing_year' => $q()->whereNull('publication_year')->count(),
            'missing_doi' => $q()->whereNull('doi')->count(),
            'missing_abstract' => $q()->where(fn ($w) => $w->whereNull('abstract')->orWhere('abstract', ''))->count(),
            'types' => $q()->select('publication_type', DB::raw('count(*) as n'))->groupBy('publication_type')->pluck('n', 'publication_type')->all(),
            'availability' => $q()->select('availability', DB::raw('count(*) as n'))->groupBy('availability')->pluck('n', 'availability')->all(),
        ];
    }

    /** Kartu kampanye: run terbuka + yang baru selesai (2 jam), digabung per grup. */
    private function campaigns(): array
    {
        $runs = ExternalSyncRun::query()->with('category:id,name')->where(fn ($q) => $q->whereIn('status', ExternalSyncRun::OPEN)->orWhere('updated_at', '>=', now()->subHours(2)))
            ->orderBy('id')->limit(60)->get();

        $campaigns = $runs->groupBy(fn ($r) => $r->scope_json['group'] ?? 'run-'.$r->id)->map(function ($g, $key) {
            $items = $g->map(fn ($r) => $this->runPayload($r) + ['category' => $r->category?->name])->values();
            $statuses = $items->pluck('status');
            $status = match (true) {
                $statuses->contains(fn ($s) => in_array($s, ExternalSyncRun::ACTIVE, true)) => 'running',
                $statuses->contains('paused') => 'paused',
                $statuses->contains('waiting') => 'waiting',
                default => $items->last()['status'],
            };

            return [
                'key' => $key, 'group' => str_starts_with($key, 'run-') ? null : $key, 'status' => $status, 'label' => self::STATUS_LABELS[$status] ?? $status,
                'mode' => $items->first()['mode'], 'runs' => $items->all(),
                'processed' => $items->sum('processed'), 'new' => $items->sum('new'), 'updated' => $items->sum('updated'), 'duplicate' => $items->sum('duplicate'), 'failed' => $items->sum('failed'),
                'max' => $items->sum('max'), 'open' => $items->contains('open', true),
            ];
        })->values();

        // Semua kampanye terbuka (urut lama -> baru) lalu maksimal 5 yang baru selesai (terbaru dulu): halaman tetap ringkas.
        $open = $campaigns->filter(fn ($c) => $c['open'])->values();
        $done = $campaigns->reject(fn ($c) => $c['open'])->sortByDesc(fn ($c) => collect($c['runs'])->max('id'))->take(5)->values();

        return $open->concat($done)->all();
    }
}
