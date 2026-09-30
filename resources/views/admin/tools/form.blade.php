@extends('layouts.admin')

@section(
    'title',
    (
        $tool->exists
            ? 'Edit Research Tool'
            : 'Create Research Tool'
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
                ADMIN / RESEARCH TOOLS
            </p>

            <h1>
                {{
                    $tool->exists
                        ? 'Edit research tool.'
                        : 'Create research tool.'
                }}
            </h1>

            <p>
                Research Tool adalah kemampuan
                yang dilihat user.
                Internal prompt dikelola terpisah.
            </p>
        </div>

        <a
            href="{{
                route(
                    'admin.tools.index'
                )
            }}"
            class="btn btn-secondary"
        >
            Back to tools
        </a>
    </div>

    @if($errors->any())
        <div
            class="alert alert-error"
            role="alert"
        >
            {{ $errors->first() }}
        </div>
    @endif

    <form
        method="POST"
        action="{{
            $tool->exists
                ? route(
                    'admin.tools.update',
                    $tool
                )
                : route(
                    'admin.tools.store'
                )
        }}"
        class="admin-form-card"
        data-admin-reveal
    >
        @csrf

        @if($tool->exists)
            @method('PUT')
        @endif

        <div class="admin-form-section">
            <span class="admin-kicker">
                TOOL IDENTITY
            </span>

            <h2>
                User-facing research capability
            </h2>

            <p>
                Nama dan deskripsi tool dapat
                terlihat di Research Workspace.
            </p>
        </div>

        <div class="admin-form-grid">

            <label>
                Name

                <input
                    type="text"
                    name="name"
                    maxlength="180"
                    value="{{
                        old(
                            'name',
                            $tool->name
                        )
                    }}"
                    placeholder="Literature Synthesis"
                    required
                >
            </label>

            <label>
                Code

                <input
                    type="text"
                    name="code"
                    maxlength="120"
                    value="{{
                        old(
                            'code',
                            $tool->code
                        )
                    }}"
                    placeholder="literature-synthesis"
                    required
                >

                <small class="field-help">
                    Lowercase dan dash saja.
                    Contoh:
                    literature-synthesis
                </small>
            </label>

            <label>
                Short Mark / Icon
                <span class="field-optional">
                    optional
                </span>

                <input
                    type="text"
                    name="icon"
                    maxlength="40"
                    value="{{
                        old(
                            'icon',
                            $tool->icon
                        )
                    }}"
                    placeholder="LS"
                >
            </label>

            <label>
                Status

                <select
                    name="status"
                    required
                >
                    <option
                        value="active"
                        @selected(
                            old(
                                'status',
                                $tool->status
                                    ?: 'active'
                            )
                            === 'active'
                        )
                    >
                        Active
                    </option>

                    <option
                        value="archived"
                        @selected(
                            old(
                                'status',
                                $tool->status
                            )
                            === 'archived'
                        )
                    >
                        Archived
                    </option>
                </select>
            </label>

            <label class="full">
                Description

                <textarea
                    name="description"
                    rows="5"
                    maxlength="2000"
                    placeholder="Jelaskan fungsi Research Tool kepada user..."
                >{{ old(
                    'description',
                    $tool->description
                ) }}</textarea>
            </label>

        </div>

        @if($tool->exists)

            <div class="admin-form-section">
                <span class="admin-kicker">
                    ACTIVE PROMPT
                </span>

                <h2>
                    Current configuration
                </h2>
            </div>

            <div class="admin-info-banner">

                <span class="quick-icon">
                    P
                </span>

                <div>
                    @if(
                        $tool
                            ->activePromptTemplate
                    )
                        <strong>
                            {{
                                $tool
                                    ->activePromptTemplate
                                    ->name
                            }}
                            · v{{
                                $tool
                                    ->activePromptTemplate
                                    ->version
                            }}
                        </strong>

                        <p>
                            Prompt aktif diubah melalui
                            Prompt Library. Mengedit Tool
                            tidak mengubah system prompt.
                        </p>
                    @else
                        <strong>
                            Belum ada active prompt.
                        </strong>

                        <p>
                            Buat Prompt Version lalu
                            pilih Research Tool ini
                            dan ubah status prompt
                            menjadi Active.
                        </p>
                    @endif
                </div>

            </div>

            <a
                href="{{
                    route(
                        'admin.prompts.index',
                        [
                            'search'
                                => $tool->name,
                        ]
                    )
                }}"
                class="btn btn-secondary"
            >
                Open Prompt Library
            </a>

        @endif

        <div class="admin-form-footer">

            <button
                type="submit"
                class="btn btn-primary"
            >
                {{
                    $tool->exists
                        ? 'Save changes'
                        : 'Create tool'
                }}
            </button>

            <a
                href="{{
                    route(
                        'admin.tools.index'
                    )
                }}"
                class="btn btn-secondary"
            >
                Cancel
            </a>

        </div>

    </form>

</section>

@endsection