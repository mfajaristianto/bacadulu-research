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
        @yield('title', 'BacaDulu Research')
    </title>

    <link
        rel="icon"
        type="image/x-icon"
        href="{{ asset('favicon.ico') }}"
    >

    {{-- Body font is needed on first paint; display serif loads on demand. --}}
    <link
        rel="preload"
        href="{{ asset('fonts/plus-jakarta-sans-latin-wght-normal.woff2') }}"
        as="font"
        type="font/woff2"
        crossorigin
    >

    {{-- =========================================================
         VITE ASSETS
    ========================================================== --}}
    @vite(array_values(array_filter([
        'resources/css/app.css',
        'resources/js/app.js',
        request()->routeIs('home') ? 'resources/js/landing-motion.js' : null,
        request()->routeIs('workspace*') ? 'resources/js/workspace-ui.js' : null,
        request()->routeIs('workspace*', 'saved.*', 'documents.*') ? 'resources/js/research-output.js' : null,
        request()->routeIs('login', 'register', 'password.*', 'google.link.*') ? 'resources/js/auth-research.js' : null,
        request()->routeIs('register.success', 'workspace*') ? 'resources/js/bacadulu-onboarding.js' : null,
    ])))

    {{-- CSS tambahan halaman tertentu --}}
    @stack('styles')
</head>

@php
    /*
    |--------------------------------------------------------------------------
    | AUTH PORTAL
    |--------------------------------------------------------------------------
    */

    $isAuthPortal = request()->routeIs(
        'login',
        'register',
        'register.success',
        'google.link.*',
        'password.*'
    );


    /*
    |--------------------------------------------------------------------------
    | WORKSPACE
    |--------------------------------------------------------------------------
    |
    | Khusus halaman research workspace.
    |
    | Variable ini tetap terpisah karena dipakai untuk:
    | - Prompt Question Map
    | - Research prompt runtime
    | - Workspace-only JavaScript data
    |
    */

    $isWorkspace = request()->routeIs(
        'workspace*'
    );


    /*
    |--------------------------------------------------------------------------
    | RESEARCH SHELL
    |--------------------------------------------------------------------------
    |
    | Semua halaman yang memakai UI Research:
    |
    | - Workspace
    | - Saved
    |
    | Halaman di dalam shell ini TIDAK memakai:
    | - navbar publik
    | - footer publik
    |
    | dan mendapatkan class:
    |
    | research-chat-body
    |
    */

     $isResearchShell = request()->routeIs(
    'workspace*',
    'saved.*',
    'profile.edit',
    'documents.*'
    );


    /*
    |--------------------------------------------------------------------------
    | USER-FACING QUESTION TEMPLATE MAP
    |--------------------------------------------------------------------------
    |
    | Data yang dikirim ke browser hanya:
    |
    | - Prompt ID
    | - Question Template
    |
    | System Prompt, Task Prompt,
    | Retrieval Instructions, dan
    | Output Instructions tetap di server.
    |
    */

    $promptQuestionMap = [];

    if (
        $isWorkspace
        && isset($prompts)
    ) {
        $promptQuestionMap = collect(
            $prompts
        )
            ->mapWithKeys(
                function ($prompt): array {
                    return [
                        (string) $prompt->id =>
                            (string) (
                                $prompt->question_template
                                ?: ''
                            ),
                    ];
                }
            )
            ->all();
    }
@endphp


<body
    class="{{
        collect([
            $isResearchShell
                ? 'research-chat-body'
                : null,

            $isAuthPortal
                ? 'research-auth-body'
                : null,

            trim(
                (string) $__env->yieldContent(
                    'body_class'
                )
            )
                ?: null,
        ])
            ->filter()
            ->implode(' ')
    }}"
>

    {{-- =========================================================
         ACCESSIBILITY
    ========================================================== --}}
    <a
        class="skip-link"
        href="#main-content"
    >
        Lewati ke konten utama
    </a>


    {{-- =========================================================
         PUBLIC NAVBAR
    =========================================================
         Navbar publik hanya tampil jika:
         - bukan auth portal
         - bukan research shell

         Jadi Workspace dan Saved memakai topbar research sendiri.
    ========================================================== --}}
    @if(
        ! $isAuthPortal
        && ! $isResearchShell
    )
        @include('components.navbar')
    @endif


    {{-- =========================================================
         MAIN CONTENT
    ========================================================== --}}
    <main id="main-content">
        @yield('content')
    </main>


    {{-- =========================================================
         PUBLIC FOOTER
    =========================================================
         Footer publik tidak tampil di:
         - Login/Register
         - Workspace
         - Saved
    ========================================================== --}}
    @if(
        ! $isAuthPortal
        && ! $isResearchShell
    )
        @include('components.footer')
    @endif


    @if($isResearchShell)
        <nav class="br-mobile-tabs" aria-label="Navigasi Research mobile">
            <a href="{{ route('workspace') }}" class="{{ request()->routeIs('workspace*') ? 'is-active' : '' }}" @if(request()->routeIs('workspace*')) aria-current="page" @endif><span aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m3 11 9-8 9 8v10h-7v-7h-4v7H3z"/></svg></span>Research</a>
            <a href="{{ route('saved.index') }}" class="{{ request()->routeIs('saved.*') ? 'is-active' : '' }}" @if(request()->routeIs('saved.*')) aria-current="page" @endif><span aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6 3h12v18l-6-4-6 4z"/></svg></span>Saved</a>
            <a href="{{ route('documents.index') }}" class="{{ request()->routeIs('documents.*') ? 'is-active' : '' }}" @if(request()->routeIs('documents.*')) aria-current="page" @endif><span aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6 2h9l4 4v16H6zM15 2v5h5M9 12h7M9 16h7"/></svg></span>Dokumen</a>
            <a href="{{ route('profile.edit') }}" class="{{ request()->routeIs('profile.edit') ? 'is-active' : '' }}" @if(request()->routeIs('profile.edit')) aria-current="page" @endif><span aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-5 3-7 8-7s8 2 8 7"/></svg></span>Profil</a>
        </nav>
    @endif

    {{-- =========================================================
         NETWORK STATUS
    ========================================================== --}}
    <div
        class="network-banner"
        data-network-banner
        role="status"
        aria-live="polite"
        hidden
    >
        Anda sedang offline. Input yang belum dikirim tetap dipertahankan di halaman ini.
    </div>


    {{-- =========================================================
         WORKSPACE PROMPT QUESTION MAP
    =========================================================
         Sengaja tetap memakai $isWorkspace,
         BUKAN $isResearchShell.

         Saved tidak membutuhkan prompt runtime.
    ========================================================== --}}
    @if(
        $isWorkspace
        && ! empty($promptQuestionMap)
    )
        <script
            type="application/json"
            data-prompt-question-map
        >{!! json_encode(
            $promptQuestionMap,
            JSON_HEX_TAG
            | JSON_HEX_AMP
            | JSON_HEX_APOS
            | JSON_HEX_QUOT
        ) !!}</script>
    @endif


    {{-- JavaScript tambahan halaman tertentu --}}
    @stack('scripts')

</body>

</html>