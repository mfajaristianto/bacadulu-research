@extends('layouts.admin')

@section('title', 'AI Run #'.$run->id)
@section('page_title', 'AI Runs')

@section('content')
@php
    $validCitations = $run->citations->where('is_valid', true);
    $invalidCitations = $run->citations->where('is_valid', false);
    $primaryResult = $run->results->firstWhere('result_type', 'primary');
@endphp

<section class="admin-page">
    <header class="bd-admin-page-head">
        <div class="bd-admin-page-head__copy">
            <p class="bd-admin-kicker">AI CONTROL / RUN TRACE</p>
            <h1>RUN-{{ str_pad($run->id, 5, '0', STR_PAD_LEFT) }}</h1>
            <p>{{ $run->uuid }}</p>
        </div>
        <div class="bd-admin-page-head__actions"><a href="{{ route('admin.ai-runs.index') }}" class="bd-admin-button bd-admin-button--ghost">← AI Runs</a></div>
    </header>

    <div class="bd-admin-panels">
        <article class="bd-admin-panel"><small>STATUS</small><strong>{{ str($run->status)->replace('_',' ')->title() }}</strong><p>{{ $durationMs !== null ? number_format($durationMs / 1000, 2).' sec' : 'Execution timing incomplete' }}</p></article>
        <article class="bd-admin-panel"><small>RETRIEVAL</small><strong>{{ $run->retrievals->count() }}</strong><p>{{ $run->sources->count() }} contributing sources.</p></article>
        <article class="bd-admin-panel"><small>CITATIONS</small><strong>{{ $validCitations->count() }}</strong><p>{{ $invalidCitations->count() }} invalid citation.</p></article>
    </div>

    <section class="bd-admin-section">
        <div class="bd-admin-section__head"><div><h2>Execution Trace</h2><p>Urutan provenance run ini.</p></div></div>
        <div class="bd-admin-trace">
            <article><span>01</span><div><strong>Request</strong><small>{{ $run->user?->name ?: 'Unknown user' }} · {{ $conversation?->title ?: 'No conversation title' }}</small></div></article>
            <article><span>02</span><div><strong>Prompt Version</strong><small>{{ $run->promptTemplate ? $run->promptTemplate->name.' · v'.$run->promptTemplate->version : 'Not recorded' }}</small></div></article>
            <article><span>03</span><div><strong>Retrieval</strong><small>{{ $run->retrievals->count() }} ranked chunks selected</small></div></article>
            <article><span>04</span><div><strong>Evidence</strong><small>{{ $run->sources->count() }} source documents contributed</small></div></article>
            <article><span>05</span><div><strong>Provider</strong><small>{{ strtoupper($run->model_provider ?: '—') }} · {{ $run->model_name ?: '—' }}</small></div></article>
            <article><span>06</span><div><strong>Citation Validation</strong><small>{{ $validCitations->count() }} valid · {{ $invalidCitations->count() }} invalid</small></div></article>
            <article><span>07</span><div><strong>Result</strong><small>{{ $primaryResult ? 'Primary result stored' : 'No primary result stored' }}</small></div></article>
        </div>
    </section>

    <section class="bd-admin-section">
        <div class="bd-admin-two-column">
            <div class="bd-admin-detail-list">
                <div><span>User</span><strong>{{ $run->user?->name ?: '—' }}</strong></div>
                <div><span>Email</span><strong>{{ $run->user?->email ?: '—' }}</strong></div>
                <div><span>Research Tool</span><strong>{{ $run->aiTool?->name ?: '—' }}</strong></div>
                <div><span>Prompt</span><strong>{{ $run->promptTemplate ? $run->promptTemplate->name.' · v'.$run->promptTemplate->version : '—' }}</strong></div>
                <div><span>Provider</span><strong>{{ strtoupper($run->model_provider ?: '—') }}</strong></div>
                <div><span>Model</span><strong>{{ $run->model_name ?: '—' }}</strong></div>
                <div><span>Started</span><strong>{{ optional($run->started_at)->format('d M Y · H:i:s') ?: '—' }}</strong></div>
                <div><span>Finished</span><strong>{{ optional($run->finished_at)->format('d M Y · H:i:s') ?: '—' }}</strong></div>
            </div>

            <aside class="bd-admin-form__aside">
                <section><strong>Parameters</strong>@forelse($parameters as $key => $value)<p><code>{{ $key }}</code>: {{ is_scalar($value) ? $value : json_encode($value) }}</p>@empty<p>No user parameters recorded.</p>@endforelse</section>
                @if($run->error_message)<section><strong>Error</strong><p class="admin-danger-text">{{ $run->error_message }}</p></section>@endif
            </aside>
        </div>
    </section>

    <section class="bd-admin-section">
        <div class="bd-admin-section__head"><div><h2>Retrieved Evidence</h2><p>Ranked evidence chunks used by the run.</p></div></div>
        <div class="bd-admin-table-wrap">
            <table class="bd-admin-table">
                <thead><tr><th>Rank</th><th>Source</th><th>Chunk</th><th>Score</th></tr></thead>
                <tbody>
                @forelse($run->retrievals as $retrieval)
                    <tr>
                        <td>#{{ $retrieval->rank_no }}</td>
                        <td><div class="bd-admin-table__title"><strong>{{ $retrieval->document?->title ?: '—' }}</strong><small>SRC-{{ $retrieval->document_id }}</small></div></td>
                        <td>{{ $retrieval->document_chunk_id ?: '—' }}</td>
                        <td>{{ $retrieval->score !== null ? number_format((float) $retrieval->score, 4) : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4">No retrieval evidence recorded.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="bd-admin-section">
        <div class="bd-admin-section__head"><div><h2>Citations</h2><p>Validation result per citation key.</p></div></div>
        <div class="bd-admin-table-wrap">
            <table class="bd-admin-table">
                <thead><tr><th>Key</th><th>Source</th><th>Chunk</th><th>Validation</th></tr></thead>
                <tbody>
                @forelse($run->citations as $citation)
                    <tr>
                        <td><code>{{ $citation->citation_key }}</code></td>
                        <td>{{ $citation->document?->title ?: '—' }}</td>
                        <td>{{ $citation->document_chunk_id ?: '—' }}</td>
                        <td><span class="bd-admin-status bd-admin-status--{{ $citation->is_valid ? 'success' : 'failed' }}">{{ $citation->is_valid ? 'Valid' : 'Invalid' }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="4">No citations recorded.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="bd-admin-section">
        <div class="bd-admin-section__head"><div><h2>Primary Result</h2><p>Stored output from the provider pipeline.</p></div></div>
        <div class="bd-admin-result"><pre>{{ $primaryResult?->content ?: 'No primary result stored.' }}</pre></div>
    </section>
</section>
@endsection
