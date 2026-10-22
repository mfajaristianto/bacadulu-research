@extends('layouts.admin')

@php
    $editing =
        $template->exists;

    $schemaValue =
        old(
            'schema_json',
            json_encode(
                $template->schema_json
                ?? [],
                JSON_PRETTY_PRINT
                |
                JSON_UNESCAPED_UNICODE
                |
                JSON_UNESCAPED_SLASHES
            )
        );
@endphp

@section(
    'title',
    $editing
        ? 'Edit Document Template'
        : 'Document Template Baru'
)

@section('page_title', 'Document Templates')

@section('content')

<section class="admin-page">

    <header class="bd-admin-page-head">

        <div class="bd-admin-page-head__copy">

            <p class="bd-admin-kicker">
                OUTPUT ENGINE / TEMPLATE
            </p>

            <h1>
                {{
                    $editing
                        ? $template->name
                        : 'Template Baru'
                }}
            </h1>

            <p>
                Setiap perubahan pada template menaikkan version.
                Generation lama tetap menyimpan snapshot template lama.
            </p>

        </div>

        <div class="bd-admin-page-head__actions">

            <a
                href="{{ route('admin.document-templates.index') }}"
                class="bd-admin-button bd-admin-button--ghost"
            >
                ← Templates
            </a>

        </div>

    </header>

    <form
        method="POST"
        action="{{
            $editing
                ? route(
                    'admin.document-templates.update',
                    $template
                )
                : route(
                    'admin.document-templates.store'
                )
        }}"
        class="bd-admin-form-shell"
    >

        @csrf

        @if($editing)
            @method('PUT')
        @endif

        <div class="bd-admin-form">

            <section class="bd-admin-form__section">

                <div class="bd-admin-form__section-head">

                    <strong>
                        Identity
                    </strong>

                    <p>
                        Code harus stabil karena masuk provenance generation.
                    </p>

                </div>

                <div class="bd-admin-form__grid">

                    <label class="bd-admin-form-field">

                        <span>
                            Code
                        </span>

                        <input
                            class="bd-admin-input"
                            name="code"
                            value="{{ old('code', $template->code) }}"
                            placeholder="custom-research-article"
                            required
                        >

                    </label>

                    <label class="bd-admin-form-field">

                        <span>
                            Name
                        </span>

                        <input
                            class="bd-admin-input"
                            name="name"
                            value="{{ old('name', $template->name) }}"
                            required
                        >

                    </label>

                    <label class="bd-admin-form-field">

                        <span>
                            Document type
                        </span>

                        <select
                            class="bd-admin-select"
                            name="document_type"
                        >

                            @foreach($documentTypes as $type)

                                <option
                                    value="{{ $type }}"
                                    @selected(
                                        old(
                                            'document_type',
                                            $template->document_type
                                        )
                                        ===
                                        $type
                                    )
                                >
                                    {{ $type }}
                                </option>

                            @endforeach

                        </select>

                    </label>

                    <label class="bd-admin-form-field">

                        <span>
                            Status
                        </span>

                        <select
                            class="bd-admin-select"
                            name="status"
                        >

                            <option
                                value="active"
                                @selected(
                                    old(
                                        'status',
                                        $template->status
                                        ?: 'active'
                                    )
                                    ===
                                    'active'
                                )
                            >
                                Active
                            </option>

                            <option
                                value="inactive"
                                @selected(
                                    old(
                                        'status',
                                        $template->status
                                    )
                                    ===
                                    'inactive'
                                )
                            >
                                Inactive
                            </option>

                        </select>

                    </label>

                    <label class="bd-admin-form-field is-full">

                        <span>
                            Description
                        </span>

                        <textarea
                            class="bd-admin-input"
                            name="description"
                            rows="3"
                        >{{ old('description', $template->description) }}</textarea>

                    </label>

                    <label class="bd-admin-form-field is-full">

                        <input
                            type="hidden"
                            name="is_default"
                            value="0"
                        >

                        <span
                            style="
                                display:flex;
                                align-items:center;
                                gap:10px;
                            "
                        >
                            <input
                                type="checkbox"
                                name="is_default"
                                value="1"
                                @checked(
                                    old(
                                        'is_default',
                                        $template->is_default
                                    )
                                )
                            >

                            Jadikan default template untuk document type ini
                        </span>

                    </label>

                </div>

            </section>

            <section class="bd-admin-form__section">

                <div class="bd-admin-form__section-head">

                    <strong>
                        Schema JSON
                    </strong>

                    <p>
                        Role yang diizinkan:
                        research_result,
                        evidence,
                        method_note,
                        conclusion,
                        draft.
                    </p>

                </div>

                <label class="bd-admin-form-field is-full">

                    <textarea
                        class="bd-admin-input"
                        name="schema_json"
                        rows="28"
                        spellcheck="false"
                        style="
                            font-family:
                                ui-monospace,
                                SFMono-Regular,
                                Menlo,
                                Consolas,
                                monospace;
                            line-height:1.55;
                        "
                        required
                    >{{ $schemaValue }}</textarea>

                </label>

            </section>

            <section class="bd-admin-form__section">

                <button
                    type="submit"
                    class="bd-admin-button bd-admin-button--primary"
                >
                    {{
                        $editing
                            ? 'Simpan sebagai Version Baru'
                            : 'Buat Template'
                    }}
                </button>

            </section>

        </div>

    </form>

    @if(
        $editing
        &&
        ! $template->is_default
        &&
        $template->status === 'active'
    )

        <form
            method="POST"
            action="{{ route('admin.document-templates.destroy', $template) }}"
            style="margin-top:18px;"
        >

            @csrf
            @method('DELETE')

            <button
                type="submit"
                class="bd-admin-button bd-admin-button--ghost"
            >
                Nonaktifkan Template
            </button>

        </form>

    @endif

</section>

@endsection