@php
    [$from, $to] = $range ?? [null, null];
    $span = $from && $to ? ($from->isSameDay($to) ? $from->locale('id')->translatedFormat('j F Y') : $from->locale('id')->translatedFormat('j M Y').' – '.$to->locale('id')->translatedFormat('j M Y')) : null;
@endphp
<header class="bd-ix__head bd-au__head">
    <div>
        <h1>Log Aktivitas</h1>
        <p>Telusuri perubahan, aktivitas admin, dan jejak audit sistem BacaDulu Research.</p>
    </div>
</header>

@if ($ready)
    <nav class="bd-au__periods" aria-label="Periode">
        <ul>
            @foreach (['hari-ini', '7-hari', '30-hari', 'bulan-ini'] as $key)
                <li><a href="{{ $u(['periode' => $key, 'dari' => null, 'sampai' => null]) }}" data-au-nav class="bd-au__period {{ $f['periode'] === $key ? 'is-on' : '' }}" @if ($f['periode'] === $key) aria-current="true" @endif>{{ $periods[$key] }}</a></li>
            @endforeach
            <li><button type="button" class="bd-au__period {{ $f['periode'] === 'kustom' ? 'is-on' : '' }}" data-au-custom-toggle aria-expanded="{{ $f['periode'] === 'kustom' ? 'true' : 'false' }}" aria-controls="au-custom">{{ $periods['kustom'] }}</button></li>
        </ul>
        <p class="bd-au__span">{{ $span }} <span>· waktu WIB</span></p>
    </nav>

    <form method="GET" action="{{ route('admin.audit.index') }}" class="bd-au__custom" id="au-custom" data-au-form @if ($f['periode'] !== 'kustom') hidden @endif>
        <input type="hidden" name="periode" value="kustom">
        @foreach (['q', 'aktor', 'tipe', 'aksi', 'entitas', 'kategori'] as $k)
            @if (filled($f[$k] ?? null))<input type="hidden" name="{{ $k }}" value="{{ $f[$k] }}">@endif
        @endforeach
        @if ($f['urut'] !== 'terbaru')<input type="hidden" name="urut" value="{{ $f['urut'] }}">@endif
        <label class="bd-ix__field"><span class="bd-ix__label">Dari tanggal</span><input class="bd-ix__input" type="date" name="dari" value="{{ $f['dari'] }}" required></label>
        <label class="bd-ix__field"><span class="bd-ix__label">Sampai tanggal</span><input class="bd-ix__input" type="date" name="sampai" value="{{ $f['sampai'] }}" required></label>
        <button type="submit" class="bd-ix__btn bd-ix__btn--primary">Terapkan</button>
    </form>
@endif
