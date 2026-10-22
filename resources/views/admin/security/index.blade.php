@extends('layouts.admin')

@section('title', 'Security')
@section('page_title', 'Security')

@section('content')
<section class="admin-page">
    <header class="bd-admin-page-head">
        <div class="bd-admin-page-head__copy">
            <p class="bd-admin-kicker">SYSTEM / SECURITY</p>
            <h1>Security</h1>
            <p>Status konfigurasi keamanan admin tanpa pernah menampilkan password atau secret key.</p>
        </div>
    </header>

    <div class="bd-admin-panels">
        <article class="bd-admin-panel"><small>ADMIN CREDENTIAL</small><strong>{{ $security['admin_email_configured'] && $security['admin_password_configured'] ? 'Configured' : 'Incomplete' }}</strong><p>{{ $security['admin_password_hashed'] ? 'Password admin tersimpan sebagai hash (ADMIN_PASSWORD_HASH).' : 'Password admin masih plaintext. Jalankan php artisan research:admin-password-hash lalu isi ADMIN_PASSWORD_HASH.' }}</p></article>
        <article class="bd-admin-panel"><small>SESSION</small><strong>{{ strtoupper($security['session_driver']) }}</strong><p>SameSite {{ $security['same_site'] }} · Secure cookie {{ $security['secure_cookie'] ? 'on' : 'off' }}.</p></article>
        <article class="bd-admin-panel"><small>APP ENV</small><strong>{{ strtoupper($security['environment']) }}</strong><p>Debug {{ $security['debug'] ? 'ON' : 'OFF' }}.</p></article>
    </div>

    <section class="bd-admin-section">
        <div class="bd-admin-section__head"><div><h2>Security Configuration</h2><p>Nilai aman yang boleh dilihat administrator.</p></div></div>
        <div class="bd-admin-detail-list">
            <div><span>Admin path</span><strong>/{{ $security['admin_path'] }}</strong></div>
            <div><span>Email verification user</span><strong>{{ $security['email_verification'] ? 'Required' : 'Optional' }}</strong></div>
            <div><span>Current request IP</span><strong>{{ $security['current_ip'] }}</strong></div>
            <div><span>Login throttle</span><strong>Enabled</strong></div>
            <div><span>Security headers</span><strong>Enabled</strong></div>
        </div>
    </section>

    <section class="bd-admin-section">
        <div class="bd-admin-section__head"><div><h2>Recent Admin Events</h2><p>Login, logout, dan login gagal.</p></div></div>
        <div class="bd-admin-table-wrap">
            <table class="bd-admin-table">
                <thead><tr><th>Time</th><th>Action</th><th>IP</th><th>Metadata</th></tr></thead>
                <tbody>
                @forelse($recentAdminEvents as $event)
                    <tr>
                        <td>{{ optional($event->created_at)->format('d M Y · H:i:s') }}</td>
                        <td><code>{{ $event->action }}</code></td>
                        <td>{{ $event->ip_address ?: '—' }}</td>
                        <td>{{ data_get($event->metadata_json, 'email', '—') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4">Belum ada admin security event.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</section>
@endsection
