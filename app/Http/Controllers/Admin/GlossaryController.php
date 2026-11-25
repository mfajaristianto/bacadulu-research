<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\GlossaryTerm;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

/*
 * Glosarium. Tabel glossary_terms: term, slug (unik), definition, aliases_json (array), context_note, category_id
 * (null = berlaku untuk semua kategori), status active|inactive. GlossaryContextService membaca istilah aktif
 * (cakupan: tanpa kategori + kategori pertanyaan) sebagai KONTEKS terminologi untuk AI; bukan evidence dan tidak
 * boleh disitasi. Kontrak data itu tidak diubah di sini.
 *
 * Simpan/edit/nonaktifkan menjawab JSON untuk admin-glossary.js (tanpa memuat ulang halaman) dan tetap redirect
 * untuk form biasa. Aturan keunikan dijalankan di server.
 */
class GlossaryController extends Controller
{
    private const SORTS = ['alpha', 'updated'];

    private const ALIAS_LIMIT = 30;

    private const ALIAS_LENGTH = 120;

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:160'],
            'category' => ['nullable', 'string', 'max:20'],
            'status' => ['nullable', 'in:active,inactive'],
            'sort' => ['nullable', Rule::in(self::SORTS)],
        ]);

        $category = $filters['category'] ?? null;
        $query = GlossaryTerm::query()->with('category:id,name');

        if (filled($filters['search'] ?? null)) {
            $like = '%'.addcslashes(trim((string) $filters['search']), '%_\\').'%';
            $query->where(fn ($q) => $q->where('term', 'like', $like)
                ->orWhere('definition', 'like', $like)
                ->orWhere('context_note', 'like', $like)
                ->orWhere('aliases_json', 'like', $like));
        }

        if ($category === 'general') {
            $query->whereNull('category_id');
        } elseif (filled($category) && ctype_digit((string) $category)) {
            $query->where('category_id', (int) $category);
        }

        if (filled($filters['status'] ?? null)) {
            $query->where('status', $filters['status']);
        }

        if (($filters['sort'] ?? 'alpha') === 'updated') {
            $query->orderByDesc('updated_at')->orderByDesc('id');
        } else {
            $query->orderBy('term')->orderBy('id');
        }

        $all = GlossaryTerm::query()->get(['id', 'status', 'category_id', 'aliases_json']);

        return view('admin.glossary.index', [
            'terms' => $query->paginate(20)->withQueryString(),
            'categories' => $this->categories(),
            'filtering' => filled($filters['search'] ?? null) || filled($category) || filled($filters['status'] ?? null) || ($filters['sort'] ?? null) === 'updated',
            'stats' => [
                'total' => $all->count(),
                'active' => $all->where('status', 'active')->count(),
                'categories' => $all->whereNotNull('category_id')->pluck('category_id')->unique()->count(),
                'aliases' => $all->sum(fn (GlossaryTerm $t) => count($t->aliases_json ?? [])),
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.glossary.form', [
            'term' => new GlossaryTerm(['status' => 'active']),
            'categories' => $this->categories(),
        ]);
    }

    public function edit(GlossaryTerm $glossaryTerm): View
    {
        return view('admin.glossary.form', [
            'term' => $glossaryTerm->load('category:id,name'),
            'categories' => $this->categories($glossaryTerm->category_id),
        ]);
    }

    /** Pemeriksaan konflik selagi mengetik. Tidak mengubah apa pun. */
    public function check(Request $request): JsonResponse
    {
        $input = $request->validate([
            'term' => ['nullable', 'string', 'max:180'],
            'category' => ['nullable', 'integer'],
            'aliases' => ['nullable', 'array', 'max:'.self::ALIAS_LIMIT],
            'aliases.*' => ['nullable', 'string', 'max:'.self::ALIAS_LENGTH],
            'ignore' => ['nullable', 'integer'],
        ]);

        $term = $this->clean($input['term'] ?? '');
        $category = $input['category'] ?? null;
        $ignore = $input['ignore'] ?? null;
        $scope = $this->scope($category, $ignore);

        $same = $term !== '' ? $this->termConflict($scope, $term) : null;
        $aliases = [];

        foreach ($this->normalizeAliases($input['aliases'] ?? [], $term) as $alias) {
            if ($other = $this->aliasConflict($scope, $alias)) {
                $aliases[] = ['alias' => $alias] + $this->pointer($other);
            }
        }

        $elsewhere = null;

        if ($term !== '' && ! $same) {
            $elsewhere = GlossaryTerm::query()
                ->when($ignore, fn ($q) => $q->whereKeyNot($ignore))
                ->get(['id', 'term', 'slug', 'category_id'])
                ->first(fn (GlossaryTerm $t) => mb_strtolower($t->term) === mb_strtolower($term));
        }

        return response()->json([
            'term' => $same ? $this->pointer($same) : null,
            'aliases' => $aliases,
            'elsewhere' => $elsewhere ? $this->pointer($elsewhere) + ['category' => $elsewhere->category?->name ?? 'Umum'] : null,
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $this->validated($request);
        $this->refuseConflicts($request, $data, null);

        $adminId = $request->session()->get('research_admin_user_id');

        $term = GlossaryTerm::create([
            'category_id' => $data['category_id'],
            'term' => $data['term'],
            'slug' => $this->uniqueSlug($data['term']),
            'definition' => $data['definition'],
            'aliases_json' => $data['aliases'],
            'context_note' => $data['context_note'],
            'status' => $data['status'],
            'created_by' => $adminId,
            'updated_by' => $adminId,
        ]);

        $this->audit($request, 'glossary.created', $term);

        return $this->done($request, 'Istilah "'.$term->term.'" dibuat.', $term, redirect()->route('admin.glossary.edit', $term));
    }

    public function update(Request $request, GlossaryTerm $glossaryTerm): RedirectResponse|JsonResponse
    {
        $data = $this->validated($request);
        $this->refuseConflicts($request, $data, $glossaryTerm);

        // Slug tetap saat istilah diganti: tidak ada yang boleh berubah diam-diam di luar field yang diedit.
        $glossaryTerm->update([
            'category_id' => $data['category_id'],
            'term' => $data['term'],
            'definition' => $data['definition'],
            'aliases_json' => $data['aliases'],
            'context_note' => $data['context_note'],
            'status' => $data['status'],
            'updated_by' => $request->session()->get('research_admin_user_id'),
        ]);

        $this->audit($request, 'glossary.updated', $glossaryTerm);

        return $this->done($request, 'Istilah "'.$glossaryTerm->term.'" diperbarui.', $glossaryTerm, back());
    }

    public function destroy(Request $request, GlossaryTerm $glossaryTerm): RedirectResponse|JsonResponse
    {
        $glossaryTerm->update([
            'status' => 'inactive',
            'updated_by' => $request->session()->get('research_admin_user_id'),
        ]);

        $this->audit($request, 'glossary.deactivated', $glossaryTerm);

        return $this->done($request, 'Istilah "'.$glossaryTerm->term.'" dinonaktifkan. AI tidak lagi memakainya sebagai konteks; riwayatnya tetap tersimpan.', $glossaryTerm, redirect()->route('admin.glossary.index'));
    }

    /*
    |--------------------------------------------------------------------------
    | Rules
    |--------------------------------------------------------------------------
    */

    private function validated(Request $request): array
    {
        $request->merge(['term' => $this->clean((string) $request->input('term'))]);

        $data = $request->validate([
            'term' => ['required', 'string', 'max:180'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'definition' => ['required', 'string', 'max:5000'],
            // Form dengan JavaScript mengirim aliases[]; form biasa mengirim satu string dipisah koma/baris.
            'aliases' => ['nullable'],
            'aliases.*' => ['nullable', 'string', 'max:'.self::ALIAS_LENGTH],
            'context_note' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', 'in:active,inactive'],
        ], [
            'term.required' => 'Istilah wajib diisi.',
            'term.max' => 'Istilah maksimal 180 karakter.',
            'definition.required' => 'Definisi wajib diisi.',
            'definition.max' => 'Definisi maksimal 5.000 karakter.',
            'aliases.*.max' => 'Setiap alias maksimal '.self::ALIAS_LENGTH.' karakter.',
            'context_note.max' => 'Catatan maksimal 5.000 karakter.',
            'category_id.exists' => 'Kategori tidak ditemukan.',
        ]);

        $raw = $data['aliases'] ?? [];
        $raw = is_array($raw) ? $raw : (preg_split('/[,\n;]+/u', (string) $raw) ?: []);

        if (count(array_filter($raw, fn ($a) => filled($a))) > self::ALIAS_LIMIT) {
            $this->raise($request, ['aliases' => 'Maksimal '.self::ALIAS_LIMIT.' alias per istilah.']);
        }

        foreach ($raw as $alias) {
            if (mb_strlen($this->clean((string) $alias)) > self::ALIAS_LENGTH) {
                $this->raise($request, ['aliases' => 'Setiap alias maksimal '.self::ALIAS_LENGTH.' karakter.']);
            }
        }

        return [
            'term' => $data['term'],
            'category_id' => $data['category_id'] ?? null,
            'definition' => trim($data['definition']),
            'aliases' => $this->normalizeAliases($raw, $data['term']),
            'context_note' => filled($data['context_note'] ?? null) ? trim((string) $data['context_note']) : null,
            'status' => $data['status'],
        ];
    }

    /**
     * Istilah dan alias harus unik dalam cakupan yang sama (kategori yang sama, atau sama-sama umum), tanpa membedakan
     * huruf besar/kecil dan spasi. Istilah yang sama di kategori berbeda boleh karena maknanya bisa berbeda.
     * Tidak ada penggabungan otomatis berdasarkan kemiripan.
     */
    private function refuseConflicts(Request $request, array $data, ?GlossaryTerm $current): void
    {
        $scope = $this->scope($data['category_id'], $current?->id);
        $errors = [];
        $existing = null;

        if ($same = $this->termConflict($scope, $data['term'])) {
            $errors['term'] = 'Istilah "'.$same->term.'" sudah ada'.($data['category_id'] ? ' di kategori ini' : ' sebagai istilah umum').'.';
            $existing = $same;
        }

        foreach ($data['aliases'] as $alias) {
            if ($other = $this->aliasConflict($scope, $alias)) {
                $errors['aliases'] = 'Alias "'.$alias.'" sudah dipakai oleh istilah "'.$other->term.'".';
                $existing ??= $other;

                break;
            }
        }

        if ($errors) {
            $this->raise($request, $errors, $existing);
        }
    }

    /** Istilah lain dengan cakupan sama (kategori yang sama atau sama-sama umum). */
    private function scope(?int $category, ?int $ignore)
    {
        return GlossaryTerm::query()
            ->when($category, fn ($q) => $q->where('category_id', $category), fn ($q) => $q->whereNull('category_id'))
            ->when($ignore, fn ($q) => $q->whereKeyNot($ignore))
            ->get(['id', 'term', 'slug', 'aliases_json', 'category_id']);
    }

    private function termConflict($scope, string $term): ?GlossaryTerm
    {
        $needle = mb_strtolower($term);

        return $scope->first(fn (GlossaryTerm $t) => mb_strtolower($t->term) === $needle)
            ?? $scope->first(fn (GlossaryTerm $t) => in_array($needle, array_map('mb_strtolower', $t->aliases_json ?? []), true));
    }

    private function aliasConflict($scope, string $alias): ?GlossaryTerm
    {
        return $this->termConflict($scope, $alias);
    }

    /** @return list<string> */
    private function normalizeAliases(iterable $raw, string $term): array
    {
        $seen = [mb_strtolower($term) => true];
        $out = [];

        foreach ($raw as $alias) {
            $alias = $this->clean((string) $alias);
            $key = mb_strtolower($alias);

            // Kosong, sama dengan istilah utama, atau berulang: dibuang.
            if ($alias === '' || isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $out[] = $alias;
        }

        return array_slice($out, 0, self::ALIAS_LIMIT);
    }

    private function clean(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    private function raise(Request $request, array $errors, ?GlossaryTerm $existing = null): void
    {
        if ($request->expectsJson()) {
            throw new HttpResponseException(response()->json([
                'message' => reset($errors),
                'errors' => array_map(fn ($message) => [$message], $errors),
                'existing' => $existing ? $this->pointer($existing) : null,
            ], 422));
        }

        throw ValidationException::withMessages($errors);
    }

    private function pointer(GlossaryTerm $term): array
    {
        return ['id' => $term->id, 'term' => $term->term, 'url' => route('admin.glossary.edit', $term)];
    }

    private function done(Request $request, string $message, GlossaryTerm $term, RedirectResponse $redirect): RedirectResponse|JsonResponse
    {
        return $request->expectsJson()
            ? response()->json(['ok' => true, 'message' => $message, 'id' => $term->id])
            : $redirect->with('success', $message);
    }

    private function uniqueSlug(string $term): string
    {
        $base = Str::slug($term) ?: 'term';
        $slug = $base;
        $counter = 2;

        while (GlossaryTerm::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }

    private function categories(?int $keep = null)
    {
        return Category::query()
            ->where(fn ($q) => $q->where('status', 'active')->when($keep, fn ($w) => $w->orWhere('id', $keep)))
            ->orderBy('name')
            ->get(['id', 'name', 'status']);
    }

    private function audit(Request $request, string $action, GlossaryTerm $term): void
    {
        try {
            AuditLog::create([
                'user_id' => $request->session()->get('research_admin_user_id'),
                'action' => $action,
                'auditable_type' => GlossaryTerm::class,
                'auditable_id' => $term->id,
                'metadata_json' => ['term' => $term->term, 'status' => $term->status, 'category_id' => $term->category_id],
                'ip_address' => $request->ip(),
            ]);
        } catch (Throwable) {
            // audit tidak boleh menggagalkan penyimpanan istilah
        }
    }
}
