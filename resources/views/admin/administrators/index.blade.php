@extends('layouts.admin')

@section('title', 'Admin & Keamanan')
@section('page_title', 'Admin & Keamanan')

@php
    $wib = fn ($t) => $t ? \Carbon\CarbonImmutable::parse($t, 'UTC')->setTimezone('Asia/Jakarta')->locale('id')->translatedFormat('j M Y, H.i').' WIB' : '—';
@endphp

@section('content')
<section class="admin-page bd-ix bd-us" aria-labelledby="adm-title">
    <header class="bd-ix__head">
        <div>
            <h1 id="adm-title">Admin &amp; Keamanan</h1>
            <p>Kelola siapa yang dapat masuk ke panel admin. Akun pengguna biasa dikelola di menu Pengguna.</p>
        </div>
        <a class="bd-ix__btn" href="{{ route('admin.security.index') }}">Log keamanan akses →</a>
    </header>

    @unless ($supported)
        <div class="bd-admin__flash bd-admin__flash--warning" role="status"><span></span><p>Akun administrator tambahan belum aktif: migration database belum dijalankan. Hanya Super Admin dari konfigurasi server yang berlaku.</p></div>
    @endunless

    <div class="bd-us__block">
        <h2 class="bd-us__h">Daftar administrator</h2>
        <div class="bd-us__tablewrap">
            <table class="bd-us__dtable" aria-label="Daftar administrator">
                <thead>
                    <tr>
                        <th scope="col">Nama</th>
                        <th scope="col">Email</th>
                        <th scope="col">Role</th>
                        <th scope="col">Terakhir login</th>
                        <th scope="col">Status</th>
                        @if ($isSuper)<th scope="col"><span class="bd-us__sr">Aksi</span></th>@endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($admins as $a)
                        @php
                            $isConfig = \App\Services\Security\AdminAccess::isConfigAdmin($a);
                            $role = \App\Services\Security\AdminAccess::roleFor($a);
                            $isMe = (int) $meId === (int) $a->id;
                        @endphp
                        <tr>
                            <th scope="row">{{ $a->name }}@if ($isMe) <em class="bd-us__tag">Anda</em>@endif</th>
                            <td>{{ $a->email }}</td>
                            <td>{{ $roles[$role] ?? '—' }}@if ($isConfig) <small>(konfigurasi server)</small>@endif</td>
                            <td>{{ $wib($lastPanelLogin[$a->id] ?? $a->last_login_at) }}</td>
                            <td><span class="bd-us__state bd-us__state--{{ $a->status === 'active' ? 'active' : 'suspended' }}">{{ $a->status === 'active' ? 'Aktif' : 'Nonaktif' }}</span></td>
                            @if ($isSuper)
                                <td class="bd-us__cell-actions">
                                    @unless ($isConfig)
                                        <form method="POST" action="{{ route('admin.administrators.update', $a) }}" class="bd-us__inline-action">
                                            @csrf @method('PATCH')
                                            <label class="bd-us__sr" for="role-{{ $a->id }}">Role {{ $a->name }}</label>
                                            <select class="bd-ix__select" id="role-{{ $a->id }}" name="role">
                                                @foreach ($roles as $k => $label)<option value="{{ $k }}" @selected($role === $k)>{{ $label }}</option>@endforeach
                                            </select>
                                            <button type="submit" class="bd-ix__btn bd-ix__btn--small">Simpan</button>
                                        </form>
                                        @unless ($isMe)
                                            <form method="POST" action="{{ route('admin.administrators.destroy', $a) }}" class="bd-us__inline-action" onsubmit="return confirm('Cabut akses administrator {{ e($a->email) }}? Akun pengguna tetap ada.')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="bd-ix__btn bd-ix__btn--small bd-ix__btn--danger">Cabut akses</button>
                                            </form>
                                        @endunless
                                    @endunless
                                </td>
                            @endif
                        </tr>
                    @endforeach
                    @if (! $configHasAccount && $configEmail !== '')
                        <tr>
                            <th scope="row">Administrator utama</th>
                            <td>{{ $configEmail }}</td>
                            <td>Super Admin <small>(konfigurasi server)</small></td>
                            <td>—</td>
                            <td><span class="bd-us__state bd-us__state--active">Aktif</span></td>
                            @if ($isSuper)<td></td>@endif
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>

    @if ($isSuper && $supported)
        <form method="POST" action="{{ route('admin.administrators.store') }}" class="bd-us__block" autocomplete="off">
            @csrf
            <h2 class="bd-us__h">+ Tambahkan Akun Admin</h2>
            <p class="bd-ix__hint">Jika email sudah terdaftar sebagai pengguna, akun itu dinaikkan menjadi admin dan memakai kata sandinya sendiri. Jika belum, isi nama dan kata sandi (minimal 12 karakter) untuk membuat akun baru.</p>
            <div class="bd-us__formgrid">
                <label class="bd-ix__field"><span class="bd-ix__label">Email</span><input class="bd-ix__input" type="email" name="email" value="{{ old('email') }}" required></label>
                <label class="bd-ix__field"><span class="bd-ix__label">Role</span>
                    <select class="bd-ix__select" name="role">@foreach ($roles as $k => $label)<option value="{{ $k }}" @selected(old('role', 'admin') === $k)>{{ $label }}</option>@endforeach</select>
                </label>
                <label class="bd-ix__field"><span class="bd-ix__label">Nama (akun baru)</span><input class="bd-ix__input" type="text" name="name" value="{{ old('name') }}" maxlength="120"></label>
                <label class="bd-ix__field"><span class="bd-ix__label">Kata sandi (akun baru)</span><input class="bd-ix__input" type="password" name="password" minlength="12" autocomplete="new-password"></label>
                <label class="bd-ix__field"><span class="bd-ix__label">Ulangi kata sandi</span><input class="bd-ix__input" type="password" name="password_confirmation" minlength="12" autocomplete="new-password"></label>
            </div>
            <button type="submit" class="bd-ix__btn bd-ix__btn--primary">Tambahkan admin</button>
        </form>
    @elseif (! $isSuper)
        <p class="bd-ix__hint">Hanya Super Admin yang dapat menambahkan, mengubah peran, atau mencabut administrator.</p>
    @endif
</section>
@endsection
