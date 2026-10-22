@extends('layouts.app')
@section('title', 'Akun Berhasil Dibuat — BacaDulu Research')
@section('content')
<section class="br-welcome" data-register-success data-target="{{ route('dashboard') }}">
    <div class="br-welcome__panel">
        <span class="br-welcome__symbol" aria-hidden="true">✓</span>
        <span class="br-doc-eyebrow">SELAMAT DATANG DI BACADULU</span>
        <h1>Akun berhasil dibuat</h1>
        <p>Anda sudah masuk. Workspace siap digunakan. Panduan singkat akan muncul satu kali untuk mengenalkan fitur utama.</p>
        <div class="br-welcome__countdown" role="status" aria-live="polite">
            Menuju dashboard dalam <strong data-welcome-seconds>5</strong> detik
        </div>
        <a class="btn btn-primary" href="{{ route('dashboard') }}">Masuk Dashboard Sekarang →</a>
        <small>Data profil lainnya bisa dilengkapi nanti dari menu Profil.</small>
    </div>
</section>
@endsection
