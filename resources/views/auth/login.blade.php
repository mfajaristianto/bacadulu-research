@extends('layouts.app')
@section('title', 'Login — BacaDulu Research')
@section('content')
<section class="auth-page">
    <div class="auth-shell">
        <aside class="auth-context" data-reveal>
            <p class="eyebrow">RESEARCH WORKSPACE</p>
            <h1>Continue where your evidence left off.</h1>
            <p>Masuk untuk membuka workspace, sumber internal, dan riwayat research milik akun Anda.</p>
            <div class="auth-proof"><span>01</span><div><strong>Internal library</strong><small>Sumber yang eligible saja yang dapat digunakan AI.</small></div></div>
            <div class="auth-proof"><span>02</span><div><strong>Prepared prompts</strong><small>Research task dikendalikan prompt internal, bukan instruksi liar.</small></div></div>
        </aside>

        <div class="auth-card" data-reveal>
            <p class="eyebrow">WELCOME BACK</p>
            <h2>Sign in to Research.</h2>
            <p class="auth-subtitle">Gunakan akun Google atau email yang sudah terdaftar.</p>

            @if($errors->any())<div class="alert alert-error" role="alert">{{ $errors->first() }}</div>@endif

            <a href="{{ route('google.redirect') }}" class="google-btn"><span class="google-mark">G</span> Continue with Google</a>
            <div class="auth-divider"><span>or use email</span></div>

            <form method="POST" action="{{ route('login.store') }}" class="form-stack">
                @csrf
                <label>Email address<input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required autofocus></label>
                <label>Password
                    <span class="password-field">
                        <input type="password" name="password" autocomplete="current-password" required data-password-input>
                        <button type="button" class="password-toggle" aria-label="Show password" data-password-toggle>Show</button>
                    </span>
                </label>
                <label class="check-row"><input type="checkbox" name="remember" value="1"><span>Keep me signed in on this device</span></label>
                <button class="btn btn-primary btn-full" type="submit">Login <span>↗</span></button>
            </form>

            <p class="auth-foot">Belum punya akun? <a href="{{ route('register') }}">Daftar sekarang</a></p>
        </div>
    </div>
</section>
@endsection
