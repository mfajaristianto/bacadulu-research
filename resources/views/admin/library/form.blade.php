@extends('layouts.admin')

@section(
    'title',
    (
        $document->exists
            ? 'Edit Source'
            : 'Add Source'
    ).' — Admin'
)

@section('content')

<section class="admin-page narrow-page">

    <div
        class="admin-hero compact"
        data-admin-reveal
    >
        <div>
            <p class="eyebrow">
                ADMIN / LIBRARY
            </p>

            <h1>
                {{
                    $document->exists
                        ? 'Edit source.'
                        : 'Add source.'
                }}
            </h1>

            <p>
                Source disimpan secara private,
                diproses menjadi text chunks,
                lalu baru tersedia untuk
                evidence retrieval.
            </p>
        </div>

        <a
            href="{{
                route(
                    'admin.library.index'
                )
            }}"
            class="btn btn-secondary"
        >
            Back to library
        </a>
    </div>

    @if(session('warning'))
        <div
            class="alert alert-warning"
            role="status"
        >
            {{ session('warning') }}
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

    @if($document->exists)
        <div
            class="admin-info-banner"
            data-admin-reveal
        >
            <span class="quick-icon">
                {{
                    $chunkCount > 0
                        ? '✓'
                        : '!'
                }}
            </span>

            <div>
                <strong>
                    {{
                        $chunkCount > 0
                            ? $chunkCount.' chunks siap'
                            : 'Source belum memiliki chunks'
                    }}
                </strong>

                <p>
                    @if($latestFile)
                        File:
                        {{
                            $latestFile
                                ->original_name
                            ?: 'Private PDF'
                        }}

                        ·

                        {{
                            number_format(
                                (
                                    $latestFile
                                        ->file_size
                                    ?? 0
                                ) / 1024 / 1024,
                                2
                            )
                        }}
                        MB

                        ·

                        Status file:
                        {{
                            $latestFile
                                ->status
                        }}
                    @else
                        Belum ada PDF tersimpan
                        untuk source ini.
                    @endif
                </p>
            </div>
        </div>
    @endif

    <form
        method="POST"
        action="{{
            $document->exists
                ? route(
                    'admin.library.update',
                    $document
                )
                : route(
                    'admin.library.store'
                )
        }}"
        class="admin-form-card"
        enctype="multipart/form-data"
        data-admin-reveal
    >
        @csrf

        @if($document->exists)
            @method('PUT')
        @endif

        <div class="admin-form-section">
            <span class="admin-kicker">
                SOURCE IDENTITY
            </span>

            <h2>
                Core metadata
            </h2>
        </div>

        <div class="admin-form-grid">

            <label class="full">
                Title

                <input
                    name="title"
                    value="{{
                        old(
                            'title',
                            $document->title
                        )
                    }}"
                    required
                    maxlength="255"
                >
            </label>

            <label>
                Category

                <select name="category_id">
                    <option value="">
                        No category
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
                                    $document
                                        ->category_id
                                )
                                == $category->id
                            )
                        >
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label>
                Document type

                <input
                    name="document_type"
                    value="{{
                        old(
                            'document_type',
                            $document
                                ->document_type
                        )
                    }}"
                    required
                    maxlength="100"
                    placeholder="journal article, report, book chapter…"
                >
            </label>

            <label>
                Publication year

                <input
                    type="number"
                    name="publication_year"
                    min="1900"
                    max="{{
                        now()->year + 2
                    }}"
                    value="{{
                        old(
                            'publication_year',
                            $document
                                ->publication_year
                        )
                    }}"
                >
            </label>

            <label>
                Language

                <input
                    name="language"
                    value="{{
                        old(
                            'language',
                            $document->language
                                ?: 'id'
                        )
                    }}"
                    maxlength="20"
                >
            </label>

            <label>
                Publisher

                <input
                    name="publisher"
                    value="{{
                        old(
                            'publisher',
                            $document->publisher
                        )
                    }}"
                    maxlength="255"
                >
            </label>

            <label>
                Source label

                <input
                    name="source_label"
                    value="{{
                        old(
                            'source_label',
                            $document
                                ->source_label
                        )
                    }}"
                    maxlength="255"
                    placeholder="Curated Source"
                >
            </label>

            <label>
                DOI

                <input
                    name="doi"
                    value="{{
                        old(
                            'doi',
                            $document->doi
                        )
                    }}"
                    maxlength="255"
                >
            </label>

            <label>
                ISBN

                <input
                    name="isbn"
                    value="{{
                        old(
                            'isbn',
                            $document->isbn
                        )
                    }}"
                    maxlength="100"
                >
            </label>

        </div>

        <label>
            Abstract

            <textarea
                name="abstract"
                rows="8"
                placeholder="Abstract atau editorial summary source…"
            >{{ old(
                'abstract',
                $document->abstract
            ) }}</textarea>
        </label>

        <div class="admin-form-section">
            <span class="admin-kicker">
                SOURCE FILE
            </span>

            <h2>
                PDF & extraction
            </h2>

            <p>
                PDF disimpan pada private storage.
                User tidak memperoleh akses langsung
                ke raw internal PDF.
            </p>
        </div>

        <label>
            {{
                $document->exists
                    ? 'Replace PDF'
                    : 'Upload PDF'
            }}
            <span class="field-optional">
                optional
            </span>

            <input
                type="file"
                name="source_file"
                accept="application/pdf,.pdf"
            >

            <small class="field-help">
                PDF maksimal
                {{
                    number_format(
                        config(
                            'research.ingestion.max_upload_kb',
                            51200
                        ) / 1024,
                        0
                    )
                }}
                MB.
                File disimpan private.
            </small>
        </label>

        <label>
            Extracted Text
            <span class="field-optional">
                fallback
            </span>

            <textarea
                name="extracted_text"
                rows="12"
                placeholder="Opsional. Paste text hasil ekstraksi PDF di sini jika pdftotext belum tersedia atau PDF merupakan hasil scan."
            >{{ old(
                'extracted_text'
            ) }}</textarea>

            <small class="field-help">
                Jika field ini diisi,
                text ini diprioritaskan untuk chunking.
                PDF tetap disimpan sebagai source file.
            </small>
        </label>

        @if($document->exists && $latestFile)
            <label class="check-row">
                <input
                    type="checkbox"
                    name="reprocess_source"
                    value="1"
                    @checked(
                        old(
                            'reprocess_source'
                        )
                    )
                >

                <span>
                    Reprocess PDF existing dan
                    rebuild seluruh chunks.
                </span>
            </label>
        @endif

        <div class="admin-form-section">
            <span class="admin-kicker">
                ACCESS & INDEX STATE
            </span>

            <h2>
                Availability
            </h2>
        </div>

        <div class="admin-form-grid">

            <label>
                Visibility

                <select
                    name="visibility"
                    required
                >
                    <option
                        value="public"
                        @selected(
                            old(
                                'visibility',
                                $document
                                    ->visibility
                                ?: 'restricted'
                            )
                            === 'public'
                        )
                    >
                        Eligible for retrieval
                    </option>

                    <option
                        value="restricted"
                        @selected(
                            old(
                                'visibility',
                                $document
                                    ->visibility
                                ?: 'restricted'
                            )
                            === 'restricted'
                        )
                    >
                        Restricted
                    </option>
                </select>
            </label>

            <label>
                Status

                <select
                    name="status"
                    required
                >
                    @foreach(
                        [
                            'draft',
                            'processing',
                            'ready',
                            'published',
                            'archived',
                        ]
                        as $status
                    )
                        <option
                            value="{{ $status }}"
                            @selected(
                                old(
                                    'status',
                                    $document->status
                                        ?: 'draft'
                                )
                                === $status
                            )
                        >
                            {{ ucfirst($status) }}
                        </option>
                    @endforeach
                </select>

                <small class="field-help">
                    Hanya
                    <strong>Published + Eligible for retrieval</strong>
                    yang dapat dipakai oleh Research Workspace.
                </small>
            </label>

        </div>

        <div class="admin-info-banner">
            <span class="quick-icon">
                i
            </span>

            <div>
                <strong>
                    Flow ingestion
                </strong>

                <p>
                    PDF → private storage →
                    text extraction →
                    chunking →
                    ready/published →
                    retrieval.
                </p>
            </div>
        </div>

        <div class="admin-form-footer">

            <button
                class="btn btn-primary"
                type="submit"
            >
                {{
                    $document->exists
                        ? 'Save & Process'
                        : 'Add & Process Source'
                }}
            </button>

            <a
                class="btn btn-secondary"
                href="{{
                    route(
                        'admin.library.index'
                    )
                }}"
            >
                Cancel
            </a>

        </div>

    </form>

</section>

@endsection