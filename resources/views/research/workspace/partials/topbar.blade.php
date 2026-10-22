<header
    class="brx-topbar"
    data-brx-topbar
>
    <div class="brx-topbar__left">

        {{-- MOBILE SIDEBAR --}}
        <button
            type="button"
            class="research-sidebar__open brx-topbar__mobile"
            data-chat-sidebar-open
            aria-label="Buka riwayat research"
            aria-expanded="false"
        >
            <span></span>
            <span></span>
            <span></span>
        </button>


        {{-- =========================================================
             PERSONAL WORKSPACE
        ========================================================== --}}
        <div class="brx-dropdown">

            <button
                type="button"
                class="brx-workspace-trigger"
                data-brx-workspace-trigger
                aria-expanded="false"
                aria-controls="brx-workspace-menu"
            >
                <span class="brx-workspace-trigger__icon">
                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        aria-hidden="true"
                    >
                        <rect
                            x="5"
                            y="3"
                            width="14"
                            height="18"
                            rx="2"
                        />
                        <path d="M9 7h6" />
                        <path d="M9 11h6" />
                        <path d="M9 15h4" />
                    </svg>
                </span>

                <span class="brx-workspace-trigger__text">
                    Personal Workspace
                </span>

                <span class="brx-workspace-trigger__chevron">
                    <svg
                        viewBox="0 0 20 20"
                        fill="none"
                        aria-hidden="true"
                    >
                        <path d="m6 8 4 4 4-4" />
                    </svg>
                </span>
            </button>


            <div
                id="brx-workspace-menu"
                class="brx-workspace-menu"
                data-brx-workspace-menu
                hidden
            >
                <div class="brx-workspace-menu__header">
                    <span>
                        WORKSPACE
                    </span>

                    <strong>
                        Personal Workspace
                    </strong>

                    <p>
                        Research, history, evidence, dan dokumen Anda
                    </p>
                </div>


                <a
                    href="{{ route('workspace') }}"
                    class="brx-workspace-menu__active"
                >
                    <span class="brx-menu-icon brx-menu-icon--teal">
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            aria-hidden="true"
                        >
                            <rect
                                x="5"
                                y="3"
                                width="14"
                                height="18"
                                rx="2"
                            />
                            <path d="M9 7h6" />
                            <path d="M9 11h6" />
                            <path d="M9 15h4" />
                        </svg>
                    </span>

                    <span class="brx-menu-copy">
                        <strong>
                            Personal Workspace
                        </strong>

                        <small>
                            Workspace research aktif
                        </small>
                    </span>

                    <span class="brx-menu-check">
                        <svg
                            viewBox="0 0 20 20"
                            fill="none"
                            aria-hidden="true"
                        >
                            <path d="m5 10 3 3 7-7" />
                        </svg>
                    </span>
                </a>


                <div class="brx-workspace-menu__divider"></div>


                <nav class="brx-workspace-menu__nav">

                    {{-- NEW RESEARCH --}}
                    <a href="{{ route('workspace') }}">
                        <span class="brx-menu-icon">
                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                aria-hidden="true"
                            >
                                <path d="M12 5v14" />
                                <path d="M5 12h14" />
                            </svg>
                        </span>

                        <span class="brx-menu-copy">
                            <strong>
                                New Research
                            </strong>

                            <small>
                                Mulai workspace research baru
                            </small>
                        </span>

                        <span class="brx-menu-arrow">
                            <svg
                                viewBox="0 0 20 20"
                                fill="none"
                                aria-hidden="true"
                            >
                                <path d="M5 10h10" />
                                <path d="m11 6 4 4-4 4" />
                            </svg>
                        </span>
                    </a>


                    {{-- SAVED --}}
                    <a href="{{ url('/saved') }}">
                        <span class="brx-menu-icon">
                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                aria-hidden="true"
                            >
                                <path d="M6 4h12v17l-6-4-6 4z" />
                            </svg>
                        </span>

                        <span class="brx-menu-copy">
                            <strong>
                                Saved
                            </strong>

                            <small>
                                Hasil research yang Anda simpan
                            </small>
                        </span>

                        <span class="brx-menu-arrow">
                            <svg
                                viewBox="0 0 20 20"
                                fill="none"
                                aria-hidden="true"
                            >
                                <path d="M5 10h10" />
                                <path d="m11 6 4 4-4 4" />
                            </svg>
                        </span>
                    </a>


                    {{-- HISTORY --}}
                    <a href="{{ url('/history') }}">
                        <span class="brx-menu-icon">
                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                aria-hidden="true"
                            >
                                <circle
                                    cx="12"
                                    cy="12"
                                    r="8"
                                />
                                <path d="M12 7v5l3 2" />
                            </svg>
                        </span>

                        <span class="brx-menu-copy">
                            <strong>
                                History
                            </strong>

                            <small>
                                Kelola seluruh riwayat research
                            </small>
                        </span>

                        <span class="brx-menu-arrow">
                            <svg
                                viewBox="0 0 20 20"
                                fill="none"
                                aria-hidden="true"
                            >
                                <path d="M5 10h10" />
                                <path d="m11 6 4 4-4 4" />
                            </svg>
                        </span>
                    </a>


                    {{-- PROFILE --}}
                    <a href="{{ route('profile.edit') }}">
                        <span class="brx-menu-icon">
                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                aria-hidden="true"
                            >
                                <circle
                                    cx="12"
                                    cy="8"
                                    r="3"
                                />
                                <path
                                    d="M6 20c0-4 2.5-6 6-6s6 2 6 6"
                                />
                            </svg>
                        </span>

                        <span class="brx-menu-copy">
                            <strong>
                                Profile
                            </strong>

                            <small>
                                Pengaturan profil dan identitas
                            </small>
                        </span>

                        <span class="brx-menu-arrow">
                            <svg
                                viewBox="0 0 20 20"
                                fill="none"
                                aria-hidden="true"
                            >
                                <path d="M5 10h10" />
                                <path d="m11 6 4 4-4 4" />
                            </svg>
                        </span>
                    </a>

                </nav>
            </div>
        </div>
    </div>


    <div class="brx-topbar__right">
        {{-- =========================================================
             HELP
        ========================================================== --}}
        <div class="brx-dropdown brx-dropdown--right">

            <button
                type="button"
                class="brx-help-trigger"
                data-brx-help-trigger
                aria-label="Cara kerja BacaResearch"
                aria-expanded="false"
                aria-controls="brx-help-menu"
            >
                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    aria-hidden="true"
                >
                    <circle
                        cx="12"
                        cy="12"
                        r="9"
                    />

                    <path
                        d="
                            M9.7 9
                            a2.45 2.45 0 0 1 4.7 1
                            c0 2.1-2.4 2.25-2.4 4
                        "
                    />

                    <path d="M12 17h.01" />
                </svg>
            </button>


            <div
                id="brx-help-menu"
                class="brx-help-menu"
                data-brx-help-menu
                hidden
            >
                <div class="brx-help-menu__header">

                    <div>
                        <span>
                            WORKSPACE HELP
                        </span>

                        <strong>
                            Cara kerja BacaResearch
                        </strong>
                    </div>


                    <button
                        type="button"
                        class="brx-help-menu__close"
                        data-brx-help-close
                        aria-label="Tutup bantuan"
                    >
                        <svg
                            viewBox="0 0 20 20"
                            fill="none"
                            aria-hidden="true"
                        >
                            <path d="m6 6 8 8" />
                            <path d="m14 6-8 8" />
                        </svg>
                    </button>

                </div>


                <div class="brx-help-menu__steps">

                    <article>
                        <span class="brx-help-step__number">
                            01
                        </span>

                        <span class="brx-help-step__icon">
                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                aria-hidden="true"
                            >
                                <rect
                                    x="5"
                                    y="4"
                                    width="14"
                                    height="16"
                                    rx="2"
                                />
                                <path d="M9 8h6" />
                                <path d="M9 12h6" />
                                <path d="M9 16h4" />
                            </svg>
                        </span>

                        <div>
                            <strong>
                                Pilih Research Tool
                            </strong>

                            <p>
                                Tentukan jenis analisis seperti Research Gap,
                                Literature Synthesis, atau Theoretical Mapping
                            </p>
                        </div>
                    </article>


                    <article>
                        <span class="brx-help-step__number">
                            02
                        </span>

                        <span class="brx-help-step__icon brx-help-step__icon--blue">
                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                aria-hidden="true"
                            >
                                <circle
                                    cx="11"
                                    cy="11"
                                    r="6"
                                />
                                <path d="m16 16 4 4" />
                                <path d="M8.5 11h5" />
                            </svg>
                        </span>

                        <div>
                            <strong>
                                Tulis pertanyaan yang fokus
                            </strong>

                            <p>
                                Gunakan topik, objek, konteks, dan hubungan
                                penelitian yang ingin dianalisis
                            </p>
                        </div>
                    </article>


                    <article>
                        <span class="brx-help-step__number">
                            03
                        </span>

                        <span class="brx-help-step__icon brx-help-step__icon--orange">
                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                aria-hidden="true"
                            >
                                <path d="M6 4h10l3 3v13H6z" />
                                <path d="M16 4v4h4" />
                                <path d="m9 14 2 2 4-5" />
                            </svg>
                        </span>

                        <div>
                            <strong>
                                Periksa evidence
                            </strong>

                            <p>
                                Nilai jawaban bersama source dan validated
                                evidence, bukan output AI saja
                            </p>
                        </div>
                    </article>

                </div>


                <div class="brx-help-menu__note">

                    <span>
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            aria-hidden="true"
                        >
                            <circle
                                cx="12"
                                cy="12"
                                r="9"
                            />
                            <path d="M12 11v5" />
                            <path d="M12 8h.01" />
                        </svg>
                    </span>

                    <p>
                        Saved dan Profile tersedia melalui
                        <strong>Personal Workspace</strong>
                    </p>

                </div>
            </div>
        </div>


        {{-- =========================================================
             PROFILE
        ========================================================== --}}
        <a
            href="{{ route('profile.edit') }}"
            class="brx-profile"
        >
            @if($user->avatar_url)

                <img
                    src="{{ $user->avatar_url }}"
                    alt="Foto profil {{ $user->name }}"
                    referrerpolicy="no-referrer"
                >

            @else

                <span class="brx-profile__fallback">
                    {{ $user->initials }}
                </span>

            @endif


            <strong>
                {{ $user->name }}
            </strong>


            <span class="brx-profile__chevron">
                <svg
                    viewBox="0 0 20 20"
                    fill="none"
                    aria-hidden="true"
                >
                    <path d="m6 8 4 4 4-4" />
                </svg>
            </span>
        </a>

    </div>
</header>