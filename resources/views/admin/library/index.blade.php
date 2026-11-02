@extends('layouts.admin')

@section('title', 'Library')
@section('page_title', 'Library')

@section('content')

<section class="admin-page">

    <header class="bd-admin-page-head">

        <div
            class="bd-admin-page-head__copy"
            data-admin-reveal
        >
            <p class="bd-admin-kicker">
                KNOWLEDGE / LIBRARY
            </p>

            <h1>
                Curated Sources
            </h1>

            <p>
                Semua PDF yang menjadi sumber retrieval dan evidence.
                Source harus memiliki chunks sebelum dapat dipublish.
            </p>
        </div>


        <div
            class="bd-admin-page-head__actions"
            data-admin-reveal
            data-reveal-from="right"
        >

            <a
                href="{{ route('admin.categories.index') }}"
                class="
                    bd-admin-button
                    bd-admin-button--ghost
                "
            >
                Categories
            </a>


            <a
                href="{{ route('admin.collections.index') }}"
                class="
                    bd-admin-button
                    bd-admin-button--ghost
                "
            >
                Collections
            </a>


            <a
                href="{{ route('admin.library.create') }}"
                class="
                    bd-admin-button
                    bd-admin-button--primary
                "
            >
                Tambah Source

                <span aria-hidden="true">
                    ＋
                </span>
            </a>

        </div>

    </header>


    <form
        method="GET"
        action="{{ route('admin.library.index') }}"
        class="bd-admin-filter"
        data-admin-reveal
        data-reveal-delay="60"
    >

        <input
            class="bd-admin-input"
            type="search"
            name="search"
            value="{{ request('search') }}"
            maxlength="160"
            placeholder="Cari judul source…"
            aria-label="Cari judul source"
        >


        <select
            class="bd-admin-select"
            name="category"
            aria-label="Filter kategori"
        >

            <option value="">
                Semua kategori
            </option>

            @foreach($categories as $category)

                <option
                    value="{{ $category->id }}"
                    @selected(
                        (string) request('category')
                        ===
                        (string) $category->id
                    )
                >
                    {{ $category->name }}
                </option>

            @endforeach

        </select>


        <select
            class="bd-admin-select"
            name="status"
            aria-label="Filter status"
            data-auto-submit
        >

            <option value="">
                Semua status
            </option>

            @foreach (
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
                        request('status')
                        ===
                        $status
                    )
                >
                    {{ ucfirst($status) }}
                </option>

            @endforeach

        </select>

    </form>


    @if($documents->isEmpty())

        <div
            class="bd-admin-empty"
            data-admin-reveal
            data-reveal-delay="100"
        >

            <div class="bd-admin-empty__inner">

                <span class="bd-admin-empty__mark">
                    SOURCE LAYER
                </span>

                <h3>
                    Belum ada source yang cocok.
                </h3>


                @if(
                    request()->hasAny(
                        [
                            'search',
                            'category',
                            'status',
                        ]
                    )
                )

                    <p>
                        Filter aktif tidak menemukan source.
                        Reset filter atau tambahkan source baru.
                    </p>

                @else

                    <p>
                        Mulai dengan judul, kategori, dan PDF.
                        Sistem akan memproses file menjadi chunks
                        sebelum source dipublish.
                    </p>

                @endif


                <div class="bd-admin-empty__actions">

                    <a
                        href="{{ route('admin.library.create') }}"
                        class="
                            bd-admin-button
                            bd-admin-button--primary
                        "
                    >
                        Tambah Source
                    </a>


                    @if(
                        request()->hasAny(
                            [
                                'search',
                                'category',
                                'status',
                            ]
                        )
                    )

                        <a
                            href="{{ route('admin.library.index') }}"
                            class="
                                bd-admin-button
                                bd-admin-button--ghost
                            "
                        >
                            Reset Filter
                        </a>

                    @endif

                </div>

            </div>

        </div>

    @else

        <div
            class="bd-admin-table-wrap"
            data-admin-reveal
            data-reveal-delay="100"
        >

            <table class="bd-admin-table">

                <thead>

                    <tr>
                        <th>Source</th>
                        <th>Kategori</th>
                        <th>PDF</th>
                        <th>Chunks</th>
                        <th>Status</th>
                        <th></th>
                    </tr>

                </thead>


                <tbody>

                    @foreach($documents as $document)

                        <tr>

                            <td>

                                <div
                                    class="
                                        bd-library-source-cell
                                    "
                                >

                                    <span
                                        class="
                                            bd-library-pdf-mini
                                        "
                                        aria-hidden="true"
                                    >

                                        <i>
                                            PDF
                                        </i>

                                        <b>
                                            {{
                                                \Illuminate\Support\Str::limit(
                                                    $document->title,
                                                    45
                                                )
                                            }}
                                        </b>

                                        <em>
                                            SRC-{{
                                                str_pad(
                                                    $document->id,
                                                    4,
                                                    '0',
                                                    STR_PAD_LEFT
                                                )
                                            }}
                                        </em>

                                    </span>


                                    <div
                                        class="
                                            bd-admin-table__title
                                        "
                                    >

                                        <strong>
                                            {{ $document->title }}
                                        </strong>

                                        <small>
                                            SRC-{{
                                                str_pad(
                                                    $document->id,
                                                    4,
                                                    '0',
                                                    STR_PAD_LEFT
                                                )
                                            }}

                                            ·

                                            {{
                                                optional(
                                                    $document->updated_at
                                                )->format(
                                                    'd M Y · H:i'
                                                )
                                            }}
                                        </small>

                                    </div>

                                </div>

                            </td>


                            <td>
                                {{
                                    $document->category?->name
                                    ?: '—'
                                }}
                            </td>


                            <td>

                                <span
                                    class="
                                        bd-admin-status
                                        bd-admin-status--{{
                                            $document->files_count > 0
                                                ? 'ready'
                                                : 'failed'
                                        }}
                                    "
                                >
                                    {{
                                        $document->files_count > 0
                                            ? 'Available'
                                            : 'Missing'
                                    }}
                                </span>

                            </td>


                            <td>
                                {{ $document->chunks_count }}
                            </td>


                            <td>

                                <span
                                    class="
                                        bd-admin-status
                                        bd-admin-status--{{
                                            $document->status
                                        }}
                                    "
                                >
                                    {{
                                        ucfirst(
                                            $document->status
                                        )
                                    }}
                                </span>

                            </td>


                            <td>

                                <a
                                    class="
                                        bd-admin-table__action
                                    "
                                    href="{{
                                        route(
                                            'admin.library.edit',
                                            $document
                                        )
                                    }}"
                                >
                                    Kelola →
                                </a>

                            </td>

                        </tr>

                    @endforeach

                </tbody>

            </table>

        </div>


        <div style="margin-top:18px;">
            {{ $documents->links() }}
        </div>

    @endif

</section>

@endsection