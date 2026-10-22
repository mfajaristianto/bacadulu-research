@extends('layouts.app')

@section(
    'title',
    $document->title.' — Research Document'
)

@section('content')

@php
    $content = is_array($document->content_json)
        ? $document->content_json
        : [];

    $sections = collect(
        data_get(
            $content,
            'sections',
            []
        )
    )
        ->filter(
            fn ($section) => is_array($section)
        )
        ->values();

    $references = collect(
        data_get(
            $content,
            'references',
            []
        )
    )
        ->map(
            fn ($reference) => trim((string) $reference)
        )
        ->filter()
        ->values();

    $documentTitle = (string) data_get(
        $content,
        'title',
        $document->title
    );

    $isReady =
        $document->status === 'completed';

    $storedFormat = in_array(
        $activeFormat ?? null,
        [
            'pdf',
            'docx',
        ],
        true
    )
        ? $activeFormat
        : null;
@endphp


<div
    class="
        br-document-layout
        br-document-view
        {{ $editing ? 'is-editing-draft' : 'is-final-document' }}
    "
>

    <x-research-sidebar
        :conversation="$document->conversation"
        :recent-conversations="$recentConversations"
    />


    <button
        type="button"
        class="research-sidebar-backdrop"
        data-chat-sidebar-backdrop
        aria-label="Tutup navigasi Research"
    ></button>


    <main class="br-document-main">

        <button
            type="button"
            class="br-doc-mobile-nav"
            data-chat-sidebar-open
            aria-expanded="false"
            aria-label="Buka navigasi Research"
        >
            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                aria-hidden="true"
            >
                <path d="M4 6h16M4 12h16M4 18h16"/>
            </svg>

            Menu Research
        </button>


        @if($editing)

            <section
                class="br-draft-workspace"
                data-document-editor
                data-document-id="{{ $document->id }}"
                data-update-url="{{ route('documents.update', $document) }}"
                data-render-url="{{ route('documents.render', $document) }}"
            >

                {{-- HEADER --}}

                <header class="br-draft-workspace__head">

                    <div>

                        <span class="br-doc-eyebrow">
                            EDIT DRAFT · FINAL REVIEW
                        </span>


                        <h1>
                            Periksa dokumen sebelum dicetak
                        </h1>


                        <p>
                            Edit isi di panel kanan.
                            Preview di tengah berubah langsung
                            dan mengikuti bagian yang sedang Anda pilih.
                            Simpan draft sebelum mencetak hasil akhir.
                        </p>

                    </div>


                    <div class="br-draft-workspace__head-actions">

                        <span
                            class="br-draft-save-state"
                            data-draft-save-state
                        >
                            Draft tersimpan
                        </span>


                        <a
                            href="{{ route('documents.index', ['format' => 'draft']) }}"
                            class="btn btn-secondary"
                        >
                            Dokumen Saya
                        </a>

                    </div>

                </header>


                @if(session('success'))

                    <div
                        class="alert alert-success"
                        role="status"
                    >
                        {{ session('success') }}
                    </div>

                @endif


                @if($errors->any())

                    <div
                        class="alert alert-error"
                        role="alert"
                    >
                        {{ $errors->first() }}
                    </div>

                @endif


                {{-- MOBILE MODE --}}

                <div
                    class="br-draft-mobile-mode"
                    role="tablist"
                    aria-label="Tampilan editor dokumen"
                >

                    <button
                        type="button"
                        role="tab"
                        data-doc-mobile-mode="edit"
                        aria-selected="true"
                    >
                        Edit
                    </button>


                    <button
                        type="button"
                        role="tab"
                        data-doc-mobile-mode="preview"
                        aria-selected="false"
                    >
                        Preview
                    </button>

                </div>


                <div
                    class="br-draft-workspace__grid"
                    data-doc-workspace-grid
                    data-mobile-view="edit"
                >

                    {{-- =========================================
                        MIDDLE — PREVIEW
                    ========================================== --}}

                    <section
                        class="br-draft-preview"
                        aria-label="Live document preview"
                    >

                        <div class="br-draft-preview__topbar">

                            <div>

                                <span class="br-doc-eyebrow">
                                    LIVE PREVIEW
                                </span>


                                <strong>
                                    Hasil sementara dokumen
                                </strong>

                            </div>


                            <div class="br-draft-preview__meta">

                                <span>
                                    {{ $document->document_type }}
                                </span>

                                <span>·</span>

                                <span>
                                    {{ $document->citation_style }}
                                </span>

                            </div>

                        </div>


                        <div
                            class="br-draft-preview__canvas"
                            data-preview-scroller
                        >

                            <article
                                class="br-draft-paper"
                                data-draft-paper
                            >

                                <header
                                    class="br-draft-paper__head"
                                    data-preview-title-anchor
                                >

                                    <h2 data-preview-title>
                                        {{ $documentTitle }}
                                    </h2>


                                    <p>
                                        {{ $document->document_type }}
                                        ·
                                        {{ $document->citation_style }}
                                    </p>

                                </header>


                                <div data-preview-sections>

                                    @foreach($sections as $index => $section)

                                        <section
                                            class="br-draft-paper__section"
                                            data-preview-section="{{ $index }}"
                                        >

                                            <h3 data-preview-heading>
                                                {{
                                                    preg_replace(
                                                        '/^\s*#{1,6}\s*/u',
                                                        '',
                                                        (string) data_get(
                                                            $section,
                                                            'heading',
                                                            'SECTION'
                                                        )
                                                    )
                                                }}
                                            </h3>


                                            <div
                                                class="br-draft-paper__body"
                                                data-preview-body
                                            >
                                                {!!
                                                    \Illuminate\Support\Str::markdown(
                                                        (string) data_get(
                                                            $section,
                                                            'body',
                                                            ''
                                                        ),
                                                        [
                                                            'html_input' => 'strip',
                                                            'allow_unsafe_links' => false,
                                                        ]
                                                    )
                                                !!}
                                            </div>

                                        </section>

                                    @endforeach

                                </div>


                                <section
                                    class="
                                        br-draft-paper__section
                                        br-draft-paper__references
                                    "
                                    data-preview-references
                                >

                                    <h3>
                                        DAFTAR PUSTAKA
                                    </h3>


                                    <div data-preview-reference-list>

                                        @forelse($references as $reference)

                                            <p>
                                                {{ $reference }}
                                            </p>

                                        @empty

                                            <p class="is-empty-reference">
                                                Belum ada daftar pustaka.
                                            </p>

                                        @endforelse

                                    </div>

                                </section>

                            </article>

                        </div>

                    </section>


                    {{-- =========================================
                        RIGHT — EDITOR
                    ========================================== --}}

                    <aside class="br-draft-editor">

                        <div class="br-draft-editor__head">

                            <span class="br-doc-eyebrow">
                                EDIT DRAFT
                            </span>


                            <h2>
                                Edit per bagian
                            </h2>


                            <p>
                                Pilih bagian di bawah.
                                Preview tengah otomatis menuju
                                bagian yang sama tanpa reload halaman.
                            </p>

                        </div>


                        {{-- =====================================
                            COMPACT OUTPUT
                        ====================================== --}}

                        <section
                            class="br-draft-print"
                            data-draft-print
                        >

                            <div
                                class="br-draft-print__formats"
                                role="radiogroup"
                                aria-label="Format cetak"
                            >

                                <button
                                    type="button"
                                    data-print-format="pdf"
                                    role="radio"
                                    aria-checked="false"
                                    title="Cetak sebagai PDF"
                                >

                                    <span class="br-draft-print__check">
                                        ✓
                                    </span>


                                    <svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        aria-hidden="true"
                                    >
                                        <path d="M6 3h8l4 4v14H6V3Z"/>
                                        <path d="M14 3v5h5"/>
                                    </svg>


                                    <strong>
                                        PDF
                                    </strong>


                                    <small>
                                        Tampilan tetap
                                    </small>

                                </button>


                                <button
                                    type="button"
                                    data-print-format="docx"
                                    role="radio"
                                    aria-checked="false"
                                    title="Cetak sebagai DOCX"
                                >

                                    <span class="br-draft-print__check">
                                        ✓
                                    </span>


                                    <svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        aria-hidden="true"
                                    >
                                        <path d="M6 3h8l4 4v14H6V3Z"/>
                                        <path d="M14 3v5h5"/>
                                    </svg>


                                    <strong>
                                        DOCX
                                    </strong>


                                    <small>
                                        File Word editable
                                    </small>

                                </button>

                            </div>


                            <button
                                type="button"
                                class="
                                    btn
                                    btn-primary
                                    br-draft-print__button
                                "
                                data-open-print-confirm
                                disabled
                            >
                                Review & Cetak →
                            </button>

                        </section>


                        {{-- =====================================
                            EDIT FORM
                        ====================================== --}}

                        <form
                            method="POST"
                            action="{{ route('documents.update', $document) }}"
                            data-draft-edit-form
                        >

                            @csrf

                            @method('PATCH')


                            {{-- SECTION NAV --}}

                            <div class="br-draft-editor__nav-wrap">

                                <span class="br-draft-editor__nav-label">
                                    BAGIAN DOKUMEN
                                </span>


                                <nav
                                    class="br-draft-editor__nav"
                                    aria-label="Bagian dokumen"
                                >

                                    <button
                                        type="button"
                                        class="is-active"
                                        data-editor-tab="title"
                                        aria-selected="true"
                                    >

                                        <span>
                                            Judul
                                        </span>


                                        <small>
                                            Document title
                                        </small>

                                    </button>


                                    @foreach($sections as $index => $section)

                                        <button
                                            type="button"
                                            data-editor-tab="section-{{ $index }}"
                                            aria-selected="false"
                                        >

                                            <span>
                                                {{
                                                    preg_replace(
                                                        '/^\s*#{1,6}\s*/u',
                                                        '',
                                                        (string) data_get(
                                                            $section,
                                                            'heading',
                                                            'SECTION'
                                                        )
                                                    )
                                                }}
                                            </span>


                                            <small>
                                                {{
                                                    str_pad(
                                                        (string) ($index + 1),
                                                        2,
                                                        '0',
                                                        STR_PAD_LEFT
                                                    )
                                                }}
                                            </small>

                                        </button>

                                    @endforeach


                                    <button
                                        type="button"
                                        data-editor-tab="references"
                                        aria-selected="false"
                                    >

                                        <span>
                                            Daftar Pustaka
                                        </span>


                                        <small>
                                            {{ $references->count() }}
                                            ref.
                                        </small>

                                    </button>

                                </nav>

                            </div>


                            {{-- EDITOR CONTENT --}}

                            <div class="br-draft-editor__panes">

                                {{-- TITLE --}}

                                <section
                                    class="
                                        br-draft-editor__pane
                                        is-active
                                    "
                                    data-editor-pane="title"
                                >

                                    <label class="document-field">

                                        <span>
                                            Judul dokumen
                                        </span>


                                        <textarea
                                            name="title"
                                            rows="5"
                                            maxlength="255"
                                            required
                                            data-draft-title-input
                                        >{{ old('title', $documentTitle) }}</textarea>

                                    </label>


                                    <p class="br-draft-editor__hint">
                                        Judul diperbarui langsung
                                        pada preview di tengah.
                                    </p>

                                </section>


                                {{-- SECTIONS --}}

                                @foreach($sections as $index => $section)

                                    <section
                                        class="br-draft-editor__pane"
                                        data-editor-pane="section-{{ $index }}"
                                        hidden
                                    >

                                        <input
                                            type="hidden"
                                            name="sections[{{ $index }}][heading]"
                                            value="{{ old(
                                                'sections.'.$index.'.heading',
                                                data_get(
                                                    $section,
                                                    'heading',
                                                    'SECTION'
                                                )
                                            ) }}"
                                            data-section-heading-input="{{ $index }}"
                                        >


                                        <div class="br-draft-editor__section-title">

                                            <span>
                                                Bagian
                                                {{
                                                    str_pad(
                                                        (string) ($index + 1),
                                                        2,
                                                        '0',
                                                        STR_PAD_LEFT
                                                    )
                                                }}
                                            </span>


                                            <h3>
                                                {{
                                                    preg_replace(
                                                        '/^\s*#{1,6}\s*/u',
                                                        '',
                                                        (string) data_get(
                                                            $section,
                                                            'heading',
                                                            'SECTION'
                                                        )
                                                    )
                                                }}
                                            </h3>

                                        </div>


                                        <label class="document-field">

                                            <span>
                                                Isi bagian
                                            </span>


                                            <textarea
                                                name="sections[{{ $index }}][body]"
                                                rows="16"
                                                maxlength="50000"
                                                data-section-body-input="{{ $index }}"
                                            >{{ old(
                                                'sections.'.$index.'.body',
                                                data_get(
                                                    $section,
                                                    'body',
                                                    ''
                                                )
                                            ) }}</textarea>

                                        </label>


                                        <p class="br-draft-editor__hint">
                                            Saat Anda mengetik,
                                            isi preview berubah secara real-time.
                                        </p>

                                    </section>

                                @endforeach


                                {{-- REFERENCES --}}

                                <section
                                    class="br-draft-editor__pane"
                                    data-editor-pane="references"
                                    hidden
                                >

                                    <label class="document-field">

                                        <span>
                                            Daftar Pustaka
                                        </span>


                                        <textarea
                                            name="references_text"
                                            rows="16"
                                            data-references-text
                                        >{{ old(
                                            'references_text',
                                            $references->implode("\n")
                                        ) }}</textarea>

                                    </label>


                                    <p class="br-draft-editor__hint">
                                        Tulis satu referensi per baris.
                                    </p>


                                    <div data-reference-hidden-inputs>

                                        @foreach($references as $index => $reference)

                                            <input
                                                type="hidden"
                                                name="references[{{ $index }}]"
                                                value="{{ $reference }}"
                                            >

                                        @endforeach

                                    </div>

                                </section>

                            </div>


                            {{-- SAVE --}}

                            <div class="br-draft-editor__actions">

                                <span data-draft-unsaved-label>
                                    Tidak ada perubahan
                                </span>


                                <button
                                    type="submit"
                                    class="btn btn-primary"
                                    data-draft-save-button
                                >
                                    Simpan Perubahan
                                </button>

                            </div>

                        </form>

                    </aside>

                </div>

            </section>


            {{-- =============================================
                FINAL CONFIRMATION
            ============================================== --}}

            <dialog
                class="
                    br-doc-dialog
                    br-print-confirm
                "
                data-print-confirm
                aria-labelledby="br-print-confirm-title"
            >

                <span class="br-doc-eyebrow">
                    FINAL PRINT CHECK
                </span>


                <h2 id="br-print-confirm-title">
                    Cetak dokumen sekarang?
                </h2>


                <p>
                    Pastikan seluruh pengaturan
                    dan isi sudah sesuai.
                </p>


                <dl>

                    <div>
                        <dt>Judul</dt>

                        <dd data-print-summary-title>
                            {{ $documentTitle }}
                        </dd>
                    </div>


                    <div>
                        <dt>Jenis dokumen</dt>

                        <dd>
                            {{ $document->document_type }}
                        </dd>
                    </div>


                    <div>
                        <dt>Citation Style</dt>

                        <dd>
                            {{ $document->citation_style }}
                        </dd>
                    </div>


                    <div>
                        <dt>Bagian</dt>

                        <dd>
                            {{ $sections->count() }}
                            bagian + Daftar Pustaka
                        </dd>
                    </div>


                    <div>
                        <dt>Format final</dt>

                        <dd data-print-summary-format>
                            —
                        </dd>
                    </div>

                </dl>


                <div class="br-doc-dialog__actions">

                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-print-cancel
                    >
                        Belum, cek lagi
                    </button>


                    <button
                        type="button"
                        class="btn btn-primary"
                        data-print-confirm-button
                    >
                        Cetak sekarang →
                    </button>

                </div>

            </dialog>


            {{-- =============================================
                GENERATION
            ============================================== --}}

            <div
                class="br-doc-generation"
                data-document-generation
                hidden
                aria-live="polite"
                aria-busy="true"
            >

                <div class="br-doc-generation__backdrop"></div>


                <section
                    class="br-doc-generation__panel"
                    role="dialog"
                    aria-modal="true"
                >

                    <span class="br-doc-eyebrow">
                        DOCUMENT ENGINE
                    </span>


                    <div
                        class="br-doc-generation__file-motion"
                        aria-hidden="true"
                    >
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>


                    <strong data-doc-progress-title>
                        Menyiapkan dokumen
                    </strong>


                    <p data-doc-progress-copy>
                        Menata isi final,
                        citation,
                        dan format file.
                    </p>


                    <div
                        class="br-doc-generation__track"
                        role="progressbar"
                        aria-valuemin="0"
                        aria-valuemax="100"
                        aria-valuenow="0"
                        data-doc-progress-bar
                    >
                        <span data-doc-progress-fill></span>
                    </div>


                    <div class="br-doc-generation__meta">

                        <span data-doc-progress-stage>
                            Menyiapkan struktur…
                        </span>


                        <b data-doc-progress-value>
                            0%
                        </b>

                    </div>


                    <div
                        class="br-doc-generation__success"
                        data-doc-success
                        hidden
                    >

                        <div
                            class="br-doc-generation__success-icon"
                            aria-hidden="true"
                        >
                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                            >
                                <path d="m6 12 4 4 8-9"/>
                            </svg>
                        </div>


                        <div class="br-doc-generation__success-copy">

                            <strong data-doc-success-title>
                                Dokumen berhasil dibuat
                            </strong>


                            <p>
                                Anda akan diarahkan dalam

                                <b data-doc-success-countdown>
                                    5
                                </b>

                                detik.

                                Jika tidak ingin menunggu,
                                pilih tindakan di bawah.
                            </p>

                        </div>


                        <div class="br-doc-generation__success-actions">

                            <button
                                type="button"
                                class="btn btn-primary"
                                data-doc-open-result
                            >
                                Buka dokumen sekarang
                            </button>


                            <button
                                type="button"
                                class="btn btn-secondary"
                                data-doc-stay-here
                            >
                                Tetap di halaman ini
                            </button>

                        </div>

                    </div>


                    <button
                        type="button"
                        class="br-doc-generation__retry"
                        data-doc-progress-retry
                        hidden
                    >
                        Coba lagi
                    </button>

                </section>

            </div>


        @else

            {{-- =============================================
                GENERATED DOCUMENT
            ============================================== --}}

            <section
                class="
                    document-page
                    br-final-document-page
                "
            >

                <header
                    class="
                        document-page-head
                        document-page-head--single
                    "
                >

                    <div>

                        <div class="document-page-kicker">

                            <span>
                                {{
                                    $storedFormat === 'pdf'
                                        ? 'PDF DOCUMENT'
                                        : 'DOCX DOCUMENT'
                                }}
                            </span>


                            <span
                                class="
                                    document-status
                                    document-status--{{ $document->status }}
                                "
                            >
                                {{ strtoupper($document->status) }}
                            </span>

                        </div>


                        <h1 class="display-type">
                            {{ $document->title }}
                        </h1>


                        <p>
                            {{ $document->document_type }}
                            ·
                            {{ $document->citation_style }}

                            @if($document->generated_at)
                                · Generated
                                {{ $document->generated_at->format('d M Y · H:i') }}
                            @endif
                        </p>

                    </div>


                    @if($isReady && $storedFormat)

                        <a
                            href="{{
                                route(
                                    'documents.download',
                                    [
                                        $document,
                                        $storedFormat,
                                    ]
                                )
                            }}"
                            class="btn btn-primary"
                        >
                            Download
                            {{ strtoupper($storedFormat) }}
                            ↓
                        </a>

                    @endif

                </header>


                <div class="br-doc-view-toolbar">

                    <a
                        href="{{
                            route(
                                'documents.index',
                                [
                                    'format' => $storedFormat ?: 'all',
                                ]
                            )
                        }}"
                    >
                        ← Dokumen Saya
                    </a>


                    <span>
                        Dokumen sudah tersimpan di Dokumen Saya
                    </span>

                </div>


                @if(session('success'))

                    <div
                        class="alert alert-success"
                        role="status"
                    >
                        {{ session('success') }}
                    </div>

                @endif


                {{-- PDF --}}

                @if($storedFormat === 'pdf')

                    <section
                        class="br-doc-pdf-only"
                        aria-label="Pratinjau PDF"
                    >

                        <div class="br-doc-pdf-only__head">

                            <div>

                                <span class="br-doc-eyebrow">
                                    PDF PREVIEW
                                </span>


                                <h2>
                                    Lihat PDF
                                </h2>

                            </div>


                            <small>
                                PDF ditampilkan di browser,
                                tidak diunduh otomatis.
                            </small>

                        </div>


                        @if($isReady && filled($document->pdf_path))

                            <iframe
                                src="{{ route('documents.preview', $document) }}"
                                title="Pratinjau PDF {{ $document->title }}"
                                loading="eager"
                            ></iframe>

                        @else

                            <div class="br-doc-view-empty">
                                PDF belum tersedia.
                            </div>

                        @endif

                    </section>


                {{-- DOCX --}}

                @elseif($storedFormat === 'docx')

                    <section class="br-docx-final-preview">

                        <div class="br-doc-pdf-only__head">

                            <div>

                                <span class="br-doc-eyebrow">
                                    DOCX CONTENT
                                </span>


                                <h2>
                                    Isi dokumen
                                </h2>

                            </div>


                            <small>
                                Gunakan Download DOCX
                                untuk membuka file di Word.
                            </small>

                        </div>


                        <article class="br-draft-paper">

                            <header class="br-draft-paper__head">

                                <h2>
                                    {{ $documentTitle }}
                                </h2>


                                <p>
                                    {{ $document->document_type }}
                                    ·
                                    {{ $document->citation_style }}
                                </p>

                            </header>


                            @foreach($sections as $section)

                                <section class="br-draft-paper__section">

                                    <h3>
                                        {{
                                            preg_replace(
                                                '/^\s*#{1,6}\s*/u',
                                                '',
                                                (string) data_get(
                                                    $section,
                                                    'heading',
                                                    'SECTION'
                                                )
                                            )
                                        }}
                                    </h3>


                                    <div class="br-draft-paper__body">
                                        {!!
                                            \Illuminate\Support\Str::markdown(
                                                (string) data_get(
                                                    $section,
                                                    'body',
                                                    ''
                                                ),
                                                [
                                                    'html_input' => 'strip',
                                                    'allow_unsafe_links' => false,
                                                ]
                                            )
                                        !!}
                                    </div>

                                </section>

                            @endforeach


                            @if($references->isNotEmpty())

                                <section
                                    class="
                                        br-draft-paper__section
                                        br-draft-paper__references
                                    "
                                >

                                    <h3>
                                        DAFTAR PUSTAKA
                                    </h3>


                                    @foreach($references as $reference)

                                        <p>
                                            {{ $reference }}
                                        </p>

                                    @endforeach

                                </section>

                            @endif

                        </article>

                    </section>

                @endif

            </section>

        @endif

    </main>

</div>

@endsection