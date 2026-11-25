@extends('layouts.admin')

@section('title', 'Beranda Admin')
@section('page_title', 'Beranda')

@php
    /* Plain-language labels for raw status codes. */
    $statusLabels = [
        'draft' => 'Draf',
        'queued' => 'Antre',
        'processing' => 'Diproses',
        'ready' => 'Siap terbit',
        'published' => 'Terbit',
        'archived' => 'Diarsipkan',
        'failed' => 'Gagal',
    ];

    $statusTone = fn (?string $status) => match ($status) {
        'published' => 'success',
        'ready' => 'info',
        'failed' => 'danger',
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

    $n = fn (string $key): int => (int) ($stats[$key] ?? 0);
    $fmt = fn (int $value): string => number_format($value, 0, ',', '.');
    $num = fn (string $key): string => $fmt($n($key));
    $pct = fn (int $part, int $whole): ?int => $whole > 0 ? (int) round($part / $whole * 100) : null;
    $has = fn (string $name): bool => \Illuminate\Support\Facades\Route::has($name);
    $time = fn ($value) => \Illuminate\Support\Carbon::parse($value);
    $ago = fn ($value): string => $time($value)->locale('id')->diffForHumans();
    $wib = fn ($value): string => $time($value)->timezone('Asia/Jakarta')->locale('id')->translatedFormat('j M Y, H.i').' WIB';

    $sourcePct = $pct($n('published'), $n('sources'));

    /* Distribusi akun: irisan = status di database; total di tengah donut, bukan irisan sendiri (tanpa hitung ganda). */
    $usersTotal = $n('users');
    $userRows = [
        ['key' => 'active', 'label' => 'Akun berstatus aktif', 'count' => $n('users_active')],
        ['key' => 'suspended', 'label' => 'Akun suspended', 'count' => $n('users_suspended')],
    ];

    if ($n('users_other') > 0) {
        $userRows[] = ['key' => 'other', 'label' => 'Status lainnya', 'count' => $n('users_other')];
    }

    $share = function (int $count) use ($usersTotal): string {
        if ($usersTotal === 0) {
            return '—';
        }

        $value = $count / $usersTotal * 100;

        return $value > 0 && $value < 1 ? '<1%' : round($value).'%';
    };

    $userRows = array_map(fn ($row) => $row + ['share' => $share($row['count'])], $userRows);

    $donutLabel = $usersTotal === 0
        ? 'Belum ada akun terdaftar'
        : 'Distribusi status dari '.$fmt($usersTotal).' akun: '.collect($userRows)->map(fn ($row) => $row['label'].' '.$fmt($row['count']).' ('.$share($row['count']).')')->implode(', ');

    $coveragePct = $pct($n('tools_with_prompt'), $n('tools_active'));
    $toolsMissing = max(0, $n('tools_active') - $n('tools_with_prompt'));

    /* Ikon + nada semantik untuk Aktivitas Terbaru, dari awalan kode aksi. */
    $eventMeta = fn (string $action): array => match (true) {
        str_ends_with($action, 'failed') => ['alert', 'coral'],
        str_starts_with($action, 'source_sync.') => ['sync', 'info'],
        $action === 'source.publish' => ['check', 'green'],
        $action === 'source.archive' => ['archive', 'navy'],
        str_starts_with($action, 'source.') => ['file', 'gold'],
        str_starts_with($action, 'admin.') => ['user', 'navy'],
        str_starts_with($action, 'research.') => ['search', 'info'],
        str_starts_with($action, 'document.') => ['file', 'coral'],
        str_starts_with($action, 'prompt.') => ['prompt', 'coral'],
        str_starts_with($action, 'settings.') => ['settings', 'navy'],
        default => ['info', 'navy'],
    };

    /* Status AI hanya dari konfigurasi (lihat DashboardController::aiStatus); "terhubung" tidak diklaim tanpa uji koneksi. */
    $ai = match ($aiStatus['state']) {
        'mock' => ['tone' => 'info', 'label' => 'Mode uji (mock)', 'note' => 'Jawaban riset belum memakai layanan AI sungguhan.'],
        'unconfigured' => ['tone' => 'warning', 'label' => 'Belum dikonfigurasi', 'note' => 'Provider dipilih, tetapi API key belum diisi.'],
        'unknown' => ['tone' => 'danger', 'label' => 'Bermasalah', 'note' => 'Nilai AI_PROVIDER tidak didukung; gunakan mock atau openai.'],
        default => ['tone' => 'neutral', 'label' => 'Belum diverifikasi'.($aiStatus['model'] !== '' ? ' · '.$aiStatus['model'] : ''), 'note' => 'Terkonfigurasi. Koneksi tidak diuji dari Beranda.'],
    };

    $sourceCheck = match (true) {
        $n('sources') === 0 => ['warning', 'Belum ada sumber'],
        $n('failed') > 0 => ['danger', 'Ada yang gagal'],
        $n('published') === 0 => ['warning', 'Belum ada yang terbit'],
        default => ['success', 'Siap'],
    };

    $toolCheck = match (true) {
        $n('tools_active') === 0 => ['warning', 'Belum ada alat aktif'],
        $toolsMissing > 0 => ['warning', $fmt($toolsMissing).' perlu konfigurasi'],
        default => ['success', 'Lengkap'],
    };

    $checksPassed = collect([$sourceCheck[0], $toolCheck[0], $ai['tone']])->filter(fn ($tone) => $tone === 'success')->count();

    /* Hanya kondisi yang bisa ditindaklanjuti; urut kritis -> peringatan -> info. */
    $levels = [
        'critical' => ['rank' => 0, 'label' => 'Kritis', 'icon' => 'alert'],
        'warning' => ['rank' => 1, 'label' => 'Peringatan', 'icon' => 'alert'],
        'info' => ['rank' => 2, 'label' => 'Info', 'icon' => 'info'],
    ];

    $issues = collect([
        [
            'show' => $n('failed') > 0,
            'level' => 'critical',
            'count' => $n('failed'),
            'title' => 'sumber gagal diproses',
            'hint' => 'Periksa file PDF lalu proses ulang.',
            'href' => route('admin.library.index', ['status' => 'failed']),
        ],
        [
            'show' => $aiStatus['state'] === 'unknown',
            'level' => 'critical',
            'count' => null,
            'title' => 'Provider AI tidak dikenali',
            'hint' => 'Riset pengguna tidak bisa berjalan.',
            'href' => route('admin.settings.index'),
        ],
        [
            'show' => $n('runs_failed_today') > 0,
            'level' => 'warning',
            'count' => $n('runs_failed_today'),
            'title' => 'riset pengguna gagal hari ini',
            'hint' => 'Lihat penyebabnya di riwayat riset.',
            'href' => route('admin.ai-runs.index', ['status' => 'failed']),
        ],
        [
            'show' => $toolsMissing > 0,
            'level' => 'warning',
            'count' => $toolsMissing,
            'title' => 'alat riset tanpa instruksi aktif',
            'hint' => 'Aktifkan versi instruksi untuk alat tersebut.',
            'href' => route('admin.tools.index'),
        ],
        [
            'show' => $aiStatus['state'] === 'unconfigured',
            'level' => 'warning',
            'count' => null,
            'title' => 'Konfigurasi AI belum lengkap',
            'hint' => 'API key OpenAI belum diisi.',
            'href' => route('admin.settings.index'),
        ],
        [
            'show' => $n('sync_pending') > 0 && $has('admin.source-sync.index'),
            'level' => 'info',
            'count' => $n('sync_pending'),
            'title' => 'item impor menunggu ditinjau',
            'hint' => 'Pilih kategori lalu impor atau abaikan.',
            'href' => $has('admin.source-sync.index') ? route('admin.source-sync.index') : '#',
        ],
        [
            'show' => $n('ready') > 0,
            'level' => 'info',
            'count' => $n('ready'),
            'title' => 'sumber siap diterbitkan',
            'hint' => 'Belum bisa dipakai pengguna sampai diterbitkan.',
            'href' => route('admin.library.index', ['status' => 'ready']),
        ],
        [
            'show' => $aiStatus['state'] === 'mock',
            'level' => 'info',
            'count' => null,
            'title' => 'Layanan AI masih mode uji (mock)',
            'hint' => 'Wajar untuk pengujian; ganti sebelum dipakai pengguna.',
            'href' => route('admin.settings.index'),
        ],
    ])->where('show', true)->sortBy(fn ($issue) => $levels[$issue['level']]['rank'])->values();

    $issueTally = $issues->countBy('level');
    $topLevel = $issues->first()['level'] ?? 'clear';
@endphp

@php
    $signed = fn (int $v): string => ($v > 0 ? '+' : ($v < 0 ? '−' : '')).$fmt(abs($v));
    $signedPct = fn (float $v): string => ($v > 0 ? '+' : ($v < 0 ? '−' : '')).number_format(abs($v), 1, ',', '.').'%';
    $kpiIcon = ['users' => ['users', 'navy'], 'sources' => ['library', 'gold'], 'prompts' => ['prompt', 'coral'], 'runs' => ['chart', 'info']];
    $tabs = ['ai' => 'Penggunaan AI', 'prompt' => 'Statistik Prompt', 'source' => 'Sumber Terbit', 'research' => 'Riset'];
    $sysItems = [
        ['href' => route('admin.library.index'), 'tone' => $sourceCheck[0], 'name' => 'Sumber', 'text' => is_null($sourcePct) ? 'Pustaka masih kosong' : $num('published').' dari '.$num('sources').' terbit', 'badge' => $sourceCheck[1]],
        ['href' => route('admin.tools.index'), 'tone' => $toolCheck[0], 'name' => 'Alat riset', 'text' => is_null($coveragePct) ? 'Belum ada alat aktif' : $num('tools_with_prompt').' dari '.$num('tools_active').' alat aktif', 'badge' => $toolCheck[1]],
        ['href' => route('admin.settings.index'), 'tone' => $ai['tone'], 'name' => 'Layanan AI', 'text' => $ai['label'], 'badge' => null],
    ];
@endphp

@section('content')
<section class="admin-page bd-dash" data-dash>

    {{-- A. Greeting Bubu + status sistem + perlu tindakan: satu kepala halaman, tanpa banner ----------- --}}
    <header class="bd-dash__hero" aria-labelledby="dash-greeting-title">
        <img
            class="bd-dash__bubu"
            src="{{ asset('assets/mascot/mascot-admin-dashboard.png') }}"
            alt=""
            width="112"
            height="112"
            decoding="async"
            data-dash-bubu
        >
        <div class="bd-dash__hero-copy">
            <h1 id="dash-greeting-title">Selamat bekerja, Admin!</h1>
            <p>Pantau sumber, aktivitas riset, prompt AI, dan pengguna BacaDulu Research dari satu beranda.</p>
            <ul class="bd-dash__sys" aria-label="Status sistem">
                @foreach ($sysItems as $item)
                    <li>
                        <a href="{{ $item['href'] }}" class="bd-dash__sys-item">
                            <span class="bd-dash__dot bd-dash__dot--{{ $item['tone'] }}" aria-hidden="true"></span>
                            <span><b>{{ $item['name'] }}</b> {{ $item['text'] }}@if ($item['badge']) · {{ $item['badge'] }}@endif</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
        <nav class="bd-dash__quick" aria-label="Aksi cepat">
            @if ($has('admin.library.create'))
                <a href="{{ route('admin.library.create') }}" class="bd-dash__btn bd-dash__btn--primary">
                    @include('admin.partials.dash-icon', ['name' => 'plus'])
                    Tambah Sumber
                </a>
            @endif
            @if ($has('admin.source-sync.index'))
                <a href="{{ route('admin.external-sources.index') }}" class="bd-dash__btn">Sumber Eksternal</a>
            @endif
            @if ($has('admin.prompts.index'))
                <a href="{{ route('admin.prompts.index') }}" class="bd-dash__btn">Kelola Prompt</a>
            @endif
        </nav>
    </header>

    <section class="bd-dash__attention" aria-labelledby="dash-attention-title">
        <h2 id="dash-attention-title" class="bd-dash__attention-title">
            @if ($issues->isEmpty())
                <span class="bd-dash__dot bd-dash__dot--success" aria-hidden="true"></span>
                Tidak ada masalah utama yang terdeteksi.
            @else
                <span class="bd-dash__dot bd-dash__dot--{{ $topLevel === 'critical' ? 'danger' : ($topLevel === 'warning' ? 'warning' : 'info') }}" aria-hidden="true"></span>
                Perlu tindakan <span class="bd-dash__count">{{ $issues->count() }}</span>
            @endif
        </h2>

        @if ($issues->isEmpty())
            <p class="bd-dash__muted">Sumber, alat riset, dan konfigurasi AI tidak menunjukkan kendala.</p>
        @else
            <ul class="bd-dash__issues">
                @foreach ($issues as $issue)
                    <li>
                        <a href="{{ $issue['href'] }}" class="bd-dash__issue bd-dash__issue--{{ $issue['level'] }}">
                            <span class="bd-dash__issue-text">
                                <strong>@if (! is_null($issue['count']))<b>{{ $fmt($issue['count']) }}</b> @endif{{ $issue['title'] }}</strong>
                                <small><span class="bd-dash__issue-level">{{ $levels[$issue['level']]['label'] }}</span> · {{ $issue['hint'] }}</small>
                            </span>
                            <span class="bd-dash__go">@include('admin.partials.dash-icon', ['name' => 'chevron'])</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- B. Empat statistik utama: strip terbuka, perubahan dari data historis ----------------------- --}}
    <ul class="bd-dash__kpis" aria-label="Statistik utama">
        @foreach ($kpis as $kpi)
            @php
                $delta = $kpi['delta'];
                $dir = is_null($delta) ? 'none' : ($kpi['activity'] ? 'flat' : ($delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'flat')));
                $note = $kpi['caption'].' · '.(is_null($kpi['pct']) ? $kpi['no_pct'] : $kpi['versus']);
            @endphp
            <li>
                <a href="{{ $kpi['href'] }}" class="bd-dash__kpi">
                    <span class="bd-dash__kpi-label">
                        <span class="bd-dash__icon bd-dash__icon--sm bd-dash__icon--{{ $kpiIcon[$kpi['key']][1] }}">@include('admin.partials.dash-icon', ['name' => $kpiIcon[$kpi['key']][0]])</span>
                        {{ $kpi['label'] }}
                    </span>
                    <strong class="bd-dash__kpi-value">{{ $fmt($kpi['value']) }}</strong>
                    <span class="bd-dash__delta bd-dash__delta--{{ $dir }}">
                        @if (is_null($delta))
                            Perbandingan belum tersedia
                        @elseif ($kpi['activity'])
                            {{ $fmt($delta) }} aktivasi
                        @else
                            @include('admin.partials.dash-icon', ['name' => $dir])
                            @if ($delta === 0) Tidak berubah @else {{ $signed($delta) }}@unless (is_null($kpi['pct'])) · {{ $signedPct($kpi['pct']) }}@endunless @endif
                        @endif
                    </span>
                    <small class="bd-dash__kpi-note">{{ $note }}</small>
                </a>
            </li>
        @endforeach
    </ul>

    {{-- C. Diagram pengguna interaktif ---------------------------------------------------------- --}}
    <section class="bd-dash__section bd-dash__users" aria-labelledby="dash-users-title" data-dash-users>
        <header class="bd-dash__section-head">
            <div>
                <h2 id="dash-users-title">Distribusi Akun</h2>
                <p>Status akun menurut database. Arahkan kursor atau pilih status untuk melihat rinciannya.</p>
            </div>
            <a href="{{ route('admin.users.index') }}" class="bd-dash__link">Kelola pengguna @include('admin.partials.dash-icon', ['name' => 'arrow'])</a>
        </header>

        <div class="bd-dash__users-body">
            @include('admin.partials.dash-donut', ['segments' => $userRows, 'total' => $usersTotal, 'label' => $donutLabel])

            <div class="bd-dash__users-side">
                <ul class="bd-dash__legend" aria-label="Status akun">
                    @foreach ($userRows as $row)
                        <li>
                            <button type="button" class="bd-dash__legend-row" data-seg="{{ $row['key'] }}" aria-pressed="false" @disabled($row['count'] === 0)>
                                <span class="bd-dash__swatch bd-dash__swatch--{{ $row['key'] }}" aria-hidden="true"></span>
                                <span class="bd-dash__legend-label">{{ $row['label'] }}</span>
                                <b class="bd-dash__legend-count">{{ $fmt($row['count']) }}</b>
                                <span class="bd-dash__legend-pct">{{ $row['share'] }}</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
                <p class="bd-dash__muted">
                    @if ($usersTotal === 0)
                        Belum ada akun terdaftar. Distribusi muncul setelah pengguna pertama mendaftar.
                    @else
                        Status diambil dari kolom status akun; ini bukan jumlah pengguna yang sedang online.
                    @endif
                </p>
            </div>
        </div>
    </section>

    {{-- D. Pusat Analitik: empat tab, filter periode tanpa reload (admin.dashboard.ai-usage) ---------- --}}
    <section
        class="bd-dash__section bd-dash__analytics"
        aria-labelledby="dash-analytics-title"
        data-dash-usage
        data-usage-url="{{ route('admin.dashboard.ai-usage') }}"
        data-usage-default-month="{{ $usageMonths[0]['value'] }}"
    >
        <header class="bd-dash__section-head">
            <div>
                <h2 id="dash-analytics-title">Pusat Analitik</h2>
                <p>Penggunaan AI, prompt, sumber terbit, dan riset. Semua tanggal mengikuti WIB.</p>
            </div>
        </header>

        <div class="bd-dash__toolbar">
            <div class="bd-dash__tabs" role="tablist" aria-label="Kategori analitik" data-dash-tabs>
                @foreach ($tabs as $key => $label)
                    <button type="button" role="tab" class="bd-dash__tab" id="dash-tab-{{ $key }}" aria-controls="dash-panel-{{ $key }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}" tabindex="{{ $loop->first ? '0' : '-1' }}" data-tab="{{ $key }}">{{ $label }}</button>
                @endforeach
                <span class="bd-dash__tab-ink" data-tab-ink aria-hidden="true"></span>
            </div>

            <div class="bd-dash__controls">
                <nav class="bd-dash__periods" aria-label="Periode analitik">
                    @foreach ($usagePeriods as $key => $label)
                        <a
                            href="{{ route('admin.dashboard', ['periode' => $key] + ($key === 'history' ? ['bulan' => $usageMonth ?? $usageMonths[0]['value']] : [])) }}"
                            data-usage-period="{{ $key }}"
                            @if ($key === 'history') title="Riwayat per bulan" @endif
                            @if ($usagePeriod === $key) aria-current="true" @endif
                        >{{ $label }}</a>
                    @endforeach
                </nav>

                <label class="bd-dash__field" data-usage-month-field @if ($usagePeriod !== 'history') hidden @endif>
                    <span>Bulan</span>
                    <select data-usage-month aria-label="Bulan riwayat analitik">
                        @foreach (collect($usageMonths)->groupBy('year') as $year => $months)
                            <optgroup label="{{ $year }}">
                                @foreach ($months as $month)
                                    <option value="{{ $month['value'] }}" @selected($month['value'] === ($usageMonth ?? $usageMonths[0]['value']))>{{ $month['label'] }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </label>
            </div>
        </div>

        <p class="bd-dash__sr" role="status" data-usage-status></p>
        <div class="bd-dash__usage-body" data-usage-body aria-busy="false">
            @include('admin.partials.analytics-body')
        </div>
    </section>

    {{-- E. Jenis konten akademik: daftar navigasi editorial; backend belum mengklasifikasikan prompt, jadi tanpa angka ---- --}}
    <section class="bd-dash__section" aria-labelledby="dash-types-title">
        <header class="bd-dash__section-head">
            <h2 id="dash-types-title">Jenis Konten Akademik</h2>
        </header>

        <ul class="bd-dash__nav">
            <li>
                <a href="{{ route('admin.prompts.index') }}" class="bd-dash__nav-row">
                    <span class="bd-dash__icon bd-dash__icon--coral">@include('admin.partials.dash-icon', ['name' => 'file'])</span>
                    <span class="bd-dash__nav-copy">
                        <strong>Jurnal &amp; Makalah</strong>
                        <span>Pengelolaan instruksi untuk artikel ilmiah, penelitian, manuskrip, dan makalah akademik.</span>
                    </span>
                    <span class="bd-dash__badge">Klasifikasi khusus belum tersedia</span>
                    <span class="bd-dash__go">@include('admin.partials.dash-icon', ['name' => 'chevron'])</span>
                </a>
            </li>
            <li>
                <a href="{{ route('admin.prompts.index') }}" class="bd-dash__nav-row">
                    <span class="bd-dash__icon bd-dash__icon--navy">@include('admin.partials.dash-icon', ['name' => 'book'])</span>
                    <span class="bd-dash__nav-copy">
                        <strong>Buku / Book Chapter</strong>
                        <span>Pengelolaan instruksi untuk buku, bab buku, dan monograf akademik.</span>
                    </span>
                    <span class="bd-dash__badge">Klasifikasi khusus belum tersedia</span>
                    <span class="bd-dash__go">@include('admin.partials.dash-icon', ['name' => 'chevron'])</span>
                </a>
            </li>
        </ul>
    </section>

    {{-- F. Sumber terbaru + aktivitas terbaru: umpan terbuka, tanpa kotak per item ----------------- --}}
    <div class="bd-dash__lists">
        <section class="bd-dash__section" aria-labelledby="dash-sources-title">
            <header class="bd-dash__section-head">
                <h2 id="dash-sources-title">Sumber Terbaru</h2>
                <a href="{{ route('admin.library.index') }}" class="bd-dash__link">Lihat semua @include('admin.partials.dash-icon', ['name' => 'arrow'])</a>
            </header>

            @if ($recentDocuments->isEmpty())
                <div class="bd-dash__empty">
                    <p>Pustaka masih kosong. Tambahkan PDF pertama agar pengguna bisa mulai riset.</p>
                    <a href="{{ route('admin.library.create') }}" class="bd-dash__btn bd-dash__btn--primary">+ Tambah sumber</a>
                </div>
            @else
                <ul class="bd-dash__feed">
                    @foreach ($recentDocuments as $document)
                        @php $tone = $statusTone($document->status); @endphp
                        <li>
                            <a href="{{ route('admin.library.edit', $document->id) }}" class="bd-dash__row">
                                <span class="bd-dash__icon bd-dash__icon--sm bd-dash__icon--{{ $tone === 'success' ? 'green' : ($tone === 'danger' ? 'coral' : 'navy') }}">@include('admin.partials.dash-icon', ['name' => 'file'])</span>
                                <span class="bd-dash__row-copy">
                                    <strong title="{{ $document->title }}">{{ $document->title }}</strong>
                                    <small>
                                        {{ $document->category_name ?? 'Tanpa kategori' }}
                                        @if ($document->document_type) · {{ ucfirst(str_replace(['_', '-'], ' ', $document->document_type)) }}@endif
                                        · <span class="bd-dash__status bd-dash__status--{{ $tone }}">{{ $statusLabels[$document->status] ?? ucfirst((string) $document->status) }}</span>
                                    </small>
                                </span>
                                <time class="bd-dash__row-time" datetime="{{ $time($document->created_at)->toIso8601String() }}" title="{{ $wib($document->created_at) }}">{{ $ago($document->created_at) }}</time>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="bd-dash__section" aria-labelledby="dash-activity-title">
            <header class="bd-dash__section-head">
                <h2 id="dash-activity-title">Aktivitas Terbaru</h2>
                <a href="{{ route('admin.audit.index') }}" class="bd-dash__link">Log lengkap @include('admin.partials.dash-icon', ['name' => 'arrow'])</a>
            </header>

            @if ($recentActivity->isEmpty())
                <div class="bd-dash__empty"><p>Belum ada aktivitas yang tercatat.</p></div>
            @else
                <ul class="bd-dash__feed">
                    @foreach ($recentActivity as $activity)
                        @php
                            $event = $eventMeta($activity->action);
                            $tag = $activity->href ? 'a' : 'div';
                        @endphp
                        <li>
                            <{{ $tag }} @if ($activity->href) href="{{ $activity->href }}" @endif class="bd-dash__row">
                                <span class="bd-dash__icon bd-dash__icon--sm bd-dash__icon--{{ $event[1] }}">@include('admin.partials.dash-icon', ['name' => $event[0]])</span>
                                <span class="bd-dash__row-copy">
                                    <strong>{{ $activityLabels[$activity->action] ?? str($activity->action)->replace(['.', '_'], ' ')->ucfirst() }}</strong>
                                    <small>{{ $activity->actor_name ?? 'Sistem' }}</small>
                                </span>
                                <time class="bd-dash__row-time" datetime="{{ $time($activity->created_at)->toIso8601String() }}" title="{{ $wib($activity->created_at) }}">{{ $ago($activity->created_at) }}</time>
                            </{{ $tag }}>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
</section>
@endsection
