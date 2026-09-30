@extends('layouts.app')

@section(
    'title',
    $document->title
    .' — Research Document'
)

@section('content')

@php
    $content =
        is_array(
            $document
                ->content_json
        )
            ? $document
                ->content_json
            : [];

    $sections =
        collect(
            data_get(
                $content,
                'sections',
                []
            )
        )
            ->filter(
                fn ($section) =>
                    is_array($section)
            )
            ->values();

    $references =
        collect(
            data_get(
                $content,
                'references',
                []
            )
        )
            ->filter()
            ->values();

    $provenance =
        data_get(
            $content,
            'provenance',
            []
        );

    $isReady =
        $document->status
        === 'completed';
@endphp

<section class="document-page">

    <header
        class="document-page-head"
        data-reveal
    >

        <div>

            <div class="document-page-kicker">

                <span>
                    DOCUMENT ENGINE
                </span>

                <span
                    class="
                        document-status
                        document-status--{{
                            $document->status
                        }}
                    "
                >
                    {{
                        strtoupper(
                            $document->status
                        )
                    }}
                </span>

            </div>

            <h1 class="display-type">
                {{ $document->title }}
            </h1>

            <p>
                {{
                    $document
                        ->document_type
                }}
                ·
                {{
                    $document
                        ->citation_style
                }}

                @if(
                    $document
                        ->generated_at
                )
                    · Generated
                    {{
                        $document
                            ->generated_at
                            ->format(
                                'd M Y · H:i'
                            )
                    }}
                @endif
            </p>

        </div>

        <div class="document-page-actions">

            @if($isReady)

                <a
                    href="{{
                        route(
                            'documents.download',
                            [
                                $document,
                                'docx',
                            ]
                        )
                    }}"
                    class="btn btn-secondary"
                >
                    DOCX
                    <span>↓</span>
                </a>

                <a
                    href="{{
                        route(
                            'documents.download',
                            [
                                $document,
                                'pdf',
                            ]
                        )
                    }}"
                    class="btn btn-primary"
                >
                    PDF
                    <span>↓</span>
                </a>

            @endif

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

    @if(
        $document->status
        === 'failed'
    )

        <div
            class="document-render-error"
            role="alert"
        >
            <strong>
                Document rendering gagal.
            </strong>

            <p>
                {{
                    $document
                        ->error_message
                    ?: 'Tidak ada detail error.'
                }}
            </p>
        </div>

    @endif

    <div class="document-workbench">

        <main
            class="document-preview-shell"
            data-reveal
        >

            <div class="document-preview-label">
                DOCUMENT PREVIEW
            </div>

            <article class="document-paper">

                <header class="document-paper__head">

                    <h2>
                        {{
                            data_get(
                                $content,
                                'title',
                                $document->title
                            )
                        }}
                    </h2>

                    <p>
                        {{
                            data_get(
                                $content,
                                'document_type',
                                $document
                                    ->document_type
                            )
                        }}

                        ·

                        {{
                            data_get(
                                $content,
                                'citation_style',
                                $document
                                    ->citation_style
                            )
                        }}
                    </p>

                </header>

                @foreach(
                    $sections
                    as $section
                )

                    <section class="document-paper__section">

                        <h3>
                            {{
                                data_get(
                                    $section,
                                    'heading',
                                    'SECTION'
                                )
                            }}
                        </h3>

                        <div class="document-paper__body">
                            {!! nl2br(
                                e(
                                    data_get(
                                        $section,
                                        'body',
                                        ''
                                    )
                                )
                            ) !!}
                        </div>

                    </section>

                @endforeach

                @if($references->isNotEmpty())

                    <section class="document-paper__section">

                        <h3>
                            DAFTAR PUSTAKA
                        </h3>

                        <div
                            class="
                                document-paper__references
                            "
                        >

                            @foreach(
                                $references
                                as $reference
                            )

                                <p>
                                    {{ $reference }}
                                </p>

                            @endforeach

                        </div>

                    </section>

                @endif

            </article>

        </main>

        <aside
            class="document-editor"
            data-reveal
        >

            <div class="document-editor__head">

                <span>
                    EDIT DRAFT
                </span>

                <h2>
                    Refine sebelum digunakan.
                </h2>

                <p>
                    Perubahan di sini akan membuat
                    ulang DOCX dan PDF.
                </p>

            </div>

            <form
                method="POST"
                action="{{
                    route(
                        'documents.update',
                        $document
                    )
                }}"
            >
                @csrf
                @method('PATCH')

                <label class="document-field">

                    <span>
                        Judul
                    </span>

                    <input
                        type="text"
                        name="title"
                        maxlength="255"
                        required
                        value="{{
                            old(
                                'title',
                                $document->title
                            )
                        }}"
                    >

                </label>

                <div class="document-editor__sections">

                    @foreach(
                        $sections
                        as $index => $section
                    )

                        <div
                            class="
                                document-editor-section
                            "
                        >

                            <div
                                class="
                                    document-editor-section__index
                                "
                            >
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
                            </div>

                            <label class="document-field">

                                <span>
                                    Heading
                                </span>

                                <input
                                    type="text"
                                    name="sections[{{
                                        $index
                                    }}][heading]"
                                    maxlength="255"
                                    required
                                    value="{{
                                        old(
                                            'sections.'
                                            .$index
                                            .'.heading',
                                            data_get(
                                                $section,
                                                'heading'
                                            )
                                        )
                                    }}"
                                >

                            </label>

                            <label class="document-field">

                                <span>
                                    Content
                                </span>

                                <textarea
                                    name="sections[{{
                                        $index
                                    }}][body]"
                                    rows="10"
                                    maxlength="50000"
                                >{{ old(
                                    'sections.'
                                    .$index
                                    .'.body',
                                    data_get(
                                        $section,
                                        'body'
                                    )
                                ) }}</textarea>

                            </label>

                        </div>

                    @endforeach

                </div>

                <button
                    type="submit"
                    class="
                        btn
                        btn-primary
                        btn-full
                    "
                >
                    Save & Rebuild
                    <span>↗</span>
                </button>

            </form>

            <section class="document-provenance">

                <div class="document-context-label">
                    PROVENANCE
                </div>

                <dl>

                    <div>
                        <dt>
                            Research Tool
                        </dt>

                        <dd>
                            {{
                                data_get(
                                    $provenance,
                                    'research_tool'
                                )
                                ?: $document
                                    ->aiRun
                                    ?->aiTool
                                    ?->name
                                ?: '—'
                            }}
                        </dd>
                    </div>

                    <div>
                        <dt>
                            Prompt Version
                        </dt>

                        <dd>
                            {{
                                data_get(
                                    $provenance,
                                    'prompt_version'
                                )
                                ?: '—'
                            }}
                        </dd>
                    </div>

                    <div>
                        <dt>
                            Evidence
                        </dt>

                        <dd>
                            {{
                                data_get(
                                    $provenance,
                                    'evidence_count',
                                    0
                                )
                            }}
                        </dd>
                    </div>

                    <div>
                        <dt>
                            Sources
                        </dt>

                        <dd>
                            {{
                                data_get(
                                    $provenance,
                                    'source_count',
                                    0
                                )
                            }}
                        </dd>
                    </div>

                    <div>
                        <dt>
                            AI Run
                        </dt>

                        <dd>
                            {{
                                data_get(
                                    $provenance,
                                    'ai_run_uuid'
                                )
                                ?: $document
                                    ->aiRun
                                    ?->uuid
                                ?: '—'
                            }}
                        </dd>
                    </div>

                </dl>

                @php
                    $citationKeys =
                        collect(
                            data_get(
                                $provenance,
                                'citation_keys',
                                []
                            )
                        );
                @endphp

                @if(
                    $citationKeys
                        ->isNotEmpty()
                )

                    <div
                        class="
                            document-provenance__citations
                        "
                    >
                        <span>
                            VALIDATED CITATIONS
                        </span>

                        <div>
                            @foreach(
                                $citationKeys
                                as $citation
                            )
                                <b>
                                    [{{ $citation }}]
                                </b>
                            @endforeach
                        </div>
                    </div>

                @endif

            </section>

            <div class="document-editor__back">

                @if(
                    $document
                        ->savedResult
                )

                    <a
                        href="{{
                            route(
                                'saved.show',
                                $document
                                    ->savedResult
                            )
                        }}"
                    >
                        ← Kembali ke Saved Result
                    </a>

                @elseif(
                    $document
                        ->conversation
                )

                    <a
                        href="{{
                            route(
                                'workspace.conversation',
                                $document
                                    ->conversation
                            )
                        }}"
                    >
                        ← Kembali ke Conversation
                    </a>

                @else

                    <a
                        href="{{
                            route(
                                'workspace'
                            )
                        }}"
                    >
                        ← Research Workspace
                    </a>

                @endif

            </div>

        </aside>

    </div>

</section>

@endsection