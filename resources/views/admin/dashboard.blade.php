@extends('layouts.admin')

@section('title', 'Beranda Admin')
@section('page_title', 'Beranda')

@php
    $aiProvider = strtolower((string) config('research.ai_provider', 'mock'));
    $aiLive = ! in_array($aiProvider, ['mock', 'unconfigured', ''], true);

    /* Plain-language labels for raw status codes. */
    $statusLabels = [
        'draft' => 'Draf',
        'queued' => 'Antre',
        'processing' => 'Diproses',
        'ready' => 'Siap terbit',
        'published' => 'Terbit',
        'archived' => 'Diarsipkan',
        'failed' => 'Gagal',
        'retrieving' => 'Mencari sumber',
        'evidence_ready' => 'Bukti siap',
        'generating' => 'Menyusun jawaban',
        'completed' => 'Selesai',
        'insufficient_evidence' => 'Bukti kurang',
        'citation_failed' => 'Sitasi tidak valid',
    ];

    $statusTone = fn (?string $status) => match ($status) {
        'published', 'completed' => 'success',
        'ready', 'evidence_ready' => 'info',
        'processing', 'queued', 'retrieving', 'generating', 'draft' => 'neutral',
        'insufficient_evidence' => 'warning',
        'failed', 'citation_failed' => 'danger',
        default => 'neutral',
    };

    $activityLabels = [
        'admin.login' => 'Admin masuk ke panel',
        'admin.logout' => 'Admin keluar dari panel',
        'admin.login.failed' => 'Percobaan login admin gagal',
        'source.created' => 'Sumber baru ditambahkan',
        'source.updated' => 'Sumber diperbarui',
        'source.published' => 'Sumber diterbitkan',
        'source.unpublished' => 'Sumber ditarik dari publikasi',
        'source.archived' => 'Sumber diarsipkan',
        'source.ingestion_queued' => 'Sumber masuk antrean pemrosesan',
        'source.ingestion_completed' => 'Pemrosesan sumber selesai',
        'source.ingestion_failed' => 'Pemrosesan sumber gagal',
        'research.completed' => 'Riset pengguna selesai',
        'research.failed' => 'Riset pengguna gagal',
        'research.citation_failed' => 'Riset ditahan karena sitasi tidak valid',
        'settings.updated' => 'Pengaturan diperbarui',
        'prompt.activated' => 'Versi instruksi AI diaktifkan',
    ];

    $needsAction = collect([
        [
            'show' => ($stats['failed'] ?? 0) > 0,
            'tone' => 'danger',
            'count' => $stats['failed'] ?? 0,
            'title' => 'sumber gagal diproses',
            'hint' => 'Periksa file PDF lalu proses ulang.',
            'href' => route('admin.library.index', ['status' => 'failed']),
            'cta' => 'Periksa',
        ],
        [
            'show' => ($stats['ready'] ?? 0) > 0,
            'tone' => 'info',
            'count' => $stats['ready'] ?? 0,
            'title' => 'sumber siap diterbitkan',
            'hint' => 'Sudah diproses, tetapi belum bisa dipakai pengguna sampai diterbitkan.',
            'href' => route('admin.library.index', ['status' => 'ready']),
            'cta' => 'Terbitkan',
        ],
        [
            'show' => ($stats['sync_pending'] ?? 0) > 0 && \Illuminate\Support\Facades\Route::has('admin.source-sync.index'),
            'tone' => 'info',
            'count' => $stats['sync_pending'] ?? 0,
            'title' => 'item impor menunggu ditinjau',
            'hint' => 'Pilih kategori lalu impor atau abaikan.',
            'href' => \Illuminate\Support\Facades\Route::has('admin.source-sync.index') ? route('admin.source-sync.index') : '#',
            'cta' => 'Tinjau',
        ],
        [
            'show' => ($stats['runs_failed_today'] ?? 0) > 0,
            'tone' => 'warning',
            'count' => $stats['runs_failed_today'] ?? 0,
            'title' => 'riset pengguna gagal hari ini',
            'hint' => 'Lihat penyebabnya di riwayat riset.',
            'href' => route('admin.ai-runs.index', ['status' => 'failed']),
            'cta' => 'Lihat',
        ],
        [
            'show' => ! $aiLive,
            'tone' => 'warning',
            'count' => null,
            'title' => 'Layanan AI masih mode uji (mock)',
            'hint' => 'Jawaban riset belum memakai layanan AI sungguhan.',
            'href' => route('admin.settings.index'),
            'cta' => 'Pengaturan',
        ],
    ])->where('show', true)->values();
@endphp

@section('content')
<section class="admin-page bd-dash">
    <header class="bd-admin-page-head">
        <div class="bd-admin-page-head__copy">
            <p class="bd-admin-kicker">{{ now()->translatedFormat('l, d F Y') }}</p>
            <h1>Halo, apa yang perlu diurus hari ini?</h1>
            <p>Ringkasan pustaka, riset pengguna, dan hal yang membutuhkan tindakan.</p>
        </div>

        <div class="bd-admin-page-head__actions">
            @if (\Illuminate\Support\Facades\Route::has('admin.source-sync.index'))
                <a href="{{ route('admin.source-sync.index') }}" class="bd-admin-button bd-admin-button--ghost">Impor sumber</a>
            @endif
            <a href="{{ route('admin.library.create') }}" class="bd-admin-button bd-admin-button--primary">+ Tambah sumber</a>
        </div>
    </header>

    {{-- Needs attention --------------------------------------------------- --}}
    <section class="bd-dash__card bd-dash__attention" aria-labelledby="dash-attention-title">
        <div class="bd-dash__card-head">
            <h2 id="dash-attention-title">Perlu tindakan</h2>
            @if ($needsAction->isNotEmpty())
                <span class="bd-dash__count">{{ $needsAction->count() }}</span>
            @endif
        </div>

        @if ($needsAction->isEmpty())
            <div class="bd-dash__all-clear">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4.5 4.5L19 7" /></svg>
                <div>
                    <strong>Semua aman.</strong>
                    <span>Tidak ada sumber gagal, sumber menunggu terbit, atau riset gagal hari ini.</span>
                </div>
            </div>
        @else
            <ul class="bd-dash__tasks">
                @foreach ($needsAction as $task)
                    <li class="bd-dash__task bd-dash__task--{{ $task['tone'] }}">
                        <span class="bd-dash__task-dot" aria-hidden="true"></span>
                        <div class="bd-dash__task-copy">
                            <strong>
                                @if (! is_null($task['count'])){{ number_format($task['count']) }} @endif{{ $task['title'] }}
                            </strong>
                            <span>{{ $task['hint'] }}</span>
                        </div>
                        <a href="{{ $task['href'] }}" class="bd-admin-button bd-admin-button--ghost">{{ $task['cta'] }} →</a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- Compact figures -------------------------------------------------- --}}
    <ul class="bd-dash__stats" aria-label="Ringkasan">
        <li>
            <span>Sumber terbit</span>
            <strong>{{ number_format($stats['published'] ?? 0) }}</strong>
            <small>dari {{ number_format($stats['sources'] ?? 0) }} sumber di pustaka</small>
        </li>
        <li>
            <span>Sedang diproses</span>
            <strong>{{ number_format($stats['processing'] ?? 0) }}</strong>
            <small>sumber dalam antrean</small>
        </li>
        <li>
            <span>Riset hari ini</span>
            <strong>{{ number_format($stats['runs_today'] ?? 0) }}</strong>
            <small>permintaan dari pengguna</small>
        </li>
        <li>
            <span>Pengguna</span>
            <strong>{{ number_format($stats['users'] ?? 0) }}</strong>
            <small>akun terdaftar</small>
        </li>
    </ul>

    <div class="bd-dash__grid">
        {{-- Recent sources ----------------------------------------------- --}}
        <section class="bd-dash__card" aria-labelledby="dash-sources-title">
            <div class="bd-dash__card-head">
                <h2 id="dash-sources-title">Sumber terbaru</h2>
                <a href="{{ route('admin.library.index') }}" class="bd-admin-table__action">Buka pustaka →</a>
            </div>

            @if ($recentDocuments->isEmpty())
                <div class="bd-dash__empty">
                    <p>Pustaka masih kosong. Tambahkan PDF pertama agar pengguna bisa mulai riset.</p>
                    <a href="{{ route('admin.library.create') }}" class="bd-admin-button bd-admin-button--primary">+ Tambah sumber</a>
                </div>
            @else
                <ul class="bd-dash__list">
                    @foreach ($recentDocuments as $document)
                        <li>
                            <a href="{{ route('admin.library.edit', $document->id) }}" class="bd-dash__row">
                                <span class="bd-dash__row-main">
                                    <strong>{{ $document->title }}</strong>
                                    <small>
                                        {{ $document->category_name ?? 'Tanpa kategori' }}
                                        · diperbarui {{ \Carbon\Carbon::parse($document->updated_at)->diffForHumans() }}
                                    </small>
                                </span>
                                <span class="bd-dash__badge bd-dash__badge--{{ $statusTone($document->status) }}">
                                    {{ $statusLabels[$document->status] ?? ucfirst($document->status) }}
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- Recent activity ---------------------------------------------- --}}
        <section class="bd-dash__card" aria-labelledby="dash-activity-title">
            <div class="bd-dash__card-head">
                <h2 id="dash-activity-title">Aktivitas terbaru</h2>
                <a href="{{ route('admin.audit.index') }}" class="bd-admin-table__action">Log lengkap →</a>
            </div>

            @if ($recentActivity->isEmpty())
                <div class="bd-dash__empty"><p>Belum ada aktivitas tercatat.</p></div>
            @else
                <ol class="bd-dash__timeline">
                    @foreach ($recentActivity as $activity)
                        <li>
                            <strong>{{ $activityLabels[$activity->action] ?? str($activity->action)->replace(['.', '_'], ' ')->ucfirst() }}</strong>
                            <small>{{ \Carbon\Carbon::parse($activity->created_at)->diffForHumans() }}</small>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>
    </div>

    {{-- Recent research runs --------------------------------------------- --}}
    @if ($recentRuns->isNotEmpty())
        <section class="bd-dash__card" aria-labelledby="dash-runs-title">
            <div class="bd-dash__card-head">
                <h2 id="dash-runs-title">Riset terbaru dari pengguna</h2>
                <a href="{{ route('admin.ai-runs.index') }}" class="bd-admin-table__action">Semua riwayat →</a>
            </div>

            <div class="bd-admin-table-wrap">
                <table class="bd-admin-table">
                    <thead>
                        <tr>
                            <th scope="col">Riset</th>
                            <th scope="col">Alat riset</th>
                            <th scope="col">Status</th>
                            <th scope="col">Waktu</th>
                            <th scope="col"><span class="bd-admin__sr-only">Aksi</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recentRuns as $run)
                            <tr>
                                <td><strong>RUN-{{ str_pad($run->id, 5, '0', STR_PAD_LEFT) }}</strong></td>
                                <td>{{ $run->tool_name ?? '—' }}</td>
                                <td>
                                    <span class="bd-dash__badge bd-dash__badge--{{ $statusTone($run->status) }}">
                                        {{ $statusLabels[$run->status] ?? str($run->status)->replace('_', ' ')->title() }}
                                    </span>
                                </td>
                                <td>{{ \Carbon\Carbon::parse($run->created_at)->format('d M · H:i') }}</td>
                                <td><a href="{{ route('admin.ai-runs.show', $run->id) }}" class="bd-admin-table__action">Detail →</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</section>
@endsection
