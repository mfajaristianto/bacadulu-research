@extends('layouts.app')

@section('title', 'Akun Berhasil Dibuat — BacaDulu Research')

@section('content')

{{-- Success setelah register: shell auth yang sama dengan Masuk/Daftar, Bubu sebagai fokus. --}}
<section class="research-auth research-auth--success">
    <div class="research-auth__page">

        <div class="research-auth__shell">
            <header class="research-auth__header">
                <a href="{{ route('home') }}" class="research-auth__brand">
                    <span>BacaDulu</span>
                    <strong>Research</strong>
                </a>
            </header>

            <div class="research-auth__body">

                {{-- Bubu + speech bubble --}}
                <section class="research-auth__visual research-auth__success-art">
                    <p class="research-auth__bubble" role="status">
                        Yeay, akunmu berhasil dibuat!
                    </p>

                    <div class="research-auth__stage">
                        <img
                            class="research-auth__mascot"
                            src="{{ asset('assets/mascot/mascot-success-verified.png') }}"
                            alt="Bubu, maskot BacaDulu, tersenyum dengan tanda centang"
                            width="280"
                            height="280"
                            decoding="async"
                        >
                    </div>
                </section>

                {{-- Pesan + langkah berikutnya --}}
                <section class="research-auth__forms research-auth__success-copy">
                    <div class="research-auth__form-inner">
                        <h1>Siap mulai research?</h1>

                        <p class="research-auth__success-lead">
                            Akunmu sudah aktif. Mulai dari judul atau topik pertamamu.
                        </p>

                        <ol class="research-auth__status" aria-label="Status: akun selesai, profil selesai, siap memulai research">
                            <li class="is-done">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>
                                Akun
                            </li>
                            <li class="is-done">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>
                                Profil
                            </li>
                            <li class="is-current" aria-current="step">
                                <i aria-hidden="true"></i>
                                Mulai
                            </li>
                        </ol>

                        <a class="research-auth__submit is-navy" href="{{ route('workspace') }}">
                            <span>Mulai Research</span>
                            <span aria-hidden="true">→</span>
                        </a>

                        <p class="research-auth__success-note">
                            Nanti kamu bisa memilih topik, kategori, dan research tool sesuai kebutuhanmu.
                        </p>
                    </div>
                </section>

            </div>
        </div>

        <footer class="research-auth__footer">
            <span>© {{ now()->year }} BacaDulu Research</span>
            <span>Research with evidence, not just answers.</span>
        </footer>

    </div>
</section>

@endsection
