@extends('layouts.app')

@section('title', 'Daftar — BacaDulu Research')

@section(
    'meta_description',
    'Buat akun BacaDulu Research untuk mulai bekerja dengan research tools, curated sources, evidence, dan workspace penelitian.'
)

@section('content')

<section
    class="research-auth-final research-auth-final--register"
    data-research-auth
    data-auth-page="register"
>
    <div
        class="research-auth-final__scene"
        aria-hidden="true"
    >
        <span class="research-auth-final__blob research-auth-final__blob--coral"></span>
        <span class="research-auth-final__blob research-auth-final__blob--teal"></span>
        <span class="research-auth-final__blob research-auth-final__blob--blue"></span>
        <span class="research-auth-final__blob research-auth-final__blob--peach"></span>

        <span class="research-auth-final__bubble research-auth-final__bubble--one"></span>
        <span class="research-auth-final__bubble research-auth-final__bubble--two"></span>
        <span class="research-auth-final__bubble research-auth-final__bubble--three"></span>
        <span class="research-auth-final__bubble research-auth-final__bubble--four"></span>
    </div>


    <div class="research-auth-final__page">

        <div class="research-auth-final__topbar">

            <a
                href="{{ route('home') }}"
                class="research-auth-final__back"
            >
                <svg
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <path d="M19 12H5"/>
                    <path d="M11 18l-6-6 6-6"/>
                </svg>

                <span>
                    Kembali ke situs
                </span>
            </a>

        </div>


        <div class="research-auth-final__card">

            <header class="research-auth-final__header">

                <a
                    href="{{ route('home') }}"
                    class="research-auth-final__brand"
                    aria-label="BacaDulu Research"
                >
                    <span>
                        BacaDulu
                    </span>

                    <strong>
                        Research
                    </strong>
                </a>


                <nav
                    class="research-auth-final__tabs"
                    aria-label="Autentikasi"
                >

                    <a
                        href="{{ route('login') }}"
                        class="research-auth-final__tab"
                    >
                        Masuk
                    </a>

                    <a
                        href="{{ route('register') }}"
                        class="research-auth-final__tab is-active"
                        aria-current="page"
                    >
                        Daftar
                    </a>

                </nav>

            </header>


            <div class="research-auth-final__body">

                {{-- =================================================
                     LEFT
                ================================================== --}}
                <section
                    class="research-auth-final__visual"
                    data-auth-visual
                >

                    <div
                        class="research-auth-final__visual-grid"
                        data-auth-grid
                        aria-hidden="true"
                    ></div>


                    <div class="research-auth-final__hero">

                        <div class="research-auth-final__intro">

                            <p class="research-auth-final__eyebrow">
                                MULAI DI BACADULU RESEARCH
                            </p>

                            <h1>
                                Buat akun,
                                <span>
                                    mulai jelajah riset.
                                </span>
                            </h1>

                            <p>
                                Dapatkan akses ke research tools,
                                curated sources, evidence, dan
                                workspace untuk mendukung pekerjaan penelitian.
                            </p>

                        </div>


                        {{-- JOURNEY --}}
                        <div
                            class="research-auth-final__journey"
                            data-auth-journey
                        >

                            <div
                                class="research-auth-final__journey-line"
                                aria-hidden="true"
                            >
                                <span
                                    data-auth-journey-progress
                                ></span>
                            </div>


                            <article data-auth-journey-step>
                                <i></i>

                                <small>
                                    01
                                </small>

                                <strong>
                                    Daftar
                                </strong>
                            </article>


                            <article data-auth-journey-step>
                                <i></i>

                                <small>
                                    02
                                </small>

                                <strong>
                                    Verifikasi
                                </strong>
                            </article>


                            <article data-auth-journey-step>
                                <i></i>

                                <small>
                                    03
                                </small>

                                <strong>
                                    Atur profil
                                </strong>
                            </article>


                            <article data-auth-journey-step>
                                <i></i>

                                <small>
                                    04
                                </small>

                                <strong>
                                    Mulai riset
                                </strong>
                            </article>

                        </div>


                        {{-- RESEARCH FLOW --}}
                        <div
                            class="research-auth-final__research-flow"
                            data-auth-flow
                        >

                            <svg
                                class="research-auth-final__flow-svg"
                                viewBox="0 0 440 235"
                                preserveAspectRatio="none"
                                aria-hidden="true"
                            >

                                <path
                                    data-auth-route
                                    d="M88 42 C132 55 158 77 194 103"
                                />

                                <path
                                    data-auth-route
                                    d="M352 42 C308 55 282 77 246 103"
                                />

                                <path
                                    data-auth-route
                                    d="M204 145 C166 169 128 188 87 198"
                                />

                                <path
                                    data-auth-route
                                    d="M236 145 C274 169 312 188 353 198"
                                />


                                <circle
                                    data-auth-packet
                                    data-start-x="88"
                                    data-start-y="42"
                                    data-end-x="194"
                                    data-end-y="103"
                                    cx="88"
                                    cy="42"
                                    r="4"
                                />

                                <circle
                                    data-auth-packet
                                    data-start-x="352"
                                    data-start-y="42"
                                    data-end-x="246"
                                    data-end-y="103"
                                    cx="352"
                                    cy="42"
                                    r="4"
                                />

                                <circle
                                    data-auth-packet
                                    data-start-x="220"
                                    data-start-y="145"
                                    data-end-x="87"
                                    data-end-y="198"
                                    cx="220"
                                    cy="145"
                                    r="4"
                                />

                                <circle
                                    data-auth-packet
                                    data-start-x="220"
                                    data-start-y="145"
                                    data-end-x="353"
                                    data-end-y="198"
                                    cx="220"
                                    cy="145"
                                    r="4"
                                />

                            </svg>


                            <article
                                class="research-auth-final__flow-card research-auth-final__flow-card--source"
                                data-auth-flow-card
                            >

                                <span class="is-teal">

                                    <svg
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    >
                                        <path
                                            d="M6 3h9l3 3v15H6z"
                                        />

                                        <path
                                            d="M14 3v4h4"
                                        />

                                        <path
                                            d="M9 12h6M9 16h5"
                                        />
                                    </svg>

                                </span>


                                <div>

                                    <strong>
                                        Curated Sources
                                    </strong>

                                    <small>
                                        Jurnal, dataset, laporan
                                    </small>

                                </div>

                            </article>


                            <article
                                class="research-auth-final__flow-card research-auth-final__flow-card--ai"
                                data-auth-flow-card
                            >

                                <span class="is-purple">

                                    <svg
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    >
                                        <path
                                            d="M12 3l1.5 4.3L18 9l-4.5 1.7L12 15l-1.5-4.3L6 9l4.5-1.7z"
                                        />

                                        <path
                                            d="M18 15l.8 2.2 2.2.8-2.2.8L18 21l-.8-2.2L15 18l2.2-.8z"
                                        />
                                    </svg>

                                </span>


                                <div>

                                    <strong>
                                        AI Research Assistant
                                    </strong>

                                    <small>
                                        Analisis berbasis evidence
                                    </small>

                                </div>

                            </article>


                            <article
                                class="research-auth-final__note"
                                data-auth-note
                            >

                                <div>

                                    <i></i>

                                    <small>
                                        RESEARCH NOTE
                                    </small>

                                </div>

                                <span></span>
                                <span></span>
                                <span></span>
                                <span></span>

                                <b></b>
                                <em></em>

                            </article>


                            <article
                                class="research-auth-final__flow-card research-auth-final__flow-card--evidence"
                                data-auth-flow-card
                            >

                                <span class="is-teal">

                                    <svg
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    >
                                        <circle
                                            cx="10"
                                            cy="10"
                                            r="5"
                                        />

                                        <path
                                            d="M14 14l5 5"
                                        />

                                        <path
                                            d="M7.5 10h5"
                                        />
                                    </svg>

                                </span>


                                <div>

                                    <small>
                                        VALIDATED
                                    </small>

                                    <strong>
                                        Evidence
                                    </strong>

                                </div>

                            </article>


                            <article
                                class="research-auth-final__flow-card research-auth-final__flow-card--workspace"
                                data-auth-flow-card
                            >

                                <span class="is-coral">

                                    <svg
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    >
                                        <path
                                            d="M3 7h7l2 2h9v11H3z"
                                        />
                                    </svg>

                                </span>


                                <div>

                                    <small>
                                        PERSONAL
                                    </small>

                                    <strong>
                                        Workspace
                                    </strong>

                                </div>

                            </article>

                        </div>

                    </div>

                </section>


                {{-- =================================================
                     REGISTER FORM
                ================================================== --}}
                <section class="research-auth-final__forms">

                    <div
                        class="research-auth-final__form-inner research-auth-final__form-inner--register"
                    >

                        <div class="research-auth-final__form-head">

                            <p class="research-auth-final__eyebrow is-coral">
                                MULAI RESEARCH
                            </p>

                            <h2>
                                Buat akun baru
                            </h2>

                            <p>
                                Isi informasi berikut untuk mulai
                                menggunakan BacaDulu Research.
                            </p>

                        </div>


                        @if ($errors->any())

                            <div
                                class="alert alert-error"
                                role="alert"
                            >
                                {{ $errors->first() }}
                            </div>

                        @endif


                        <a
                            href="{{ route('google.redirect') }}"
                            class="research-auth-final__google"
                        >

                            <span aria-hidden="true">

                                <svg viewBox="0 0 24 24">

                                    <path
                                        fill="#4285F4"
                                        d="M21.35 12.2c0-.7-.06-1.2-.2-1.72H12v3.32h5.37a4.6 4.6 0 01-1.99 2.93l3.21 2.49c1.87-1.73 2.76-4.28 2.76-7.02z"
                                    />

                                    <path
                                        fill="#34A853"
                                        d="M12 21.7c2.68 0 4.93-.88 6.58-2.39l-3.21-2.49c-.89.6-2.03.96-3.37.96-2.58 0-4.77-1.74-5.56-4.09l-3.31 2.56A9.94 9.94 0 0012 21.7z"
                                    />

                                    <path
                                        fill="#FBBC05"
                                        d="M6.44 13.69A6.01 6.01 0 016.13 12c0-.59.1-1.16.31-1.69L3.13 7.75A9.99 9.99 0 002.05 12c0 1.54.39 3 1.08 4.25l3.31-2.56z"
                                    />

                                    <path
                                        fill="#EA4335"
                                        d="M12 6.22c1.46 0 2.77.5 3.8 1.48l2.85-2.85A9.55 9.55 0 0012 2.3a9.94 9.94 0 00-8.87 5.45l3.31 2.56C7.23 7.96 9.42 6.22 12 6.22z"
                                    />

                                </svg>

                            </span>


                            <strong>
                                Daftar dengan Google
                            </strong>

                        </a>


                        <div class="research-auth-final__divider">

                            <span>
                                atau gunakan email
                            </span>

                        </div>


                        <form
                            method="POST"
                            action="{{ route('register.store') }}"
                            class="research-auth-final__form research-auth-final__form--register"
                        >

                            @csrf


                            <label
                                class="is-full"
                                for="register-name"
                            >

                                <span>
                                    Nama lengkap
                                </span>


                                <div class="research-auth-final__input">

                                    <i aria-hidden="true">

                                        <svg
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="1.8"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        >
                                            <circle
                                                cx="12"
                                                cy="8"
                                                r="4"
                                            />

                                            <path
                                                d="M5 21a7 7 0 0114 0"
                                            />
                                        </svg>

                                    </i>


                                    <input
                                        id="register-name"
                                        type="text"
                                        name="name"
                                        value="{{ old('name') }}"
                                        autocomplete="name"
                                        maxlength="120"
                                        placeholder="Nama sesuai identitas"
                                        required
                                        autofocus
                                    >

                                </div>

                            </label>


                            <label
                                class="is-full"
                                for="register-email"
                            >

                                <span>
                                    Email
                                </span>


                                <div class="research-auth-final__input">

                                    <i aria-hidden="true">

                                        <svg
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="1.8"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        >
                                            <rect
                                                x="3"
                                                y="5"
                                                width="18"
                                                height="14"
                                                rx="2"
                                            />

                                            <path
                                                d="M3 7l9 6 9-6"
                                            />
                                        </svg>

                                    </i>


                                    <input
                                        id="register-email"
                                        type="email"
                                        name="email"
                                        value="{{ old('email') }}"
                                        autocomplete="email"
                                        inputmode="email"
                                        placeholder="nama@email.com"
                                        required
                                    >

                                </div>

                            </label>


                            <label
                                class="is-full"
                                for="register-institution"
                            >

                                <span>
                                    Institusi
                                    <small>
                                        (opsional)
                                    </small>
                                </span>


                                <div class="research-auth-final__input">

                                    <i aria-hidden="true">

                                        <svg
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="1.8"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        >
                                            <path
                                                d="M4 21V7l8-4 8 4v14"
                                            />

                                            <path
                                                d="M8 10h1M8 14h1M15 10h1M15 14h1M9 21v-4h6v4"
                                            />
                                        </svg>

                                    </i>


                                    <input
                                        id="register-institution"
                                        type="text"
                                        name="institution"
                                        value="{{ old('institution') }}"
                                        autocomplete="organization"
                                        maxlength="180"
                                        placeholder="Universitas atau organisasi"
                                    >

                                </div>

                            </label>


                            <label for="register-password">

                                <span>
                                    Kata sandi
                                </span>


                                <div
                                    class="research-auth-final__input password-field"
                                >

                                    <i aria-hidden="true">

                                        <svg
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="1.8"
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

                                    </i>


                                    <input
                                        id="register-password"
                                        type="password"
                                        name="password"
                                        autocomplete="new-password"
                                        placeholder="Buat password"
                                        required
                                        data-password-input
                                    >


                                    <button
                                        type="button"
                                        class="research-auth-final__show password-toggle"
                                        aria-label="Tampilkan password"
                                        data-password-toggle
                                    >
                                        Show
                                    </button>

                                </div>

                            </label>


                            <label for="register-password-confirmation">

                                <span>
                                    Ulangi sandi
                                </span>


                                <div
                                    class="research-auth-final__input password-field"
                                >

                                    <i aria-hidden="true">

                                        <svg
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="1.8"
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

                                    </i>


                                    <input
                                        id="register-password-confirmation"
                                        type="password"
                                        name="password_confirmation"
                                        autocomplete="new-password"
                                        placeholder="Ulangi password"
                                        required
                                        data-password-input
                                    >


                                    <button
                                        type="button"
                                        class="research-auth-final__show password-toggle"
                                        aria-label="Tampilkan password"
                                        data-password-toggle
                                    >
                                        Show
                                    </button>

                                </div>

                            </label>


                            <div
                                class="research-auth-final__profile-note is-full"
                            >

                                <span>

                                    <strong>
                                        MIN.
                                    </strong>

                                    80%

                                </span>


                                <div>

                                    <strong>
                                        Kelengkapan profil untuk membuka Workspace
                                    </strong>

                                    <p>
                                        Progress aktual dihitung setelah akun dibuat
                                        dan tampil pada halaman profil Anda.
                                    </p>

                                </div>

                            </div>


                            <button
                                type="submit"
                                class="research-auth-final__submit is-coral is-full"
                            >

                                <span>
                                    Buat akun
                                </span>

                                <span aria-hidden="true">
                                    →
                                </span>

                            </button>

                        </form>


                        <p class="research-auth-final__switch">

                            Sudah punya akun?

                            <a href="{{ route('login') }}">
                                Masuk
                            </a>

                        </p>

                    </div>

                </section>

            </div>

        </div>


        <footer class="research-auth-final__footer">

            <span>
                © {{ now()->year }} BacaDulu Research
            </span>

            <span>
                Research with evidence, not just answers.
            </span>

        </footer>

    </div>

</section>

@endsection