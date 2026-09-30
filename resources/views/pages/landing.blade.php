@extends('layouts.app')

@section('title', 'BacaDulu Research — Research with evidence, not guesswork.')

@section('meta_description', 'BacaDulu Research membantu pengguna bergerak dari pertanyaan penelitian menuju evidence, insight, dan dokumen yang siap digunakan.')

@section('content')
<section class="bd-research-hero">
    <div class="bd-research-hero__inner">
        <div class="bd-research-hero__copy" data-reveal data-reveal-from="left">
            <p class="bd-kicker">AI RESEARCH WORKSPACE</p>

            <h1 class="bd-research-hero__title">
                Mulai dari
                <span class="display-type">evidence</span>
                <small>Tiba pada insight</small>
            </h1>

            <p class="bd-research-hero__lead">
                BacaDulu Research membantu Anda memahami literatur, menemukan pola,
                menguji research gap, dan menyusun output penelitian berdasarkan evidence
                dari sumber yang dikelola secara terkurasi
            </p>

            <div class="bd-research-hero__actions">
                <a
                    href="{{ auth()->check() ? route('workspace') : route('register') }}"
                    class="btn btn-primary"
                    data-motion-button
                >
                    <span>Mulai Research</span>
                    <svg viewBox="0 0 20 20" aria-hidden="true">
                        <path d="M4 10h11M10 5l5 5-5 5"></path>
                    </svg>
                </a>

                <a href="#workflow" class="bd-inline-link">
                    Lihat cara kerja
                    <svg viewBox="0 0 20 20" aria-hidden="true">
                        <path d="M10 4v11M5 10l5 5 5-5"></path>
                    </svg>
                </a>
            </div>

            <div class="bd-research-hero__principles" aria-label="Prinsip BacaDulu Research">
                <span><i></i> Evidence-first</span>
                <span><i></i> Source-aware</span>
                <span><i></i> Research-oriented</span>
            </div>
        </div>

        <div class="bd-research-hero__visual" data-reveal data-reveal-from="right" data-reveal-delay="100">
            <div class="bd-workbench" data-motion-card>
                <div class="bd-workbench__bar">
                    <div class="bd-workbench__brand">
                        <span class="bd-workbench__dot"></span>
                        <strong>Active Research</strong>
                    </div>
                    <span class="bd-workbench__status"><i></i> Evidence ready</span>
                </div>

                <div class="bd-workbench__body">
                    <aside class="bd-workbench__rail" aria-label="Research session preview">
                        <span class="bd-workbench__label">SESSION</span>
                        <strong>ESG &amp; Firm Value</strong>
                        <small>Literature synthesis</small>

                        <div class="bd-workbench__rail-line"></div>

                        <span class="bd-workbench__label">RESEARCH FLOW</span>
                        <ol class="bd-workbench__steps">
                            <li class="is-done"><span>01</span> Retrieve</li>
                            <li class="is-done"><span>02</span> Evidence</li>
                            <li class="is-active"><span>03</span> Compare</li>
                            <li><span>04</span> Insight</li>
                        </ol>
                    </aside>

                    <div class="bd-workbench__canvas">
                        <div class="bd-workbench__prompt">
                            <div>
                                <span class="bd-workbench__label">ACTIVE TOOL</span>
                                <strong>Literature Synthesis</strong>
                            </div>
                            <button type="button" tabindex="-1" aria-hidden="true">Apa ini?</button>
                        </div>

                        <div class="bd-workbench__question">
                            <span>PERTANYAAN</span>
                            <p>Bagaimana hubungan ESG disclosure dan firm value berkembang dalam literatur?</p>
                        </div>

                        <div class="bd-workbench__answer">
                            <div class="bd-workbench__answer-head">
                                <span class="bd-workbench__answer-mark">AI</span>
                                <strong>Research insight</strong>
                            </div>
                            <p>
                                Evidence yang relevan menunjukkan pola temuan yang tidak sepenuhnya konsisten.
                                Perbedaan pengukuran ESG dan konteks perusahaan menjadi bagian penting untuk dibandingkan.
                            </p>

                            <div class="bd-evidence-row" aria-label="Evidence preview">
                                <span>[01] ESG disclosure</span>
                                <span>[03] Firm value</span>
                                <span>[07] Measurement</span>
                            </div>
                        </div>

                        <div class="bd-workbench__next">
                            <span>NEXT RESEARCH ACTION</span>
                            <strong>Identifikasi research gap dari pola temuan ini</strong>
                            <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M4 10h11M10 5l5 5-5 5"></path></svg>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bd-hero-note bd-hero-note--evidence" aria-hidden="true">
                <span>Evidence</span>
                <strong>Claim terhubung ke sumber</strong>
            </div>

            <div class="bd-hero-note bd-hero-note--source" aria-hidden="true">
                <span>Source context</span>
                <strong>Metadata tetap terlihat</strong>
            </div>
        </div>
    </div>
</section>

<section class="bd-research-flow" id="workflow">
    <div class="bd-research-flow__inner">
        <header class="bd-section-heading" data-reveal>
            <div class="bd-section-heading__number">01</div>
            <div>
                <p class="bd-kicker">HOW RESEARCH WORKS</p>
                <h2>Bukan sekadar bertanya kepada AI</h2>
            </div>
            <p>
                Setiap sesi membawa pertanyaan menuju evidence, analisis, dan langkah penelitian berikutnya.
                AI membantu pekerjaan research tanpa menyamarkan asal jawabannya
            </p>
        </header>

        <div class="bd-research-flow__track" data-reveal data-reveal-from="up">
            <article class="bd-flow-step">
                <span class="bd-flow-step__number">01</span>
                <div>
                    <small>QUESTION</small>
                    <h3>Tentukan fokus</h3>
                    <p>Mulai dari pertanyaan, topik, atau masalah penelitian yang ingin Anda pahami</p>
                </div>
            </article>

            <article class="bd-flow-step bd-flow-step--evidence">
                <span class="bd-flow-step__number">02</span>
                <div>
                    <small>RETRIEVAL</small>
                    <h3>Temukan evidence</h3>
                    <p>Sistem mengambil bagian sumber yang relevan, bukan memasukkan seluruh Library ke jawaban</p>
                </div>
            </article>

            <article class="bd-flow-step">
                <span class="bd-flow-step__number">03</span>
                <div>
                    <small>ANALYSIS</small>
                    <h3>Bandingkan temuan</h3>
                    <p>Research Tool membantu melihat pola, perbedaan, teori, variabel, atau research gap</p>
                </div>
            </article>

            <article class="bd-flow-step bd-flow-step--insight">
                <span class="bd-flow-step__number">04</span>
                <div>
                    <small>OUTPUT</small>
                    <h3>Kembangkan insight</h3>
                    <p>Lanjutkan ke follow-up research atau ubah hasilnya menjadi dokumen akademik</p>
                </div>
            </article>
        </div>
    </div>
</section>

<section class="bd-research-tools" id="tools">
    <div class="bd-research-tools__inner">
        <div class="bd-research-tools__intro" data-reveal data-reveal-from="left">
            <div class="bd-section-heading__number">02</div>
            <p class="bd-kicker">RESEARCH TOOLS</p>
            <h2>Pilih cara berpikir yang sesuai dengan pekerjaan research Anda</h2>
            <p>
                Setiap tool memiliki istilah, penjelasan, kapan digunakan, kapan tidak digunakan,
                contoh, dan expected output—supaya Anda tahu apa yang sedang dijalankan
            </p>
            <a href="{{ auth()->check() ? route('workspace') : route('register') }}" class="bd-inline-link bd-inline-link--dark">
                Buka Research Workspace
                <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M4 10h11M10 5l5 5-5 5"></path></svg>
            </a>
        </div>

        <div class="bd-research-tools__list" data-reveal data-reveal-from="right">
            <article class="bd-tool-row is-featured">
                <span>01</span>
                <div>
                    <small>Literature Synthesis · Sintesis Literatur</small>
                    <h3>Satukan temuan dari beberapa sumber</h3>
                    <p>Melihat pola, persamaan, perbedaan, dan perkembangan dalam literatur</p>
                </div>
                <b>↗</b>
            </article>

            <article class="bd-tool-row">
                <span>02</span>
                <div>
                    <small>Research Gap · Kesenjangan Penelitian</small>
                    <h3>Temukan ruang penelitian yang masih terbuka</h3>
                    <p>Mengidentifikasi keterbatasan, konflik hasil, dan peluang penelitian</p>
                </div>
                <b>↗</b>
            </article>

            <article class="bd-tool-row">
                <span>03</span>
                <div>
                    <small>Theoretical Mapping · Pemetaan Teori</small>
                    <h3>Lihat teori dan hubungan konseptualnya</h3>
                    <p>Membantu memetakan teori yang digunakan dalam sumber dan hubungannya dengan topik</p>
                </div>
                <b>↗</b>
            </article>

            <article class="bd-tool-row">
                <span>04</span>
                <div>
                    <small>Conceptual Framework · Kerangka Konseptual</small>
                    <h3>Bangun hubungan antarvariabel dengan dasar evidence</h3>
                    <p>Menyusun arah hubungan konseptual tanpa menjadikan output AI sebagai evidence akademik</p>
                </div>
                <b>↗</b>
            </article>
        </div>
    </div>
</section>

<section class="bd-evidence-story">
    <div class="bd-evidence-story__inner">
        <div class="bd-evidence-story__copy" data-reveal data-reveal-from="left">
            <div class="bd-section-heading__number">03</div>
            <p class="bd-kicker">EVIDENCE BEFORE CONFIDENCE</p>
            <h2>Jawaban yang kuat harus menunjukkan dari mana ia berasal</h2>
            <p>
                User tidak perlu membuka repository internal mentah. BacaDulu menyajikan evidence yang relevan,
                metadata sumber, dan citation context tepat di dalam alur research
            </p>

            <div class="bd-evidence-story__rules">
                <div><span>01</span><p><strong>Answer</strong><small>Jawaban menyampaikan hasil analisis secara jelas</small></p></div>
                <div><span>02</span><p><strong>Evidence</strong><small>Claim penting memiliki evidence yang dapat dilihat</small></p></div>
                <div><span>03</span><p><strong>Source metadata</strong><small>Judul, author, tahun, identifier, dan konteks tersedia saat dibutuhkan</small></p></div>
            </div>
        </div>

        <div class="bd-evidence-board" data-reveal data-reveal-from="right" data-reveal-delay="100">
            <div class="bd-evidence-board__answer">
                <span>ANSWER</span>
                <p>
                    Hasil penelitian mengenai ESG dan firm value menunjukkan variasi yang dapat dipengaruhi oleh konteks,
                    pengukuran, serta karakteristik sampel
                </p>
            </div>

            <div class="bd-evidence-board__evidence">
                <div class="bd-evidence-board__top">
                    <span>EVIDENCE [03]</span>
                    <b>Relevant excerpt</b>
                </div>
                <blockquote>
                    Perbedaan operationalization dan karakteristik perusahaan dapat menghasilkan pola temuan yang berbeda
                </blockquote>
            </div>

            <div class="bd-evidence-board__source">
                <span class="bd-source-icon">S</span>
                <div>
                    <strong>ESG Disclosure and Firm Value</strong>
                    <small>Curated Source · Author · 2024</small>
                </div>
                <span class="bd-source-arrow">→</span>
            </div>
        </div>
    </div>
</section>

<section class="bd-document-story">
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

<section class="bd-methodology">
    <div class="bd-methodology__inner" data-reveal>
        <div>
            <p class="bd-kicker">SOURCE METHODOLOGY</p>
            <h2>Curated source bukan berarti klaim otomatis benar</h2>
        </div>
        <p>
            BacaDulu Research memperlakukan source sebagai bahan evidence yang perlu dibaca dalam konteks.
            Jika evidence pada Library belum cukup, sistem harus menyatakan keterbatasannya daripada membuat citation fiktif
        </p>
    </div>
</section>

<section class="bd-research-cta">
    <div class="bd-research-cta__inner" data-reveal data-reveal-from="up">
        <p class="bd-kicker">START YOUR RESEARCH</p>
        <h2>Bergerak dari pertanyaan menuju evidence, insight, dan dokumen</h2>
        <a
            href="{{ auth()->check() ? route('workspace') : route('register') }}"
            class="btn btn-primary"
            data-motion-button
        >
            <span>{{ auth()->check() ? 'Buka Research Workspace' : 'Mulai Research' }}</span>
            <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M4 10h11M10 5l5 5-5 5"></path></svg>
        </a>
    </div>
</section>
@endsection
