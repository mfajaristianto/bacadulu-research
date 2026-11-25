@extends('layouts.admin')

@php $account = $mode === 'account'; @endphp
@section('title', $account ? 'Hapus pengguna' : 'Hapus riset pengguna')
@section('page_title', 'Pengguna')

@section('content')
<section class="admin-page bd-ix bd-us" aria-labelledby="del-title">
    <header class="bd-ix__head">
        <div>
            <h1 id="del-title">{{ $account ? 'Hapus pengguna permanen' : 'Hapus riset pengguna' }}</h1>
            <p>{{ $target->name }} · {{ $target->email }}</p>
        </div>
    </header>

    @if ($locked)
        <div class="bd-admin__flash bd-admin__flash--error" role="alert"><span></span><p>Akun administrator atau akun yang sedang dipakai masuk tidak dapat dihapus dari halaman ini.</p></div>
    @endif

    <div class="bd-us__block">
        <h2 class="bd-us__h">Data terkait yang akan terdampak</h2>
        <dl class="bd-us__mini-stats">
            <div><dt>Riset</dt><dd>{{ $counts['runs'] }}</dd></div>
            <div><dt>Percakapan</dt><dd>{{ $counts['conversations'] }}</dd></div>
            <div><dt>Hasil tersimpan</dt><dd>{{ $counts['saved'] }}</dd></div>
            <div><dt>Dokumen</dt><dd>{{ $counts['documents'] }}</dd></div>
            @if ($account)<div><dt>Riwayat login</dt><dd>{{ $counts['logins'] }}</dd></div>@endif
        </dl>
        @if ($account)
            <p>Menghapus akun <strong>menghapus permanen</strong> seluruh data di atas beserta berkas dokumennya. Tindakan ini tidak dapat dibatalkan. Untuk menghentikan akses tanpa kehilangan data, <strong>nonaktifkan</strong> akun saja.</p>
        @else
            <p>Hanya riset, percakapan, dan hasil tersimpan yang dihapus. <strong>Dokumen ({{ $counts['documents'] }}) dan akun tidak diubah.</strong></p>
        @endif
    </div>

    @unless ($locked)
        <form method="POST" action="{{ $account ? route('admin.users.destroy', $target) : route('admin.users.research.destroy', $target) }}" class="bd-us__block">
            @csrf @method('DELETE')
            <label class="bd-ix__field">
                <span class="bd-ix__label">Ketik email pengguna untuk mengonfirmasi</span>
                <input class="bd-ix__input" type="email" name="confirm_email" autocomplete="off" required placeholder="{{ $target->email }}">
            </label>
            <div class="bd-us__confirm-actions">
                <button type="submit" class="bd-ix__btn bd-ix__btn--danger">{{ $account ? 'Hapus pengguna permanen' : 'Hapus riset' }}</button>
                <a class="bd-ix__btn bd-ix__btn--quiet" href="{{ route('admin.users.index', ['pengguna' => $target->id]) }}">Batal</a>
            </div>
        </form>
    @else
        <a class="bd-ix__btn" href="{{ route('admin.users.index') }}">Kembali ke Pengguna</a>
    @endunless
</section>
@endsection
