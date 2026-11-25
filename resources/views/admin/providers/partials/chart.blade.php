@php
    $c = $data['chart'];
    $metrics = ['input' => 'Input token', 'output' => 'Output token', 'total' => 'Total token', 'cost' => 'Biaya AI', 'runs' => 'Request'];
    $payload = $c + ['metric' => $metric, 'period' => $range['period'], 'hourly' => $range['hourly'], 'range_text' => $range['text']];
@endphp
<section class="bd-pv__section" aria-labelledby="pv-chart" data-pv-chart-root>
    <div class="bd-pv__section-head">
        <h2 id="pv-chart">Penggunaan sepanjang waktu</h2>
        <p>Dari run riset yang benar-benar memanggil provider. Tiap metrik punya satuan sendiri, jadi ditampilkan terpisah, bukan dalam satu sumbu.</p>
    </div>

    <div class="bd-pv__metrics" role="group" aria-label="Metrik grafik">
        @foreach ($metrics as $key => $label)
            <button type="button" class="bd-pv__tab" data-pv-metric="{{ $key }}" aria-pressed="{{ $metric === $key ? 'true' : 'false' }}">{{ $label }}</button>
        @endforeach
    </div>

    @if ($c['empty'])
        <div class="bd-pv__empty bd-pv__empty--chart">
            <h3>Belum ada data penggunaan</h3>
            <p>Grafik muncul setelah ada run riset yang memanggil provider pada periode ini. Run dengan penyedia mock tidak dihitung.</p>
        </div>
    @else
        <div class="bd-pv__legend" data-pv-legend role="group" aria-label="Pilih provider dan model yang ditampilkan"></div>
        <div class="bd-pv__plot" data-pv-plot tabindex="0" role="img" aria-label="Grafik penggunaan. Gunakan tombol panah kiri dan kanan untuk membaca nilai per titik.">
            <svg data-pv-svg focusable="false"></svg>
            <div class="bd-pv__tip" data-pv-tip hidden></div>
            <div class="bd-pv__plot-state" data-pv-state hidden></div>
        </div>
        <p class="bd-pv__note-line" data-pv-note></p>
        <details class="bd-pv__table-alt">
            <summary>Lihat sebagai tabel</summary>
            <div class="bd-pv__table-wrap" data-pv-table></div>
        </details>
    @endif
    <script type="application/json" data-pv-chart-data>{!! json_encode($payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}</script>
</section>
