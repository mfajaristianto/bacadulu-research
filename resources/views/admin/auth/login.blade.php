@extends('layouts.admin-auth')

@section('title', 'Login Admin')

@section('content')
<aside class="bd-admin-auth__aside">
    {{-- Walk and wave are the only assets. The wave PNG is drawn twice: once with its built-in
         bubble masked out, once as the bubble alone, so the greeting can be revealed after the wave. --}}
    <div class="bd-admin-auth__stage">
        <div
            class="bd-admin-auth__guardian"
            data-guardian
            data-guardian-state="hidden"
            role="img"
            aria-label="Bubu, penjaga admin BacaDulu, menyapa: Hai, Admin BacaDulu!"
        >
            <div class="bd-admin-auth__run" data-guardian-run>
                <span class="bd-admin-auth__shadow" data-guardian-shadow></span>
                <div class="bd-admin-auth__bob" data-guardian-bob>
                    <img class="bd-admin-auth__walk" src="{{ asset('assets/mascot/mascot-admin-guardian-walk.png') }}" alt="" width="611" height="778" decoding="async">
                    <img class="bd-admin-auth__wave" src="{{ asset('assets/mascot/mascot-admin-guardian-wave.png') }}" alt="" width="899" height="823" decoding="async">
                    <div class="bd-admin-auth__bubble" data-guardian-bubble>
                        <img src="{{ asset('assets/mascot/mascot-admin-guardian-wave.png') }}" alt="" width="899" height="823" decoding="async">
                    </div>
                </div>
            </div>
        </div>
    </div>
    {{-- Module status only (not navigation): revealed one by one after the greeting by initAdminGuardian(). --}}
    <div class="bd-admin-auth__console">
        <p class="bd-admin-auth__tagline" data-console-title>Research Management Console</p>
        <ul class="bd-admin-auth__scope" aria-label="Status modul admin">
            @foreach (['Library', 'Prompt', 'Research Tools'] as $module)
                <li data-console-module>
                    {{ $module }}
                    <svg class="bd-admin-auth__check" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M3.5 8.5l3 3 6-7" />
                    </svg>
                </li>
            @endforeach
        </ul>
        <div class="bd-admin-auth__console-foot">
            <p class="bd-admin-auth__status" data-console-status>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 3l7 3v5c0 4.5-3 8-7 10-4-2-7-5.5-7-10V6l7-3z" />
                    <path d="M9 12l2 2 4-4" />
                </svg>
                Admin Console Ready
            </p>
            <button
                type="button"
                class="bd-admin-auth__replay"
                data-guardian-replay
                aria-label="Putar ulang animasi Bubu Admin"
                title="Ulangi animasi Bubu"
                aria-disabled="true"
            >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M20 12a8 8 0 1 1-2.6-5.9" />
                    <path d="M20 4v5h-5" />
                </svg>
            </button>
        </div>
    </div>
</aside>

<section class="bd-admin-login" id="admin-auth-form">
    <header class="bd-admin-login__head">
        <h1>Masuk ke Admin BacaDulu</h1>
        <p class="bd-admin-login__copy">
            Kelola library, research tools, prompt, dan operasional BacaDulu Research.
        </p>
    </header>

    @if ($errors->any())
        <div class="bd-admin-alert bd-admin-alert--error" role="alert" id="admin-login-error">
            <strong>Login belum berhasil.</strong>
            <span>{{ $errors->first() }}</span>
        </div>
    @endif

    @if (session('success'))
        <div class="bd-admin-alert bd-admin-alert--success" role="status">
            {{ session('success') }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.login.store') }}" class="bd-admin-login__form">
        @csrf

        <div class="bd-admin-field">
            <label for="admin-email">Email administrator</label>
            <div class="bd-admin-field__control">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <rect x="3" y="5" width="18" height="14" rx="2" />
                    <path d="M4 7l8 6 8-6" />
                </svg>
                <input
                    id="admin-email"
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    autocomplete="username"
                    inputmode="email"
                    placeholder="nama@email.com"
                    maxlength="190"
                    required
                    autofocus
                    @if ($errors->has('email')) aria-invalid="true" aria-describedby="admin-login-error" @endif
                >
            </div>
        </div>

        <div class="bd-admin-field">
            <label for="admin-password">Kata sandi</label>
            <div class="bd-admin-field__control bd-admin-field__control--password">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <rect x="5" y="10" width="14" height="10" rx="2" />
                    <path d="M8 10V7a4 4 0 0 1 8 0v3" />
                </svg>
                <input
                    id="admin-password"
                    type="password"
                    name="password"
                    autocomplete="current-password"
                    placeholder="Masukkan kata sandi"
                    maxlength="128"
                    required
                    @if ($errors->has('password')) aria-invalid="true" @endif
                >
                <button
                    type="button"
                    class="bd-admin-field__toggle"
                    data-admin-password-toggle
                    aria-controls="admin-password"
                    aria-pressed="false"
                >Lihat</button>
            </div>
        </div>

        <button type="submit" class="bd-admin-login__submit">Masuk Admin</button>
    </form>

    <p class="bd-admin-login__note">
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <rect x="5" y="10" width="14" height="10" rx="2" />
            <path d="M8 10V7a4 4 0 0 1 8 0v3" />
        </svg>
        Akses terbatas untuk administrator.
    </p>
</section>
@endsection
