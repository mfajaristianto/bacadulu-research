@extends('layouts.admin')

@section('title', 'Prompts')
@section('page_title', 'Prompts')

@php
    $promptStatuses = [
        'draft',
        'active',
        'archived',
    ];
@endphp

@section('content')

<section class="admin-page">

    <header class="bd-admin-page-head">

        <div
            class="bd-admin-page-head__copy"
            data-admin-reveal
        >
            <p class="bd-admin-kicker">
                AI CONTROL / PROMPTS
            </p>

            <h1>
                Prompt Manager
            </h1>

            <p>
                Atur bagaimana setiap Research Tool memahami pertanyaan,
                memilih evidence, dan menyusun jawaban.
            </p>
        </div>

        <div
            class="bd-admin-page-head__actions"
            data-admin-reveal
            data-reveal-from="right"
        >
            <a
                href="{{ route('admin.tools.index') }}"
                class="
                    bd-admin-button
                    bd-admin-button--ghost
                "
            >
                Research Tools
            </a>

            <a
                href="{{ route('admin.prompt-versions.index') }}"
                class="
                    bd-admin-button
                    bd-admin-button--ghost
                "
            >
                Version History
            </a>

            <a
                href="{{ route('admin.prompts.create') }}"
                class="
                    bd-admin-button
                    bd-admin-button--primary
                "
            >
                Prompt Baru
                <span aria-hidden="true">＋</span>
            </a>
        </div>

    </header>

    <form
        method="GET"
        action="{{ route('admin.prompts.index') }}"
        class="bd-admin-filter"
        data-admin-reveal
        data-reveal-delay="60"
    >
        <input
            class="bd-admin-input"
            type="search"
            name="search"
            value="{{ request('search') }}"
            maxlength="160"
            placeholder="Cari prompt, deskripsi, atau instruction…"
            aria-label="Cari prompt"
        >

        <select
            class="bd-admin-select"
            name="status"
            data-auto-submit
            aria-label="Filter status prompt"
        >
            <option value="">
                Semua status
            </option>

            @foreach ($promptStatuses as $status)
                <option
                    value="{{ $status }}"
                    @selected(request('status') === $status)
                >
                    {{ ucfirst($status) }}
                </option>
            @endforeach
        </select>

        <span></span>
    </form>

    @if ($prompts->isEmpty())

        <div
            class="bd-admin-empty"
            data-admin-reveal
            data-reveal-delay="100"
        >
            <div class="bd-admin-empty__inner">

                <span class="bd-admin-empty__mark">
                    PROMPT SYSTEM
                </span>

                <h3>
                    Belum ada prompt.
                </h3>

                <p>
                    Buat Prompt Version pertama, hubungkan ke Research Tool,
                    lalu aktifkan version yang akan digunakan oleh pipeline AI.
                </p>

                <div class="bd-admin-empty__actions">

                    <a
                        href="{{ route('admin.prompts.create') }}"
                        class="
                            bd-admin-button
                            bd-admin-button--primary
                        "
                    >
                        Buat Prompt
                    </a>

                    <a
                        href="{{ route('admin.tools.index') }}"
                        class="
                            bd-admin-button
                            bd-admin-button--ghost
                        "
                    >
                        Lihat Research Tools
                    </a>

                </div>

            </div>
        </div>

    @else

        <div
            class="bd-admin-table-wrap"
            data-admin-reveal
            data-reveal-delay="100"
        >

            <table class="bd-admin-table">

                <thead>
                    <tr>
                        <th>Prompt</th>
                        <th>Research Tool</th>
                        <th>Version</th>
                        <th>Status</th>
                        <th>Updated</th>
                        <th></th>
                    </tr>
                </thead>

                <tbody>

                    @forelse ($prompts as $prompt)

                        <tr>

                            <td>
                                <div class="bd-admin-table__title">

                                    <strong>
                                        {{ $prompt->display_name }}
                                    </strong>

                                    <small>
                                        {{
                                            \Illuminate\Support\Str::limit(
                                                $prompt->short_description
                                                ?: $prompt->task_prompt,
                                                110
                                            )
                                        }}
                                    </small>

                                </div>
                            </td>

                            <td>
                                {{
                                    $prompt->aiTool?->name
                                    ?: 'Unassigned'
                                }}
                            </td>

                            <td>
                                v{{ $prompt->version }}
                            </td>

                            <td>
                                <span
                                    class="
                                        bd-admin-status
                                        bd-admin-status--{{ $prompt->status }}
                                    "
                                >
                                    {{ ucfirst($prompt->status) }}
                                </span>
                            </td>

                            <td>
                                {{
                                    optional(
                                        $prompt->updated_at
                                    )->format('d M Y')
                                }}
                            </td>

                            <td>
                                <a
                                    class="bd-admin-table__action"
                                    href="{{
                                        route(
                                            'admin.prompts.edit',
                                            $prompt
                                        )
                                    }}"
                                >
                                    {{
                                        $prompt->status === 'active'
                                        ? 'Lihat'
                                        : 'Edit'
                                    }}
                                    →
                                </a>
                            </td>

                        </tr>

                    @empty

                        <tr>
                            <td colspan="6">
                                Belum ada prompt.
                            </td>
                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

        <div style="margin-top:18px;">
            {{ $prompts->links() }}
        </div>

    @endif

</section>

@endsection