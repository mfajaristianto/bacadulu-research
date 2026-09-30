@extends('layouts.app')

@section(
    'title',
    'Hubungkan Google — BacaDulu Research'
)

@section(
    'meta_description',
    'Konfirmasi akun BacaDulu Research sebelum menghubungkan Google.'
)

@section('content')

<section
    class="
        research-auth
        research-auth--link
    "
    data-auth-portal
>

    <div
        class="research-auth__ambient"
        aria-hidden="true"
    >
        <span
            class="
                research-auth__orb
                research-auth__orb--one
            "
        ></span>

        <span
            class="
                research-auth__orb
                research-auth__orb--two
            "
        ></span>

        <span
            class="
                research-auth__orb
                research-auth__orb--three
            "
        ></span>
    </div>


    <div class="research-auth__page">

        <div class="research-auth__topline">

            <a
                href="{{ route('login') }}"
                class="research-auth__back"
            >
                <span aria-hidden="true">
                    ←
                </span>

                Kembali ke Masuk
            </a>

        </div>


        <div class="research-auth__frame">

            <header class="research-auth__header">

                <a
                    href="{{ route('home') }}"
                    class="research-auth__brand"
                >

                    <span>
                        BacaDulu
                    </span>

                    <strong>
                        Research
                    </strong>

                </a>


                <span
                    style="
                        color:#7b7780;
                        font-size:10px;
                        font-weight:700;
                    "
                >
                    SECURE ACCOUNT LINKING
                </span>

            </header>


            <div class="research-auth__stage">

                {{-- =============================================
                     VISUAL
                ============================================== --}}

                <aside class="research-auth__visual">

                    <div class="research-auth__visual-grid"></div>


                    <div class="research-auth__visual-copy">

                        <p class="research-auth__eyebrow">
                            ACCOUNT SECURITY
                        </p>

                        <h1>
                            Satu akun,
                            <span>
                                dua cara masuk.
                            </span>
                        </h1>

                        <p>
                            Email Google Anda sudah terdaftar
                            sebagai akun BacaDulu Research.
                            Konfirmasi kepemilikan akun sebelum
                            Google dihubungkan.
                        </p>

                    </div>


                    <div
                        style="
                            position:absolute;
                            left:50%;
                            bottom:70px;
                            width:min(74%,360px);
                            transform:translateX(-50%);
                            display:grid;
                            gap:14px;
                        "
                    >

                        <div
                            style="
                                padding:15px;
                                border:1px solid rgba(23,23,43,.1);
                                border-radius:12px;
                                background:rgba(255,255,255,.93);
                                box-shadow:0 18px 42px rgba(23,23,43,.08);
                            "
                        >

                            <small
                                style="
                                    color:#918c86;
                                    font-size:7px;
                                    font-weight:800;
                                    letter-spacing:.12em;
                                "
                            >
                                EXISTING ACCOUNT
                            </small>

                            <strong
                                style="
                                    display:block;
                                    margin-top:5px;
                                    color:#17172b;
                                    font-size:12px;
                                "
                            >
                                {{ $user->email }}
                            </strong>

                        </div>


                        <div
                            style="
                                display:grid;
                                grid-template-columns:1fr 34px 1fr;
                                align-items:center;
                                gap:10px;
                            "
                        >

                            <div
                                style="
                                    padding:12px;
                                    text-align:center;
                                    border:1px solid #ddd7ce;
                                    border-radius:10px;
                                    background:white;
                                    font-size:10px;
                                    font-weight:750;
                                "
                            >
                                BacaDulu Account
                            </div>


                            <div
                                style="
                                    text-align:center;
                                    color:#f26b4f;
                                    font-weight:900;
                                "
                            >
                                ↔
                            </div>


                            <div
                                style="
                                    padding:12px;
                                    text-align:center;
                                    border:1px solid #ddd7ce;
                                    border-radius:10px;
                                    background:white;
                                    font-size:10px;
                                    font-weight:750;
                                "
                            >
                                Google
                            </div>

                        </div>

                    </div>

                </aside>


                {{-- =============================================
                     FORM
                ============================================== --}}

                <section class="research-auth__form-panel">

                    <div class="research-auth__form-inner">

                        <div class="research-auth__form-heading">

                            <p class="research-auth__eyebrow">
                                VERIFIKASI SEKALI
                            </p>

                            <h2>
                                Hubungkan akun Google.
                            </h2>

                            <p>
                                Masukkan password BacaDulu Anda.
                                Setelah berhasil, login Google
                                berikutnya tidak memerlukan
                                password ini lagi.
                            </p>

                        </div>


                        <div
                            style="
                                margin-bottom:18px;
                                padding:11px 12px;
                                border:1px solid rgba(33,166,160,.16);
                                border-radius:8px;
                                background:rgba(33,166,160,.05);
                            "
                        >

                            <small
                                style="
                                    display:block;
                                    color:#77727b;
                                    font-size:8px;
                                    font-weight:700;
                                "
                            >
                                GOOGLE EMAIL
                            </small>

                            <strong
                                style="
                                    display:block;
                                    margin-top:3px;
                                    color:#17172b;
                                    font-size:11px;
                                "
                            >
                                {{ $pendingGoogleEmail }}
                            </strong>

                        </div>


                        @if ($errors->any())

                            <div
                                class="alert alert-error"
                                role="alert"
                            >
                                {{ $errors->first() }}
                            </div>

                        @endif


                        <form
                            method="POST"
                            action="{{
                                route(
                                    'google.link.store'
                                )
                            }}"
                            class="research-auth__form"
                        >

                            @csrf


                            <label for="google-link-password">

                                <span>
                                    Password BacaDulu
                                </span>


                                <div
                                    class="
                                        research-auth__input-wrap
                                        password-field
                                    "
                                >

                                    <span
                                        class="research-auth__input-icon"
                                        aria-hidden="true"
                                    >

                                        <svg
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="1.7"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        >

                                            <rect
                                                x="5"
                                                y="10"
                                                width="14"
                                                height="10"
                                                rx="2"
                                            />

                                            <path
                                                d="M8 10V7a4 4 0 018 0v3"
                                            />

                                        </svg>

                                    </span>


                                    <input
                                        id="google-link-password"
                                        type="password"
                                        name="password"
                                        autocomplete="current-password"
                                        placeholder="Masukkan password"
                                        required
                                        autofocus
                                        data-password-input
                                    >


                                    <button
                                        type="button"
                                        class="
                                            password-toggle
                                            research-auth__password-toggle
                                        "
                                        data-password-toggle
                                    >
                                        Show
                                    </button>

                                </div>


                                @error('password')

                                    <span class="field-error">
                                        {{ $message }}
                                    </span>

                                @enderror

                            </label>


                            <button
                                type="submit"
                                class="research-auth__submit"
                            >

                                <span>
                                    Hubungkan Google
                                </span>

                                <span aria-hidden="true">
                                    →
                                </span>

                            </button>

                        </form>


                        <p class="research-auth__switch-copy">

                            Tidak ingin menghubungkan Google?

                            <a href="{{ route('login') }}">
                                Login dengan email
                            </a>

                        </p>

                    </div>

                </section>

            </div>

        </div>


        <footer class="research-auth__footer">

            <span>
                © {{ now()->year }}
                BacaDulu Research
            </span>

            <span>
                Secure account linking
            </span>

        </footer>

    </div>

</section>

@endsection