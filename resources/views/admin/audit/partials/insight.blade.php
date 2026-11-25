@php
    $series = $overview['series'];
    $active = collect($overview['series']['all']['points'])->filter(fn ($n) => $n > 0)->count();
    $enough = $overview['total'] >= 6 && $active >= 2;
    $verbs = $overview['verbs'];
    $verbTotal = max(1, array_sum($verbs));
    $payload = ['series' => $series, 'labels' => $overview['labels'], 'buckets' => $overview['buckets'], 'gran' => $overview['gran']];
    $unit = ['hour' => 'jam', 'day' => 'hari', 'week' => 'minggu'][$overview['gran']];
@endphp
<div class="bd-au__insight {{ count($verbs) >= 2 ? 'has-dist' : '' }}">
    <section class="bd-au__chart" aria-labelledby="au-chart-title" data-au-chart data-au-payload="{{ json_encode($payload, JSON_UNESCAPED_UNICODE) }}">
        <div class="bd-au__chart-head">
            <h2 id="au-chart-title" class="bd-ix__side-title">Tren aktivitas sistem</h2>
            @if ($enough && count($series) > 1)
                <div class="bd-au__series" role="tablist" aria-label="Seri tren">
                    @foreach ($series as $key => $s)
                        <button type="button" role="tab" class="bd-au__serie {{ $loop->first ? 'is-on' : '' }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}" tabindex="{{ $loop->first ? 0 : -1 }}" data-au-serie="{{ $key }}">{{ $s['label'] }} <span>{{ number_format($s['total'], 0, ',', '.') }}</span></button>
                    @endforeach
                </div>
            @endif
        </div>

        @if ($overview['total'] === 0)
            <p class="bd-au__chart-empty">Belum ada aktivitas tercatat pada periode ini{{ $filtering ? ' dengan filter yang dipilih' : '' }}.</p>
        @elseif (! $enough)
            <p class="bd-au__chart-empty">Baru {{ $overview['total'] }} aktivitas pada {{ $active }} {{ $unit }}. Tren belum bermakna, jadi grafik tidak ditampilkan. Rinciannya ada di tabel di bawah.</p>
        @else
            <div class="bd-au__plot" data-au-plot role="group" aria-label="Grafik jumlah aktivitas per {{ $unit }}. Tabel setara tersedia di bawah."></div>
            <p class="bd-au__tip" data-au-tip role="status" aria-live="polite">Arahkan kursor atau gunakan tombol panah untuk melihat jumlah per {{ $unit }}.</p>
        @endif

        @if ($overview['total'] > 0)
            <details class="bd-au__table">
                <summary>Tampilkan sebagai tabel</summary>
                <div class="bd-au__table-scroll" tabindex="0" role="region" aria-label="Tabel jumlah aktivitas per {{ $unit }}">
                    <table>
                        <thead><tr><th scope="col">{{ ucfirst($unit) }}</th>@foreach ($series as $s)<th scope="col">{{ $s['label'] }}</th>@endforeach</tr></thead>
                        <tbody>
                            @foreach ($overview['labels'] as $i => $label)
                                @if ($series['all']['points'][$i] > 0)
                                    <tr><th scope="row">{{ $label }}</th>@foreach ($series as $s)<td>{{ $s['points'][$i] }}</td>@endforeach</tr>
                                @endif
                            @endforeach
                        </tbody>
                        <tfoot><tr><th scope="row">Total</th>@foreach ($series as $s)<td>{{ number_format($s['total'], 0, ',', '.') }}</td>@endforeach</tr></tfoot>
                    </table>
                </div>
                <p class="bd-ix__hint">Hanya {{ $unit }} yang punya aktivitas ditampilkan. "Event gagal" dan "Aktivitas admin" dihitung dari kode event, bukan dari kolom tersendiri.</p>
            </details>
        @endif
    </section>

    @if (count($verbs) >= 2)
        <section class="bd-au__dist" aria-labelledby="au-dist-title">
            <h2 id="au-dist-title" class="bd-ix__side-title">Distribusi aktivitas</h2>
            <ol>
                @foreach ($verbs as $verb => $n)
                    <li style="--w: {{ round($n / $verbTotal * 100, 1) }}%">
                        <span class="bd-au__dist-name">{{ \App\Services\Audit\AuditEvents::VERBS[$verb] }}</span>
                        <span class="bd-au__dist-bar" aria-hidden="true"></span>
                        <span class="bd-au__dist-n">{{ number_format($n, 0, ',', '.') }} <small>{{ round($n / $verbTotal * 100) }}%</small></span>
                    </li>
                @endforeach
            </ol>
            <p class="bd-ix__hint">Jenis aksi dipetakan dari kode event.</p>
        </section>
    @endif
</div>
