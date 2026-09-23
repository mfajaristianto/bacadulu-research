@extends('layouts.admin')
@section('title', 'Internal Library — Admin')
@section('content')
<section class="admin-page">
    <div class="admin-hero compact" data-reveal>
        <div><p class="eyebrow">ADMIN / LIBRARY</p><h1>Internal Library.</h1><p>Manage source metadata, publication state, and user visibility.</p></div>
        <a href="{{ route('admin.library.create') }}" class="btn btn-primary">Add source <span>+</span></a>
    </div>

    <form method="GET" action="{{ route('admin.library.index') }}" class="admin-filter" data-reveal>
        <label class="filter-field"><span>Search</span><input type="search" name="search" value="{{ request('search') }}" maxlength="160" placeholder="Title, publisher, DOI…"></label>
        <label class="filter-field"><span>Status</span><select name="status"><option value="">All status</option>@foreach(['draft','processing','ready','published','archived'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></label>
        <label class="filter-field"><span>Visibility</span><select name="visibility"><option value="">All visibility</option><option value="public" @selected(request('visibility') === 'public')>Public</option><option value="restricted" @selected(request('visibility') === 'restricted')>Restricted</option></select></label>
        <button type="submit" class="btn btn-secondary">Filter</button>
    </form>

    <div class="admin-table-wrap" data-reveal>
        <table class="admin-table">
            <thead><tr><th>Title</th><th>Category</th><th>Type</th><th>Visibility</th><th>Status</th><th>Year</th><th></th></tr></thead>
            <tbody>
            @forelse($documents as $document)
                <tr>
                    <td><strong>{{ $document->title }}</strong><small>{{ $document->publisher ?: $document->source_label ?: 'Internal source' }}</small></td>
                    <td>{{ $document->category?->name ?: '—' }}</td>
                    <td>{{ $document->document_type ?: '—' }}</td>
                    <td><span class="status-pill {{ $document->visibility === 'public' ? 'status-published' : 'status-draft' }}">{{ ucfirst($document->visibility) }}</span></td>
                    <td><span class="status-pill status-{{ $document->status }}">{{ ucfirst($document->status) }}</span></td>
                    <td>{{ $document->publication_year ?: '—' }}</td>
                    <td><a class="table-link" href="{{ route('admin.library.edit', $document) }}">Edit</a></td>
                </tr>
            @empty
                <tr><td colspan="7"><div class="admin-empty">No documents found.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $documents->links() }}
</section>
@endsection
