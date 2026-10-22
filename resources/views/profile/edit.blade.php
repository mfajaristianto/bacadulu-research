@extends('layouts.app')

@section('title', 'Profil — BacaDulu Research')

@section('body_class', 'br-profile-body')

@section('content')

@php
    use Illuminate\Support\Carbon;
    use Illuminate\Support\Str;

    $profileName =
        trim(
            (string) $profileUser->name
        );

    $initials =
        collect(
            preg_split(
                '/\s+/',
                $profileName
            )
        )
            ->filter()
            ->take(2)
            ->map(
                fn ($part) =>
                    Str::upper(
                        Str::substr(
                            $part,
                            0,
                            1
                        )
                    )
            )
            ->implode('');

    $initials =
        $initials
        ?: 'BR';

    $firstName =
        Str::before(
            $profileName,
            ' '
        );

    $birthDateLabel =
        $profileUser->date_of_birth
            ? Carbon::parse(
                $profileUser->date_of_birth
            )
                ->locale('id')
                ->translatedFormat(
                    'd F Y'
                )
            : null;

    $organizationType =
        $profileUser->organization_type
        ?: 'other';

    [$divisionLabel, $departmentLabel] =
        match($organizationType) {
            'education' => [
                'Fakultas / Unit',
                'Program Studi / Bidang',
            ],

            'company' => [
                'Divisi / Unit',
                'Departemen / Bidang',
            ],

            'government' => [
                'Unit / Direktorat',
                'Bagian / Bidang',
            ],

            'research' => [
                'Unit / Pusat',
                'Bidang / Program',
            ],

            'nonprofit' => [
                'Divisi / Unit',
                'Program / Bidang',
            ],

            'independent' => [
                'Unit / Peran',
                'Bidang Keahlian',
            ],

            default => [
                'Divisi / Unit',
                'Departemen / Bidang',
            ],
        };

    $hasProfessionalDetails =
        filled(
            $profileUser->institution
        )
        || filled(
            $profileUser->professional_role
        )
        || filled(
            $profileUser->division
        )
        || filled(
            $profileUser->department
        )
        || filled(
            $profileUser->organization_location
        );

    $interestText =
        old(
            'research_interests_text',
            implode(
                ', ',
                $researchInterests
            )
        );

    $credentialErrors = collect(['security','current_password','email','new_password','new_password_confirmation'])
        ->contains(fn ($key) => $errors->has($key));
    $autoOpenEditor =
        ($errors->any() && ! $credentialErrors)
        || session()->has('open_profile_editor');
@endphp


<div
    class="br-profile-shell"
    data-profile-page
    data-profile-open-editor="{{ $autoOpenEditor ? '1' : '0' }}"
>

    {{-- TOPBAR --}}
    <header class="br-profile-topbar">

        <div class="br-profile-topbar__left">

            <button
                type="button"
                class="br-profile-mobile-menu"
                data-chat-sidebar-open
                aria-label="Buka menu"
            >
                <span></span>
                <span></span>
                <span></span>
            </button>

            <a
                href="{{ route('workspace') }}"
                class="br-profile-brand"
            >
                <span>BacaDulu</span>
                <strong>Research</strong>
            </a>

        </div>


        <div class="br-profile-topbar__right">

            <a
                href="{{ route('workspace') }}"
                class="br-profile-workspace-button"
            >
                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    aria-hidden="true"
                >
                    <path d="M5 6h14v13H5z"/>
                    <path d="M8 6V4h8v2"/>
                    <path d="M9 11h6"/>
                </svg>

                <span>
                    Personal Workspace
                </span>

                <b>
                   ⌄
                </b>
            </a>


            <button
                type="button"
                class="br-profile-help"
                data-profile-help
                aria-label="Bantuan"
            >
                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                >
                    <circle
                        cx="12"
                        cy="12"
                        r="9"
                    />
                    <path d="M9.8 9a2.4 2.4 0 0 1 4.6 1c0 2-2.4 2.2-2.4 4"/>
                    <path d="M12 17h.01"/>
                </svg>
            </button>


            <span class="br-profile-topbar__divider"></span>


            <a
                href="{{ route('profile.edit') }}"
                class="br-profile-account"
            >
                @if($avatarUrl)
                    <img
                        src="{{ $avatarUrl }}"
                        alt="{{ $profileName }}"
                        referrerpolicy="no-referrer"
                    >
                @else
                    <span>
                        {{ $initials }}
                    </span>
                @endif

                <strong>
                    {{ $firstName }}
                </strong>

                <b>
                   ⌄
                </b>
            </a>

        </div>

    </header>


    <div class="br-profile-layout">

        {{-- Shared, locked Research sidebar: same Recent and nav as Workspace --}}
        <x-research-sidebar :conversation="null" :recent-conversations="$recentConversations" />
        <button type="button" class="research-sidebar-backdrop" data-chat-sidebar-backdrop aria-label="Tutup navigasi Research"></button>

        {{-- CONTENT --}}
        <main class="br-profile-content">

            <header class="br-profile-heading">
                <h1>
                    Profil
                </h1>

                <p>
                    Kelola informasi profil, preferensi riset, dan pengaturan akun Anda.
                </p>
            </header>


            @if(session('success'))
                <div class="br-profile-alert br-profile-alert--success">
                    {{ session('success') }}
                </div>
            @endif


            {{-- PROFILE HERO --}}
            <section class="br-profile-hero">

                <div class="br-profile-hero__wave"></div>


                <div class="br-profile-avatar-wrap">

                    <div class="br-profile-avatar">
                        @if($avatarUrl)
                            <img
                                src="{{ $avatarUrl }}"
                                alt="{{ $profileName }}"
                                data-profile-main-avatar
                            >
                        @else
                            <span data-profile-avatar-fallback>
                                {{ $initials }}
                            </span>
                        @endif
                    </div>


                    <button
                        type="button"
                        class="br-profile-camera"
                        data-profile-edit="personal"
                        aria-label="Ganti foto profil"
                    >
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M5 8h3l1.5-2h5L16 8h3v10H5z"/>
                            <circle cx="12" cy="13" r="3"/>
                        </svg>
                    </button>

                </div>


                <div class="br-profile-hero__copy">

                    <h2>
                        {{ $profileName }}
                    </h2>


                    <div class="br-profile-hero__meta">

                        <span>
                            <svg viewBox="0 0 24 24" fill="none">
                                <circle cx="12" cy="8" r="3"/>
                                <path d="M6 20c0-4 2.5-6 6-6s6 2 6 6"/>
                            </svg>

                            {{ $profileUser->professional_role
                                ?: 'Tambahkan peran profesional' }}
                        </span>


                        <i></i>


                        <span>
                            <svg viewBox="0 0 24 24" fill="none">
                                <path d="M5 20V7h14v13"/>
                                <path d="M8 7V4h8v3"/>
                                <path d="M9 11h2"/>
                                <path d="M13 11h2"/>
                                <path d="M9 15h2"/>
                                <path d="M13 15h2"/>
                            </svg>

                            {{ $profileUser->institution
                                ?: 'Tambahkan institusi / organisasi' }}
                        </span>

                    </div>


                    <p>
                        {{ $profileUser->bio
                            ?: 'Lengkapi bio singkat untuk menjelaskan fokus pekerjaan, pengalaman, atau bidang riset Anda.' }}
                    </p>


                    <div class="br-profile-interest-row">
    @forelse(array_slice($researchInterests, 0, 5) as $interest)
        <span>
            {{ $interest }}
        </span>
    @empty
        <span class="is-empty">
            + Tambahkan minat riset
        </span>
    @endforelse
</div>  

                </div>


                <button
                    type="button"
                    class="br-profile-edit-main"
                    data-profile-edit="personal"
                >
                    <svg viewBox="0 0 24 24" fill="none">
                        <path d="M4 20h4L19 9l-4-4L4 16z"/>
                        <path d="m13 7 4 4"/>
                    </svg>

                    Edit Profil
                </button>

            </section>


            {{-- STATS --}}
            <section class="br-profile-stats">

                <article>
                    <span class="is-blue">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M6 3h9l4 4v14H6z"/>
                            <path d="M15 3v5h5"/>
                            <path d="M9 12h6"/>
                            <path d="M9 16h5"/>
                        </svg>
                    </span>

                    <div>
                        <strong>
                            {{ number_format($stats['research_sessions']) }}
                        </strong>

                        <b>
                            Total Research Sessions
                        </b>

                        <small>
                            seluruh aktivitas research
                        </small>
                    </div>
                </article>


                <article>
                    <span class="is-orange">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M6 4h12v17l-6-4-6 4z"/>
                        </svg>
                    </span>

                    <div>
                        <strong>
                            {{ number_format($stats['saved_projects']) }}
                        </strong>

                        <b>
                            Saved Projects
                        </b>

                        <small>
                            research yang disimpan
                        </small>
                    </div>
                </article>


                <article>
                    <span class="is-purple">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M6 3h9l4 4v14H6z"/>
                            <path d="M15 3v5h5"/>
                            <path d="M9 12h6"/>
                            <path d="M9 16h6"/>
                        </svg>
                    </span>

                    <div>
                        <strong>
                            {{ number_format($stats['documents_exported']) }}
                        </strong>

                        <b>
                            Documents Exported
                        </b>

                        <small>
                            dokumen research yang dibuat
                        </small>
                    </div>
                </article>

            </section>


            {{-- CARDS --}}
            <section class="br-profile-grid">

                {{-- PERSONAL --}}
                <article class="br-profile-card">

                    <header>
                        <span class="br-profile-card__icon is-orange">
                            <svg viewBox="0 0 24 24" fill="none">
                                <circle cx="12" cy="7" r="3"/>
                                <path d="M6 20c0-4 2.5-7 6-7s6 3 6 7"/>
                            </svg>
                        </span>

                        <div>
                            <h3>
                                Informasi Pribadi
                            </h3>

                            <p>
                                Informasi dasar akun Anda.
                            </p>
                        </div>
                    </header>


                    <div class="br-profile-personal-grid">

                        <div>

                            <div class="br-profile-data-row">
                                <span>
                                    <svg viewBox="0 0 24 24" fill="none">
                                        <circle cx="12" cy="8" r="3"/>
                                        <path d="M6 20c0-4 2.5-6 6-6s6 2 6 6"/>
                                    </svg>
                                </span>

                                <div>
                                    <small>
                                        Nama Lengkap
                                    </small>

                                    <strong>
                                        {{ $profileName }}
                                    </strong>
                                </div>
                            </div>


                            <div class="br-profile-data-row">
                                <span>
                                    <svg viewBox="0 0 24 24" fill="none">
                                        <path d="M7 4h3l2 5-2 2c1.3 2.4 2.6 3.7 5 5l2-2 4 2v3c0 1-1 2-2 2C10 21 3 14 3 6c0-1 1-2 2-2z"/>
                                    </svg>
                                </span>

                                <div>
                                    <small>
                                        Nomor Telepon
                                    </small>

                                    <strong>
                                        {{ $phone ?: 'Belum diisi' }}
                                    </strong>
                                </div>
                            </div>


                            <div class="br-profile-data-row">
                                <span>
                                    <svg viewBox="0 0 24 24" fill="none">
                                        <rect x="4" y="6" width="16" height="14" rx="2"/>
                                        <path d="M8 3v5"/>
                                        <path d="M16 3v5"/>
                                        <path d="M4 10h16"/>
                                    </svg>
                                </span>

                                <div>
                                    <small>
                                        Tanggal Lahir
                                    </small>

                                    <strong>
                                        {{ $birthDateLabel ?: 'Belum diisi' }}
                                    </strong>
                                </div>
                            </div>

                        </div>


                        <div class="br-profile-data-row br-profile-data-row--address">
                            <span>
                                <svg viewBox="0 0 24 24" fill="none">
                                    <path d="M12 21s6-5 6-11a6 6 0 0 0-12 0c0 6 6 11 6 11z"/>
                                    <circle cx="12" cy="10" r="2"/>
                                </svg>
                            </span>

                            <div>
                                <small>
                                    Alamat
                                </small>

                                <strong>
                                    {{ $address ?: 'Belum diisi' }}
                                </strong>
                            </div>
                        </div>

                    </div>

                </article>


                {{-- INTEREST --}}
                <article class="br-profile-card">

                    <header>
                        <span class="br-profile-card__icon is-orange">
                            <svg viewBox="0 0 24 24" fill="none">
                                <circle cx="12" cy="12" r="8"/>
                                <circle cx="12" cy="12" r="4"/>
                                <circle cx="12" cy="12" r="1"/>
                            </svg>
                        </span>

                        <div>
                            <h3>
                                Minat dan Fokus Riset
                            </h3>

                            <p>
                                Topik dan area penelitian yang Anda minati.
                            </p>
                        </div>
                    </header>


                    <div class="br-profile-interest-card">
                        @forelse($researchInterests as $interest)
                            <span>
                                {{ $interest }}
                            </span>
                        @empty
                            <span class="br-profile-empty-hint">Belum diisi · Gunakan Edit Profil untuk melengkapi</span>
                        @endforelse
                    </div>

                </article>


                {{-- PROFESSIONAL --}}
                <article class="br-profile-card">

                    <header>
                        <span class="br-profile-card__icon is-teal">
                            <svg viewBox="0 0 24 24" fill="none">
                                <path d="M4 20V7h16v13"/>
                                <path d="M8 7V4h8v3"/>
                                <path d="M8 11h2"/>
                                <path d="M14 11h2"/>
                                <path d="M8 15h2"/>
                                <path d="M14 15h2"/>
                            </svg>
                        </span>

                        <div>
                            <h3>
                                Afiliasi dan Informasi Profesional
                            </h3>

                            <p>
                                Informasi organisasi dan peran profesional Anda.
                            </p>
                        </div>
                    </header>


                    @if($hasProfessionalDetails)

                        <dl class="br-professional-list">

                            @if($profileUser->institution)
                                <div>
                                    <dt>
                                        Institusi / Organisasi
                                    </dt>

                                    <dd>
                                        {{ $profileUser->institution }}
                                    </dd>
                                </div>
                            @endif


                            @if($profileUser->division)
                                <div>
                                    <dt>
                                        {{ $divisionLabel }}
                                    </dt>

                                    <dd>
                                        {{ $profileUser->division }}
                                    </dd>
                                </div>
                            @endif


                            @if($profileUser->department)
                                <div>
                                    <dt>
                                        {{ $departmentLabel }}
                                    </dt>

                                    <dd>
                                        {{ $profileUser->department }}
                                    </dd>
                                </div>
                            @endif


                            @if($profileUser->professional_role)
                                <div>
                                    <dt>
                                        Jabatan / Peran
                                    </dt>

                                    <dd>
                                        {{ $profileUser->professional_role }}
                                    </dd>
                                </div>
                            @endif


                            @if($profileUser->organization_location)
                                <div>
                                    <dt>
                                        Lokasi
                                    </dt>

                                    <dd>
                                        {{ $profileUser->organization_location }}
                                    </dd>
                                </div>
                            @endif


                            @if($profileUser->public_profile_url)
                                <div>
                                    <dt>
                                        Profil Publik
                                    </dt>

                                    <dd>
                                        <a
                                            href="{{ $profileUser->public_profile_url }}"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            <svg viewBox="0 0 24 24" fill="none">
                                                <path d="M10 13a5 5 0 0 0 7.5.5l2-2a5 5 0 0 0-7-7"/>
                                                <path d="M14 11a5 5 0 0 0-7.5-.5l-2 2a5 5 0 0 0 7 7"/>
                                            </svg>

                                            {{ Str::limit(
                                                $profileUser->public_profile_url,
                                                42
                                            ) }}
                                        </a>
                                    </dd>
                                </div>
                            @endif

                        </dl>


                        @if(
                            ! $profileUser->division
                            || ! $profileUser->professional_role
                        )
                            <div class="br-profile-info-note">
                                <svg viewBox="0 0 24 24" fill="none">
                                    <circle cx="12" cy="12" r="9"/>
                                    <path d="M12 11v5"/>
                                    <path d="M12 8h.01"/>
                                </svg>

                                Lengkapi informasi lainnya seperti divisi,
                                jabatan, bidang, atau lokasi agar profil
                                profesional Anda lebih lengkap.
                            </div>
                        @endif

                    @else

                        <div class="br-profile-professional-empty">
                            <span>
                                <svg viewBox="0 0 24 24" fill="none">
                                    <path d="M4 20V7h16v13"/>
                                    <path d="M8 7V4h8v3"/>
                                </svg>
                            </span>

                            <div>
                                <strong>
                                    Lengkapi afiliasi profesional
                                </strong>

                                <p>
                                    Tambahkan perusahaan, kampus, instansi,
                                    organisasi, atau status profesional independen.
                                </p>
                            </div>
                        </div>

                    @endif

                </article>


                {{-- SECURITY --}}
                <article
                    class="br-profile-card"
                    id="security"
                >

                    <header>
                        <span class="br-profile-card__icon is-blue">
                            <svg viewBox="0 0 24 24" fill="none">
                                <path d="M12 3 5 6v5c0 5 3 8 7 10 4-2 7-5 7-10V6z"/>
                                <path d="M9 12l2 2 4-4"/>
                            </svg>
                        </span>

                        <div>
                            <h3>
                                Akun & Keamanan
                            </h3>

                            <p>
                                Kelola informasi login, keamanan, dan data akun Anda.
                            </p>
                        </div>
                    </header>


                    <div class="br-security-list">

                        <div>
                            <span>
                                <svg viewBox="0 0 24 24" fill="none">
                                    <rect x="4" y="6" width="16" height="12" rx="2"/>
                                    <path d="m5 8 7 5 7-5"/>
                                </svg>
                            </span>

                            <small>Email</small>
                            <strong>{{ $profileUser->email }}</strong>

                            <button
                                type="button"
                                class="br-security-action"
                                data-security-open
                            >
                                Kelola
                            </button>
                        </div>

                        <div>
                            <span>
                                <svg viewBox="0 0 24 24" fill="none">
                                    <rect x="6" y="10" width="12" height="10" rx="2"/>
                                    <path d="M9 10V7a3 3 0 0 1 6 0v3"/>
                                </svg>
                            </span>

                            <small>Kata Sandi</small>
                            <strong>••••••••••••</strong>

                            <button
                                type="button"
                                class="br-security-action"
                                data-security-open
                            >
                                Ganti
                            </button>
                        </div>

                    </div>

                    <p class="br-security-note">
                        Demi keamanan, kata sandi yang tersimpan tidak dapat dibuka kembali karena
                        disimpan sebagai hash. Tombol <b>Lihat</b> di bawah hanya menampilkan
                        password yang sedang Anda ketik.
                    </p>

                    <form
                        action="{{ route('profile.credentials.update') }}"
                        method="POST"
                        class="br-security-form"
                        id="account-security-form"
                        data-security-panel
                        @if(! $credentialErrors) hidden @endif
                    >
                        @csrf
                        @method('PATCH')

                        <div class="br-security-form__head">
                            <div>
                                <strong>Kelola email & kata sandi</strong>
                                <p>
                                    Masukkan kata sandi saat ini untuk memverifikasi perubahan.
                                    Jika verifikasi gagal 5 kali, perubahan akun dikunci sementara selama 15 menit.
                                </p>
                            </div>

                            <button
                                type="button"
                                class="br-security-form__close"
                                data-security-close
                                aria-label="Tutup pengaturan keamanan"
                            >×</button>
                        </div>

                        <div class="br-security-form__grid">
                            <label>
                                <span>Email Akun</span>
                                <input
                                    type="email"
                                    name="email"
                                    value="{{ old('email', $profileUser->email) }}"
                                    autocomplete="email"
                                    required
                                >
                            </label>

                            <label>
                                <span>Kata Sandi Saat Ini</span>
                                <span class="br-security-password">
                                    <input
                                        id="security-current"
                                        type="password"
                                        name="current_password"
                                        autocomplete="current-password"
                                        required
                                    >
                                    <button
                                        type="button"
                                        data-toggle-security-password="security-current"
                                        aria-label="Tampilkan kata sandi saat ini"
                                    >Lihat</button>
                                </span>
                            </label>

                            <label>
                                <span>Kata Sandi Baru (opsional)</span>
                                <span class="br-security-password">
                                    <input
                                        id="security-new"
                                        type="password"
                                        name="new_password"
                                        minlength="12"
                                        autocomplete="new-password"
                                        placeholder="Minimal 12 karakter"
                                    >
                                    <button
                                        type="button"
                                        data-toggle-security-password="security-new"
                                        aria-label="Tampilkan kata sandi baru"
                                    >Lihat</button>
                                </span>
                            </label>

                            <label>
                                <span>Ulangi Kata Sandi Baru</span>
                                <span class="br-security-password">
                                    <input
                                        id="security-confirm"
                                        type="password"
                                        name="new_password_confirmation"
                                        autocomplete="new-password"
                                    >
                                    <button
                                        type="button"
                                        data-toggle-security-password="security-confirm"
                                        aria-label="Tampilkan konfirmasi kata sandi"
                                    >Lihat</button>
                                </span>
                            </label>

                            <div class="br-security-password-requirements" aria-label="Syarat kata sandi baru">
                                <span>12+ karakter</span>
                                <span>1 huruf besar</span>
                                <span>1 huruf kecil</span>
                                <span>1 angka</span>
                                <span>1 simbol</span>
                            </div>
                        </div>

                        @if($credentialErrors)
                            <p class="br-security-form__error" role="alert">
                                {{
                                    $errors->first('security')
                                    ?: $errors->first('current_password')
                                    ?: $errors->first('email')
                                    ?: $errors->first('new_password')
                                    ?: $errors->first('new_password_confirmation')
                                }}
                            </p>
                        @endif

                        <div class="br-security-form__footer">
                            <a href="{{ route('password.request') }}">
                                Lupa kata sandi? Reset melalui email →
                            </a>

                            <button type="submit" class="br-security-form__submit">
                                Simpan perubahan akun
                            </button>
                        </div>
                    </form>
                </article>

            </section>

        </main>

    </div>


    {{-- EDIT PROFILE MODAL --}}
    <div
        class="br-profile-modal"
        data-profile-modal
        hidden
    >

        <button
            type="button"
            class="br-profile-modal__backdrop"
            data-profile-modal-close
            aria-label="Tutup"
        ></button>


        <section
            class="br-profile-modal__panel"
            role="dialog"
            aria-modal="true"
            aria-labelledby="profile-modal-title"
        >

            <header>
                <div>
                    <span>
                        PROFILE SETTINGS
                    </span>

                    <h2 id="profile-modal-title">
                        Edit Profil
                    </h2>

                    <p>
                        Lengkapi data pribadi dan profesional Anda.
                    </p>
                </div>

                <button
                    type="button"
                    data-profile-modal-close
                    aria-label="Tutup"
                >
                    ×
                </button>
            </header>


            <form
                method="POST"
                action="{{ route('profile.update') }}"
                enctype="multipart/form-data"
                data-profile-form
            >
                @csrf
                @method('PATCH')


                {{-- PERSONAL --}}
                <section
                    class="br-profile-form-section"
                    data-profile-section="personal"
                >

                    <div class="br-profile-form-section__title">
                        <span>01</span>

                        <div>
                            <h3>
                                Informasi Pribadi
                            </h3>

                            <p>
                                Data dasar profil Anda.
                            </p>
                        </div>
                    </div>


                    <div class="br-profile-avatar-editor">

                        <div
                            class="br-profile-avatar-editor__preview"
                            data-profile-avatar-preview
                        >
                            @if($avatarUrl)
                                <img
                                    src="{{ $avatarUrl }}"
                                    alt=""
                                    data-profile-preview-image
                                >
                            @else
                                <span>
                                    {{ $initials }}
                                </span>
                            @endif
                        </div>


                        <label>
                            <strong>
                                Foto Profil
                            </strong>

                            <span>
                                JPG, PNG, atau WEBP maksimal 5 MB.
                            </span>

                            <b>
                                Pilih Foto
                            </b>

                            <input
                                type="file"
                                name="avatar"
                                accept="image/jpeg,image/png,image/webp"
                                data-profile-avatar-input
                            >
                        </label>

                    </div>


                    <div class="br-profile-form-grid">

                        <label>
                            <span>
                                Nama Lengkap
                            </span>

                            <input
                                type="text"
                                name="name"
                                value="{{ old('name', $profileUser->name) }}"
                                maxlength="120"
                                required
                            >

                            @error('name')
                                <small>
                                    {{ $message }}
                                </small>
                            @enderror
                        </label>


                        <label>
                            <span>
                                Nomor Telepon
                            </span>

                            <input
                                type="text"
                                name="phone"
                                value="{{ old('phone', $phone) }}"
                                maxlength="40"
                                placeholder="+62..."
                            >

                            @error('phone')
                                <small>
                                    {{ $message }}
                                </small>
                            @enderror
                        </label>


                        <label>
                            <span>
                                Tanggal Lahir
                            </span>

                            <input
                                type="date"
                                name="date_of_birth"
                                value="{{ old(
                                    'date_of_birth',
                                    $profileUser->date_of_birth
                                        ? Carbon::parse(
                                            $profileUser->date_of_birth
                                        )->format('Y-m-d')
                                        : ''
                                ) }}"
                            >

                            @error('date_of_birth')
                                <small>
                                    {{ $message }}
                                </small>
                            @enderror
                        </label>


                        <label class="is-wide">
                            <span>
                                Alamat
                            </span>

                            <textarea
                                name="address"
                                rows="3"
                                maxlength="1200"
                                placeholder="Alamat tempat tinggal"
                            >{{ old('address', $address) }}</textarea>

                            @error('address')
                                <small>
                                    {{ $message }}
                                </small>
                            @enderror
                        </label>


                        <label class="is-wide">
                            <span>
                                Bio Singkat
                            </span>

                            <textarea
                                name="bio"
                                rows="3"
                                maxlength="600"
                                placeholder="Ceritakan pekerjaan, pengalaman, atau fokus Anda"
                            >{{ old('bio', $profileUser->bio) }}</textarea>

                            @error('bio')
                                <small>
                                    {{ $message }}
                                </small>
                            @enderror
                        </label>

                    </div>

                </section>


                {{-- PROFESSIONAL --}}
                <section
                    class="br-profile-form-section"
                    data-profile-section="professional"
                >

                    <div class="br-profile-form-section__title">
                        <span>02</span>

                        <div>
                            <h3>
                                Afiliasi dan Informasi Profesional
                            </h3>

                            <p>
                                Berlaku untuk kampus, perusahaan, pemerintah,
                                organisasi, maupun profesional independen.
                            </p>
                        </div>
                    </div>


                    <div class="br-profile-form-grid">

                        <label>
                            <span>
                                Jenis Organisasi
                            </span>

                            <select
                                name="organization_type"
                                data-organization-type
                            >
                                <option value="">
                                    Pilih jenis
                                </option>

                                <option
                                    value="education"
                                    @selected(old(
                                        'organization_type',
                                        $profileUser->organization_type
                                    ) === 'education')
                                >
                                    Pendidikan / Kampus
                                </option>

                                <option
                                    value="company"
                                    @selected(old(
                                        'organization_type',
                                        $profileUser->organization_type
                                    ) === 'company')
                                >
                                    Perusahaan
                                </option>

                                <option
                                    value="government"
                                    @selected(old(
                                        'organization_type',
                                        $profileUser->organization_type
                                    ) === 'government')
                                >
                                    Instansi Pemerintah
                                </option>

                                <option
                                    value="research"
                                    @selected(old(
                                        'organization_type',
                                        $profileUser->organization_type
                                    ) === 'research')
                                >
                                    Lembaga Riset
                                </option>

                                <option
                                    value="nonprofit"
                                    @selected(old(
                                        'organization_type',
                                        $profileUser->organization_type
                                    ) === 'nonprofit')
                                >
                                    Organisasi / Nonprofit
                                </option>

                                <option
                                    value="independent"
                                    @selected(old(
                                        'organization_type',
                                        $profileUser->organization_type
                                    ) === 'independent')
                                >
                                    Independen / Freelancer
                                </option>

                                <option
                                    value="other"
                                    @selected(old(
                                        'organization_type',
                                        $profileUser->organization_type
                                    ) === 'other')
                                >
                                    Lainnya
                                </option>
                            </select>
                        </label>


                        <label>
                            <span>
                                Institusi / Organisasi
                            </span>

                            <input
                                type="text"
                                name="institution"
                                value="{{ old(
                                    'institution',
                                    $profileUser->institution
                                ) }}"
                                maxlength="190"
                                placeholder="Nama perusahaan, kampus, instansi, atau organisasi"
                            >
                        </label>


                        <label>
                            <span data-division-label>
                                Divisi / Unit
                            </span>

                            <input
                                type="text"
                                name="division"
                                value="{{ old(
                                    'division',
                                    $profileUser->division
                                ) }}"
                                maxlength="160"
                                placeholder="Contoh: Finance, FEB, Direktorat..."
                            >
                        </label>


                        <label>
                            <span data-department-label>
                                Departemen / Bidang
                            </span>

                            <input
                                type="text"
                                name="department"
                                value="{{ old(
                                    'department',
                                    $profileUser->department
                                ) }}"
                                maxlength="160"
                                placeholder="Bidang, departemen, program, atau keahlian"
                            >
                        </label>


                        <label>
                            <span>
                                Jabatan / Peran
                            </span>

                            <input
                                type="text"
                                name="professional_role"
                                value="{{ old(
                                    'professional_role',
                                    $profileUser->professional_role
                                ) }}"
                                maxlength="160"
                                placeholder="Contoh: Finance Staff, Dosen, Manager, Consultant"
                            >
                        </label>


                        <label>
                            <span>
                                Lokasi Organisasi
                            </span>

                            <input
                                type="text"
                                name="organization_location"
                                value="{{ old(
                                    'organization_location',
                                    $profileUser->organization_location
                                ) }}"
                                maxlength="190"
                                placeholder="Contoh: Jakarta, Depok, Bandung"
                            >
                        </label>


                        <label class="is-wide">
                            <span>
                                Profil Publik
                            </span>

                            <input
                                type="url"
                                name="public_profile_url"
                                value="{{ old(
                                    'public_profile_url',
                                    $profileUser->public_profile_url
                                ) }}"
                                maxlength="500"
                                placeholder="https://linkedin.com/... atau https://orcid.org/..."
                            >

                            <small class="br-profile-field-help">
                                Opsional. Bisa LinkedIn, ORCID, profil perusahaan,
                                website pribadi, atau profil profesional lainnya.
                            </small>
                        </label>

                    </div>

                </section>


                {{-- RESEARCH --}}
                <section
                    class="br-profile-form-section"
                    data-profile-section="research"
                >

                    <div class="br-profile-form-section__title">
                        <span>03</span>

                        <div>
                            <h3>
                                Minat dan Fokus Riset
                            </h3>

                            <p>
                                Pisahkan setiap topik menggunakan koma.
                            </p>
                        </div>
                    </div>


                    <label class="br-profile-interest-input">
                        <span>
                            Topik / Minat
                        </span>

                        <textarea
                            name="research_interests_text"
                            rows="4"
                            maxlength="1000"
                            placeholder="ESG Disclosure, Corporate Governance, Artificial Intelligence, Firm Value"
                        >{{ $interestText }}</textarea>

                        <small>
                            Maksimum 12 topik.
                        </small>
                    </label>

                </section>


                <footer class="br-profile-modal__footer">

                    <button
                        type="button"
                        class="is-cancel"
                        data-profile-modal-close
                    >
                        Batal
                    </button>

                    <button
                        type="submit"
                        class="is-save"
                    >
                        Simpan Perubahan

                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M5 12h14"/>
                            <path d="m15 8 4 4-4 4"/>
                        </svg>
                    </button>

                </footer>

            </form>

        </section>

    </div>

</div>

@endsection