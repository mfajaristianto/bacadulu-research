<!doctype html>

<html lang="id">

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <meta
        name="csrf-token"
        content="{{ csrf_token() }}"
    >

    <meta
        name="description"
        content="@yield(
            'meta_description',
            'BacaDulu Research membantu pengguna bergerak dari pertanyaan penelitian menuju evidence, insight, dan dokumen yang siap digunakan.'
        )"
    >

    <meta
        name="theme-color"
        content="#17172B"
    >

    <title>
        @yield(
            'title',
            'BacaDulu Research'
        )
    </title>


    <link
        rel="icon"
        href="{{ asset('favicon.ico') }}"
    >


    @vite([
        'resources/css/app.css',

        'resources/css/pages/research-output.css',

        'resources/js/app.js',

        'resources/js/research-output.js',

        'resources/js/auth-research.js',
    ])

</head>


@php
    $isAuthPortal =
        request()
            ->routeIs(
                'login',
                'register',
                'google.link.*'
            );

    $isWorkspace =
        request()
            ->routeIs(
                'workspace*'
            );
@endphp


<body
    class="{{
        collect([
            $isWorkspace
                ? 'research-chat-body'
                : null,

            $isAuthPortal
                ? 'research-auth-body'
                : null,
        ])
            ->filter()
            ->implode(' ')
    }}"
>

    <a
        class="skip-link"
        href="#main-content"
    >
        Lewati ke konten utama
    </a>


    @unless($isAuthPortal)

        @include(
            'components.navbar'
        )

    @endunless


    <main id="main-content">

        @yield('content')

    </main>


    @if(
        ! $isWorkspace
        && ! $isAuthPortal
    )

        @include(
            'components.footer'
        )

    @endif


    <div
        class="network-banner"
        data-network-banner
        role="status"
        aria-live="polite"
        hidden
    >
        Anda sedang offline.
        Input yang belum dikirim
        tetap dipertahankan di halaman ini.
    </div>

</body>

</html>