@extends('layouts.admin')

@section('title', 'Glossary')
@section('page_title', 'Glossary')

@section('content')

<section class="admin-page">

    <header class="bd-admin-page-head">

        <div class="bd-admin-page-head__copy">

            <p class="bd-admin-kicker">
                KNOWLEDGE / GLOSSARY
            </p>

            <h1>
                Research Glossary
            </h1>

            <p>
                Istilah membantu AI memahami konteks dan terminology.
                Glossary bukan evidence dan tidak dapat menjadi citation.
            </p>

        </div>

        <div class="bd-admin-page-head__actions">

            <a
                href="{{ route('admin.glossary.create') }}"
                class="bd-admin-button bd-admin-button--primary"
            >
                Tambah Istilah ＋
            </a>

        </div>

    </header>

    <form
        method="GET"
        class="bd-admin-filter"
    >

        <input
            class="bd-admin-input"
            type="search"
            name="search"
            value="{{ request('search') }}"
            placeholder="Cari istilah…"
        >

        <select
            class="bd-admin-select"
            name="category"
            data-auto-submit
        >

            <option value="">
                Semua category
            </option>

            @foreach($categories as $category)

                <option
                    value="{{ $category->id }}"
                    @selected(
                        (string)
                            request('category')
                        ===
                        (string)
                            $category->id
                    )
                >
                    {{ $category->name }}
                </option>

            @endforeach

        </select>

        <select
            class="bd-admin-select"
            name="status"
            data-auto-submit
        >

            <option value="">
                Semua status
            </option>

            <option
                value="active"
                @selected(
                    request('status')
                    ===
                    'active'
                )
            >
                Active
            </option>

            <option
                value="inactive"
                @selected(
                    request('status')
                    ===
                    'inactive'
                )
            >
                Inactive
            </option>

        </select>

    </form>

    <div class="bd-admin-table-wrap">

        <table class="bd-admin-table">

            <thead>

                <tr>
                    <th>Term</th>
                    <th>Category</th>
                    <th>Aliases</th>
                    <th>Status</th>
                    <th></th>
                </tr>

            </thead>

            <tbody>

                @forelse($terms as $term)

                    <tr>

                        <td>

                            <div class="bd-admin-table__title">

                                <strong>
                                    {{ $term->term }}
                                </strong>

                                <small>
                                    {{
                                        \Illuminate\Support\Str::limit(
                                            $term->definition,
                                            105
                                        )
                                    }}
                                </small>

                            </div>

                        </td>

                        <td>
                            {{ $term->category?->name ?: 'Global' }}
                        </td>

                        <td>

                            @if(
                                collect(
                                    $term->aliases_json
                                    ?? []
                                )->isNotEmpty()
                            )

                                {{
                                    collect(
                                        $term->aliases_json
                                    )
                                        ->take(3)
                                        ->implode(', ')
                                }}

                            @else
                                —
                            @endif

                        </td>

                        <td>

                            <span
                                class="
                                    bd-admin-status
                                    bd-admin-status--{{
                                        $term->status === 'active'
                                            ? 'active'
                                            : 'draft'
                                    }}
                                "
                            >
                                {{ ucfirst($term->status) }}
                            </span>

                        </td>

                        <td>

                            <a
                                class="bd-admin-table__action"
                                href="{{ route('admin.glossary.edit', $term) }}"
                            >
                                Edit →
                            </a>

                        </td>

                    </tr>

                @empty

                    <tr>

                        <td colspan="5">

                            <div class="bd-admin-empty">

                                <div class="bd-admin-empty__inner">

                                    <span class="bd-admin-empty__mark">
                                        TERM
                                    </span>

                                    <h3>
                                        Belum ada glossary.
                                    </h3>

                                    <p>
                                        Tambahkan istilah yang perlu
                                        dipahami secara konsisten oleh
                                        research engine.
                                    </p>

                                </div>

                            </div>

                        </td>

                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>

    {{ $terms->links() }}

</section>

@endsection