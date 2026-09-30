@extends('layouts.admin')

@section(
    'title',
    'Research Tools — Admin'
)

@section('content')

<section class="admin-page">

    <div
        class="admin-hero compact"
        data-admin-reveal
    >
        <div>
            <p class="eyebrow">
                ADMIN / AI CONFIGURATION
            </p>

            <h1>
                Research Tools.
            </h1>

            <p>
                Kelola jenis pekerjaan research
                yang dapat digunakan user.
                Prompt internal tetap dikelola
                melalui Prompt Library.
            </p>
        </div>

        <a
            href="{{ route(
                'admin.tools.create'
            ) }}"
            class="btn btn-primary"
        >
            Create tool
            <span>+</span>
        </a>
    </div>

    <div
        class="admin-info-banner"
        data-admin-reveal
    >
        <span class="quick-icon">
            T
        </span>

        <div>
            <strong>
                Research Tool ≠ Prompt Version
            </strong>

            <p>
                Tool menjelaskan pekerjaan research
                kepada user. Prompt Version mengontrol
                instruction internal AI dan dapat
                berubah tanpa mengganti identitas tool.
            </p>
        </div>
    </div>

    <form
        method="GET"
        action="{{
            route(
                'admin.tools.index'
            )
        }}"
        class="admin-filter"
        data-admin-reveal
    >
        <label class="filter-field">
            <span>Search</span>

            <input
                type="search"
                name="search"
                maxlength="160"
                value="{{ request('search') }}"
                placeholder="Tool name or code…"
            >
        </label>

        <label class="filter-field">
            <span>Status</span>

            <select name="status">
                <option value="">
                    All status
                </option>

                <option
                    value="active"
                    @selected(
                        request('status')
                            === 'active'
                    )
                >
                    Active
                </option>

                <option
                    value="archived"
                    @selected(
                        request('status')
                            === 'archived'
                    )
                >
                    Archived
                </option>
            </select>
        </label>

        <div></div>

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
        <table
            class="
                admin-table
                admin-table-modern
            "
        >
            <thead>
                <tr>
                    <th>Tool</th>
                    <th>Code</th>
                    <th>Active Prompt</th>
                    <th>Prompt Versions</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>

            <tbody>
                @forelse(
                    $tools
                    as $tool
                )
                    <tr>
                        <td>
                            <strong>
                                {{ $tool->name }}
                            </strong>

                            <small>
                                {{
                                    Str::limit(
                                        $tool->description
                                        ?: 'No description.',
                                        120
                                    )
                                }}
                            </small>
                        </td>

                        <td>
                            <code>
                                {{ $tool->code }}
                            </code>
                        </td>

                        <td>
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
                                </strong>

                                <small>
                                    v{{
                                        $tool
                                            ->activePromptTemplate
                                            ->version
                                    }}
                                </small>
                            @else
                                <span class="status-pill">
                                    None
                                </span>
                            @endif
                        </td>

                        <td>
                            {{
                                $tool
                                    ->prompt_templates_count
                            }}
                        </td>

                        <td>
                            <span
                                class="
                                    status-pill
                                    status-{{
                                        $tool->status
                                    }}
                                "
                            >
                                {{
                                    ucfirst(
                                        $tool->status
                                    )
                                }}
                            </span>
                        </td>

                        <td>
                            <a
                                class="table-link"
                                href="{{
                                    route(
                                        'admin.tools.edit',
                                        $tool
                                    )
                                }}"
                            >
                                Edit
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">
                            <div class="admin-empty">
                                No Research Tools found.
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $tools->links() }}

</section>

@endsection