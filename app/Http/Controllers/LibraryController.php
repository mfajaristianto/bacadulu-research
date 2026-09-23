<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LibraryController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:160'],
            'category' => ['nullable', 'integer', 'exists:categories,id'],
        ]);

        $query = Document::query()
            ->eligibleForUsers()
            ->with('category');

        if (filled($filters['search'] ?? null)) {
            $search = trim((string) $filters['search']);

            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('abstract', 'like', "%{$search}%")
                    ->orWhere('publisher', 'like', "%{$search}%")
                    ->orWhere('doi', 'like', "%{$search}%");
            });
        }

        if (filled($filters['category'] ?? null)) {
            $query->where('category_id', (int) $filters['category']);
        }

        return view('research.library.index', [
            'documents' => $query->latest('published_at')->paginate(12)->withQueryString(),
            'categories' => Category::query()->where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function show(Document $document): View
    {
        $document->load('category');

        abort_unless(
            $document->status === 'published'
            && $document->visibility === 'public'
            && $document->category?->status === 'active',
            404
        );

        return view('research.library.show', [
            'document' => $document,
        ]);
    }
}
