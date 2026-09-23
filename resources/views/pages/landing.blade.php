@extends('layouts.app')

@section('title', 'BacaDulu Research — Turn knowledge into research insight.')

@section('content')
<section class="hero-section">
    <div class="hero-copy" data-reveal data-reveal-from="left">
        <p class="eyebrow">AI RESEARCH ASSISTANT</p>
        <h1>Turn knowledge into <em>research insight.</em></h1>
        <p class="hero-lead">
            BacaDulu Research membantu peneliti menemukan, memahami, dan mengembangkan
            ide penelitian melalui AI yang bekerja dengan trusted internal library.
        </p>

        <div class="hero-actions">
            <a href="{{ auth()->check() ? route('workspace') : route('register') }}" class="btn btn-primary" data-motion-button>
                Start Research <span>↗</span>
            </a>
            <a href="#workflow" class="btn btn-secondary" data-motion-button>Explore Platform</a>
        </div>

        <div class="hero-note">
            <span class="dot"></span>
            Evidence-first · Internal sources · Structured prompts
        </div>
    </div>

    <div class="hero-visual" data-reveal data-reveal-from="right" data-reveal-delay="90">
        <div class="research-window" data-motion-card>
            <div class="window-top">
                <div class="window-dots"><i></i><i></i><i></i></div>
                <span>Research Workspace</span>
                <span class="window-status">READY</span>
            </div>
            <div class="window-body">
                <div class="source-column">
                    <div class="mini-label">SELECTED SOURCES</div>
                    <div class="source-item active"><b>Journal Collection</b><span>Curated</span></div>
                    <div class="source-item"><b>Research Data</b><span>Verified</span></div>
                    <div class="source-item"><b>Knowledge Base</b><span>Internal</span></div>
                </div>
                <div class="insight-column">
                    <div class="mini-label">AI INSIGHT</div>
                    <h3>Analyze sustainability research trends</h3>
                    <p>Generate insight from selected documents...</p>
                    <div class="insight-lines"><span></span><span></span><span></span></div>
                    <div class="citation-row"><span>[01]</span><span>[02]</span><span>[04]</span></div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="signal-strip">
    <div data-reveal data-reveal-from="up">Library <span>Journal Collection</span></div>
    <div data-reveal data-reveal-from="up" data-reveal-delay="70">Library <span>Research Data</span></div>
    <div data-reveal data-reveal-from="up" data-reveal-delay="140">Library <span>Knowledge Base</span></div>
    <div data-reveal data-reveal-from="up" data-reveal-delay="210">AI <span>Assistant</span></div>
</section>

<section class="section workflow-section" id="workflow">
    <div class="section-heading" data-reveal data-reveal-from="left">
        <p class="eyebrow">HOW IT WORKS</p>
        <h2>One workspace for your research journey.</h2>
        <p>Mulai dari sumber internal yang terkurasi, pilih research tool, lalu bangun insight secara bertahap.</p>
    </div>

    <div class="workflow-grid">
        <article class="feature-card" data-reveal data-reveal-from="left">
            <span class="feature-number">01</span>
            <h3>Internal Library</h3>
            <p>Gunakan sumber terpercaya yang sudah tersedia dalam library internal BacaDulu.</p>
        </article>
        <article class="feature-card" data-reveal data-reveal-from="down" data-reveal-delay="90">
            <span class="feature-number">02</span>
            <h3>AI Research Tools</h3>
            <p>Gunakan prompt terstruktur yang disiapkan untuk kebutuhan eksplorasi penelitian.</p>
        </article>
        <article class="feature-card" data-reveal data-reveal-from="right" data-reveal-delay="180">
            <span class="feature-number">03</span>
            <h3>Research Workspace</h3>
            <p>Simpan sumber, ide, insight, dan hasil eksplorasi dalam satu ruang kerja.</p>
        </article>
    </div>
</section>

<section class="section tools-section" id="tools">
    <div class="section-heading split-heading" data-reveal data-reveal-from="up">
        <div>
            <p class="eyebrow">RESEARCH TOOLS</p>
            <h2>Built for researchers.</h2>
        </div>
        <p>Prompt siap pakai membantu menjaga proses analisis tetap terarah dan berbasis sumber yang dipilih.</p>
    </div>

    <div class="tools-grid">
        <div class="tool-card tool-dark" data-reveal data-reveal-from="left">
            <span>◎</span>
            <h3>Literature Insight</h3>
            <p>Temukan tema, argumen, dan pola dari dokumen yang dipilih.</p>
        </div>
        <div class="tool-card" data-reveal data-reveal-from="down" data-reveal-delay="70">
            <span>▤</span>
            <h3>Research Summary</h3>
            <p>Bangun ringkasan terstruktur dengan evidence dari library.</p>
        </div>
        <div class="tool-card" data-reveal data-reveal-from="down" data-reveal-delay="140">
            <span>⌁</span>
            <h3>Trend Explorer</h3>
            <p>Eksplorasi konsep yang berulang dan arah riset yang berkembang.</p>
        </div>
        <div class="tool-card" data-reveal data-reveal-from="right" data-reveal-delay="210">
            <span>＋</span>
            <h3>Research Gap</h3>
            <p>Petakan keterbatasan dan peluang pertanyaan penelitian.</p>
        </div>
    </div>
</section>

<section class="section about-section" id="about">
    <div class="about-panel" data-reveal data-reveal-from="left">
        <div>
            <p class="eyebrow">WHY BACADULU RESEARCH</p>
            <h2>Research starts with the right evidence.</h2>
        </div>
        <div>
            <p>
                BacaDulu Research dirancang untuk mengurangi friksi antara membaca sumber,
                memahami bukti, dan mengembangkan ide penelitian. Sumber tidak dicari secara
                bebas oleh AI; prosesnya berangkat dari internal library yang tersedia.
            </p>
            <a href="{{ route('workspace') }}" class="text-link">Start a research chat →</a>
        </div>
    </div>
</section>

<section class="section cta-section" id="contact">
    <div class="cta-panel" data-reveal data-reveal-from="up">
        <p class="eyebrow">START YOUR RESEARCH</p>
        <h2>Build better research with intelligent assistance.</h2>
        <p>Mulai dengan library internal dan tools yang dirancang untuk alur penelitian.</p>
        <a href="{{ auth()->check() ? route('workspace') : route('register') }}" class="btn btn-light" data-motion-button>
            Join BacaDulu Research
        </a>
    </div>
</section>
@endsection
