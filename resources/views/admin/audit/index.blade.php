@extends('layouts.admin')

@section('title', 'Audit Logs')
@section('page_title', 'Audit Logs')

@section('content')
<section class="admin-page">
    <header class="bd-admin-page-head">
        <div class="bd-admin-page-head__copy"><p class="bd-admin-kicker">SYSTEM / AUDIT</p><h1>Audit Logs</h1><p>Jejak administratif dan perubahan operasional penting. Read-only.</p></div>
    </header>

    <form method="GET" class="bd-admin-filter">
        <input class="bd-admin-input" type="search" name="search" value="{{ request('search') }}" placeholder="Action, user, IP…">
        <select class="bd-admin-select" name="action" data-auto-submit><option value="">Semua action</option>@foreach($actions as $action)<option value="{{ $action }}" @selected(request('action') === $action)>{{ $action }}</option>@endforeach</select>
        <span></span>
    </form>

    <div class="bd-admin-table-wrap">
        <table class="bd-admin-table">
            <thead><tr><th>Time</th><th>Actor</th><th>Action</th><th>Target</th><th>IP</th></tr></thead>
            <tbody>
            @forelse($logs as $log)
                <tr>
                    <td>{{ optional($log->created_at)->format('d M Y · H:i:s') }}</td>
                    <td><div class="bd-admin-table__title"><strong>{{ $log->user?->name ?: 'System / Admin' }}</strong><small>{{ $log->user?->email ?: '—' }}</small></div></td>
                    <td><code>{{ $log->action }}</code></td>
                    <td>{{ $log->auditable_type ? class_basename($log->auditable_type).' #'.($log->auditable_id ?: '—') : '—' }}</td>
                    <td>{{ $log->ip_address ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5"><div class="bd-admin-empty"><div class="bd-admin-empty__inner"><span class="bd-admin-empty__mark">LOG</span><h3>Belum ada audit event.</h3><p>Event admin baru akan muncul setelah login dan operasi source berikutnya.</p></div></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{ $logs->links() }}
</section>
@endsection
