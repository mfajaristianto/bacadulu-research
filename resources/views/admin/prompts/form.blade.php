@extends('layouts.admin')

@section(
    'title',
    (
        $prompt->exists
            ? 'Edit Prompt'
            : 'Create Prompt'
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
                ADMIN / PROMPT LIBRARY
            </p>

            <h1>
                {{
                    $prompt->exists
                        ? 'Edit prompt.'
                        : 'Create a prompt.'
                }}
            </h1>

            <p>
                Kelola penjelasan yang dilihat user
                dan instruction internal yang digunakan AI.
            </p>
        </div>

        <a
            href="{{ route('admin.prompts.index') }}"
            class="btn btn-secondary"
        >
            Back to prompts
        </a>
    </div>

    @if($errors->any())
        <div class="alert alert-error">
            {{ $errors->first() }}
        </div>
    @endif

    <form
        method="POST"
        action="{{
            $prompt->exists
                ? route(
                    'admin.prompts.update',
                    $prompt
                )
                : route(
                    'admin.prompts.store'
                )
        }}"
        class="admin-form-card"
        data-admin-reveal
    >
        @csrf

        @if($prompt->exists)
            @method('PUT')
        @endif

        <div class="admin-form-section">
            <span class="admin-kicker">
                BASIC INFORMATION
            </span>

            <h2>
                Prompt identity
            </h2>
        </div>

        <div class="admin-form-grid">

            <label>
                English Term / Name

                <input
                    name="name"
                    value="{{
                        old(
                            'name',
                            $prompt->name
                        )
                    }}"
                    placeholder="Research Gap"
                    required
                >
            </label>

            <label>
                Bahasa Indonesia
                <span class="field-optional">
                    optional
                </span>

                <input
                    name="indonesian_name"
                    value="{{
                        old(
                            'indonesian_name',
                            $prompt->indonesian_name
                        )
                    }}"
                    placeholder="Kesenjangan Penelitian"
                >
            </label>

            <label>
                Research Tool

                <select name="ai_tool_id">
                    <option value="">
                        Unassigned
                    </option>

                    @foreach(
                        $tools
                        as $tool
                    )
                        <option
                            value="{{ $tool->id }}"
                            @selected(
                                old(
                                    'ai_tool_id',
                                    $prompt->ai_tool_id
                                )
                                == $tool->id
                            )
                        >
                            {{ $tool->name }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label>
                Version

                <input
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
                    required
                >
            </label>

            <label>
                Status

                <select name="status">
                    <option
                        value="draft"
                        @selected(
                            old(
                                'status',
                                $prompt->status
                            ) === 'draft'
                        )
                    >
                        Draft
                    </option>

                    <option
                        value="active"
                        @selected(
                            old(
                                'status',
                                $prompt->status
                            ) === 'active'
                        )
                    >
                        Active
                    </option>

                    <option
                        value="archived"
                        @selected(
                            old(
                                'status',
                                $prompt->status
                            ) === 'archived'
                        )
                    >
                        Archived
                    </option>
                </select>
            </label>

        </div>

        <div class="admin-form-section">
            <span class="admin-kicker">
                USER-FACING EDUCATION
            </span>

            <h2>
                What users understand
            </h2>

            <p>
                Bagian ini boleh dikirim ke browser.
                Jangan menaruh system prompt,
                API key, secret, atau internal instruction.
            </p>
        </div>

        <label>
            Short Description

            <textarea
                name="short_description"
                rows="3"
                placeholder="Menganalisis keterbatasan dan peluang penelitian berdasarkan evidence yang tersedia."
            >{{ old(
                'short_description',
                $prompt->short_description
            ) }}</textarea>
        </label>

        <label>
            Meaning / Apa maksudnya?

            <textarea
                name="meaning"
                rows="5"
                placeholder="Jelaskan istilah ini dengan bahasa yang mudah dipahami user..."
            >{{ old(
                'meaning',
                $prompt->meaning
            ) }}</textarea>
        </label>

        <label>
            When to Use / Digunakan untuk

            <textarea
                name="when_to_use"
                rows="6"
                placeholder="Gunakan ketika Anda ingin:&#10;- ...&#10;- ..."
            >{{ old(
                'when_to_use',
                $prompt->when_to_use
            ) }}</textarea>
        </label>

        <label>
            When Not to Use

            <textarea
                name="when_not_to_use"
                rows="5"
                placeholder="Jangan gunakan ketika..."
            >{{ old(
                'when_not_to_use',
                $prompt->when_not_to_use
            ) }}</textarea>
        </label>

        <label>
            Example

            <textarea
                name="example_text"
                rows="4"
                placeholder="Identifikasi research gap dari penelitian ESG disclosure dan firm value."
            >{{ old(
                'example_text',
                $prompt->example_text
            ) }}</textarea>
        </label>

        <label>
            Expected Output

            <textarea
                name="expected_output"
                rows="7"
                placeholder="1. Existing findings&#10;2. Limitations&#10;3. Inconsistencies&#10;4. Potential research gap"
            >{{ old(
                'expected_output',
                $prompt->expected_output
            ) }}</textarea>
        </label>

        <div class="admin-form-section">
            <span class="admin-kicker">
                INTERNAL AI INSTRUCTIONS
            </span>

            <h2>
                Controlled research behaviour
            </h2>

            <p>
                Bagian ini hanya untuk server/Admin
                dan tidak boleh ditampilkan mentah
                kepada user.
            </p>
        </div>

        <label>
            System Prompt
            <span class="field-optional">
                optional
            </span>

            <textarea
                name="system_prompt"
                rows="7"
                placeholder="Set the assistant's role and boundaries..."
            >{{ old(
                'system_prompt',
                $prompt->system_prompt
            ) }}</textarea>
        </label>

        <label>
            Task Prompt

            <textarea
                name="task_prompt"
                rows="8"
                placeholder="Analyse the selected internal sources and identify..."
                required
            >{{ old(
                'task_prompt',
                $prompt->task_prompt
            ) }}</textarea>
        </label>

        <label>
            Retrieval Instructions
            <span class="field-optional">
                optional
            </span>

            <textarea
                name="retrieval_instructions"
                rows="6"
                placeholder="Prioritise sources from the selected category..."
            >{{ old(
                'retrieval_instructions',
                $prompt->retrieval_instructions
            ) }}</textarea>
        </label>

        <label>
            Output Instructions
            <span class="field-optional">
                optional
            </span>

            <textarea
                name="output_instructions"
                rows="6"
                placeholder="Return a structured answer with source citations..."
            >{{ old(
                'output_instructions',
                $prompt->output_instructions
            ) }}</textarea>
        </label>

        <div class="admin-form-footer">

            <button
                class="btn btn-primary"
                type="submit"
            >
                {{
                    $prompt->exists
                        ? 'Save changes'
                        : 'Create prompt'
                }}
            </button>

            <a
                class="btn btn-secondary"
                href="{{ route(
                    'admin.prompts.index'
                ) }}"
            >
                Cancel
            </a>

        </div>

    </form>

</section>

@endsection