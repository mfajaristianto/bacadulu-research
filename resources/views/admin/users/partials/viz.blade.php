@php
    $nf = fn ($n) => number_format((int) $n, 0, ',', '.');
    $unit = ['hour' => 'jam', 'day' => 'hari', 'week' => 'minggu'][$growth['gran']];
    $payload = ['series' => $growth['series'], 'labels' => $growth['labels'], 'gran' => $growth['gran'], 'unit' => $unit];
    $totalUsers = array_sum(array_column($composition, 'n'));
    $nonEmpty = collect($composition)->filter(fn ($c) => $c['n'] > 0)->count();
    $colors = ['ya' => '#4F8A66', 'belum' => '#D9A441'];
    $offset = 0;
@endphp
<div class="bd-us__viz">
    <section class="bd-us__growth" aria-labelledby="us-growth-title" data-us-chart data-us-payload="{{ json_encode($payload, JSON_UNESCAPED_UNICODE) }}">
        <div class="bd-us__chart-head">
            <div>
                <h2 id="us-growth-title" class="bd-ix__side-title">Pertumbuhan Pengguna</h2>
                <p class="bd-us__sub">Per {{ $unit }}, periode terpilih</p>
            </div>
            <div class="bd-us__series" role="tablist" aria-label="Metrik pertumbuhan">
                @foreach ($growth['series'] as $key => $s)
                    <button type="button" role="tab" class="bd-us__serie {{ $loop->first ? 'is-on' : '' }}" id="us-serie-{{ $key }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}" tabindex="{{ $loop->first ? 0 : -1 }}" data-us-serie="{{ $key }}"><i class="bd-us__swatch bd-us__swatch--{{ $key }}" aria-hidden="true"></i>{{ $s['label'] }} <span>{{ $nf($s['total']) }}</span></button>
                @endforeach
            </div>
        </div>

        <div class="bd-us__plot" data-us-plot role="group" aria-label="Grafik pertumbuhan pengguna per {{ $unit }}. Tabel setara tersedia di bawah." tabindex="0"></div>
        <p class="bd-us__tip" data-us-tip role="status" aria-live="polite">Arahkan kursor atau gunakan tombol panah untuk melihat nilai per {{ $unit }}.</p>

        @if ($growth['sparse'])
            <p class="bd-us__caption">Data masih sedikit ({{ $nf($growth['series']['baru']['total']) }} pengguna baru pada periode ini), jadi bentuk grafik belum menunjukkan tren. Baca angkanya apa adanya.</p>
        @endif

        <details class="bd-us__table">
            <summary>Tampilkan sebagai tabel</summary>
            <div class="bd-us__table-scroll" tabindex="0" role="region" aria-label="Tabel pertumbuhan pengguna per {{ $unit }}">
                <table>
                    <thead><tr><th scope="col">{{ ucfirst($unit) }}</th>@foreach ($growth['series'] as $s)<th scope="col">{{ $s['label'] }}</th>@endforeach</tr></thead>
                    <tbody>
                        @foreach ($growth['labels'] as $i => $label)
                            <tr><th scope="row">{{ $label }}</th>@foreach ($growth['series'] as $s)<td>{{ $nf($s['points'][$i]) }}</td>@endforeach</tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
        <p class="bd-ix__hint">Sumber: tabel pengguna (tanggal daftar) dan riset pengguna. Waktu WIB. "Total kumulatif" = akun terdaftar sampai akhir tiap {{ $unit }}.</p>
    </section>

    <section class="bd-us__comp" aria-labelledby="us-comp-title">
        <h2 id="us-comp-title" class="bd-ix__side-title">Komposisi Pengguna</h2>
        <p class="bd-us__sub">Status verifikasi email, sepanjang waktu</p>

        @if ($totalUsers === 0)
            <p class="bd-us__chart-empty">Belum ada akun terdaftar.</p>
        @else
            @if ($nonEmpty > 1)
                <div class="bd-us__donut" data-us-donut>
                    <svg viewBox="0 0 42 42" role="img" aria-label="Diagram komposisi verifikasi email: {{ collect($composition)->map(fn ($c) => $c['label'].' '.$nf($c['n']).' ('.str_replace('.', ',', (string) $c['pct']).'%)')->implode(', ') }}">
                        <circle class="bd-us__donut-track" cx="21" cy="21" r="16" pathLength="100" />
                        @foreach ($composition as $i => $c)
                            @if ($c['n'] > 0)
                                <a href="{{ $u(['verifikasi' => $c['key']]) }}" data-us-nav tabindex="-1">
                                    <circle class="bd-us__seg" data-us-seg="{{ $c['key'] }}" cx="21" cy="21" r="16" pathLength="100" stroke="{{ $colors[$c['key']] }}" stroke-dasharray="{{ max(0.5, $c['pct'] - 0.8) }} {{ 100 - max(0.5, $c['pct'] - 0.8) }}" stroke-dashoffset="{{ -$offset }}" style="--i: {{ $i }}" />
                                </a>
                                @php $offset += $c['pct']; @endphp
                            @endif
                        @endforeach
                    </svg>
                    <div class="bd-us__donut-center" aria-hidden="true">
                        <strong data-us-donut-n>{{ $nf($totalUsers) }}</strong>
                        <span data-us-donut-l>pengguna</span>
                    </div>
                </div>
            @else
                <p class="bd-us__chart-empty">Semua {{ $nf($totalUsers) }} akun berada pada satu kategori ({{ collect($composition)->firstWhere(fn ($c) => $c['n'] > 0)['label'] }}), jadi tidak ada perbandingan untuk divisualkan.</p>
            @endif

            <ul class="bd-us__legend">
                @foreach ($composition as $c)
                    <li>
                        <a href="{{ $u(['verifikasi' => $c['key']]) }}" data-us-nav data-us-leg="{{ $c['key'] }}" data-us-n="{{ $nf($c['n']) }}" data-us-l="{{ $c['label'] }}">
                            <i class="bd-us__dot" style="background: {{ $colors[$c['key']] }}" aria-hidden="true"></i>
                            <span class="bd-us__leg-name">{{ $c['label'] }}</span>
                            <span class="bd-us__leg-n">{{ $nf($c['n']) }} <small>{{ str_replace('.', ',', (string) $c['pct']) }}%</small></span>
                        </a>
                    </li>
                @endforeach
            </ul>
            <p class="bd-ix__hint">Klik segmen (atau baris legenda, bisa dengan keyboard) untuk menyaring direktori di bawah. Persentase dihitung dari seluruh akun terdaftar.</p>
        @endif
    </section>
</div>
