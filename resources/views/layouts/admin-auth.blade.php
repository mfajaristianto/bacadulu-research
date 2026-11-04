<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex,nofollow,noarchive">

    <title>{{ trim($__env->yieldContent('title', 'Admin')) }} — BacaDulu Research</title>

    @vite([
        'resources/css/admin.css',
        'resources/js/app.js',
        'resources/js/admin.js',
    ])
</head>
<body class="bd-admin-auth">
    <a href="#admin-auth-form" class="bd-admin-auth__skip">Lewati ke formulir</a>

    <main class="bd-admin-auth__page">
        <a href="{{ route('home') }}" class="bd-admin-auth__back">
            <span aria-hidden="true">←</span> Kembali ke BacaDulu Research
        </a>

        <div class="bd-admin-auth__shell">
            <header class="bd-admin-auth__bar">
                <a href="{{ route('home') }}" class="bd-admin-auth__brand" aria-label="BacaDulu Research — beranda">
                    <span class="bd-admin-auth__mark" aria-hidden="true">
                        <svg viewBox="0 0 48 48" fill="none">
                            <path d="M5 10c8-3 14-2 19 3v28c-6-5-12-6-19-3V10Z" />
                            <path d="M43 10c-8-3-14-2-19 3v28c6-5 12-6 19-3V10Z" />
                            <path d="M24 13v28" />
                        </svg>
                    </span>
                    <span>BacaDulu <em>Research</em></span>
                </a>
                <span class="bd-admin-auth__label">Admin Access</span>
            </header>

            @yield('content')
        </div>
    </main>
</body>
</html>
