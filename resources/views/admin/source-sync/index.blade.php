@extends('layouts.admin')

@section('title', 'Source Sync')
@section('page_title', 'Source Sync')

@section('content')

<section class="admin-page">

    <header class="bd-admin-page-head">

        <div
            class="bd-admin-page-head__copy"
            data-admin-reveal
        >
            <p class="bd-admin-kicker">
                KNOWLEDGE / SOURCE SYNC
            </p>

            <h1>
                Source Connector Staging
            </h1>

            <p>
                Ambil daftar source dari connector, bandingkan checksum,
                review perubahan, lalu ingest ke Library.
                Source hasil sync berhenti di status Ready dan tidak dipublish otomatis.
            </p>
        </div>

        <div
            class="bd-admin-page-head__actions"
            data-admin-reveal
            data-reveal-from="right"
        >

            <a
                href="{{ route('admin.library.index') }}"
                class="bd-admin-button bd-admin-button--ghost"
            >
                Library
            </a>

            <form
                method="POST"
                action="{{ route('admin.source-sync.scan') }}"
            >
                @csrf

                <button
                    type="submit"
                    class="bd-admin-button bd-admin-button--primary"
                >
                    Scan Sources

                    <span aria-hidden="true">
                        ↻
                    </span>
                </button>
            </form>

        </div>

    </header>

    <div
        class="bd-admin-note"
        data-admin-reveal
        data-reveal-delay="50"
    >
        <strong>
            Connector aktif:
            {{ strtoupper($provider) }}
        </strong>

        <p>
            Auto-publish:
            <strong>
                {{ $autoPublish ? 'ON' : 'OFF' }}
            </strong>.

            Tahap lokal memakai connector mock untuk menguji alur
            Discover → Checksum/Diff → Review → Ingest → Ready.
        </p>
    </div>

    @if($selectedRun)

        @php
            $runStatusClass =
                match(
                    $selectedRun->status
                ) {
                    'completed' =>
                        'completed',

                    'review_ready' =>
                        'warning',

                    'completed_with_errors' =>
                        'warning',

                    'failed' =>
                        'failed',

                    default =>
                        'running',
                };
        @endphp

        <section
            data-admin-reveal
            data-reveal-delay="80"
            style="margin-top:22px;"
        >

            <header
                class="bd-admin-page-head"
                style="margin-bottom:12px;"
            >

                <div class="bd-admin-page-head__copy">

                    <p class="bd-admin-kicker">
                        REVIEW RUN
                    </p>

                    <h1 style="font-size:24px;">
                        Sync #{{ $selectedRun->id }}
                        ·
                        {{ strtoupper($selectedRun->connector) }}
                    </h1>

                    <p>
                        {{ $selectedRun->discovered_count }}
                        ditemukan ·

                        {{ $selectedRun->new_count }}
                        baru ·

                        {{ $selectedRun->changed_count }}
                        berubah ·

                        {{ $selectedRun->unchanged_count }}
                        tidak berubah ·

                        {{ $selectedRun->imported_count }}
                        diimport ·

                        {{ $selectedRun->ignored_count }}
                        diabaikan.
                    </p>

                </div>

                <span
                    class="
                        bd-admin-status
                        bd-admin-status--{{ $runStatusClass }}
                    "
                >
                    {{
                        strtoupper(
                            str_replace(
                                '_',
                                ' ',
                                $selectedRun->status
                            )
                        )
                    }}
                </span>

            </header>

            @if($items->isEmpty())

                <div class="bd-admin-empty">

                    <div class="bd-admin-empty__inner">

                        <span class="bd-admin-empty__mark">
                            NO ITEMS
                        </span>

                        <h3>
                            Run ini tidak mempunyai source item.
                        </h3>

                        <p>
                            Jalankan scan baru atau periksa konfigurasi connector.
                        </p>

                    </div>

                </div>

            @else

                <div class="bd-admin-table-wrap">

                    <table class="bd-admin-table">

                        <thead>

                            <tr>
                                <th>Source</th>
                                <th>Diff</th>
                                <th>Checksum</th>
                                <th>Library Match</th>
                                <th>Review</th>
                            </tr>

                        </thead>

                        <tbody>

                            @foreach($items as $item)

                                @php
                                    $itemStatusClass =
                                        match(
                                            $item->status
                                        ) {
                                            'new' =>
                                                'ready',

                                            'changed' =>
                                                'warning',

                                            'unchanged' =>
                                                'completed',

                                            'imported' =>
                                                'success',

                                            'failed' =>
                                                'failed',

                                            'ignored' =>
                                                'draft',

                                            default =>
                                                'draft',
                                        };
                                @endphp

                                <tr>

                                    <td>

                                        <div class="bd-admin-table__title">

                                            <strong>
                                                {{ $item->title }}
                                            </strong>

                                            <small>
                                                {{ $item->external_id }}
                                                ·
                                                {{ strtoupper($item->connector) }}
                                            </small>

                                            @if($item->remote_updated_at)

                                                <small>
                                                    Remote update
                                                    {{
                                                        $item
                                                            ->remote_updated_at
                                                            ->format(
                                                                'd M Y H:i'
                                                            )
                                                    }}
                                                </small>

                                            @endif

                                        </div>

                                    </td>

                                    <td>

                                        <span
                                            class="
                                                bd-admin-status
                                                bd-admin-status--{{ $itemStatusClass }}
                                            "
                                        >
                                            {{ strtoupper($item->status) }}
                                        </span>

                                        @if($item->error_message)

                                            <small
                                                style="
                                                    display:block;
                                                    margin-top:6px;
                                                    color:#a33e36;
                                                    max-width:260px;
                                                "
                                            >
                                                {{ $item->error_message }}
                                            </small>

                                        @endif

                                    </td>

                                    <td>

                                        <code
                                            title="{{ $item->external_checksum }}"
                                        >
                                            {{
                                                $item->external_checksum
                                                    ? substr(
                                                        $item->external_checksum,
                                                        0,
                                                        12
                                                    ).'…'
                                                    : '—'
                                            }}
                                        </code>

                                        @if($item->previous_checksum)

                                            <small
                                                style="
                                                    display:block;
                                                    margin-top:4px;
                                                "
                                            >
                                                Prev
                                                {{
                                                    substr(
                                                        $item->previous_checksum,
                                                        0,
                                                        10
                                                    )
                                                }}…
                                            </small>

                                        @endif

                                    </td>

                                    <td>

                                        @if($item->importedDocument)

                                            <div class="bd-admin-table__title">

                                                <strong>
                                                    {{ $item->importedDocument->title }}
                                                </strong>

                                                <small>
                                                    Library
                                                    #{{ $item->importedDocument->id }}
                                                    ·
                                                    {{
                                                        strtoupper(
                                                            $item
                                                                ->importedDocument
                                                                ->status
                                                        )
                                                    }}
                                                </small>

                                            </div>

                                        @elseif($item->matchedDocument)

                                            <div class="bd-admin-table__title">

                                                <strong>
                                                    {{ $item->matchedDocument->title }}
                                                </strong>

                                                <small>
                                                    Existing
                                                    #{{ $item->matchedDocument->id }}
                                                    ·
                                                    {{
                                                        strtoupper(
                                                            $item
                                                                ->matchedDocument
                                                                ->status
                                                        )
                                                    }}
                                                </small>

                                            </div>

                                        @else

                                            <small>
                                                Belum ada source lokal.
                                            </small>

                                        @endif

                                    </td>

                                    <td>

                                        @if(
                                            in_array(
                                                $item->status,
                                                [
                                                    'new',
                                                    'changed',
                                                ],
                                                true
                                            )
                                        )

                                            <form
                                                method="POST"
                                                action="{{ route('admin.source-sync.import', $item) }}"
                                                class="bd-admin-inline-actions"
                                                style="
                                                    justify-content:flex-start;
                                                    min-width:300px;
                                                "
                                            >

                                                @csrf

                                                <select
                                                    name="category_id"
                                                    class="bd-admin-select"
                                                    required
                                                    style="width:165px;"
                                                    aria-label="Kategori import untuk {{ $item->title }}"
                                                >

                                                    <option value="">
                                                        Pilih kategori
                                                    </option>

                                                    @foreach($categories as $category)

                                                        <option
                                                            value="{{ $category->id }}"
                                                            @selected(
                                                                (string)
                                                                    old(
                                                                        'category_id'
                                                                    )
                                                                ===
                                                                (string)
                                                                    $category
                                                                        ->id
                                                            )
                                                        >
                                                            {{ $category->name }}
                                                        </option>

                                                    @endforeach

                                                </select>

                                                <button
                                                    type="submit"
                                                    class="
                                                        bd-admin-button
                                                        bd-admin-button--primary
                                                    "
                                                >
                                                    Import → Ready
                                                </button>

                                            </form>

                                            <form
                                                method="POST"
                                                action="{{ route('admin.source-sync.ignore', $item) }}"
                                                style="margin-top:7px;"
                                            >

                                                @csrf
                                                @method('PATCH')

                                                <button
                                                    type="submit"
                                                    class="bd-admin-link-button"
                                                >
                                                    Abaikan item
                                                </button>

                                            </form>

                                        @elseif(
                                            $item->status === 'imported'
                                            &&
                                            $item->importedDocument
                                        )

                                            <a
                                                href="{{ route('admin.library.edit', $item->importedDocument) }}"
                                                class="bd-admin-table__action"
                                            >
                                                Review di Library →
                                            </a>

                                        @elseif(
                                            $item->status
                                            ===
                                            'unchanged'
                                        )

                                            <small>
                                                Tidak perlu tindakan.
                                            </small>

                                        @elseif(
                                            $item->status
                                            ===
                                            'ignored'
                                        )

                                            <small>
                                                Diabaikan oleh admin.
                                            </small>

                                        @else

                                            <small>
                                                Jalankan scan baru bila source remote berubah.
                                            </small>

                                        @endif

                                    </td>

                                </tr>

                            @endforeach

                        </tbody>

                    </table>

                </div>

            @endif

        </section>

    @else

        <div
            class="bd-admin-empty"
            data-admin-reveal
            data-reveal-delay="80"
            style="margin-top:22px;"
        >

            <div class="bd-admin-empty__inner">

                <span class="bd-admin-empty__mark">
                    SOURCE CONNECTOR
                </span>

                <h3>
                    Belum ada Source Sync run.
                </h3>

                <p>
                    Klik Scan Sources. Connector hanya membuat staging item;
                    Library tidak diubah sampai admin memilih Import.
                </p>

            </div>

        </div>

    @endif

    @if($runs->isNotEmpty())

        <section
            data-admin-reveal
            data-reveal-delay="120"
            style="margin-top:28px;"
        >

            <header
                class="bd-admin-page-head"
                style="margin-bottom:12px;"
            >

                <div class="bd-admin-page-head__copy">

                    <p class="bd-admin-kicker">
                        SYNC HISTORY
                    </p>

                    <h1 style="font-size:24px;">
                        Run History
                    </h1>

                </div>

            </header>

            <div class="bd-admin-table-wrap">

                <table class="bd-admin-table">

                    <thead>
                        <tr>
                            <th>Run</th>
                            <th>Connector</th>
                            <th>Discovered</th>
                            <th>Diff</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>

                    <tbody>

                        @foreach($runs as $run)

                            @php
                                $historyStatusClass =
                                    match(
                                        $run->status
                                    ) {
                                        'completed' =>
                                            'completed',

                                        'review_ready' =>
                                            'warning',

                                        'completed_with_errors' =>
                                            'warning',

                                        'failed' =>
                                            'failed',

                                        default =>
                                            'running',
                                    };
                            @endphp

                            <tr>

                                <td>

                                    <div class="bd-admin-table__title">

                                        <strong>
                                            #{{ $run->id }}
                                        </strong>

                                        <small>
                                            {{
                                                $run
                                                    ->created_at
                                                    ?->format(
                                                        'd M Y H:i:s'
                                                    )
                                            }}
                                        </small>

                                    </div>

                                </td>

                                <td>
                                    {{ strtoupper($run->connector) }}
                                </td>

                                <td>
                                    {{ $run->discovered_count }}
                                </td>

                                <td>

                                    <small>
                                        New {{ $run->new_count }}
                                        ·
                                        Changed {{ $run->changed_count }}
                                        ·
                                        Same {{ $run->unchanged_count }}
                                        ·
                                        Imported {{ $run->imported_count }}
                                    </small>

                                </td>

                                <td>

                                    <span
                                        class="
                                            bd-admin-status
                                            bd-admin-status--{{ $historyStatusClass }}
                                        "
                                    >
                                        {{
                                            strtoupper(
                                                str_replace(
                                                    '_',
                                                    ' ',
                                                    $run->status
                                                )
                                            )
                                        }}
                                    </span>

                                </td>

                                <td>

                                    <a
                                        href="{{
                                            route(
                                                'admin.source-sync.index',
                                                [
                                                    'run' =>
                                                        $run->id,
                                                ]
                                            )
                                        }}"
                                        class="bd-admin-table__action"
                                    >
                                        Review →
                                    </a>

                                </td>

                            </tr>

                        @endforeach

                    </tbody>

                </table>

            </div>

            <div style="margin-top:14px;">
                {{ $runs->links() }}
            </div>

        </section>

    @endif

</section>

@endsection