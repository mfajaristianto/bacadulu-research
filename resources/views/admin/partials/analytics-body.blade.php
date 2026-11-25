{{-- Isi Pusat Analitik: empat panel tab. Dipakai render awal Beranda dan endpoint JSON admin.dashboard.ai-usage;
     butuh $aiUsage (AiUsageSummary::summary) dan $panels (DashboardAnalytics::panels). Hanya panel Penggunaan AI yang tampil tanpa JS. --}}
@php
    $fmt = fn (int $value): string => number_format($value, 0, ',', '.');
    $val = fn (?int $value): string => is_null($value) ? '—' : $fmt($value);
    $u = $aiUsage;
    $sources = $panels['sources'];
    $prompts = $panels['prompts'];
    $providerRuns = $u['provider_runs'];
    $usageRuns = (int) $u['usage_runs'];
    $cost = $u['cost'];

    $tokenNote = fn (?int $value): string => match (true) {
        is_null($value) => 'Belum tercatat',
        $u['partial'] => 'Parsial · dari '.$fmt($usageRuns).' dari '.$fmt((int) $providerRuns).' run provider nyata',
        default => 'dari '.$fmt($usageRuns).' run dengan data penggunaan',
    };

    $foot = match (true) {
        is_null($providerRuns) => 'Data run riset tidak dapat dibaca, sehingga penggunaan belum dapat dihitung.',
        $providerRuns === 0 => 'Belum ada run dengan provider nyata pada periode ini. Run mode uji (mock) tidak memakai API berbayar dan tidak melaporkan token maupun biaya.',
        $usageRuns === 0 => $fmt($providerRuns).' run memakai provider nyata, tetapi respons belum menyertakan data token.',
        $u['partial'] => 'Token dihitung dari '.$fmt($usageRuns).' dari '.$fmt($providerRuns).' run provider nyata (parsial); sisanya belum melaporkan penggunaan, jadi total sebenarnya lebih besar.',
        default => 'Token dihitung dari semua '.$fmt($providerRuns).' run provider nyata pada periode ini.',
    };

    $rate = is_null($u['success_rate']) ? '—' : number_format($u['success_rate'], 1, ',', '.').'%';
    $finished = (int) $u['runs_ok'] + (int) $u['runs_failed'] + (int) $u['runs_insufficient'];
    $unit = $u['series'] !== [] && str_contains($u['series'][0]['t'], 'WIB') ? 'jam' : 'hari';

    $tokenSeries = [['key' => 'input', 'label' => 'Input', 'tone' => 'input'], ['key' => 'output', 'label' => 'Output', 'tone' => 'output']];
    $hasTokens = ! is_null($u['input']) || ! is_null($u['output']);

    $usd = fn (float $v): string => '$'.number_format($v, $v < 1 ? 4 : 2, ',', '.');
    $idr = fn (float $v): string => 'Rp '.number_format($v, 0, ',', '.');

    $topPrompt = $u['prompts'][0] ?? null;

    $statusLabels = ['draft' => 'Draf', 'queued' => 'Antre', 'processing' => 'Diproses', 'ready' => 'Siap terbit', 'published' => 'Terbit', 'archived' => 'Diarsipkan', 'failed' => 'Gagal'];
    $statusRows = collect($sources['statuses'])->map(fn ($row) => ['name' => $statusLabels[$row['key']] ?? ucfirst($row['key']), 'sub' => null, 'count' => $row['count']])->all();
    $sourceTotal = collect($sources['statuses'])->sum('count');
    $noRuns = ($u['runs_total'] ?? 0) === 0;
@endphp
<p class="bd-dash__range">Periode: <time datetime="{{ $u['range_start'] }}">{{ $u['range_text'] }}</time></p>

{{-- Penggunaan AI --}}
<div class="bd-dash__tabpanel" role="tabpanel" id="dash-panel-ai" aria-labelledby="dash-tab-ai" data-dash-panel="ai" data-active>
    <dl class="bd-dash__stats bd-dash__stats--lead">
        <div class="bd-dash__stat bd-dash__stat--lead">
            <dt>Total Tokens</dt>
            <dd class="{{ is_null($u['total_tokens']) ? 'is-empty' : '' }}">{{ $val($u['total_tokens']) }}</dd>
            <small>{{ $tokenNote($u['total_tokens']) }}</small>
        </div>
        <div class="bd-dash__stat">
            <dt>Input Tokens</dt>
            <dd class="{{ is_null($u['input']) ? 'is-empty' : '' }}">{{ $val($u['input']) }}</dd>
            <small class="{{ $u['partial'] && ! is_null($u['input']) ? 'is-partial' : '' }}">{{ $tokenNote($u['input']) }}</small>
        </div>
        <div class="bd-dash__stat">
            <dt>Output Tokens</dt>
            <dd class="{{ is_null($u['output']) ? 'is-empty' : '' }}">{{ $val($u['output']) }}</dd>
            <small class="{{ $u['partial'] && ! is_null($u['output']) ? 'is-partial' : '' }}">{{ $tokenNote($u['output']) }}</small>
        </div>
        <div class="bd-dash__stat">
            <dt>Cached Input Tokens</dt>
            <dd class="{{ is_null($u['cached']) ? 'is-empty' : '' }}">{{ $val($u['cached']) }}</dd>
            <small>{{ is_null($u['cached']) ? 'Tidak dilaporkan provider' : 'bagian input yang dilayani dari cache' }}</small>
        </div>
    </dl>

    <dl class="bd-dash__stats">
        <div class="bd-dash__stat">
            <dt>Total Riset</dt>
            <dd class="{{ is_null($u['runs_total']) ? 'is-empty' : '' }}">{{ $val($u['runs_total']) }}</dd>
            <small>@if (is_null($providerRuns)) Belum tersedia @else {{ $fmt($providerRuns) }} run provider nyata · {{ $fmt((int) $u['mock_runs']) }} run mode uji (mock) @endif</small>
        </div>
        <div class="bd-dash__stat">
            <dt>Riset Berhasil</dt>
            <dd class="{{ is_null($u['runs_ok']) ? 'is-empty' : '' }}">{{ $val($u['runs_ok']) }}</dd>
            <small>status selesai</small>
        </div>
        <div class="bd-dash__stat">
            <dt>Riset Gagal</dt>
            <dd class="{{ ($u['runs_failed'] ?? 0) > 0 ? 'is-bad' : (is_null($u['runs_failed']) ? 'is-empty' : '') }}">{{ $val($u['runs_failed']) }}</dd>
            <small>gagal atau sitasi tidak valid</small>
        </div>
        <div class="bd-dash__stat">
            <dt>Success Rate</dt>
            <dd class="{{ is_null($u['success_rate']) ? 'is-empty' : '' }}">{{ $rate }}</dd>
            <small>{{ $finished > 0 ? 'dari '.$fmt($finished).' riset yang sudah selesai' : 'Belum ada riset selesai' }}</small>
        </div>
    </dl>

    @include('admin.partials.dash-chart', [
        'title' => 'Token per '.$unit,
        'series' => $tokenSeries,
        'rows' => $u['series'],
        'empty' => $hasTokens ? null : 'Belum ada data token pada periode ini.',
        'summary' => 'Grafik garis input dan output token per '.$unit.' pada periode '.$u['label'].'.',
    ])

    <dl class="bd-dash__stats bd-dash__stats--cost">
        <div class="bd-dash__stat">
            <dt>Biaya AI · IDR</dt>
            @if (! is_null($cost['idr']))
                <dd>{{ $idr($cost['idr']) }}</dd>
                <small>{{ $cost['partial'] ? 'Parsial, ' : '' }}Kurs {{ $cost['fx_source'] }}, {{ $cost['fx_as_of'] }}</small>
            @else
                <dd class="is-na">Biaya belum tersedia</dd>
                <small>{{ $cost['idr_reason'] }}</small>
            @endif
        </div>
        <div class="bd-dash__stat">
            <dt>Biaya AI · USD</dt>
            @if (! is_null($cost['usd']))
                <dd>{{ $usd($cost['usd']) }}</dd>
                <small>{{ $cost['partial'] ? 'Parsial, ' : '' }}Tarif {{ $cost['pricing_source'] }}, diverifikasi {{ $cost['pricing_verified_at'] }}</small>
            @else
                <dd class="is-na">Biaya belum tersedia</dd>
                <small>{{ $cost['usd_reason'] }}</small>
            @endif
        </div>
    </dl>

    <p class="bd-dash__foot">{{ $foot }}</p>
</div>

{{-- Statistik Prompt --}}
<div class="bd-dash__tabpanel" role="tabpanel" id="dash-panel-prompt" aria-labelledby="dash-tab-prompt" data-dash-panel="prompt">
    <dl class="bd-dash__stats">
        <div class="bd-dash__stat">
            <dt>Total Prompt</dt>
            <dd>{{ $fmt($prompts['total']) }}</dd>
            <small>{{ $fmt($prompts['draft']) }} draf · {{ $fmt($prompts['archived']) }} arsip</small>
        </div>
        <div class="bd-dash__stat">
            <dt>Prompt Aktif</dt>
            <dd>{{ $fmt($prompts['active']) }}</dd>
            <small>versi yang dipakai riset</small>
        </div>
        <div class="bd-dash__stat">
            <dt>Penggunaan Prompt</dt>
            <dd class="{{ is_null($u['prompt_runs']) ? 'is-empty' : '' }}">{{ $val($u['prompt_runs']) }}</dd>
            <small>run riset yang mencatat versi prompt</small>
        </div>
        <div class="bd-dash__stat">
            <dt>Paling Sering Digunakan</dt>
            @if ($topPrompt)
                <dd class="is-text">{{ $topPrompt['name'] }}</dd>
                <small>{{ $fmt($topPrompt['count']) }} kali · {{ $topPrompt['sub'] }}</small>
            @else
                <dd class="is-empty">—</dd>
                <small>Belum ada penggunaan pada periode ini</small>
            @endif
        </div>
    </dl>

    <div class="bd-dash__pair">
        @include('admin.partials.dash-chart', [
            'title' => 'Penggunaan prompt per '.$unit,
            'series' => [['key' => 'prompt', 'label' => 'Penggunaan', 'tone' => 'output']],
            'rows' => $u['series'],
            'empty' => ($u['prompt_runs'] ?? 0) > 0 ? null : 'Belum ada penggunaan prompt pada periode ini.',
            'summary' => 'Grafik penggunaan prompt per '.$unit.' pada periode '.$u['label'].'.',
        ])
        <section aria-label="Prompt paling sering digunakan">
            <h3 class="bd-dash__sub">Peringkat prompt</h3>
            @include('admin.partials.dash-rank', ['items' => $u['prompts'], 'unit' => 'kali', 'empty' => 'Belum ada prompt yang dipakai pada periode ini.'])
        </section>
    </div>
</div>

{{-- Sumber Terbit: hanya dokumen berstatus terbit; antrean impor dihitung terpisah --}}
<div class="bd-dash__tabpanel" role="tabpanel" id="dash-panel-source" aria-labelledby="dash-tab-source" data-dash-panel="source">
    <dl class="bd-dash__stats">
        <div class="bd-dash__stat">
            <dt>Total Sumber Terbit</dt>
            <dd>{{ $fmt($sources['total']) }}</dd>
            <small>dari {{ $fmt($sourceTotal) }} sumber di pustaka</small>
        </div>
        <div class="bd-dash__stat">
            <dt>Sumber Baru Terbit</dt>
            <dd>{{ $fmt($sources['new']) }}</dd>
            <small>diterbitkan pada periode ini</small>
        </div>
        <div class="bd-dash__stat">
            <dt>Antrean Impor</dt>
            <dd>{{ $fmt($sources['sync_pending']) }}</dd>
            <small>menunggu ditinjau · belum terbit</small>
        </div>
        <div class="bd-dash__stat">
            <dt>Total di Pustaka</dt>
            <dd>{{ $fmt($sourceTotal) }}</dd>
            <small>semua status</small>
        </div>
    </dl>

    @include('admin.partials.dash-chart', [
        'title' => 'Sumber diterbitkan per '.$unit,
        'series' => [['key' => 'count', 'label' => 'Diterbitkan', 'tone' => 'output']],
        'rows' => $sources['series'],
        'empty' => $sources['new'] > 0 ? null : 'Belum ada sumber yang diterbitkan pada periode ini.',
        'summary' => 'Grafik jumlah sumber yang diterbitkan per '.$unit.' pada periode '.$u['label'].'.',
    ])

    <div class="bd-dash__pair bd-dash__pair--even">
        <section aria-label="Jenis sumber terbit">
            <h3 class="bd-dash__sub">Jenis sumber terbit</h3>
            @include('admin.partials.dash-rank', ['items' => $sources['types'], 'unit' => 'sumber', 'empty' => 'Belum ada sumber terbit.'])
        </section>
        <section aria-label="Status sumber">
            <h3 class="bd-dash__sub">Status sumber</h3>
            @include('admin.partials.dash-rank', ['items' => $statusRows, 'unit' => 'sumber', 'empty' => 'Pustaka masih kosong.'])
        </section>
    </div>
</div>

{{-- Riset --}}
<div class="bd-dash__tabpanel" role="tabpanel" id="dash-panel-research" aria-labelledby="dash-tab-research" data-dash-panel="research">
    <dl class="bd-dash__stats">
        <div class="bd-dash__stat">
            <dt>Jumlah Riset</dt>
            <dd class="{{ is_null($u['runs_total']) ? 'is-empty' : '' }}">{{ $val($u['runs_total']) }}</dd>
            <small>semua run pada periode ini</small>
        </div>
        <div class="bd-dash__stat">
            <dt>Berhasil</dt>
            <dd class="{{ is_null($u['runs_ok']) ? 'is-empty' : '' }}">{{ $val($u['runs_ok']) }}</dd>
            <small>status selesai</small>
        </div>
        <div class="bd-dash__stat">
            <dt>Gagal</dt>
            <dd class="{{ ($u['runs_failed'] ?? 0) > 0 ? 'is-bad' : (is_null($u['runs_failed']) ? 'is-empty' : '') }}">{{ $val($u['runs_failed']) }}</dd>
            <small>{{ ($u['runs_insufficient'] ?? 0) > 0 ? '+ '.$fmt($u['runs_insufficient']).' berhenti karena bukti kurang' : 'gagal atau sitasi tidak valid' }}</small>
        </div>
        <div class="bd-dash__stat">
            <dt>Success Rate</dt>
            <dd class="{{ is_null($u['success_rate']) ? 'is-empty' : '' }}">{{ $rate }}</dd>
            <small>{{ $finished > 0 ? 'dari '.$fmt($finished).' riset yang sudah selesai' : 'Belum ada riset selesai' }}</small>
        </div>
    </dl>

    <div class="bd-dash__pair">
        @include('admin.partials.dash-chart', [
            'title' => 'Riset per '.$unit,
            'series' => [['key' => 'ok', 'label' => 'Berhasil', 'tone' => 'input'], ['key' => 'fail', 'label' => 'Gagal', 'tone' => 'output']],
            'rows' => $u['series'],
            'empty' => $noRuns ? 'Belum ada riset pada periode ini.' : null,
            'summary' => 'Grafik riset berhasil dan gagal per '.$unit.' pada periode '.$u['label'].'.',
        ])
        <section aria-label="Alat riset paling sering digunakan">
            <h3 class="bd-dash__sub">Alat riset terpopuler</h3>
            @include('admin.partials.dash-rank', ['items' => $u['tools'], 'unit' => 'riset', 'empty' => 'Belum ada riset yang mencatat alat pada periode ini.'])
        </section>
    </div>
</div>
