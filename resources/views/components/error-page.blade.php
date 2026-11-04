{{--
    Halaman error HTTP BacaDulu: satu komposisi, satu Bubu, maksimal dua aksi.
    Mandiri (dokumen penuh, tanpa csrf_token/session/layout aplikasi) supaya tetap
    bisa dirender saat 500/503. Tidak pernah menampilkan pesan exception.

    <x-error-page code="404" mascot="no-results" heading="…" description="…" primary="home" secondary="back" />

    Aksi: home (Workspace bila login, Beranda bila tamu) | beranda | login | back | retry
--}}
@props([
    'code',
    'mascot',
    'heading',
    'description',
    'primary' => 'home',
    'secondary' => null,
])

@php
    try {
        $signedIn = auth()->check();
    } catch (\Throwable $e) {
        $signedIn = false;
    }

    $isGet = request()->isMethod('GET');
    $homeHref = $signedIn ? route('workspace') : url('/');

    // Tautan aman: dipakai sebagai fallback "Kembali" dan "Coba lagi" untuk request non-GET.
    $actions = [
        'home' => ['label' => $signedIn ? 'Ke Workspace' : 'Ke Beranda', 'href' => $homeHref],
        'beranda' => ['label' => 'Ke Beranda', 'href' => url('/')],
        'login' => ['label' => 'Masuk lagi', 'href' => route('login')],
        'back' => ['label' => 'Kembali', 'href' => $homeHref, 'attr' => 'data-error-back'],
        'retry' => ['label' => 'Coba lagi', 'href' => $isGet ? request()->fullUrl() : $homeHref, 'attr' => 'data-error-retry'],
    ];

    $buttons = collect([$primary, $secondary])->filter()->map(fn ($key) => $actions[$key])->values();
@endphp
<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="#fbf8f4">

    <title>{{ $code }} — BacaDulu Research</title>

    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="error-body">
    <main class="error-page" data-error-page data-method="{{ $isGet ? 'get' : 'other' }}">

        <header class="error-page__top">
            <a href="{{ url('/') }}" class="error-page__brand">
                <span>BacaDulu</span>
                <strong>Research</strong>
            </a>
        </header>

        <section class="error-page__body">
            <img
                class="error-page__mascot"
                src="{{ asset('assets/mascot/mascot-'.$mascot.'.png') }}"
                alt=""
                width="260"
                height="260"
                decoding="async"
            >

            <p class="error-page__code">ERROR {{ $code }}</p>

            <h1>{{ $heading }}</h1>

            <p class="error-page__text">{{ $description }}</p>

            <div class="error-page__actions">
                @foreach ($buttons as $index => $button)
                    <a
                        href="{{ $button['href'] }}"
                        class="error-page__btn {{ $index === 0 ? 'error-page__btn--primary' : 'error-page__btn--plain' }}"
                        {!! $button['attr'] ?? '' !!}
                    >
                        {{ $button['label'] }}
                    </a>
                @endforeach
            </div>
        </section>

    </main>
</body>

</html>
