<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/*
 * Kategori (taksonomi sumber). Tabel categories hanya punya name, slug (unik), parent_id, dan status
 * (active|archived); tidak ada deskripsi dan tidak ada hapus. Mengarsipkan tidak menyentuh sumber:
 * documents.category_id tetap menunjuk kategori yang sama.
 *
 * Simpan dan edit menjawab JSON untuk modal di admin-categories.js (tanpa memuat ulang halaman) dan
 * tetap berupa redirect untuk form biasa. Semua aturan (duplikat, slug, parent, siklus) dijalankan di server.
 */
class CategoryController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:160'],
            'status' => ['nullable', 'in:active,archived'],
            'level' => ['nullable', 'in:main,sub'],
        ]);

        $all = Category::query()->withCount(['documents', 'children'])->orderBy('name')->get();
        $byParent = $all->groupBy(fn (Category $category) => $category->parent_id ?? 0);
        $names = $all->pluck('name', 'id');

        $search = mb_strtolower(trim((string) ($filters['search'] ?? '')));
        $filtering = $search !== '' || filled($filters['status'] ?? null) || filled($filters['level'] ?? null);

        $matches = $all->filter(fn (Category $c) => (! filled($filters['status'] ?? null) || $c->status === $filters['status'])
            && ($search === '' || str_contains(mb_strtolower($c->name), $search) || str_contains(mb_strtolower($c->slug), $search))
            && (($filters['level'] ?? null) === null || (($filters['level'] === 'sub') === ($c->parent_id !== null))));

        // Saat memfilter, induk dari yang cocok tetap tampil (redup) supaya konteks hierarkinya jelas.
        $visible = $matches->pluck('id')->flip();

        if ($filtering) {
            foreach ($matches as $category) {
                for ($parent = $category->parent_id, $guard = 0; $parent && $guard < 50; $guard++) {
                    $visible[$parent] ??= true;
                    $parent = $all->firstWhere('id', $parent)?->parent_id;
                }
            }
        }

        $seen = [];
        $build = function (int $parentId) use (&$build, $byParent, $visible, $matches, $filtering, &$seen): array {
            return ($byParent[$parentId] ?? collect())
                ->filter(fn (Category $c) => ! $filtering || isset($visible[$c->id]))
                ->map(function (Category $c) use (&$build, $matches, $filtering, &$seen) {
                    $seen[$c->id] = true;

                    return ['category' => $c, 'context' => $filtering && ! $matches->contains('id', $c->id), 'children' => $build($c->id)];
                })
                ->values()
                ->all();
        };

        $tree = $build(0);

        // Data yang tidak terjangkau dari akar (tidak seharusnya ada; mis. siklus lama) tetap ditampilkan sebagai kategori utama.
        foreach ($all->reject(fn (Category $c) => isset($seen[$c->id]) || ($filtering && ! isset($visible[$c->id]))) as $stray) {
            $seen[$stray->id] = true;
            $tree[] = ['category' => $stray, 'context' => false, 'children' => []];
        }

        return view('admin.categories.index', [
            'tree' => $tree,
            'names' => $names,
            'descendants' => $all->mapWithKeys(fn (Category $c) => [$c->id => $this->descendantIds($c->id, $byParent)]),
            'parents' => $all->values(),
            'filtering' => $filtering,
            'matchCount' => $matches->count(),
            'stats' => [
                'total' => $all->count(),
                'active' => $all->where('status', 'active')->count(),
                'sub' => $all->whereNotNull('parent_id')->count(),
                'used' => $all->where('documents_count', '>', 0)->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.categories.form', [
            'category' => new Category,
            'parents' => Category::query()->where('status', 'active')->orderBy('name')->get(),
            'blocked' => [],
        ]);
    }

    public function edit(Category $category): View
    {
        $blocked = $this->descendantIds($category->id);

        return view('admin.categories.form', [
            'category' => $category,
            'parents' => Category::query()
                ->whereNotIn('id', [$category->id, ...$blocked])
                ->where(fn ($q) => $q->where('status', 'active')->orWhere('id', $category->parent_id))
                ->orderBy('name')
                ->get(),
            'blocked' => $blocked,
        ]);
    }

    /** Pemeriksaan duplikat untuk modal/form selagi mengetik. Tidak mengubah apa pun. */
    public function check(Request $request): JsonResponse
    {
        $input = $request->validate([
            'name' => ['nullable', 'string', 'max:180'],
            'slug' => ['nullable', 'string', 'max:190'],
            'parent_id' => ['nullable', 'integer'],
            'ignore' => ['nullable', 'integer'],
        ]);

        $name = $this->cleanName($input['name'] ?? '');
        $slug = filled($input['slug'] ?? null) ? Str::slug($input['slug']) : null;
        $parent = $input['parent_id'] ?? null;
        $ignore = $input['ignore'] ?? null;

        $same = $name !== '' ? $this->sameName($name, $parent, $ignore) : null;
        $elsewhere = $name !== '' && ! $same ? $this->sameName($name, false, $ignore) : null;
        $taken = $slug ? Category::query()->when($ignore, fn ($q) => $q->whereKeyNot($ignore))->where('slug', $slug)->first() : null;

        return response()->json([
            'name' => $same ? $this->pointer($same) : null,
            'slug' => $taken ? $this->pointer($taken) : null,
            'elsewhere' => $elsewhere ? $this->pointer($elsewhere) + ['parent' => $elsewhere->parent?->name] : null,
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $this->validated($request);

        if ($failure = $this->conflicts($request, $data, null)) {
            return $failure;
        }

        $category = Category::create($data);

        $this->audit($request, 'category.created', $category, ['name' => $category->name, 'slug' => $category->slug, 'parent_id' => $category->parent_id, 'status' => $category->status]);

        return $this->done($request, 'Kategori "'.$category->name.'" dibuat.', $category);
    }

    public function update(Request $request, Category $category): RedirectResponse|JsonResponse
    {
        $data = $this->validated($request, $category);

        if ($failure = $this->conflicts($request, $data, $category)) {
            return $failure;
        }

        $category->fill($data);
        $changes = array_keys($category->getDirty());
        $category->save();

        $this->audit($request, 'category.updated', $category, ['changed' => $changes, 'name' => $category->name, 'slug' => $category->slug, 'status' => $category->status]);

        return $this->done($request, 'Kategori "'.$category->name.'" diperbarui.', $category);
    }

    /*
    |--------------------------------------------------------------------------
    | Rules
    |--------------------------------------------------------------------------
    */

    /** Bentuk dasar + normalisasi. Slug kosong saat membuat dibuat dari nama; saat mengedit slug lama TIDAK diubah diam-diam. */
    private function validated(Request $request, ?Category $category = null): array
    {
        $request->merge(['name' => $this->cleanName((string) $request->input('name'))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'slug' => ['nullable', 'string', 'max:190'],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'status' => ['required', Rule::in(['active', 'archived'])],
        ], [
            'name.required' => 'Nama kategori wajib diisi.',
            'name.max' => 'Nama kategori maksimal 180 karakter.',
            'parent_id.exists' => 'Kategori induk tidak ditemukan.',
            'status.required' => 'Pilih status kategori.',
            'status.in' => 'Status tidak valid.',
        ]);

        $data['parent_id'] = $data['parent_id'] ?? null;

        if (filled($data['slug'] ?? null)) {
            $data['slug'] = Str::slug($data['slug']);

            if ($data['slug'] === '') {
                $this->raise($request, ['slug' => 'Slug hanya boleh berisi huruf, angka, dan tanda hubung.']);
            }
        } elseif ($category) {
            $data['slug'] = $category->slug;
        } else {
            $data['slug'] = $this->uniqueSlug($data['name']);
        }

        return $data;
    }

    /** Aturan yang butuh data: duplikat nama/slug, parent sendiri/keturunan, parent harus aktif. */
    private function conflicts(Request $request, array $data, ?Category $category): RedirectResponse|JsonResponse|null
    {
        $errors = [];
        $existing = null;
        $same = $this->sameName($data['name'], $data['parent_id'], $category?->id);

        if ($same) {
            $errors['name'] = 'Kategori "'.$same->name.'" sudah ada'.($data['parent_id'] ? ' di induk yang sama' : '').'.';
            $existing = $same;
        }

        $taken = Category::query()->when($category, fn ($q) => $q->whereKeyNot($category->id))->where('slug', $data['slug'])->first();

        if ($taken) {
            $errors['slug'] = 'Slug "'.$data['slug'].'" sudah dipakai oleh kategori "'.$taken->name.'".';
            $existing ??= $taken;
        }

        if ($data['parent_id']) {
            if ($category && (int) $data['parent_id'] === $category->id) {
                $errors['parent_id'] = 'Kategori tidak bisa menjadi induk dirinya sendiri.';
            } elseif ($category && in_array((int) $data['parent_id'], $this->descendantIds($category->id), true)) {
                $errors['parent_id'] = 'Subkategori tidak bisa menjadi induk kategori ini; hierarki akan berputar.';
            } elseif ((! $category || (int) $data['parent_id'] !== (int) $category->parent_id) && Category::query()->whereKey($data['parent_id'])->value('status') !== 'active') {
                $errors['parent_id'] = 'Pilih kategori induk yang aktif.';
            }
        }

        return $errors ? $this->raise($request, $errors, $existing) : null;
    }

    private function raise(Request $request, array $errors, ?Category $existing = null): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            $response = response()->json([
                'message' => reset($errors),
                'errors' => array_map(fn ($message) => [$message], $errors),
                'existing' => $existing ? $this->pointer($existing) : null,
            ], 422);

            // dipakai juga oleh validated(), yang tidak bisa mengembalikan response: lempar agar framework meneruskannya
            throw new \Illuminate\Http\Exceptions\HttpResponseException($response);
        }

        throw \Illuminate\Validation\ValidationException::withMessages($errors);
    }

    private function sameName(string $name, int|string|null|false $parent, ?int $ignore): ?Category
    {
        $needle = mb_strtolower($name);

        return Category::query()
            ->when($parent !== false, fn ($q) => $parent ? $q->where('parent_id', $parent) : $q->whereNull('parent_id'))
            ->when($ignore, fn ($q) => $q->whereKeyNot($ignore))
            ->get(['id', 'name', 'slug', 'parent_id'])
            ->first(fn (Category $c) => mb_strtolower($c->name) === $needle);
    }

    private function cleanName(string $name): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $name));
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'kategori';
        $slug = $base;
        $counter = 2;

        while (Category::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }

    /** @return list<int> */
    private function descendantIds(int $id, ?Collection $byParent = null): array
    {
        $byParent ??= Category::query()->get(['id', 'parent_id'])->groupBy(fn ($c) => $c->parent_id ?? 0);
        $out = [];
        $queue = [$id];

        while ($queue) {
            foreach ($byParent[array_shift($queue)] ?? [] as $child) {
                if (! in_array($child->id, $out, true) && $child->id !== $id) {
                    $out[] = $child->id;
                    $queue[] = $child->id;
                }
            }
        }

        return $out;
    }

    private function pointer(Category $category): array
    {
        return ['id' => $category->id, 'name' => $category->name, 'slug' => $category->slug, 'url' => route('admin.categories.edit', $category)];
    }

    private function done(Request $request, string $message, Category $category): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'message' => $message, 'category' => ['id' => $category->id, 'name' => $category->name]]);
        }

        return redirect()->route('admin.categories.index')->with('success', $message);
    }

    private function audit(Request $request, string $action, Category $category, array $metadata): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        AuditLog::create([
            'user_id' => $request->session()->get('research_admin_user_id'),
            'action' => $action,
            'auditable_type' => Category::class,
            'auditable_id' => $category->id,
            'metadata_json' => $metadata,
            'ip_address' => $request->ip(),
        ]);
    }
}
