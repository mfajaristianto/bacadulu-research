@extends('layouts.admin')

@section(
    'title',
    'AI Run #'.$run->id.' — Admin'
)

@section('content')

@php
    $validCitations =
        $run
            ->citations
            ->where(
                'is_valid',
                true
            );

    $invalidCitations =
        $run
            ->citations
            ->where(
                'is_valid',
                false
            );

    $primaryResult =
        $run
            ->results
            ->firstWhere(
                'result_type',
                'primary'
            );
@endphp

<section class="admin-page">

    <div
        class="admin-hero compact"
        data-admin-reveal
    >
        <div>
            <p class="eyebrow">
                ADMIN / AI RUN AUDIT
            </p>

            <h1>
                Run #{{ $run->id }}
            </h1>

            <p class="admin-run-uuid">
                {{ $run->uuid }}
            </p>
        </div>

        <div class="admin-hero-actions">

            <a
                href="{{
                    route(
                        'admin.ai-runs.index'
                    )
                }}"
                class="btn btn-secondary"
            >
                ← AI Runs
            </a>

        </div>
    </div>

    <div class="admin-run-summary">

        <article class="admin-stat-card">

            <span>
                STATUS
            </span>

            <strong class="admin-stat-status">
                {{
                    str(
                        $run->status
                    )
                        ->replace(
                            '_',
                            ' '
                        )
                        ->title()
                }}
            </strong>

            <small>
                current execution state
            </small>

        </article>

        <article class="admin-stat-card">

            <span>
                RETRIEVAL
            </span>

            <strong>
                {{
                    $run
                        ->retrievals
                        ->count()
                }}
            </strong>

            <small>
                evidence chunks selected
            </small>

        </article>

        <article class="admin-stat-card">

            <span>
                SOURCES
            </span>

            <strong>
                {{
                    $run
                        ->sources
                        ->count()
                }}
            </strong>

            <small>
                documents contributing evidence
            </small>

        </article>

        <article
            class="
                admin-stat-card
                {{
                    $invalidCitations
                        ->isEmpty()
                    ? 'admin-stat-accent'
                    : ''
                }}
            "
        >

            <span>
                CITATIONS
            </span>

            <strong>
                {{
                    $validCitations
                        ->count()
                }}
            </strong>

            <small>
                {{
                    $invalidCitations
                        ->count()
                }}
                invalid citation
            </small>

        </article>

    </div>

    <div class="admin-run-grid">

        <section
            class="admin-panel-card"
            data-admin-reveal
        >

            <div class="admin-panel-head">
                <div>
                    <span class="admin-kicker">
                        EXECUTION
                    </span>

                    <h2>
                        Run metadata
                    </h2>
                </div>
            </div>

            <dl class="admin-audit-list">

                <div>
                    <dt>User</dt>

                    <dd>
                        {{
                            $run
                                ->user
                                ?->name
                            ?: '—'
                        }}

                        <small>
                            {{
                                $run
                                    ->user
                                    ?->email
                                ?: ''
                            }}
                        </small>
                    </dd>
                </div>

                <div>
                    <dt>Conversation</dt>

                    <dd>
                        {{
                            $conversation
                                ?->title
                            ?: '—'
                        }}
                    </dd>
                </div>

                <div>
                    <dt>Research Tool</dt>

                    <dd>
                        {{
                            $run
                                ->aiTool
                                ?->name
                            ?: '—'
                        }}

                        <small>
                            {{
                                $run
                                    ->aiTool
                                    ?->code
                                ?: ''
                            }}
                        </small>
                    </dd>
                </div>

                <div>
                    <dt>Prompt</dt>

                    <dd>
                        {{
                            $run
                                ->promptTemplate
                                ?->name
                            ?: '—'
                        }}

                        <small>
                            Version
                            {{
                                $run
                                    ->promptTemplate
                                    ?->version
                                ?: '—'
                            }}
                        </small>
                    </dd>
                </div>

                <div>
                    <dt>Provider</dt>

                    <dd>
                        {{
                            $run
                                ->model_provider
                            ?: '—'
                        }}

                        <small>
                            {{
                                $run
                                    ->model_name
                                ?: 'Model not recorded'
                            }}
                        </small>
                    </dd>
                </div>

                <div>
                    <dt>Started</dt>

                    <dd>
                        {{
                            $run
                                ->started_at
                                ?->format(
                                    'd M Y · H:i:s'
                                )
                            ?: '—'
                        }}
                    </dd>
                </div>

                <div>
                    <dt>Finished</dt>

                    <dd>
                        {{
                            $run
                                ->finished_at
                                ?->format(
                                    'd M Y · H:i:s'
                                )
                            ?: '—'
                        }}
                    </dd>
                </div>

                <div>
                    <dt>Duration</dt>

                    <dd>
                        @if(
                            $durationMs
                            !== null
                        )
                            {{
                                number_format(
                                    $durationMs
                                    / 1000,
                                    2
                                )
                            }}
                            seconds
                        @else
                            —
                        @endif
                    </dd>
                </div>

            </dl>

        </section>

        <section
            class="admin-panel-card"
            data-admin-reveal
        >

            <div class="admin-panel-head">
                <div>
                    <span class="admin-kicker">
                        REQUEST
                    </span>

                    <h2>
                        User parameters
                    </h2>
                </div>
            </div>

            <div class="admin-run-question">

                <span>
                    USER REQUEST
                </span>

                <p>
                    {{
                        data_get(
                            $parameters,
                            'user_message',
                            '—'
                        )
                    }}
                </p>

            </div>

            <dl class="admin-audit-list compact">

                <div>
                    <dt>
                        Category
                    </dt>

                    <dd>
                        {{
                            data_get(
                                $parameters,
                                'category',
                                '—'
                            )
                        }}
                    </dd>
                </div>

                <div>
                    <dt>
                        Candidate sources
                    </dt>

                    <dd>
                        {{
                            collect(
                                data_get(
                                    $parameters,
                                    'candidate_document_ids',
                                    data_get(
                                        $parameters,
                                        'document_ids',
                                        []
                                    )
                                )
                            )->count()
                        }}
                    </dd>
                </div>

                <div>
                    <dt>
                        Retrieved sources
                    </dt>

                    <dd>
                        {{
                            collect(
                                data_get(
                                    $parameters,
                                    'retrieved_document_ids',
                                    []
                                )
                            )->count()
                        }}
                    </dd>
                </div>

                <div>
                    <dt>
                        Retrieved chunks
                    </dt>

                    <dd>
                        {{
                            collect(
                                data_get(
                                    $parameters,
                                    'retrieved_chunk_ids',
                                    []
                                )
                            )->count()
                        }}
                    </dd>
                </div>

            </dl>

        </section>

    </div>

    @if($run->error_message)

        <section
            class="
                admin-panel-card
                admin-run-error
            "
            data-admin-reveal
        >
            <span class="admin-kicker">
                ERROR
            </span>

            <h2>
                Execution error
            </h2>

            <pre>{{
                $run->error_message
            }}</pre>
        </section>

    @endif

    <section
        class="admin-panel-card"
        data-admin-reveal
    >

        <div class="admin-panel-head">
            <div>
                <span class="admin-kicker">
                    RETRIEVAL
                </span>

                <h2>
                    Retrieved evidence
                </h2>
            </div>

            <span class="admin-panel-count">
                {{
                    $run
                        ->retrievals
                        ->count()
                }}
                chunks
            </span>
        </div>

        <div class="admin-evidence-list">

            @forelse(
                $run
                    ->retrievals
                as $retrieval
            )

                <article class="admin-evidence-item">

                    <div class="admin-evidence-rank">

                        <strong>
                            {{
                                $retrieval
                                    ->citation_key
                            }}
                        </strong>

                        <small>
                            #{{ $retrieval->rank_no }}
                        </small>

                    </div>

                    <div class="admin-evidence-copy">

                        <div class="admin-evidence-meta">

                            <strong>
                                {{
                                    $retrieval
                                        ->document
                                        ?->title
                                    ?: 'Unknown Source'
                                }}
                            </strong>

                            <span>
                                score
                                {{
                                    number_format(
                                        (float)
                                            $retrieval
                                                ->score,
                                        4
                                    )
                                }}
                            </span>

                            <span>
                                {{
                                    $retrieval
                                        ->retrieval_type
                                }}
                            </span>

                        </div>

                        <p>
                            {{
                                $retrieval
                                    ->excerpt
                            }}
                        </p>

                        <small>
                            Document ID:
                            {{
                                $retrieval
                                    ->document_id
                            }}

                            · Chunk ID:
                            {{
                                $retrieval
                                    ->document_chunk_id
                            }}
                        </small>

                    </div>

                </article>

            @empty

                <div class="admin-empty">
                    Run ini tidak memiliki retrieval record.
                </div>

            @endforelse

        </div>

    </section>

    <section
        class="admin-panel-card"
        data-admin-reveal
    >

        <div class="admin-panel-head">

            <div>
                <span class="admin-kicker">
                    CITATION VALIDATION
                </span>

                <h2>
                    Citation audit
                </h2>
            </div>

            <span class="admin-panel-count">
                {{
                    $validCitations
                        ->count()
                }}
                valid
                /
                {{
                    $invalidCitations
                        ->count()
                }}
                invalid
            </span>

        </div>

        <div class="admin-citation-audit">

            @forelse(
                $run
                    ->citations
                as $citation
            )

                <article
                    class="
                        admin-citation-audit__item
                        {{
                            $citation
                                ->is_valid
                            ? 'is-valid'
                            : 'is-invalid'
                        }}
                    "
                >

                    <div class="admin-citation-audit__head">

                        <strong>
                            [{{
                                $citation
                                    ->citation_key
                            }}]
                        </strong>

                        <span>
                            {{
                                $citation
                                    ->is_valid
                                ? 'VALID'
                                : 'INVALID'
                            }}
                        </span>

                    </div>

                    @if(
                        $citation
                            ->claim_label
                    )

                        <h3>
                            {{
                                $citation
                                    ->claim_label
                            }}
                        </h3>

                    @endif

                    @if(
                        $citation
                            ->quote_snippet
                    )

                        <blockquote>
                            “{{
                                $citation
                                    ->quote_snippet
                            }}”
                        </blockquote>

                    @endif

                    <div class="admin-citation-audit__meta">

                        <span>
                            {{
                                $citation
                                    ->document
                                    ?->title
                                ?: 'No source'
                            }}
                        </span>

                        @if(
                            $citation
                                ->validation_message
                        )
                            <span>
                                {{
                                    $citation
                                        ->validation_message
                                }}
                            </span>
                        @endif

                    </div>

                </article>

            @empty

                <div class="admin-empty">
                    Belum ada citation validation record.
                </div>

            @endforelse

        </div>

    </section>

    @if($primaryResult)

        <section
            class="admin-panel-card"
            data-admin-reveal
        >

            <div class="admin-panel-head">
                <div>
                    <span class="admin-kicker">
                        FINAL OUTPUT
                    </span>

                    <h2>
                        Primary result
                    </h2>
                </div>
            </div>

            <div class="admin-primary-result">
                {!! nl2br(
                    e(
                        $primaryResult
                            ->content
                    )
                ) !!}
            </div>

        </section>

    @endif

</section>

@endsection