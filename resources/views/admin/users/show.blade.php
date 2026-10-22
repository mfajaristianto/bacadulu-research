@extends('layouts.admin')

@section('title', 'User Detail')
@section('page_title', 'Users')

@section('content')
<section class="admin-page">
    <header class="bd-admin-page-head">
        <div class="bd-admin-page-head__copy">
            <p class="bd-admin-kicker">ACCESS / USER</p>
            <h1>{{ $user->name }}</h1>
            <p>{{ $user->email }} · User #{{ $user->id }}</p>
        </div>
        <div class="bd-admin-page-head__actions"><a href="{{ route('admin.users.index') }}" class="bd-admin-button bd-admin-button--ghost">← Users</a></div>
    </header>

    <div class="bd-admin-panels">
        <article class="bd-admin-panel"><small>PROFILE COMPLETION</small><strong>{{ $user->profileCompletion() }}%</strong><p>{{ implode(', ', $user->missingProfileFields()) ?: 'Profile minimum lengkap.' }}</p></article>
        <article class="bd-admin-panel"><small>AI RUNS</small><strong>{{ $user->ai_runs_count }}</strong><p>Total execution yang tercatat.</p></article>
        <article class="bd-admin-panel"><small>CONVERSATIONS</small><strong>{{ $user->conversations_count }}</strong><p>Research conversations.</p></article>
    </div>

    <section class="bd-admin-section">
        <div class="bd-admin-two-column">
            <div class="bd-admin-detail-list">
                <div><span>Email</span><strong>{{ $user->email }}</strong></div>
                <div><span>Institusi</span><strong>{{ $user->institution ?: '—' }}</strong></div>
                <div><span>Peran</span><strong>{{ $user->role ?? $user->occupation ?? '—' }}</strong></div>
                <div><span>Domisili</span><strong>{{ $user->domicile_city ?? $user->address ?? '—' }}</strong></div>
                <div><span>Email verified</span><strong>{{ $user->email_verified_at ? 'Yes' : 'No' }}</strong></div>
                <div><span>Last login</span><strong>{{ optional($user->last_login_at)->format('d M Y · H:i') ?: '—' }}</strong></div>
                <div><span>Status</span><strong><span class="bd-admin-status bd-admin-status--{{ $user->status }}">{{ ucfirst($user->status) }}</span></strong></div>
            </div>

            <aside class="bd-admin-form__aside">
                <section><strong>Access Control</strong><p>Suspend memblokir user dari area Research tanpa menghapus data historis.</p></section>
                <section>
                    <form method="POST" action="{{ route('admin.users.status', $user) }}">
                        @csrf @method('PATCH')
                        <input type="hidden" name="status" value="{{ $user->status === 'active' ? 'suspended' : 'active' }}">
                        <button class="bd-admin-button {{ $user->status === 'active' ? 'bd-admin-button--danger' : 'bd-admin-button--primary' }}" type="submit">{{ $user->status === 'active' ? 'Suspend User' : 'Aktifkan User' }}</button>
                    </form>
                </section>
            </aside>
        </div>
    </section>
</section>
@endsection
