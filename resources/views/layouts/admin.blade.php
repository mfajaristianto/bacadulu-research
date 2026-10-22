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
        name="robots"
        content="noindex,nofollow,noarchive"
    >

    <title>
        {{ trim($__env->yieldContent('title', 'Admin')) }}
        — BacaDulu Research
    </title>

    @vite([
        'resources/css/admin.css',
        'resources/js/app.js',
    ])
</head>

@php
    $adminUserId =
        session('research_admin_user_id');

    $admin =
        $adminUserId
            ? \App\Models\User::query()
                ->find($adminUserId)
            : null;

    $adminName =
        $admin?->name
        ?: 'Administrator';

    $adminEmail =
        session('research_admin_email')
        ?: $admin?->email
        ?: 'admin';

    $adminInitials =
        collect(
            preg_split(
                '/\s+/',
                trim($adminName),
                -1,
                PREG_SPLIT_NO_EMPTY
            )
        )
            ->take(2)
            ->map(
                fn ($part) =>
                    mb_strtoupper(
                        mb_substr(
                            $part,
                            0,
                            1
                        )
                    )
            )
            ->implode('');

    $menu = [
        [
            'label' => 'OVERVIEW',
            'items' => [
                [
                    'name' => 'admin.dashboard',
                    'label' => 'Overview',
                    'icon' => 'overview',
                    'match' => [
                        'admin.dashboard',
                    ],
                ],
            ],
        ],

        [
            'label' => 'KNOWLEDGE',
            'items' => [
                [
                    'name' => 'admin.library.index',
                    'label' => 'Library',
                    'icon' => 'library',
                    'match' => [
                        'admin.library.*',
                    ],
                ],
                [
                    'name' => 'admin.source-sync.index',
                    'label' => 'Source Sync',
                    'icon' => 'sync',
                    'match' => [
                        'admin.source-sync.*',
                    ],
                ],
                [
                    'name' => 'admin.categories.index',
                    'label' => 'Categories',
                    'icon' => 'category',
                    'match' => [
                        'admin.categories.*',
                    ],
                ],
                [
                    'name' => 'admin.collections.index',
                    'label' => 'Collections',
                    'icon' => 'collection',
                    'match' => [
                        'admin.collections.*',
                    ],
                ],
                [
                    'name' => 'admin.glossary.index',
                    'label' => 'Glossary',
                    'icon' => 'glossary',
                    'match' => [
                        'admin.glossary.*',
                    ],
                ],
            ],
        ],

        [
            'label' => 'AI CONTROL',
            'items' => [
                [
                    'name' => 'admin.tools.index',
                    'label' => 'Research Tools',
                    'icon' => 'tools',
                    'match' => [
                        'admin.tools.*',
                    ],
                ],
                [
                    'name' => 'admin.prompts.index',
                    'label' => 'Prompts',
                    'icon' => 'prompt',
                    'match' => [
                        'admin.prompts.*',
                    ],
                ],
                [
                    'name' => 'admin.prompt-versions.index',
                    'label' => 'Prompt Versions',
                    'icon' => 'prompt',
                    'match' => [
                        'admin.prompt-versions.*',
                    ],
                ],
                [
                    'name' => 'admin.ai-runs.index',
                    'label' => 'AI Runs',
                    'icon' => 'runs',
                    'match' => [
                        'admin.ai-runs.*',
                    ],
                ],
            ],
        ],

        [
            'label' => 'ACCESS & SYSTEM',
            'items' => [
                [
                    'name' => 'admin.users.index',
                    'label' => 'Users',
                    'icon' => 'users',
                    'match' => [
                        'admin.users.*',
                    ],
                ],
                [
                    'name' => 'admin.audit.index',
                    'label' => 'Audit Logs',
                    'icon' => 'audit',
                    'match' => [
                        'admin.audit.*',
                    ],
                ],
                [
                    'name' => 'admin.security.index',
                    'label' => 'Security',
                    'icon' => 'security',
                    'match' => [
                        'admin.security.*',
                    ],
                ],
                [
                    'name' => 'admin.settings.index',
                    'label' => 'Settings',
                    'icon' => 'settings',
                    'match' => [
                        'admin.settings.*',
                    ],
                ],
            ],
        ],
    ];
@endphp

<body class="bd-admin">

    <div
        class="bd-admin__mobile-overlay"
        data-admin-overlay
    ></div>

    <aside
        id="admin-sidebar"
        class="bd-admin__sidebar"
        data-admin-sidebar
    >

        <div class="bd-admin__sidebar-head">

            <a
                href="{{ route('admin.dashboard') }}"
                class="bd-admin__brand"
                aria-label="BacaDulu Research Admin"
            >
                <span>
                    BacaDulu
                </span>

                <strong>
                    Research
                </strong>

                <small>
                    OPS
                </small>
            </a>

            <button
                type="button"
                class="bd-admin__mobile-close"
                data-admin-sidebar-close
                aria-label="Tutup menu admin"
            >
                ×
            </button>

        </div>

        <div class="bd-admin__sidebar-context">

            <span>
                RESEARCH OPERATIONS
            </span>

            <i></i>

        </div>

        <nav
            class="bd-admin__nav"
            aria-label="Navigasi administrator"
        >

            @foreach ($menu as $section)

                <section class="bd-admin__nav-section">

                    <span class="bd-admin__nav-label">
                        {{ $section['label'] }}
                    </span>

                    @foreach ($section['items'] as $item)

                        @php
                            $exists =
                                \Illuminate\Support\Facades\Route::has(
                                    $item['name']
                                );

                            $active =
                                collect(
                                    $item['match']
                                )
                                    ->contains(
                                        fn ($pattern) =>
                                            request()
                                                ->routeIs(
                                                    $pattern
                                                )
                                    );
                        @endphp

                        @if ($exists)

                            <a
                                href="{{ route($item['name']) }}"
                                class="
                                    bd-admin__nav-item
                                    {{ $active ? 'is-active' : '' }}
                                "
                                @if ($active)
                                    aria-current="page"
                                @endif
                            >

                                <span class="bd-admin__nav-icon">

                                    @include(
                                        'admin.partials.nav-icon',
                                        [
                                            'icon' =>
                                                $item['icon'],
                                        ]
                                    )

                                </span>

                                <span class="bd-admin__nav-copy">
                                    {{ $item['label'] }}
                                </span>

                                @if ($active)

                                    <span
                                        class="bd-admin__nav-current"
                                        aria-hidden="true"
                                    ></span>

                                @endif

                            </a>

                        @else

                            <span
                                class="
                                    bd-admin__nav-item
                                    is-disabled
                                "
                                aria-disabled="true"
                            >

                                <span class="bd-admin__nav-icon">

                                    @include(
                                        'admin.partials.nav-icon',
                                        [
                                            'icon' =>
                                                $item['icon'],
                                        ]
                                    )

                                </span>

                                <span class="bd-admin__nav-copy">
                                    {{ $item['label'] }}
                                </span>

                            </span>

                        @endif

                    @endforeach

                </section>

            @endforeach

        </nav>

        <div class="bd-admin__sidebar-foot">

            <div class="bd-admin__admin-card">

                <span class="bd-admin__admin-avatar">
                    {{ $adminInitials ?: 'AD' }}
                </span>

                <div>

                    <strong>
                        {{ $adminName }}
                    </strong>

                    <small>
                        {{ $adminEmail }}
                    </small>

                </div>

            </div>

            <form
                method="POST"
                action="{{ route('admin.logout') }}"
            >

                @csrf

                <button
                    type="submit"
                    class="bd-admin__logout"
                >
                    <span>
                        Keluar
                    </span>

                    <span aria-hidden="true">
                        ↗
                    </span>
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
                    <span></span>
                    <span></span>
                </button>

                <div class="bd-admin__topbar-title">

                    <small>
                        BACADULU / ADMIN
                    </small>

                    <strong>
                        @yield(
                            'page_title',
                            'Research Operations'
                        )
                    </strong>

                </div>

            </div>

            <div class="bd-admin__topbar-right">

                <span class="bd-admin__system">
                    <i></i>
                    System online
                </span>

                <a
                    href="{{ route('home') }}"
                    target="_blank"
                    rel="noopener"
                >
                    Lihat situs

                    <span aria-hidden="true">
                        ↗
                    </span>
                </a>

            </div>

        </header>

        <div class="bd-admin__content">

            @if (session('success'))

                <div
                    class="
                        bd-admin__flash
                        bd-admin__flash--success
                    "
                    role="status"
                    data-admin-toast
                >
                    <span></span>

                    <p>
                        {{ session('success') }}
                    </p>
                </div>

            @endif

            @if (session('warning'))

                <div
                    class="
                        bd-admin__flash
                        bd-admin__flash--warning
                    "
                    role="status"
                >
                    <span></span>

                    <p>
                        {{ session('warning') }}
                    </p>
                </div>

            @endif

            @if (session('error'))

                <div
                    class="
                        bd-admin__flash
                        bd-admin__flash--error
                    "
                    role="alert"
                >
                    <span></span>

                    <p>
                        {{ session('error') }}
                    </p>
                </div>

            @endif

            @if ($errors->any())

                <div
                    class="
                        bd-admin__flash
                        bd-admin__flash--error
                    "
                    role="alert"
                >
                    <span></span>

                    <p>
                        {{ $errors->first() }}
                    </p>
                </div>

            @endif

            @yield('content')

        </div>

    </main>

    <script>
        document.addEventListener(
            'DOMContentLoaded',
            () => {
                const sidebar =
                    document.querySelector(
                        '[data-admin-sidebar]'
                    );

                const overlay =
                    document.querySelector(
                        '[data-admin-overlay]'
                    );

                const openButton =
                    document.querySelector(
                        '[data-admin-sidebar-open]'
                    );

                const closeButton =
                    document.querySelector(
                        '[data-admin-sidebar-close]'
                    );

                const navLinks =
                    document.querySelectorAll(
                        '.bd-admin__nav a'
                    );

                const isMobile = () =>
                    window.matchMedia(
                        '(max-width: 900px)'
                    ).matches;

                const setSidebar = (open) => {
                    const nextOpen =
                        Boolean(
                            open
                            && isMobile()
                        );

                    sidebar
                        ?.classList
                        .toggle(
                            'is-open',
                            nextOpen
                        );

                    overlay
                        ?.classList
                        .toggle(
                            'is-visible',
                            nextOpen
                        );

                    document
                        .body
                        .classList
                        .toggle(
                            'admin-menu-open',
                            nextOpen
                        );

                    openButton
                        ?.setAttribute(
                            'aria-expanded',
                            String(nextOpen)
                        );
                };


                openButton
                    ?.addEventListener(
                        'click',
                        () =>
                            setSidebar(
                                true
                            )
                    );


                closeButton
                    ?.addEventListener(
                        'click',
                        () =>
                            setSidebar(
                                false
                            )
                    );


                overlay
                    ?.addEventListener(
                        'click',
                        () =>
                            setSidebar(
                                false
                            )
                    );


                navLinks
                    .forEach(
                        (link) => {
                            link
                                .addEventListener(
                                    'click',
                                    () => {
                                        if (
                                            isMobile()
                                        ) {
                                            setSidebar(
                                                false
                                            );
                                        }
                                    }
                                );
                        }
                    );


                window
                    .addEventListener(
                        'resize',
                        () => {
                            if (
                                !isMobile()
                            ) {
                                setSidebar(
                                    false
                                );
                            }
                        },
                        {
                            passive: true,
                        }
                    );


                document
                    .addEventListener(
                        'keydown',
                        (event) => {
                            if (
                                event.key
                                ===
                                'Escape'
                            ) {
                                setSidebar(
                                    false
                                );
                            }
                        }
                    );


                document
                    .querySelectorAll(
                        '[data-file-input]'
                    )
                    .forEach(
                        (input) => {
                            input
                                .addEventListener(
                                    'change',
                                    () => {
                                        const target =
                                            document
                                                .querySelector(
                                                    input
                                                        .dataset
                                                        .fileTarget
                                                );

                                        const file =
                                            input
                                                .files
                                                ?.[0];

                                        if (!target) {
                                            return;
                                        }

                                        if (!file) {
                                            target
                                                .textContent =
                                                'Belum ada PDF dipilih';

                                            return;
                                        }

                                        const sizeMb =
                                            (
                                                file.size
                                                /
                                                1024
                                                /
                                                1024
                                            )
                                                .toFixed(
                                                    1
                                                );

                                        target
                                            .textContent =
                                            `${file.name} · ${sizeMb} MB`;
                                    }
                                );
                        }
                    );


                setSidebar(
                    false
                );
            }
        );
    </script>

</body>
</html>