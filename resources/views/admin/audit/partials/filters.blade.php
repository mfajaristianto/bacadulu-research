<nav class="bd-au__chips" aria-label="Filter cepat">
    <ul>
        @foreach ($chips as $chip)
            <li><a href="{{ $u($chip['query']) }}" data-au-nav class="bd-au__chip {{ $chip['active'] ? 'is-on' : '' }}" @if ($chip['active']) aria-current="true" @endif>{{ $chip['label'] }}@if ($chip['count'] !== null) <span>{{ number_format($chip['count'], 0, ',', '.') }}</span>@endif</a></li>
        @endforeach
    </ul>
</nav>

<form method="GET" action="{{ route('admin.audit.index') }}" class="bd-ix__filters bd-au__filters" role="search" data-au-form data-au-filters>
    @if ($f['periode'] !== '30-hari')<input type="hidden" name="periode" value="{{ $f['periode'] }}">@endif
    @if ($f['periode'] === 'kustom')<input type="hidden" name="dari" value="{{ $f['dari'] }}"><input type="hidden" name="sampai" value="{{ $f['sampai'] }}">@endif
    @if ($f['kategori'])<input type="hidden" name="kategori" value="{{ $f['kategori'] }}">@endif
    <label class="bd-ix__search">
        <span class="bd-ix__sr">Cari aktor, aksi, nama objek, ID objek, atau ID event</span>
        @include('admin.tools.partials.icon', ['name' => 'search'])
        <input class="bd-ix__input" type="search" name="q" value="{{ $f['q'] }}" maxlength="160" placeholder="Cari aktor, aksi, objek, atau #ID…" autocomplete="off" data-au-search>
    </label>
    <select class="bd-ix__select bd-ix__select--filter" name="aktor" aria-label="Filter aktor" data-au-auto>
        <option value="">Semua aktor</option>
        @foreach ($options['actors'] as $id => $name)<option value="{{ $id }}" @selected((int) $f['aktor'] === (int) $id)>{{ $name }}</option>@endforeach
    </select>
    <select class="bd-ix__select bd-ix__select--filter" name="tipe" aria-label="Filter tipe aktor" data-au-auto>
        <option value="">Semua tipe aktor</option>
        @foreach (\App\Services\Audit\AuditEvents::ACTOR_KINDS as $key => $label)<option value="{{ $key }}" @selected($f['tipe'] === $key)>{{ $label }}</option>@endforeach
    </select>
    <select class="bd-ix__select bd-ix__select--filter" name="aksi" aria-label="Filter aksi" data-au-auto>
        <option value="">Semua aksi</option>
        @foreach ($options['actions'] as $code => $label)<option value="{{ $code }}" @selected($f['aksi'] === $code)>{{ $label }}</option>@endforeach
    </select>
    <select class="bd-ix__select bd-ix__select--filter" name="entitas" aria-label="Filter jenis objek" data-au-auto>
        <option value="">Semua objek</option>
        @foreach ($options['entities'] as $key => $label)<option value="{{ $key }}" @selected($f['entitas'] === $key)>{{ $label }}</option>@endforeach
    </select>
    <select class="bd-ix__select bd-ix__select--filter" name="urut" aria-label="Urutkan" data-au-auto>
        @foreach ($sorts as $key => $label)<option value="{{ $key }}" @selected($f['urut'] === $key)>{{ $label }}</option>@endforeach
    </select>
    <button type="submit" class="bd-ix__btn">Cari</button>
    <span data-au-reset-slot>
        @if ($filtering || $f['urut'] !== 'terbaru')
            <a href="{{ $u(['q' => null, 'aktor' => null, 'tipe' => null, 'aksi' => null, 'entitas' => null, 'kategori' => null, 'urut' => null]) }}" class="bd-ix__btn bd-ix__btn--quiet" data-au-nav>Atur ulang</a>
        @endif
    </span>
</form>
