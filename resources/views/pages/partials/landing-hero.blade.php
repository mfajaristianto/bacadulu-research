@php
    $heroHref = auth()->check()
        ? url('/workspace')
        : url('/register');
@endphp

<section
    class="br-ref-hero"
    id="fitur"
>

    {{-- =========================================================
         BACKGROUND SCENE
    ========================================================== --}}
    <div
        class="br-ref-scene"
        aria-hidden="true"
    >

        <div class="br-ref-scene__orange"></div>
        <div class="br-ref-scene__green"></div>

        {{-- blurred plant --}}
        <div class="br-ref-plant">
            <i></i>
            <i></i>
            <i></i>
            <i></i>
        </div>

        {{-- blurred background papers --}}
        <div class="br-ref-bg-paper br-ref-bg-paper--one">
            <span></span>
            <span></span>
            <span></span>
            <span></span>
            <span></span>
        </div>

        <div class="br-ref-bg-paper br-ref-bg-paper--two">

            <strong>
                academic research
            </strong>

            <span></span>
            <span></span>
            <span></span>
            <span></span>

        </div>

        <div class="br-ref-desk"></div>

    </div>


    {{-- =========================================================
         MAIN HERO
    ========================================================== --}}
    <div class="br-shell br-ref-main">

        {{-- =====================================================
             LEFT SIDE
        ====================================================== --}}
        <div class="br-ref-copy">

            <p class="br-ref-eyebrow">
                TEMUKAN PENGETAHUAN YANG TERBUKTI
            </p>


            <h1 class="br-ref-title">

                <span>
                    Satu pencarian,
                </span>

                <span class="br-ref-title__second">

                    <em>
                        banyak temuan
                    </em>

                    <strong>
                        bermakna.
                    </strong>

                </span>

            </h1>


            <p class="br-ref-description">

                Cari topik apa pun, dan biarkan BacaResearch menelusuri
                ribuan sumber akademik untuk menampilkan temuan,
                konsep, dan bukti yang paling relevan bagi Anda.

            </p>


            {{-- =================================================
                 SEARCH
            ================================================== --}}
            <a
                href="{{ $heroHref }}"
                class="br-ref-search"
                aria-label="Mulai penelitian"
            >

                <span class="br-ref-search__icon">

                    <svg viewBox="0 0 24 24" aria-hidden="true">

                        <circle
                            cx="10.5"
                            cy="10.5"
                            r="6.2"
                        ></circle>

                        <path
                            d="M15.1 15.1 20 20"
                        ></path>

                    </svg>

                </span>


                <span class="br-ref-search__text">
                    Apa yang ingin Anda teliti hari ini?
                </span>


                <span class="br-ref-search__arrow">

                    <svg viewBox="0 0 24 24" aria-hidden="true">

                        <path
                            d="M5 12h13"
                        ></path>

                        <path
                            d="m14 7 5 5-5 5"
                        ></path>

                    </svg>

                </span>

            </a>


            {{-- =================================================
                 TOPIC CHIPS
            ================================================== --}}
            <div class="br-ref-topics">

                <a href="{{ $heroHref }}">

                    <svg viewBox="0 0 20 20" aria-hidden="true">

                        <circle
                            cx="8"
                            cy="8"
                            r="4.25"
                        ></circle>

                        <path
                            d="m11.4 11.4 4 4"
                        ></path>

                    </svg>

                    <span>
                        Pendidikan inklusif
                    </span>

                </a>


                <a href="{{ $heroHref }}">

                    <svg viewBox="0 0 20 20" aria-hidden="true">

                        <circle
                            cx="8"
                            cy="8"
                            r="4.25"
                        ></circle>

                        <path
                            d="m11.4 11.4 4 4"
                        ></path>

                    </svg>

                    <span>
                        Ekonomi digital
                    </span>

                </a>


                <a href="{{ $heroHref }}">

                    <svg viewBox="0 0 20 20" aria-hidden="true">

                        <circle
                            cx="8"
                            cy="8"
                            r="4.25"
                        ></circle>

                        <path
                            d="m11.4 11.4 4 4"
                        ></path>

                    </svg>

                    <span>
                        Perubahan iklim
                    </span>

                </a>


                <a href="{{ $heroHref }}">

                    <svg viewBox="0 0 20 20" aria-hidden="true">

                        <circle
                            cx="8"
                            cy="8"
                            r="4.25"
                        ></circle>

                        <path
                            d="m11.4 11.4 4 4"
                        ></path>

                    </svg>

                    <span>
                        Kesehatan masyarakat
                    </span>

                </a>

            </div>

        </div>


        {{-- =====================================================
             RIGHT VISUAL
        ====================================================== --}}
        <div
            class="br-ref-visual"
            aria-hidden="true"
        >

            <div class="br-ref-stage">

                {{-- ambient depth --}}
                <div class="br-ref-stage-glow"></div>


                {{-- =================================================
                     PAPERS
                     POSISI DIPERTAHANKAN RAPI
                ================================================== --}}
                <article class="br-ref-paper br-ref-paper--left">

                    <strong>
                        pendidikan<br>
                        inklusif
                    </strong>

                    <span></span>
                    <span></span>
                    <span></span>
                    <span></span>
                    <span></span>

                </article>


                <article class="br-ref-paper br-ref-paper--main">

                    <strong>
                        metode pembelajaran
                    </strong>

                    <p>
                        Hasil penelitian menunjukkan bahwa
                        pembelajaran berbasis proyek secara signifikan
                        meningkatkan keterlibatan siswa.
                    </p>

                    <span></span>
                    <span></span>
                    <span></span>

                </article>


                <article class="br-ref-paper br-ref-paper--right">

                    <p>

                        Hasil penelitian menunjukkan bahwa

                        <mark>
                            pembelajaran berbasis proyek
                        </mark>

                        secara signifikan meningkatkan

                        <mark class="orange">
                            keterlibatan siswa
                        </mark>

                        dan

                        <mark>
                            hasil belajar
                        </mark>

                        pada konteks pendidikan menengah.

                    </p>

                    <span></span>
                    <span></span>
                    <span></span>

                </article>


                {{-- =================================================
                     MAGNIFIER TRAIL 02 — PALING JAUH
                     Lengkap: lens + connector + handle
                ================================================== --}}
                <div class="br-ref-trail-lens br-ref-trail-lens--far">

                    <svg
                        class="br-ref-trail-lens__svg"
                        viewBox="0 0 560 560"
                        xmlns="http://www.w3.org/2000/svg"
                    >

                        {{-- dark handle --}}
                        <path
                            class="br-ref-trail-lens__handle"
                            d="M334 334 L478 478"
                        ></path>

                        {{-- orange connector --}}
                        <path
                            class="br-ref-trail-lens__connector"
                            d="M293 293 L355 355"
                        ></path>

                        {{-- glass --}}
                        <circle
                            class="br-ref-trail-lens__glass"
                            cx="190"
                            cy="190"
                            r="151"
                        ></circle>

                        {{-- bronze outer ring --}}
                        <circle
                            class="br-ref-trail-lens__ring"
                            cx="190"
                            cy="190"
                            r="160"
                        ></circle>

                        {{-- inner line --}}
                        <circle
                            class="br-ref-trail-lens__inner"
                            cx="190"
                            cy="190"
                            r="144"
                        ></circle>

                    </svg>

                </div>


                {{-- =================================================
                     MAGNIFIER TRAIL 01 — DEKAT
                     Lebih jelas dari far trail.
                ================================================== --}}
                <div class="br-ref-trail-lens br-ref-trail-lens--near">

                    <svg
                        class="br-ref-trail-lens__svg"
                        viewBox="0 0 560 560"
                        xmlns="http://www.w3.org/2000/svg"
                    >

                        {{-- dark handle --}}
                        <path
                            class="br-ref-trail-lens__handle"
                            d="M334 334 L478 478"
                        ></path>

                        {{-- subtle handle shine --}}
                        <path
                            class="br-ref-trail-lens__handle-light"
                            d="M350 344 L474 468"
                        ></path>

                        {{-- orange connector --}}
                        <path
                            class="br-ref-trail-lens__connector"
                            d="M293 293 L355 355"
                        ></path>

                        {{-- glass --}}
                        <circle
                            class="br-ref-trail-lens__glass"
                            cx="190"
                            cy="190"
                            r="151"
                        ></circle>

                        {{-- bronze ring --}}
                        <circle
                            class="br-ref-trail-lens__ring"
                            cx="190"
                            cy="190"
                            r="160"
                        ></circle>

                        {{-- inner ring --}}
                        <circle
                            class="br-ref-trail-lens__inner"
                            cx="190"
                            cy="190"
                            r="144"
                        ></circle>

                    </svg>

                </div>


                {{-- =================================================
                     LABELS
                ================================================== --}}
                <div class="br-ref-label br-ref-label--student">

                    <b>
                        +
                    </b>

                    <span>
                        keterlibatan siswa
                    </span>

                </div>


                <div class="br-ref-label br-ref-label--result">

                    <b>
                        +
                    </b>

                    <span>
                        hasil belajar
                    </span>

                </div>


                <div class="br-ref-label br-ref-label--project">

                    <b>
                        +
                    </b>

                    <span>
                        pembelajaran<br>
                        berbasis proyek
                    </span>

                </div>


                {{-- =================================================
                     DOTTED ROUTES
                ================================================== --}}
                <svg
                    class="br-ref-routes"
                    viewBox="0 0 720 470"
                    preserveAspectRatio="none"
                >

                    <path
                        d="
                            M358 211
                            C320 166,
                            282 129,
                            238 99
                        "
                    ></path>

                    <path
                        d="
                            M493 207
                            C556 181,
                            598 140,
                            611 98
                        "
                    ></path>

                    <path
                        d="
                            M531 267
                            C589 278,
                            632 297,
                            670 321
                        "
                    ></path>

                </svg>


                {{-- =================================================
                     MAIN MAGNIFIER
                     ORIGINAL / SOLID
                ================================================== --}}
                <div class="br-ref-lens">

                    <svg
                        class="br-ref-lens__svg"
                        viewBox="0 0 560 560"
                        xmlns="http://www.w3.org/2000/svg"
                    >

                        <defs>

                            <filter
                                id="brMainLensShadow"
                                x="-30%"
                                y="-30%"
                                width="180%"
                                height="180%"
                            >

                                <feDropShadow
                                    dx="0"
                                    dy="15"
                                    stdDeviation="12"
                                    flood-color="#3a2116"
                                    flood-opacity=".20"
                                />

                            </filter>


                            <radialGradient
                                id="brMainLensGlass"
                                cx="34%"
                                cy="28%"
                                r="78%"
                            >

                                <stop
                                    offset="0%"
                                    stop-color="#fffefb"
                                    stop-opacity=".92"
                                />

                                <stop
                                    offset="58%"
                                    stop-color="#fff7eb"
                                    stop-opacity=".74"
                                />

                                <stop
                                    offset="100%"
                                    stop-color="#f8ead8"
                                    stop-opacity=".68"
                                />

                            </radialGradient>


                            <linearGradient
                                id="brMainLensRing"
                                x1="70"
                                y1="60"
                                x2="330"
                                y2="340"
                                gradientUnits="userSpaceOnUse"
                            >

                                <stop
                                    offset="0%"
                                    stop-color="#dc9150"
                                />

                                <stop
                                    offset="22%"
                                    stop-color="#bd6628"
                                />

                                <stop
                                    offset="58%"
                                    stop-color="#934619"
                                />

                                <stop
                                    offset="82%"
                                    stop-color="#b95d20"
                                />

                                <stop
                                    offset="100%"
                                    stop-color="#d78a46"
                                />

                            </linearGradient>


                            <linearGradient
                                id="brMainLensConnector"
                                x1="285"
                                y1="285"
                                x2="370"
                                y2="370"
                                gradientUnits="userSpaceOnUse"
                            >

                                <stop
                                    offset="0%"
                                    stop-color="#dd8a43"
                                />

                                <stop
                                    offset="45%"
                                    stop-color="#b75e27"
                                />

                                <stop
                                    offset="100%"
                                    stop-color="#82401e"
                                />

                            </linearGradient>


                            <linearGradient
                                id="brMainLensHandle"
                                x1="345"
                                y1="350"
                                x2="500"
                                y2="500"
                                gradientUnits="userSpaceOnUse"
                            >

                                <stop
                                    offset="0%"
                                    stop-color="#684432"
                                />

                                <stop
                                    offset="34%"
                                    stop-color="#472c25"
                                />

                                <stop
                                    offset="70%"
                                    stop-color="#2b1c1a"
                                />

                                <stop
                                    offset="100%"
                                    stop-color="#140e10"
                                />

                            </linearGradient>

                        </defs>


                        <g filter="url(#brMainLensShadow)">

                            {{-- =================================================
                                 HANDLE
                            ================================================== --}}
                            <path
                                d="M330 330 L480 480"
                                fill="none"
                                stroke="url(#brMainLensHandle)"
                                stroke-width="58"
                                stroke-linecap="round"
                            ></path>


                            {{-- subtle handle light --}}
                            <path
                                d="M348 341 L474 467"
                                fill="none"
                                stroke="rgba(255,255,255,.075)"
                                stroke-width="9"
                                stroke-linecap="round"
                            ></path>


                            {{-- =================================================
                                 CONNECTOR
                            ================================================== --}}
                            <path
                                d="M290 290 L358 358"
                                fill="none"
                                stroke="url(#brMainLensConnector)"
                                stroke-width="47"
                                stroke-linecap="round"
                            ></path>


                            {{-- connector highlight --}}
                            <path
                                d="M301 297 L352 348"
                                fill="none"
                                stroke="rgba(255,214,166,.23)"
                                stroke-width="8"
                                stroke-linecap="round"
                            ></path>


                            {{-- =================================================
                                 GLASS
                            ================================================== --}}
                            <circle
                                cx="190"
                                cy="190"
                                r="151"
                                fill="url(#brMainLensGlass)"
                            ></circle>


                            {{-- bronze outer ring --}}
                            <circle
                                cx="190"
                                cy="190"
                                r="161"
                                fill="none"
                                stroke="url(#brMainLensRing)"
                                stroke-width="20"
                            ></circle>


                            {{-- outside shadow edge --}}
                            <circle
                                cx="190"
                                cy="190"
                                r="172"
                                fill="none"
                                stroke="rgba(102,48,19,.15)"
                                stroke-width="3"
                            ></circle>


                            {{-- inner bronze edge --}}
                            <circle
                                cx="190"
                                cy="190"
                                r="147"
                                fill="none"
                                stroke="rgba(111,63,35,.42)"
                                stroke-width="3"
                            ></circle>


                            {{-- inner light --}}
                            <circle
                                cx="190"
                                cy="190"
                                r="141"
                                fill="none"
                                stroke="rgba(255,255,255,.72)"
                                stroke-width="3"
                            ></circle>


                            {{-- glass reflection --}}
                            <ellipse
                                cx="127"
                                cy="101"
                                rx="74"
                                ry="31"
                                transform="rotate(-31 127 101)"
                                fill="rgba(255,255,255,.18)"
                            ></ellipse>

                        </g>

                    </svg>


                    {{-- magnified text --}}
                    <div class="br-ref-lens__copy">

                        <small>
                            menunjukkan bahwa
                        </small>

                        <strong>
                            pembelajaran
                        </strong>

                        <strong>
                            berbasis proyek
                        </strong>

                        <span>
                            secara signifikan
                        </span>

                        <span>
                            meningkatkan
                        </span>

                        <em>
                            keterlibatan siswa
                        </em>

                    </div>

                </div>


                {{-- =================================================
                     LOWER MINI CARDS
                ================================================== --}}
                <div class="br-ref-mini br-ref-mini--chart">

                    <svg viewBox="0 0 48 48">

                        <path d="M10 39V27"></path>
                        <path d="M20 39V17"></path>
                        <path d="M30 39V24"></path>
                        <path d="M40 39V10"></path>

                    </svg>

                    <span>
                        motivasi<br>
                        intrinsik
                    </span>

                </div>


                <div class="br-ref-mini br-ref-mini--book">

                    <svg viewBox="0 0 48 48">

                        <path
                            d="
                                M6 12
                                c8-3 13-1 18 3
                                v26
                                c-6-4-12-5-18-2z
                            "
                        ></path>

                        <path
                            d="
                                M42 12
                                c-8-3-13-1-18 3
                                v26
                                c6-4 12-5 18-2z
                            "
                        ></path>

                    </svg>

                    <span>
                        keterampilan<br>
                        abad 21
                    </span>

                </div>

            </div>

        </div>

    </div>


    {{-- =========================================================
         BENEFIT STRIP
    ========================================================== --}}
    <div class="br-shell br-ref-benefits">

        <article>

            <div class="br-ref-benefit-icon">

                <svg viewBox="0 0 48 48">

                    <path d="M10 7h21l7 7v27H10z"></path>
                    <path d="M31 7v9h8"></path>

                    <circle
                        cx="20"
                        cy="25"
                        r="6"
                    ></circle>

                    <path
                        d="m24.5 29.5 6 6"
                    ></path>

                </svg>

            </div>

            <div>

                <h3>
                    Pencarian berbasis evidence
                </h3>

                <p>
                    Menelusuri publikasi akademik terpercaya dari berbagai
                    sumber untuk hasil yang relevan dan dapat dipertanggungjawabkan.
                </p>

            </div>

        </article>


        <article>

            <div class="br-ref-benefit-icon">

                <svg viewBox="0 0 48 48">

                    <circle
                        cx="24"
                        cy="10"
                        r="5"
                    ></circle>

                    <circle
                        cx="10"
                        cy="35"
                        r="5"
                    ></circle>

                    <circle
                        cx="38"
                        cy="35"
                        r="5"
                    ></circle>

                    <path d="M22 15 13 30"></path>
                    <path d="M26 15 35 30"></path>
                    <path d="M15 35h18"></path>

                </svg>

            </div>

            <div>

                <h3>
                    Temukan konsep terkait
                </h3>

                <p>
                    Lihat istilah, teori, dan topik yang berhubungan dengan
                    pencarian Anda untuk memperluas wawasan.
                </p>

            </div>

        </article>


        <article>

            <div class="br-ref-benefit-icon">

                <svg viewBox="0 0 48 48">

                    <path d="M9 39h31"></path>
                    <path d="M14 34V22"></path>
                    <path d="M23 34V11"></path>
                    <path d="M32 34V18"></path>
                    <path d="M40 34V7"></path>

                </svg>

            </div>

            <div>

                <h3>
                    Fokus pada yang penting
                </h3>

                <p>
                    Dapatkan ringkasan temuan kunci dan kutipan relevan,
                    sehingga Anda bisa langsung menuju pada insight.
                </p>

            </div>

        </article>

    </div>

</section>