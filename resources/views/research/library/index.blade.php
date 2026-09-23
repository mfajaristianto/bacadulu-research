@extends('layouts.app')
@section('title', 'Internal Library — BacaDulu Research')
@section('content')
<section class="library-page">
    <div class="library-head" data-reveal>
        <div><p class="eyebrow">INTERNAL LIBRARY</p><h1>Research Library.</h1><p>Browse sumber published yang eligible untuk research workspace.</p></div>
        <div class="library-head-note"><span>Public sources only</span><small>Restricted records stay outside user retrieval.</small></div>
    </div>

    <form method="GET" action="{{ route('library.index') }}" class="library-filter" data-reveal>
        <label class="filter-field"><span>Search</span><input id="search" type="search" name="search" value="{{ request('search') }}" maxlength="160" placeholder="Title, abstract, publisher, DOI…"></label>
        <label class="filter-field"><span>Category</span><select id="category" name="category"><option value="">All categories</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((string) request('category') === (string) $category->id)>{{ $category->name }}</option>@endforeach</select></label>
        <button type="submit" class="btn btn-primary">Search</button>
        @if(request()->filled('search') || request()->filled('category'))<a href="{{ route('library.index') }}" class="btn btn-ghost">Reset</a>@endif
    </form>

    <div class="library-result-meta"><span>{{ $documents->total() }} sources</span><span>Page {{ $documents->currentPage() }} of {{ $documents->lastPage() }}</span></div>

    <div class="document-grid">
        @forelse($documents as $document)
            <a href="{{ route('library.show', $document) }}" class="document-card" data-reveal>
                <div class="document-card-top"><span class="document-type">{{ strtoupper($document->document_type ?: 'DOCUMENT') }}</span>@if($document->publication_year)<span class="document-year">{{ $document->publication_year }}</span>@endif</div>
                <h3>{{ $document->title }}</h3>
                <p>{{ Str::limit($document->abstract ?: 'No abstract available.', 165) }}</p>
                <div class="document-meta"><span>{{ $document->category?->name ?: 'Uncategorised' }}</span>@if($document->publisher)<span>{{ $document->publisher }}</span>@endif</div>
                <div class="document-card-action">Open source <span>↗</span></div>
            </a>
        @empty
            <div class="empty-state" data-reveal><span>0 results</span><strong>No matching sources.</strong><p>Try another keyword or category filter.</p></div>
        @endforelse
    </div>

    @if($documents->hasPages())<div class="library-pagination">{{ $documents->links() }}</div>@endif
</section>
@endsection
