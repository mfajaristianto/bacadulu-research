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
        'source.save' => 'Sumber disimpan',
        'source.publish' => 'Sumber diterbitkan',
        'source.archive' => 'Sumber diarsipkan',
        'source.reprocess' => 'Sumber diproses ulang',
        'source.reprocess_failed' => 'Pemrosesan ulang sumber gagal',
        'source.ingestion_failed' => 'Pemrosesan sumber gagal',
        'source_sync.scanned' => 'Pemindaian impor sumber selesai',
        'research.insufficient_evidence' => 'Riset berhenti: bukti kurang',
        'document.draft_created' => 'Draf dokumen dibuat',
        'document.generated' => 'Dokumen disusun',
        'document.updated' => 'Dokumen diperbarui',
        'document.downloaded' => 'Dokumen diunduh',
        'document.deleted' => 'Dokumen dihapus',
        'settings.updated' => 'Pengaturan diperbarui',
        'prompt.activated' => 'Versi instruksi AI diaktifkan',
    ];

    $pct = fn (int $part, int $whole): ?int => $whole > 0 ? (int) round($part / $whole * 100) : null;
    $has = fn (string $name): bool => \Illuminate\Support\Facades\Route::has($name);
    $ago = fn ($time): string => \Carbon\Carbon::parse($time)->locale('id')->diffForHumans();

    $sourcePct = $pct($stats['published'] ?? 0, $stats['sources'] ?? 0);
    $userPct = $pct($stats['users_active'] ?? 0, $stats['users'] ?? 0);
    $promptPct = $pct($stats['prompts_active'] ?? 0, $stats['prompts'] ?? 0);
    $coveragePct = $pct($stats['tools_with_prompt'] ?? 0, $stats['tools_active'] ?? 0);

    /* Ikon + nada semantik untuk Aktivitas Terbaru, dari awalan kode aksi. */
    $eventMeta = fn (string $action): array => match (true) {
        str_ends_with($action, 'failed') => ['alert', 'coral'],
        str_starts_with($action, 'source_sync.') => ['sync', 'info'],
        $action === 'source.publish' => ['check', 'green'],
        $action === 'source.archive' => ['archive', 'navy'],
        str_starts_with($action, 'source.') => ['file', 'gold'],
        str_starts_with($action, 'admin.') => ['user', 'lav'],
        str_starts_with($action, 'research.') => ['search', 'info'],
        str_starts_with($action, 'document.') => ['file', 'coral'],
        str_starts_with($action, 'prompt.') => ['prompt', 'lav'],
        str_starts_with($action, 'settings.') => ['settings', 'navy'],
        default => ['info', 'navy'],
    };

    /* "Siap" hanya bila ada sumber terbit, semua alat aktif berprompt, tidak ada sumber gagal, dan AI bukan mock. */
    $systemReady = $aiLive
        && ($stats['published'] ?? 0) > 0
        && ($stats['failed'] ?? 0) === 0
        && $coveragePct === 100;

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
            'show' => ($stats['processing'] ?? 0) > 0,
            'tone' => 'info',
            'count' => $stats['processing'] ?? 0,
            'title' => 'sumber sedang diproses',
            'hint' => 'Pemrosesan berjalan di latar belakang.',
            'href' => route('admin.library.index', ['status' => 'processing']),
            'cta' => 'Lihat',
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
<section class="admin-page bd-dash" data-dash>
    <header class="bd-admin-page-head" data-dash-in>
        <div class="bd-admin-page-head__copy">
            <h1>Beranda Admin</h1>
            <p>Ringkasan operasional BacaDulu Research hari ini.</p>
        </div>
    </header>

    {{-- 1. Greeting + Bubu ------------------------------------------------- --}}
    <section class="bd-dash__greeting" aria-labelledby="dash-greeting-title" data-dash-in>
        <img
            class="bd-dash__bubu"
            src="{{ asset('assets/mascot/mascot-admin-dashboard.png') }}"
            alt=""
            width="140"
            height="140"
            decoding="async"
            data-dash-bubu
        >
        <div class="bd-dash__greeting-copy">
            <h2 id="dash-greeting-title">Selamat bekerja, Admin!</h2>
            <p>Pantau sumber, aktivitas riset, prompt AI, dan pengguna BacaDulu Research dari satu beranda.</p>
        </div>
        <nav class="bd-dash__quick" aria-label="Aksi cepat">
            @if ($has('admin.library.create'))
                <a href="{{ route('admin.library.create') }}" class="bd-dash__quick-item bd-dash__quick-item--primary">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
                    Tambah Sumber
                </a>
            @endif
            @if ($has('admin.source-sync.index'))
                <a href="{{ route('admin.source-sync.index') }}" class="bd-dash__quick-item">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12a8 8 0 0 1 13.7-5.6L20 9M20 4v5h-5M20 12a8 8 0 0 1-13.7 5.6L4 15M4 20v-5h5" /></svg>
                    Impor Sumber
                </a>
            @endif
            @if ($has('admin.prompts.index'))
                <a href="{{ route('admin.prompts.index') }}" class="bd-dash__quick-item">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 5h14v10H9l-4 4V5Z" /><path d="M9 9h6M9 12h4" /></svg>
                    Kelola Prompt
                </a>
            @endif
        </nav>
    </section>

    {{-- A. Operational alert ---------------------------------------------- --}}
    <section class="bd-dash__alert {{ $needsAction->isEmpty() ? 'is-clear' : '' }}" aria-labelledby="dash-attention-title" data-dash-in>
        <div class="bd-dash__alert-lead">
            <span class="bd-dash__alert-icon">@include('admin.partials.dash-icon', ['name' => $needsAction->isEmpty() ? 'check' : 'alert'])</span>
            <h2 id="dash-attention-title">{{ $needsAction->isEmpty() ? 'Semua sistem utama siap.' : 'Perlu tindakan' }}</h2>
            @if ($needsAction->isNotEmpty())
                <span class="bd-dash__alert-count">{{ $needsAction->count() }}</span>
            @endif
        </div>

        @if ($needsAction->isNotEmpty())
            <ul class="bd-dash__issues">
                @foreach ($needsAction as $task)
                    <li>
                        <a href="{{ $task['href'] }}" class="bd-dash__issue bd-dash__issue--{{ $task['tone'] }}" title="{{ $task['hint'] }}">
                            <span class="bd-dash__issue-icon">@include('admin.partials.dash-icon', ['name' => $task['tone'] === 'info' ? 'info' : 'alert'])</span>
                            <span class="bd-dash__issue-text">@if (! is_null($task['count']))<b>{{ number_format($task['count']) }}</b> @endif{{ $task['title'] }}</span>
                            <span class="bd-dash__issue-go">@include('admin.partials.dash-icon', ['name' => 'arrow'])</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- B. Ringkasan hari ini ---------------------------------------------- --}}
    <section class="bd-dash__block" aria-labelledby="dash-summary-title">
        <h2 class="bd-dash__block-title" id="dash-summary-title" data-dash-in>Ringkasan Hari Ini</h2>

        <ul class="bd-dash__stats">
            <li class="bd-dash__card bd-dash__stat bd-dash__stat--coral" data-dash-in>
                <div class="bd-dash__stat-top">
                    <span class="bd-dash__icon bd-dash__icon--coral">@include('admin.partials.dash-icon', ['name' => 'prompt'])</span>
                    <span class="bd-dash__stat-label">Prompt Aktif</span>
                </div>
                <strong><b data-dash-count="{{ $stats['prompts_active'] ?? 0 }}">{{ number_format($stats['prompts_active'] ?? 0) }}</b></strong>
                <small>dari {{ number_format($stats['prompts'] ?? 0) }} prompt</small>
                <div class="bd-dash__stat-visual">
                    @if (! is_null($promptPct))
                        <span class="bd-dash__bar" role="img" aria-label="{{ $promptPct }} persen prompt aktif"><i data-dash-bar style="width: {{ $promptPct }}%"></i></span>
                    @endif
                    <span class="bd-dash__stat-note">{{ number_format($stats['prompts_draft'] ?? 0) }} draf · {{ number_format($stats['prompts_archived'] ?? 0) }} arsip</span>
                </div>
            </li>

            <li class="bd-dash__card bd-dash__stat bd-dash__stat--green" data-dash-in>
                <div class="bd-dash__stat-top">
                    <span class="bd-dash__icon bd-dash__icon--green">@include('admin.partials.dash-icon', ['name' => 'users'])</span>
                    <span class="bd-dash__stat-label">Pengguna</span>
                </div>
                <strong><b data-dash-count="{{ $stats['users'] ?? 0 }}">{{ number_format($stats['users'] ?? 0) }}</b></strong>
                <small>{{ number_format($stats['users_active'] ?? 0) }} aktif · {{ number_format(($stats['users'] ?? 0) - ($stats['users_active'] ?? 0)) }} tidak aktif</small>
                @if (! is_null($userPct))
                    <span class="bd-dash__stat-ring">
                        @include('admin.partials.dash-ring', ['pct' => $userPct, 'tone' => 'green', 'label' => $userPct.' persen pengguna aktif'])
                    </span>
                @endif
            </li>

            <li class="bd-dash__card bd-dash__stat bd-dash__stat--gold" data-dash-in>
                <div class="bd-dash__stat-top">
                    <span class="bd-dash__icon bd-dash__icon--gold">@include('admin.partials.dash-icon', ['name' => 'library'])</span>
                    <span class="bd-dash__stat-label">Sumber Aktif</span>
                </div>
                <strong><b data-dash-count="{{ $stats['published'] ?? 0 }}">{{ number_format($stats['published'] ?? 0) }}</b><em> / {{ number_format($stats['sources'] ?? 0) }}</em></strong>
                <small>{{ is_null($sourcePct) ? 'Belum ada sumber' : $sourcePct.'% sumber terbit' }}</small>
                @if (! is_null($sourcePct))
                    <span class="bd-dash__stat-ring">
                        @include('admin.partials.dash-ring', ['pct' => $sourcePct, 'tone' => 'gold', 'label' => $sourcePct.' persen sumber terbit'])
                    </span>
                @endif
            </li>

            <li class="bd-dash__card bd-dash__stat bd-dash__stat--lav" data-dash-in>
                <div class="bd-dash__stat-top">
                    <span class="bd-dash__icon bd-dash__icon--lav">@include('admin.partials.dash-icon', ['name' => 'chart'])</span>
                    <span class="bd-dash__stat-label">Riset Hari Ini</span>
                </div>
                <strong><b data-dash-count="{{ $stats['runs_today'] ?? 0 }}">{{ number_format($stats['runs_today'] ?? 0) }}</b></strong>
                <small>riset dijalankan hari ini</small>
                <div class="bd-dash__stat-visual">
                    @if (($stats['runs_failed_today'] ?? 0) > 0)
                        <span class="bd-dash__stat-note bd-dash__stat-note--warn">{{ number_format($stats['runs_failed_today']) }} gagal hari ini</span>
                    @elseif (($stats['runs_today'] ?? 0) === 0)
                        <span class="bd-dash__stat-note">@include('admin.partials.dash-icon', ['name' => 'info']) Belum ada riset hari ini</span>
                    @else
                        <span class="bd-dash__stat-note bd-dash__stat-note--ok">@include('admin.partials.dash-icon', ['name' => 'check']) Tidak ada riset gagal</span>
                    @endif
                </div>
            </li>
        </ul>
    </section>

    {{-- C. Kesiapan sistem ------------------------------------------------- --}}
    <section class="bd-dash__card bd-dash__ready-card" aria-labelledby="dash-ready-title" data-dash-in>
        <div class="bd-dash__card-head">
            <div class="bd-dash__card-title">
                <span class="bd-dash__icon bd-dash__icon--green">@include('admin.partials.dash-icon', ['name' => 'shield'])</span>
                <div>
                    <h2 id="dash-ready-title">Kesiapan Sistem</h2>
                    <p>Status kesiapan modul utama BacaDulu Research.</p>
                </div>
            </div>
            <span class="bd-dash__badge bd-dash__badge--{{ $systemReady ? 'success' : 'warning' }}">{{ $systemReady ? 'Sistem utama siap digunakan' : 'Ada modul yang belum siap' }}</span>
        </div>

        <ul class="bd-dash__ready">
            <li class="bd-dash__mini bd-dash__mini--gold">
                <div class="bd-dash__mini-top">
                    <span class="bd-dash__icon bd-dash__icon--tile">@include('admin.partials.dash-icon', ['name' => 'library'])</span>
                    <strong>Sumber</strong>
                    @if (! is_null($sourcePct))<span class="bd-dash__mini-pct">{{ $sourcePct }}%</span>@endif
                </div>
                <span class="bd-dash__mini-value">{{ number_format($stats['published'] ?? 0) }} / {{ number_format($stats['sources'] ?? 0) }} siap</span>
                @if (! is_null($sourcePct))
                    <span class="bd-dash__bar" role="img" aria-label="{{ $sourcePct }} persen sumber siap"><i data-dash-bar style="width: {{ $sourcePct }}%"></i></span>
                @endif
                <span class="bd-dash__mini-note">sumber terbit dan siap dipakai riset</span>
            </li>

            <li class="bd-dash__mini bd-dash__mini--lav">
                <div class="bd-dash__mini-top">
                    <span class="bd-dash__icon bd-dash__icon--tile">@include('admin.partials.dash-icon', ['name' => 'settings'])</span>
                    <strong>AI</strong>
                    <span class="bd-dash__badge bd-dash__badge--{{ $aiLive ? 'success' : 'warning' }}">{{ $aiLive ? 'Produksi' : 'Belum produksi' }}</span>
                </div>
                <span class="bd-dash__mini-value">{{ $aiLive ? 'Layanan '.strtoupper($aiProvider) : 'Mode uji' }}</span>
                <span class="bd-dash__mini-note">{{ $aiLive ? 'Jawaban riset memakai layanan AI sungguhan.' : 'Provider nyata belum digunakan.' }}</span>
            </li>

            <li class="bd-dash__mini bd-dash__mini--coral">
                <div class="bd-dash__mini-top">
                    <span class="bd-dash__icon bd-dash__icon--tile">@include('admin.partials.dash-icon', ['name' => 'prompt'])</span>
                    <strong>Prompt</strong>
                    @if (! is_null($coveragePct))<span class="bd-dash__mini-pct">{{ $coveragePct }}%</span>@endif
                </div>
                @if (is_null($coveragePct))
                    <span class="bd-dash__mini-value">Belum ada alat riset aktif</span>
                @else
                    <span class="bd-dash__mini-value">{{ number_format($stats['tools_with_prompt'] ?? 0) }} / {{ number_format($stats['tools_active'] ?? 0) }} alat riset</span>
                    <span class="bd-dash__bar bd-dash__bar--green" role="img" aria-label="{{ $coveragePct }} persen alat riset punya prompt aktif"><i data-dash-bar style="width: {{ $coveragePct }}%"></i></span>
                    <span class="bd-dash__mini-note">{{ $coveragePct === 100 ? 'Prompt aktif tersedia untuk semua alat.' : 'Sebagian alat riset belum punya prompt aktif.' }}</span>
                @endif
            </li>
        </ul>
    </section>

    {{-- D. Kategori prompt ------------------------------------------------- --}}
    <section class="bd-dash__block" aria-labelledby="dash-cat-title">
        <div class="bd-dash__section-head" data-dash-in>
            <div>
                <h2 id="dash-cat-title">Kategori Prompt</h2>
                <p>Pantau prompt berdasarkan jenis kebutuhan konten.</p>
            </div>
            @if ($has('admin.prompts.index'))
                <a href="{{ route('admin.prompts.index') }}" class="bd-admin-table__action">Kelola Prompt →</a>
            @endif
        </div>

        <div class="bd-dash__category-grid">
            {{-- Database belum punya penanda Buku vs Jurnal/Makalah; semua prompt yang ada adalah prompt akademik umum. --}}
            <article class="bd-dash__card bd-dash__category bd-dash__category--coral" data-dash-in>
                <header>
                    <span class="bd-dash__icon bd-dash__icon--coral">@include('admin.partials.dash-icon', ['name' => 'file'])</span>
                    <h3>Jurnal &amp; Makalah</h3>
                    <span class="bd-dash__badge bd-dash__badge--{{ ($stats['prompts_active'] ?? 0) > 0 ? 'success' : 'neutral' }}">{{ ($stats['prompts_active'] ?? 0) > 0 ? 'Aktif' : 'Belum aktif' }}</span>
                </header>
                <p>Prompt untuk artikel, manuskrip, tugas akademik, dan karya ilmiah.</p>
                <dl class="bd-dash__minis">
                    <div><dt>Template</dt><dd data-dash-count="{{ $stats['prompts'] ?? 0 }}">{{ number_format($stats['prompts'] ?? 0) }}</dd></div>
                    <div class="is-green"><dt>Aktif</dt><dd data-dash-count="{{ $stats['prompts_active'] ?? 0 }}">{{ number_format($stats['prompts_active'] ?? 0) }}</dd></div>
                    <div class="is-gold"><dt>Draf</dt><dd data-dash-count="{{ $stats['prompts_draft'] ?? 0 }}">{{ number_format($stats['prompts_draft'] ?? 0) }}</dd></div>
                    <div class="is-muted"><dt>Arsip</dt><dd data-dash-count="{{ $stats['prompts_archived'] ?? 0 }}">{{ number_format($stats['prompts_archived'] ?? 0) }}</dd></div>
                </dl>
            </article>

            <article class="bd-dash__card bd-dash__category bd-dash__category--lav" data-dash-in>
                <header>
                    <span class="bd-dash__icon bd-dash__icon--lav">@include('admin.partials.dash-icon', ['name' => 'book'])</span>
                    <h3>Buku</h3>
                    <span class="bd-dash__badge bd-dash__badge--gold">Belum diklasifikasikan</span>
                </header>
                <p>Prompt khusus buku, book chapter, rangkuman, ekstraksi, dan analisis konten buku.</p>
                <div class="bd-dash__note">
                    <span class="bd-dash__note-icon">@include('admin.partials.dash-icon', ['name' => 'info'])</span>
                    <div>
                        <strong>Kategori Buku belum dipisahkan dari prompt umum.</strong>
                        <span>Penandaan kategori diperlukan untuk menampilkan statistik Buku.</span>
                    </div>
                </div>
            </article>
        </div>
    </section>

    {{-- E. Sumber terbaru + aktivitas -------------------------------------- --}}
    <div class="bd-dash__grid">
        <section class="bd-dash__card" aria-labelledby="dash-sources-title" data-dash-in>
            <div class="bd-dash__card-head">
                <h2 id="dash-sources-title">Sumber Terbaru</h2>
                <a href="{{ route('admin.library.index') }}" class="bd-admin-table__action">Lihat Semua →</a>
            </div>

            @if ($recentDocuments->isEmpty())
                <div class="bd-dash__empty">
                    <p>Pustaka masih kosong. Tambahkan PDF pertama agar pengguna bisa mulai riset.</p>
                    <a href="{{ route('admin.library.create') }}" class="bd-admin-button bd-admin-button--primary">+ Tambah sumber</a>
                </div>
            @else
                <table class="bd-dash__table">
                    <thead>
                        <tr>
                            <th scope="col">Judul</th>
                            <th scope="col">Kategori</th>
                            <th scope="col">Status</th>
                            <th scope="col">Waktu</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recentDocuments as $document)
                            <tr data-dash-row>
                                <td class="bd-dash__table-title"><a href="{{ route('admin.library.edit', $document->id) }}">{{ $document->title }}</a></td>
                                <td class="bd-dash__table-cat">{{ $document->category_name ?? 'Tanpa kategori' }}</td>
                                <td class="bd-dash__table-status">
                                    <span class="bd-dash__badge bd-dash__badge--{{ $statusTone($document->status) }}">{{ $statusLabels[$document->status] ?? ucfirst($document->status) }}</span>
                                </td>
                                <td class="bd-dash__table-date">{{ $ago($document->created_at) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>

        <section class="bd-dash__card" aria-labelledby="dash-activity-title" data-dash-in>
            <div class="bd-dash__card-head">
                <h2 id="dash-activity-title">Aktivitas Terbaru</h2>
                <a href="{{ route('admin.audit.index') }}" class="bd-admin-table__action">Log lengkap →</a>
            </div>

            @if ($recentActivity->isEmpty())
                <div class="bd-dash__empty"><p>Belum ada aktivitas terbaru.</p></div>
            @else
                <ol class="bd-dash__timeline">
                    @foreach ($recentActivity as $activity)
                        @php($event = $eventMeta($activity->action))
                        <li data-dash-activity>
                            <span class="bd-dash__icon bd-dash__icon--sm bd-dash__icon--{{ $event[1] }}">@include('admin.partials.dash-icon', ['name' => $event[0]])</span>
                            <div>
                                <strong>{{ $activityLabels[$activity->action] ?? str($activity->action)->replace(['.', '_'], ' ')->ucfirst() }}</strong>
                                <small>{{ $activity->actor_name ?? 'Sistem' }} · {{ $ago($activity->created_at) }}</small>
                            </div>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>
    </div>
</section>
@endsection
