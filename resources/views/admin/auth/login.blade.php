@extends('layouts.admin-auth')

@section('title', 'Admin Login — BacaDulu Research')

@section('content')

<section
    class="admin-gate"
    data-reveal
>

    <div
        class="admin-gate__ambient"
        aria-hidden="true"
    >
        <span class="admin-gate__orb admin-gate__orb--coral"></span>
        <span class="admin-gate__orb admin-gate__orb--cyan"></span>
        <span class="admin-gate__orb admin-gate__orb--blue"></span>
        <span class="admin-gate__orb admin-gate__orb--red"></span>
    </div>


    <div class="admin-gate__shell">

        {{-- =====================================================
             ADMIN VISUAL
        ====================================================== --}}
        <section class="admin-gate__visual">

            <div
                class="admin-gate__grid"
                aria-hidden="true"
            ></div>


            <a
                href="{{ route('home') }}"
                class="admin-gate__brand"
            >
                <span>
                    BacaDulu
                </span>

                <strong>
                    Research
                </strong>

                <small>
                    ADMIN
                </small>
            </a>


            <div class="admin-gate__copy">

                <p class="admin-gate__eyebrow">
                    RESEARCH OPERATIONS
                </p>

                <h1>
                    Kelola sumber, prompt, dan jejak AI dari satu console.
                </h1>

                <p>
                    Area internal untuk menjaga Library,
                    Research Tools, prompt version, dan audit
                    trail tetap terkontrol sebelum digunakan pengguna.
                </p>

            </div>


            <div
                class="admin-gate__console"
                aria-label="Area yang dikelola administrator"
            >

                <div
                    class="admin-gate__console-line admin-gate__console-line--one"
                    aria-hidden="true"
                ></div>

                <div
                    class="admin-gate__console-line admin-gate__console-line--two"
                    aria-hidden="true"
                ></div>


                {{-- LIBRARY --}}
                <article
                    class="admin-gate__module admin-gate__module--library"
                >

                    <span class="is-coral">

                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <path
                                d="M4 5h6l2 2h8v12H4z"
                            />

                            <path
                                d="M8 11h8M8 15h5"
                            />
                        </svg>

                    </span>


                    <div>

                        <small>
                            01
                        </small>

                        <strong>
                            Library
                        </strong>

                        <p>
                            Curated source & indexing
                        </p>

                    </div>

                </article>


                {{-- PROMPTS --}}
                <article
                    class="admin-gate__module admin-gate__module--prompt"
                >

                    <span class="is-cyan">

                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <path
                                d="M5 4h14v16H5z"
                            />

                            <path
                                d="M8 8h8M8 12h5M8 16h7"
                            />
                        </svg>

                    </span>


                    <div>

                        <small>
                            02
                        </small>

                        <strong>
                            Prompt Control
                        </strong>

                        <p>
                            Versioned research instructions
                        </p>

                    </div>

                </article>


                {{-- AI RUNS --}}
                <article
                    class="admin-gate__module admin-gate__module--runs"
                >

                    <span class="is-blue">

                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <path
                                d="M4 18l5-6 4 3 7-9"
                            />

                            <path
                                d="M17 6h3v3"
                            />
                        </svg>

                    </span>


                    <div>

                        <small>
                            03
                        </small>

                        <strong>
                            AI Runs
                        </strong>

                        <p>
                            Evidence, citation & audit
                        </p>

                    </div>

                </article>


                {{-- ACCESS --}}
                <article
                    class="admin-gate__module admin-gate__module--access"
                >

                    <span class="is-red">

                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <path
                                d="M12 3l7 3v5c0 4.5-2.8 8-7 10-4.2-2-7-5.5-7-10V6z"
                            />

                            <path
                                d="M9 12l2 2 4-4"
                            />
                        </svg>

                    </span>


                    <div>

                        <small>
                            04
                        </small>

                        <strong>
                            Access
                        </strong>

                        <p>
                            Private admin boundary
                        </p>

                    </div>

                </article>


                <div
                    class="admin-gate__pulse"
                    aria-hidden="true"
                >

                    <span></span>

                    <b>
                        Controlled Research Layer
                    </b>

                </div>

            </div>


            <div class="admin-gate__security-note">

                <span></span>

                Restricted administration area

            </div>

        </section>


        {{-- =====================================================
             LOGIN
        ====================================================== --}}
        <section class="admin-gate__login">

            <div class="admin-gate__login-inner">

                <a
                    href="{{ route('home') }}"
                    class="admin-gate__back"
                >
                    <span aria-hidden="true">
                        ←
                    </span>

                    Kembali ke BacaDulu Research
                </a>


                <div class="admin-gate__login-head">

                    <p class="admin-gate__eyebrow is-coral">
                        ADMIN ACCESS
                    </p>

                    <h2>
                        Masuk ke Console
                    </h2>

                    <p>
                        Gunakan kredensial administrator
                        yang terdaftar untuk instalasi ini.
                    </p>

                </div>


                @if ($errors->any())

                    <div
                        class="alert alert-error"
                        role="alert"
                    >
                        {{ $errors->first() }}
                    </div>

                @endif


                <form
                    method="POST"
                    action="{{ route('admin.login.store') }}"
                    class="admin-gate__form"
                >

                    @csrf


                    <label for="admin-email">

                        <span>
                            Email admin
                        </span>


                        <div class="admin-gate__input">

                            <i aria-hidden="true">

                                <svg
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <rect
                                        x="3"
                                        y="5"
                                        width="18"
                                        height="14"
                                        rx="2"
                                    />

                                    <path
                                        d="M3 7l9 6 9-6"
                                    />
                                </svg>

                            </i>


                            <input
                                id="admin-email"
                                type="email"
                                name="email"
                                value="{{ old('email') }}"
                                autocomplete="username"
                                placeholder="admin@domain.com"
                                required
                                autofocus
                            >

                        </div>

                    </label>


                    <label for="admin-password">

                        <span>
                            Password
                        </span>


                        <div
                            class="admin-gate__input password-field"
                        >

                            <i aria-hidden="true">

                                <svg
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <rect
                                        x="5"
                                        y="10"
                                        width="14"
                                        height="10"
                                        rx="2"
                                    />

                                    <path
                                        d="M8 10V7a4 4 0 018 0v3"
                                    />
                                </svg>

                            </i>


                            <input
                                id="admin-password"
                                type="password"
                                name="password"
                                autocomplete="current-password"
                                placeholder="Masukkan password"
                                required
                                data-password-input
                            >


                            <button
                                type="button"
                                class="admin-gate__password-toggle password-toggle"
                                aria-label="Tampilkan password"
                                data-password-toggle
                            >
                                Show
                            </button>

                        </div>

                    </label>


                    <button
                        type="submit"
                        class="admin-gate__submit"
                    >

                        <span>
                            Masuk ke Console
                        </span>

                        <span aria-hidden="true">
                            →
                        </span>

                    </button>

                </form>


                <div class="admin-gate__login-foot">

                    <span class="admin-gate__status-dot"></span>

                    Admin session menggunakan area
                    autentikasi terpisah dari user.

                </div>

            </div>

        </section>

    </div>

</section>

@endsection