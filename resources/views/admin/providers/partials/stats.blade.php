@php
    $s = $data['summary'];
    $keep = request()->only(['search', 'status', 'sort', 'metrik']);
    $cost = $s['cost'];
    $today = now('Asia/Jakarta')->format('Y-m-d');
@endphp

<div class="bd-pv__period" role="group" aria-label="Periode data">
    <div class="bd-pv__tabs">
        @foreach ($periods as $key => $label)
            <a href="{{ route('admin.providers.index', $keep + ['periode' => $key]) }}" class="bd-pv__tab" data-pv-nav="period" @if ($range['period'] === $key) aria-current="true" @endif>{{ $label }}</a>
        @endforeach
    </div>
    @if ($range['period'] === 'custom')
        <form method="GET" action="{{ route('admin.providers.index') }}" class="bd-pv__range" data-pv-range>
            @foreach ($keep as $k => $v)
                <input type="hidden" name="{{ $k }}" value="{{ $v }}">
            @endforeach
            <input type="hidden" name="periode" value="custom">
            <label>Dari <input class="bd-pv__input" type="date" name="dari" value="{{ $range['from'] }}" max="{{ $today }}" required></label>
            <label>Sampai <input class="bd-pv__input" type="date" name="sampai" value="{{ $range['to'] }}" max="{{ $today }}" required></label>
            <button type="submit" class="bd-pv__btn bd-pv__btn--small">Terapkan</button>
        </form>
    @endif
    <p class="bd-pv__range-text">{{ $range['text'] }}</p>
</div>

<dl class="bd-pv__stats" aria-label="Ringkasan penggunaan">
    <div class="bd-pv__stat">
        <dt>Input token</dt>
        <dd>{{ $int($s['input']) }}</dd>
        @if ($s['cached'] !== null && $s['input'])
            <dd class="bd-pv__stat-note">{{ $int($s['cached']) }} di antaranya cache</dd>
        @endif
    </div>
    <div class="bd-pv__stat">
        <dt>Output token</dt>
        <dd>{{ $int($s['output']) }}</dd>
    </div>
    <div class="bd-pv__stat">
        <dt>Total token</dt>
        <dd>{{ $int($s['total']) }}</dd>
        <dd class="bd-pv__stat-note">input + output</dd>
    </div>
    <div class="bd-pv__stat">
        <dt>Biaya AI</dt>
        @if ($cost['usd'] !== null)
            <dd>{{ $usd($cost['usd']) }}</dd>
            <dd class="bd-pv__stat-note">
                @if ($cost['idr'] !== null)
                    ≈ {{ $idr($cost['idr']) }}, estimasi kurs {{ $cost['fx_source'] }} ({{ $cost['fx_as_of'] }})
                @else
                    Tarif: {{ $cost['pricing_source'] }} (diverifikasi {{ $cost['pricing_verified_at'] }})
                @endif
            </dd>
        @else
            <dd class="bd-pv__stat-na">{{ $s['usage_runs'] ? 'Biaya belum dapat dihitung' : 'Belum ada data' }}</dd>
            <dd class="bd-pv__stat-note">{{ $s['usage_runs'] ? $cost['reason'] : 'Belum ada penggunaan token tercatat.' }}</dd>
        @endif
    </div>
    <div class="bd-pv__stat">
        <dt>Request ke provider</dt>
        <dd>{{ $s['provider_runs'] ? $int($s['provider_runs']) : ($s['mock_runs'] ? '0' : '—') }}</dd>
        <dd class="bd-pv__stat-note">satu run riset = satu panggilan</dd>
    </div>
</dl>

@if ($s['partial'])
    <p class="bd-pv__note-line">Sebagian run tidak melaporkan token ({{ $s['usage_runs'] }} dari {{ $s['provider_runs'] }} run), jadi angka di atas bukan total penuh.</p>
@endif
@if ($s['mock_runs'])
    <p class="bd-pv__note-line">{{ $int($s['mock_runs']) }} run memakai penyedia mock (mode uji) dan tidak dihitung sebagai pemakaian API.</p>
@endif
@if (! $s['provider_runs'] && ! $s['mock_runs'])
    <p class="bd-pv__note-line">Belum ada data penggunaan pada periode ini. Angka muncul setelah run riset memanggil provider.</p>
@endif
