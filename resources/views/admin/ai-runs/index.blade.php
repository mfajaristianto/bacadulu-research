@extends('layouts.admin')

@section(
    'title',
    'AI Runs — Admin'
)

@section('content')

<section class="admin-page">

    <div
        class="admin-hero compact"
        data-admin-reveal
    >
        <div>
            <p class="eyebrow">
                ADMIN / AI RUNS
            </p>

            <h1>
                Research execution audit.
            </h1>

            <p>
                Lihat bagaimana sebuah research request
                diproses dari Research Tool dan Prompt
                menuju retrieval, evidence, citation
                validation, dan final output.
            </p>
        </div>
    </div>

    <div
        class="admin-info-banner"
        data-admin-reveal
    >
        <span class="quick-icon">
            A
        </span>

        <div>
            <strong>
                AI Runs adalah audit trail.
            </strong>

            <p>
                Data pada halaman ini bersifat read-only.
                Hasil run, evidence, dan citation tidak
                diedit setelah proses selesai.
            </p>
        </div>
    </div>

    <form
        method="GET"
        action="{{
            route(
                'admin.ai-runs.index'
            )
        }}"
        class="
            admin-filter
            admin-run-filter
        "
        data-admin-reveal
    >

        <label class="filter-field">
            <span>
                Search
            </span>

            <input
                type="search"
                name="search"
                maxlength="160"
                value="{{
                    request(
                        'search'
                    )
                }}"
                placeholder="UUID, user, tool, prompt…"
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
                        'queued',
                        'retrieving',
                        'evidence_ready',
                        'generating',
                        'completed',
                        'insufficient_evidence',
                        'citation_failed',
                        'failed',
                    ]
                    as $status
                )

                    <option
                        value="{{ $status }}"
                        @selected(
                            request(
                                'status'
                            )
                            === $status
                        )
                    >
                        {{
                            str(
                                $status
                            )
                                ->replace(
                                    '_',
                                    ' '
                                )
                                ->title()
                        }}
                    </option>

                @endforeach

            </select>
        </label>

        <label class="filter-field">
            <span>
                Provider
            </span>

            <select name="provider">

                <option value="">
                    All provider
                </option>

                @foreach(
                    $providers
                    as $provider
                )

                    <option
                        value="{{ $provider }}"
                        @selected(
                            request(
                                'provider'
                            )
                            === $provider
                        )
                    >
                        {{ $provider }}
                    </option>

                @endforeach

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
        <table class="admin-table admin-run-table">

            <thead>
                <tr>
                    <th>Run</th>
                    <th>User</th>
                    <th>Research Tool</th>
                    <th>Evidence</th>
                    <th>Citations</th>
                    <th>Provider</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>

            <tbody>

            @forelse(
                $runs
                as $run
            )

                @php
                    $conversation =
                        $run
                            ->assistantMessage
                            ?->conversation;
                @endphp

                <tr>

                    <td>
                        <strong>
                            #{{
                                $run->id
                            }}
                        </strong>

                        <small>
                            {{
                                \Illuminate\Support\Str::limit(
                                    $run->uuid,
                                    22,
                                    '…'
                                )
                            }}
                        </small>

                        <small>
                            {{
                                $run
                                    ->created_at
                                    ?->format(
                                        'd M Y · H:i'
                                    )
                            }}
                        </small>
                    </td>

                    <td>
                        <strong>
                            {{
                                $run
                                    ->user
                                    ?->name
                                ?: 'Unknown user'
                            }}
                        </strong>

                        <small>
                            {{
                                $run
                                    ->user
                                    ?->email
                                ?: '—'
                            }}
                        </small>
                    </td>

                    <td>
                        <strong>
                            {{
                                $run
                                    ->aiTool
                                    ?->name
                                ?: '—'
                            }}
                        </strong>

                        <small>
                            {{
                                $run
                                    ->promptTemplate
                                    ?->name
                                ?: 'No prompt'
                            }}

                            @if(
                                $run
                                    ->promptTemplate
                                    ?->version
                            )
                                · v{{
                                    $run
                                        ->promptTemplate
                                        ->version
                                }}
                            @endif
                        </small>

                        @if($conversation)
                            <small>
                                {{
                                    $conversation
                                        ->title
                                }}
                            </small>
                        @endif
                    </td>

                    <td>
                        <strong>
                            {{
                                $run
                                    ->retrievals_count
                            }}
                            chunks
                        </strong>

                        <small>
                            {{
                                $run
                                    ->sources_count
                            }}
                            sources
                        </small>
                    </td>

                    <td>
                        <strong>
                            {{
                                $run
                                    ->valid_citations_count
                            }}
                            valid
                        </strong>

                        @if(
                            $run
                                ->invalid_citations_count
                            > 0
                        )
                            <small class="admin-danger-text">
                                {{
                                    $run
                                        ->invalid_citations_count
                                }}
                                invalid
                            </small>
                        @else
                            <small>
                                no invalid citation
                            </small>
                        @endif
                    </td>

                    <td>
                        <strong>
                            {{
                                $run
                                    ->model_provider
                                ?: '—'
                            }}
                        </strong>

                        <small>
                            {{
                                $run
                                    ->model_name
                                ?: 'No model'
                            }}
                        </small>
                    </td>

                    <td>
                        <span
                            class="
                                status-pill
                                status-{{
                                    $run->status
                                }}
                            "
                        >
                            {{
                                str(
                                    $run->status
                                )
                                    ->replace(
                                        '_',
                                        ' '
                                    )
                                    ->title()
                            }}
                        </span>
                    </td>

                    <td>
                        <a
                            class="table-link"
                            href="{{
                                route(
                                    'admin.ai-runs.show',
                                    $run
                                )
                            }}"
                        >
                            Audit →
                        </a>
                    </td>

                </tr>

            @empty

                <tr>
                    <td colspan="8">

                        <div class="admin-empty">
                            Belum ada AI Run.
                        </div>

                    </td>
                </tr>

            @endforelse

            </tbody>

        </table>
    </div>

    <div class="admin-pagination">
        {{
            $runs->links()
        }}
    </div>

</section>

@endsection