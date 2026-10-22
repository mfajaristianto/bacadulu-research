@extends('layouts.admin')

@php
    $editing =
        $term->exists;

    $aliases =
        old(
            'aliases',
            collect(
                $term->aliases_json
                ?? []
            )->implode(', ')
        );
@endphp

@section(
    'title',
    $editing
        ? 'Edit Glossary'
        : 'Tambah Glossary'
)

@section('page_title', 'Glossary')

@section('content')

<section class="admin-page">

    <header class="bd-admin-page-head">

        <div class="bd-admin-page-head__copy">

            <p class="bd-admin-kicker">
                KNOWLEDGE / GLOSSARY
            </p>

            <h1>
                {{
                    $editing
                        ? $term->term
                        : 'Istilah Baru'
                }}
            </h1>

            <p>
                Glossary memberikan definisi terminology.
                Jangan masukkan hasil penelitian atau klaim empiris
                sebagai glossary.
            </p>

        </div>

        <div class="bd-admin-page-head__actions">

            <a
                href="{{ route('admin.glossary.index') }}"
                class="bd-admin-button bd-admin-button--ghost"
            >
                ← Glossary
            </a>

        </div>

    </header>

    <form
        method="POST"
        action="{{
            $editing
                ? route(
                    'admin.glossary.update',
                    $term
                )
                : route(
                    'admin.glossary.store'
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
                        Terminology
                    </strong>

                    <p>
                        Global berarti dapat dipakai lintas category.
                    </p>

                </div>

                <div class="bd-admin-form__grid">

                    <label class="bd-admin-form-field">

                        <span>
                            Term
                        </span>

                        <input
                            class="bd-admin-input"
                            name="term"
                            maxlength="180"
                            value="{{
                                old(
                                    'term',
                                    $term->term
                                )
                            }}"
                            required
                        >

                    </label>

                    <label class="bd-admin-form-field">

                        <span>
                            Category
                        </span>

                        <select
                            class="bd-admin-select"
                            name="category_id"
                        >

                            <option value="">
                                Global
                            </option>

                            @foreach($categories as $category)

                                <option
                                    value="{{ $category->id }}"
                                    @selected(
                                        (string)
                                            old(
                                                'category_id',
                                                $term->category_id
                                            )
                                        ===
                                        (string)
                                            $category->id
                                    )
                                >
                                    {{ $category->name }}
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
                                        $term->status
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
                                        $term->status
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
                            Aliases
                        </span>

                        <textarea
                            class="bd-admin-input"
                            name="aliases"
                            rows="3"
                            placeholder="ESG disclosure, environmental social governance"
                        >{{ $aliases }}</textarea>

                        <small>
                            Pisahkan menggunakan koma, titik koma, atau baris baru.
                        </small>

                    </label>

                    <label class="bd-admin-form-field is-full">

                        <span>
                            Definition
                        </span>

                        <textarea
                            class="bd-admin-input"
                            name="definition"
                            rows="7"
                            required
                        >{{ old('definition', $term->definition) }}</textarea>

                    </label>

                    <label class="bd-admin-form-field is-full">

                        <span>
                            Context note
                            <small>opsional</small>
                        </span>

                        <textarea
                            class="bd-admin-input"
                            name="context_note"
                            rows="5"
                            placeholder="Contoh: bedakan ESG disclosure dari ESG performance."
                        >{{ old('context_note', $term->context_note) }}</textarea>

                    </label>

                </div>

            </section>

            <section class="bd-admin-form__section">

                <div class="bd-admin-inline-actions">

                    <button
                        class="bd-admin-button bd-admin-button--primary"
                        type="submit"
                    >
                        {{
                            $editing
                                ? 'Simpan Perubahan'
                                : 'Buat Istilah'
                        }}
                    </button>

                </div>

            </section>

        </div>

    </form>

    @if(
        $editing
        &&
        $term->status === 'active'
    )

        <form
            method="POST"
            action="{{ route('admin.glossary.destroy', $term) }}"
            style="margin-top:18px;"
        >

            @csrf
            @method('DELETE')

            <button
                type="submit"
                class="bd-admin-button bd-admin-button--ghost"
            >
                Nonaktifkan Istilah
            </button>

        </form>

    @endif

</section>

@endsection