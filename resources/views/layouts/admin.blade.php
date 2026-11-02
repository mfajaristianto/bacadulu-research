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

@php
    $adminUserId = session('research_admin_user_id');
    $admin = $adminUserId ? \App\Models\User::query()->find($adminUserId) : null;
    $adminName = $admin?->name ?: 'Administrator';
    $adminEmail = session('research_admin_email') ?: $admin?->email ?: 'admin';

    $adminInitials = collect(preg_split('/\s+/', trim($adminName), -1, PREG_SPLIT_NO_EMPTY))
        ->take(2)
        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');

    $aiProvider = strtolower((string) config('research.ai_provider', 'mock'));
    $aiLive = ! in_array($aiProvider, ['mock', 'unconfigured', ''], true);

    /*
     * Sidebar IA. Labels are plain Indonesian; route names are unchanged.
     */
    $menu = [
        [
            'label' => null,
            'items' => [
                ['name' => 'admin.dashboard', 'label' => 'Beranda', 'icon' => 'overview', 'match' => ['admin.dashboard']],
            ],
        ],
        [
            'label' => 'Sumber & Pustaka',
            'items' => [
                ['name' => 'admin.library.index', 'label' => 'Pustaka', 'icon' => 'library', 'match' => ['admin.library.*']],
                ['name' => 'admin.source-sync.index', 'label' => 'Impor Sumber', 'icon' => 'sync', 'match' => ['admin.source-sync.*']],
                ['name' => 'admin.categories.index', 'label' => 'Kategori', 'icon' => 'category', 'match' => ['admin.categories.*']],
                ['name' => 'admin.collections.index', 'label' => 'Koleksi', 'icon' => 'collection', 'match' => ['admin.collections.*']],
                ['name' => 'admin.glossary.index', 'label' => 'Glosarium', 'icon' => 'glossary', 'match' => ['admin.glossary.*']],
            ],
        ],
        [
            'label' => 'Alat Riset',
            'items' => [
                ['name' => 'admin.tools.index', 'label' => 'Alat Riset', 'icon' => 'tools', 'match' => ['admin.tools.*']],
                ['name' => 'admin.prompts.index', 'label' => 'Instruksi AI', 'icon' => 'prompt', 'match' => ['admin.prompts.*']],
                ['name' => 'admin.prompt-versions.index', 'label' => 'Versi Instruksi', 'icon' => 'versions', 'match' => ['admin.prompt-versions.*']],
                ['name' => 'admin.document-templates.index', 'label' => 'Template Dokumen', 'icon' => 'template', 'match' => ['admin.document-templates.*']],
            ],
        ],
        [
            'label' => 'Pemantauan',
            'items' => [
                ['name' => 'admin.ai-runs.index', 'label' => 'Riwayat Riset', 'icon' => 'runs', 'match' => ['admin.ai-runs.*']],
                ['name' => 'admin.audit.index', 'label' => 'Log Aktivitas', 'icon' => 'audit', 'match' => ['admin.audit.*']],
            ],
        ],
        [
            'label' => 'Pengguna & Sistem',
            'items' => [
                ['name' => 'admin.users.index', 'label' => 'Pengguna', 'icon' => 'users', 'match' => ['admin.users.*']],
                ['name' => 'admin.security.index', 'label' => 'Keamanan', 'icon' => 'security', 'match' => ['admin.security.*']],
                ['name' => 'admin.settings.index', 'label' => 'Pengaturan', 'icon' => 'settings', 'match' => ['admin.settings.*']],
            ],
        ],
    ];
@endphp

<body class="bd-admin">
    <a href="#admin-content" class="bd-admin__skip">Lewati ke konten</a>

    <div class="bd-admin__mobile-overlay" data-admin-overlay></div>

    <aside id="admin-sidebar" class="bd-admin__sidebar" data-admin-sidebar aria-label="Menu administrator">
        <div class="bd-admin__sidebar-head">
            <a href="{{ route('admin.dashboard') }}" class="bd-admin__brand" aria-label="BacaDulu Research — panel admin">
                <span class="bd-admin__brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 48 48" fill="none">
                        <path d="M5 10c8-3 14-2 19 3v28c-6-5-12-6-19-3V10Z" />
                        <path d="M43 10c-8-3-14-2-19 3v28c6-5 12-6 19-3V10Z" />
                        <path d="M24 13v28" />
                    </svg>
                </span>
                <span class="bd-admin__brand-word">
                    <strong>BacaDulu</strong>
                    <small>Panel Admin</small>
                </span>
            </a>

            <button type="button" class="bd-admin__mobile-close" data-admin-sidebar-close aria-label="Tutup menu admin">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" /></svg>
            </button>
        </div>

        <nav class="bd-admin__nav" aria-label="Navigasi administrator">
            @foreach ($menu as $section)
                <section class="bd-admin__nav-section">
                    @if ($section['label'])
                        <span class="bd-admin__nav-label">{{ $section['label'] }}</span>
                    @endif

                    @foreach ($section['items'] as $item)
                        @php
                            $exists = \Illuminate\Support\Facades\Route::has($item['name']);
                            $active = collect($item['match'])->contains(fn ($pattern) => request()->routeIs($pattern));
                        @endphp

                        @if ($exists)
                            <a
                                href="{{ route($item['name']) }}"
                                class="bd-admin__nav-item {{ $active ? 'is-active' : '' }}"
                                @if ($active) aria-current="page" @endif
                            >
                                <span class="bd-admin__nav-icon">@include('admin.partials.nav-icon', ['icon' => $item['icon']])</span>
                                <span class="bd-admin__nav-copy">{{ $item['label'] }}</span>
                            </a>
                        @endif
                    @endforeach
                </section>
            @endforeach
        </nav>

        <div class="bd-admin__sidebar-foot">
            <div class="bd-admin__admin-card">
                <span class="bd-admin__admin-avatar" aria-hidden="true">{{ $adminInitials ?: 'AD' }}</span>
                <div>
                    <strong>{{ $adminName }}</strong>
                    <small>{{ $adminEmail }}</small>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button type="submit" class="bd-admin__logout">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3" /><path d="M10 17l5-5-5-5" /><path d="M15 12H4" /></svg>
                    Keluar
                </button>
            </form>
        </div>
    </aside>

    <main class="bd-admin__main">
        <header class="bd-admin__topbar">
            <div class="bd-admin__topbar-left">
                <button
                    type="button"
                    class="bd-admin__mobile-menu"
                    data-admin-sidebar-open
                    aria-label="Buka menu admin"
                    aria-expanded="false"
                    aria-controls="admin-sidebar"
                >
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16" /></svg>
                </button>

                <p class="bd-admin__topbar-title">@yield('page_title', 'Beranda')</p>
            </div>

            <div class="bd-admin__topbar-right">
                <a
                    href="{{ route('admin.settings.index') }}"
                    class="bd-admin__system {{ $aiLive ? 'is-live' : 'is-mock' }}"
                    title="Layanan AI yang aktif saat ini"
                >
                    <i aria-hidden="true"></i>
                    Layanan AI: {{ $aiLive ? strtoupper($aiProvider) : 'Mode uji (mock)' }}
                </a>

                <a href="{{ route('home') }}" target="_blank" rel="noopener" class="bd-admin__site-link">
                    Lihat situs <span aria-hidden="true">↗</span>
                </a>
            </div>
        </header>

        <div class="bd-admin__content" id="admin-content">
            @if (session('success'))
                <div class="bd-admin__flash bd-admin__flash--success" role="status" data-admin-toast>
                    <span></span>
                    <p>{{ session('success') }}</p>
                </div>
            @endif

            @if (session('warning'))
                <div class="bd-admin__flash bd-admin__flash--warning" role="status">
                    <span></span>
                    <p>{{ session('warning') }}</p>
                </div>
            @endif

            @if (session('error'))
                <div class="bd-admin__flash bd-admin__flash--error" role="alert">
                    <span></span>
                    <p>{{ session('error') }}</p>
                </div>
            @endif

            @if ($errors->any())
                <div class="bd-admin__flash bd-admin__flash--error" role="alert">
                    <span></span>
                    <p>{{ $errors->first() }}</p>
                </div>
            @endif

            @yield('content')
        </div>
    </main>
</body>
</html>
