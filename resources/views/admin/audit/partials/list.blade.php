@if ($logs->total() === 0)
    <div class="bd-ix__empty">
        @if ($filtering)
            <h2>Tidak ada aktivitas yang cocok</h2>
            <p>Tidak ada event pada periode ini yang sesuai dengan filter. Coba perluas periode atau atur ulang filter.</p>
            <a href="{{ $u(['q' => null, 'aktor' => null, 'tipe' => null, 'aksi' => null, 'entitas' => null, 'kategori' => null]) }}" class="bd-ix__btn" data-au-nav>Atur ulang filter</a>
        @else
            <h2>Belum ada aktivitas pada periode ini</h2>
            <p>Event baru muncul di sini ketika admin mengubah konfigurasi, mengelola sumber, atau saat sistem memproses riset. Coba pilih periode yang lebih panjang.</p>
        @endif
    </div>
@else
    <p class="bd-au__count">Menampilkan {{ number_format($logs->firstItem(), 0, ',', '.') }}–{{ number_format($logs->lastItem(), 0, ',', '.') }} dari {{ number_format($logs->total(), 0, ',', '.') }} aktivitas. Log bersifat baca-saja.</p>
    <ul class="bd-au__list" aria-label="Daftar aktivitas">
        @foreach ($rows as $r)
            @php $kindName = $r['actor']['kind'] === 'tidak-diketahui' ? 'Pelaku tidak diketahui' : $r['actor']['name']; @endphp
            <li class="bd-au__item is-{{ $r['outcome'] }}">
                <a class="bd-au__row" href="{{ request()->fullUrlWithQuery(['log' => $r['id']]) }}" data-au-detail="{{ route('admin.audit.show', $r['id']) }}" data-au-id="{{ $r['id'] }}" aria-label="Detail aktivitas #{{ $r['id'] }}: {{ $kindName }} {{ $r['phrase'] }}">
                    <span class="bd-au__avatar bd-au__avatar--{{ $r['actor']['kind'] }}" aria-hidden="true">{{ $r['actor']['initials'] }}</span>
                    <span class="bd-au__main">
                        <span class="bd-au__line"><strong>{{ $kindName }}</strong> {{ $r['phrase'] }}@if ($r['object']) <strong class="bd-au__obj">{{ $r['object'] }}</strong>@endif</span>
                        <span class="bd-au__sub">
                            <span>{{ $r['actor']['kindLabel'] }}@if ($r['actor']['onBehalf']) untuk {{ $r['actor']['onBehalf'] }}@endif</span>
                            <code>{{ $r['action'] }}</code>
                            @if ($r['entity']['isModel'] && $r['entity']['id'] && $r['object'] !== $r['entity']['label'].' #'.$r['entity']['id'])<span>{{ $r['entity']['label'] }} #{{ $r['entity']['id'] }}@unless ($r['entity']['exists']) (sudah tidak ada)@endunless</span>@elseif ($r['entity']['isModel'] && ! $r['entity']['exists'])<span>(objek sudah tidak ada)</span>@endif
                        </span>
                    </span>
                    <span class="bd-au__tags">
                        <span class="bd-au__cat">{{ $r['categoryLabel'] }}</span>
                        @if ($r['outcome'] === 'failed')<span class="bd-au__flag bd-au__flag--bad">Gagal</span>@elseif ($r['outcome'] === 'blocked')<span class="bd-au__flag bd-au__flag--warn">Ditolak</span>@endif
                    </span>
                    <time class="bd-au__time" datetime="{{ $r['time']->toIso8601String() }}">{{ $r['timeLabel'] }}</time>
                    <span class="bd-au__go">Detail @include('admin.tools.partials.icon', ['name' => 'arrow'])</span>
                </a>
            </li>
        @endforeach
    </ul>

    @if ($logs->hasPages())
        <nav class="bd-ix__pager" aria-label="Halaman log">
            @if ($logs->onFirstPage())
                <span class="bd-ix__btn bd-ix__btn--quiet" aria-disabled="true">Sebelumnya</span>
            @else
                <a class="bd-ix__btn" href="{{ $logs->previousPageUrl() }}" data-au-nav rel="prev">Sebelumnya</a>
            @endif
            <span class="bd-ix__muted">Halaman {{ $logs->currentPage() }} dari {{ $logs->lastPage() }}</span>
            @if ($logs->hasMorePages())
                <a class="bd-ix__btn" href="{{ $logs->nextPageUrl() }}" data-au-nav rel="next">Berikutnya</a>
            @else
                <span class="bd-ix__btn bd-ix__btn--quiet" aria-disabled="true">Berikutnya</span>
            @endif
        </nav>
    @endif
@endif
