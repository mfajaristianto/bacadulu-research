@extends('layouts.admin')

@section('title', 'Document Templates')
@section('page_title', 'Document Templates')

@section('content')

<section class="admin-page">

    <header class="bd-admin-page-head">

        <div class="bd-admin-page-head__copy">

            <p class="bd-admin-kicker">
                OUTPUT ENGINE / TEMPLATES
            </p>

            <h1>
                Document Templates
            </h1>

            <p>
                Template menentukan section structure dan format
                DOCX/PDF tanpa mengubah provenance generation lama.
            </p>

        </div>

        <div class="bd-admin-page-head__actions">

            <a
                href="{{ route('admin.document-templates.create') }}"
                class="bd-admin-button bd-admin-button--primary"
            >
                Template Baru ＋
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
            placeholder="Cari template…"
        >

        <select
            class="bd-admin-select"
            name="document_type"
            data-auto-submit
        >

            <option value="">
                Semua document type
            </option>

            @foreach($documentTypes as $type)

                <option
                    value="{{ $type }}"
                    @selected(
                        request(
                            'document_type'
                        )
                        ===
                        $type
                    )
                >
                    {{ $type }}
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
                    <th>Template</th>
                    <th>Document Type</th>
                    <th>Version</th>
                    <th>Generation</th>
                    <th>Status</th>
                    <th></th>
                </tr>

            </thead>

            <tbody>

                @forelse($templates as $template)

                    <tr>

                        <td>

                            <div class="bd-admin-table__title">

                                <strong>
                                    {{ $template->name }}

                                    @if($template->is_default)
                                        · DEFAULT
                                    @endif
                                </strong>

                                <small>
                                    {{ $template->code }}
                                </small>

                            </div>

                        </td>

                        <td>
                            {{ $template->document_type }}
                        </td>

                        <td>
                            v{{ $template->version }}
                        </td>

                        <td>
                            {{ $template->generations_count }}
                        </td>

                        <td>

                            <span
                                class="
                                    bd-admin-status
                                    bd-admin-status--{{
                                        $template->status === 'active'
                                            ? 'active'
                                            : 'draft'
                                    }}
                                "
                            >
                                {{ ucfirst($template->status) }}
                            </span>

                        </td>

                        <td>

                            <a
                                href="{{ route('admin.document-templates.edit', $template) }}"
                                class="bd-admin-table__action"
                            >
                                Edit →
                            </a>

                        </td>

                    </tr>

                @empty

                    <tr>

                        <td colspan="6">

                            <div class="bd-admin-empty">

                                <div class="bd-admin-empty__inner">

                                    <span class="bd-admin-empty__mark">
                                        DOC
                                    </span>

                                    <h3>
                                        Belum ada Document Template.
                                    </h3>

                                </div>

                            </div>

                        </td>

                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>

    {{ $templates->links() }}

</section>

@endsection