@php
    $activeCount = collect(['jenis', 'hasil', 'aktor'])->filter(fn ($k) => filled($f[$k]))->count();
@endphp
<div class="bd-sc__tl-head">
    <h2 class="bd-ix__side-title">Linimasa Peristiwa Akses</h2>
    <p class="bd-sc__sub">Satu daftar untuk linimasa dan log akses. Hanya event akses; seluruh audit ada di Log Aktivitas.</p>
</div>

<form method="GET" action="{{ route('admin.security.index') }}" class="bd-ix__filters bd-sc__filters" role="search" data-sc-form data-sc-filters>
    @if ($f['periode'] !== '30-hari')<input type="hidden" name="periode" value="{{ $f['periode'] }}">@endif
    @if ($f['periode'] === 'kustom')<input type="hidden" name="dari" value="{{ $f['dari'] }}"><input type="hidden" name="sampai" value="{{ $f['sampai'] }}">@endif
    @if ($f['skala'] !== 'hari')<input type="hidden" name="skala" value="{{ $f['skala'] }}">@endif
    <label class="bd-ix__search">
        <span class="bd-ix__sr">Cari jenis event, pelaku, email yang dicoba, alamat IP, atau ID event</span>
        @include('admin.tools.partials.icon', ['name' => 'search'])
        <input class="bd-ix__input" type="search" name="q" value="{{ $f['q'] }}" maxlength="160" placeholder="Cari jenis event, pelaku, email, IP, atau #ID…" autocomplete="off" data-sc-search>
    </label>
    <button type="button" class="bd-ix__btn bd-sc__filter-toggle" data-sc-filter-toggle aria-expanded="false" aria-controls="sc-selects">Filter @if ($activeCount > 0) <span class="bd-sc__badge">{{ $activeCount }}</span>@endif</button>
    <div class="bd-sc__selects" id="sc-selects">
        <select class="bd-ix__select bd-ix__select--filter" name="jenis" aria-label="Filter jenis event" data-sc-auto>
            <option value="">Semua jenis</option>
            @foreach ($events as $code => $e)<option value="{{ $code }}" @selected($f['jenis'] === $code)>{{ $e[0] }}</option>@endforeach
        </select>
        <select class="bd-ix__select bd-ix__select--filter" name="hasil" aria-label="Filter hasil" data-sc-auto>
            <option value="">Semua hasil</option>
            @foreach ($results as $key => $label)<option value="{{ $key }}" @selected($f['hasil'] === $key)>{{ $label }}</option>@endforeach
        </select>
        <select class="bd-ix__select bd-ix__select--filter" name="aktor" aria-label="Filter pelaku" data-sc-auto>
            <option value="">Semua pelaku</option>
            <option value="diketahui" @selected($f['aktor'] === 'diketahui')>Pelaku diketahui</option>
            <option value="tidak-diketahui" @selected($f['aktor'] === 'tidak-diketahui')>Pelaku tidak teridentifikasi</option>
        </select>
        <select class="bd-ix__select bd-ix__select--filter" name="urut" aria-label="Urutkan" data-sc-auto>
            @foreach ($sorts as $key => $label)<option value="{{ $key }}" @selected($f['urut'] === $key)>{{ $label }}</option>@endforeach
        </select>
    </div>
    <button type="submit" class="bd-ix__btn">Cari</button>
    <span data-sc-reset-slot>
        @if ($filtering || $f['urut'] !== 'terbaru')
            <a href="{{ $u(['q' => null, 'jenis' => null, 'hasil' => null, 'aktor' => null, 'urut' => null]) }}" class="bd-ix__btn bd-ix__btn--quiet" data-sc-nav>Atur ulang</a>
        @endif
    </span>
</form>
