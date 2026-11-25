{{-- Grafik garis interaktif (digambar resources/js/admin-dashboard.js).
     $title, $series: [['key','label','tone']], $rows: titik dari AiUsageSummary::emptySeries, $empty: teks bila tidak ada data (null = ada data), $summary: ringkasan untuk pembaca layar. --}}
@php
    $points = collect($rows)->map(fn ($row) => [
        'l' => $row['l'],
        't' => $row['t'],
        'v' => collect($series)->mapWithKeys(fn ($s) => [$s['key'] => (int) $row[$s['key']]])->all(),
    ])->values()->all();
@endphp
<figure class="bd-dash__chart" data-dash-chart data-chart="{{ json_encode(['series' => $series, 'points' => $points], JSON_UNESCAPED_UNICODE) }}">
    <figcaption class="bd-dash__chart-head">
        <span class="bd-dash__chart-title">{{ $title }}</span>
        @if (is_null($empty) && count($series) > 1)
            <span class="bd-dash__keys" role="group" aria-label="Tampilkan seri">
                @foreach ($series as $s)
                    <button type="button" class="bd-dash__key" data-series="{{ $s['key'] }}" aria-pressed="true">
                        <i class="bd-dash__key-dot bd-dash__key-dot--{{ $s['tone'] }}" aria-hidden="true"></i>{{ $s['label'] }}
                    </button>
                @endforeach
            </span>
        @endif
    </figcaption>
    @if (is_null($empty))
        <div class="bd-dash__plot" data-plot tabindex="0" role="group" aria-label="{{ $summary }} Gunakan panah kiri dan kanan untuk membaca tiap titik."></div>
    @else
        <p class="bd-dash__chart-empty">{{ $empty }}</p>
    @endif
</figure>
