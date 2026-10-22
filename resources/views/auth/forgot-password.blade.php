@extends('layouts.app')

@section('title', 'Lupa Kata Sandi — BacaDulu Research')

@section('content')
<section class="br-password-page">
    <div class="br-password-card">
        <a href="{{ route('home') }}" class="br-password-card__brand">
            <span>BacaDulu</span> Research
        </a>

        <h1>Lupa kata sandi?</h1>
        <p>
            Masukkan email akun Anda. Kami akan mengirim tautan reset ke email tersebut.
            Tautan ini menjadi verifikasi bahwa akun memang milik Anda.
        </p>

        @if(session('status'))
            <p class="br-password-card__message" role="status">{{ session('status') }}</p>
        @endif

        @error('email')
            <p class="br-password-card__error" role="alert">{{ $message }}</p>
        @enderror

        <form method="POST" action="{{ route('password.email') }}">
            @csrf

            <label>
                <span>Email akun</span>
                <input
                    type="email"
                    name="email"
                    value="{{ old('email', $email) }}"
                    autocomplete="email"
                    required
                    autofocus
                >
            </label>

            <button type="submit">Kirim tautan reset</button>
        </form>

        <a
            href="{{ auth()->check() ? route('profile.edit') : route('login') }}"
            class="br-password-card__back"
        >
            ← {{ auth()->check() ? 'Kembali ke Profil' : 'Kembali ke Masuk' }}
        </a>
    </div>
</section>
@endsection
