@extends('layouts.app')

@section('title', 'Buat Kata Sandi Baru — BacaDulu Research')

@section('content')
<section class="br-password-page">
    <div class="br-password-card">
        <a href="{{ route('home') }}" class="br-password-card__brand">
            <span>BacaDulu</span> Research
        </a>

        <h1>Buat kata sandi baru</h1>
        <p>
            Gunakan minimal 12 karakter dengan huruf besar, huruf kecil, angka,
            dan simbol agar akun lebih aman.
        </p>

        @if($errors->any())
            <p class="br-password-card__error" role="alert">{{ $errors->first() }}</p>
        @endif

        <form method="POST" action="{{ route('password.update') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <label>
                <span>Email akun</span>
                <input
                    type="email"
                    name="email"
                    value="{{ old('email', $email) }}"
                    autocomplete="email"
                    required
                >
            </label>

            <label>
                <span>Kata sandi baru</span>
                <input
                    type="password"
                    name="password"
                    minlength="12"
                    autocomplete="new-password"
                    required
                >
            </label>

            <label>
                <span>Ulangi kata sandi baru</span>
                <input
                    type="password"
                    name="password_confirmation"
                    minlength="12"
                    autocomplete="new-password"
                    required
                >
            </label>

            <button type="submit">Simpan kata sandi baru</button>
        </form>

        <a href="{{ route('login') }}" class="br-password-card__back">← Kembali ke Masuk</a>
    </div>
</section>
@endsection
