{{-- Grafik SVG/CSS dari data tersimpan. Animasi masuk dikendalikan CSS (.is-in) dan dimatikan oleh prefers-reduced-motion. --}}
@php
    $bars = function (array $data, ?callable $label = null) {
        $top = max(1, ...array_map('intval', array_values($data) ?: [0]));

        return collect($data)->map(fn ($v, $k) => ['label' => $label ? $label($k) : $k, 'value' => (int) $v, 'pct' => max(2, round($v / $top * 100))])->values();
    };
    $providerBars = $bars($charts['provider'], fn ($k) => $names[$k] ?? $k);
    $categoryBars = $bars($charts['category']);
    $yearBars = $bars($charts['years']);
    $growth = collect($charts['growth']);
    $gMax = max(1, $growth->max('value'));
    $gMin = $growth->min('value');
    $span = max(1, $gMax - $gMin);
    $points = $growth->values()->map(fn ($p, $i) => round($i / max(1, $growth->count() - 1) * 300, 1).','.round(92 - (($p['value'] - $gMin) / $span) * 80, 1))->implode(' ');
    $statusTotal = max(1, array_sum($charts['status']));
    $statusColors = ['completed' => 'ok', 'partial' => 'warn', 'failed' => 'bad', 'rate_limited' => 'warn', 'cancelled' => 'mute', 'queued' => 'run', 'running' => 'run'];
@endphp

<div class="bd-ext__charts">
    <figure class="bd-ext__chart">
        <figcaption>Rekaman per penyedia</figcaption>
        @if ($providerBars->isEmpty()) <p class="bd-ext__empty">Belum ada data.</p> @else
            <ul class="bd-ext__hbars">@foreach ($providerBars as $b)<li><span>{{ $b['label'] }}</span><div><i style="--w: {{ $b['pct'] }}%"></i></div><b>{{ number_format($b['value'], 0, ',', '.') }}</b></li>@endforeach</ul>
        @endif
    </figure>

    <figure class="bd-ext__chart">
        <figcaption>Publikasi per kategori</figcaption>
        @if ($categoryBars->isEmpty()) <p class="bd-ext__empty">Belum ada publikasi yang dikaitkan ke kategori.</p> @else
            <ul class="bd-ext__hbars">@foreach ($categoryBars as $b)<li><span>{{ $b['label'] }}</span><div><i style="--w: {{ $b['pct'] }}%"></i></div><b>{{ number_format($b['value'], 0, ',', '.') }}</b></li>@endforeach</ul>
        @endif
    </figure>

    <figure class="bd-ext__chart bd-ext__chart--wide">
        <figcaption>Pertumbuhan metadata, 30 hari terakhir <span class="bd-ext__muted">(total kumulatif {{ number_format($gMax, 0, ',', '.') }})</span></figcaption>
        <svg viewBox="0 0 300 100" class="bd-ext__line" role="img" aria-label="Grafik garis total publikasi tersimpan selama 30 hari terakhir, dari {{ $gMin }} menjadi {{ $gMax }}" preserveAspectRatio="none">
            <line x1="0" y1="92" x2="300" y2="92" class="bd-ext__axis"/>
            <polyline points="{{ $points }}" pathLength="1" class="bd-ext__path"/>
        </svg>
        <p class="bd-ext__axis-labels"><span>{{ \Illuminate\Support\Carbon::parse($growth->first()['date'])->locale('id')->translatedFormat('j M') }}</span><span>{{ \Illuminate\Support\Carbon::parse($growth->last()['date'])->locale('id')->translatedFormat('j M') }}</span></p>
    </figure>

    <figure class="bd-ext__chart">
        <figcaption>Baru, diperbarui, duplikat per penyedia</figcaption>
        @if (empty($charts['flow'])) <p class="bd-ext__empty">Belum ada sinkronisasi.</p> @else
            <ul class="bd-ext__stack-list">
                @foreach ($charts['flow'] as $code => $f)
                    @php $t = max(1, $f['new'] + $f['updated'] + $f['duplicate']); @endphp
                    <li><span>{{ $names[$code] ?? $code }}</span>
                        <div class="bd-ext__stack" role="img" aria-label="{{ $f['new'] }} baru, {{ $f['updated'] }} diperbarui, {{ $f['duplicate'] }} duplikat">
                            <i class="is-ok" style="--w: {{ $f['new'] / $t * 100 }}%"></i><i class="is-warn" style="--w: {{ $f['updated'] / $t * 100 }}%"></i><i class="is-mute" style="--w: {{ $f['duplicate'] / $t * 100 }}%"></i>
                        </div>
                        <b>{{ $f['new'] }}/{{ $f['updated'] }}/{{ $f['duplicate'] }}</b></li>
                @endforeach
            </ul>
            <p class="bd-ext__legend"><span class="is-ok">Baru</span><span class="is-warn">Diperbarui</span><span class="is-mute">Duplikat</span></p>
        @endif
    </figure>

    <figure class="bd-ext__chart">
        <figcaption>Status sinkronisasi</figcaption>
        @if (empty($charts['status'])) <p class="bd-ext__empty">Belum ada sinkronisasi.</p> @else
            <div class="bd-ext__stack bd-ext__stack--tall" role="img" aria-label="Distribusi status sinkronisasi">
                @foreach ($charts['status'] as $st => $n)<i class="is-{{ $statusColors[$st] ?? 'mute' }}" style="--w: {{ $n / $statusTotal * 100 }}%" title="{{ $statusLabels[$st] ?? $st }}: {{ $n }}"></i>@endforeach
            </div>
            <ul class="bd-ext__legend-list">@foreach ($charts['status'] as $st => $n)<li><span class="bd-ext__dot is-{{ $statusColors[$st] ?? 'mute' }}"></span>{{ $statusLabels[$st] ?? $st }} <b>{{ $n }}</b></li>@endforeach</ul>
        @endif
    </figure>

    <figure class="bd-ext__chart bd-ext__chart--wide">
        <figcaption>Cakupan per tahun terbit <span class="bd-ext__muted">(12 tahun terbaru yang ada datanya)</span></figcaption>
        @if ($yearBars->isEmpty()) <p class="bd-ext__empty">Belum ada tahun terbit.</p> @else
            <ul class="bd-ext__vbars">@foreach ($yearBars as $b)<li><b>{{ $b['value'] }}</b><div><i style="--h: {{ $b['pct'] }}%"></i></div><span>{{ $b['label'] }}</span></li>@endforeach</ul>
        @endif
    </figure>
    <figure class="bd-ext__chart bd-ext__chart--wide">
        <figcaption>Rekaman diproses per jam <span class="bd-ext__muted">(24 jam terakhir, dari batch worker nyata)</span></figcaption>
        @php $ingest = collect($charts['ingest'] ?? []); $iTop = max(1, (int) $ingest->max('value')); @endphp
        @if ($ingest->isEmpty()) <p class="bd-ext__empty">Belum ada batch yang tercatat dalam 24 jam terakhir.</p> @else
            <ul class="bd-ext__vbars">@foreach ($ingest as $b)<li><b>{{ number_format($b['value'], 0, ',', '.') }}</b><div><i style="--h: {{ max(2, round($b['value'] / $iTop * 100)) }}%"></i></div><span>{{ substr($b['hour'], 11, 2) }}:00</span></li>@endforeach</ul>
        @endif
    </figure>
</div>
