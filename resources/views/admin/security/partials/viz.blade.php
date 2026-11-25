@php
    $nf = fn ($n) => number_format((int) $n, 0, ',', '.');
    $unit = ['hour' => 'jam', 'day' => 'hari', 'week' => 'minggu'][$activity['gran']];
    $payload = ['series' => $activity['series'], 'labels' => $activity['labels'], 'gran' => $activity['gran'], 'unit' => $unit, 'max' => $activity['max']];
    $colors = ['admin.login' => '#4F8A66', 'admin.login.failed' => '#B5433A', 'admin.logout' => '#7C8B99', 'user.suspended' => '#D9A441', 'user.reactivated' => '#E2BC6A'];
    $distTotal = array_sum(array_column($distribution, 'n'));
@endphp
<div class="bd-sc__viz">
    <section class="bd-sc__chart" aria-labelledby="sc-chart-title" data-sc-chart data-sc-payload="{{ json_encode($payload, JSON_UNESCAPED_UNICODE) }}">
        <div class="bd-sc__chart-head">
            <div>
                <h2 id="sc-chart-title" class="bd-ix__side-title">Aktivitas Akses</h2>
                <p class="bd-sc__sub">Jumlah event per {{ $unit }} pada periode terpilih</p>
            </div>
            @if ($activity['canWeek'])
                <nav class="bd-sc__scale" aria-label="Skala waktu grafik">
                    <a href="{{ $u(['skala' => 'hari']) }}" data-sc-nav class="bd-sc__scale-opt {{ $activity['gran'] === 'day' ? 'is-on' : '' }}" @if ($activity['gran'] === 'day') aria-current="true" @endif>Harian</a>
                    <a href="{{ $u(['skala' => 'minggu']) }}" data-sc-nav class="bd-sc__scale-opt {{ $activity['gran'] === 'week' ? 'is-on' : '' }}" @if ($activity['gran'] === 'week') aria-current="true" @endif>Mingguan</a>
                </nav>
            @endif
        </div>

        <div class="bd-sc__legend" role="group" aria-label="Tampilkan seri">
            @foreach ($activity['series'] as $key => $s)
                <button type="button" class="bd-sc__leg is-on" data-sc-serie="{{ $key }}" aria-pressed="true"><i class="bd-sc__swatch bd-sc__swatch--{{ $key }}" aria-hidden="true"></i>{{ $s['label'] }} <span>{{ $nf($s['total']) }}</span></button>
            @endforeach
        </div>

        @if ($activity['total'] === 0)
            <p class="bd-sc__chart-empty">Tidak ada event akses yang tercatat pada periode ini. Pencatatan login admin aktif, jadi ini berarti memang tidak ada kejadian, bukan data yang hilang.</p>
        @else
            <div class="bd-sc__plot" data-sc-plot role="group" tabindex="0" aria-label="Grafik batang bertumpuk jumlah event akses per {{ $unit }}. Tabel setara tersedia di bawah."></div>
            <p class="bd-sc__tip" data-sc-tip role="status" aria-live="polite">Arahkan kursor atau gunakan tombol panah untuk melihat rincian per {{ $unit }}.</p>
            @if ($activity['total'] < 4 || $activity['activeBuckets'] < 2)
                <p class="bd-sc__caption">Hanya {{ $nf($activity['total']) }} event pada {{ $nf($activity['activeBuckets']) }} {{ $unit }}. Terlalu sedikit untuk disebut pola; baca angkanya apa adanya.</p>
            @endif

            <details class="bd-sc__table">
                <summary>Tampilkan sebagai tabel</summary>
                <div class="bd-sc__table-scroll" tabindex="0" role="region" aria-label="Tabel event akses per {{ $unit }}">
                    <table>
                        <thead><tr><th scope="col">{{ ucfirst($unit) }}</th>@foreach ($activity['series'] as $s)<th scope="col">{{ $s['label'] }}</th>@endforeach</tr></thead>
                        <tbody>
                            @foreach ($activity['labels'] as $i => $label)
                                @php $any = collect($activity['series'])->sum(fn ($s) => $s['points'][$i]); @endphp
                                @if ($any > 0)
                                    <tr><th scope="row">{{ $label }}</th>@foreach ($activity['series'] as $s)<td>{{ $s['points'][$i] }}</td>@endforeach</tr>
                                @endif
                            @endforeach
                        </tbody>
                        <tfoot><tr><th scope="row">Total</th>@foreach ($activity['series'] as $s)<td>{{ $nf($s['total']) }}</td>@endforeach</tr></tfoot>
                    </table>
                </div>
                <p class="bd-ix__hint">Hanya {{ $unit }} yang punya event ditampilkan. Sumber: tabel audit (event akses saja), waktu WIB.</p>
            </details>
        @endif
    </section>

    <section class="bd-sc__dist" aria-labelledby="sc-dist-title">
        <h2 id="sc-dist-title" class="bd-ix__side-title">Jenis Aktivitas Akses</h2>
        <p class="bd-sc__sub">Menurut kode event, pada periode terpilih</p>

        @if ($distTotal === 0)
            <p class="bd-sc__chart-empty">Belum ada event pada periode ini.</p>
        @else
            <div class="bd-sc__seg" role="img" aria-label="{{ collect($distribution)->map(fn ($d) => $d['label'].' '.$nf($d['n']).' ('.str_replace('.', ',', (string) $d['pct']).'%)')->implode(', ') }}">
                @foreach ($distribution as $i => $d)
                    <span class="bd-sc__seg-part" data-sc-part="{{ $d['code'] }}" style="--w: {{ $d['pct'] }}%; --c: {{ $colors[$d['code']] }}; --i: {{ $i }}"></span>
                @endforeach
            </div>
            <ol class="bd-sc__rank">
                @foreach ($distribution as $d)
                    <li>
                        <a href="{{ $u(['jenis' => $d['code']]) }}" data-sc-nav data-sc-rank="{{ $d['code'] }}">
                            <i class="bd-sc__dot" style="background: {{ $colors[$d['code']] }}" aria-hidden="true"></i>
                            <span class="bd-sc__rank-name">{{ $d['label'] }}</span>
                            <span class="bd-sc__rank-n">{{ $nf($d['n']) }} <small>{{ str_replace('.', ',', (string) $d['pct']) }}%</small></span>
                        </a>
                    </li>
                @endforeach
            </ol>
            <p class="bd-ix__hint">@if (count($distribution) === 1)Hanya satu jenis event pada periode ini, jadi tidak ada perbandingan. @endif Klik satu jenis untuk menyaring linimasa di bawah.</p>
        @endif
    </section>
</div>
