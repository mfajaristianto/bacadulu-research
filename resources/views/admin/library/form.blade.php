@extends('layouts.admin')
@section('title', ($document->exists ? 'Edit Source' : 'Add Source').' — Admin')
@section('content')
<section class="admin-page narrow-page">
    <div class="admin-hero compact" data-reveal>
        <div><p class="eyebrow">ADMIN / LIBRARY</p><h1>{{ $document->exists ? 'Edit source.' : 'Add source.' }}</h1><p>Published public sources can be retrieved by eligible research users. Restricted sources stay outside user retrieval.</p></div>
        <a href="{{ route('admin.library.index') }}" class="btn btn-secondary">Back to library</a>
    </div>

    @if($errors->any())<div class="alert alert-error" role="alert">{{ $errors->first() }}</div>@endif

    <form method="POST" action="{{ $document->exists ? route('admin.library.update', $document) : route('admin.library.store') }}" class="admin-form-card" data-reveal>
        @csrf
        @if($document->exists) @method('PUT') @endif

        <div class="admin-form-section"><span class="admin-kicker">SOURCE IDENTITY</span><h2>Core metadata</h2></div>
        <div class="admin-form-grid">
            <label class="full">Title<input name="title" value="{{ old('title', $document->title) }}" required maxlength="255"></label>
            <label>Category<select name="category_id"><option value="">No category</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(old('category_id', $document->category_id) == $category->id)>{{ $category->name }}</option>@endforeach</select></label>
            <label>Document type<input name="document_type" value="{{ old('document_type', $document->document_type) }}" required maxlength="100" placeholder="journal article, report, dataset…"></label>
            <label>Publication year<input type="number" name="publication_year" min="1900" max="{{ now()->year + 2 }}" value="{{ old('publication_year', $document->publication_year) }}"></label>
            <label>Language<input name="language" value="{{ old('language', $document->language ?: 'id') }}" maxlength="20"></label>
            <label>Publisher<input name="publisher" value="{{ old('publisher', $document->publisher) }}" maxlength="255"></label>
            <label>Source label<input name="source_label" value="{{ old('source_label', $document->source_label) }}" maxlength="255"></label>
            <label>DOI<input name="doi" value="{{ old('doi', $document->doi) }}" maxlength="255"></label>
            <label>ISBN<input name="isbn" value="{{ old('isbn', $document->isbn) }}" maxlength="100"></label>
        </div>

        <div class="admin-form-section"><span class="admin-kicker">ACCESS CONTROL</span><h2>Availability</h2></div>
        <div class="admin-form-grid">
            <label>Visibility<select name="visibility" required><option value="public" @selected(old('visibility', $document->visibility ?: 'public') === 'public')>Public to eligible users</option><option value="restricted" @selected(old('visibility', $document->visibility) === 'restricted')>Restricted</option></select></label>
            <label>Status<select name="status" required>@foreach(['draft','processing','ready','published','archived'] as $status)<option value="{{ $status }}" @selected(old('status', $document->status ?: 'draft') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></label>
        </div>

        <label>Abstract<textarea name="abstract" rows="10" placeholder="Source abstract or editorial summary…">{{ old('abstract', $document->abstract) }}</textarea></label>

        <div class="admin-form-footer"><button class="btn btn-primary" type="submit">Save Document</button><a class="btn btn-secondary" href="{{ route('admin.library.index') }}">Cancel</a></div>
    </form>
</section>
@endsection
