<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:160'],
            'status' => ['nullable', 'in:active,archived'],
        ]);

        $query = Category::query()->with('parent')->withCount('documents');

        if (filled($filters['search'] ?? null)) {
            $search = trim((string) $filters['search']);
            $query->where('name', 'like', "%{$search}%");
        }

        if (filled($filters['status'] ?? null)) {
            $query->where('status', $filters['status']);
        }

        return view('admin.categories.index', [
            'categories' => $query->orderBy('name')->paginate(20)->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('admin.categories.form', [
            'category' => new Category,
            'parents' => Category::query()->where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $data['slug'] = $this->uniqueSlug($data['name']);

        Category::create($data);

        return redirect()->route('admin.categories.index')->with('success', 'Kategori berhasil dibuat.');
    }

    public function edit(Category $category): View
    {
        return view('admin.categories.form', [
            'category' => $category,
            'parents' => Category::query()->whereKeyNot($category->id)->where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $data = $this->validateData($request, $category);

        if ($category->name !== $data['name']) {
            $data['slug'] = $this->uniqueSlug($data['name'], $category->id);
        }

        $category->update($data);

        return redirect()->route('admin.categories.index')->with('success', 'Kategori berhasil diperbarui.');
    }

    private function validateData(Request $request, ?Category $category = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'parent_id' => [
                'nullable',
                'integer',
                'exists:categories,id',
                Rule::notIn(array_filter([$category?->id])),
            ],
            'status' => ['required', 'in:active,archived'],
        ]);
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        $counter = 2;

        while (Category::query()->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }
}
