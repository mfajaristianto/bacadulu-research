@extends('layouts.app')

@section(
    'title',
    ($authView ?? 'signin') === 'signup'
        ? 'Daftar — BacaDulu Research'
        : 'Masuk — BacaDulu Research'
)

@section('content')

@php
    $initialView = ($authView ?? 'signin') === 'signup'
        ? 'signup'
        : 'signin';

    $roles = [
        'student' => 'Pelajar',
        'university_student' => 'Mahasiswa',
        'teacher' => 'Guru',
        'lecturer' => 'Dosen',
        'researcher' => 'Peneliti',
        'professional' => 'Profesional / Karyawan',
        'practitioner' => 'Praktisi / Konsultan',
        'entrepreneur' => 'Pengusaha / Wirausaha',
        'editor_reviewer' => 'Editor / Reviewer Jurnal',
        'education_staff' => 'Tenaga Kependidikan',
        'government_employee' => 'Pegawai Pemerintah',
        'other' => 'Lainnya',
    ];

    $researchInterests = [
        'Akuntansi',
        'Perpajakan',
        'Keuangan',
        'Manajemen',
        'Ekonomi',
        'Bisnis',
        'Sustainability / ESG',
        'Sistem Informasi',
        'Artificial Intelligence',
        'Pendidikan',
        'Hukum',
        'Ilmu Sosial',
        'Kesehatan',
        'Teknik / Teknologi',
        'Lainnya',
    ];
@endphp

<section
    class="research-auth"
    data-research-auth
    data-view="{{ $initialView }}"
    data-login-url="{{ route('login') }}"
    data-register-url="{{ route('register') }}"
>
    <div class="research-auth__ambient" aria-hidden="true">
        <span class="research-auth__blob research-auth__blob--coral"></span>
        <span class="research-auth__blob research-auth__blob--teal"></span>
        <span class="research-auth__blob research-auth__blob--blue"></span>
        <span class="research-auth__blob research-auth__blob--peach"></span>

        <span class="research-auth__particle research-auth__particle--1"></span>
        <span class="research-auth__particle research-auth__particle--2"></span>
        <span class="research-auth__particle research-auth__particle--3"></span>
        <span class="research-auth__particle research-auth__particle--4"></span>
    </div>

    <div class="research-auth__page">
        <div class="research-auth__top">
            <a href="{{ route('home') }}" class="research-auth__back">
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

                <span>Kembali ke situs</span>
            </a>
        </div>

        <div class="research-auth__shell">
            <header class="research-auth__header">
                <a href="{{ route('home') }}" class="research-auth__brand">
                    <span>BacaDulu</span>
                    <strong>Research</strong>
                </a>

                <nav class="research-auth__tabs" aria-label="Navigasi autentikasi">
                    <a
                        href="{{ route('login') }}"
                        class="research-auth__tab"
                        data-auth-switch="signin"
                    >
                        Masuk
                    </a>

                    <a
                        href="{{ route('register') }}"
                        class="research-auth__tab"
                        data-auth-switch="signup"
                    >
                        Daftar
                    </a>
                </nav>
            </header>

            <div class="research-auth__body">
                {{-- =====================================================
                     LEFT VISUAL
                ====================================================== --}}
                <section class="research-auth__visual">
                    <div
                        class="research-auth__grid"
                        data-auth-grid
                        aria-hidden="true"
                    ></div>

                    {{-- LOGIN --}}
                    <div
                        class="research-auth__hero"
                        data-auth-hero="signin"
                    >
                        <div class="research-auth__intro">
                            <p class="research-auth__eyebrow">
                                BACADULU RESEARCH
                            </p>

                            <h1>
                                Lanjutkan research,
                                <span>bukan mulai dari nol.</span>
                            </h1>

                            <p>
                                Kembali ke conversation, evidence, source,
                                dan research output yang sebelumnya Anda kerjakan.
                            </p>
                        </div>

                        <div
                            class="research-auth__journey"
                            data-auth-journey
                        >
                            <div class="research-auth__journey-track">
                                <span data-auth-journey-progress></span>
                            </div>

                            <article data-auth-journey-step>
                                <i></i>
                                <small>01</small>
                                <strong>Question</strong>
                            </article>

                            <article data-auth-journey-step>
                                <i></i>
                                <small>02</small>
                                <strong>Retrieval</strong>
                            </article>

                            <article data-auth-journey-step>
                                <i></i>
                                <small>03</small>
                                <strong>Analysis</strong>
                            </article>

                            <article data-auth-journey-step>
                                <i></i>
                                <small>04</small>
                                <strong>Output</strong>
                            </article>
                        </div>

                        <div
                            class="research-auth__workspace-stage"
                            data-login-stage
                        >
                            <article
                                class="research-auth__workspace"
                                data-login-workspace
                            >
                                <header class="research-auth__workspace-head">
                                    <div>
                                        <small>RESEARCH SESSION</small>
                                        <strong>ESG &amp; Firm Value</strong>
                                    </div>

                                    <span class="research-auth__workspace-status">
                                        <i></i>
                                        Evidence ready
                                    </span>
                                </header>

                                <div class="research-auth__workspace-body">
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

                                        <span
                                            class="is-active"
                                            data-evidence-nav
                                        >
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
                                                <path d="M9 9h6"/>
                                                <path d="M9 13h6"/>
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
                                                <path d="M5 17l4-5 4 2 6-8"/>
                                                <path d="M16 6h3v3"/>
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
                                                <path d="M7 3h7l4 4v14H7z"/>
                                                <path d="M14 3v5h5"/>
                                            </svg>

                                            Output
                                        </span>
                                    </nav>

                                    <div class="research-auth__workspace-main">
                                        <small>
                                            RELEVANT ACADEMIC SOURCES
                                        </small>

                                        <div
                                            class="research-auth__source-lines"
                                            data-source-lines
                                        >
                                            <span></span>
                                            <span></span>
                                            <span></span>
                                        </div>

                                        <article
                                            class="research-auth__evidence"
                                            data-evidence-card
                                        >
                                            <b>E01</b>

                                            <div>
                                                <small>CURATED SOURCE</small>
                                                <strong>
                                                    Relevant academic evidence
                                                </strong>
                                            </div>
                                        </article>

                                        <div class="research-auth__chips">
                                            <span>PDF</span>
                                            <span>Journal</span>
                                            <span>Dataset</span>
                                        </div>
                                    </div>
                                </div>
                            </article>

                            <div
                                class="research-auth__search"
                                data-search-orb
                                aria-hidden="true"
                            >
                                <svg
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <circle cx="10.5" cy="10.5" r="5.5"/>
                                    <path d="M15 15l4 4"/>
                                </svg>
                            </div>
                        </div>
                    </div>

                    {{-- REGISTER --}}
                    <div
                        class="research-auth__hero"
                        data-auth-hero="signup"
                    >
                        <div class="research-auth__intro">
                            <p class="research-auth__eyebrow">
                                MULAI DI BACADULU RESEARCH
                            </p>

                            <h1>
                                Buat akun,
                                <span>mulai jelajah riset.</span>
                            </h1>

                            <p>
                                Daftar sekali untuk membuka research tools,
                                curated sources, evidence, dan personal workspace.
                            </p>
                        </div>

                        <div
                            class="research-auth__journey"
                            data-auth-journey
                        >
                            <div class="research-auth__journey-track">
                                <span data-auth-journey-progress></span>
                            </div>

                            <article data-auth-journey-step>
                                <i></i>
                                <small>01</small>
                                <strong>Daftar</strong>
                            </article>

                            <article data-auth-journey-step>
                                <i></i>
                                <small>02</small>
                                <strong>Verifikasi</strong>
                            </article>

                            <article data-auth-journey-step>
                                <i></i>
                                <small>03</small>
                                <strong>Atur profil</strong>
                            </article>

                            <article data-auth-journey-step>
                                <i></i>
                                <small>04</small>
                                <strong>Mulai riset</strong>
                            </article>
                        </div>

                        <div
                            class="research-auth__flow"
                            data-register-flow
                        >
                            <svg
                                class="research-auth__flow-lines"
                                viewBox="0 0 600 300"
                                preserveAspectRatio="none"
                                aria-hidden="true"
                            >
                                <path
                                    data-flow-path
                                    d="M125 62 C190 80 240 112 285 142"
                                />

                                <path
                                    data-flow-path
                                    d="M475 62 C410 80 360 112 315 142"
                                />

                                <path
                                    data-flow-path
                                    d="M285 165 C230 195 185 218 125 240"
                                />

                                <path
                                    data-flow-path
                                    d="M315 165 C370 195 415 218 475 240"
                                />
                            </svg>

                            <article
                                class="research-auth__flow-card flow-source"
                                data-flow-card
                            >
                                <span class="is-teal">
                                    <svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                    >
                                        <path d="M7 3h7l4 4v14H7z"/>
                                        <path d="M14 3v5h5"/>
                                        <path d="M10 12h5"/>
                                        <path d="M10 16h5"/>
                                    </svg>
                                </span>

                                <div>
                                    <strong>Curated Sources</strong>
                                    <small>Jurnal, dataset, laporan</small>
                                </div>
                            </article>

                            <article
                                class="research-auth__flow-card flow-ai"
                                data-flow-card
                            >
                                <span class="is-purple">
                                    <svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                    >
                                        <path d="M12 3l1.4 4.1L17.5 8.5l-4.1 1.4L12 14l-1.4-4.1L6.5 8.5l4.1-1.4L12 3z"/>
                                        <path d="M18 14l.8 2.2L21 17l-2.2.8L18 20l-.8-2.2L15 17l2.2-.8L18 14z"/>
                                    </svg>
                                </span>

                                <div>
                                    <strong>AI Research Assistant</strong>
                                    <small>Analisis berbasis evidence</small>
                                </div>
                            </article>

                            <article
                                class="research-auth__research-note"
                                data-research-note
                            >
                                <header>
                                    <i></i>
                                    <small>RESEARCH NOTE</small>
                                </header>

                                <span></span>
                                <span></span>
                                <span></span>
                                <span></span>

                                <b></b>
                                <em></em>
                            </article>

                            <article
                                class="research-auth__flow-card flow-evidence"
                                data-flow-card
                            >
                                <span class="is-teal">
                                    <svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                    >
                                        <circle cx="10.5" cy="10.5" r="5"/>
                                        <path d="M14.5 14.5L19 19"/>
                                        <path d="M8.5 10.5l1.3 1.3 2.5-2.7"/>
                                    </svg>
                                </span>

                                <div>
                                    <small>VALIDATED</small>
                                    <strong>Evidence</strong>
                                </div>
                            </article>

                            <article
                                class="research-auth__flow-card flow-workspace"
                                data-flow-card
                            >
                                <span class="is-coral">
                                    <svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                    >
                                        <path d="M3 7h7l2 2h9v10H3z"/>
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

                {{-- =====================================================
                     RIGHT FORMS
                ====================================================== --}}
                <section class="research-auth__forms">
                    {{-- LOGIN --}}
                    <div
                        class="research-auth__form-pane"
                        data-auth-pane="signin"
                    >
                        <div class="research-auth__form-inner">
                            <header class="research-auth__form-head">
                                <p class="research-auth__eyebrow is-coral">
                                    SELAMAT DATANG KEMBALI
                                </p>

                                <h2>Selamat datang kembali</h2>

                                <p>
                                    Masuk dengan akun yang telah terdaftar.
                                </p>
                            </header>

                            @if ($initialView === 'signin' && $errors->any())
                                <div
                                    class="research-auth__error"
                                    role="alert"
                                >
                                    {{ $errors->first() }}
                                </div>
                            @endif

                            {{-- Keep Google OAuth markup for future reactivation. --}}
                            @if (config('research.google_auth_enabled', false))
                            <a
                                href="{{ route('google.redirect') }}"
                                class="research-auth__google"
                            >
                                <span class="research-auth__google-icon">
                                    <svg
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                    >
                                        <path
                                            fill="#4285F4"
                                            d="M21.6 12.2c0-.7-.1-1.4-.2-2H12v3.8h5.4a4.7 4.7 0 0 1-2 3.1v2.6h3.2c1.9-1.7 3-4.3 3-7.5z"
                                        />
                                        <path
                                            fill="#34A853"
                                            d="M12 22c2.7 0 5-.9 6.6-2.3l-3.2-2.6c-.9.6-2 1-3.4 1-2.6 0-4.8-1.8-5.6-4.2H3.1v2.6A10 10 0 0 0 12 22z"
                                        />
                                        <path
                                            fill="#FBBC05"
                                            d="M6.4 13.9a6 6 0 0 1 0-3.8V7.5H3.1a10 10 0 0 0 0 9l3.3-2.6z"
                                        />
                                        <path
                                            fill="#EA4335"
                                            d="M12 5.9c1.5 0 2.9.5 3.9 1.5l2.9-2.9A9.8 9.8 0 0 0 3.1 7.5l3.3 2.6C7.2 7.7 9.4 5.9 12 5.9z"
                                        />
                                    </svg>
                                </span>

                                <strong>
                                    Masuk dengan Google
                                </strong>
                            </a>

                            <div class="research-auth__divider">
                                <span>atau gunakan email</span>
                            </div>

                            @endif

                            <form
                                method="POST"
                                action="{{ route('login.store') }}"
                                class="research-auth__form"
                            >
                                @csrf

                                <label>
                                    <span>Email</span>

                                    <div class="research-auth__input">
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
                                                <path d="M4 7l8 6 8-6"/>
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

                                    <div class="research-auth__input">
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
                                                <path d="M8 10V7a4 4 0 0 1 8 0v3"/>
                                            </svg>
                                        </i>

                                        <input
                                            type="password"
                                            name="password"
                                            placeholder="Masukkan password"
                                            autocomplete="current-password"
                                            required
                                            data-password-input
                                        >

                                        <button
                                            type="button"
                                            class="research-auth__show"
                                            data-password-toggle
                                        >
                                            Show
                                        </button>
                                    </div>
                                </label>

                                <label class="research-auth__remember">
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
                                    class="research-auth__submit is-navy"
                                >
                                    <span>Masuk</span>
                                    <span>→</span>
                                </button>
                            </form>

                            <p class="research-auth__switch">
                                Belum punya akun?

                                <a
                                    href="{{ route('register') }}"
                                    data-auth-switch="signup"
                                >
                                    Daftar terlebih dahulu
                                </a>
                            </p>
                        </div>
                    </div>

                    {{-- REGISTER --}}
                    <div
                        class="research-auth__form-pane"
                        data-auth-pane="signup"
                    >
                        <div class="research-auth__form-inner is-register">
                            <header class="research-auth__form-head">
                                <p class="research-auth__eyebrow is-coral">
                                    PENDAFTARAN
                                </p>

                                <h2>Buat akun baru</h2>

                                <p>
                                    Minimum 80% data identitas untuk membuat akun.
                                </p>
                            </header>

                            @if ($initialView === 'signup' && $errors->any())
                                <div
                                    class="research-auth__error"
                                    role="alert"
                                >
                                    {{ $errors->first() }}
                                </div>
                            @endif

                            <form
                                method="POST"
                                action="{{ route('register.store') }}"
                                class="research-auth__form research-auth__form--register"
                                data-register-form
                            >
                                @csrf

                                {{-- NAME --}}
                                <label class="is-full">
                                    <span>Nama lengkap</span>

                                    <div class="research-auth__input">
                                        <i>
                                            <svg
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="1.8"
                                            >
                                                <circle cx="12" cy="8" r="4"/>
                                                <path d="M5 20c0-4 3-7 7-7s7 3 7 7"/>
                                            </svg>
                                        </i>

                                        <input
                                            type="text"
                                            name="name"
                                            value="{{ old('name') }}"
                                            placeholder="Nama sesuai identitas"
                                            autocomplete="name"
                                            required
                                            data-progress-field="name"
                                        >
                                    </div>
                                </label>

                                {{-- EMAIL --}}
                                <label class="is-full">
                                    <span>Email</span>

                                    <div class="research-auth__input">
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
                                                <path d="M4 7l8 6 8-6"/>
                                            </svg>
                                        </i>

                                        <input
                                            type="email"
                                            name="email"
                                            value="{{ old('email') }}"
                                            placeholder="nama@email.com"
                                            autocomplete="email"
                                            required
                                            data-progress-field="email"
                                        >
                                    </div>
                                </label>

                                {{-- INSTITUTION --}}
                                <label>
                                    <span>Institusi</span>

                                    <div class="research-auth__input">
                                        <i>
                                            <svg
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="1.8"
                                            >
                                                <path d="M4 21V7l8-4 8 4v14"/>
                                                <path d="M8 10h2"/>
                                                <path d="M14 10h2"/>
                                                <path d="M8 14h2"/>
                                                <path d="M14 14h2"/>
                                                <path d="M10 21v-4h4v4"/>
                                            </svg>
                                        </i>

                                        <input
                                            type="text"
                                            name="institution"
                                            value="{{ old('institution') }}"
                                            placeholder="Universitas / organisasi"
                                            autocomplete="organization"
                                            required
                                            data-progress-field="institution"
                                        >
                                    </div>
                                </label>

                                {{-- ROLE --}}
                                <label>
                                    <span>Peran utama</span>

                                    <div class="research-auth__select">
                                        <select
                                            name="role"
                                            required
                                            data-progress-field="role"
                                        >
                                            <option value="">
                                                Pilih peran
                                            </option>

                                            @foreach ($roles as $value => $label)
                                                <option
                                                    value="{{ $value }}"
                                                    @selected(old('role') === $value)
                                                >
                                                    {{ $label }}
                                                </option>
                                            @endforeach
                                        </select>

                                        <svg
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="1.8"
                                            aria-hidden="true"
                                        >
                                            <path d="M7 10l5 5 5-5"/>
                                        </svg>
                                    </div>
                                </label>

                                {{-- DOMICILE --}}
                                <label>
                                    <span>
                                        Kota / Kabupaten
                                        <small>opsional</small>
                                    </span>

                                    <div
                                        class="research-auth__combo"
                                        data-domicile-combo
                                    >
                                        <div class="research-auth__input">
                                            <i>
                                                <svg
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    stroke-width="1.8"
                                                >
                                                    <path d="M12 21s6-5.4 6-11a6 6 0 1 0-12 0c0 5.6 6 11 6 11z"/>
                                                    <circle cx="12" cy="10" r="2"/>
                                                </svg>
                                            </i>

                                            <input
                                                type="text"
                                                value="{{ old('domicile_city') }}"
                                                placeholder="Cari kota / kabupaten"
                                                autocomplete="off"
                                                role="combobox"
                                                aria-autocomplete="list"
                                                aria-expanded="false"
                                                data-domicile-search
                                            >
                                        </div>

                                        <input
                                            type="hidden"
                                            name="domicile_city"
                                            value="{{ old('domicile_city') }}"
                                            data-domicile-value
                                            data-progress-field="domicile"
                                        >

                                        <div
                                            class="research-auth__combo-panel"
                                            data-domicile-panel
                                            hidden
                                        >
                                            <div
                                                class="research-auth__combo-status"
                                                data-domicile-status
                                            >
                                                Ketik nama kota atau kabupaten.
                                            </div>

                                            <div
                                                class="research-auth__combo-results"
                                                data-domicile-results
                                            ></div>
                                        </div>
                                    </div>
                                </label>

                                {{-- RESEARCH INTEREST --}}
                                <label>
                                    <span>
                                        Minat riset
                                        <small>opsional</small>
                                    </span>

                                    <div
                                        class="research-auth__multi"
                                        data-interest-select
                                    >
                                        <button
                                            type="button"
                                            class="research-auth__multi-trigger"
                                            data-interest-trigger
                                            aria-expanded="false"
                                        >
                                            <span data-interest-label>
                                                Pilih maksimal 3
                                            </span>

                                            <svg
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="1.8"
                                            >
                                                <path d="M7 10l5 5 5-5"/>
                                            </svg>
                                        </button>

                                        <div
                                            class="research-auth__multi-panel"
                                            data-interest-panel
                                            hidden
                                        >
                                            <div class="research-auth__multi-head">
                                                <strong>Minat Riset</strong>
                                                <small data-interest-count>
                                                    0/3
                                                </small>
                                            </div>

                                            <div class="research-auth__multi-options">
                                                @foreach ($researchInterests as $interest)
                                                    <label>
                                                        <input
                                                            type="checkbox"
                                                            value="{{ $interest }}"
                                                            data-interest-option
                                                        >

                                                        <span>{{ $interest }}</span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        </div>

                                        <input
                                            type="hidden"
                                            name="research_interest"
                                            value="{{ old('research_interest') }}"
                                            data-interest-value
                                            data-progress-field="interest"
                                        >
                                    </div>
                                </label>

                                {{-- PASSWORD --}}
                                <label>
                                    <span>Kata sandi</span>

                                    <div class="research-auth__input">
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
                                                <path d="M8 10V7a4 4 0 0 1 8 0v3"/>
                                            </svg>
                                        </i>

                                        <input
                                            type="password"
                                            name="password"
                                            placeholder="Minimal 8 karakter"
                                            autocomplete="new-password"
                                            required
                                            data-password-input
                                            data-register-password
                                        >

                                        <button
                                            type="button"
                                            class="research-auth__show"
                                            data-password-toggle
                                        >
                                            Show
                                        </button>
                                    </div>
                                </label>

                                {{-- PASSWORD CONFIRMATION --}}
                                <label>
                                    <span>Ulangi sandi</span>

                                    <div class="research-auth__input">
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
                                                <path d="M8 10V7a4 4 0 0 1 8 0v3"/>
                                            </svg>
                                        </i>

                                        <input
                                            type="password"
                                            name="password_confirmation"
                                            placeholder="Ulangi password"
                                            autocomplete="new-password"
                                            required
                                            data-password-input
                                            data-register-confirmation
                                        >

                                        <button
                                            type="button"
                                            class="research-auth__show"
                                            data-password-toggle
                                        >
                                            Show
                                        </button>
                                    </div>
                                </label>

                                {{-- PROGRESS --}}
                                <div
                                    class="research-auth__signup-progress is-full"
                                    data-signup-progress
                                    style="--signup-progress: 0;"
                                    aria-live="polite"
                                >
                                    <div class="research-auth__signup-progress-ring">
                                        <strong data-signup-progress-value>
                                            0%
                                        </strong>
                                    </div>

                                    <div class="research-auth__signup-progress-copy">
                                        <b>
                                            Kelengkapan pendaftaran
                                        </b>

                                        <span data-signup-progress-text>
                                            Minimum 80% untuk membuat akun.
                                        </span>
                                    </div>
                                </div>

                                {{-- SUBMIT --}}
                                <button
                                    type="submit"
                                    class="research-auth__submit is-coral is-full"
                                    data-register-submit
                                    disabled
                                >
                                    <span data-register-submit-label>
                                        Lengkapi minimal 80%
                                    </span>

                                    <span>→</span>
                                </button>
                            </form>

                            <p class="research-auth__switch">
                                Sudah terdaftar?

                                <a
                                    href="{{ route('login') }}"
                                    data-auth-switch="signin"
                                >
                                    Masuk
                                </a>
                            </p>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <footer class="research-auth__footer">
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