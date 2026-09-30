@extends('layouts.app')

@section('title', 'Masuk — BacaDulu Research')

@section(
    'meta_description',
    'Masuk ke BacaDulu Research untuk melanjutkan conversation, evidence, source, dan research output Anda.'
)

@section('content')

<section
    class="research-auth-final research-auth-final--login"
    data-research-auth
    data-auth-page="login"
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
                        class="research-auth-final__tab is-active"
                        aria-current="page"
                    >
                        Masuk
                    </a>

                    <a
                        href="{{ route('register') }}"
                        class="research-auth-final__tab"
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
                                BACADULU RESEARCH
                            </p>

                            <h1>
                                Lanjutkan research,
                                <span>
                                    bukan mulai dari nol.
                                </span>
                            </h1>

                            <p>
                                Kembali ke conversation, evidence, source,
                                dan research output yang sebelumnya Anda
                                kerjakan dalam satu workspace.
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
                                    Question
                                </strong>
                            </article>


                            <article data-auth-journey-step>
                                <i></i>

                                <small>
                                    02
                                </small>

                                <strong>
                                    Retrieval
                                </strong>
                            </article>


                            <article data-auth-journey-step>
                                <i></i>

                                <small>
                                    03
                                </small>

                                <strong>
                                    Analysis
                                </strong>
                            </article>


                            <article data-auth-journey-step>
                                <i></i>

                                <small>
                                    04
                                </small>

                                <strong>
                                    Output
                                </strong>
                            </article>
                        </div>


                        {{-- WORKSPACE --}}
                        <div class="research-auth-final__workspace-stage">

                            <div
                                class="research-auth-final__workspace"
                                data-auth-workspace
                            >

                                <div class="research-auth-final__workspace-head">

                                    <div>
                                        <small>
                                            RESEARCH SESSION
                                        </small>

                                        <strong>
                                            ESG & Firm Value
                                        </strong>
                                    </div>


                                    <span class="research-auth-final__workspace-status">

                                        <i></i>

                                        Evidence ready

                                    </span>

                                </div>


                                <div class="research-auth-final__workspace-body">

                                    <nav
                                        aria-label="Contoh alur research"
                                    >
                                        <span>

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
                                                    cx="12"
                                                    cy="12"
                                                    r="8"
                                                />

                                                <path d="M9 12h6"/>
                                            </svg>

                                            Question

                                        </span>


                                        <span
                                            class="is-active"
                                            data-auth-evidence-nav
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
                                                <rect
                                                    x="5"
                                                    y="4"
                                                    width="14"
                                                    height="16"
                                                    rx="2"
                                                />

                                                <path
                                                    d="M8 9h8M8 13h6"
                                                />
                                            </svg>

                                            Evidence

                                        </span>


                                        <span>

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
                                                    d="M4 18l5-5 4 3 7-8"
                                                />
                                            </svg>

                                            Analysis

                                        </span>


                                        <span>

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
                                            </svg>

                                            Output

                                        </span>

                                    </nav>


                                    <div class="research-auth-final__workspace-content">

                                        <small>
                                            RELEVANT ACADEMIC SOURCES
                                        </small>


                                        <div
                                            class="research-auth-final__lines"
                                            data-auth-lines
                                            aria-hidden="true"
                                        >
                                            <span></span>
                                            <span></span>
                                            <span></span>
                                        </div>


                                        <div
                                            class="research-auth-final__evidence"
                                            data-auth-evidence-card
                                        >

                                            <b>
                                                E01
                                            </b>


                                            <div>

                                                <small>
                                                    CURATED SOURCE
                                                </small>

                                                <strong>
                                                    Relevant academic evidence
                                                </strong>

                                            </div>

                                        </div>


                                        <div class="research-auth-final__chips">

                                            <span>
                                                PDF
                                            </span>

                                            <span>
                                                Jurnal
                                            </span>

                                            <span>
                                                Dataset
                                            </span>

                                        </div>

                                    </div>

                                </div>

                            </div>


                            <div
                                class="research-auth-final__search-orb"
                                data-auth-search-orb
                                aria-hidden="true"
                            >
                                <svg
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
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
                                </svg>
                            </div>

                        </div>

                    </div>

                </section>


                {{-- =================================================
                     RIGHT FORM
                ================================================== --}}
                <section class="research-auth-final__forms">

                    <div class="research-auth-final__form-inner">

                        <div class="research-auth-final__form-head">

                            <p class="research-auth-final__eyebrow is-coral">
                                SELAMAT DATANG KEMBALI
                            </p>

                            <h2>
                                Selamat datang kembali
                            </h2>

                            <p>
                                Masuk untuk melanjutkan penelitian
                                dari workspace terakhir Anda.
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


                        @if (session('success'))

                            <div
                                class="alert alert-success"
                                role="status"
                            >
                                {{ session('success') }}
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
                                Lanjutkan dengan Google
                            </strong>

                        </a>


                        <div class="research-auth-final__divider">

                            <span>
                                atau gunakan email
                            </span>

                        </div>


                        <form
                            method="POST"
                            action="{{ route('login.store') }}"
                            class="research-auth-final__form"
                        >

                            @csrf


                            <label for="login-email">

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
                                        id="login-email"
                                        type="email"
                                        name="email"
                                        value="{{ old('email') }}"
                                        autocomplete="email"
                                        inputmode="email"
                                        placeholder="nama@email.com"
                                        required
                                        autofocus
                                    >

                                </div>

                            </label>


                            <label for="login-password">

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
                                        id="login-password"
                                        type="password"
                                        name="password"
                                        autocomplete="current-password"
                                        placeholder="Masukkan password"
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


                            <label class="research-auth-final__remember">

                                <input
                                    type="checkbox"
                                    name="remember"
                                    value="1"
                                >

                                <span>
                                    Ingat perangkat ini
                                </span>

                            </label>


                            <button
                                type="submit"
                                class="research-auth-final__submit is-navy"
                            >

                                <span>
                                    Masuk
                                </span>

                                <span aria-hidden="true">
                                    →
                                </span>

                            </button>

                        </form>


                        <p class="research-auth-final__switch">

                            Belum punya akun?

                            <a href="{{ route('register') }}">
                                Buat akun
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