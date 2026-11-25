@php
    $activeCount = collect(['peran', 'status', 'verifikasi', 'daftar', 'riset'])->filter(fn ($k) => filled($f[$k]))->count();
@endphp
<div class="bd-us__dir-head">
    <h2 class="bd-ix__side-title">Direktori Pengguna</h2>
    <p class="bd-us__sub">Cari, saring, dan buka detail akun. Direktori tidak ikut berubah saat periode analitik diganti, kecuali "Daftar dalam periode" dipilih.</p>
</div>

<form method="GET" action="{{ route('admin.users.index') }}" class="bd-ix__filters bd-us__filters {{ $activeCount > 0 ? 'has-active' : '' }}" role="search" data-us-form data-us-filters>
    @if ($f['periode'] !== '30-hari')<input type="hidden" name="periode" value="{{ $f['periode'] }}">@endif
    @if ($f['periode'] === 'kustom')<input type="hidden" name="dari" value="{{ $f['dari'] }}"><input type="hidden" name="sampai" value="{{ $f['sampai'] }}">@endif
    <label class="bd-ix__search">
        <span class="bd-ix__sr">Cari nama, email, institusi, atau ID pengguna</span>
        @include('admin.tools.partials.icon', ['name' => 'search'])
        <input class="bd-ix__input" type="search" name="q" value="{{ $f['q'] }}" maxlength="160" placeholder="Cari nama, email, institusi, atau #ID…" autocomplete="off" data-us-search>
    </label>
    <button type="button" class="bd-ix__btn bd-us__filter-toggle" data-us-filter-toggle aria-expanded="false" aria-controls="us-selects">Filter @if ($activeCount > 0) <span class="bd-us__badge">{{ $activeCount }}</span>@endif</button>
    <div class="bd-us__selects" id="us-selects">
        <select class="bd-ix__select bd-ix__select--filter" name="peran" aria-label="Filter peran" data-us-auto>
            <option value="">Semua peran</option>
            <option value="admin" @selected($f['peran'] === 'admin')>Administrator panel</option>
            <option value="pengguna" @selected($f['peran'] === 'pengguna')>Pengguna</option>
        </select>
        <select class="bd-ix__select bd-ix__select--filter" name="status" aria-label="Filter status akun" data-us-auto>
            <option value="">Semua status</option>
            <option value="active" @selected($f['status'] === 'active')>Aktif</option>
            <option value="suspended" @selected($f['status'] === 'suspended')>Ditangguhkan</option>
        </select>
        <select class="bd-ix__select bd-ix__select--filter" name="verifikasi" aria-label="Filter verifikasi email" data-us-auto>
            <option value="">Semua verifikasi</option>
            <option value="ya" @selected($f['verifikasi'] === 'ya')>Terverifikasi</option>
            <option value="belum" @selected($f['verifikasi'] === 'belum')>Belum terverifikasi</option>
        </select>
        <select class="bd-ix__select bd-ix__select--filter" name="daftar" aria-label="Filter periode pendaftaran" data-us-auto>
            <option value="">Daftar kapan saja</option>
            <option value="periode" @selected($f['daftar'] === 'periode')>Daftar dalam periode</option>
        </select>
        <select class="bd-ix__select bd-ix__select--filter" name="riset" aria-label="Filter riset" data-us-auto>
            <option value="">Semua (riset)</option>
            <option value="pernah" @selected($f['riset'] === 'pernah')>Pernah riset</option>
            <option value="belum" @selected($f['riset'] === 'belum')>Belum pernah riset</option>
        </select>
        <select class="bd-ix__select bd-ix__select--filter" name="urut" aria-label="Urutkan" data-us-auto>
            @foreach ($sorts as $key => $label)<option value="{{ $key }}" @selected($f['urut'] === $key)>{{ $label }}</option>@endforeach
        </select>
    </div>
    <button type="submit" class="bd-ix__btn">Cari</button>
    <span data-us-reset-slot>
        @if ($filtering || $f['urut'] !== 'email')
            <a href="{{ $u(['q' => null, 'peran' => null, 'status' => null, 'verifikasi' => null, 'daftar' => null, 'riset' => null, 'urut' => null]) }}" class="bd-ix__btn bd-ix__btn--quiet" data-us-nav>Atur ulang</a>
        @endif
    </span>
</form>
