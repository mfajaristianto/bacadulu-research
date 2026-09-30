@extends('layouts.app')

@section('title', 'Profil Saya — BacaDulu Research')

@section('content')
<section class="profile-page">
    <header
        class="profile-head"
        data-reveal
    >
        <div class="profile-head-copy">
            <p class="eyebrow">PROFIL SAYA</p>

            <h1 class="display-type">
                Identitas di dalam workspace.
            </h1>

            <p>
                Kelola foto dan informasi profil tanpa mengubah
                history atau conversation research Anda.
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
            'mode' => 'edit',
        ])
    </div>
</section>
@endsection