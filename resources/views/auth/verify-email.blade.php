@extends('layouts.app')
@section('title', 'Verify Email — BacaDulu Research')
@section('content')
<section class="auth-page auth-page-compact">
    <div class="auth-card" data-reveal>
        <p class="eyebrow">VERIFY EMAIL</p>
        <h1>Check your inbox.</h1>
        <p class="auth-subtitle">Kami mengirim tautan verifikasi ke <strong>{{ auth()->user()->email }}</strong>. Verifikasi email diperlukan bila mode verifikasi diaktifkan administrator.</p>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <form method="POST" action="{{ route('verification.send') }}" class="form-stack">
            @csrf
            <button type="submit" class="btn btn-primary btn-full">Send verification link again</button>
        </form>

        <form method="POST" action="{{ route('logout') }}" class="auth-secondary-form">
            @csrf
            <button type="submit" class="text-button">Logout</button>
        </form>
    </div>
</section>
@endsection
