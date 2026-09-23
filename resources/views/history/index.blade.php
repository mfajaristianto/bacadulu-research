@extends('layouts.app')
@section('title', 'Research History — BacaDulu Research')
@section('content')
<section class="history-page">
    <div class="dashboard-head" data-reveal><div><p class="eyebrow">RESEARCH HISTORY</p><h1>Recent research.</h1><p>Research runs milik akun Anda tersimpan di sini.</p></div><a href="{{ route('workspace') }}" class="btn btn-primary">New Research <span>↗</span></a></div>
    <div class="admin-table-wrap" data-reveal>
        <table class="admin-table"><thead><tr><th>Research tool</th><th>Status</th><th>Started</th><th>Model</th></tr></thead><tbody>
        @forelse($runs as $run)
            <tr><td><strong>{{ $run->aiTool?->name ?: 'Research tool' }}</strong><small>{{ Str::limit($run->uuid, 22) }}</small></td><td><span class="status-pill status-{{ $run->status }}">{{ ucfirst($run->status) }}</span></td><td>{{ optional($run->started_at)->format('d M Y H:i') }}</td><td>{{ $run->model_name ?: 'Not configured' }}</td></tr>
        @empty
            <tr><td colspan="4"><div class="admin-empty">No research runs yet. Start your first research conversation.</div></td></tr>
        @endforelse
        </tbody></table>
    </div>
    {{ $runs->links() }}
</section>
@endsection
