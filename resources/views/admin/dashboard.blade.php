@extends('layouts.admin')

@section('title', 'Research Operations')
@section('page_title', 'Overview')

@section('content')

<section class="admin-page">

    <header class="bd-admin-page-head">

        <div
            class="bd-admin-page-head__copy"
            data-admin-reveal
        >
            <p class="bd-admin-kicker">
                RESEARCH OPERATIONS
            </p>

            <h1>
                Operasi research yang perlu diperhatikan hari ini.
            </h1>

            <p>
                Fokus pada source, prompt, dan AI run yang memerlukan tindakan.
                Bukan dashboard statistik dekoratif.
            </p>
        </div>

        <div
            class="bd-admin-page-head__actions"
            data-admin-reveal
            data-reveal-from="right"
        >
            <a
                href="{{ route('admin.library.create') }}"
                class="
                    bd-admin-button
                    bd-admin-button--primary
                "
            >
                Tambah Source

                <span aria-hidden="true">
                    ＋
                </span>
            </a>
        </div>

    </header>

    <div
        class="bd-admin-inline-actions"
        style="
            justify-content:flex-start;
            margin-bottom:18px;
        "
    >
        <a
            href="{{ route('admin.library.index') }}"
            class="bd-admin-link-button"
        >
            Library
        </a>

        <a
            href="{{ route('admin.categories.index') }}"
            class="bd-admin-link-button"
        >
            Categories
        </a>

        <a
            href="{{ route('admin.tools.index') }}"
            class="bd-admin-link-button"
        >
            Research Tools
        </a>

        <a
            href="{{ route('admin.prompts.index') }}"
            class="bd-admin-link-button"
        >
            Prompts
        </a>

        <a
            href="{{ route('admin.ai-runs.index') }}"
            class="bd-admin-link-button"
        >
            AI Runs
        </a>
    </div>

    <section
        class="bd-admin-ops"
        data-admin-reveal
        data-reveal-delay="60"
    >

        <div class="bd-admin-ops__main">

            <span class="bd-admin-ops__label">
                WORK QUEUE
            </span>

            <h2 class="bd-admin-ops__question">
                Yang perlu ditindak, bukan sekadar dihitung.
            </h2>

            <div class="bd-admin-queue">

                <article class="bd-admin-queue__item">

                    <strong>
                        {{ $stats['processing'] ?? 0 }}
                    </strong>

                    <div>
                        <strong>
                            Source sedang diproses
                        </strong>

                        <small>
                            PDF belum selesai menjadi evidence.
                        </small>
                    </div>

                    <a
                        href="{{
                            route(
                                'admin.library.index',
                                [
                                    'status'
                                    =>
                                    'processing',
                                ]
                            )
                        }}"
                    >
                        Periksa →
                    </a>

                </article>

                <article class="bd-admin-queue__item">

                    <strong>
                        {{ $stats['failed'] ?? 0 }}
                    </strong>

                    <div>
                        <strong>
                            Source membutuhkan perhatian
                        </strong>

                        <small>
                            Ingestion atau lifecycle perlu ditinjau.
                        </small>
                    </div>

                    <a
                        href="{{
                            route(
                                'admin.library.index'
                            )
                        }}"
                    >
                        Library →
                    </a>

                </article>

                <article class="bd-admin-queue__item">

                    <strong>
                        {{ $stats['prompts'] ?? 0 }}
                    </strong>

                    <div>
                        <strong>
                            Prompt Versions
                        </strong>

                        <small>
                            Kontrol instruction yang digunakan Research Tool.
                        </small>
                    </div>

                    <a
                        href="{{
                            route(
                                'admin.prompt-versions.index'
                            )
                        }}"
                    >
                        Kelola →
                    </a>

                </article>

                <article class="bd-admin-queue__item">

                    <strong>
                        {{ $stats['runs_today'] ?? 0 }}
                    </strong>

                    <div>
                        <strong>
                            AI Runs hari ini
                        </strong>

                        <small>
                            Audit retrieval, evidence, provider, dan citation.
                        </small>
                    </div>

                    <a
                        href="{{
                            route(
                                'admin.ai-runs.index'
                            )
                        }}"
                    >
                        Lihat →
                    </a>

                </article>

            </div>

        </div>

        <aside class="bd-admin-ops__side">

            <span class="bd-admin-ops__label">
                SYSTEM PULSE
            </span>

            <div class="bd-admin-pulse">

                <div class="bd-admin-pulse__row">
                    <span>
                        Library
                    </span>

                    <strong>
                        {{ $stats['sources'] ?? 0 }}
                        sources
                    </strong>
                </div>

                <div class="bd-admin-pulse__row">
                    <span>
                        Published
                    </span>

                    <strong>
                        {{ $stats['published'] ?? 0 }}
                    </strong>
                </div>

                <div class="bd-admin-pulse__row">
                    <span>
                        Chunks
                    </span>

                    <strong>
                        {{
                            number_format(
                                $stats['chunks']
                                ?? 0
                            )
                        }}
                    </strong>
                </div>

                <div class="bd-admin-pulse__row">
                    <span>
                        Research Tools
                    </span>

                    <strong>
                        {{ $stats['tools'] ?? 0 }}
                    </strong>
                </div>

                <div class="bd-admin-pulse__row">
                    <span>
                        Users
                    </span>

                    <strong>
                        {{ $stats['users'] ?? 0 }}
                    </strong>
                </div>

                <div class="bd-admin-pulse__row">
                    <span>
                        AI Provider
                    </span>

                    <strong>
                        {{
                            strtoupper(
                                config(
                                    'research.ai_provider',
                                    'mock'
                                )
                            )
                        }}
                    </strong>
                </div>

            </div>

        </aside>

    </section>

    <section
        class="bd-admin-section"
        data-admin-reveal
        data-reveal-delay="100"
    >

        <div class="bd-admin-section__head">

            <div>
                <h2>
                    Recent Sources
                </h2>

                <p>
                    Source terakhir yang berubah di Library.
                </p>
            </div>

            <a
                href="{{ route('admin.library.index') }}"
                class="bd-admin-table__action"
            >
                Buka Library →
            </a>

        </div>

        @if($recentDocuments->isEmpty())

            <div class="bd-admin-empty">

                <div class="bd-admin-empty__inner">

                    <span class="bd-admin-empty__mark">
                        SOURCE LAYER
                    </span>

                    <h3>
                        Library masih kosong.
                    </h3>

                    <p>
                        Tambahkan PDF pertama agar sistem dapat membangun
                        evidence layer untuk Research Tools.
                    </p>

                    <div class="bd-admin-empty__actions">

                        <a
                            href="{{ route('admin.library.create') }}"
                            class="
                                bd-admin-button
                                bd-admin-button--primary
                            "
                        >
                            Tambah Source
                        </a>

                        <a
                            href="{{ route('admin.categories.index') }}"
                            class="
                                bd-admin-button
                                bd-admin-button--ghost
                            "
                        >
                            Kelola Category
                        </a>

                    </div>

                </div>

            </div>

        @else

            <div class="bd-admin-table-wrap">

                <table class="bd-admin-table">

                    <thead>
                        <tr>
                            <th>Source</th>
                            <th>Kategori</th>
                            <th>Chunks</th>
                            <th>Status</th>
                            <th>Updated</th>
                            <th></th>
                        </tr>
                    </thead>

                    <tbody>

                        @foreach($recentDocuments as $document)

                            <tr>

                                <td>
                                    <div class="bd-admin-table__title">
                                        <strong>
                                            {{ $document->title }}
                                        </strong>

                                        <small>
                                            SRC-{{
                                                str_pad(
                                                    $document->id,
                                                    4,
                                                    '0',
                                                    STR_PAD_LEFT
                                                )
                                            }}
                                        </small>
                                    </div>
                                </td>

                                <td>
                                    {{
                                        $document->category_name
                                        ?? '—'
                                    }}
                                </td>

                                <td>
                                    {{
                                        $document->chunks_count
                                        ?? 0
                                    }}
                                </td>

                                <td>
                                    <span
                                        class="
                                            bd-admin-status
                                            bd-admin-status--{{ $document->status }}
                                        "
                                    >
                                        {{
                                            ucfirst(
                                                $document->status
                                            )
                                        }}
                                    </span>
                                </td>

                                <td>
                                    {{
                                        \Carbon\Carbon::parse(
                                            $document->updated_at
                                        )->format(
                                            'd M · H:i'
                                        )
                                    }}
                                </td>

                                <td>
                                    <a
                                        href="{{
                                            route(
                                                'admin.library.edit',
                                                $document->id
                                            )
                                        }}"
                                        class="bd-admin-table__action"
                                    >
                                        Kelola →
                                    </a>
                                </td>

                            </tr>

                        @endforeach

                    </tbody>

                </table>

            </div>

        @endif

    </section>

    @if($recentRuns->isNotEmpty())

        <section
            class="bd-admin-section"
            data-admin-reveal
            data-reveal-delay="140"
        >

            <div class="bd-admin-section__head">

                <div>
                    <h2>
                        Recent AI Runs
                    </h2>

                    <p>
                        Execution terbaru yang tercatat untuk audit.
                    </p>
                </div>

                <a
                    href="{{ route('admin.ai-runs.index') }}"
                    class="bd-admin-table__action"
                >
                    Semua AI Runs →
                </a>

            </div>

            <div class="bd-admin-table-wrap">

                <table class="bd-admin-table">

                    <thead>
                        <tr>
                            <th>Run</th>
                            <th>Research Tool</th>
                            <th>Model</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th></th>
                        </tr>
                    </thead>

                    <tbody>

                        @foreach($recentRuns as $run)

                            <tr>

                                <td>
                                    <strong>
                                        RUN-{{
                                            str_pad(
                                                $run->id,
                                                5,
                                                '0',
                                                STR_PAD_LEFT
                                            )
                                        }}
                                    </strong>
                                </td>

                                <td>
                                    {{
                                        $run->tool_name
                                        ?? '—'
                                    }}
                                </td>

                                <td>
                                    {{
                                        $run->model_name
                                        ?? '—'
                                    }}
                                </td>

                                <td>
                                    <span
                                        class="
                                            bd-admin-status
                                            bd-admin-status--{{ $run->status }}
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
                                    {{
                                        \Carbon\Carbon::parse(
                                            $run->created_at
                                        )->format(
                                            'd M · H:i'
                                        )
                                    }}
                                </td>

                                <td>
                                    <a
                                        href="{{
                                            route(
                                                'admin.ai-runs.show',
                                                $run->id
                                            )
                                        }}"
                                        class="bd-admin-table__action"
                                    >
                                        Trace →
                                    </a>
                                </td>

                            </tr>

                        @endforeach

                    </tbody>

                </table>

            </div>

        </section>

    @endif

</section>

@endsection