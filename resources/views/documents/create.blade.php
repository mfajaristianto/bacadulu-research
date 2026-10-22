@extends('layouts.app')

@section('title', 'Generate Document — BacaDulu Research')

@section('content')

@php
    $primaryResult = $run->primaryResult;

    $structured = is_array(
        $primaryResult?->structured_output_json
    )
        ? $primaryResult->structured_output_json
        : [];

    $answer = data_get(
        $structured,
        'answer'
    )
        ?: $primaryResult?->content
        ?: '';

    $conversationTitle =
        $run->conversation?->title
        ?: $run
            ->assistantMessage
            ?->conversation
            ?->title;

    $defaultTitle =
        $conversationTitle
        ?: $run
            ->promptTemplate
            ?->name
        ?: 'BacaDulu Research Document';

    $defaultDocumentType =
        old(
            'document_type',
            config(
                'research.documents.default_type',
                'Research Article'
            )
        );

    $defaultCitationStyle =
        old(
            'citation_style',
            config(
                'research.documents.default_citation_style',
                'APA 7'
            )
        );

    $backConversation =
        $run
            ->assistantMessage
            ?->conversation
        ?: $run->conversation;
@endphp


<div class="br-document-layout">

    <x-research-sidebar
        :conversation="$backConversation"
        :recent-conversations="$recentConversations"
    />


    <button
        type="button"
        class="research-sidebar-backdrop"
        data-chat-sidebar-backdrop
        aria-label="Tutup navigasi Research"
    ></button>


    <div class="br-document-main">

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


        <section
            class="
                document-create-page
                br-doc-config-page
            "
        >

            <div
                class="document-create-head"
                data-reveal
            >

                <div>

                    <p class="eyebrow">
                        DOCUMENT ENGINE
                    </p>


                    <h1 class="display-type">
                        Siapkan draft sebelum dicetak
                    </h1>


                    <p>
                        Tentukan judul, jenis dokumen,
                        dan citation style.
                        Setelah Final Check,
                        BacaDulu menyiapkan draft
                        yang bisa Anda edit per bagian
                        sebelum memilih PDF atau DOCX.
                    </p>

                </div>


                @if($backConversation)

                    <a
                        href="{{
                            route(
                                'workspace.conversation',
                                $backConversation
                            )
                        }}"
                        class="btn btn-secondary"
                    >
                        ← Conversation
                    </a>

                @else

                    <a
                        href="{{ route('workspace') }}"
                        class="btn btn-secondary"
                    >
                        ← Workspace
                    </a>

                @endif

            </div>


            @if($errors->any())

                <div
                    class="alert alert-error"
                    role="alert"
                >
                    {{ $errors->first() }}
                </div>

            @endif


            <div class="br-doc-config-shell">

                <main
                    class="document-create-main"
                    data-reveal
                >

                    <div
                        class="br-doc-steps"
                        aria-label="Tahap pembuatan dokumen"
                    >

                        <span class="is-current">
                            01 · Konfigurasi
                        </span>

                        <span>
                            02 · Review
                        </span>

                        <span>
                            03 · Edit Draft
                        </span>

                        <span>
                            04 · Cetak
                        </span>

                    </div>


                    <form
                        data-document-create-form
                        method="POST"
                        action="{{ route('documents.store') }}"
                        class="document-create-form"
                    >

                        @csrf


                        <input
                            type="hidden"
                            name="ai_run_id"
                            value="{{ $run->id }}"
                        >


                        @if($savedResultId)

                            <input
                                type="hidden"
                                name="saved_result_id"
                                value="{{ $savedResultId }}"
                            >

                        @endif


                        <div class="document-form-section">

                            <div class="document-form-section__index">
                                01
                            </div>


                            <div>

                                <span>
                                    DOCUMENT IDENTITY
                                </span>

                                <h2>
                                    Atur identitas draft
                                </h2>

                            </div>

                        </div>


                        <label
                            class="
                                document-field
                                document-field--full
                            "
                        >

                            <span>
                                Judul dokumen
                            </span>


                            <input
                                type="text"
                                name="title"
                                maxlength="255"
                                value="{{
                                    old(
                                        'title',
                                        $defaultTitle
                                    )
                                }}"
                                required
                            >


                            <small>
                                Judul masih bisa diedit
                                lagi di tahap Edit Draft.
                            </small>

                        </label>


                        <div class="document-form-grid">

                            <label class="document-field">

                                <span>
                                    Jenis dokumen
                                </span>


                                <select
                                    name="document_type"
                                    required
                                >

                                    @foreach ($documentTypes as $documentType)

                                        <option
                                            value="{{ $documentType }}"
                                            {{
                                                $defaultDocumentType
                                                ===
                                                $documentType
                                                    ? 'selected'
                                                    : ''
                                            }}
                                        >
                                            {{ $documentType }}
                                        </option>

                                    @endforeach

                                </select>

                            </label>


                            <label class="document-field">

                                <span>
                                    Citation style
                                </span>


                                <select
                                    name="citation_style"
                                    required
                                >

                                    @foreach ($citationStyles as $style)

                                        <option
                                            value="{{ $style }}"
                                            {{
                                                $defaultCitationStyle
                                                ===
                                                $style
                                                    ? 'selected'
                                                    : ''
                                            }}
                                        >
                                            {{ $style }}
                                        </option>

                                    @endforeach

                                </select>

                            </label>

                        </div>


                        <section
                            class="br-doc-citation-education"
                            aria-label="Panduan Citation Style"
                        >

                            <header>

                                <span class="br-doc-eyebrow">
                                    CITATION STYLE
                                </span>


                                <h2>
                                    Pilih sesuai kebutuhan publikasi
                                </h2>


                                <p>
                                    Perbedaan utamanya ada
                                    pada cara sitasi dan
                                    penulisan referensi.
                                </p>

                            </header>


                            <div class="br-doc-citation-options">

                                @foreach ($citationStyles as $style)

                                    @php
                                        $styleLower = strtolower(
                                            trim(
                                                (string) $style
                                            )
                                        );

                                        if (
                                            str_contains(
                                                $styleLower,
                                                'ieee'
                                            )
                                        ) {
                                            $citationSummary =
                                                'Sitasi berbentuk nomor seperti [1], [2], dan [3].';

                                            $citationUse =
                                                'Umum untuk teknik, teknologi, dan ilmu komputer.';
                                        } elseif (
                                            str_contains(
                                                $styleLower,
                                                'harvard'
                                            )
                                        ) {
                                            $citationSummary =
                                                'Menggunakan nama penulis dan tahun.';

                                            $citationUse =
                                                'Detail format dapat berbeda menurut institusi atau penerbit.';
                                        } else {
                                            $citationSummary =
                                                'Menggunakan nama penulis dan tahun mengikuti APA edisi ke-7.';

                                            $citationUse =
                                                'Umum untuk bisnis, akuntansi, pendidikan, dan ilmu sosial.';
                                        }
                                    @endphp


                                    <button
                                        type="button"
                                        class="br-doc-citation-choice"
                                        data-doc-cite-choice="{{ $style }}"
                                        aria-pressed="{{
                                            $defaultCitationStyle
                                            ===
                                            $style
                                                ? 'true'
                                                : 'false'
                                        }}"
                                    >

                                        <strong
                                            class="
                                                br-doc-citation-choice__name
                                            "
                                        >
                                            {{ $style }}
                                        </strong>


                                        <span>
                                            {{ $citationSummary }}
                                        </span>


                                        <small>
                                            {{ $citationUse }}
                                        </small>

                                    </button>

                                @endforeach

                            </div>

                        </section>


                        <div class="document-integrity-note">

                            <span class="document-integrity-note__mark">
                                ✓
                            </span>


                            <div>

                                <strong>
                                    Draft masih bisa Anda sempurnakan
                                </strong>


                                <p>
                                    Setelah review,
                                    sistem membuat draft terstruktur.
                                    PDF/DOCX belum dibuat sampai
                                    Anda selesai mengedit dan
                                    menekan Cetak.
                                </p>

                            </div>

                        </div>


                        <div class="document-form-actions">

                            <button
                                type="submit"
                                class="btn btn-primary"
                            >
                                Final Check

                                <span aria-hidden="true">
                                    →
                                </span>
                            </button>


                            <span>
                                Belum ada file yang dibuat
                                pada tahap ini.
                            </span>

                        </div>

                    </form>

                </main>


                <aside
                    class="document-source-context"
                    data-reveal
                >

                    <div class="document-context-label">
                        RESEARCH CONTEXT
                    </div>


                    <dl>

                        <div>

                            <dt>
                                Research Tool
                            </dt>

                            <dd>
                                {{
                                    $run
                                        ->aiTool
                                        ?->name
                                    ?: '—'
                                }}
                            </dd>

                        </div>


                        <div>

                            <dt>
                                Prompt
                            </dt>

                            <dd>
                                {{
                                    $run
                                        ->promptTemplate
                                        ?->name
                                    ?: '—'
                                }}
                            </dd>

                        </div>


                        <div>

                            <dt>
                                Status
                            </dt>

                            <dd>
                                {{
                                    strtoupper(
                                        $run->status
                                    )
                                }}
                            </dd>

                        </div>

                    </dl>


                    @if($answer !== '')

                        <div class="document-context-answer">

                            <span>
                                FINAL RESEARCH OUTPUT
                            </span>


                            <p>
                                {{
                                    \Illuminate\Support\Str::limit(
                                        $answer,
                                        560,
                                        '…'
                                    )
                                }}
                            </p>

                        </div>

                    @endif

                </aside>

            </div>

        </section>


        <dialog
            class="br-doc-dialog"
            data-document-confirm
            aria-labelledby="br-doc-dialog-title"
        >

            <span class="br-doc-eyebrow">
                REVIEW · FINAL CHECK
            </span>


            <h2 id="br-doc-dialog-title">
                Apakah Anda sudah yakin?
            </h2>


            <p>
                Setelah menekan Ya,
                sistem hanya menyiapkan draft.
                Anda masih bisa mengedit Judul,
                setiap bagian A/B/C,
                sampai Daftar Pustaka
                sebelum mencetak.
            </p>


            <dl>

                <div>

                    <dt>
                        Judul
                    </dt>

                    <dd data-doc-review-title>
                        —
                    </dd>

                </div>


                <div>

                    <dt>
                        Jenis dokumen
                    </dt>

                    <dd data-doc-review-type>
                        —
                    </dd>

                </div>


                <div>

                    <dt>
                        Citation Style
                    </dt>

                    <dd data-doc-review-citation>
                        —
                    </dd>

                </div>

            </dl>


            <div class="br-doc-dialog__actions">

                <button
                    type="button"
                    class="btn btn-secondary"
                    data-doc-back
                >
                    Belum, ubah pengaturan
                </button>


                <button
                    type="button"
                    class="btn btn-primary"
                    data-doc-create-draft
                >
                    Ya, siapkan draft →
                </button>

            </div>

        </dialog>


        <div
            class="
                br-doc-generation
                br-doc-generation--draft
            "
            data-document-draft-loading
            hidden
            aria-live="polite"
            aria-busy="true"
        >

            <div class="br-doc-generation__backdrop"></div>


            <section
                class="br-doc-generation__panel"
                role="status"
            >

                <span class="br-doc-eyebrow">
                    PREPARING DRAFT
                </span>


                <div
                    class="br-doc-generation__file-motion"
                    aria-hidden="true"
                >
                    <span></span>
                    <span></span>
                    <span></span>
                </div>


                <strong>
                    Menyiapkan ruang penyuntingan
                </strong>


                <p>
                    Struktur dokumen sedang disiapkan.
                    Belum ada PDF atau DOCX yang dicetak.
                </p>

            </section>

        </div>

    </div>

</div>

@endsection