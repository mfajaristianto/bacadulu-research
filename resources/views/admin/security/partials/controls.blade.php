@php
    $labels = ['aktif' => 'Aktif', 'tidak-aktif' => 'Tidak aktif', 'belum-diverifikasi' => 'Belum dapat diverifikasi', 'tidak-didukung' => 'Tidak didukung'];
    $groups = collect($controls)->groupBy('group');
    $count = collect($controls)->countBy('status');
@endphp
<section class="bd-sc__controls" aria-labelledby="sc-ctl-title">
    <div class="bd-sc__ctl-head">
        <div>
            <h2 id="sc-ctl-title" class="bd-ix__side-title">Kontrol Perlindungan</h2>
            <p class="bd-sc__sub">Diperiksa saat halaman dibuka dari konfigurasi dan tabel rute yang sebenarnya. Ini status kontrol yang diketahui, bukan skor dan bukan pengganti audit keamanan.</p>
        </div>
        <p class="bd-sc__ctl-sum">
            @foreach ($labels as $key => $label)
                @if (($count[$key] ?? 0) > 0)<span class="bd-sc__st bd-sc__st--{{ $key }}">{{ $count[$key] }} {{ strtolower($label) }}</span>@endif
            @endforeach
        </p>
    </div>

    <div class="bd-sc__ctl-groups">
        @foreach ($groups as $group => $items)
            <div class="bd-sc__ctl-group">
                <h3 class="bd-sc__h">{{ $group }}</h3>
                <ul>
                    @foreach ($items as $c)
                        <li>
                            <div class="bd-sc__ctl-top">
                                <strong>{{ $c['name'] }}</strong>
                                <span class="bd-sc__st bd-sc__st--{{ $c['status'] }}">{{ $labels[$c['status']] }}</span>
                            </div>
                            <p class="bd-sc__ctl-note">{{ $c['note'] }}</p>
                            <p class="bd-sc__ctl-meta"><span>Dasar:</span> {{ $c['basis'] }}</p>
                            @if ($c['limit'])<p class="bd-sc__ctl-meta"><span>Batasan:</span> {{ $c['limit'] }}</p>@endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach

        <div class="bd-sc__ctl-group bd-sc__gaps">
            <h3 class="bd-sc__h">Belum tercatat</h3>
            <ul>
                @foreach ($limitations as $l)
                    <li>
                        <div class="bd-sc__ctl-top"><strong>{{ $l['name'] }}</strong><span class="bd-sc__st bd-sc__st--tidak-dicatat">Tidak dicatat</span></div>
                        <p class="bd-sc__ctl-note">{{ $l['note'] }}</p>
                    </li>
                @endforeach
            </ul>
            <p class="bd-ix__hint">Karena hal di atas tidak dicatat, angka "0" pada halaman ini hanya berlaku untuk event yang memang dicatat. Menambah pencatatan butuh perubahan di alur autentikasi dan akan diusulkan sebagai tahap terpisah.</p>
        </div>
    </div>
</section>
