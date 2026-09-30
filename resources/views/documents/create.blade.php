@extends('layouts.app')

@section(
    'title',
    'Generate Document — BacaDulu Research'
)

@section('content')

@php
    $primaryResult =
        $run->primaryResult;

    $structured =
        is_array(
            $primaryResult
                ?->structured_output_json
        )
            ? $primaryResult
                ->structured_output_json
            : [];

    $answer =
        data_get(
            $structured,
            'answer'
        )
        ?: $primaryResult
            ?->content
        ?: '';

    $conversationTitle =
        $run
            ->conversation
            ?->title
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

    $sourceCount =
        collect(
            data_get(
                $structured,
                'sources',
                []
            )
        )->count();

    $evidenceCount =
        collect(
            data_get(
                $structured,
                'evidence',
                []
            )
        )->count();
@endphp

<section class="document-create-page">

    <div
        class="document-create-head"
        data-reveal
    >
        <div>
            <p class="eyebrow">
                DOCUMENT ENGINE
            </p>

            <h1 class="display-type">
                Ubah research menjadi
                dokumen yang bisa Anda lanjutkan.
            </h1>

            <p>
                BacaDulu akan menyusun draft berdasarkan
                final answer, evidence tervalidasi,
                source metadata, dan provenance dari
                AI Run ini.
            </p>
        </div>

        @if(
            $run
                ->assistantMessage
                ?->conversation
        )
            <a
                href="{{
                    route(
                        'workspace.conversation',
                        $run
                            ->assistantMessage
                            ->conversation
                    )
                }}"
                class="btn btn-secondary"
            >
                ← Conversation
            </a>
        @else
            <a
                href="{{
                    route(
                        'saved.index'
                    )
                }}"
                class="btn btn-secondary"
            >
                ← Saved Results
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

    <div class="document-create-layout">

        <main
            class="document-create-main"
            data-reveal
        >

            <form
                method="POST"
                action="{{
                    route(
                        'documents.store'
                    )
                }}"
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
                        value="{{
                            $savedResultId
                        }}"
                    >
                @endif

                <div class="document-form-section">

                    <div
                        class="
                            document-form-section__index
                        "
                    >
                        01
                    </div>

                    <div>
                        <span>
                            DOCUMENT IDENTITY
                        </span>

                        <h2>
                            Tentukan jenis output.
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
                        Judul masih dapat diedit
                        kembali setelah draft dibuat.
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
                            <option value="">
                                Pilih output
                            </option>

                            @foreach(
                                $documentTypes
                                as $documentType
                            )
                                <option
                                    value="{{
                                        $documentType
                                    }}"
                                    @selected(
                                        old(
                                            'document_type'
                                        )
                                        ===
                                        $documentType
                                    )
                                >
                                    {{
                                        $documentType
                                    }}
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
                            @foreach(
                                $citationStyles
                                as $style
                            )
                                <option
                                    value="{{ $style }}"
                                    @selected(
                                        old(
                                            'citation_style',
                                            'APA 7'
                                        )
                                        ===
                                        $style
                                    )
                                >
                                    {{ $style }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                </div>

                <div
                    class="
                        document-integrity-note
                    "
                >
                    <span class="document-integrity-note__mark">
                        ✓
                    </span>

                    <div>
                        <strong>
                            Evidence-aware generation
                        </strong>

                        <p>
                            Bagian yang belum didukung
                            research result tidak akan
                            diisi dengan klaim buatan.
                            Sistem akan menandainya
                            sebagai draft yang perlu
                            Anda kembangkan.
                        </p>
                    </div>
                </div>

                <div class="document-form-actions">

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        Generate Document
                        <span>↗</span>
                    </button>

                    <span>
                        Output:
                        DOCX + PDF
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
                        Evidence
                    </dt>

                    <dd>
                        {{
                            $evidenceCount
                        }}
                        item
                    </dd>
                </div>

                <div>
                    <dt>
                        Sources
                    </dt>

                    <dd>
                        {{
                            $sourceCount
                                ?: $run
                                    ->sources
                                    ->count()
                        }}
                        source
                    </dd>
                </div>

                <div>
                    <dt>
                        Run status
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
                                620,
                                '…'
                            )
                        }}
                    </p>

                </div>

            @endif

        </aside>

    </div>

</section>

@endsection