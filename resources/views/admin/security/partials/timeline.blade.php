@php
    $nf = fn ($n) => number_format((int) $n, 0, ',', '.');
    $days = collect($rows)->groupBy('day');
@endphp
@if ($logs->total() === 0)
    <div class="bd-ix__empty">
        @if ($filtering)
            <h2>Tidak ada event yang cocok</h2>
            <p>Tidak ada event akses pada periode ini yang sesuai dengan pencarian dan filter. Coba perluas periode atau atur ulang filter.</p>
            <a href="{{ $u(['q' => null, 'jenis' => null, 'hasil' => null, 'aktor' => null]) }}" class="bd-ix__btn" data-sc-nav>Atur ulang filter</a>
        @else
            <h2>Tidak ada event akses pada periode ini</h2>
            <p>Event muncul di sini saat admin masuk atau keluar, gagal login, atau saat akun pengguna ditangguhkan atau diaktifkan kembali. Pencatatan ini aktif, jadi daftar kosong berarti memang tidak ada kejadian.</p>
        @endif
    </div>
@else
    <p class="bd-sc__count">Menampilkan {{ $nf($logs->firstItem()) }}–{{ $nf($logs->lastItem()) }} dari {{ $nf($logs->total()) }} event. Log bersifat baca-saja.</p>
    <div class="bd-sc__tl">
        @foreach ($days as $day => $items)
            <section class="bd-sc__day" aria-label="{{ $day }}">
                <h3 class="bd-sc__day-h">{{ $day }}</h3>
                <ol class="bd-sc__events">
                    @foreach ($items as $r)
                        <li class="bd-sc__event is-{{ $r['result'] }}" style="--i: {{ $loop->index }}">
                            <a class="bd-sc__row" href="{{ request()->fullUrlWithQuery(['log' => $r['id']]) }}" data-sc-detail="{{ route('admin.security.show', $r['id']) }}" data-sc-id="{{ $r['id'] }}" aria-label="Detail event #{{ $r['id'] }}: {{ $r['sentence'] }}, {{ $r['resultLabel'] }}">
                                <time class="bd-sc__clock" datetime="{{ $r['time']->toIso8601String() }}">{{ $r['clock'] }}</time>
                                <span class="bd-sc__mark" aria-hidden="true"></span>
                                <span class="bd-sc__main">
                                    <span class="bd-sc__line">{{ $r['sentence'] }}</span>
                                    <span class="bd-sc__sub-line">
                                        <span>{{ $r['label'] }}</span>
                                        <span>Pelaku: {{ $r['actor'] }}</span>
                                        <span>{{ $r['ip'] ? 'IP '.$r['ip'] : 'IP tidak tercatat' }}</span>
                                    </span>
                                </span>
                                <span class="bd-sc__result bd-sc__result--{{ $r['result'] }}">{{ $r['resultLabel'] }}</span>
                                <span class="bd-sc__go">Detail @include('admin.tools.partials.icon', ['name' => 'arrow'])</span>
                            </a>
                        </li>
                    @endforeach
                </ol>
            </section>
        @endforeach
    </div>

    @if ($logs->hasPages())
        <nav class="bd-ix__pager" aria-label="Halaman event">
            @if ($logs->onFirstPage())
                <span class="bd-ix__btn bd-ix__btn--quiet" aria-disabled="true">Sebelumnya</span>
            @else
                <a class="bd-ix__btn" href="{{ $logs->previousPageUrl() }}" data-sc-nav rel="prev">Sebelumnya</a>
            @endif
            <span class="bd-ix__muted">Halaman {{ $logs->currentPage() }} dari {{ $logs->lastPage() }}</span>
            @if ($logs->hasMorePages())
                <a class="bd-ix__btn" href="{{ $logs->nextPageUrl() }}" data-sc-nav rel="next">Berikutnya</a>
            @else
                <span class="bd-ix__btn bd-ix__btn--quiet" aria-disabled="true">Berikutnya</span>
            @endif
        </nav>
    @endif
@endif
