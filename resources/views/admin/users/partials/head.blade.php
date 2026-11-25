@php
    [$from, $to] = $range;
    $span = $from->isSameDay($to) ? $from->locale('id')->translatedFormat('j F Y') : $from->locale('id')->translatedFormat('j M Y').' – '.$to->locale('id')->translatedFormat('j M Y');
@endphp
<header class="bd-ix__head bd-us__head">
    <div>
        <h1>Pengguna</h1>
        <p>Pantau pertumbuhan, aktivitas, dan penggunaan BacaDulu Research serta kelola akun pengguna.</p>
    </div>
</header>

<nav class="bd-us__periods" aria-label="Periode analitik">
    <ul>
        @foreach (['hari-ini', '7-hari', '30-hari', 'bulan-ini'] as $key)
            <li><a href="{{ $u(['periode' => $key, 'dari' => null, 'sampai' => null]) }}" data-us-nav class="bd-us__period {{ $f['periode'] === $key ? 'is-on' : '' }}" @if ($f['periode'] === $key) aria-current="true" @endif>{{ $periods[$key] }}</a></li>
        @endforeach
        <li><button type="button" class="bd-us__period {{ $f['periode'] === 'kustom' ? 'is-on' : '' }}" data-us-custom-toggle aria-expanded="{{ $f['periode'] === 'kustom' ? 'true' : 'false' }}" aria-controls="us-custom">{{ $periods['kustom'] }}</button></li>
    </ul>
    <p class="bd-us__span">{{ $span }} <span>· waktu WIB</span></p>
</nav>

<form method="GET" action="{{ route('admin.users.index') }}" class="bd-us__custom" id="us-custom" data-us-form @if ($f['periode'] !== 'kustom') hidden @endif>
    <input type="hidden" name="periode" value="kustom">
    @foreach (['q', 'peran', 'status', 'verifikasi', 'daftar', 'riset'] as $k)
        @if (filled($f[$k] ?? null))<input type="hidden" name="{{ $k }}" value="{{ $f[$k] }}">@endif
    @endforeach
    @if ($f['urut'] !== 'email')<input type="hidden" name="urut" value="{{ $f['urut'] }}">@endif
    <label class="bd-ix__field"><span class="bd-ix__label">Dari tanggal</span><input class="bd-ix__input" type="date" name="dari" value="{{ $f['dari'] }}" required></label>
    <label class="bd-ix__field"><span class="bd-ix__label">Sampai tanggal</span><input class="bd-ix__input" type="date" name="sampai" value="{{ $f['sampai'] }}" required></label>
    <button type="submit" class="bd-ix__btn bd-ix__btn--primary">Terapkan</button>
</form>
