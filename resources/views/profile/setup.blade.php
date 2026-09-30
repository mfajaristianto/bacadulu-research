@extends('layouts.app')

@section('title', 'Lengkapi Profil — BacaDulu Research')

@section('content')
<section class="profile-page profile-page--setup">
    <header
        class="profile-head"
        data-reveal
    >
        <div class="profile-head-copy">
            <p class="eyebrow">PROFILE SETUP</p>

            <h1 class="display-type">
                Sedikit konteks sebelum mulai research.
            </h1>

            <p>
                Lengkapi profil sampai minimum
                {{ config('research.profile_gate', 80) }}%.
                Field kosong tetap dapat disimpan dan tidak akan
                merusak halaman profil.
            </p>
        </div>

        <div
            class="profile-score"
            aria-label="Kelengkapan profil {{ $user->profileCompletion() }} persen"
        >
            <span>Kelengkapan</span>

            <strong>
                {{ $user->profileCompletion() }}%
            </strong>
        </div>
    </header>

    <div
        class="profile-progress-card"
        data-reveal
    >
        <div class="profile-progress-meta">
            <div>
                <strong>
                    {{ $user->profileCompletion() }}%
                </strong>

                <span>
                    dari minimum
                    {{ config('research.profile_gate', 80) }}%
                </span>
            </div>

            @if ($user->profileCompletion() >= config('research.profile_gate', 80))
                <span class="profile-progress-status is-ready">
                    Siap
                </span>
            @else
                <span class="profile-progress-status">
                    Belum lengkap
                </span>
            @endif
        </div>

        <div
            class="setup-progress"
            role="progressbar"
            aria-valuemin="0"
            aria-valuemax="100"
            aria-valuenow="{{ $user->profileCompletion() }}"
        >
            <span
                data-progress-value="{{ min(
                    100,
                    $user->profileCompletion()
                ) }}"
            ></span>
        </div>

        @if ($user->missingProfileFields())
            <p>
                Masih kosong:
                <strong>
                    {{ implode(
                        ', ',
                        $user->missingProfileFields()
                    ) }}
                </strong>
            </p>
        @else
            <p>
                Field profil yang digunakan untuk kelengkapan sudah terisi.
            </p>
        @endif
    </div>

    @if (session('success'))
        <div
            class="alert alert-success"
            role="status"
        >
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div
            class="alert alert-error"
            role="alert"
        >
            Periksa kembali field yang ditandai di bawah.
        </div>
    @endif

    <div data-reveal>
        @include('components.profile-form', [
            'user' => $user,
            'mode' => 'setup',
        ])
    </div>
</section>
@endsection