@extends('layouts.admin')

@php
    $editing = $prompt->exists;

    $locked =
        $editing
        && $prompt->status === 'active';

    $promptStatuses = [
        'draft',
        'active',
        'archived',
    ];
@endphp

@section(
    'title',
    $editing
        ? 'Prompt Editor'
        : 'Prompt Baru'
)

@section('page_title', 'Prompts')

@section('content')

<section class="admin-page">

    <header class="bd-admin-page-head">

        <div
            class="bd-admin-page-head__copy"
            data-admin-reveal
        >
            <p class="bd-admin-kicker">
                AI CONTROL / PROMPT EDITOR
            </p>

            <h1>
                {{
                    $editing
                        ? $prompt->name.' · v'.$prompt->version
                        : 'Prompt Baru'
                }}
            </h1>

            <p>
                {{
                    $locked
                        ? 'Version aktif dikunci. Buat version baru untuk mengubah instruction tanpa merusak provenance AI Run lama.'
                        : 'Atur question template, instruction internal, retrieval behaviour, output, dan edukasi yang akan dilihat researcher.'
                }}
            </p>
        </div>


        <div
            class="bd-admin-page-head__actions"
            data-admin-reveal
            data-reveal-from="right"
        >

            @if ($editing)

                <form
                    method="POST"
                    action="{{
                        route(
                            'admin.prompt-versions.duplicate',
                            $prompt
                        )
                    }}"
                >
                    @csrf

                    <button
                        class="
                            bd-admin-button
                            bd-admin-button--ghost
                        "
                        type="submit"
                    >
                        Buat Versi Baru
                    </button>
                </form>


                @if (! $locked)

                    <form
                        method="POST"
                        action="{{
                            route(
                                'admin.prompt-versions.activate',
                                $prompt
                            )
                        }}"
                    >
                        @csrf
                        @method('PATCH')

                        <button
                            class="
                                bd-admin-button
                                bd-admin-button--coral
                            "
                            type="submit"
                        >
                            Activate Version
                        </button>
                    </form>

                @endif

            @endif


            <a
                href="{{ route('admin.prompts.index') }}"
                class="
                    bd-admin-button
                    bd-admin-button--ghost
                "
            >
                ← Prompts
            </a>

        </div>

    </header>


    @if ($locked)

        <div
            class="
                bd-admin__flash
                bd-admin__flash--success
            "
            role="status"
        >
            <span></span>

            <p>
                Ini adalah version aktif.
                Instruction dikunci supaya AI Run lama tetap dapat
                ditelusuri ke Prompt Version yang sama.
                Untuk mengubah prompt, buat version baru.
            </p>
        </div>

    @endif


    @if ($errors->any())

        <div
            class="
                bd-admin__flash
                bd-admin__flash--warning
            "
            role="alert"
        >
            <span></span>

            <div>
                <strong>
                    Prompt belum dapat disimpan.
                </strong>

                <ul style="margin:8px 0 0;padding-left:18px;">
                    @foreach ($errors->all() as $error)
                        <li>
                            {{ $error }}
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>

    @endif


    <div
        class="bd-admin-prompt-layout"
        data-admin-reveal
        data-reveal-delay="70"
    >

        <aside
            class="bd-admin-prompt-versions"
            aria-label="Prompt version history"
        >

            <div class="bd-admin-prompt-versions__head">
                <strong>
                    VERSION HISTORY
                </strong>
            </div>


            @forelse ($versions as $version)

                <a
                    href="{{
                        route(
                            'admin.prompts.edit',
                            $version
                        )
                    }}"
                    class="
                        bd-admin-prompt-version
                        {{
                            $editing
                            && $version->id === $prompt->id
                                ? 'is-active'
                                : ''
                        }}
                    "
                >
                    <span>
                        v{{ $version->version }}
                    </span>

                    <span>
                        {{ ucfirst($version->status) }}
                    </span>
                </a>

            @empty

                <div
                    class="
                        bd-admin-prompt-version
                        is-active
                    "
                >
                    <span>
                        New
                    </span>

                    <span>
                        Draft
                    </span>
                </div>

            @endforelse

        </aside>


        <form
            method="POST"
            action="{{
                $editing
                    ? route(
                        'admin.prompts.update',
                        $prompt
                    )
                    : route(
                        'admin.prompts.store'
                    )
            }}"
            class="bd-admin-prompt-editor"
        >

            @csrf

            @if ($editing)
                @method('PUT')
            @endif


            {{-- =========================================================
                 IDENTITY
                 ========================================================= --}}

            <section class="bd-admin-form__section">

                <div class="bd-admin-form__section-head">

                    <strong>
                        Identity
                    </strong>

                    <p>
                        Hubungkan Prompt Version ke Research Tool.
                    </p>

                </div>


                <div class="bd-admin-form__grid">

                    <label class="bd-admin-form-field">

                        <span>
                            Research Tool
                        </span>

                        <select
                            class="bd-admin-select"
                            name="ai_tool_id"
                            @disabled($locked)
                        >
                            <option value="">
                                Unassigned
                            </option>

                            @foreach ($tools as $tool)

                                <option
                                    value="{{ $tool->id }}"
                                    @selected(
                                        (string) old(
                                            'ai_tool_id',
                                            $prompt->ai_tool_id
                                        )
                                        ===
                                        (string) $tool->id
                                    )
                                >
                                    {{ $tool->name }}
                                </option>

                            @endforeach
                        </select>


                        @if ($locked)

                            <input
                                type="hidden"
                                name="ai_tool_id"
                                value="{{ $prompt->ai_tool_id }}"
                            >

                        @endif

                    </label>


                    <label class="bd-admin-form-field">

                        <span>
                            Version
                        </span>

                        <input
                            class="bd-admin-input"
                            type="number"
                            min="1"
                            max="9999"
                            name="version"
                            value="{{
                                old(
                                    'version',
                                    $prompt->version ?: 1
                                )
                            }}"
                            @readonly($locked)
                            required
                        >

                    </label>


                    <label
                        class="
                            bd-admin-form-field
                            is-full
                        "
                    >

                        <span>
                            English Name
                        </span>

                        <input
                            class="bd-admin-input"
                            type="text"
                            name="name"
                            value="{{
                                old(
                                    'name',
                                    $prompt->name
                                )
                            }}"
                            maxlength="180"
                            placeholder="Research Gap"
                            @readonly($locked)
                            required
                        >

                    </label>


                    <label class="bd-admin-form-field">

                        <span>
                            Nama Indonesia
                            <small>
                                opsional
                            </small>
                        </span>

                        <input
                            class="bd-admin-input"
                            type="text"
                            name="indonesian_name"
                            value="{{
                                old(
                                    'indonesian_name',
                                    $prompt->indonesian_name
                                )
                            }}"
                            maxlength="180"
                            placeholder="Kesenjangan Penelitian"
                            @readonly($locked)
                        >

                    </label>


                    <label class="bd-admin-form-field">

                        <span>
                            Status
                        </span>

                        <select
                            class="bd-admin-select"
                            name="status"
                            @disabled($locked)
                        >

                            @foreach ($promptStatuses as $status)

                                <option
                                    value="{{ $status }}"
                                    @selected(
                                        old(
                                            'status',
                                            $prompt->status ?: 'draft'
                                        )
                                        ===
                                        $status
                                    )
                                >
                                    {{ ucfirst($status) }}
                                </option>

                            @endforeach

                        </select>


                        @if ($locked)

                            <input
                                type="hidden"
                                name="status"
                                value="active"
                            >

                        @endif

                    </label>


                    <label
                        class="
                            bd-admin-form-field
                            is-full
                        "
                    >

                        <span>
                            Short Description
                            <small>
                                user-facing
                            </small>
                        </span>

                        <textarea
                            class="bd-admin-textarea"
                            name="short_description"
                            maxlength="1000"
                            placeholder="Penjelasan singkat fungsi prompt untuk researcher."
                            @readonly($locked)
                        >{{ old('short_description', $prompt->short_description) }}</textarea>

                    </label>

                </div>

            </section>


            {{-- =========================================================
                 QUESTION TEMPLATE
                 ========================================================= --}}

            <section class="bd-admin-form__section">

                <div class="bd-admin-form__section-head">

                    <strong>
                        Prompt Pertanyaan User
                    </strong>

                    <p>
                        Starter prompt yang otomatis masuk ke composer
                        ketika researcher memilih prompt ini.
                        User tetap dapat mengedit pertanyaannya sebelum dikirim.
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
                            Question Template
                            <small>
                                user-facing
                            </small>
                        </span>

                        <textarea
                            class="
                                bd-admin-textarea
                                bd-admin-codearea
                            "
                            name="question_template"
                            maxlength="5000"
                            placeholder="Contoh: Identifikasi research gap mengenai {topik} berdasarkan evidence yang tersedia."
                            @readonly($locked)
                        >{{ old('question_template', $prompt->question_template) }}</textarea>

                        <small
                            style="
                                display:block;
                                margin-top:8px;
                                line-height:1.6;
                            "
                        >
                            Gunakan placeholder seperti
                            <strong>{topik}</strong>,
                            <strong>{objek}</strong>,
                            atau
                            <strong>{fokus}</strong>.
                            Placeholder pertama akan otomatis terseleksi
                            saat template masuk ke composer.
                        </small>

                    </label>

                </div>

            </section>


            {{-- =========================================================
                 INTERNAL AI PROMPT
                 ========================================================= --}}

            <section class="bd-admin-form__section">

                <div class="bd-admin-form__section-head">

                    <strong>
                        Prompt Jawaban AI
                    </strong>

                    <p>
                        Bagian internal ini mengatur cara AI bekerja.
                        User tidak melihat isinya secara mentah.
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
                            System Prompt
                        </span>

                        <textarea
                            class="
                                bd-admin-textarea
                                bd-admin-codearea
                            "
                            name="system_prompt"
                            maxlength="20000"
                            placeholder="Peran, batasan, dan behaviour utama AI..."
                            @readonly($locked)
                        >{{ old('system_prompt', $prompt->system_prompt) }}</textarea>

                    </label>


                    <label
                        class="
                            bd-admin-form-field
                            is-full
                        "
                    >

                        <span>
                            Task Prompt
                            <small>
                                wajib
                            </small>
                        </span>

                        <textarea
                            class="
                                bd-admin-textarea
                                bd-admin-codearea
                            "
                            name="task_prompt"
                            maxlength="30000"
                            placeholder="Apa pekerjaan akademik yang harus dilakukan AI..."
                            required
                            @readonly($locked)
                        >{{ old('task_prompt', $prompt->task_prompt) }}</textarea>

                    </label>


                    <label
                        class="
                            bd-admin-form-field
                            is-full
                        "
                    >

                        <span>
                            Retrieval Instructions
                        </span>

                        <textarea
                            class="
                                bd-admin-textarea
                                bd-admin-codearea
                            "
                            name="retrieval_instructions"
                            maxlength="20000"
                            placeholder="Evidence seperti apa yang harus diprioritaskan..."
                            @readonly($locked)
                        >{{ old('retrieval_instructions', $prompt->retrieval_instructions) }}</textarea>

                    </label>


                    <label
                        class="
                            bd-admin-form-field
                            is-full
                        "
                    >

                        <span>
                            Output Instructions
                        </span>

                        <textarea
                            class="
                                bd-admin-textarea
                                bd-admin-codearea
                            "
                            name="output_instructions"
                            maxlength="20000"
                            placeholder="Struktur dan format jawaban..."
                            @readonly($locked)
                        >{{ old('output_instructions', $prompt->output_instructions) }}</textarea>

                    </label>

                </div>

            </section>


            {{-- =========================================================
                 USER EDUCATION
                 ========================================================= --}}

            <section class="bd-admin-form__section">

                <div class="bd-admin-form__section-head">

                    <strong>
                        User-facing Education
                    </strong>

                    <p>
                        Informasi yang membantu researcher memahami
                        fungsi prompt sebelum menggunakannya.
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
                            Apa maksudnya?
                        </span>

                        <textarea
                            class="bd-admin-textarea"
                            name="meaning"
                            maxlength="5000"
                            @readonly($locked)
                        >{{ old('meaning', $prompt->meaning) }}</textarea>

                    </label>


                    <label class="bd-admin-form-field">

                        <span>
                            Digunakan untuk
                        </span>

                        <textarea
                            class="bd-admin-textarea"
                            name="when_to_use"
                            maxlength="5000"
                            @readonly($locked)
                        >{{ old('when_to_use', $prompt->when_to_use) }}</textarea>

                    </label>


                    <label class="bd-admin-form-field">

                        <span>
                            Jangan digunakan untuk
                        </span>

                        <textarea
                            class="bd-admin-textarea"
                            name="when_not_to_use"
                            maxlength="5000"
                            @readonly($locked)
                        >{{ old('when_not_to_use', $prompt->when_not_to_use) }}</textarea>

                    </label>


                    <label class="bd-admin-form-field">

                        <span>
                            Contoh Pertanyaan
                        </span>

                        <textarea
                            class="bd-admin-textarea"
                            name="example_text"
                            maxlength="5000"
                            @readonly($locked)
                        >{{ old('example_text', $prompt->example_text) }}</textarea>

                    </label>


                    <label class="bd-admin-form-field">

                        <span>
                            Expected Output
                        </span>

                        <textarea
                            class="bd-admin-textarea"
                            name="expected_output"
                            maxlength="5000"
                            @readonly($locked)
                        >{{ old('expected_output', $prompt->expected_output) }}</textarea>

                    </label>

                </div>

            </section>


            {{-- =========================================================
                 ACTIONS
                 ========================================================= --}}

            @if (! $locked)

                <section class="bd-admin-form__section">

                    <div
                        class="bd-admin-inline-actions"
                        style="justify-content:flex-start;"
                    >

                        <button
                            class="
                                bd-admin-button
                                bd-admin-button--primary
                            "
                            type="submit"
                        >
                            {{
                                $editing
                                    ? 'Simpan Draft'
                                    : 'Buat Prompt'
                            }}
                        </button>


                        <a
                            href="{{ route('admin.prompts.index') }}"
                            class="
                                bd-admin-button
                                bd-admin-button--ghost
                            "
                        >
                            Batal
                        </a>

                    </div>

                </section>

            @endif

        </form>

    </div>

</section>

@endsection