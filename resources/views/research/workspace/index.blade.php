@extends('layouts.app')

@section(
    'title',
    'Research Workspace — BacaDulu Research'
)

@section('content')

@php
    $user = auth()->user();

    $messages = $conversation
        ?->messages
        ?? collect();

    $lastUserMessage = $messages
        ->where('role', 'user')
        ->last();

    $latestAssistantMessage = $messages
        ->where('role', 'assistant')
        ->last();

    $activePromptId =
        old('prompt_template_id')
        ?: request()->integer('prompt')
        ?: data_get(
            $lastUserMessage?->metadata,
            'prompt_template_id'
        );

    $activePrompt = $activePromptId
        ? $prompts->firstWhere(
            'id',
            (int) $activePromptId
        )
        : null;

    $latestEvidence = data_get(
        $latestAssistantMessage?->metadata,
        'evidence',
        []
    );

    $latestSources = data_get(
        $latestAssistantMessage?->metadata,
        'sources',
        []
    );

    $latestStatus = data_get(
        $latestAssistantMessage?->metadata,
        'status'
    );

    $isQueued =
        $latestStatus === 'queued';

    $isProcessing = in_array(
        $latestStatus,
        ['queued', 'retrieving', 'evidence_ready', 'generating'],
        true
    );

    $latestRunId = $latestAssistantMessage?->ai_run_id;

    $runStatusUrl = $latestRunId
        ? route('research.runs.status', $latestRunId)
        : null;

    $researchQuestion =
        $lastUserMessage?->content
        ?: $conversation?->title
        ?: 'Research Anda';

    $sourceCount =
        is_array($latestSources)
        ? count($latestSources)
        : 0;

    $evidenceCount =
        is_array($latestEvidence)
        ? count($latestEvidence)
        : 0;

    $excludedMessageIds = collect([
        $lastUserMessage?->id,
        $latestAssistantMessage?->id,
    ])->filter();

    $olderMessages =
        $conversation
            ? $messages->reject(
                fn ($message) =>
                    $excludedMessageIds
                        ->contains(
                            $message->id
                        )
            )
            : collect();

    $nextPrompt =
        $activePrompt
            ? $prompts
                ->first(
                    fn ($prompt) =>
                        $prompt->id
                        !== $activePrompt->id
                )
            : $prompts->first();

    // User tanpa riwayat research => First Research Experience (returning user tetap melihat halaman lama).
    $isFirstRun =
        ! $conversation
        && $pinnedConversations->isEmpty()
        && $recentConversations->isEmpty();
@endphp


<div class="research-workspace-shell">

    @if(is_null($user->onboarding_completed_at))
        @include('research.workspace.partials.first-tour')
    @endif

    <x-research-sidebar
        :conversation="$conversation"
        :pinned-conversations="$pinnedConversations"
        :recent-conversations="$recentConversations"
    />


    <button
        class="research-sidebar-backdrop"
        type="button"
        data-chat-sidebar-backdrop
        aria-label="Tutup riwayat research"
    ></button>


    <section
        class="
            research-canvas
            {{
                $conversation
                    ? 'has-conversation'
                    : 'is-new-research'
            }}
        "
    >

            @include('research.workspace.partials.topbar')

        
        {{-- ============================================================
            SELECTED SOURCE
        ============================================================ --}}
        @if($selectedSource)

            <div class="research-source-context">

                <div>

                    <span>
                        SOURCE CONTEXT
                    </span>

                    <strong>
                        {{ $selectedSource->title }}
                    </strong>

                    <small>

                        {{
                            $selectedSource->source_label
                            ?: 'Curated Source'
                        }}

                        @if(
                            $selectedSource
                                ->publication_year
                        )
                            ·
                            {{
                                $selectedSource
                                    ->publication_year
                            }}
                        @endif

                    </small>

                </div>


                <p>
                    Source ini digunakan sebagai
                    context retrieval untuk
                    memperkuat evidence research.
                </p>

            </div>

        @endif



        {{-- ============================================================
            ACTIVE TOOL — CONVERSATION
        ============================================================ --}}
        @if(
            $conversation
            && $activePrompt
        )

            <div
                class="research-active-tool"
                data-active-prompt
            >

                <div class="research-active-tool__mark">

                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                    >
                        <path d="M5 4h14v16H5z" />
                        <path d="M8 8h8M8 12h8M8 16h5" />
                    </svg>

                </div>


                <div class="research-active-tool__copy">

                    <span>
                        ACTIVE RESEARCH TOOL
                    </span>

                    <strong data-active-prompt-name>
                        {{ $activePrompt->display_name }}
                    </strong>

                    <p data-active-prompt-description>

                        {{
                            $activePrompt
                                ->short_description
                            ?: $activePrompt
                                ->aiTool
                                ?->description
                            ?: ''
                        }}

                    </p>

                </div>


                <button
                    type="button"
                    data-prompt-open-secondary
                >
                    Ganti Prompt
                </button>

            </div>

        @endif



        {{-- ============================================================
            NEW RESEARCH
        ============================================================ --}}
        @if(!$conversation)

            @if($isFirstRun)

                @include('research.workspace.partials.first-research')

            @else

            <div class="research-new-page">

                {{-- HERO --}}
                <section class="research-new-hero">

                    <div
                        class="research-new-hero__copy"
                        data-reveal
                    >

                        <span class="research-section-label">
                            NEW RESEARCH
                        </span>

                        <h1>
                            Mulai dari pertanyaan
                        </h1>

                        <p class="research-new-hero__script">
                            Bangun dari evidence
                        </p>

                        <p class="research-new-hero__lead">
                            Pilih Research Tool, pahami kegunaannya,
                            lalu tulis pertanyaan penelitian Anda.
                            BacaDulu akan menggunakan source yang
                            eligible untuk membantu proses research.
                        </p>

                    </div>


                    {{-- RESEARCH VISUAL --}}
                    <div
                        class="research-discovery-visual"
                        aria-hidden="true"
                    >

                        <div
                            class="
                                research-discovery-paper
                                research-discovery-paper--top
                            "
                        >
                            <span class="research-paper-badge">
                                Jurnal Akademik
                            </span>

                            <i></i>
                            <i></i>
                            <i></i>
                        </div>


                        <div
                            class="
                                research-discovery-paper
                                research-discovery-paper--middle
                            "
                        >
                            <span class="research-paper-badge">
                                Laporan Institusi
                            </span>

                            <i></i>
                            <i></i>
                            <i></i>
                        </div>


                        <div
                            class="
                                research-discovery-paper
                                research-discovery-paper--bottom
                            "
                        >
                            <span class="research-paper-badge">
                                Policy Paper
                            </span>

                            <i></i>
                            <i></i>
                            <i></i>
                        </div>


                        <div class="research-discovery-search">

                            <svg
                                viewBox="0 0 64 64"
                                fill="none"
                            >
                                <circle
                                    cx="27"
                                    cy="27"
                                    r="13"
                                />

                                <path d="m37 37 15 15" />
                            </svg>

                        </div>


                        <div class="research-evidence-aware-card">

                            <span>
                                <i></i>
                                Evidence-aware
                            </span>

                            <p>
                                Menggunakan sumber
                                yang relevan dan
                                terpercaya
                            </p>

                            <b>
                                →
                            </b>

                        </div>


                        <svg
                            class="research-discovery-route"
                            viewBox="0 0 520 330"
                            preserveAspectRatio="none"
                        >
                            <path
                                d="
                                    M330 45
                                    C450 45,
                                    460 80,
                                    460 120
                                "
                            />

                            <path
                                d="
                                    M80 210
                                    C28 210,
                                    30 260,
                                    60 280
                                "
                            />
                        </svg>

                    </div>

                </section>



                {{-- GUIDE --}}
                <section class="research-start-guide">

                    <article>

                        <span>
                            01
                        </span>

                        <div class="research-start-guide__icon">

                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                            >
                                <rect
                                    x="4"
                                    y="4"
                                    width="6"
                                    height="6"
                                    rx="1"
                                />

                                <rect
                                    x="14"
                                    y="4"
                                    width="6"
                                    height="6"
                                    rx="1"
                                />

                                <rect
                                    x="4"
                                    y="14"
                                    width="6"
                                    height="6"
                                    rx="1"
                                />

                                <rect
                                    x="14"
                                    y="14"
                                    width="6"
                                    height="6"
                                    rx="1"
                                />
                            </svg>

                        </div>

                        <div>

                            <strong>
                                Pilih Research Tool
                            </strong>

                            <small>
                                Buka “Apa ini?” jika istilah
                                belum familiar.
                            </small>

                        </div>

                        <b>
                            →
                        </b>

                    </article>


                    <article>

                        <span>
                            02
                        </span>

                        <div class="research-start-guide__icon">

                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                            >
                                <path d="m4 17 9-9 3 3-9 9H4z" />
                                <path d="m14 7 2-2 3 3-2 2" />
                            </svg>

                        </div>

                        <div>

                            <strong>
                                Tulis pertanyaan
                            </strong>

                            <small>
                                Berikan fokus topik, objek,
                                atau hubungan yang ingin dianalisis.
                            </small>

                        </div>

                        <b>
                            →
                        </b>

                    </article>


                    <article>

                        <span>
                            03
                        </span>

                        <div class="research-start-guide__icon">

                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                            >
                                <path d="M5 3h10l4 4v14H5z" />
                                <path d="M15 3v5h5" />
                                <circle
                                    cx="10"
                                    cy="13"
                                    r="3"
                                />
                                <path d="m12 15 3 3" />
                            </svg>

                        </div>

                        <div>

                            <strong>
                                Periksa evidence
                            </strong>

                            <small>
                                Nilai jawaban bersama source context,
                                bukan output AI saja.
                            </small>

                        </div>

                    </article>

                </section>



                {{-- ======================================================
                    NEW RESEARCH FORM
                ====================================================== --}}
                <section class="research-new-composer-card">

                    @if($errors->any())

                        <div
                            class="
                                alert
                                alert-error
                                research-composer-alert
                            "
                            role="alert"
                        >
                            {{ $errors->first() }}
                        </div>

                    @endif


                    @if(session('success'))

                        <div
                            class="research-composer-success"
                            role="status"
                        >
                            {{ session('success') }}
                        </div>

                    @endif


                    <form
                        method="POST"
                        action="{{ route('research.run') }}"
                        class="research-composer research-composer--new"
                        data-chat-form
                    >

                        @csrf


                        @if($selectedSource)

                            <input
                                type="hidden"
                                name="source_id"
                                value="{{ $selectedSource->id }}"
                            >

                        @endif


                        <div class="research-composer__controls">

                            <label class="research-category-select">

                                <span>
                                    KATEGORI
                                </span>

                                <div class="research-select-shell">

                                    <svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                    >
                                        <path
                                            d="
                                                M3 5
                                                c4-2 7-1 9 1
                                                v14
                                                c-3-2-6-3-9-1z
                                            "
                                        />

                                        <path
                                            d="
                                                M21 5
                                                c-4-2-7-1-9 1
                                                v14
                                                c3-2 6-3 9-1z
                                            "
                                        />
                                    </svg>

                                    <select
                                        name="category_id"
                                        required
                                        aria-label="Kategori research"
                                    >

                                        <option value="">
                                            Pilih kategori
                                        </option>

                                        @foreach(
                                            $categories
                                            as $category
                                        )

                                            <option
                                                value="{{ $category->id }}"
                                                @selected(
                                                    old(
                                                        'category_id',
                                                        $selectedSource
                                                            ?->category_id
                                                    )
                                                    == $category->id
                                                )
                                            >
                                                {{ $category->name }}
                                            </option>

                                        @endforeach

                                    </select>

                                </div>

                            </label>



                            <div class="research-prompt-picker-wrap">

                                <span class="research-composer__label">
                                    RESEARCH TOOL / PROMPT
                                </span>

                                <button
                                    type="button"
                                    class="research-prompt-picker"
                                    data-prompt-open
                                    aria-expanded="false"
                                    aria-controls="research-prompt-menu"
                                >

                                    <span class="research-prompt-picker__plus">
                                        ✦
                                    </span>

                                    <span data-prompt-label>
                                        {{
                                            $activePrompt
                                                ?->display_name
                                            ?: 'Pilih research prompt'
                                        }}
                                    </span>

                                    <span class="research-prompt-picker__chevron">
                                        ⌄
                                    </span>

                                </button>


                                <input
                                    type="hidden"
                                    name="prompt_template_id"
                                    value="{{
                                        old(
                                            'prompt_template_id',
                                            $activePrompt?->id
                                        )
                                    }}"
                                    data-prompt-input
                                    required
                                >


                                @include(
                                    'research.workspace.prompt-menu',
                                    [
                                        'prompts' => $prompts,
                                        'activePrompt' => $activePrompt,
                                    ]
                                )

                            </div>

                        </div>


                        <div class="research-question-panel">

                            <div class="research-question-panel__head">

                                <strong>
                                    TULIS PERTANYAAN ANDA
                                </strong>

                                <span>
                                    ⓘ Gunakan bahasa yang spesifik dan fokus.
                                </span>

                            </div>


                            <div class="research-question-panel__input">

                                <textarea
                                    name="message"
                                    rows="3"
                                    placeholder="Tulis pertanyaan research Anda…"
                                    required
                                    maxlength="5000"
                                    data-chat-input
                                >{{ old('message') }}</textarea>


                                <button
                                    class="research-send"
                                    type="submit"
                                    aria-label="Jalankan research"
                                    data-motion-button
                                >

                                    <svg
                                        viewBox="0 0 20 20"
                                        aria-hidden="true"
                                    >
                                        <path
                                            d="
                                                M4 10h11
                                                M10 5l5 5-5 5
                                            "
                                        ></path>
                                    </svg>

                                </button>

                            </div>


                            <div class="research-question-panel__footer">

                                <span>
                                    Output AI bukan evidence akademik.
                                    Evidence tetap berasal dari source
                                    yang diretrieve.
                                </span>

                                <strong>
                                    5000 karakter maks.
                                </strong>

                            </div>

                        </div>

                    </form>

                </section>

            </div>

            @endif


        {{-- ============================================================
            ACTIVE RESEARCH RESULT
        ============================================================ --}}
        @else

            <div class="research-result-page">

                {{-- RESULT HEADING --}}
                <header class="research-result-header">

                    <div>

                        <span class="research-section-label">
                            RESEARCH RESULT
                        </span>

                        <h1>
                            {{ $researchQuestion }}
                        </h1>

                        <p>
                            @if($sourceCount > 0)

                                Menampilkan jawaban berdasarkan
                                {{ $sourceCount }}
                                sumber akademik yang relevan.

                            @else

                                Menampilkan hasil research berdasarkan
                                evidence yang tersedia.

                            @endif
                        </p>

                        @if($activePrompt)
                            <div class="research-result-context">
                                <span class="research-result-context__icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none">
                                        <circle cx="10" cy="10" r="5" />
                                        <path d="m14 14 5 5" />
                                    </svg>
                                </span>

                                <span>
                                    <small>ACTIVE RESEARCH TOOL</small>
                                    <strong>{{ $activePrompt->display_name }}</strong>
                                </span>
                            </div>
                        @endif

                    </div>


                    <div class="br-result-head-actions">
                        <span class="research-result-header__trust"><i></i> Evidence-aware</span>
                    </div>

                </header>

                @if(session('success'))
                    <div class="research-result-flash" role="status">
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="m5 12 4 4 10-10" />
                        </svg>
                        <span>{{ session('success') }}</span>
                    </div>
                @endif


                {{-- PROCESS --}}
                <section
                    class="
                        research-result-process
                        {{
                            $isProcessing
                                ? 'is-processing'
                                : 'is-complete'
                        }}
                    "
                    @if($latestRunId && $runStatusUrl)
                        data-research-run-status="{{ $runStatusUrl }}"
                        data-research-run-id="{{ $latestRunId }}"
                        data-research-run-state="{{ $latestStatus ?: 'queued' }}"
                    @endif
                >

                    <article>

                        <span class="research-process-check">
                            ✓
                        </span>

                        <div class="research-process-icon">

                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                            >
                                <path d="M5 3h10l4 4v14H5z" />
                                <path d="M15 3v5h5" />
                                <path d="M8 12h8M8 16h6" />
                            </svg>

                        </div>

                        <div>

                            <strong>
                                Retrieving sources
                            </strong>

                            <small>
                                Mencari sumber akademik yang relevan
                            </small>

                        </div>

                        <b>
                            →
                        </b>

                    </article>


                    <article>

                        <span class="research-process-check">
                            ✓
                        </span>

                        <div class="research-process-icon">

                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                            >
                                <circle
                                    cx="10"
                                    cy="10"
                                    r="5"
                                />

                                <path d="m14 14 6 6" />
                            </svg>

                        </div>

                        <div>

                            <strong>
                                Finding evidence
                            </strong>

                            <small>
                                Mengidentifikasi bukti utama
                            </small>

                        </div>

                        <b>
                            →
                        </b>

                    </article>


                    <article>

                        <span class="research-process-check">
                            ✓
                        </span>

                        <div class="research-process-icon">

                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                            >
                                <path d="M5 19V11" />
                                <path d="M12 19V5" />
                                <path d="M19 19V9" />
                            </svg>

                        </div>

                        <div>

                            <strong>
                                Comparing findings
                            </strong>

                            <small>
                                Membandingkan temuan antar sumber
                            </small>

                        </div>

                        <b>
                            →
                        </b>

                    </article>


                    <article>

                        <span class="research-process-check">
                            ✓
                        </span>

                        <div class="research-process-icon">

                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                            >
                                <path d="M5 3h10l4 4v14H5z" />
                                <path d="M15 3v5h5" />
                                <path d="M8 12h8M8 16h8" />
                            </svg>

                        </div>

                        <div>

                            <strong>
                                Preparing response
                            </strong>

                            <small>
                                Menyusun jawaban dengan sitasi
                            </small>

                        </div>

                    </article>

                    @if($isProcessing && $latestRunId)
                        <div class="research-live-progress" aria-live="polite">
                            <div
                                class="research-live-progress__bar"
                                data-research-live-progress
                                role="progressbar"
                                aria-valuemin="0"
                                aria-valuemax="100"
                                aria-valuenow="10"
                            ><i></i></div>
                            <span data-research-live-label>Research sedang diproses…</span>
                            <a
                                href="{{ request()->fullUrl() }}"
                                data-research-live-retry
                                hidden
                            >Cek ulang</a>
                        </div>
                    @endif

                </section>



                {{-- MAIN RESULT --}}
                <div class="research-result-layout">

                    <main class="research-answer-column">

                        @if(in_array($latestStatus, ['insufficient_evidence', 'citation_failed', 'failed'], true))
                            <section class="br-research-recovery" role="status" aria-live="polite">
                                <div class="br-research-recovery__icon" aria-hidden="true">?</div>
                                <div>
                                    <strong>{{ $latestStatus === 'failed' ? 'Research belum berhasil diproses' : 'Evidence belum cukup untuk menjawab' }}</strong>
                                    <p>{{ $latestStatus === 'failed' ? 'Ada kendala pemrosesan. Periksa koneksi atau coba ulang nanti.' : 'Library belum menyediakan bukti yang dapat dipertanggungjawabkan untuk pertanyaan ini. Kami tidak akan mengarang referensi.' }}</p>
                                    <span>Coba: persempit topik, tambahkan tahun atau konteks, atau ganti kata kunci.</span>
                                    <a href="{{ route('workspace') }}">Buat pencarian baru →</a>
                                </div>
                            </section>
                        @endif

                        <article class="research-answer-card">

                            <header class="research-answer-card__head">

                                <div>

                                    <span>
                                        ✦
                                    </span>

                                    <strong>
                                        JAWABAN DARI BACADULU
                                    </strong>

                                </div>

                                @if($latestAssistantMessage && $latestAssistantMessage->ai_run_id && $latestStatus === 'completed')
                                    <form
                                        method="POST"
                                        action="{{ route('saved.store') }}"
                                        class="research-answer-save-form"
                                        data-inline-save-form
                                    >
                                        @csrf
                                        <input type="hidden" name="ai_run_id" value="{{ $latestAssistantMessage->ai_run_id }}">
                                        <button type="submit" class="research-answer-save-inline" aria-label="Simpan jawaban ke Saved">
                                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                <path d="M7 4h10v16l-5-3-5 3z"/>
                                            </svg>
                                            <span data-inline-save-label>Save</span>
                                        </button>
                                    </form>
                                @endif

                            </header>


                            @if($latestAssistantMessage)

                                <div class="research-answer-text">

                                    {!! \Illuminate\Support\Str::markdown(
                                        (string) $latestAssistantMessage->content,
                                        ['html_input' => 'strip', 'allow_unsafe_links' => false]
                                    ) !!}

                                </div>

                            @else

                                <div class="research-answer-text">
                                    Research sedang diproses.
                                </div>

                            @endif






                        </article>



                        {{-- BENEFIT STRIP --}}
                        <div class="research-result-benefits">

                            <article>

                                <span class="is-teal">

                                    <svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                    >
                                        <path d="M5 3h10l4 4v14H5z" />
                                        <path d="M15 3v5h5" />
                                        <path d="M8 12h8M8 16h6" />
                                    </svg>

                                </span>

                                <div>

                                    <strong>
                                        Sumber terkurasi
                                    </strong>

                                    <small>
                                        Hanya dari source akademik
                                        yang relevan.
                                    </small>

                                </div>

                            </article>


                            <article>

                                <span class="is-blue">

                                    <svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                    >
                                        <path d="M12 3v18" />
                                        <path d="M5 6h14" />
                                        <path d="M7 6 4 13h6L7 6Z" />
                                        <path d="m17 6-3 7h6l-3-7Z" />
                                    </svg>

                                </span>

                                <div>

                                    <strong>
                                        Jawaban dengan bukti
                                    </strong>

                                    <small>
                                        Setiap claim dapat ditelusuri
                                        ke evidence.
                                    </small>

                                </div>

                            </article>


                            <article>

                                <span class="is-orange">

                                    <svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                    >
                                        <path d="m3 9 9-5 9 5-9 5-9-5Z" />
                                        <path d="M7 11v5c3 2 7 2 10 0v-5" />
                                    </svg>

                                </span>

                                <div>

                                    <strong>
                                        Siap menjadi karya ilmiah
                                    </strong>

                                    <small>
                                        Lanjutkan hasil menjadi dokumen
                                        akademik.
                                    </small>

                                </div>

                            </article>

                        </div>



                        {{-- DOCUMENT CTA --}}
                        @if(
                            $latestAssistantMessage
                            && $latestAssistantMessage->ai_run_id
                            && $latestStatus === 'completed'
                        )

                            <section class="research-document-cta">

                                <div class="research-document-mini">

                                    <span>
                                        Baca<span>Dulu</span>
                                    </span>

                                    <strong>
                                        {{
                                            Str::limit(
                                                $conversation->title,
                                                48
                                            )
                                        }}
                                    </strong>

                                    <i></i>
                                    <i></i>
                                    <i></i>

                                </div>


                                <div>

                                    <span>
                                        UBAH JAWABAN MENJADI DOKUMEN
                                    </span>

                                    <p>
                                        Lanjutkan hasil research menjadi
                                        dokumen akademik dengan template
                                        dan citation style.
                                    </p>

                                </div>


                                <a
                                    href="{{
                                        route(
                                            'documents.create',
                                            [
                                                'ai_run'
                                                    => $latestAssistantMessage
                                                        ->ai_run_id
                                            ]
                                        )
                                    }}"
                                >
                                    Generate Document
                                    <b>→</b>
                                </a>

                            </section>

                        @endif



                        {{-- PREVIOUS MESSAGES --}}
                        @if($olderMessages->isNotEmpty())

                            <details class="research-previous-messages">

                                <summary>
                                    Lihat percakapan sebelumnya
                                </summary>


                                <div>

                                    @foreach(
                                        $olderMessages
                                        as $message
                                    )

                                        <article>

                                            <strong>
                                                {{
                                                    $message->role === 'user'
                                                        ? 'Anda'
                                                        : 'BacaDulu Research'
                                                }}
                                            </strong>

                                            <p>
                                                {!! nl2br(
                                                    e(
                                                        $message->content
                                                    )
                                                ) !!}
                                            </p>

                                        </article>

                                    @endforeach

                                </div>

                            </details>

                        @endif

                    </main>



                    {{-- ==================================================
                        SOURCES RIGHT RAIL
                    ================================================== --}}
                    <aside class="research-result-sidebar">

                        <section class="research-supporting-sources">

                            <header>

                                <strong>
                                    SUMBER PENDUKUNG
                                    @if($sourceCount)
                                        ({{ $sourceCount }})
                                    @endif
                                </strong>

                                <span>
                                    Evidence-aware
                                </span>

                            </header>


                            @if(
                                is_array($latestSources)
                                && count($latestSources)
                            )

                                <div class="research-supporting-sources__list">

                                    @foreach(
                                        array_slice(
                                            $latestSources,
                                            0,
                                            8
                                        )
                                        as $index => $source
                                    )

                                        <article>

                                            <span class="research-source-file">

                                                <svg
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                >
                                                    <path d="M5 3h10l4 4v14H5z" />
                                                    <path d="M15 3v5h5" />
                                                    <path d="M8 12h8M8 16h6" />
                                                </svg>

                                            </span>


                                            <span class="research-source-number">
                                                {{
                                                    str_pad(
                                                        (string) (
                                                            $index + 1
                                                        ),
                                                        2,
                                                        '0',
                                                        STR_PAD_LEFT
                                                    )
                                                }}
                                            </span>


                                            <div>

                                                <strong>
                                                    {{
                                                        data_get(
                                                            $source,
                                                            'title',
                                                            'Curated Academic Source'
                                                        )
                                                    }}
                                                </strong>

                                                <small>

                                                    {{
                                                        data_get(
                                                            $source,
                                                            'author'
                                                        )
                                                        ?: data_get(
                                                            $source,
                                                            'source_label',
                                                            'Curated Source'
                                                        )
                                                    }}

                                                    @if(
                                                        data_get(
                                                            $source,
                                                            'year'
                                                        )
                                                    )
                                                        ·
                                                        {{
                                                            data_get(
                                                                $source,
                                                                'year'
                                                            )
                                                        }}
                                                    @endif

                                                </small>

                                            </div>


                                            <b>
                                                →
                                            </b>

                                        </article>

                                    @endforeach

                                </div>

                            @else

                                <p class="research-source-empty">
                                    Source akan muncul di sini
                                    setelah retrieval selesai.
                                </p>

                            @endif

                        </section>



                        @if(
                            is_array($latestEvidence)
                            && count($latestEvidence)
                        )

                            <section class="research-validated-citations">

                                <header>

                                    <strong>
                                        VALIDATED EVIDENCE
                                    </strong>

                                    <span>
                                        {{ $evidenceCount }} evidence
                                    </span>

                                </header>


                                @foreach(
                                    array_slice(
                                        $latestEvidence,
                                        0,
                                        8
                                    )
                                    as $index => $item
                                )

                                    <article>

                                        <div class="research-validation-head">

                                            <span>

                                                <svg
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                >
                                                    <path d="M5 3h10l4 4v14H5z" />
                                                    <path d="M15 3v5h5" />
                                                    <path d="M8 12h8M8 16h6" />
                                                </svg>

                                            </span>

                                            <div>

                                                <small>
                                                    [E{{
                                                        str_pad(
                                                            (string) (
                                                                $index + 1
                                                            ),
                                                            2,
                                                            '0',
                                                            STR_PAD_LEFT
                                                        )
                                                    }}]
                                                </small>

                                                <strong>
                                                    Evidence terkait hasil
                                                    research
                                                </strong>

                                            </div>

                                        </div>


                                        <blockquote>
                                            {{
                                                Str::limit(
                                                    is_array($item)
                                                        ? data_get(
                                                            $item,
                                                            'text'
                                                        )
                                                        : $item,
                                                    185
                                                )
                                            }}
                                        </blockquote>

                                    </article>

                                @endforeach


                                <p class="research-validation-note">
                                    ✦ Evidence terhubung kembali
                                    ke source akademik yang diretrieve.
                                </p>

                            </section>

                        @endif

                    </aside>

                </div>



                {{-- NEXT ACTION --}}
                @if($nextPrompt)

                    <section class="research-next-action">

                        <span>
                            NEXT ACTION
                        </span>

                        <p>
                            Lanjutkan research menggunakan tool
                            berikut untuk memperdalam analisis.
                        </p>


                        <a
                            href="{{
                                request()
                                    ->fullUrlWithQuery([
                                        'prompt'
                                            => $nextPrompt->id
                                    ])
                            }}"
                        >

                            <div>

                                <small>
                                    SUGGESTED RESEARCH TOOL
                                </small>

                                <strong>
                                    {{ $nextPrompt->display_name }}
                                </strong>

                                <p>
                                    {{
                                        $nextPrompt
                                            ->short_description
                                        ?: $nextPrompt
                                            ->aiTool
                                            ?->description
                                        ?: ''
                                    }}
                                </p>

                            </div>

                            <b>
                                →
                            </b>

                        </a>

                    </section>

                @endif



                {{-- ======================================================
                    FOLLOW-UP COMPOSER
                ====================================================== --}}
                <section class="research-followup-wrap">

                    @if($errors->any())

                        <div
                            class="
                                alert
                                alert-error
                                research-composer-alert
                            "
                            role="alert"
                        >
                            {{ $errors->first() }}
                        </div>

                    @endif


                    @if(session('success'))

                        <div
                            class="research-composer-success"
                            role="status"
                        >
                            {{ session('success') }}
                        </div>

                    @endif


                    <form
                        method="POST"
                        action="{{ route('research.run') }}"
                        class="
                            research-composer
                            research-composer--followup
                        "
                        data-chat-form
                    >

                        @csrf


                        <input
                            type="hidden"
                            name="conversation_id"
                            value="{{ $conversation->id }}"
                        >


                        @if($selectedSource)

                            <input
                                type="hidden"
                                name="source_id"
                                value="{{ $selectedSource->id }}"
                            >

                        @endif


                        <div class="research-composer__controls">

                            <label class="research-category-select">

                                <span>
                                    KATEGORI
                                </span>

                                <select
                                    name="category_id"
                                    required
                                    aria-label="Kategori research"
                                >

                                    <option value="">
                                        Pilih kategori
                                    </option>

                                    @foreach(
                                        $categories
                                        as $category
                                    )

                                        <option
                                            value="{{ $category->id }}"
                                            @selected(
                                                old(
                                                    'category_id',
                                                    $conversation
                                                        ?->category_id
                                                )
                                                == $category->id
                                            )
                                        >
                                            {{ $category->name }}
                                        </option>

                                    @endforeach

                                </select>

                            </label>


                            <div class="research-prompt-picker-wrap">

                                <span class="research-composer__label">
                                    RESEARCH TOOL / PROMPT
                                </span>

                                <button
                                    type="button"
                                    class="research-prompt-picker"
                                    data-prompt-open
                                    aria-expanded="false"
                                    aria-controls="research-prompt-menu"
                                >

                                    <span class="research-prompt-picker__plus">
                                        ✦
                                    </span>

                                    <span data-prompt-label>
                                        {{
                                            $activePrompt
                                                ?->display_name
                                            ?: 'Pilih research prompt'
                                        }}
                                    </span>

                                    <span class="research-prompt-picker__chevron">
                                        ⌄
                                    </span>

                                </button>


                                <input
                                    type="hidden"
                                    name="prompt_template_id"
                                    value="{{
                                        old(
                                            'prompt_template_id',
                                            $activePrompt?->id
                                        )
                                    }}"
                                    data-prompt-input
                                    required
                                >


                                @include(
                                    'research.workspace.prompt-menu',
                                    [
                                        'prompts' => $prompts,
                                        'activePrompt' => $activePrompt,
                                    ]
                                )

                            </div>

                        </div>


                        <div class="research-composer__input-row">

                            <textarea
                                name="message"
                                rows="1"
                                placeholder="Lanjutkan pertanyaan research Anda…"
                                required
                                maxlength="5000"
                                data-chat-input
                            >{{ old('message') }}</textarea>


                            <button
                                class="research-send"
                                type="submit"
                                aria-label="Jalankan research"
                                data-motion-button
                            >
                                <svg
                                    viewBox="0 0 20 20"
                                    aria-hidden="true"
                                >
                                    <path
                                        d="
                                            M4 10h11
                                            M10 5l5 5-5 5
                                        "
                                    ></path>
                                </svg>
                            </button>

                        </div>


                        <div class="research-composer__note">
                            Output AI bukan evidence akademik.
                            Evidence tetap berasal dari source
                            yang diretrieve.
                        </div>

                    </form>

                </section>

            </div>

        @endif

    </section>



    {{-- ================================================================
        PROMPT EXPLAINERS
    ================================================================ --}}
    @foreach($prompts as $prompt)

        <aside
            id="prompt-info-{{ $prompt->id }}"
            class="prompt-explainer"
            popover="auto"
            role="dialog"
            aria-modal="true"
            aria-label="Penjelasan Research Tool: {{ $prompt->name }}"
        >

            <header class="prompt-explainer__head">

                <div>

                    <span class="prompt-explainer__eyebrow">
                        RESEARCH TOOL
                    </span>

                    <h2>
                        {{ $prompt->name }}
                    </h2>


                    @if($prompt->indonesian_name)

                        <span class="prompt-explainer__indonesian">
                            {{ $prompt->indonesian_name }}
                        </span>

                    @endif

                </div>


                <button
                    type="button"
                    class="prompt-explainer__close"
                    popovertarget="prompt-info-{{ $prompt->id }}"
                    popovertargetaction="hide"
                    aria-label="Tutup penjelasan prompt"
                >
                    ×
                </button>

            </header>


            <p class="prompt-explainer__summary">

                {{
                    $prompt->short_description
                    ?: $prompt
                        ->aiTool
                        ?->description
                    ?: 'Penjelasan singkat belum tersedia.'
                }}

            </p>


            <div class="prompt-explainer__body">

                <section class="prompt-explainer__section">

                    <div class="prompt-explainer__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 5C9.7 3.4 7.2 3.6 4.5 4.5v14c2.9-1 5.4-1 7.5.5m0-14c2.3-1.6 4.8-1.4 7.5-.5v14c-2.9-1-5.4-1-7.5.5V5Z"/></svg></div>

                    <span>
                        APA MAKSUDNYA?
                    </span>

                    <p>
                        {{
                            $prompt->meaning
                            ?: 'Penjelasan belum diisi oleh Admin.'
                        }}
                    </p>

                </section>


                <section class="prompt-explainer__section">

                    <div class="prompt-explainer__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="7"/><circle cx="12" cy="12" r="3"/><path d="m12 12 8-8"/></svg></div>

                    <span>
                        DIGUNAKAN UNTUK
                    </span>

                    <p>
                        {{
                            $prompt->when_to_use
                            ?: 'Panduan penggunaan belum diisi oleh Admin.'
                        }}
                    </p>

                </section>


                <section class="prompt-explainer__section">

                    <div class="prompt-explainer__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 3 2.5 20h19L12 3Z"/><path d="M12 9v4m0 3h.01"/></svg></div>

                    <span>
                        JANGAN DIGUNAKAN UNTUK
                    </span>

                    <p>
                        {{
                            $prompt->when_not_to_use
                            ?: 'Batas penggunaan belum diisi oleh Admin.'
                        }}
                    </p>

                </section>


                <section class="prompt-explainer__section">

                    <div class="prompt-explainer__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6 3h9l4 4v14H6V3Z"/><path d="M14 3v5h5M9 12h6M9 16h6"/></svg></div>

                    <span>
                        EXPECTED OUTPUT
                    </span>

                    <p>
                        {{
                            $prompt->expected_output
                            ?: 'Struktur output belum diisi oleh Admin.'
                        }}
                    </p>

                </section>


                <section class="prompt-explainer__section">

                    <div class="prompt-explainer__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m12 3 2.4 6.6L21 12l-6.6 2.4L12 21l-2.4-6.6L3 12l6.6-2.4L12 3Z"/></svg></div>

                    <span>
                        CONTOH PROMPT
                    </span>

                    <p class="prompt-explainer__example">
                        {{
                            $prompt->example_text
                            ?: 'Contoh pertanyaan belum diisi oleh Admin.'
                        }}
                    </p>

                </section>

            </div>


            <footer class="prompt-explainer__footer">

                <span>
                    Panel menjelaskan fungsi tool sebelum Anda memilihnya.
                </span>

                <div class="prompt-explainer__actions">
                    <button
                        type="button"
                        class="prompt-explainer__back"
                        popovertarget="prompt-info-{{ $prompt->id }}"
                        popovertargetaction="hide"
                    >
                        Kembali
                    </button>

                    <a
                        href="{{
                            request()
                                ->fullUrlWithQuery([
                                    'prompt'
                                        => $prompt->id
                                ])
                        }}"
                        class="
                            btn
                            btn-primary
                            prompt-explainer__use
                        "
                    >
                        Gunakan Prompt
                        <b>→</b>
                    </a>
                </div>

            </footer>

        </aside>

    @endforeach

</div>

@endsection