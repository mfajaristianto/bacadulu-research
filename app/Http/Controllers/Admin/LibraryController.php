<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Document;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LibraryController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:160'],
            'status' => ['nullable', 'in:draft,processing,ready,published,archived'],
            'visibility' => ['nullable', 'in:public,restricted'],
        ]);

        $documents = Document::query()->with('category');

        if (filled($filters['search'] ?? null)) {
            $search = trim((string) $filters['search']);
            $documents->where(fn ($query) => $query
                ->where('title', 'like', "%{$search}%")
                ->orWhere('publisher', 'like', "%{$search}%")
                ->orWhere('doi', 'like', "%{$search}%"));
        }

        if (filled($filters['status'] ?? null)) {
            $documents->where('status', $filters['status']);
        }

        if (filled($filters['visibility'] ?? null)) {
            $documents->where('visibility', $filters['visibility']);
        }

        return view('admin.library.index', [
            'documents' => $documents->latest()->paginate(15)->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('admin.library.form', [
            'document' => new Document(),
            'categories' => Category::query()->where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $data['uuid'] = (string) Str::uuid();
        $data['published_at'] = $data['status'] === 'published' ? now() : null;
        $data['uploaded_by'] = $request->session()->get('research_admin_user_id');

        Document::create($data);

        return redirect()
            ->route('admin.library.index')
            ->with('success', 'Dokumen berhasil ditambahkan.');
    }

    public function edit(Document $document): View
    {
        return view('admin.library.form', [
            'document' => $document,
            'categories' => Category::query()->where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Document $document): RedirectResponse
    {
        $data = $this->validateData($request);
        $data['published_at'] = $data['status'] === 'published'
            ? ($document->published_at ?: now())
            : null;

        $document->update($data);

        return redirect()
            ->route('admin.library.index')
            ->with('success', 'Dokumen berhasil diperbarui.');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'abstract' => ['nullable', 'string'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'document_type' => ['required', 'string', 'max:100'],
            'publication_year' => ['nullable', 'integer', 'min:1900', 'max:'.(now()->year + 2)],
            'language' => ['nullable', 'string', 'max:20'],
            'doi' => ['nullable', 'string', 'max:255'],
            'isbn' => ['nullable', 'string', 'max:100'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'source_label' => ['nullable', 'string', 'max:255'],
            'visibility' => ['required', 'in:public,restricted'],
            'status' => ['required', 'in:draft,processing,ready,published,archived'],
        ]);
    }
}
