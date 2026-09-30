@extends('layouts.app')

@section(
    'title',
    'Research Workspace — BacaDulu Research'
)

@section('content')

@php
    $lastUserMessage = $conversation
        ?->messages
        ?->where('role', 'user')
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
@endphp

<div class="research-workspace-shell">

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

    <section class="research-canvas">

        <header class="research-canvas__topbar">

            <button
                type="button"
                class="research-sidebar__open"
                data-chat-sidebar-open
                aria-label="Buka riwayat research"
                aria-expanded="false"
            >
                <span></span>
                <span></span>
                <span></span>
            </button>

            <div class="research-canvas__title">
                <span>
                    {{
                        $conversation
                            ? 'ACTIVE RESEARCH'
                            : 'NEW RESEARCH'
                    }}
                </span>

                <h1>
                    {{
                        $conversation?->title
                            ?: 'Apa yang ingin Anda teliti?'
                    }}
                </h1>
            </div>

            <div class="research-canvas__trust">
                <i></i>
                Evidence-aware
            </div>

        </header>

        @if($selectedSource)

            <div
                class="research-source-context"
                role="status"
            >
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
                    context retrieval. File internal
                    penuh tidak dibuka di workspace user.
                </p>
            </div>

        @endif

        <div
            class="
                research-active-tool
                {{
                    $activePrompt
                        ? 'is-visible'
                        : ''
                }}
            "
            data-active-prompt
            @unless($activePrompt)
                hidden
            @endunless
        >
            <div class="research-active-tool__mark">
                T
            </div>

            <div class="research-active-tool__copy">
                <span>
                    ACTIVE RESEARCH TOOL
                </span>

                <strong data-active-prompt-name>
                    @if($activePrompt)
                        {{ $activePrompt->display_name }}
                    @endif
                </strong>

                <p data-active-prompt-description>
                    {{
                        $activePrompt
                            ?->short_description
                        ?: $activePrompt
                            ?->aiTool
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

        <div
            class="research-thread"
            data-chat-scroll
            aria-live="polite"
        >

            @if(!$conversation)

                <div
                    class="research-empty"
                    data-reveal
                    data-reveal-from="down"
                >
                    <div class="research-empty__index">
                        01
                    </div>

                    <p class="research-empty__kicker">
                        RESEARCH, NOT CHAT
                    </p>

                    <h2>
                        Mulai dari pertanyaan.
                        <br>

                        <span class="display-type">
                            Bangun dari evidence.
                        </span>
                    </h2>

                    <p>
                        Pilih Research Tool,
                        pahami kegunaannya,
                        lalu tulis pertanyaan Anda.
                        BacaDulu menggunakan source
                        yang eligible untuk membantu
                        proses research.
                    </p>

                    <div class="research-empty__guide">

                        <div>
                            <span>01</span>

                            <strong>
                                Pilih Research Tool
                            </strong>

                            <small>
                                Buka “Apa ini?” jika
                                istilah belum familiar.
                            </small>
                        </div>

                        <div>
                            <span>02</span>

                            <strong>
                                Tulis pertanyaan
                            </strong>

                            <small>
                                Berikan fokus topik,
                                objek, atau hubungan
                                yang ingin dianalisis.
                            </small>
                        </div>

                        <div>
                            <span>03</span>

                            <strong>
                                Periksa evidence
                            </strong>

                            <small>
                                Nilai jawaban bersama
                                source context, bukan
                                output AI saja.
                            </small>
                        </div>

                    </div>
                </div>

            @else

                <div class="research-thread__inner">

                    @foreach(
                        $conversation->messages
                        as $message
                    )

                        <article
                            class="
                                research-message
                                {{
                                    $message->role
                                        === 'user'
                                    ? 'is-user'
                                    : 'is-assistant'
                                }}
                            "
                        >

                            <div
                                class="
                                    research-message__identity
                                "
                            >

                                @if(
                                    $message->role
                                        === 'user'
                                )

                                    <span
                                        class="
                                            research-message__avatar
                                            research-message__avatar--user
                                        "
                                    >
                                        {{
                                            auth()
                                                ->user()
                                                ->initials
                                        }}
                                    </span>

                                @else

                                    <span
                                        class="
                                            research-message__avatar
                                        "
                                    >
                                        BR
                                    </span>

                                @endif

                            </div>

                            <div
                                class="
                                    research-message__body
                                "
                            >

                                <div
                                    class="
                                        research-message__meta
                                    "
                                >
                                    <strong>
                                        {{
                                            $message->role
                                                === 'user'
                                            ? 'Anda'
                                            : 'BacaDulu Research'
                                        }}
                                    </strong>

                                    <span>
                                        {{
                                            $message
                                                ->created_at
                                                ?->format('H:i')
                                        }}
                                    </span>
                                </div>

                                <div
                                    class="
                                        research-message__content
                                    "
                                >
                                    {!! nl2br(
                                        e(
                                            $message->content
                                        )
                                    ) !!}
                                </div>

                                @if(
                                    $message->role
                                        === 'assistant'
                                    && data_get(
                                        $message->metadata,
                                        'status'
                                    ) === 'queued'
                                )

                                    <div
                                        class="research-process"
                                        role="status"
                                    >

                                        <div
                                            class="
                                                research-process__head
                                            "
                                        >
                                            <span>
                                                AI PROCESS
                                            </span>

                                            <strong>
                                                Request diterima
                                            </strong>
                                        </div>

                                        <ol>
                                            <li class="is-active">
                                                <i></i>

                                                <span>
                                                    Menyiapkan
                                                    retrieval source
                                                </span>
                                            </li>

                                            <li>
                                                <i></i>

                                                <span>
                                                    Mencari evidence
                                                    relevan
                                                </span>
                                            </li>

                                            <li>
                                                <i></i>

                                                <span>
                                                    Membandingkan
                                                    temuan
                                                </span>
                                            </li>

                                            <li>
                                                <i></i>

                                                <span>
                                                    Menyiapkan
                                                    response
                                                </span>
                                            </li>
                                        </ol>

                                    </div>

                                @endif

                                @php
                                    $evidence = data_get(
                                        $message->metadata,
                                        'evidence',
                                        []
                                    );

                                    $sources = data_get(
                                        $message->metadata,
                                        'sources',
                                        []
                                    );
                                @endphp

                                @if(
                                    $message->role
                                        === 'assistant'
                                    && is_array($evidence)
                                    && count($evidence)
                                )

                                    <div
                                        class="
                                            research-evidence-block
                                        "
                                    >
                                        <div
                                            class="
                                                research-evidence-block__label
                                            "
                                        >
                                            EVIDENCE
                                        </div>

                                        <div
                                            class="
                                                research-evidence-block__items
                                            "
                                        >

                                            @foreach(
                                                $evidence
                                                as $index => $item
                                            )

                                                <div
                                                    class="
                                                        research-evidence-item
                                                    "
                                                >
                                                    <span>
                                                        [
                                                        {{
                                                            str_pad(
                                                                (string) (
                                                                    $index
                                                                    + 1
                                                                ),
                                                                2,
                                                                '0',
                                                                STR_PAD_LEFT
                                                            )
                                                        }}
                                                        ]
                                                    </span>

                                                    <p>
                                                        {{
                                                            is_array($item)
                                                                ? data_get(
                                                                    $item,
                                                                    'text'
                                                                )
                                                                : $item
                                                        }}
                                                    </p>
                                                </div>

                                            @endforeach

                                        </div>
                                    </div>

                                @endif

                                @if(
                                    $message->role
                                        === 'assistant'
                                    && is_array($sources)
                                    && count($sources)
                                )

                                    <div
                                        class="
                                            research-source-list
                                        "
                                    >

                                        <div
                                            class="
                                                research-evidence-block__label
                                            "
                                        >
                                            SOURCES
                                        </div>

                                        @foreach(
                                            $sources
                                            as $source
                                        )

                                            <div
                                                class="
                                                    research-source-row
                                                "
                                            >
                                                <span>
                                                    S
                                                </span>

                                                <div>
                                                    <strong>
                                                        {{
                                                            data_get(
                                                                $source,
                                                                'title',
                                                                'Curated Source'
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
                                                                'Internal Source'
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
                                            </div>

                                        @endforeach

                                    </div>

                                @endif

                                @if(
                                    $message->role
                                        === 'assistant'
                                    && $message->ai_run_id
                                )

                                    <div
                                        class="
                                            research-message__actions
                                        "
                                    >
                                        <form
                                            method="POST"
                                            action="{{
                                                route(
                                                    'saved.store'
                                                )
                                            }}"
                                        >
                                            @csrf

                                            <input
                                                type="hidden"
                                                name="ai_run_id"
                                                value="{{
                                                    $message
                                                        ->ai_run_id
                                                }}"
                                            >

                                            <input
                                                type="hidden"
                                                name="title"
                                                value="{{
                                                    $conversation
                                                        ->title
                                                }}"
                                            >

                                            <button
                                                type="submit"
                                                class="
                                                    research-save-result
                                                "
                                            >
                                                <span
                                                    aria-hidden="true"
                                                >
                                                    ☆
                                                </span>

                                                Simpan hasil
                                            </button>
                                        </form>
                                    </div>

                                @endif

                            </div>
                        </article>

                    @endforeach

                </div>

            @endif

        </div>

        <div class="research-composer-wrap">

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
                    class="
                        research-composer-success
                    "
                    role="status"
                >
                    {{ session('success') }}
                </div>

            @endif

            <form
                method="POST"
                action="{{ route('research.run') }}"
                class="research-composer"
                data-chat-form
            >
                @csrf

                @if($conversation)
                    <input
                        type="hidden"
                        name="conversation_id"
                        value="{{ $conversation->id }}"
                    >
                @endif

                @if($selectedSource)
                    <input
                        type="hidden"
                        name="source_id"
                        value="{{ $selectedSource->id }}"
                    >
                @endif

                <div
                    class="
                        research-composer__controls
                    "
                >

                    <label
                        class="
                            research-category-select
                        "
                    >
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
                                            $selectedSource
                                                ?->category_id
                                            ?: $conversation
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

                    <div
                        class="
                            research-prompt-picker-wrap
                        "
                    >
                        <span
                            class="
                                research-composer__label
                            "
                        >
                            RESEARCH TOOL / PROMPT
                        </span>

                        <button
                            type="button"
                            class="
                                research-prompt-picker
                            "
                            data-prompt-open
                            aria-expanded="false"
                            aria-controls="
                                research-prompt-menu
                            "
                        >
                            <span
                                class="
                                    research-prompt-picker__plus
                                "
                            >
                                +
                            </span>

                            <span data-prompt-label>
                                {{
                                    $activePrompt
                                        ?->display_name
                                    ?: 'Pilih research prompt'
                                }}
                            </span>

                            <span
                                class="
                                    research-prompt-picker__chevron
                                "
                            >
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

                        <div
                            class="
                                research-prompt-menu
                            "
                            id="research-prompt-menu"
                            data-prompt-menu
                            role="listbox"
                            aria-label="Research prompts"
                            hidden
                        >

                            <div
                                class="
                                    research-prompt-menu__head
                                "
                            >
                                <div>
                                    <span>
                                        RESEARCH TOOLS
                                    </span>

                                    <strong>
                                        Pilih cara analisis
                                    </strong>
                                </div>

                                <button
                                    type="button"
                                    data-prompt-close
                                    aria-label="
                                        Tutup pilihan prompt
                                    "
                                >
                                    ×
                                </button>
                            </div>

                            @forelse(
                                $prompts
                                as $prompt
                            )

                                <div
                                    class="
                                        research-prompt-option-row
                                    "
                                >
                                    <button
                                        type="button"
                                        class="
                                            research-prompt-option
                                        "
                                        role="option"
                                        aria-selected="{{
                                            $activePrompt?->id
                                                === $prompt->id
                                            ? 'true'
                                            : 'false'
                                        }}"
                                        data-prompt-id="{{
                                            $prompt->id
                                        }}"
                                        data-prompt-name="{{
                                            $prompt->display_name
                                        }}"
                                        data-prompt-tool="{{
                                            $prompt
                                                ->aiTool
                                                ?->name
                                            ?: 'Research Tool'
                                        }}"
                                        data-prompt-description="{{
                                            $prompt
                                                ->short_description
                                            ?: $prompt
                                                ->aiTool
                                                ?->description
                                            ?: 'Research prompt'
                                        }}"
                                    >

                                        <span
                                            class="
                                                research-prompt-option__index
                                            "
                                        >
                                            {{
                                                str_pad(
                                                    (string)
                                                        $loop
                                                            ->iteration,
                                                    2,
                                                    '0',
                                                    STR_PAD_LEFT
                                                )
                                            }}
                                        </span>

                                        <span
                                            class="
                                                research-prompt-option__copy
                                            "
                                        >

                                            <small>
                                                {{
                                                    $prompt
                                                        ->aiTool
                                                        ?->name
                                                    ?: 'Research Tool'
                                                }}
                                            </small>

                                            <strong>
                                                {{ $prompt->name }}
                                            </strong>

                                            @if(
                                                $prompt
                                                    ->indonesian_name
                                            )
                                                <span
                                                    class="
                                                        research-prompt-option__translation
                                                    "
                                                >
                                                    {{
                                                        $prompt
                                                            ->indonesian_name
                                                    }}
                                                </span>
                                            @endif

                                            <p>
                                                {{
                                                    $prompt
                                                        ->short_description
                                                    ?: $prompt
                                                        ->aiTool
                                                        ?->description
                                                    ?: 'Penjelasan prompt belum tersedia.'
                                                }}
                                            </p>

                                        </span>

                                        <span
                                            class="
                                                research-prompt-option__arrow
                                            "
                                        >
                                            →
                                        </span>

                                    </button>

                                    <button
                                        type="button"
                                        class="
                                            research-prompt-info-button
                                        "
                                        popovertarget="prompt-info-{{
                                            $prompt->id
                                        }}"
                                        popovertargetaction="show"
                                    >
                                        ⓘ Apa ini?
                                    </button>
                                </div>

                            @empty

                                <div
                                    class="
                                        research-sidebar__empty
                                    "
                                >
                                    Belum ada prompt aktif
                                    yang tersedia.
                                </div>

                            @endforelse

                        </div>
                    </div>
                </div>

                <div
                    class="
                        research-composer__input-row
                    "
                >

                    <textarea
                        name="message"
                        rows="1"
                        placeholder="
                            Tulis pertanyaan research Anda…
                        "
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

                <div
                    class="
                        research-composer__note
                    "
                >
                    Output AI bukan evidence akademik.
                    Evidence tetap berasal dari source
                    yang diretrieve.
                </div>

            </form>
        </div>

    </section>

    @foreach(
        $prompts
        as $prompt
    )

        <aside
            id="prompt-info-{{ $prompt->id }}"
            class="prompt-explainer"
            popover
        >

            <header
                class="
                    prompt-explainer__head
                "
            >
                <div>
                    <span
                        class="
                            prompt-explainer__eyebrow
                        "
                    >
                        RESEARCH TOOL
                    </span>

                    <h2>
                        {{ $prompt->name }}
                    </h2>

                    @if(
                        $prompt->indonesian_name
                    )
                        <span
                            class="
                                prompt-explainer__indonesian
                            "
                        >
                            {{
                                $prompt
                                    ->indonesian_name
                            }}
                        </span>
                    @endif
                </div>

                <button
                    type="button"
                    class="
                        prompt-explainer__close
                    "
                    popovertarget="prompt-info-{{
                        $prompt->id
                    }}"
                    popovertargetaction="hide"
                    aria-label="Tutup penjelasan prompt"
                >
                    ×
                </button>
            </header>

            <p
                class="
                    prompt-explainer__summary
                "
            >
                {{
                    $prompt->short_description
                    ?: $prompt
                        ->aiTool
                        ?->description
                    ?: 'Penjelasan singkat belum diisi oleh Admin.'
                }}
            </p>

            <div
                class="
                    prompt-explainer__body
                "
            >

                <section
                    class="
                        prompt-explainer__section
                    "
                >
                    <span>
                        Apa maksudnya?
                    </span>

                    <p
                        class="{{
                            filled($prompt->meaning)
                                ? ''
                                : 'prompt-explainer__empty'
                        }}"
                    >
                        {{
                            $prompt->meaning
                            ?: 'Penjelasan belum diisi oleh Admin.'
                        }}
                    </p>
                </section>

                <section
                    class="
                        prompt-explainer__section
                    "
                >
                    <span>
                        Digunakan untuk
                    </span>

                    <p
                        class="{{
                            filled($prompt->when_to_use)
                                ? ''
                                : 'prompt-explainer__empty'
                        }}"
                    >
                        {{
                            $prompt->when_to_use
                            ?: 'Panduan penggunaan belum diisi oleh Admin.'
                        }}
                    </p>
                </section>

                <section
                    class="
                        prompt-explainer__section
                    "
                >
                    <span>
                        Jangan digunakan untuk
                    </span>

                    <p
                        class="{{
                            filled($prompt->when_not_to_use)
                                ? ''
                                : 'prompt-explainer__empty'
                        }}"
                    >
                        {{
                            $prompt->when_not_to_use
                            ?: 'Batas penggunaan belum diisi oleh Admin.'
                        }}
                    </p>
                </section>

                <section
                    class="
                        prompt-explainer__section
                    "
                >
                    <span>
                        Contoh
                    </span>

                    <p
                        class="
                            prompt-explainer__example
                            {{
                                filled($prompt->example_text)
                                    ? ''
                                    : 'prompt-explainer__empty'
                            }}
                        "
                    >
                        {{
                            $prompt->example_text
                            ?: 'Contoh pertanyaan belum diisi oleh Admin.'
                        }}
                    </p>
                </section>

                <section
                    class="
                        prompt-explainer__section
                    "
                >
                    <span>
                        Expected Output
                    </span>

                    <p
                        class="
                            prompt-explainer__expected
                            {{
                                filled($prompt->expected_output)
                                    ? ''
                                    : 'prompt-explainer__empty'
                            }}
                        "
                    >
                        {{
                            $prompt->expected_output
                            ?: 'Struktur output belum diisi oleh Admin.'
                        }}
                    </p>
                </section>

            </div>

            <footer
                class="
                    prompt-explainer__footer
                "
            >
                <p>
                    Penjelasan ini bersifat user-facing.
                    Internal system prompt tidak ditampilkan.
                </p>

                <a
                    href="{{
                        request()
                            ->fullUrlWithQuery([
                                'prompt'
                                    => $prompt->id,
                            ])
                    }}"
                    class="
                        btn
                        btn-primary
                        prompt-explainer__use
                    "
                >
                    Gunakan Prompt
                    <span>→</span>
                </a>
            </footer>

        </aside>

    @endforeach

</div>

@endsection