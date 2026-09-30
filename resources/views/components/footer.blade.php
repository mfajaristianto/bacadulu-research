@php
    /*
    |--------------------------------------------------------------------------
    | Contact
    |--------------------------------------------------------------------------
    */

    $contactEmail = (string) config(
        'bacadulu.contact_email',
        'admnbacadulu.net@gmail.com'
    );

    $rawCallCenter = (string) config(
        'bacadulu.call_center',
        '+62 851-7957-7876'
    );

    /*
     * Contoh:
     *
     * +62 851-7957-7876
     *
     * menjadi:
     *
     * 6285179577876
     */
    $whatsappNumber = preg_replace(
        '/\D+/',
        '',
        $rawCallCenter
    );

    /*
     * Kalau suatu saat env ditulis:
     *
     * 085179577876
     *
     * otomatis menjadi:
     *
     * 6285179577876
     */
    if (
        str_starts_with(
            $whatsappNumber,
            '0'
        )
    ) {
        $whatsappNumber =
            '62'
            . substr(
                $whatsappNumber,
                1
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Company Logo
    |--------------------------------------------------------------------------
    */

    $companyLogoExists = file_exists(
        public_path(
            'img/Bina.jpg'
        )
    );
@endphp


<footer
    id="main-footer"
    class="bdr-footer"
>

    <div
        class="bdr-footer__decoration"
        aria-hidden="true"
    ></div>


    <div class="bdr-footer__container">

        <div class="bdr-footer__grid">

            {{-- =========================================================
                 BRAND
            ========================================================== --}}

            <section class="bdr-footer__column">

                <h2 class="bdr-footer__title">
                    Baca Dulu,

                    <span>
                        Pahami Kemudian.
                    </span>
                </h2>


                <p class="bdr-footer__description">
                    Platform edukasi dan pelatihan berbasis informasi
                    yang berkualitas untuk mendukung pembelajaran
                    dan research berkelanjutan.
                </p>


                <div class="bdr-footer__company">

                    @if($companyLogoExists)

                        <img
                            src="{{ asset('img/Bina.jpg') }}"
                            alt="PT. Bina Cendikia Academy"
                            loading="lazy"
                        >

                    @else

                        <div class="bdr-footer__company-fallback">

                            <strong>
                                PT. Bina Cendikia Academy
                            </strong>

                            <span>
                                BacaDulu
                            </span>

                        </div>

                    @endif

                </div>

            </section>


            {{-- =========================================================
                 LOCATION
            ========================================================== --}}

            <section class="bdr-footer__column">

                <h3 class="bdr-footer__heading">
                    Lokasi Kantor
                </h3>


                <div class="bdr-footer__location-title">

                    <span
                        class="bdr-footer__location-dot"
                        aria-hidden="true"
                    ></span>

                    <strong>
                        The Manhattan Square
                    </strong>

                </div>


                <p class="bdr-footer__address">
                    Jl. TB Simatupang, Lt. 12,
                    RT.3/RW.3, Cilandak Timur,
                    Pasar Minggu, Kota Jakarta Selatan,
                    Daerah Khusus Ibukota Jakarta.
                </p>


                <div class="bdr-footer__map">

                    <iframe
                        src="https://www.google.com/maps?q=The%20Manhattan%20Square%2C%20Jl.%20TB%20Simatupang%2C%20Jakarta%20Selatan&amp;z=16&amp;output=embed"
                        title="Lokasi kantor BacaDulu di The Manhattan Square"
                        loading="lazy"
                        allowfullscreen
                        referrerpolicy="no-referrer-when-downgrade"
                    ></iframe>

                </div>


                <a
                    class="bdr-footer__map-link"
                    href="https://www.google.com/maps/search/?api=1&query=The%20Manhattan%20Square%20Jl.%20TB%20Simatupang%20Jakarta%20Selatan"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    Buka di Google Maps

                    <span aria-hidden="true">
                        ↗
                    </span>
                </a>

            </section>


            {{-- =========================================================
                 CONTACT
            ========================================================== --}}

            <section class="bdr-footer__column">

                <h3 class="bdr-footer__heading">
                    Hubungi Kami
                </h3>


                <div class="bdr-footer__contacts">

                    {{-- EMAIL --}}
                    <a
                        href="mailto:{{ $contactEmail }}"
                        class="bdr-footer__contact"
                        aria-label="Email BacaDulu"
                    >

                        <span class="bdr-footer__contact-icon">

                            <svg
                                viewBox="0 0 24 24"
                                aria-hidden="true"
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

                        </span>


                        <span class="bdr-footer__contact-text">

                            <small>
                                Email
                            </small>

                            <strong>
                                {{ $contactEmail }}
                            </strong>

                        </span>

                    </a>


                    {{-- WHATSAPP --}}
                    @if($whatsappNumber !== '')

                        <a
                            href="https://wa.me/{{ $whatsappNumber }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="bdr-footer__contact"
                            aria-label="WhatsApp BacaDulu"
                        >

                            <span
                                class="
                                    bdr-footer__contact-icon
                                    bdr-footer__contact-icon--whatsapp
                                "
                            >

                                <svg
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                    fill="currentColor"
                                >
                                    <path
                                        d="M12.04 2C6.52 2 2.03 6.48 2.03 12c0 1.76.46 3.48 1.34 4.99L2 22l5.14-1.35A9.96 9.96 0 0012.03 22h.01c5.52 0 10-4.48 10-10S17.56 2 12.04 2zm0 18.18a8.15 8.15 0 01-4.15-1.14l-.3-.18-3.05.8.81-2.97-.19-.3A8.16 8.16 0 1112.04 20.18zm4.48-6.12c-.25-.12-1.45-.72-1.68-.8-.22-.08-.38-.12-.55.12-.16.25-.63.8-.77.97-.14.16-.28.18-.52.06-.25-.13-1.04-.39-1.98-1.23-.73-.65-1.23-1.46-1.37-1.71-.15-.25-.02-.38.11-.51.11-.11.25-.28.37-.43.12-.14.16-.24.25-.41.08-.16.04-.31-.02-.43-.06-.12-.55-1.32-.75-1.81-.2-.48-.4-.41-.55-.42h-.47c-.16 0-.43.06-.65.31-.22.25-.85.83-.85 2.03 0 1.2.87 2.36.99 2.52.13.16 1.72 2.62 4.17 3.68.58.25 1.04.4 1.39.51.59.18 1.12.16 1.54.1.47-.07 1.45-.59 1.66-1.16.2-.57.2-1.06.14-1.16-.06-.11-.22-.17-.47-.29z"
                                    />
                                </svg>

                            </span>


                            <span class="bdr-footer__contact-text">

                                <small>
                                    WhatsApp
                                </small>

                                <strong>
                                    {{ $rawCallCenter }}
                                </strong>

                            </span>

                        </a>

                    @endif

                </div>


                {{-- SOCIAL --}}
                <div class="bdr-footer__social-area">

                    <h4>
                        Ikuti Kami
                    </h4>


                    <div class="bdr-footer__socials">

                        {{-- YOUTUBE --}}
                        <a
                            href="https://www.youtube.com/@Bacaduluofficial"
                            target="_blank"
                            rel="noopener noreferrer"
                            aria-label="YouTube BacaDulu"
                            title="YouTube"
                        >

                            <svg
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                                fill="currentColor"
                            >
                                <path
                                    d="M23.498 6.163a3.003 3.003 0 00-2.11-2.11C19.517 3.545 12 3.545 12 3.545s-7.516 0-9.387.507a3.003 3.003 0 00-2.11 2.11C0 8.033 0 12 0 12s0 3.967.502 5.837a3.003 3.003 0 002.11 2.11c1.871.507 9.387.507 9.387.507s7.517 0 9.387-.507a3.003 3.003 0 002.11-2.11C24 15.967 24 12 24 12s0-3.967-.502-5.837zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"
                                />
                            </svg>

                        </a>


                        {{-- INSTAGRAM --}}
                        <a
                            href="https://www.instagram.com/bacaduluofficial/"
                            target="_blank"
                            rel="noopener noreferrer"
                            aria-label="Instagram BacaDulu"
                            title="Instagram"
                        >

                            <svg
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >

                                <rect
                                    x="3"
                                    y="3"
                                    width="18"
                                    height="18"
                                    rx="5"
                                />

                                <circle
                                    cx="12"
                                    cy="12"
                                    r="4"
                                />

                                <circle
                                    cx="17.4"
                                    cy="6.7"
                                    r="1"
                                    fill="currentColor"
                                    stroke="none"
                                />

                            </svg>

                        </a>


                        {{-- LINKEDIN --}}
                        <a
                            href="https://www.linkedin.com/in/bacadulu?originalSubdomain=id"
                            target="_blank"
                            rel="noopener noreferrer"
                            aria-label="LinkedIn BacaDulu"
                            title="LinkedIn"
                        >

                            <svg
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                                fill="currentColor"
                            >
                                <path
                                    d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.047c.476-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 110-4.124 2.062 2.062 0 010 4.124zM7.119 20.452H3.555V9h3.564v11.452z"
                                />
                            </svg>

                        </a>

                    </div>

                </div>

            </section>

        </div>


        {{-- =============================================================
             BOTTOM
        ============================================================== --}}

        <div class="bdr-footer__bottom">

            <p>
                Copyright © {{ now()->year }}
                BacaDulu.
                All rights reserved.
            </p>

            <span>
                BacaDulu Research
            </span>

        </div>

    </div>

</footer>


<style>
/* ============================================================
   BACADULU RESEARCH FOOTER
   ============================================================ */

.bdr-footer,
.bdr-footer *,
.bdr-footer *::before,
.bdr-footer *::after {
    box-sizing: border-box;
}

.bdr-footer {
    position: relative;

    width: 100%;

    overflow: hidden;

    padding:
        64px
        0
        20px;

    color: #d4d5dc;

    background:
        linear-gradient(
            135deg,
            #111122 0%,
            #17172b 55%,
            #1c1923 100%
        );
}

.bdr-footer__decoration {
    position: absolute;

    inset: 0;

    pointer-events: none;

    background:
        radial-gradient(
            circle at 100% 0%,
            rgba(242, 107, 79, .13),
            transparent 33%
        ),
        radial-gradient(
            circle at 0% 100%,
            rgba(33, 166, 160, .07),
            transparent 38%
        );
}

.bdr-footer__container {
    position: relative;

    z-index: 2;

    width:
        min(
            calc(100% - 48px),
            1240px
        );

    margin:
        0
        auto;
}

.bdr-footer__grid {
    display: grid;

    grid-template-columns:
        .95fr
        1.12fr
        .93fr;

    align-items: start;

    gap:
        clamp(
            38px,
            4.5vw,
            58px
        );

    padding-bottom:
        46px;
}

.bdr-footer__column {
    min-width: 0;
}


/* ============================================================
   BRAND
   ============================================================ */

.bdr-footer__title {
    margin: 0;

    color: #ffffff;

    font-size:
        clamp(
            27px,
            2.4vw,
            34px
        );

    font-weight: 820;

    letter-spacing: -.045em;

    line-height: 1.07;
}

.bdr-footer__title span {
    display: block;

    margin-top: 3px;

    color: #f28263;
}

.bdr-footer__description {
    max-width: 365px;

    margin:
        16px
        0
        0;

    color: #a7a9b4;

    font-size: 12.5px;

    line-height: 1.72;
}

.bdr-footer__company {
    width:
        min(
            195px,
            100%
        );

    margin-top: 24px;
}

.bdr-footer__company img {
    display: block;

    width: 100%;
    height: auto;

    padding: 3px;

    border:
        1px solid
        rgba(255, 255, 255, .12);

    border-radius: 12px;

    background: #fff;

    box-shadow:
        0
        15px
        34px
        rgba(0, 0, 0, .23);
}

.bdr-footer__company-fallback {
    display: grid;

    gap: 3px;

    padding: 13px 15px;

    border:
        1px solid
        rgba(255, 255, 255, .11);

    border-radius: 10px;

    background:
        rgba(255, 255, 255, .05);
}

.bdr-footer__company-fallback strong {
    color: #fff;

    font-size: 11px;
}

.bdr-footer__company-fallback span {
    color: #8f929e;

    font-size: 9px;

    letter-spacing: .09em;

    text-transform: uppercase;
}


/* ============================================================
   HEADINGS
   ============================================================ */

.bdr-footer__heading {
    margin:
        0
        0
        18px;

    color: #fff;

    font-size: 15px;

    font-weight: 790;
}


/* ============================================================
   LOCATION
   ============================================================ */

.bdr-footer__location-title {
    display: flex;

    align-items: center;

    gap: 9px;

    color: #fff;

    font-size: 12px;
}

.bdr-footer__location-dot {
    width: 7px;
    height: 7px;

    flex:
        0
        0
        7px;

    border-radius: 50%;

    background: #f26b4f;

    box-shadow:
        0
        0
        0
        5px
        rgba(242, 107, 79, .1);
}

.bdr-footer__address {
    max-width: 390px;

    margin:
        8px
        0
        0;

    color: #9ea1ad;

    font-size: 11.5px;

    line-height: 1.65;
}


/* ============================================================
   MAP
   ============================================================ */

.bdr-footer__map {
    width: 100%;
    height: 205px;

    margin-top: 17px;

    overflow: hidden;

    border:
        1px solid
        rgba(255, 255, 255, .1);

    border-radius: 12px;

    background: #0d0d19;

    box-shadow:
        0
        13px
        30px
        rgba(0, 0, 0, .2);
}

.bdr-footer__map iframe {
    display: block;

    width: 100%;
    height: 100%;

    border: 0;
}

.bdr-footer__map-link {
    display: inline-flex;

    align-items: center;

    gap: 5px;

    margin-top: 10px;

    color: #aaaeba !important;

    font-size: 10.5px;

    font-weight: 680;

    text-decoration: none !important;
}

.bdr-footer__map-link:hover {
    color: #f28263 !important;
}


/* ============================================================
   CONTACT
   ============================================================ */

.bdr-footer__contacts {
    display: grid;

    gap: 11px;
}

.bdr-footer__contact {
    display: flex;

    min-width: 0;

    align-items: center;

    gap: 11px;

    color: #d4d5dc !important;

    text-decoration: none !important;
}

.bdr-footer__contact-icon {
    display: flex;

    width: 40px;
    height: 40px;

    flex:
        0
        0
        40px;

    align-items: center;

    justify-content: center;

    color: #f28263;

    border:
        1px solid
        rgba(255, 255, 255, .1);

    border-radius: 50%;

    background:
        rgba(255, 255, 255, .045);

    transition:
        transform .2s ease,
        border-color .2s ease,
        background .2s ease;
}

.bdr-footer__contact-icon svg {
    display: block;

    width: 18px;
    height: 18px;
}

.bdr-footer__contact-icon--whatsapp {
    color: #4ade80;
}

.bdr-footer__contact-text {
    display: grid;

    min-width: 0;

    gap: 2px;
}

.bdr-footer__contact-text small {
    color: #7e818d;

    font-size: 9px;

    font-weight: 700;

    letter-spacing: .08em;

    text-transform: uppercase;
}

.bdr-footer__contact-text strong {
    color: #d7d8df;

    font-size: 11.5px;

    font-weight: 650;

    overflow-wrap: anywhere;
}

.bdr-footer__contact:hover
.bdr-footer__contact-icon {
    transform: translateY(-2px);

    border-color:
        rgba(242, 107, 79, .34);

    background:
        rgba(242, 107, 79, .08);
}


/* ============================================================
   SOCIAL
   ============================================================ */

.bdr-footer__social-area {
    margin-top: 28px;
}

.bdr-footer__social-area h4 {
    margin:
        0
        0
        11px;

    color: #858894;

    font-size: 10px;

    font-weight: 760;

    letter-spacing: .12em;

    text-transform: uppercase;
}

.bdr-footer__socials {
    display: flex;

    align-items: center;

    gap: 9px;
}

.bdr-footer__socials a {
    display: flex;

    width: 40px;
    height: 40px;

    align-items: center;

    justify-content: center;

    color: #d1d3da !important;

    border:
        1px solid
        rgba(255, 255, 255, .1);

    border-radius: 50%;

    background:
        rgba(255, 255, 255, .025);

    text-decoration: none !important;

    transition:
        color .2s ease,
        background .2s ease,
        border-color .2s ease,
        transform .2s ease;
}

.bdr-footer__socials svg {
    display: block;

    width: 18px;
    height: 18px;
}

.bdr-footer__socials a:hover {
    color: #17172b !important;

    background: #f28263;

    border-color: #f28263;

    transform: translateY(-2px);
}


/* ============================================================
   BOTTOM
   ============================================================ */

.bdr-footer__bottom {
    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 20px;

    padding-top: 18px;

    color: #737682;

    border-top:
        1px solid
        rgba(255, 255, 255, .07);

    font-size: 10.5px;
}

.bdr-footer__bottom p {
    margin: 0;
}


/* ============================================================
   TABLET
   ============================================================ */

@media (max-width: 900px) {

    .bdr-footer__grid {
        grid-template-columns:
            repeat(
                2,
                minmax(0, 1fr)
            );

        gap: 40px;
    }

    .bdr-footer__column:first-child {
        grid-column:
            1 / -1;
    }

}


/* ============================================================
   MOBILE
   ============================================================ */

@media (max-width: 600px) {

    .bdr-footer {
        padding:
            50px
            0
            18px;
    }

    .bdr-footer__container {
        width: 100%;

        padding:
            0
            17px;
    }

    .bdr-footer__grid {
        grid-template-columns:
            1fr;

        gap: 36px;

        padding-bottom: 36px;
    }

    .bdr-footer__column:first-child {
        grid-column: auto;
    }

    .bdr-footer__title {
        font-size: 27px;
    }

    .bdr-footer__description {
        max-width: 100%;
    }

    .bdr-footer__company {
        width:
            min(
                190px,
                75%
            );
    }

    .bdr-footer__map {
        height: 210px;
    }

    .bdr-footer__bottom {
        align-items: flex-start;

        flex-direction: column;

        gap: 4px;
    }

}
</style>