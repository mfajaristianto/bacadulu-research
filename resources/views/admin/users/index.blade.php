@extends('layouts.admin')

@section('title', 'Users')
@section('page_title', 'Users')

@section('content')
<section class="admin-page">
    <header class="bd-admin-page-head">
        <div class="bd-admin-page-head__copy">
            <p class="bd-admin-kicker">ACCESS / USERS</p>
            <h1>Research Users</h1>
            <p>Lihat akun yang terdaftar, kelengkapan profile, aktivitas research, dan status akses.</p>
        </div>
    </header>

    <form method="GET" class="bd-admin-filter">
        <input class="bd-admin-input" type="search" name="search" value="{{ request('search') }}" placeholder="Nama, email, institusi…">
        <select class="bd-admin-select" name="status" onchange="this.form.submit()"><option value="">Semua status</option><option value="active" @selected(request('status') === 'active')>Active</option><option value="suspended" @selected(request('status') === 'suspended')>Suspended</option></select>
        <span></span>
    </form>

    <div class="bd-admin-table-wrap">
        <table class="bd-admin-table">
            <thead><tr><th>User</th><th>Institusi</th><th>Profile</th><th>Research</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($users as $user)
                <tr>
                    <td><div class="bd-admin-table__title"><strong>{{ $user->name }}</strong><small>{{ $user->email }}</small></div></td>
                    <td>{{ $user->institution ?: '—' }}</td>
                    <td>{{ $user->profileCompletion() }}%</td>
                    <td>{{ $user->ai_runs_count }} runs · {{ $user->conversations_count }} conversations</td>
                    <td><span class="bd-admin-status bd-admin-status--{{ $user->status }}">{{ ucfirst($user->status) }}</span></td>
                    <td><a class="bd-admin-table__action" href="{{ route('admin.users.show', $user) }}">Detail →</a></td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="bd-admin-empty"><div class="bd-admin-empty__inner"><span class="bd-admin-empty__mark">USR</span><h3>Tidak ada user.</h3></div></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{ $users->links() }}
</section>
@endsection
