<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Models\Document;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CollectionController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:160'],
            'status' => ['nullable', 'in:active,archived'],
        ]);

        $query = Collection::query()->withCount('documents');

        if (filled($filters['search'] ?? null)) {
            $search = trim((string) $filters['search']);
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (filled($filters['status'] ?? null)) {
            $query->where('status', $filters['status']);
        }

        return view('admin.collections.index', [
            'collections' => $query->latest('id')->paginate(20)->withQueryString(),
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

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $documentIds = $data['document_ids'] ?? [];
        unset($data['document_ids']);

        $data['slug'] = $this->uniqueSlug($data['name']);
        $collection = Collection::create($data);
        $collection->documents()->sync($documentIds);

        return redirect()->route('admin.collections.edit', $collection)->with('success', 'Collection berhasil dibuat.');
    }

    public function edit(Collection $collection): View
    {
        return view('admin.collections.form', [
            'collection' => $collection,
            'documents' => $this->documents(),
            'selectedDocuments' => $collection->documents()->pluck('documents.id')->all(),
        ]);
    }

    public function update(Request $request, Collection $collection): RedirectResponse
    {
        $data = $this->validateData($request);
        $documentIds = $data['document_ids'] ?? [];
        unset($data['document_ids']);

        if ($collection->name !== $data['name']) {
            $data['slug'] = $this->uniqueSlug($data['name'], $collection->id);
        }

        $collection->update($data);
        $collection->documents()->sync($documentIds);

        return redirect()->route('admin.collections.edit', $collection)->with('success', 'Collection berhasil diperbarui.');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', 'in:active,archived'],
            'document_ids' => ['nullable', 'array'],
            'document_ids.*' => ['integer', 'exists:documents,id'],
        ]);
    }

    private function documents()
    {
        return Document::query()->with('category')->orderBy('title')->get();
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'collection';
        $slug = $base;
        $counter = 2;

        while (Collection::query()->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }
}
