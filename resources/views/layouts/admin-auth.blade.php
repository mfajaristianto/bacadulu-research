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
    ])
</head>
<body class="bd-admin-auth">
    <a href="#admin-auth-form" class="bd-admin-skip">
        Lewati ke formulir
    </a>

    <div class="bd-admin-auth__ambient" aria-hidden="true">
        <span class="bd-admin-auth__blob bd-admin-auth__blob--coral"></span>
        <span class="bd-admin-auth__blob bd-admin-auth__blob--teal"></span>
        <span class="bd-admin-auth__blob bd-admin-auth__blob--blue"></span>
    </div>

    <main class="bd-admin-auth__page">
        <section class="bd-admin-auth__shell">

            <aside
                class="bd-admin-auth__context"
                aria-label="Research operations"
            >
                <div
                    class="bd-admin-auth__grid"
                    aria-hidden="true"
                ></div>

                <div
                    class="bd-admin-auth__orbit bd-admin-auth__orbit--one"
                    aria-hidden="true"
                ></div>

                <div
                    class="bd-admin-auth__orbit bd-admin-auth__orbit--two"
                    aria-hidden="true"
                ></div>

                <header
                    class="bd-admin-auth__brand"
                    data-admin-reveal
                    data-reveal-from="left"
                >
                    <a
                        href="{{ route('home') }}"
                        aria-label="BacaDulu Research"
                    >
                        <span>BacaDulu</span>
                        <strong>Research</strong>
                    </a>

                    <span class="bd-admin-auth__badge">
                        ADMIN
                    </span>
                </header>

                <div
                    class="bd-admin-auth__story"
                    data-admin-reveal
                    data-reveal-from="left"
                    data-reveal-delay="80"
                >
                    <p class="bd-admin-auth__eyebrow">
                        RESEARCH OPERATIONS
                    </p>

                    <h1>
                        Kendalikan sumber,
                        <span>bukan sekadar dashboard.</span>
                    </h1>

                    <p class="bd-admin-auth__lede">
                        Kelola source, evidence, prompt version, dan jejak AI
                        dalam satu console yang rapi dan dapat diaudit.
                    </p>
                </div>

                <div
                    class="bd-admin-auth__motion"
                    aria-hidden="true"
                >
                    <div class="bd-admin-auth__motion-head">
                        <span>LIVE PIPELINE</span>
                        <i></i>
                    </div>

                    <div class="bd-admin-auth__motion-row">
                        <div class="bd-admin-auth__motion-step is-active">
                            <small>01</small>
                            <strong>Source</strong>
                            <span>Curate</span>
                        </div>

                        <div class="bd-admin-auth__motion-line">
                            <i></i>
                        </div>

                        <div class="bd-admin-auth__motion-step">
                            <small>02</small>
                            <strong>Evidence</strong>
                            <span>Chunk</span>
                        </div>

                        <div class="bd-admin-auth__motion-line">
                            <i></i>
                        </div>

                        <div class="bd-admin-auth__motion-step">
                            <small>03</small>
                            <strong>Prompt</strong>
                            <span>Control</span>
                        </div>

                        <div class="bd-admin-auth__motion-line">
                            <i></i>
                        </div>

                        <div class="bd-admin-auth__motion-step">
                            <small>04</small>
                            <strong>Run</strong>
                            <span>Observe</span>
                        </div>
                    </div>

                    <div class="bd-admin-auth__motion-card">
                        <div class="bd-admin-auth__motion-card-top">
                            <span>EVIDENCE STREAM</span>
                            <strong>READY</strong>
                        </div>

                        <div class="bd-admin-auth__motion-bars">
                            <i></i>
                            <i></i>
                            <i></i>
                            <i></i>
                        </div>

                        <span class="bd-admin-auth__scan"></span>
                    </div>
                </div>

                <footer class="bd-admin-auth__context-footer">
                    <span>
                        Internal administration
                    </span>

                    <span>
                        Evidence before answers.
                    </span>
                </footer>
            </aside>

            <section
                class="bd-admin-auth__panel"
                id="admin-auth-form"
            >
                <div
                    class="bd-admin-auth__panel-inner"
                    data-admin-reveal
                    data-reveal-from="right"
                    data-reveal-delay="120"
                >
                    @yield('content')
                </div>
            </section>

        </section>

        <div class="bd-admin-auth__outside">
            <span>
                © {{ now()->year }} BacaDulu Research
            </span>

            <a href="{{ route('home') }}">
                Kembali ke situs
                <span aria-hidden="true">↗</span>
            </a>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document
                .querySelectorAll('[data-admin-password-toggle]')
                .forEach((button) => {
                    button.addEventListener('click', (event) => {
                        event.preventDefault();
                        event.stopPropagation();

                        const input = document.getElementById(
                            button.getAttribute('aria-controls')
                        );

                        if (!input) {
                            return;
                        }

                        const show =
                            input.type === 'password';

                        input.type =
                            show
                                ? 'text'
                                : 'password';

                        button.textContent =
                            show
                                ? 'Sembunyikan'
                                : 'Lihat';

                        button.setAttribute(
                            'aria-pressed',
                            show
                                ? 'true'
                                : 'false'
                        );
                    });
                });
        });
    </script>
</body>
</html>