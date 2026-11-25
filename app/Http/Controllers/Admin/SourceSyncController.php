<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\SourceDuplicateException;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\SourceSyncItem;
use App\Models\SourceSyncRun;
use App\Services\Research\Sync\SourceConnectorManager;
use App\Services\Research\Sync\SourceSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Throwable;

/*
 * Impor Sumber. Scan, impor, dan abaikan menjawab JSON untuk admin-sync.js (tanpa memuat ulang halaman) dan
 * tetap berupa redirect untuk form biasa. Aturan bisnis ada di SourceSyncService; di sini hanya HTTP, kunci,
 * audit, dan data tampilan.
 */
class SourceSyncController extends Controller
{
    /** status filter => kondisi; 'all' tanpa kondisi. */
    private const FILTERS = [
        'pending' => ['new', 'changed'],
        'new' => ['new'],
        'changed' => ['changed'],
        'unchanged' => ['unchanged'],
        'imported' => ['imported'],
        'ignored' => ['ignored'],
        'failed' => ['failed'],
    ];

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'run' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:all,'.implode(',', array_keys(self::FILTERS))],
            'q' => ['nullable', 'string', 'max:160'],
        ]);

        $runs = SourceSyncRun::query()
            ->with('initiatedBy:id,name,email')
            ->latest('id')
            ->paginate(8, pageName: 'runs_page')
            ->withQueryString();

        $selectedRun = filled($filters['run'] ?? null) ? SourceSyncRun::query()->find((int) $filters['run']) : null;
        $selectedRun ??= SourceSyncRun::query()->latest('id')->first();

        $counts = array_fill_keys(['all', ...array_keys(self::FILTERS)], 0);
        $items = null;
        $status = $filters['status'] ?? null;
        $duplicates = [];

        if ($selectedRun) {
            $byStatus = $selectedRun->items()->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');

            foreach (self::FILTERS as $key => $statuses) {
                $counts[$key] = (int) collect($statuses)->sum(fn ($s) => $byStatus[$s] ?? 0);
            }

            $counts['all'] = (int) $byStatus->sum();
            $status ??= $counts['pending'] > 0 ? 'pending' : 'all';

            $query = $selectedRun->items()->with([
                'matchedDocument' => fn ($q) => $q->select('id', 'title', 'status', 'doi', 'publication_year', 'language', 'publisher', 'abstract', 'source_label')->withCount('files'),
                'importedDocument:id,title,status',
                'category:id,name',
            ]);

            if (isset(self::FILTERS[$status])) {
                $query->whereIn('status', self::FILTERS[$status]);
            }

            if (filled($filters['q'] ?? null)) {
                $like = '%'.addcslashes(trim((string) $filters['q']), '%_\\').'%';
                $query->where(fn ($q) => $q->where('title', 'like', $like)
                    ->orWhere('external_id', 'like', $like)
                    ->orWhere('payload_json->metadata->doi', 'like', $like));
            }

            $items = $query
                ->orderByRaw("CASE status WHEN 'new' THEN 1 WHEN 'changed' THEN 2 WHEN 'failed' THEN 3 WHEN 'imported' THEN 4 WHEN 'ignored' THEN 5 ELSE 6 END")
                ->orderBy('title')
                ->paginate(25)
                ->withQueryString();

            $sync = app(SourceSyncService::class);

            foreach ($items as $item) {
                if (in_array($item->status, ['new', 'changed'], true)) {
                    $duplicates[$item->id] = $sync->duplicateOf($item);
                }
            }
        }

        $provider = (string) config('source-sync.provider', 'mock');

        return view('admin.source-sync.index', [
            'provider' => $provider,
            'host' => $provider === 'mock' ? null : (parse_url((string) config('source-sync.connectors.'.$provider.'.base_url'), PHP_URL_HOST) ?: null),
            'autoPublish' => (bool) config('source-sync.auto_publish', false),
            'lastRun' => SourceSyncRun::query()->whereNotNull('finished_at')->latest('id')->first(),
            'runs' => $runs,
            'selectedRun' => $selectedRun,
            'items' => $items,
            'counts' => $counts,
            'status' => $status ?? 'all',
            'search' => (string) ($filters['q'] ?? ''),
            'duplicates' => $duplicates,
            'categories' => Category::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function scan(Request $request, SourceSyncService $sync): RedirectResponse|JsonResponse
    {
        // Satu scan pada satu waktu: klik ganda atau dua tab tidak membuat dua run.
        $lock = Cache::lock('source-sync-scan', 120);

        if (! $lock->get()) {
            return $this->reply($request, 'Scan sedang berjalan. Tunggu sampai selesai.', 409);
        }

        try {
            $adminId = $request->session()->get('research_admin_user_id');
            $run = $sync->scan($adminId ? (int) $adminId : null);

            $this->audit($request, 'source_sync.scanned', SourceSyncRun::class, $run->id, [
                'connector' => $run->connector,
                'status' => $run->status,
                'discovered_count' => $run->discovered_count,
                'new_count' => $run->new_count,
                'changed_count' => $run->changed_count,
                'unchanged_count' => $run->unchanged_count,
            ]);

            return $this->reply(
                $request,
                'Scan selesai. '.$run->discovered_count.' sumber ditemukan; '.$run->new_count.' baru dan '.$run->changed_count.' berubah.',
                200,
                ['run' => $run->id],
                $run->id,
            );
        } catch (Throwable $exception) {
            report($exception);

            return $this->reply($request, $exception->getMessage() ?: 'Scan gagal.', 500);
        } finally {
            $lock->release();
        }
    }

    public function import(Request $request, SourceSyncItem $syncItem, SourceSyncService $sync): RedirectResponse|JsonResponse
    {
        $validated = $request->validate(['category_id' => ['required', 'integer', 'exists:categories,id']], [
            'category_id.required' => 'Pilih kategori sebelum mengimpor.',
            'category_id.exists' => 'Kategori tidak ditemukan.',
        ]);

        // Double-click atau dua permintaan bersamaan untuk item yang sama: hanya satu yang berjalan.
        $lock = Cache::lock('source-sync-item-'.$syncItem->id, 60);

        if (! $lock->get()) {
            return $this->reply($request, 'Item ini sedang diproses.', 409, [], $syncItem->source_sync_run_id);
        }

        try {
            $adminId = $request->session()->get('research_admin_user_id');
            $document = $sync->import($syncItem, (int) $validated['category_id'], $adminId ? (int) $adminId : null);

            $this->audit($request, 'source_sync.imported', SourceSyncItem::class, $syncItem->id, [
                'connector' => $syncItem->connector,
                'external_id' => $syncItem->external_id,
                'document_id' => $document->id,
                'document_status' => $document->status,
                'chunks' => $document->chunks()->count(),
            ]);

            return $this->reply(
                $request,
                'Diimpor ke Pustaka dengan status Siap terbit. Sumber belum terbit; tinjau lalu terbitkan dari Pustaka.',
                200,
                ['document_url' => route('admin.library.edit', $document)],
                $syncItem->source_sync_run_id,
            );
        } catch (SourceDuplicateException $exception) {
            return $this->reply($request, $exception->getMessage(), 409, [
                'duplicate' => ['kind' => $exception->kind, 'title' => $exception->match->title, 'url' => route('admin.library.edit', $exception->match)],
            ], $syncItem->source_sync_run_id);
        } catch (Throwable $exception) {
            report($exception);

            return $this->reply($request, $exception->getMessage(), 409, [], $syncItem->source_sync_run_id);
        } finally {
            $lock->release();
        }
    }

    public function ignore(Request $request, SourceSyncItem $syncItem, SourceSyncService $sync): RedirectResponse|JsonResponse
    {
        $lock = Cache::lock('source-sync-item-'.$syncItem->id, 60);

        if (! $lock->get()) {
            return $this->reply($request, 'Item ini sedang diproses.', 409, [], $syncItem->source_sync_run_id);
        }

        try {
            $adminId = $request->session()->get('research_admin_user_id');
            $item = $sync->ignore($syncItem, $adminId ? (int) $adminId : null);

            $this->audit($request, 'source_sync.ignored', SourceSyncItem::class, $item->id, [
                'connector' => $item->connector,
                'external_id' => $item->external_id,
            ]);

            return $this->reply($request, 'Item diabaikan. Pustaka tidak berubah.', 200, [], $item->source_sync_run_id);
        } catch (Throwable $exception) {
            report($exception);

            return $this->reply($request, $exception->getMessage(), 409, [], $syncItem->source_sync_run_id);
        } finally {
            $lock->release();
        }
    }

    private function reply(Request $request, string $message, int $status = 200, array $extra = [], ?int $run = null): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['ok' => $status < 400, 'message' => $message] + $extra, $status);
        }

        $redirect = redirect()->route('admin.source-sync.index', array_filter(['run' => $run]));

        return $status < 400 ? $redirect->with('success', $message) : $redirect->withErrors(['source_sync' => $message]);
    }

    private function audit(Request $request, string $action, string $auditableType, ?int $auditableId, array $metadata = []): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        AuditLog::create([
            'user_id' => $request->session()->get('research_admin_user_id'),
            'action' => $action,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'metadata_json' => $metadata,
            'ip_address' => $request->ip(),
        ]);
    }
}
