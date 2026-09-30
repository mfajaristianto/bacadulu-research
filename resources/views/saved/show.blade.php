@extends('layouts.app')

@section(
    'title',
    $savedResult->title
    .' — Saved Result'
)

@section('content')

@php
    $run = $savedResult->aiRun;

    $conversation =
        $run
            ?->assistantMessage
            ?->conversation;

    $primaryResult =
        $run
            ?->results
            ?->firstWhere(
                'result_type',
                'primary'
            )
        ?: $run
            ?->results
            ?->first();

    $answer =
        $primaryResult
            ?->content
        ?: $run
            ?->assistantMessage
            ?->content
        ?: 'Belum ada output final pada AI Run ini.';

    $evidence =
        data_get(
            $primaryResult
                ?->structured_output_json,
            'evidence',
            data_get(
                $run
                    ?->assistantMessage
                    ?->metadata,
                'evidence',
                []
            )
        );

    $citations =
        $primaryResult
            ?->citations_json
        ?: data_get(
            $run
                ?->assistantMessage
                ?->metadata,
            'citations',
            []
        );
@endphp

<section class="saved-detail-page">

    <div class="saved-detail-breadcrumb">
        <a href="{{ route('saved.index') }}">
            ← Saved Results
        </a>
    </div>

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

    <header
        class="saved-detail-head"
        data-reveal
    >
        <div>
            <div class="saved-detail-kicker">

                @if(
                    $savedResult
                        ->is_favorite
                )
                    <span>
                        ★ FAVORITE
                    </span>
                @endif

                <span>
                    {{
                        $run
                            ?->aiTool
                            ?->name
                        ?: 'RESEARCH RESULT'
                    }}
                </span>

            </div>

            <h1 class="display-type">
                {{ $savedResult->title }}
            </h1>

            <p>
                Disimpan
                {{
                    $savedResult
                        ->created_at
                        ->format(
                            'd M Y · H:i'
                        )
                }}.
                Output tetap terhubung dengan
                AI Run dan source asalnya.
            </p>
        </div>

        <form
            method="POST"
            action="{{
                route(
                    'saved.favorite',
                    $savedResult
                )
            }}"
        >
            @csrf
            @method('PATCH')

            <button
                type="submit"
                class="
                    saved-detail-favorite
                    {{
                        $savedResult
                            ->is_favorite
                        ? 'is-active'
                        : ''
                    }}
                "
            >
                <span>
                    {{
                        $savedResult
                            ->is_favorite
                        ? '★'
                        : '☆'
                    }}
                </span>

                {{
                    $savedResult
                        ->is_favorite
                    ? 'Favorite'
                    : 'Add favorite'
                }}
            </button>
        </form>
    </header>

    <div class="saved-detail-layout">

        <main class="saved-detail-main">

            <section class="saved-answer">

                <div class="saved-section-label">
                    ANSWER
                </div>

                <div
                    class="
                        saved-answer__content
                    "
                >
                    {!! nl2br(
                        e($answer)
                    ) !!}
                </div>

            </section>

            @if(
                is_array($evidence)
                && count($evidence)
            )

                <section class="saved-evidence">

                    <div class="saved-section-label">
                        EVIDENCE
                    </div>

                    <div
                        class="
                            saved-evidence__items
                        "
                    >
                        @foreach(
                            $evidence
                            as $index => $item
                        )

                            <article
                                class="
                                    saved-evidence__item
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
                                        is_array(
                                            $item
                                        )
                                        ? (
                                            data_get(
                                                $item,
                                                'text'
                                            )
                                            ?: data_get(
                                                $item,
                                                'content'
                                            )
                                            ?: json_encode(
                                                $item,
                                                JSON_UNESCAPED_UNICODE
                                                | JSON_UNESCAPED_SLASHES
                                            )
                                        )
                                        : $item
                                    }}
                                </p>
                            </article>

                        @endforeach
                    </div>

                </section>

            @endif

            @if(
                $run
                    ?->sources
                    ?->isNotEmpty()
            )

                <section class="saved-sources">

                    <div class="saved-section-label">
                        SOURCES
                    </div>

                    <div
                        class="
                            saved-sources__list
                        "
                    >

                        @foreach(
                            $run->sources
                            as $index => $source
                        )

                            <article
                                class="
                                    saved-source
                                "
                            >
                                <span
                                    class="
                                        saved-source__index
                                    "
                                >
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
                                </span>

                                <div>
                                    <strong>
                                        {{ $source->title }}
                                    </strong>

                                    <p>
                                        {{
                                            $source
                                                ->source_label
                                            ?: 'Curated Source'
                                        }}

                                        @if(
                                            $source
                                                ->publication_year
                                        )
                                            ·
                                            {{
                                                $source
                                                    ->publication_year
                                            }}
                                        @endif

                                        @if(
                                            $source
                                                ->category
                                                ?->name
                                        )
                                            ·
                                            {{
                                                $source
                                                    ->category
                                                    ->name
                                            }}
                                        @endif
                                    </p>

                                    @if($source->doi)
                                        <small>
                                            DOI:
                                            {{ $source->doi }}
                                        </small>
                                    @endif
                                </div>

                            </article>

                        @endforeach

                    </div>

                </section>

            @endif

            @if(
                is_array($citations)
                && count($citations)
            )

                <section class="saved-citations">

                    <div class="saved-section-label">
                        CITATIONS
                    </div>

                    <div
                        class="
                            saved-citations__content
                        "
                    >
                        @foreach(
                            $citations
                            as $citation
                        )

                            <p>
                                {{
                                    is_array(
                                        $citation
                                    )
                                    ? (
                                        data_get(
                                            $citation,
                                            'text'
                                        )
                                        ?: data_get(
                                            $citation,
                                            'citation'
                                        )
                                        ?: json_encode(
                                            $citation,
                                            JSON_UNESCAPED_UNICODE
                                            | JSON_UNESCAPED_SLASHES
                                        )
                                    )
                                    : $citation
                                }}
                            </p>

                        @endforeach
                    </div>

                </section>

            @endif

        </main>

        <aside class="saved-detail-side">

            <section class="saved-info-card">

                <div class="saved-section-label">
                    RESEARCH CONTEXT
                </div>

                <dl>

                    <div>
                        <dt>
                            Status
                        </dt>

                        <dd>
                            {{
                                strtoupper(
                                    $run?->status
                                    ?: 'UNKNOWN'
                                )
                            }}
                        </dd>
                    </div>

                    <div>
                        <dt>
                            Research Tool
                        </dt>

                        <dd>
                            {{
                                $run
                                    ?->aiTool
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
                                    ?->promptTemplate
                                    ?->name
                                ?: '—'
                            }}
                        </dd>
                    </div>

                    <div>
                        <dt>
                            Model
                        </dt>

                        <dd>
                            {{
                                $run?->model_name
                                ?: '—'
                            }}
                        </dd>
                    </div>

                    <div>
                        <dt>
                            Sources
                        </dt>

                        <dd>
                            {{
                                $run
                                    ?->sources
                                    ?->count()
                                ?? 0
                            }}
                        </dd>
                    </div>

                </dl>

                @if($conversation)

                    <a
                        href="{{
                            route(
                                'workspace.conversation',
                                $conversation
                            )
                        }}"
                        class="
                            btn
                            btn-secondary
                            btn-full
                        "
                    >
                        Buka Conversation
                    </a>

                @endif

            </section>

            <section class="saved-edit-card">

                <div class="saved-section-label">
                    SAVED NOTE
                </div>

                <form
                    method="POST"
                    action="{{
                        route(
                            'saved.update',
                            $savedResult
                        )
                    }}"
                >
                    @csrf
                    @method('PATCH')

                    <label>
                        Judul

                        <input
                            type="text"
                            name="title"
                            value="{{
                                old(
                                    'title',
                                    $savedResult
                                        ->title
                                )
                            }}"
                            maxlength="255"
                            required
                        >
                    </label>

                    <label>
                        Catatan
                        <span
                            class="
                                field-optional
                            "
                        >
                            Opsional
                        </span>

                        <textarea
                            name="notes"
                            rows="6"
                            maxlength="4000"
                            placeholder="Kenapa hasil ini penting? Apa langkah berikutnya?"
                        >{{ old(
                            'notes',
                            $savedResult->notes
                        ) }}</textarea>
                    </label>

                    <button
                        type="submit"
                        class="
                            btn
                            btn-primary
                            btn-full
                        "
                    >
                        Simpan Catatan
                    </button>
                </form>

            </section>

            <section
                class="
                    saved-delete-card
                "
            >

                <strong>
                    Hapus dari Saved Results
                </strong>

                <p>
                    Conversation dan AI Run
                    tidak ikut dihapus.
                </p>

                <form
                    method="POST"
                    action="{{
                        route(
                            'saved.destroy',
                            $savedResult
                        )
                    }}"
                    onsubmit="
                        return confirm(
                            'Hapus hasil ini dari Saved Results?'
                        );
                    "
                >
                    @csrf
                    @method('DELETE')

                    <button
                        type="submit"
                    >
                        Hapus Saved Result
                    </button>
                </form>

            </section>

        </aside>

    </div>

</section>

@endsection