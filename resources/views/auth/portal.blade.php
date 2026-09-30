@extends('layouts.app')

@section(
    'title',
    ($authMode ?? 'login') === 'register'
        ? 'Daftar — BacaDulu Research'
        : 'Masuk — BacaDulu Research'
)

@section('meta_description', 'Masuk atau buat akun BacaDulu Research.')

@section('content')

<section
    class="research-auth-final"
    data-auth-card
    data-view="{{ ($authMode ?? 'login') === 'register' ? 'signup' : 'signin' }}"
>
    {{-- =========================================================
         MOVING BACKGROUND
    ========================================================== --}}
    <div class="research-auth-final__scene" aria-hidden="true">
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

        {{-- BACK --}}
        <div class="research-auth-final__topbar">
            <a
                href="{{ route('home') }}"
                class="research-auth-final__back"
            >
                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    aria-hidden="true"
                >
                    <path d="M19 12H5"/>
                    <path d="M11 18l-6-6 6-6"/>
                </svg>

                Kembali ke situs
            </a>
        </div>


        <div class="research-auth-final__card">

            {{-- =====================================================
                 HEADER
            ====================================================== --}}
            <header class="research-auth-final__header">

                <a
                    href="{{ route('home') }}"
                    class="research-auth-final__brand"
                >
                    <span>BacaDulu</span>
                    <strong>Research</strong>
                </a>


                <nav class="research-auth-final__tabs">

                    <a
                        href="{{ route('login') }}"
                        data-auth-view="signin"
                        class="research-auth-final__tab"
                    >
                        Masuk
                    </a>

                    <a
                        href="{{ route('register') }}"
                        data-auth-view="signup"
                        class="research-auth-final__tab"
                    >
                        Daftar
                    </a>

                </nav>

            </header>


            {{-- =====================================================
                 BODY
            ====================================================== --}}
            <div class="research-auth-final__body">

                {{-- =================================================
                     LEFT VISUAL
                ================================================== --}}
                <section
                    class="research-auth-final__visual"
                    data-auth-visual
                >
                    <div
                        class="research-auth-final__visual-grid"
                        data-auth-grid
                    ></div>


                    {{-- =============================================
                         LOGIN VISUAL
                    ============================================== --}}
                    <div
                        class="research-auth-final__hero"
                        data-auth-hero="signin"
                    >

                        <div class="research-auth-final__intro">

                            <p class="research-auth-final__eyebrow">
                                BACADULU RESEARCH
                            </p>

                            <h1>
                                Lanjutkan research,
                                <span>bukan mulai dari nol.</span>
                            </h1>

                            <p>
                                Kembali ke conversation, evidence,
                                source, dan research output yang
                                sebelumnya Anda kerjakan.
                            </p>

                        </div>


                        {{-- JOURNEY --}}
                        <div class="research-auth-final__journey">

                            <div class="research-auth-final__journey-line"></div>

                            <article>
                                <i class="is-coral"></i>
                                <small>01</small>
                                <strong>Question</strong>
                            </article>

                            <article>
                                <i class="is-teal"></i>
                                <small>02</small>
                                <strong>Retrieval</strong>
                            </article>

                            <article>
                                <i></i>
                                <small>03</small>
                                <strong>Analysis</strong>
                            </article>

                            <article>
                                <i></i>
                                <small>04</small>
                                <strong>Output</strong>
                            </article>

                        </div>


                        {{-- WORKSPACE VISUAL --}}
                        <div class="research-auth-final__workspace-stage">

                            <div
                                class="research-auth-final__workspace"
                                data-auth-tile
                            >

                                <div class="research-auth-final__workspace-head">

                                    <div>
                                        <small>RESEARCH SESSION</small>
                                        <strong>ESG & Firm Value</strong>
                                    </div>


                                    <span>
                                        <i></i>
                                        Evidence ready
                                    </span>

                                </div>


                                <div class="research-auth-final__workspace-body">

                                    <nav>

                                        <span>
                                            <svg
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="1.8"
                                            >
                                                <circle cx="12" cy="12" r="8"/>
                                                <path d="M9 12h6"/>
                                            </svg>

                                            Question
                                        </span>


                                        <span class="is-active">
                                            <svg
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="1.8"
                                            >
                                                <rect
                                                    x="5"
                                                    y="4"
                                                    width="14"
                                                    height="16"
                                                    rx="2"
                                                />

                                                <path d="M8 9h8M8 13h6"/>
                                            </svg>

                                            Evidence
                                        </span>


                                        <span>
                                            <svg
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="1.8"
                                            >
                                                <path d="M4 18l5-5 4 3 7-8"/>
                                            </svg>

                                            Analysis
                                        </span>


                                        <span>
                                            <svg
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="1.8"
                                            >
                                                <path d="M6 3h9l3 3v15H6z"/>
                                                <path d="M14 3v4h4"/>
                                            </svg>

                                            Output
                                        </span>

                                    </nav>


                                    <div class="research-auth-final__workspace-content">

                                        <small>
                                            RELEVANT ACADEMIC SOURCES
                                        </small>


                                        <div class="research-auth-final__lines">
                                            <span></span>
                                            <span></span>
                                            <span></span>
                                        </div>


                                        <div class="research-auth-final__evidence">

                                            <b>E01</b>

                                            <div>
                                                <small>CURATED SOURCE</small>
                                                <strong>
                                                    Relevant academic evidence
                                                </strong>
                                            </div>

                                        </div>


                                        <div class="research-auth-final__chips">
                                            <span>PDF</span>
                                            <span>Jurnal</span>
                                            <span>Dataset</span>
                                        </div>

                                    </div>

                                </div>

                            </div>


                            {{-- SOURCE --}}
                            <div
                                class="research-auth-final__float research-auth-final__float--source"
                                data-auth-tile
                            >
                                <span class="is-coral">
                                    <svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                    >
                                        <path d="M6 3h9l3 3v15H6z"/>
                                        <path d="M14 3v4h4"/>
                                        <path d="M9 12h6M9 16h5"/>
                                    </svg>
                                </span>

                                <div>
                                    <small>SOURCE</small>
                                    <strong>Curated Evidence</strong>
                                </div>
                            </div>


                            {{-- CHART --}}
                            <div
                                class="research-auth-final__mini-icon research-auth-final__mini-icon--chart"
                                data-auth-tile
                            >
                                <svg
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                >
                                    <path d="M5 19V11"/>
                                    <path d="M12 19V5"/>
                                    <path d="M19 19v-6"/>
                                </svg>
                            </div>


                            {{-- SEARCH — sekarang nempel workspace --}}
                            <div
                                class="research-auth-final__search-orb"
                                data-auth-tile
                            >
                                <svg
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    stroke-linecap="round"
                                >
                                    <circle cx="10" cy="10" r="5"/>
                                    <path d="M14 14l5 5"/>
                                </svg>
                            </div>

                        </div>

                    </div>


                    {{-- =============================================
                         REGISTER VISUAL
                    ============================================== --}}
                    <div
                        class="research-auth-final__hero"
                        data-auth-hero="signup"
                    >

                        <div class="research-auth-final__intro">

                            <p class="research-auth-final__eyebrow">
                                MULAI DI BACADULU RESEARCH
                            </p>

                            <h1>
                                Buat akun,
                                <span>mulai jelajah riset.</span>
                            </h1>

                            <p>
                                Dapatkan akses ke research tools,
                                curated sources, evidence, dan
                                workspace penelitian.
                            </p>

                        </div>


                        <div class="research-auth-final__journey">

                            <div class="research-auth-final__journey-line"></div>

                            <article>
                                <i class="is-coral"></i>
                                <small>01</small>
                                <strong>Daftar</strong>
                            </article>

                            <article>
                                <i class="is-teal"></i>
                                <small>02</small>
                                <strong>Verifikasi</strong>
                            </article>

                            <article>
                                <i></i>
                                <small>03</small>
                                <strong>Atur profil</strong>
                            </article>

                            <article>
                                <i></i>
                                <small>04</small>
                                <strong>Mulai riset</strong>
                            </article>

                        </div>


                        {{-- REGISTER FLOW --}}
                        <div class="research-auth-final__research-flow">

                            {{-- ARROWS --}}
                            <svg
                                class="research-auth-final__flow-svg"
                                viewBox="0 0 440 235"
                                preserveAspectRatio="none"
                                aria-hidden="true"
                            >
                                <defs>
                                    <marker
                                        id="research-flow-arrow"
                                        markerWidth="8"
                                        markerHeight="8"
                                        refX="7"
                                        refY="4"
                                        orient="auto"
                                    >
                                        <path
                                            d="M0,0 L8,4 L0,8 z"
                                            fill="currentColor"
                                        />
                                    </marker>
                                </defs>


                                <path
                                    data-auth-route
                                    d="M90 45 C130 55 155 80 195 105"
                                    marker-end="url(#research-flow-arrow)"
                                />

                                <path
                                    data-auth-route
                                    d="M350 45 C310 55 285 80 245 105"
                                    marker-end="url(#research-flow-arrow)"
                                />

                                <path
                                    data-auth-route
                                    d="M205 145 C170 170 135 186 90 195"
                                    marker-end="url(#research-flow-arrow)"
                                />

                                <path
                                    data-auth-route
                                    d="M235 145 C270 170 305 186 350 195"
                                    marker-end="url(#research-flow-arrow)"
                                />


                                <circle
                                    data-auth-packet
                                    data-start-x="90"
                                    data-start-y="45"
                                    data-end-x="195"
                                    data-end-y="105"
                                    cx="90"
                                    cy="45"
                                    r="4"
                                />

                                <circle
                                    data-auth-packet
                                    data-start-x="350"
                                    data-start-y="45"
                                    data-end-x="245"
                                    data-end-y="105"
                                    cx="350"
                                    cy="45"
                                    r="4"
                                />

                                <circle
                                    data-auth-packet
                                    data-start-x="220"
                                    data-start-y="145"
                                    data-end-x="90"
                                    data-end-y="195"
                                    cx="220"
                                    cy="145"
                                    r="4"
                                />

                                <circle
                                    data-auth-packet
                                    data-start-x="220"
                                    data-start-y="145"
                                    data-end-x="350"
                                    data-end-y="195"
                                    cx="220"
                                    cy="145"
                                    r="4"
                                />

                            </svg>


                            {{-- SOURCE --}}
                            <article
                                class="research-auth-final__flow-card research-auth-final__flow-card--source"
                                data-auth-source
                            >
                                <span class="is-teal">
                                    <svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                    >
                                        <path d="M6 3h9l3 3v15H6z"/>
                                        <path d="M14 3v4h4"/>
                                        <path d="M9 12h6M9 16h5"/>
                                    </svg>
                                </span>

                                <div>
                                    <strong>Curated Sources</strong>
                                    <small>Jurnal, dataset, laporan</small>
                                </div>
                            </article>


                            {{-- AI --}}
                            <article
                                class="research-auth-final__flow-card research-auth-final__flow-card--ai"
                                data-auth-source
                            >
                                <span class="is-purple">
                                    <svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                    >
                                        <path d="M12 3l1.5 4.3L18 9l-4.5 1.7L12 15l-1.5-4.3L6 9l4.5-1.7z"/>
                                        <path d="M18 15l.8 2.2 2.2.8-2.2.8L18 21l-.8-2.2L15 18l2.2-.8z"/>
                                    </svg>
                                </span>

                                <div>
                                    <strong>AI Research Assistant</strong>
                                    <small>Analisis berbasis evidence</small>
                                </div>
                            </article>


                            {{-- CENTER NOTE --}}
                            <article
                                class="research-auth-final__note"
                                data-auth-output
                            >
                                <div>
                                    <i></i>
                                    <small>RESEARCH NOTE</small>
                                </div>

                                <span></span>
                                <span></span>
                                <span></span>
                                <span></span>

                                <b></b>
                                <em></em>
                            </article>


                            {{-- EVIDENCE --}}
                            <article
                                class="research-auth-final__flow-card research-auth-final__flow-card--evidence"
                                data-auth-source
                            >
                                <span class="is-teal">
                                    <svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                    >
                                        <circle cx="10" cy="10" r="5"/>
                                        <path d="M14 14l5 5"/>
                                        <path d="M7.5 10h5"/>
                                    </svg>
                                </span>

                                <div>
                                    <small>VALIDATED</small>
                                    <strong>Evidence</strong>
                                </div>
                            </article>


                            {{-- WORKSPACE --}}
                            <article
                                class="research-auth-final__flow-card research-auth-final__flow-card--workspace"
                                data-auth-source
                            >
                                <span class="is-gold">
                                    <svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                    >
                                        <path d="M3 7h7l2 2h9v11H3z"/>
                                    </svg>
                                </span>

                                <div>
                                    <small>PERSONAL</small>
                                    <strong>Workspace</strong>
                                </div>
                            </article>

                        </div>

                    </div>

                </section>


                {{-- =================================================
                     FORMS
                ================================================== --}}
                <section
                    class="research-auth-final__forms"
                    data-auth-forms
                >

                    {{-- LOGIN --}}
                    <div
                        class="research-auth-final__form-pane"
                        data-auth-pane="signin"
                    >
                        <div class="research-auth-final__form-inner">

                            <div class="research-auth-final__form-head">

                                <p class="research-auth-final__eyebrow is-coral">
                                    SELAMAT DATANG KEMBALI
                                </p>

                                <h2>
                                    Selamat datang kembali
                                </h2>

                                <p>
                                    Masuk ke BacaDulu Research untuk
                                    melanjutkan penelitian Anda.
                                </p>

                            </div>


                            @if (
                                ($authMode ?? 'login') === 'login'
                                && $errors->any()
                            )
                                <div class="alert alert-error">
                                    {{ $errors->first() }}
                                </div>
                            @endif


                            <a
                                href="{{ route('google.redirect') }}"
                                class="research-auth-final__google"
                            >
                                <span>
                                    <svg viewBox="0 0 24 24">
                                        <path fill="#4285F4" d="M21.35 12.2c0-.7-.06-1.2-.2-1.72H12v3.32h5.37a4.6 4.6 0 01-1.99 2.93l3.21 2.49c1.87-1.73 2.76-4.28 2.76-7.02z"/>
                                        <path fill="#34A853" d="M12 21.7c2.68 0 4.93-.88 6.58-2.39l-3.21-2.49c-.89.6-2.03.96-3.37.96-2.58 0-4.77-1.74-5.56-4.09l-3.31 2.56A9.94 9.94 0 0012 21.7z"/>
                                        <path fill="#FBBC05" d="M6.44 13.69A6.01 6.01 0 016.13 12c0-.59.1-1.16.31-1.69L3.13 7.75A9.99 9.99 0 002.05 12c0 1.54.39 3 1.08 4.25l3.31-2.56z"/>
                                        <path fill="#EA4335" d="M12 6.22c1.46 0 2.77.5 3.8 1.48l2.85-2.85A9.55 9.55 0 0012 2.3a9.94 9.94 0 00-8.87 5.45l3.31 2.56C7.23 7.96 9.42 6.22 12 6.22z"/>
                                    </svg>
                                </span>

                                <strong>
                                    Lanjutkan dengan Google
                                </strong>
                            </a>


                            <div class="research-auth-final__divider">
                                <span>atau gunakan email</span>
                            </div>


                            <form
                                method="POST"
                                action="{{ route('login.store') }}"
                                class="research-auth-final__form"
                            >
                                @csrf


                                <label>
                                    <span>Email</span>

                                    <div class="research-auth-final__input">

                                        <i>
                                            <svg
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="1.8"
                                            >
                                                <rect
                                                    x="3"
                                                    y="5"
                                                    width="18"
                                                    height="14"
                                                    rx="2"
                                                />

                                                <path d="M3 7l9 6 9-6"/>
                                            </svg>
                                        </i>


                                        <input
                                            type="email"
                                            name="email"
                                            value="{{ old('email') }}"
                                            placeholder="nama@email.com"
                                            autocomplete="email"
                                            required
                                        >

                                    </div>
                                </label>


                                <label>
                                    <span>Kata sandi</span>

                                    <div class="research-auth-final__input">

                                        <i>
                                            <svg
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="1.8"
                                            >
                                                <rect
                                                    x="5"
                                                    y="10"
                                                    width="14"
                                                    height="10"
                                                    rx="2"
                                                />

                                                <path d="M8 10V7a4 4 0 018 0v3"/>
                                            </svg>
                                        </i>


                                        <input
                                            id="auth-login-password"
                                            type="password"
                                            name="password"
                                            placeholder="Masukkan password"
                                            autocomplete="current-password"
                                            required
                                        >


                                        <button
                                            type="button"
                                            class="research-auth-final__show"
                                            data-auth-password-toggle
                                            data-target="auth-login-password"
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
                                    <span>Masuk</span>
                                    <span>→</span>
                                </button>

                            </form>


                            <p class="research-auth-final__switch">
                                Belum punya akun?

                                <a
                                    href="{{ route('register') }}"
                                    data-auth-view="signup"
                                >
                                    Buat akun
                                </a>
                            </p>

                        </div>
                    </div>


                    {{-- REGISTER --}}
                    <div
                        class="research-auth-final__form-pane"
                        data-auth-pane="signup"
                    >
                        <div class="research-auth-final__form-inner">

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


                            @if (
                                ($authMode ?? 'login') === 'register'
                                && $errors->any()
                            )
                                <div class="alert alert-error">
                                    {{ $errors->first() }}
                                </div>
                            @endif


                            <a
                                href="{{ route('google.redirect') }}"
                                class="research-auth-final__google"
                            >
                                <span>
                                    <svg viewBox="0 0 24 24">
                                        <path fill="#4285F4" d="M21.35 12.2c0-.7-.06-1.2-.2-1.72H12v3.32h5.37a4.6 4.6 0 01-1.99 2.93l3.21 2.49c1.87-1.73 2.76-4.28 2.76-7.02z"/>
                                        <path fill="#34A853" d="M12 21.7c2.68 0 4.93-.88 6.58-2.39l-3.21-2.49c-.89.6-2.03.96-3.37.96-2.58 0-4.77-1.74-5.56-4.09l-3.31 2.56A9.94 9.94 0 0012 21.7z"/>
                                        <path fill="#FBBC05" d="M6.44 13.69A6.01 6.01 0 016.13 12c0-.59.1-1.16.31-1.69L3.13 7.75A9.99 9.99 0 002.05 12c0 1.54.39 3 1.08 4.25l3.31-2.56z"/>
                                        <path fill="#EA4335" d="M12 6.22c1.46 0 2.77.5 3.8 1.48l2.85-2.85A9.55 9.55 0 0012 2.3a9.94 9.94 0 00-8.87 5.45l3.31 2.56C7.23 7.96 9.42 6.22 12 6.22z"/>
                                    </svg>
                                </span>

                                <strong>
                                    Daftar dengan Google
                                </strong>
                            </a>


                            <div class="research-auth-final__divider">
                                <span>atau gunakan email</span>
                            </div>


                            <form
                                method="POST"
                                action="{{ route('register.store') }}"
                                class="research-auth-final__form research-auth-final__form--register"
                            >
                                @csrf


                                <label class="is-full">
                                    <span>Nama lengkap</span>

                                    <div class="research-auth-final__input">
                                        <i>
                                            <svg
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="1.8"
                                            >
                                                <circle cx="12" cy="8" r="4"/>
                                                <path d="M5 21a7 7 0 0114 0"/>
                                            </svg>
                                        </i>

                                        <input
                                            type="text"
                                            name="name"
                                            value="{{ old('name') }}"
                                            placeholder="Nama sesuai identitas"
                                            autocomplete="name"
                                            required
                                        >
                                    </div>
                                </label>


                                <label class="is-full">
                                    <span>Email</span>

                                    <div class="research-auth-final__input">

                                        <i>
                                            <svg
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="1.8"
                                            >
                                                <rect
                                                    x="3"
                                                    y="5"
                                                    width="18"
                                                    height="14"
                                                    rx="2"
                                                />

                                                <path d="M3 7l9 6 9-6"/>
                                            </svg>
                                        </i>

                                        <input
                                            type="email"
                                            name="email"
                                            value="{{ old('email') }}"
                                            placeholder="nama@email.com"
                                            autocomplete="email"
                                            required
                                        >
                                    </div>
                                </label>


                                <label class="is-full">
                                    <span>
                                        Institusi
                                        <small>(opsional)</small>
                                    </span>

                                    <div class="research-auth-final__input">

                                        <i>
                                            <svg
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="1.8"
                                            >
                                                <path d="M4 21V7l8-4 8 4v14"/>
                                                <path d="M8 10h1M8 14h1M15 10h1M15 14h1M9 21v-4h6v4"/>
                                            </svg>
                                        </i>

                                        <input
                                            type="text"
                                            name="institution"
                                            value="{{ old('institution') }}"
                                            placeholder="Universitas atau organisasi"
                                            autocomplete="organization"
                                        >
                                    </div>
                                </label>


                                <label>
                                    <span>Kata sandi</span>

                                    <div class="research-auth-final__input">
                                        <i>
                                            <svg
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="1.8"
                                            >
                                                <rect
                                                    x="5"
                                                    y="10"
                                                    width="14"
                                                    height="10"
                                                    rx="2"
                                                />

                                                <path d="M8 10V7a4 4 0 018 0v3"/>
                                            </svg>
                                        </i>

                                        <input
                                            id="auth-register-password"
                                            type="password"
                                            name="password"
                                            placeholder="Buat password"
                                            autocomplete="new-password"
                                            required
                                        >

                                        <button
                                            type="button"
                                            class="research-auth-final__show"
                                            data-auth-password-toggle
                                            data-target="auth-register-password"
                                        >
                                            Show
                                        </button>
                                    </div>
                                </label>


                                <label>
                                    <span>Ulangi sandi</span>

                                    <div class="research-auth-final__input">
                                        <i>
                                            <svg
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="1.8"
                                            >
                                                <rect
                                                    x="5"
                                                    y="10"
                                                    width="14"
                                                    height="10"
                                                    rx="2"
                                                />

                                                <path d="M8 10V7a4 4 0 018 0v3"/>
                                            </svg>
                                        </i>

                                        <input
                                            id="auth-register-confirm"
                                            type="password"
                                            name="password_confirmation"
                                            placeholder="Ulangi password"
                                            autocomplete="new-password"
                                            required
                                        >

                                        <button
                                            type="button"
                                            class="research-auth-final__show"
                                            data-auth-password-toggle
                                            data-target="auth-register-confirm"
                                        >
                                            Show
                                        </button>
                                    </div>
                                </label>


                                <div class="research-auth-final__profile-note is-full">

                                    <span>
                                        <strong>MIN.</strong>
                                        80%
                                    </span>

                                    <div>
                                        <strong>
                                            Kelengkapan profil untuk membuka Workspace
                                        </strong>

                                        <p>
                                            Setelah akun dibuat, progress aktual
                                            akan tampil di halaman profil Anda.
                                        </p>
                                    </div>

                                </div>


                                <button
                                    type="submit"
                                    class="research-auth-final__submit is-coral is-full"
                                >
                                    <span>Buat akun</span>
                                    <span>→</span>
                                </button>

                            </form>


                            <p class="research-auth-final__switch">
                                Sudah punya akun?

                                <a
                                    href="{{ route('login') }}"
                                    data-auth-view="signin"
                                >
                                    Masuk
                                </a>
                            </p>

                        </div>
                    </div>

                </section>


                {{-- =================================================
                     WAVE TRANSITION
                ================================================== --}}
                <svg
                    class="research-auth-final__wave"
                    data-auth-wave
                    viewBox="0 0 1200 600"
                    preserveAspectRatio="none"
                    aria-hidden="true"
                >
                    <defs>
                        <linearGradient
                            id="research-auth-wave-gradient"
                            x1="0"
                            x2="1"
                        >
                            <stop
                                offset="0%"
                                stop-color="#fb795e"
                            />

                            <stop
                                offset="55%"
                                stop-color="#f7b784"
                            />

                            <stop
                                offset="100%"
                                stop-color="#54c9c6"
                            />
                        </linearGradient>
                    </defs>

                    <path
                        data-auth-wave-path
                        fill="url(#research-auth-wave-gradient)"
                        d="M -170 0 C -60 90 -260 210 -160 300 C -60 420 -240 520 -140 600 L -320 600 L -320 0 Z"
                    />
                </svg>

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