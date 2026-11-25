@php
    $nf = fn ($n) => number_format((int) $n, 0, ',', '.');
    $maxTool = max(1, collect($research['tools'])->max('n') ?? 1);
    $listed = collect($research['tools'])->sum('n');
    $bands = $research['intensity'];
    $bandTotal = max(1, array_sum(array_column($bands, 'n')));
@endphp
<div class="bd-us__activity">
    <section class="bd-us__research" aria-labelledby="us-res-title">
        <h2 id="us-res-title" class="bd-ix__side-title">Aktivitas Riset Pengguna</h2>
        <p class="bd-us__sub">Alat yang dipakai pada periode terpilih</p>

        @if ($research['runs'] === 0)
            <p class="bd-us__chart-empty">Belum ada riset oleh pengguna pada periode ini. Pilih periode yang lebih panjang untuk melihat penggunaan alat.</p>
        @else
            <ol class="bd-us__bars" aria-label="Jumlah riset per alat AI">
                @foreach ($research['tools'] as $i => $t)
                    <li style="--w: {{ round($t['n'] / $maxTool * 100, 1) }}%; --i: {{ $i }}">
                        <span class="bd-us__bar-name">{{ $t['name'] }}</span>
                        <span class="bd-us__bar" aria-hidden="true"></span>
                        <span class="bd-us__bar-n">{{ $nf($t['n']) }} <small>{{ str_replace('.', ',', (string) $t['pct']) }}%</small></span>
                    </li>
                @endforeach
            </ol>
            @if ($research['runs'] > $listed)
                <p class="bd-ix__hint">{{ $nf($research['runs'] - $listed) }} riset lainnya berasal dari alat di luar {{ count($research['tools']) }} teratas atau alat yang sudah tidak ada.</p>
            @endif
            <p class="bd-ix__hint">{{ $nf($research['runs']) }} riset pada periode ini{{ '' }}@if ($research['failed'] > 0), {{ $nf($research['failed']) }} di antaranya berstatus gagal{{ '' }}@endif.@if ($research['mock'] > 0) {{ $nf($research['mock']) }} riset memakai provider Mock (mode uji), bukan penggunaan AI berbayar.@endif</p>
        @endif

        <div class="bd-us__intensity">
            <h3 class="bd-us__h">Sebaran jumlah riset per pengguna <span>· sepanjang waktu</span></h3>
            @if ($kpis['total'] === 0)
                <p class="bd-us__chart-empty">Belum ada akun terdaftar.</p>
            @else
                <div class="bd-us__meter" role="img" aria-label="{{ collect($bands)->map(fn ($b) => $b['label'].': '.$nf($b['n']).' pengguna')->implode('; ') }}">
                    @foreach ($bands as $i => $b)
                        @if ($b['n'] > 0)<span class="bd-us__meter-seg bd-us__meter-seg--{{ $i }}" style="--w: {{ round($b['n'] / $bandTotal * 100, 2) }}%"></span>@endif
                    @endforeach
                </div>
                <ul class="bd-us__meter-legend">
                    @foreach ($bands as $i => $b)
                        <li><i class="bd-us__dot bd-us__dot--{{ $i }}" aria-hidden="true"></i>{{ $b['label'] }} <strong>{{ $nf($b['n']) }}</strong></li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>

    <section class="bd-us__insights" aria-labelledby="us-ins-title">
        <h2 id="us-ins-title" class="bd-ix__side-title">Insight Keterlibatan</h2>
        <p class="bd-us__sub">Dihitung langsung dari data; bersifat deskriptif</p>
        <ul>
            @foreach ($insights as $i => $ins)
                <li style="--i: {{ $i }}">
                    <span class="bd-us__ins-label">{{ $ins['label'] }}</span>
                    <strong class="bd-us__ins-value">{{ $ins['value'] }}</strong>
                    @if ($ins['pct'] !== null)<span class="bd-us__progress" aria-hidden="true" style="--w: {{ $ins['pct'] }}%"></span>@endif
                    <span class="bd-us__ins-note">{{ $ins['note'] }}</span>
                </li>
            @endforeach
        </ul>
        <p class="bd-ix__hint">Tidak ada data sesi, jadi BacaDulu tidak menampilkan "pengguna online", "aktif", atau retensi.</p>
    </section>
</div>
