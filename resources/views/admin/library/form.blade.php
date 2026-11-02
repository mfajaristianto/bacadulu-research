@extends('layouts.admin')

@php
    $editing = $document->exists;

    $maxMb = (int) round(
        config(
            'research.ingestion.max_upload_kb',
            102400
        ) / 1024
    );

    $currentFileName =
        $latestFile?->original_name
        ?: (
            $latestFile?->path
            ? basename($latestFile->path)
            : null
        );

    $currentFileMb =
        $latestFile
        ? number_format(
            ($latestFile->file_size ?? 0) / 1048576,
            2
        )
        : null;

    $previewTitle = trim(
        (string) old(
            'title',
            $document->title
        )
    );

    if (
        $previewTitle === ''
        && $currentFileName
    ) {
        $previewTitle = pathinfo(
            $currentFileName,
            PATHINFO_FILENAME
        );
    }

    if ($previewTitle === '') {
        $previewTitle = 'Judul Source';
    }
@endphp

@section(
    'title',
    $editing
        ? 'Source Detail'
        : 'Tambah Source'
)

@section(
    'page_title',
    $editing
        ? 'Source Detail'
        : 'Tambah Source'
)

@section('content')

<section class="admin-page">

    <header class="bd-admin-page-head">

        <div
            class="bd-admin-page-head__copy"
            data-admin-reveal
        >
            <p class="bd-admin-kicker">
                KNOWLEDGE / SOURCE
            </p>

            <h1>
                {{
                    $editing
                    ? $document->title
                    : 'Tambah Curated Source'
                }}
            </h1>

            <p>
                Metadata dibuat sederhana agar source mudah dikelola,
                diproses menjadi evidence, dan ditelusuri kembali.
            </p>
        </div>

        <div
            class="bd-admin-page-head__actions"
            data-admin-reveal
            data-reveal-from="right"
        >
            <a
                href="{{ route('admin.library.index') }}"
                class="
                    bd-admin-button
                    bd-admin-button--ghost
                "
            >
                ← Library
            </a>
        </div>

    </header>


    @if($categories->isEmpty())

        <div
            class="
                bd-admin__flash
                bd-admin__flash--warning
            "
            role="status"
        >
            <span></span>

            <p>
                Belum ada category aktif.
                Buat category dulu sebelum menambahkan source.

                <a
                    href="{{ route('admin.categories.create') }}"
                    class="bd-admin-table__action"
                >
                    Buat Category →
                </a>
            </p>
        </div>

    @endif


    <form
        method="POST"
        action="{{
            $editing
                ? route(
                    'admin.library.update',
                    $document
                )
                : route(
                    'admin.library.store'
                )
        }}"
        enctype="multipart/form-data"
        class="bd-admin-form-shell"
        data-admin-reveal
        data-reveal-delay="70"
    >

        @csrf

        @if($editing)
            @method('PUT')
        @endif


        <div class="bd-admin-form">

            <section class="bd-admin-form__section">

                <div class="bd-admin-form__section-head">

                    <strong>
                        Identitas Source
                    </strong>

                    <p>
                        Judul di bawah ini juga digunakan
                        sebagai visual cover source.
                    </p>

                </div>


                <div class="bd-admin-form__grid">

                    <label
                        class="
                            bd-admin-form-field
                            is-full
                        "
                    >

                        <span>
                            Judul
                            <small>wajib</small>
                        </span>

                        <input
                            class="bd-admin-input"
                            type="text"
                            name="title"
                            value="{{
                                old(
                                    'title',
                                    $document->title
                                )
                            }}"
                            maxlength="255"
                            placeholder="Contoh: ESG Disclosure and Firm Value"
                            required
                            data-source-title-input
                        >

                        @error('title')

                            <small class="admin-danger-text">
                                {{ $message }}
                            </small>

                        @enderror

                    </label>


                    <label
                        class="
                            bd-admin-form-field
                            is-full
                        "
                    >

                        <span>
                            Kategori
                            <small>wajib</small>
                        </span>

                        <select
                            class="bd-admin-select"
                            name="category_id"
                            required
                            @disabled(
                                $categories->isEmpty()
                            )
                        >

                            <option value="">
                                Pilih kategori
                            </option>

                            @foreach ($categories as $category)

                                <option
                                    value="{{ $category->id }}"
                                    @selected(
                                        (string) old(
                                            'category_id',
                                            $document->category_id
                                        )
                                        ===
                                        (string) $category->id
                                    )
                                >
                                    {{ $category->name }}
                                </option>

                            @endforeach

                        </select>

                        @error('category_id')

                            <small class="admin-danger-text">
                                {{ $message }}
                            </small>

                        @enderror

                    </label>

                </div>

            </section>


            <section class="bd-admin-form__section">

                <div class="bd-admin-form__section-head">

                    <strong>
                        PDF Source
                    </strong>

                    <p>
                        {{
                            $editing
                                ? 'Upload PDF baru hanya jika ingin mengganti file source. Visual cover mengikuti judul Library secara otomatis.'
                                : 'Pilih atau drag PDF. Visual cover akan terbentuk otomatis dari nama file dan judul Library.'
                        }}
                    </p>

                </div>


                <div
                    class="
                        bd-source-upload
                        {{
                            $latestFile
                                ? 'has-file'
                                : ''
                        }}
                    "
                    data-source-upload
                >

                    <div
                        class="bd-source-paper-stage"
                        aria-hidden="true"
                    >

                        <div
                            class="bd-source-paper-orbit"
                        ></div>


                        <article
                            class="bd-source-paper"
                            data-pdf-paper
                        >

                            <span
                                class="bd-source-paper__scan"
                            ></span>


                            <div class="bd-source-paper__top">

                                <span
                                    class="
                                        bd-source-paper__badge
                                    "
                                >
                                    PDF
                                </span>

                                <span
                                    class="
                                        bd-source-paper__mark
                                    "
                                >
                                    BACA
                                </span>

                            </div>


                            <div
                                class="
                                    bd-source-paper__content
                                "
                            >

                                <span
                                    class="
                                        bd-source-paper__eyebrow
                                    "
                                >
                                    CURATED SOURCE
                                </span>

                                <h3
                                    class="
                                        bd-source-paper__title
                                    "
                                    data-pdf-preview-title
                                >
                                    {{ $previewTitle }}
                                </h3>

                            </div>


                            <div
                                class="
                                    bd-source-paper__footer
                                "
                            >

                                <span
                                    class="
                                        bd-source-paper__file
                                    "
                                    data-pdf-preview-file
                                >
                                    {{
                                        $currentFileName
                                            ?: 'Belum ada PDF'
                                    }}
                                </span>

                                <span
                                    class="
                                        bd-source-paper__meta
                                    "
                                    data-pdf-preview-meta
                                >
                                    @if($latestFile)

                                        {{
                                            $currentFileMb
                                        }}
                                        MB · tersimpan

                                    @else

                                        Pilih file untuk preview

                                    @endif
                                </span>

                            </div>

                        </article>

                    </div>


                    <div
                        class="
                            bd-source-upload__control
                        "
                    >

                        <label
                            class="
                                bd-admin-file
                                bd-source-dropzone
                            "
                            for="source-file"
                            data-source-dropzone
                        >

                            <input
                                id="source-file"
                                type="file"
                                name="source_file"
                                accept="
                                    application/pdf,
                                    .pdf
                                "
                                data-file-input
                                data-file-target="#source-file-name"
                                data-source-pdf-input
                                @required(! $editing)
                            >


                            <span
                                class="
                                    bd-admin-file__copy
                                "
                            >

                                <span
                                    class="
                                        bd-source-dropzone__icon
                                    "
                                    aria-hidden="true"
                                >
                                    PDF
                                </span>


                                <strong>
                                    {{
                                        $latestFile
                                            ? 'Ganti PDF'
                                            : 'Pilih PDF'
                                    }}
                                </strong>


                                <span>
                                    Maksimum
                                    {{ $maxMb }}
                                    MB · PDF only
                                </span>


                                <small id="source-file-name">

                                    @if($latestFile)

                                        {{
                                            $currentFileName
                                        }}
                                        ·
                                        {{
                                            $currentFileMb
                                        }}
                                        MB

                                    @else

                                        Belum ada PDF dipilih

                                    @endif

                                </small>


                                <span
                                    class="
                                        bd-source-dropzone__hint
                                    "
                                >
                                    Klik area ini atau
                                    drag & drop file PDF.
                                </span>

                            </span>

                        </label>


                        @error('source_file')

                            <small
                                class="
                                    admin-danger-text
                                "
                            >
                                {{ $message }}
                            </small>

                        @enderror

                    </div>

                </div>

            </section>


            <section class="bd-admin-form__section">

                <div
                    class="
                        bd-admin-form__section-head
                    "
                >

                    <strong>
                        Aksi Source
                    </strong>

                    <p>
                        Simpan tidak mengubah lifecycle.
                        Publish hanya berhasil jika source
                        sudah memiliki chunks.
                    </p>

                </div>


                <div
                    class="
                        bd-admin-inline-actions
                    "
                    style="
                        justify-content:
                        flex-start;
                    "
                >

                    <button
                        class="
                            bd-admin-button
                            bd-admin-button--primary
                        "
                        type="submit"
                        name="action"
                        value="save"
                        @disabled(
                            $categories->isEmpty()
                        )
                    >
                        {{
                            $editing
                                ? 'Simpan Perubahan'
                                : 'Simpan & Proses'
                        }}
                    </button>


                    @if($editing)

                        <button
                            class="
                                bd-admin-button
                                bd-admin-button--ghost
                            "
                            type="submit"
                            name="action"
                            value="reprocess"
                        >
                            Proses Ulang
                        </button>


                        @if(
                            $document->status
                            ===
                            'published'
                        )

                            <button
                                class="
                                    bd-admin-button
                                    bd-admin-button--ghost
                                "
                                type="submit"
                                name="action"
                                value="unpublish"
                            >
                                Unpublish
                            </button>

                        @else

                            <button
                                class="
                                    bd-admin-button
                                    bd-admin-button--coral
                                "
                                type="submit"
                                name="action"
                                value="publish"
                                @disabled(
                                    $chunkCount < 1
                                )
                            >
                                Publish
                            </button>

                        @endif


                        <button
                            class="
                                bd-admin-button
                                bd-admin-button--danger
                            "
                            type="submit"
                            name="action"
                            value="archive"
                            data-confirm="Archive source ini? Source tidak lagi digunakan sebagai source aktif."
                        >
                            Archive
                        </button>

                    @endif

                </div>

            </section>

        </div>


        <aside class="bd-admin-form__aside">

            <section>

                <strong>
                    Source State
                </strong>

                <p>

                    <span
                        class="
                            bd-admin-status
                            bd-admin-status--{{
                                $document->status
                                    ?: 'draft'
                            }}
                        "
                    >
                        {{
                            ucfirst(
                                $document->status
                                    ?: 'draft'
                            )
                        }}
                    </span>

                </p>

            </section>


            <section>

                <strong>
                    Processing
                </strong>


                <div class="bd-admin-process">

                    <div
                        class="
                            bd-admin-process__item
                            {{
                                $latestFile
                                    ? 'is-done'
                                    : ''
                            }}
                        "
                    >

                        <i>
                            {{
                                $latestFile
                                    ? '✓'
                                    : '1'
                            }}
                        </i>

                        <span>
                            PDF tersimpan
                        </span>

                        <strong>
                            {{
                                $latestFile
                                    ? 'Ready'
                                    : 'Waiting'
                            }}
                        </strong>

                    </div>


                    <div
                        class="
                            bd-admin-process__item
                            {{
                                $chunkCount > 0
                                    ? 'is-done'
                                    : ''
                            }}
                        "
                    >

                        <i>
                            {{
                                $chunkCount > 0
                                    ? '✓'
                                    : '2'
                            }}
                        </i>

                        <span>
                            Chunks
                        </span>

                        <strong>
                            {{ $chunkCount }}
                        </strong>

                    </div>


                    <div
                        class="
                            bd-admin-process__item
                            {{
                                $document->status === 'published'
                                &&
                                $chunkCount > 0
                                    ? 'is-done'
                                    : ''
                            }}
                        "
                    >

                        <i>
                            {{
                                $document->status === 'published'
                                &&
                                $chunkCount > 0
                                    ? '✓'
                                    : '3'
                            }}
                        </i>

                        <span>
                            AI-ready
                        </span>

                        <strong>
                            {{
                                $document->status === 'published'
                                &&
                                $chunkCount > 0
                                    ? 'Yes'
                                    : 'No'
                            }}
                        </strong>

                    </div>

                </div>

            </section>


            <section>

                <strong>
                    Lifecycle
                </strong>

                <p>
                    Draft → Ready → Published.
                    Archive dipakai sebagai pengganti
                    hard-delete supaya provenance tetap aman.
                </p>

            </section>


            @if($editing)

                <section>

                    <strong>
                        Source ID
                    </strong>

                    <p>
                        SRC-{{
                            str_pad(
                                $document->id,
                                4,
                                '0',
                                STR_PAD_LEFT
                            )
                        }}
                    </p>

                </section>

            @endif

        </aside>

    </form>

</section>



@endsection