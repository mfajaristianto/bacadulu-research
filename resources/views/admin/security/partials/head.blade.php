@php
    [$from, $to] = $range;
    $span = $from->isSameDay($to) ? $from->locale('id')->translatedFormat('j F Y') : $from->locale('id')->translatedFormat('j M Y').' – '.$to->locale('id')->translatedFormat('j M Y');
@endphp
<header class="bd-ix__head bd-sc__head">
    <div>
        <h1>Keamanan</h1>
        <p>Pantau aktivitas akses, autentikasi, dan perlindungan akun BacaDulu Research.</p>
    </div>
    @if ($ready)
        <div class="bd-sc__refresh">
            <button type="button" class="bd-ix__btn bd-ix__btn--small" data-sc-refresh>Muat ulang data</button>
            <p>Dimuat {{ $loadedAt->format('H.i') }} WIB. Halaman tidak memperbarui diri otomatis.</p>
        </div>
    @endif
</header>

@if ($ready)
    <nav class="bd-sc__periods" aria-label="Periode">
        <ul>
            @foreach (['hari-ini', '7-hari', '30-hari', 'bulan-ini'] as $key)
                <li><a href="{{ $u(['periode' => $key, 'dari' => null, 'sampai' => null]) }}" data-sc-nav class="bd-sc__period {{ $f['periode'] === $key ? 'is-on' : '' }}" @if ($f['periode'] === $key) aria-current="true" @endif>{{ $periods[$key] }}</a></li>
            @endforeach
            <li><button type="button" class="bd-sc__period {{ $f['periode'] === 'kustom' ? 'is-on' : '' }}" data-sc-custom-toggle aria-expanded="{{ $f['periode'] === 'kustom' ? 'true' : 'false' }}" aria-controls="sc-custom">{{ $periods['kustom'] }}</button></li>
        </ul>
        <p class="bd-sc__span">{{ $span }} <span>· waktu WIB</span></p>
    </nav>

    <form method="GET" action="{{ route('admin.security.index') }}" class="bd-sc__custom" id="sc-custom" data-sc-form @if ($f['periode'] !== 'kustom') hidden @endif>
        <input type="hidden" name="periode" value="kustom">
        @foreach (['q', 'jenis', 'hasil', 'aktor'] as $k)
            @if (filled($f[$k] ?? null))<input type="hidden" name="{{ $k }}" value="{{ $f[$k] }}">@endif
        @endforeach
        @if ($f['urut'] !== 'terbaru')<input type="hidden" name="urut" value="{{ $f['urut'] }}">@endif
        <label class="bd-ix__field"><span class="bd-ix__label">Dari tanggal</span><input class="bd-ix__input" type="date" name="dari" value="{{ $f['dari'] }}" required></label>
        <label class="bd-ix__field"><span class="bd-ix__label">Sampai tanggal</span><input class="bd-ix__input" type="date" name="sampai" value="{{ $f['sampai'] }}" required></label>
        <button type="submit" class="bd-ix__btn bd-ix__btn--primary">Terapkan</button>
    </form>
@endif
