<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessSourceIngestion;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Services\Research\SourceIngestionService;
use App\Services\Research\SourceVersions;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class LibraryController extends Controller
{
    private const SORTS = ['newest', 'oldest', 'updated', 'title'];

    /*
    |--------------------------------------------------------------------------
    | Index
    |--------------------------------------------------------------------------
    */

    public function index(Request $request, SourceVersions $versions): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:160'],
            'category' => ['nullable', 'integer', 'exists:categories,id'],
            'status' => ['nullable', 'in:draft,processing,ready,published,failed,archived'],
            'type' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', 'in:'.implode(',', self::SORTS)],
        ]);

        $documents = Document::query()
            ->with('category')
            ->withCount(['files', 'chunks']);

        if (filled($filters['search'] ?? null)) {
            $search = trim((string) $filters['search']);
            $like = '%'.addcslashes($search, '%_\\').'%';

            // Judul, DOI, atau nama file asli (versi mana pun).
            $documents->where(function ($query) use ($like) {
                $query->where('title', 'like', $like)
                    ->orWhere('doi', 'like', $like)
                    ->orWhereHas('files', fn ($files) => $files->where('original_name', 'like', $like));
            });
        }

        foreach (['category' => 'category_id', 'status' => 'status', 'type' => 'document_type'] as $input => $column) {
            if (filled($filters[$input] ?? null)) {
                $documents->where($column, $filters[$input]);
            }
        }

        match ($filters['sort'] ?? 'newest') {
            'oldest' => $documents->orderBy('id'),
            'updated' => $documents->orderByDesc('updated_at')->orderByDesc('id'),
            'title' => $documents->orderBy('title')->orderBy('id'),
            default => $documents->orderByDesc('id'),
        };

        $page = $documents->paginate(20)->withQueryString();

        $types = Document::query()->whereNotNull('document_type')->distinct()->orderBy('document_type')->pluck('document_type');

        return view('admin.library.index', [
            'documents' => $page,
            'histories' => $versions->histories(collect($page->items())),
            'categories' => $this->categories(),
            'types' => $types->count() > 1 ? $types : collect(),
            'stats' => [
                'total' => Document::query()->count(),
                'published' => Document::query()->where('status', 'published')->count(),
                'processing' => Document::query()->where('status', 'processing')->count(),
                'categories' => Category::query()->where('status', 'active')->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.library.form', [
            'document' => new Document,
            'categories' => $this->categories(),
            'latestFile' => null,
            'chunkCount' => 0,
            'history' => [],
        ]);
    }

    public function edit(Document $document, SourceVersions $versions): View
    {
        return view('admin.library.form', [
            'document' => $document->load('category'),
            'categories' => $this->categories(),
            'latestFile' => $document->files()->latest('id')->first(),
            'chunkCount' => $document->chunks()->count(),
            'history' => $versions->history($document, true),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | New source
    |--------------------------------------------------------------------------
    |
    | Upload selesai cepat; ekstraksi dan chunking dikerjakan queue worker.
    | Pemeriksaan duplikat + pembuatan sumber berjalan dalam satu kunci, jadi dua unggahan
    | bersamaan tidak bisa sama-sama lolos pemeriksaan.
    |
    */

    public function store(Request $request, SourceIngestionService $ingestion, SourceVersions $versions): RedirectResponse|JsonResponse
    {
        $data = $this->validateData($request, true);
        $upload = $request->file('source_file');
        $hash = (string) hash_file('sha256', $upload->getRealPath());
        $doi = $versions->normalizeDoi($data['doi'] ?? null);

        return $this->locked($request, function () use ($request, $data, $hash, $doi, $ingestion, $versions) {
            $found = $versions->inspect($hash, $doi);

            if ($found['exact']) {
                return $this->duplicate($request, 'exact_duplicate', $found['exact'], $hash);
            }

            if ($found['same_source']) {
                $chosen = $request->input('duplicate_decision') === 'new_version'
                    && (int) $request->input('duplicate_target') === $found['same_source']['id'];

                if (! $chosen) {
                    return $this->duplicate($request, 'same_source', $found['same_source'], $hash);
                }

                // Admin memilih: file ini menjadi versi baru dari sumber yang sudah ada.
                return $this->stageVersion($request, Document::query()->findOrFail($found['same_source']['id']), $ingestion, 'new_version_same_doi');
            }

            $document = Document::create([
                'uuid' => (string) Str::uuid(),
                'title' => trim($data['title']),
                'category_id' => $data['category_id'],
                'doi' => $doi,
                'document_type' => 'pdf',
                'language' => 'id',
                'source_label' => 'Curated Source',
                'visibility' => 'public',
                'status' => 'draft',
                'uploaded_by' => $request->session()->get('research_admin_user_id'),
                'published_at' => null,
                'processing_stage' => null,
                'processing_progress' => 0,
                'processing_file_id' => null,
                'processing_error' => null,
                'processing_started_at' => null,
                'processing_finished_at' => null,
            ]);

            try {
                $sourceFile = $ingestion->stageUpload($document, $request->file('source_file'));
            } catch (Throwable $exception) {
                report($exception);
                $this->audit($request, 'source.ingestion_stage_failed', $document, ['message' => $exception->getMessage()]);

                // Tanpa file berarti tidak ada yang layak disimpan: hindari sumber kosong yatim.
                if (! $document->files()->exists()) {
                    $document->delete();
                }

                return $this->stop($request, $exception->getMessage());
            }

            $this->dispatchIngestion($document, $sourceFile->id, $request);
            $this->audit($request, 'source.ingestion_queued', $document, [
                'document_file_id' => $sourceFile->id,
                'original_name' => $sourceFile->original_name,
            ]);

            return $this->done(
                $request,
                route('admin.library.edit', $document),
                'PDF berhasil disimpan dan masuk antrean ingestion. Halaman akan diperbarui otomatis sampai proses selesai.'
            );
        });
    }

    /** Pemeriksaan duplikat sebelum unggah (hash dihitung browser); keputusan akhir tetap di store()/update(). */
    public function checkDuplicate(Request $request, SourceVersions $versions): JsonResponse
    {
        $data = $request->validate([
            'sha256' => ['nullable', 'string', 'regex:/^[a-f0-9]{64}$/i'],
            'doi' => ['nullable', 'string', 'max:190'],
            'document_id' => ['nullable', 'integer', 'exists:documents,id'],
        ]);

        $found = $versions->inspect(
            isset($data['sha256']) ? strtolower($data['sha256']) : null,
            $data['doi'] ?? null,
            isset($data['document_id']) ? Document::query()->find($data['document_id']) : null
        );

        return response()->json($found + ['message' => $found['exact'] ? $this->exactMessage($found['exact']) : null])
            ->header('Cache-Control', 'private, no-store');
    }

    /*
    |--------------------------------------------------------------------------
    | Existing source
    |--------------------------------------------------------------------------
    */

    public function update(Request $request, Document $document, SourceIngestionService $ingestion, SourceVersions $versions): RedirectResponse|JsonResponse
    {
        $data = $this->validateData($request, false, $document);
        $action = $data['action'] ?? 'save';

        $document->update([
            'title' => trim($data['title']),
            'category_id' => $data['category_id'],
            'doi' => $versions->normalizeDoi($data['doi'] ?? null),
            'document_type' => 'pdf',
            'visibility' => 'public',
        ]);

        $edit = route('admin.library.edit', $document);

        try {
            if ($action === 'archive') {
                $ingestion->transitionStatus($document, 'archived');
                $this->audit($request, 'source.archive', $document);

                return $this->done($request, $edit, 'Source berhasil diarsipkan.');
            }

            // File baru = versi baru: yang lama tetap tersimpan (superseded), yang baru diproses di background.
            if ($request->hasFile('source_file')) {
                $hash = (string) hash_file('sha256', $request->file('source_file')->getRealPath());

                return $this->locked($request, function () use ($request, $document, $hash, $ingestion, $versions) {
                    $found = $versions->inspect($hash, null, $document);

                    if ($found['exact']) {
                        return $this->duplicate($request, 'exact_duplicate', $found['exact'], $hash);
                    }

                    return $this->stageVersion($request, $document, $ingestion, 'replacement_upload');
                });
            }

            if ($action === 'reprocess') {
                $sourceFile = $ingestion->queueExistingFile($document);

                $this->dispatchIngestion($document, $sourceFile->id, $request);
                $this->audit($request, 'source.reprocess_queued', $document, ['document_file_id' => $sourceFile->id]);

                return $this->done($request, $edit, 'Proses ulang masuk antrean. Halaman akan diperbarui otomatis.');
            }

            if ($action === 'publish') {
                $ingestion->transitionStatus($document, 'published');
                $this->audit($request, 'source.publish', $document, ['chunks' => $document->chunks()->count()]);

                return $this->done($request, $edit, 'Source berhasil dipublish dan sekarang tersedia untuk retrieval.');
            }

            if ($action === 'unpublish') {
                $ingestion->transitionStatus($document, 'ready');
                $this->audit($request, 'source.unpublish', $document);

                return $this->done($request, $edit, 'Source dikembalikan ke status Ready.');
            }

            $this->audit($request, 'source.save', $document);

            return $this->done($request, $edit, 'Metadata source berhasil diperbarui.');
        } catch (Throwable $exception) {
            report($exception);
            $this->audit($request, 'source.'.$action.'_failed', $document, ['message' => $exception->getMessage()]);

            return $this->stop($request, $exception->getMessage());
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Versions: view, download, restore
    |--------------------------------------------------------------------------
    |
    | File ada di disk privat; hanya route admin ini yang menyajikannya (tanpa URL publik).
    |
    */

    public function viewFile(DocumentFile $file): Response
    {
        return $this->stream($file, false);
    }

    public function downloadFile(Request $request, DocumentFile $file): Response
    {
        $document = Document::query()->find($file->document_id);

        if ($document) {
            $this->audit($request, 'source.file_downloaded', $document, ['document_file_id' => $file->id]);
        }

        return $this->stream($file, true);
    }

    /** Pulihkan = buat versi baru dari isi versi lama. Riwayat dan file lama tidak berubah. */
    public function restore(Request $request, Document $document, DocumentFile $file, SourceIngestionService $ingestion, SourceVersions $versions): RedirectResponse|JsonResponse
    {
        abort_unless($file->document_id === $document->id, 404);

        $edit = route('admin.library.edit', $document);

        if ($file->status !== 'superseded') {
            return $this->stop($request, 'Hanya versi sebelumnya yang dapat dipulihkan.', 422, ['redirect' => $edit]);
        }

        if ($document->status === 'processing') {
            return $this->stop($request, 'Sumber sedang diproses. Tunggu sampai selesai sebelum memulihkan versi.', 422, ['redirect' => $edit]);
        }

        return $this->locked($request, function () use ($request, $document, $file, $ingestion, $versions, $edit) {
            try {
                $number = collect($versions->history($document))->firstWhere('id', $file->id)['label'] ?? 'versi lama';
                $restored = $ingestion->stageRestore($document, $file);

                $this->dispatchIngestion($document, $restored->id, $request);
                $this->audit($request, 'source.version_restored', $document, [
                    'document_file_id' => $restored->id,
                    'restored_from_file_id' => $file->id,
                    'restored_from_version' => $number,
                ]);
            } catch (Throwable $exception) {
                report($exception);

                return $this->stop($request, $exception->getMessage(), 422, ['redirect' => $edit]);
            }

            return $this->done($request, $edit, 'Versi '.$number.' dipulihkan sebagai versi baru dan sedang diproses. Riwayat versi tetap utuh; sumber perlu dipublikasikan lagi setelah selesai.');
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function stageVersion(Request $request, Document $document, SourceIngestionService $ingestion, string $reason): RedirectResponse|JsonResponse
    {
        $sourceFile = $ingestion->stageUpload($document, $request->file('source_file'));

        $this->dispatchIngestion($document, $sourceFile->id, $request);
        $this->audit($request, 'source.ingestion_queued', $document, [
            'document_file_id' => $sourceFile->id,
            'original_name' => $sourceFile->original_name,
            'reason' => $reason,
        ]);

        return $this->done(
            $request,
            route('admin.library.edit', $document),
            'PDF baru tersimpan sebagai versi baru dan sedang diproses di background. Versi sebelumnya tetap tersimpan.'
        );
    }

    /** Satu pintu unggah/pulihkan pada satu waktu: pemeriksaan duplikat dan penulisan tidak boleh berselang-seling. */
    private function locked(Request $request, Closure $callback): RedirectResponse|JsonResponse
    {
        $lock = Cache::lock('library-upload', 120);

        try {
            $lock->block(max(1, (int) config('research.ingestion.upload_lock_wait', 15)));
        } catch (LockTimeoutException) {
            return $this->stop($request, 'Ada unggahan lain yang sedang diperiksa. Coba lagi sebentar lagi.', 503);
        }

        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }

    private function duplicate(Request $request, string $code, array $match, string $hash): RedirectResponse|JsonResponse
    {
        $message = $code === 'exact_duplicate'
            ? $this->exactMessage($match)
            : 'Sumber dengan DOI yang sama sudah ada ("'.$match['title'].'"), tetapi file yang diunggah berbeda. Pilih tindakan berikutnya.';

        $this->audit($request, 'source.duplicate_blocked', Document::query()->findOrFail($match['id']), [
            'reason' => $code,
            'sha256' => $hash,
        ]);

        return $this->stop($request, $message, 409, ['code' => $code, 'match' => $match]);
    }

    private function exactMessage(array $match): string
    {
        if ($match['same_target'] ?? false) {
            return $match['is_active_version']
                ? 'File ini sama persis dengan versi aktif ('.$match['version'].') sumber ini. Tidak ada yang diunggah.'
                : 'File ini sama persis dengan '.$match['version'].' sumber ini (versi sebelumnya). Pakai Pulihkan pada riwayat versi bila ingin menggunakannya lagi.';
        }

        return 'File ini sama persis dengan file sumber "'.$match['title'].'" ('.$match['version'].'). Sumber baru tidak dibuat.';
    }

    private function stream(DocumentFile $file, bool $download): Response
    {
        $disk = Storage::disk($file->disk);

        abort_unless($disk->exists($file->path), 404);

        $name = Str::of($file->original_name ?: 'sumber.pdf')->replaceMatches('/[\r\n"\\\\\/]+/', '-')->toString();
        $headers = [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ];

        return $download ? $disk->download($file->path, $name, $headers) : $disk->response($file->path, $name, $headers);
    }

    /** Sukses: pesan flash dibawa ke halaman berikutnya; XHR menerima URL tujuan, form biasa di-redirect. */
    private function done(Request $request, string $url, string $message): RedirectResponse|JsonResponse
    {
        $request->session()->flash('success', $message);

        return $request->expectsJson() ? response()->json(['redirect' => $url, 'message' => $message]) : redirect($url);
    }

    private function stop(Request $request, string $message, int $status = 422, array $extra = []): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'errors' => ['source_file' => [$message]]] + $extra, $status);
        }

        return back()->withInput()->withErrors(['source_file' => $message]);
    }

    private function categories()
    {
        return Category::query()->where('status', 'active')->orderBy('name')->get();
    }

    private function dispatchIngestion(Document $document, int $documentFileId, Request $request): void
    {
        ProcessSourceIngestion::dispatch(
            $document->id,
            $documentFileId,
            'ready',
            $request->session()->get('research_admin_user_id')
        )->afterCommit();
    }

    private function validateData(Request $request, bool $creating, ?Document $document = null): array
    {
        $maxUpload = max(1024, (int) config('research.ingestion.max_upload_kb', 102400));

        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'doi' => [
                'nullable',
                'string',
                'max:190',
                'regex:/^(?:https?:\/\/(?:dx\.)?doi\.org\/|doi:\s*)?10\.\d{4,9}\/\S+$/i',
                // Saat mengedit, DOI tidak boleh bentrok dengan sumber lain. Saat membuat, bentrokan ditangani lewat dialog keputusan.
                function ($attribute, $value, $fail) use ($document) {
                    $other = $document ? app(SourceVersions::class)->findByDoi($value, $document->id) : null;

                    if ($other) {
                        $fail('DOI ini sudah dipakai sumber "'.$other->title.'".');
                    }
                },
            ],
            'source_file' => [$creating ? 'required' : 'nullable', 'file', 'mimes:pdf', 'max:'.$maxUpload],
            'action' => ['nullable', 'in:save,reprocess,publish,unpublish,archive'],
            'duplicate_decision' => ['nullable', 'in:new_version'],
            'duplicate_target' => ['nullable', 'integer'],
        ], [
            'source_file.max' => 'Ukuran PDF maksimal '.number_format($maxUpload / 1024, 0).' MB.',
            'source_file.mimes' => 'File harus berformat PDF.',
            'doi.regex' => 'Format DOI tidak valid. Contoh: 10.1234/abcd.5678',
        ]);
    }

    private function audit(Request $request, string $action, Document $document, array $metadata = []): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        AuditLog::create([
            'user_id' => $request->session()->get('research_admin_user_id'),
            'action' => $action,
            'auditable_type' => Document::class,
            'auditable_id' => $document->id,
            'metadata_json' => $metadata,
            'ip_address' => $request->ip(),
        ]);
    }
}
