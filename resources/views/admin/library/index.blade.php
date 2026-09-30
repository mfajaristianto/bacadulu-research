@extends('layouts.admin')

@section(
    'title',
    'Internal Library — Admin'
)

@section('content')

<section class="admin-page">

    <div
        class="admin-hero compact"
        data-admin-reveal
    >
        <div>
            <p class="eyebrow">
                ADMIN / LIBRARY
            </p>

            <h1>
                Internal Library.
            </h1>

            <p>
                Kelola source,
                private PDF,
                chunking state,
                publication,
                dan retrieval eligibility.
            </p>
        </div>

        <a
            href="{{
                route(
                    'admin.library.create'
                )
            }}"
            class="btn btn-primary"
        >
            Add source
            <span>+</span>
        </a>
    </div>

    <div
        class="admin-info-banner"
        data-admin-reveal
    >
        <span class="quick-icon">
            R
        </span>

        <div>
            <strong>
                Source harus memiliki chunks
                sebelum dapat menghasilkan evidence.
            </strong>

            <p>
                Published + Public menentukan eligibility.
                Chunk count menunjukkan apakah isi source
                sudah siap diretrieve.
            </p>
        </div>
    </div>

    <form
        method="GET"
        action="{{
            route(
                'admin.library.index'
            )
        }}"
        class="admin-filter"
        data-admin-reveal
    >
        <label class="filter-field">
            <span>
                Search
            </span>

            <input
                type="search"
                name="search"
                value="{{
                    request('search')
                }}"
                maxlength="160"
                placeholder="Title, publisher, DOI…"
            >
        </label>

        <label class="filter-field">
            <span>
                Status
            </span>

            <select name="status">
                <option value="">
                    All status
                </option>

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
                            request('status')
                                === $status
                        )
                    >
                        {{ ucfirst($status) }}
                    </option>
                @endforeach
            </select>
        </label>

        <label class="filter-field">
            <span>
                Visibility
            </span>

            <select name="visibility">
                <option value="">
                    All visibility
                </option>

                <option
                    value="public"
                    @selected(
                        request('visibility')
                            === 'public'
                    )
                >
                    Public
                </option>

                <option
                    value="restricted"
                    @selected(
                        request('visibility')
                            === 'restricted'
                    )
                >
                    Restricted
                </option>
            </select>
        </label>

        <button
            type="submit"
            class="btn btn-secondary"
        >
            Filter
        </button>
    </form>

    <div
        class="admin-table-wrap"
        data-admin-reveal
    >
        <table class="admin-table">

            <thead>
                <tr>
                    <th>Title</th>
                    <th>Category</th>
                    <th>File</th>
                    <th>Chunks</th>
                    <th>Visibility</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>

            <tbody>

            @forelse(
                $documents
                as $document
            )
                <tr>
                    <td>
                        <strong>
                            {{ $document->title }}
                        </strong>

                        <small>
                            {{
                                $document->publisher
                                ?: $document->source_label
                                ?: 'Internal Source'
                            }}

                            @if(
                                $document
                                    ->publication_year
                            )
                                ·
                                {{
                                    $document
                                        ->publication_year
                                }}
                            @endif
                        </small>
                    </td>

                    <td>
                        {{
                            $document
                                ->category
                                ?->name
                            ?: '—'
                        }}
                    </td>

                    <td>
                        @if(
                            $document
                                ->files_count > 0
                        )
                            <span
                                class="
                                    status-pill
                                    status-active
                                "
                            >
                                PDF
                            </span>
                        @else
                            <span class="status-pill">
                                None
                            </span>
                        @endif
                    </td>

                    <td>
                        @if(
                            $document
                                ->chunks_count > 0
                        )
                            <strong>
                                {{
                                    $document
                                        ->chunks_count
                                }}
                            </strong>

                            <small>
                                ready for retrieval
                            </small>
                        @else
                            <span
                                class="
                                    status-pill
                                    status-processing
                                "
                            >
                                0
                            </span>

                            <small>
                                needs processing
                            </small>
                        @endif
                    </td>

                    <td>
                        <span
                            class="
                                status-pill
                                {{
                                    $document
                                        ->visibility
                                        === 'public'
                                    ? 'status-published'
                                    : 'status-draft'
                                }}
                            "
                        >
                            {{
                                ucfirst(
                                    $document
                                        ->visibility
                                )
                            }}
                        </span>
                    </td>

                    <td>
                        <span
                            class="
                                status-pill
                                status-{{
                                    $document
                                        ->status
                                }}
                            "
                        >
                            {{
                                ucfirst(
                                    $document
                                        ->status
                                )
                            }}
                        </span>
                    </td>

                    <td>
                        <a
                            class="table-link"
                            href="{{
                                route(
                                    'admin.library.edit',
                                    $document
                                )
                            }}"
                        >
                            Edit
                        </a>
                    </td>
                </tr>

            @empty

                <tr>
                    <td colspan="7">
                        <div class="admin-empty">
                            Belum ada source.
                            Tambahkan PDF pertama untuk
                            mulai membangun Research Library.
                        </div>
                    </td>
                </tr>

            @endforelse

            </tbody>
        </table>
    </div>

    {{ $documents->links() }}

</section>

@endsection