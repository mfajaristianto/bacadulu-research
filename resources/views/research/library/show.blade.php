@extends('layouts.app')
@section('title', $document->title.' — BacaDulu Research')
@section('content')
<section class="document-detail">
    <a class="back-link" href="{{ route('library.index') }}">← Back to Library</a>
    <div class="document-detail-grid">
        <article class="document-detail-main" data-reveal>
            <p class="document-category">{{ $document->category?->name ?: 'Internal Source' }}</p>
            <h1>{{ $document->title }}</h1>
            <div class="document-meta-large"><span>{{ $document->document_type ?: 'Document' }}</span><span>{{ $document->publication_year ?: 'Year unavailable' }}</span><span>{{ strtoupper($document->language ?: 'n/a') }}</span></div>
            <div class="document-body"><h2>Abstract</h2><p>{{ $document->abstract ?: 'No abstract available.' }}</p></div>
        </article>
        <aside class="document-facts" data-reveal>
            <p class="eyebrow">SOURCE METADATA</p>
            <dl>
                <div><dt>Publisher</dt><dd>{{ $document->publisher ?: 'Not specified' }}</dd></div>
                <div><dt>DOI</dt><dd>{{ $document->doi ?: 'Not available' }}</dd></div>
                <div><dt>ISBN</dt><dd>{{ $document->isbn ?: 'Not available' }}</dd></div>
                <div><dt>Visibility</dt><dd>Eligible public source</dd></div>
            </dl>
            <a href="{{ route('workspace', ['source' => $document->id]) }}" class="btn btn-primary btn-full">Use in Research <span>↗</span></a>
        </aside>
    </div>
</section>
@endsection
