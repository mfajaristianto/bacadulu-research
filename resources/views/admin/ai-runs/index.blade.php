@extends('layouts.admin')

@section('title', 'AI Runs')
@section('page_title', 'AI Runs')

@section('content')
<section class="admin-page">
    <header class="bd-admin-page-head">
        <div class="bd-admin-page-head__copy">
            <p class="bd-admin-kicker">AI CONTROL / RUNS</p>
            <h1>AI Runs</h1>
            <p>Observability untuk request → prompt → retrieval → evidence → citation → result. Read-only.</p>
        </div>
    </header>

    <form method="GET" action="{{ route('admin.ai-runs.index') }}" class="bd-admin-filter">
        <input class="bd-admin-input" type="search" name="search" value="{{ request('search') }}" placeholder="Run UUID, user, tool, prompt…">
        <select class="bd-admin-select" name="status" onchange="this.form.submit()">
            <option value="">Semua status</option>
            @foreach(['queued','retrieving','evidence_ready','generating','completed','insufficient_evidence','citation_failed','failed'] as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ str($status)->replace('_',' ')->title() }}</option>
            @endforeach
        </select>
        <select class="bd-admin-select" name="provider" onchange="this.form.submit()">
            <option value="">Semua provider</option>
            @foreach($providers as $provider)
                <option value="{{ $provider }}" @selected(request('provider') === $provider)>{{ strtoupper($provider) }}</option>
            @endforeach
        </select>
    </form>

    <div class="bd-admin-table-wrap">
        <table class="bd-admin-table">
            <thead><tr><th>Run</th><th>User / Conversation</th><th>Research Tool</th><th>Evidence</th><th>Provider</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($runs as $run)
                <tr>
                    <td><div class="bd-admin-table__title"><strong>RUN-{{ str_pad($run->id, 5, '0', STR_PAD_LEFT) }}</strong><small>{{ Str::limit($run->uuid, 18) }}</small></div></td>
                    <td><div class="bd-admin-table__title"><strong>{{ $run->user?->name ?: '—' }}</strong><small>{{ $run->assistantMessage?->conversation?->title ?: $run->user?->email ?: '—' }}</small></div></td>
                    <td><div class="bd-admin-table__title"><strong>{{ $run->aiTool?->name ?: '—' }}</strong><small>{{ $run->promptTemplate ? $run->promptTemplate->name.' · v'.$run->promptTemplate->version : 'No prompt recorded' }}</small></div></td>
                    <td><strong>{{ $run->retrievals_count }}</strong><small> retrievals · {{ $run->valid_citations_count }} valid citations @if($run->invalid_citations_count) · {{ $run->invalid_citations_count }} invalid @endif</small></td>
                    <td><div class="bd-admin-table__title"><strong>{{ strtoupper($run->model_provider ?: '—') }}</strong><small>{{ $run->model_name ?: '—' }}</small></div></td>
                    <td><span class="bd-admin-status bd-admin-status--{{ in_array($run->status, ['completed','evidence_ready'], true) ? 'success' : (in_array($run->status, ['failed','citation_failed'], true) ? 'failed' : (in_array($run->status, ['insufficient_evidence'], true) ? 'warning' : 'processing')) }}">{{ str($run->status)->replace('_',' ')->title() }}</span></td>
                    <td><a class="bd-admin-table__action" href="{{ route('admin.ai-runs.show', $run) }}">Trace →</a></td>
                </tr>
            @empty
                <tr><td colspan="7"><div class="bd-admin-empty"><div class="bd-admin-empty__inner"><span class="bd-admin-empty__mark">RUN</span><h3>Belum ada AI Run.</h3><p>Execution akan muncul setelah user menjalankan Research Tool.</p></div></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{ $runs->links() }}
</section>
@endsection
