<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Collection;
use App\Models\Document;
use App\Services\Research\SourceVersions;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/*
 * Koleksi: pengelompokan tematik dokumen Pustaka (tabel collections + pivot collection_document, unik per pasangan).
 * Satu dokumen boleh ada di banyak koleksi; keanggotaan hanyalah baris pivot, jadi menambah/melepas tidak pernah
 * menyalin atau menghapus dokumen maupun file PDF-nya.
 *
 * Simpan/tambah/lepas menjawab JSON untuk admin-collections.js (tanpa memuat ulang halaman) dan tetap redirect
 * untuk form biasa.
 */
class CollectionController extends Controller
{
    private const SORTS = ['updated', 'name', 'sources'];

    private const MEMBER_LIMIT = 200; // shortcut: panel anggota memuat maksimal 200 sumber, naikkan dengan paginasi bila koleksi lebih besar

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:160'],
            'status' => ['nullable', 'in:active,archived'],
            'sort' => ['nullable', Rule::in(self::SORTS)],
        ]);

        $query = Collection::query()->withCount('documents');

        if (filled($filters['search'] ?? null)) {
            $like = '%'.addcslashes(trim((string) $filters['search']), '%_\\').'%';
            $query->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('description', 'like', $like));
        }

        if (filled($filters['status'] ?? null)) {
            $query->where('status', $filters['status']);
        }

        match ($filters['sort'] ?? 'updated') {
            'name' => $query->orderBy('name')->orderBy('id'),
            'sources' => $query->orderByDesc('documents_count')->orderBy('name'),
            default => $query->orderByDesc('updated_at')->orderByDesc('id'),
        };

        $newest = Collection::query()->latest('created_at')->latest('id')->first(['id', 'name', 'created_at']);

        return view('admin.collections.index', [
            'collections' => $query->paginate(20)->withQueryString(),
            'filtering' => filled($filters['search'] ?? null) || filled($filters['status'] ?? null) || in_array($filters['sort'] ?? null, ['name', 'sources'], true),
            'stats' => [
                'total' => Collection::query()->count(),
                'members' => (int) DB::table('collection_document')->distinct()->count('document_id'),
                'newest' => $newest,
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.collections.form', [
            'collection' => new Collection,
            'documents' => $this->documents(),
            'selectedDocuments' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $this->validated($request);
        $this->refuseDuplicateName($request, $data['name']);

        $ids = $this->takeDocumentIds($request, $data);
        $data['slug'] = $this->uniqueSlug($data['name']);

        $collection = Collection::create($data);

        if ($ids !== null) {
            $collection->documents()->sync($ids);
        }

        $this->audit($request, 'collection.created', $collection, ['name' => $collection->name, 'status' => $collection->status]);

        return $this->done($request, 'Koleksi "'.$collection->name.'" dibuat.', $collection, redirect()->route('admin.collections.edit', $collection));
    }

    public function edit(Collection $collection): View
    {
        return view('admin.collections.form', [
            'collection' => $collection,
            'documents' => $this->documents(),
            'selectedDocuments' => $collection->documents()->pluck('documents.id')->all(),
        ]);
    }

    public function update(Request $request, Collection $collection): RedirectResponse|JsonResponse
    {
        $data = $this->validated($request);
        $this->refuseDuplicateName($request, $data['name'], $collection->id);

        $ids = $this->takeDocumentIds($request, $data);

        // Slug tetap saat nama berubah: tautan yang memakainya tidak boleh putus diam-diam.
        $collection->fill($data);
        $changed = array_keys($collection->getDirty());
        $collection->save();

        if ($ids !== null) {
            $collection->documents()->sync($ids);
        }

        $this->audit($request, 'collection.updated', $collection, ['changed' => $changed, 'name' => $collection->name, 'status' => $collection->status]);

        return $this->done($request, 'Koleksi "'.$collection->name.'" diperbarui.', $collection, redirect()->route('admin.collections.edit', $collection));
    }

    /*
    |--------------------------------------------------------------------------
    | Anggota koleksi
    |--------------------------------------------------------------------------
    */

    /** Isi panel yang dibuka: sumber di koleksi + riwayat versi dari Pustaka (SourceVersions). */
    public function members(Collection $collection, SourceVersions $versions): View
    {
        $documents = $collection->documents()->with('category')->orderBy('title')->limit(self::MEMBER_LIMIT)->get();

        return view('admin.collections.partials.members', [
            'collection' => $collection,
            'documents' => $documents,
            'histories' => $versions->histories($documents),
            'total' => $collection->documents()->count(),
        ]);
    }

    /** Sumber Pustaka yang belum ada di koleksi ini (yang sudah ada tidak ditawarkan, jadi tidak bisa ditambahkan dua kali). */
    public function candidates(Request $request, Collection $collection): View
    {
        $input = $request->validate(['q' => ['nullable', 'string', 'max:160']]);

        $query = Document::query()
            ->with('category')
            ->whereNotIn('id', $collection->documents()->select('documents.id'))
            ->orderBy('title')
            ->orderBy('id');

        if (filled($input['q'] ?? null)) {
            $like = '%'.addcslashes(trim((string) $input['q']), '%_\\').'%';
            $query->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('doi', 'like', $like));
        }

        return view('admin.collections.partials.candidates', [
            'collection' => $collection,
            'candidates' => $query->simplePaginate(12)->withQueryString(),
            'searching' => filled($input['q'] ?? null),
        ]);
    }

    public function attach(Request $request, Collection $collection): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'document_ids' => ['required', 'array', 'min:1', 'max:100'],
            'document_ids.*' => ['integer', 'distinct', 'exists:documents,id'],
        ], [
            'document_ids.required' => 'Pilih minimal satu sumber.',
            'document_ids.min' => 'Pilih minimal satu sumber.',
        ]);

        $result = DB::transaction(function () use ($collection, $data) {
            $already = $collection->documents()->whereIn('documents.id', $data['document_ids'])->pluck('documents.id')->all();
            $new = array_values(array_diff($data['document_ids'], $already));

            // Pasangan unik di database + syncWithoutDetaching: tidak ada baris ganda, dan tidak ada yang terlepas.
            $collection->documents()->syncWithoutDetaching($new);
            $collection->touch();

            return ['added' => count($new), 'skipped' => count($already)];
        });

        $this->audit($request, 'collection.sources_added', $collection, $result + ['document_ids' => $data['document_ids']]);

        $message = $result['added'].' sumber ditambahkan ke "'.$collection->name.'".'
            .($result['skipped'] ? ' '.$result['skipped'].' sudah ada dan dilewati.' : '');

        return $this->done($request, $message, $collection, redirect()->route('admin.collections.edit', $collection), $result);
    }

    public function detach(Request $request, Collection $collection, Document $document): RedirectResponse|JsonResponse
    {
        if (! $collection->documents()->whereKey($document->id)->exists()) {
            return $this->failed($request, 'Sumber ini sudah tidak ada di koleksi.', 409);
        }

        // Hanya baris pivot yang dihapus. Dokumen, file PDF, versi, dan koleksi lain tetap utuh.
        $collection->documents()->detach($document->id);
        $collection->touch();

        $this->audit($request, 'collection.source_removed', $collection, ['document_id' => $document->id, 'title' => $document->title]);

        return $this->done($request, '"'.$document->title.'" dilepas dari koleksi. Dokumennya tetap ada di Pustaka.', $collection, redirect()->route('admin.collections.edit', $collection));
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function validated(Request $request): array
    {
        $request->merge(['name' => trim((string) preg_replace('/\s+/u', ' ', (string) $request->input('name')))]);

        return $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', 'in:active,archived'],
            'sync_documents' => ['nullable', 'boolean'],
            'document_ids' => ['nullable', 'array'],
            'document_ids.*' => ['integer', 'exists:documents,id'],
        ], [
            'name.required' => 'Nama koleksi wajib diisi.',
            'name.max' => 'Nama koleksi maksimal 180 karakter.',
            'description.max' => 'Deskripsi maksimal 2.000 karakter.',
            'status.in' => 'Status tidak valid.',
        ]);
    }

    /**
     * Daftar centang pada halaman form biasa menyinkronkan keanggotaan; dialog di daftar koleksi hanya menyimpan
     * nama/deskripsi/status dan TIDAK boleh menyentuh anggota. Karena itu sinkronisasi hanya jika diminta eksplisit.
     */
    private function takeDocumentIds(Request $request, array &$data): ?array
    {
        $ids = ($data['sync_documents'] ?? false) ? array_map('intval', $data['document_ids'] ?? []) : null;

        unset($data['document_ids'], $data['sync_documents']);

        return $ids;
    }

    private function refuseDuplicateName(Request $request, string $name, ?int $ignore = null): void
    {
        $needle = mb_strtolower($name);
        $same = Collection::query()
            ->when($ignore, fn ($q) => $q->whereKeyNot($ignore))
            ->get(['id', 'name'])
            ->first(fn (Collection $c) => mb_strtolower($c->name) === $needle);

        if (! $same) {
            return;
        }

        $message = 'Koleksi "'.$same->name.'" sudah ada.';

        if ($request->expectsJson()) {
            throw new HttpResponseException(response()->json([
                'message' => $message,
                'errors' => ['name' => [$message]],
                'existing' => ['id' => $same->id, 'name' => $same->name, 'url' => route('admin.collections.edit', $same)],
            ], 422));
        }

        throw ValidationException::withMessages(['name' => $message]);
    }

    private function documents()
    {
        return Document::query()->with('category')->orderBy('title')->get();
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'koleksi';
        $slug = $base;
        $counter = 2;

        while (Collection::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }

    private function done(Request $request, string $message, Collection $collection, RedirectResponse $redirect, array $extra = []): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'message' => $message, 'id' => $collection->id, 'count' => $collection->documents()->count()] + $extra);
        }

        return $redirect->with('success', $message);
    }

    private function failed(Request $request, string $message, int $status): RedirectResponse|JsonResponse
    {
        return $request->expectsJson()
            ? response()->json(['ok' => false, 'message' => $message], $status)
            : back()->withErrors(['collection' => $message]);
    }

    private function audit(Request $request, string $action, Collection $collection, array $metadata): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        AuditLog::create([
            'user_id' => $request->session()->get('research_admin_user_id'),
            'action' => $action,
            'auditable_type' => Collection::class,
            'auditable_id' => $collection->id,
            'metadata_json' => $metadata,
            'ip_address' => $request->ip(),
        ]);
    }
}
