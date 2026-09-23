@extends('layouts.app')
@section('title', 'Create Account — BacaDulu Research')
@section('content')
<section class="auth-page">
    <div class="auth-shell">
        <aside class="auth-context" data-reveal>
            <p class="eyebrow">CREATE RESEARCH ACCOUNT</p>
            <h1>Build a profile before entering the workspace.</h1>
            <p>Identitas dasar membantu BacaDulu Research menjaga akses internal library lebih bertanggung jawab.</p>
            <div class="auth-proof"><span>{{ config('research.profile_gate', 80) }}%</span><div><strong>Profile gate</strong><small>Lengkapi profil minimum sebelum research workspace terbuka.</small></div></div>
            <div class="auth-proof"><span>AI</span><div><strong>Evidence grounded</strong><small>AI diarahkan menggunakan sumber yang memang tersedia di platform.</small></div></div>
        </aside>

        <div class="auth-card" data-reveal>
            <p class="eyebrow">NEW ACCOUNT</p>
            <h2>Start your research journey.</h2>
            <p class="auth-subtitle">Setelah registrasi, lengkapi profil untuk membuka fitur research.</p>

            @if($errors->any())<div class="alert alert-error" role="alert">{{ $errors->first() }}</div>@endif

            <a href="{{ route('google.redirect') }}" class="google-btn"><span class="google-mark">G</span> Sign up with Google</a>
            <div class="auth-divider"><span>or use email</span></div>

            <form method="POST" action="{{ route('register.store') }}" class="form-stack">
                @csrf
                <label>Full name<input type="text" name="name" value="{{ old('name') }}" autocomplete="name" required></label>
                <label>Email address<input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required></label>
                <label>Password
                    <span class="password-field"><input type="password" name="password" autocomplete="new-password" required data-password-input><button type="button" class="password-toggle" aria-label="Show password" data-password-toggle>Show</button></span>
                </label>
                <label>Confirm password<input type="password" name="password_confirmation" autocomplete="new-password" required></label>
                <button class="btn btn-primary btn-full" type="submit">Create Account <span>↗</span></button>
            </form>

            <p class="auth-foot">Sudah punya akun? <a href="{{ route('login') }}">Login</a></p>
        </div>
    </div>
</section>
@endsection
