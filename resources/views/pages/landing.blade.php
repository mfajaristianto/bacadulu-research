@extends('layouts.app')

@section('title', 'BacaDulu Research — Research with evidence, not guesswork.')
@section('meta_description', 'BacaDulu Research membantu pengguna menelusuri sumber akademik, memahami evidence, membangun insight, dan menghasilkan dokumen penelitian.')
@section('body_class', 'landing-reference-page')

@section('content')
@php
    $researchHref = auth()->check() ? route('workspace') : route('register');
@endphp

@include('pages.partials.landing-hero', ['researchHref' => $researchHref])

{{-- =========================================================
     02 — DEEP RESEARCH / REFERENCE IMAGE 04
========================================================= --}}
<section class="br-deep" id="solusi">
    <div class="br-deep__ambient" aria-hidden="true"></div>
    <div class="br-shell br-deep__grid">
        <div class="br-deep__copy" data-reveal data-reveal-from="left">
            <p class="br-kicker">ALUR RISET BERSAMA BACARESEARCH</p>
            <h2 class="br-display br-deep__title">Riset lebih dalam<br><span>dengan evidence</span> yang kuat.</h2>
            <p class="br-lead">Jelajahi literatur akademik dari berbagai sumber, temukan gagasan penting, bandingkan temuan, dan bangun argumen yang lebih kuat dengan bantuan AI.</p>

            <a href="{{ $researchHref }}" class="br-search br-search--compact">
                <span class="br-search__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="6.5"></circle><path d="M16 16l5 5"></path></svg>
                </span>
                <span class="br-search__placeholder">Apa yang ingin Anda teliti hari ini?</span>
                <span class="br-search__submit" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 12h13"></path><path d="m14 7 5 5-5 5"></path></svg></span>
            </a>
            <div class="br-topic-row br-topic-row--tight">
                <a href="{{ $researchHref }}"><span>⌕</span>Pendidikan inklusif</a>
                <a href="{{ $researchHref }}"><span>⌕</span>Ekonomi digital</a>
                <a href="{{ $researchHref }}"><span>⌕</span>Perubahan iklim</a>
                <a href="{{ $researchHref }}"><span>⌕</span>Kesehatan masyarakat</a>
            </div>
        </div>

        <div class="br-evidence-panel" data-reveal data-reveal-from="right" data-reveal-delay="80">
            <p class="br-evidence-panel__eyebrow">DARI SUMBER KE INSIGHT</p>
            <h3>Beragam sumber.<br>Satu alur riset yang rapi.</h3>
            <p>Sumber dikumpulkan, diperiksa, dan dianalisis menjadi evidence yang siap digunakan dalam penelitian Anda.</p>

            <div class="br-evidence-panel__flow">
                <div class="br-source-stack">
                    <div><svg viewBox="0 0 24 24"><path d="M6 3h8l4 4v14H6z"></path><path d="M14 3v5h5"></path><path d="M9 12h6M9 16h6"></path></svg><span>JURNAL</span></div>
                    <div><svg viewBox="0 0 24 24"><path d="M3 5c4-2 7-1 9 1v14c-3-2-6-3-9-1z"></path><path d="M21 5c-4-2-7-1-9 1v14c3-2 6-3 9-1z"></path></svg><span>BUKU</span></div>
                    <div><svg viewBox="0 0 24 24"><path d="M6 3h8l4 4v14H6z"></path><path d="M14 3v5h5"></path><path d="M9 12h6M9 16h5"></path></svg><span>PROSIDING</span></div>
                </div>

                <div class="br-flow__join" aria-hidden="true"><i class="br-flow__dot"></i></div>
                <i class="br-flow__link br-flow__link--in" aria-hidden="true"></i>

                <div class="br-analysis-node">
                    <span class="br-analysis-node__halo" aria-hidden="true"></span>
                    <svg viewBox="0 0 60 60"><path d="M16 12h25l6 6v30H16z"></path><path d="M41 12v8h8"></path><path d="M22 27h18M22 33h18M22 39h13"></path></svg>
                    <small>ANALISIS<br>EVIDENCE</small>
                    <i class="br-flow__dot" aria-hidden="true"></i>
                </div>

                <i class="br-flow__link br-flow__link--out" aria-hidden="true"></i>
                <div class="br-insight-node">
                    <svg viewBox="0 0 54 54"><path d="M11 8h24l8 8v30H11z"></path><path d="M35 8v10h10"></path><path d="M17 29h5v10h-5zM25 24h5v15h-5zM33 19h5v20h-5z"></path></svg>
                    <small>INSIGHT SIAP</small>
                    <i class="br-flow__dot" aria-hidden="true"></i>
                </div>
            </div>

            <div class="br-evidence-panel__legend">
                <span><i></i>Sumber masuk</span>
                <span><i></i>Validasi evidence</span>
                <span><i></i>Insight siap</span>
            </div>
        </div>
    </div>

    <div class="br-shell br-focus-strip" data-reveal data-reveal-from="up">
        <div class="br-focus-strip__icon">
            <svg viewBox="0 0 48 48"><path d="M10 7h21l7 7v27H10z"></path><path d="M31 7v9h8"></path><circle cx="20" cy="25" r="6"></circle><path d="m24.5 29.5 6 6"></path></svg>
        </div>
        <div class="br-focus-strip__main">
            <small>FOKUS RISET ANDA</small>
            <h3>Sintesis temuan dan kerangka penelitian</h3>
            <p>Temukan, bandingkan, dan rangkum temuan dari berbagai sumber untuk memperkuat landasan teori dan kerangka penelitian Anda.</p>
        </div>
        <ul>
            <li><span>✓</span>Dukungan berbagai sumber akademik</li>
            <li><span>✓</span>Ringkasan temuan secara tematik</li>
            <li><span>✓</span>Bantu menyusun kerangka penelitian</li>
            <li><span>✓</span>Hemat waktu, fokus pada analisis</li>
        </ul>
        <a href="{{ $researchHref }}" class="br-circle-arrow" aria-label="Mulai research">
            <svg viewBox="0 0 24 24"><path d="M5 12h13"></path><path d="m14 7 5 5-5 5"></path></svg>
        </a>
    </div>
</section>

{{-- =========================================================
     03 — TRUSTED SOURCES / REFERENCE IMAGE 01
========================================================= --}}
<section class="br-trust" id="sumber-daya">
    <div class="br-trust__ambient" aria-hidden="true"></div>
    <div class="br-shell br-trust__grid">
        <div class="br-trust__copy" data-reveal data-reveal-from="left">
            <p class="br-kicker">JAWABAN BERDASARKAN SUMBER TEPERCAYA</p>
            <h2 class="br-display br-trust__title">Dari sumber terpercaya<br>menuju <span>karya akademik</span></h2>
            <p class="br-lead">Setiap jawaban di BacaResearch didukung oleh sumber akademik yang dikurasi, lengkap dengan sitasi, sehingga Anda bisa langsung menelusuri bukti dan mengubahnya menjadi karya ilmiah berkualitas.</p>

            <div class="br-trust__features">
                <article>
                    <div class="br-square-icon"><svg viewBox="0 0 48 48"><path d="M10 7h21l7 7v27H10z"></path><path d="M31 7v9h8"></path><circle cx="20" cy="25" r="6"></circle><path d="m24.5 29.5 6 6"></path></svg></div>
                    <div><h3>Sumber terkurasi</h3><p>Hanya literatur akademik berkualitas dari jurnal, buku, prosiding, dan sumber terpercaya.</p></div>
                </article>
                <article>
                    <div class="br-square-icon"><svg viewBox="0 0 48 48"><path d="M24 6 39 12v11c0 9-6 15-15 19C15 38 9 32 9 23V12z"></path><path d="m17 24 5 5 10-11"></path></svg></div>
                    <div><h3>Jawaban dengan bukti</h3><p>Setiap pernyataan terhubung dengan sitasi sehingga mudah ditelusuri dan diverifikasi.</p></div>
                </article>
                <article>
                    <div class="br-square-icon"><svg viewBox="0 0 48 48"><path d="M10 7h21l7 7v27H10z"></path><path d="M31 7v9h8"></path><path d="M17 20h14M17 26h14"></path><rect x="24" y="29" width="12" height="10" rx="2"></rect><path d="M30 31v6M27 34h6"></path></svg></div>
                    <div><h3>Siap menjadi karya ilmiah</h3><p>Ringkas temuan, kelola sitasi, dan ekspor ke format DOCX atau PDF dalam satu alur kerja.</p></div>
                </article>
            </div>
        </div>

        <div class="br-answer-board" data-reveal data-reveal-from="right" data-reveal-delay="90">
            <div class="br-answer-board__window">
                <div class="br-answer-board__head">
                    <strong><span class="br-answer-board__miniicon">▤</span> Jawaban dari BacaResearch</strong>
                    <span class="br-process-pill">✦ Lihat proses pencarian <b>→</b></span>
                </div>

                <div class="br-answer-board__main">
                    <div class="br-answer-board__answer">
                        <h3>Bagaimana pengaruh literasi digital terhadap prestasi belajar mahasiswa?</h3>
                        <p>
                            Literasi digital berpengaruh positif terhadap prestasi belajar mahasiswa
                            <mark>[01]</mark>. Beberapa studi menunjukkan bahwa mahasiswa dengan literasi digital yang lebih tinggi
                            cenderung memiliki kemampuan mencari, mengevaluasi, dan menggunakan informasi secara lebih efektif
                            <mark>[03]</mark> <mark>[05]</mark>. Selain itu, literasi digital juga berperan dalam meningkatkan kemandirian
                            belajar dan keterlibatan akademik secara signifikan <mark>[02]</mark>.
                        </p>
                        <button type="button" tabindex="-1">☷ &nbsp; Ringkasan temuan utama <span>⌄</span></button>
                    </div>

                    <aside class="br-answer-board__sources">
                        <div class="br-answer-board__sources-head"><strong>▤ &nbsp; Sumber yang digunakan</strong><span>5 sumber</span></div>
                        <article><b>[01]</b><div><strong>Putri, A. &amp; Rahman, F. (2023)</strong><p>Pengaruh literasi digital terhadap prestasi belajar mahasiswa...</p><small>Jurnal Pendidikan Indonesia</small></div><span>→</span></article>
                        <article><b>[02]</b><div><strong>Sari, D. (2022)</strong><p>Literasi digital dan kemandirian belajar pada mahasiswa perguruan tinggi...</p><small>Jurnal Teknologi Pendidikan</small></div><span>→</span></article>
                        <article><b>[03]</b><div><strong>Wibowo, T. (2021)</strong><p>Digital literacy skills and academic performance: A meta-analysis...</p><small>Computers &amp; Education</small></div><span>→</span></article>
                        <a href="{{ $researchHref }}">Lihat semua 5 sumber &nbsp; →</a>
                    </aside>
                </div>
            </div>

            <svg class="br-answer-board__citation-lines" viewBox="0 0 860 580" preserveAspectRatio="none" aria-hidden="true">
                <path d="M470 202 C520 202, 535 115, 585 115"></path>
                <path d="M455 320 C520 320, 535 245, 585 245"></path>
                <path d="M455 420 C520 420, 535 365, 585 365"></path>
            </svg>

            <div class="br-output-flyout">
                <span class="br-output-flyout__label">▤ &nbsp; Ubah jawaban menjadi karya ilmiah</span>
                <div class="br-output-flyout__paper">
                    <small>📚 <b>Baca<span>Research</span></b></small>
                    <h4>Literasi Digital dan<br>Prestasi Belajar Mahasiswa</h4>
                    <p>Ringkasan Temuan Berdasarkan Literatur Terkurasi</p>
                    <i></i><i></i><i></i>
                    <strong>Daftar Pustaka</strong>
                    <em>[01] Putri, A. &amp; Rahman, F. (2023)...</em>
                    <em>[02] Sari, D. (2022)...</em>
                    <em>[03] Wibowo, T. (2021)...</em>
                </div>
                <span class="br-file-chip br-file-chip--docx">W<br><small>DOCX</small></span>
                <span class="br-file-chip br-file-chip--pdf">⌁<br><small>PDF</small></span>
            </div>
        </div>
    </div>
</section>

{{-- =========================================================
     04 — WORKFLOW / REFERENCE IMAGE 03
========================================================= --}}
<section class="br-workflow" id="workflow">
    <div class="br-shell">
        <div class="br-workflow__header" data-reveal data-reveal-from="left">
            <div>
                <p class="br-kicker">CARA KERJA BACARESEARCH</p>
                <h2 class="br-display">Dari pertanyaan<br><span>menjadi insight</span> yang nyata.</h2>
                <p class="br-lead">BacaResearch membantu Anda melalui proses riset yang sistematis, dari mencari sumber hingga menghasilkan dokumen yang siap digunakan.</p>
            </div>
            <aside class="br-hand-note">✦ <em>Evidence mengalir langkah demi langkah<br>menuju insight yang lebih kuat.</em><span>↘</span></aside>
        </div>

        <div class="br-workflow__track" data-reveal data-reveal-from="up">
            <article>
                <span class="br-step-no">01</span>
                <div class="br-step-icon"><svg viewBox="0 0 48 48"><path d="M24 8c-9 0-16 6-16 14 0 5 3 9 8 12l2 6 6-4c9 0 16-6 16-14S33 8 24 8z"></path><path d="M24 16c4 0 6 2 6 5 0 3-2 4-4 5-2 1-2 2-2 4M24 35h.01"></path></svg></div>
                <h3>Ajukan Pertanyaan</h3><p>Mulai dengan topik, pertanyaan, atau masalah riset yang ingin Anda jelajahi.</p>
            </article>
            <article>
                <span class="br-step-no">02</span>
                <div class="br-step-icon"><svg viewBox="0 0 48 48"><path d="M10 7h21l7 7v27H10z"></path><path d="M31 7v9h8"></path><circle cx="20" cy="25" r="6"></circle><path d="m24.5 29.5 6 6"></path></svg></div>
                <h3>Telusuri Sumber</h3><p>BacaResearch menemukan dan mengumpulkan literatur relevan dari berbagai sumber akademik.</p>
            </article>
            <article>
                <span class="br-step-no">03</span>
                <div class="br-step-icon"><svg viewBox="0 0 48 48"><path d="M10 7h21l7 7v27H10z"></path><path d="M31 7v9h8"></path><path d="M17 32V22M24 32V16M31 32V25"></path></svg></div>
                <h3>Analisis Evidence</h3><p>Sumber dikumpulkan, diperiksa, dan dianalisis menjadi evidence yang terpercaya.</p>
            </article>
            <article>
                <span class="br-step-no">04</span>
                <div class="br-step-icon"><svg viewBox="0 0 48 48"><path d="M17 31c-3-2-5-6-5-10 0-7 5-12 12-12s12 5 12 12c0 4-2 8-5 10l-2 7H19z"></path><path d="M19 38h10M20 42h8"></path><path d="M24 4v3M8 20H5M43 20h-3M11 8l3 3M37 8l-3 3"></path></svg></div>
                <h3>Bangun Insight</h3><p>Temuan penting disintesiskan menjadi insight yang relevan dengan tujuan riset Anda.</p>
            </article>
            <article class="is-active">
                <span class="br-step-no">05</span>
                <div class="br-step-icon"><svg viewBox="0 0 48 48"><path d="M10 7h21l7 7v27H10z"></path><path d="M31 7v9h8"></path><path d="M17 20h14M17 26h14M17 32h11"></path></svg></div>
                <h3>Hasilkan Dokumen</h3><p>Dapatkan ringkasan, laporan, atau draft akademik yang siap digunakan.</p>
            </article>
        </div>
    </div>
</section>

{{-- =========================================================
     05 — RESEARCH TOOLS / REFERENCE IMAGE 05
========================================================= --}}
<section class="br-tools" id="tools">
    <div class="br-tools__ambient" aria-hidden="true"></div>
    <div class="br-shell">
        <header class="br-tools__header" data-reveal>
            <p class="br-kicker">RESEARCH TOOLS</p>
            <h2 class="br-display">Pilih alat riset <span>yang sesuai.</span></h2>
            <p>Berbagai alat riset yang dirancang untuk membantu Anda menemukan, memahami, dan mengembangkan ide penelitian dengan lebih cepat dan mendalam.</p>
        </header>

        <div class="br-tool-grid">
            <a href="{{ $researchHref }}" class="br-tool-card" data-reveal data-reveal-from="up">
                <div class="br-tool-card__visual br-tool-card__visual--search">
                    <span class="br-tool-card__paper"></span>
                    <svg viewBox="0 0 72 72"><path d="M16 14h29v38H16z"></path><path d="M23 24h15M23 30h15M23 36h11"></path><circle cx="47" cy="43" r="12"></circle><path d="m56 52 9 9"></path></svg>
                </div>
                <h3>Literature<br>Synthesis</h3><p>Rangkum temuan penting dari banyak sumber secara terstruktur dan tematik.</p><span class="br-tool-card__arrow">→</span>
            </a>

            <a href="{{ $researchHref }}" class="br-tool-card is-featured" data-reveal data-reveal-from="up" data-reveal-delay="60">
                <div class="br-tool-card__visual br-tool-card__visual--gap">
                    <span class="br-tool-card__paper"></span>
                    <svg viewBox="0 0 72 72"><path d="M17 13h31v40H17z"></path><path d="M24 24h16M24 30h16M24 36h12"></path><path d="M52 25c8 0 13 6 13 13 0 4-2 8-6 10l-1 7H47l-2-7c-4-2-6-6-6-10 0-7 6-13 13-13z"></path></svg>
                </div>
                <h3>Research<br>Gap</h3><p>Temukan celah penelitian dari literatur terkini dan bangun arah riset baru.</p><span class="br-tool-card__arrow">→</span><i class="br-tool-card__spark">⌁</i>
            </a>

            <a href="{{ $researchHref }}" class="br-tool-card" data-reveal data-reveal-from="up" data-reveal-delay="120">
                <div class="br-tool-card__visual br-tool-card__visual--map">
                    <span class="br-tool-card__paper"></span>
                    <svg viewBox="0 0 72 72"><circle cx="36" cy="17" r="7"></circle><circle cx="19" cy="48" r="7"></circle><circle cx="54" cy="48" r="7"></circle><path d="M32 23 22 41M40 23l10 18M26 48h21"></path></svg>
                </div>
                <h3>Theoretical<br>Mapping</h3><p>Petakan teori, konsep, dan keterkaitan antar-variabel dalam bidang riset Anda.</p><span class="br-tool-card__arrow">→</span>
            </a>

            <a href="{{ $researchHref }}" class="br-tool-card" data-reveal data-reveal-from="up" data-reveal-delay="180">
                <div class="br-tool-card__visual br-tool-card__visual--compare">
                    <span class="br-tool-card__paper"></span>
                    <svg viewBox="0 0 72 72"><path d="M13 15h24v36H13zM36 24h24v33H36z"></path><path d="M20 26h10M20 32h10M43 35h10M43 41h10"></path><path d="M32 12c7 0 12 3 16 8M50 12l-2 8-8-1"></path><path d="M40 60c-7 0-12-3-16-8M22 60l2-8 8 1"></path></svg>
                </div>
                <h3>Comparative<br>Analysis</h3><p>Bandingkan temuan, metode, atau perspektif dari berbagai sumber secara sistematis.</p><span class="br-tool-card__arrow">→</span>
            </a>

            <a href="{{ $researchHref }}" class="br-tool-card" data-reveal data-reveal-from="up" data-reveal-delay="240">
                <div class="br-tool-card__visual br-tool-card__visual--builder">
                    <span class="br-tool-card__paper"></span>
                    <svg viewBox="0 0 72 72"><path d="M17 13h31v40H17z"></path><path d="M24 24h16M24 30h16M24 36h12"></path><path d="m50 29 11 5v14l-11 6-11-6V34z"></path><path d="M39 34l11 6 11-6M50 40v14"></path></svg>
                </div>
                <h3>Academic<br>Builder</h3><p>Susun kerangka, argumen, dan draft penelitian dengan bantuan AI.</p><span class="br-tool-card__arrow">→</span>
            </a>
        </div>
    </div>
</section>

{{-- =========================================================
     06 — CURRENT DOCUMENT SECTION / KEEP, DON'T REMOVE
========================================================= --}}
<section class="bd-document-story" id="document-output">
    <div class="bd-document-story__inner">
        <div class="bd-document-preview" data-reveal data-reveal-from="left">
            <div class="bd-document-preview__paper">
                <span class="bd-document-preview__eyebrow">RESEARCH ARTICLE</span>
                <h3>ESG DISCLOSURE AND FIRM VALUE</h3>
                <p class="bd-document-preview__meta">Generated from research session · APA 7th</p>
                <div class="bd-document-preview__rule"></div>
                <strong>A. PENDAHULUAN</strong>
                <p>Penelitian mengenai pengungkapan ESG berkembang seiring meningkatnya perhatian terhadap...</p>
                <strong>B. TINJAUAN PUSTAKA</strong>
                <p>Literatur menunjukkan beberapa pola hubungan yang perlu dibaca bersama dengan konteks...</p>
                <div class="bd-document-preview__references">
                    <span>DAFTAR PUSTAKA</span>
                    <i></i><i></i><i></i>
                </div>
            </div>
            <span class="bd-document-preview__format">DOCX</span>
            <span class="bd-document-preview__format bd-document-preview__format--pdf">PDF</span>
        </div>

        <div class="bd-document-story__copy" data-reveal data-reveal-from="right">
            <div class="bd-section-heading__number">04</div>
            <p class="bd-kicker">FROM RESEARCH TO DOCUMENT</p>
            <h2>Insight tidak berhenti di chat</h2>
            <p>
                Research session dapat diteruskan menjadi dokumen akademik dengan template, citation style,
                preview, dan provenance yang tetap terhubung ke evidence asalnya
            </p>
            <ul class="bd-document-story__list">
                <li><span>01</span> Pilih jenis dokumen</li>
                <li><span>02</span> Pilih template dan citation style</li>
                <li><span>03</span> Preview sebelum export</li>
                <li><span>04</span> Export ke DOCX atau PDF</li>
            </ul>
        </div>
    </div>
</section>

<section class="br-final-cta">
    <div class="br-shell br-final-cta__inner" data-reveal data-reveal-from="up">
        <div>
            <p class="br-kicker">MULAI DARI EVIDENCE</p>
            <h2>Ubah pertanyaan menjadi insight dan dokumen yang bisa dipertanggungjawabkan.</h2>
        </div>
        <a href="{{ $researchHref }}" class="br-cta-button">{{ auth()->check() ? 'Buka Research Workspace' : 'Mulai Research' }} <span>→</span></a>
    </div>
</section>
@endsection
