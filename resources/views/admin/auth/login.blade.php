@extends('layouts.admin-auth')

@section('title', 'Admin Login')

@section('content')

<div class="bd-admin-login">

    <header class="bd-admin-login__head">
        <div class="bd-admin-login__micro">
            <span>
                ADMIN ACCESS
            </span>

            <span>
                01 / 01
            </span>
        </div>

        <h2>
            Masuk ke Research Console.
        </h2>

        <p class="bd-admin-login__copy">
            Gunakan akun administrator BacaDulu Research.
            Area ini terpisah dari akun researcher.
        </p>
    </header>

    @if ($errors->any())
        <div
            class="bd-admin-alert bd-admin-alert--error"
            role="alert"
        >
            <strong>
                Login belum berhasil.
            </strong>

            <span>
                {{ $errors->first() }}
            </span>
        </div>
    @endif

    @if (session('success'))
        <div
            class="bd-admin-alert bd-admin-alert--success"
            role="status"
        >
            {{ session('success') }}
        </div>
    @endif

    <form
        method="POST"
        action="{{ route('admin.login.store') }}"
        class="bd-admin-login__form"
    >
        @csrf

        <div class="bd-admin-field">
            <label for="admin-email">
                Email administrator
            </label>

            <div class="bd-admin-field__control">
                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                    aria-hidden="true"
                >
                    <rect
                        x="3"
                        y="5"
                        width="18"
                        height="14"
                        rx="2"
                    />

                    <path d="M4 7l8 6 8-6"/>
                </svg>

                <input
                    id="admin-email"
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    autocomplete="username"
                    inputmode="email"
                    placeholder="admin@bacadulu.net"
                    maxlength="190"
                    required
                    autofocus
                    @if($errors->has('email'))
                        aria-invalid="true"
                    @endif
                >
            </div>
        </div>

        <div class="bd-admin-field">
            <label for="admin-password">
                Kata sandi
            </label>

            <div
                class="
                    bd-admin-field__control
                    bd-admin-field__control--password
                "
            >
                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                    aria-hidden="true"
                >
                    <rect
                        x="5"
                        y="10"
                        width="14"
                        height="10"
                        rx="2"
                    />

                    <path
                        d="M8 10V7a4 4 0 0 1 8 0v3"
                    />
                </svg>

                <input
                    id="admin-password"
                    type="password"
                    name="password"
                    autocomplete="current-password"
                    placeholder="Masukkan kata sandi"
                    maxlength="128"
                    required
                    @if($errors->has('password'))
                        aria-invalid="true"
                    @endif
                >

                <button
                    type="button"
                    class="bd-admin-field__toggle"
                    data-admin-password-toggle
                    aria-controls="admin-password"
                    aria-pressed="false"
                >
                    Lihat
                </button>
            </div>
        </div>

        <button
            type="submit"
            class="bd-admin-login__submit"
        >
            <span>
                Masuk ke Console
            </span>

            <span aria-hidden="true">
                →
            </span>
        </button>
    </form>

    <div class="bd-admin-login__note">
        <span class="bd-admin-login__note-mark"></span>

        <p>
            Akses administrator dicatat untuk kebutuhan audit dan keamanan.
        </p>
    </div>

</div>

@endsection