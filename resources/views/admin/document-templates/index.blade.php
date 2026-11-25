@extends('layouts.admin')

{{-- HtmlString: judul lewat e() dua kali (section + layout), sehingga karakter khusus harus dilindungi. --}}
@section('title', new \Illuminate\Support\HtmlString('Template &amp; Format Cetak'))
@section('page_title', new \Illuminate\Support\HtmlString('Template &amp; Format Cetak'))

@php
    $fmt = fn ($n): string => number_format((int) $n, 0, ',', '.');
    $wib = fn ($date) => $date ? \Illuminate\Support\Carbon::parse($date)->timezone('Asia/Jakarta')->locale('id')->translatedFormat('j M Y, H.i') : null;
@endphp

@section('content')
{{-- Kerangka interaksi yang sama dengan Prompt Dokumen dan Perilaku Jawaban AI (data-dp-*); logika khusus format ada di admin-print-format.js. --}}
<section
    class="admin-page bd-ix bd-dp bd-pf"
    data-dp
    data-mode="{{ $mode }}"
    data-index-url="{{ route('admin.document-templates.index') }}"
>
    <div data-dp-swap="head" data-mode="{{ $mode }}">
        @if ($mode === 'editor')
            @include('admin.document-templates.partials.editor-head')
        @else
            <header class="bd-ix__head">
                <div>
                    @if ($mode === 'new')
                        <a href="{{ route('admin.document-templates.index') }}" class="bd-dp__back" data-dp-nav>@include('admin.tools.partials.icon', ['name' => 'arrow']) Template &amp; Format Cetak</a>
                    @endif
                    <h1>{{ $mode === 'new' ? 'Buat Template Format' : 'Template & Format Cetak' }}</h1>
                    <p>{{ $mode === 'new' ? 'Template baru selalu berstatus draf. Tidak dipakai untuk dokumen baru sampai Anda mengaktifkannya.' : 'Kelola tata letak, tipografi, dan standar penyusunan dokumen akademik untuk Word, PDF, dan naskah buku.' }}</p>
                </div>
                @if ($mode === 'list')
                    <div class="bd-dp__head-actions">
                        <a href="{{ route('admin.document-templates.index', ['baru' => 1]) }}" class="bd-ix__btn bd-ix__btn--primary" data-dp-nav>@include('admin.tools.partials.icon', ['name' => 'plus']) Buat Template Format</a>
                    </div>
                @endif
            </header>
        @endif
    </div>

    <div class="bd-ix__toast {{ session('success') ? 'is-ok' : '' }}" data-dp-toast role="status" aria-live="polite" @unless (session('success')) hidden @endunless>
        <p data-dp-toast-text>{{ session('success') }}</p>
        <button type="button" class="bd-ix__icon-btn" data-dp-toast-close aria-label="Tutup pesan">@include('admin.tools.partials.icon', ['name' => 'x'])</button>
    </div>

    <div data-dp-swap="stats">
        @if ($mode === 'list' && ($stats['total'] > 0 || $filtering))
            <dl class="bd-ix__stats" aria-label="Ringkasan template format">
                <div class="bd-ix__stat"><dt>Total template</dt><dd>{{ $fmt($stats['total']) }}</dd></div>
                <div class="bd-ix__stat"><dt>Aktif</dt><dd>{{ $fmt($stats['active']) }}</dd><dd class="bd-ix__stat-note">format cetak berversi dipakai generator</dd></div>
                <div class="bd-ix__stat"><dt>Draf</dt><dd>{{ $fmt($stats['draft']) }}</dd><dd class="bd-ix__stat-note">belum dipakai dokumen baru</dd></div>
                <div class="bd-ix__stat {{ $stats['legacy'] > 0 ? 'is-warn' : '' }}"><dt>Format lama</dt><dd>{{ $fmt($stats['legacy']) }}</dd><dd class="bd-ix__stat-note">belum diatur di editor; tetap berfungsi</dd></div>
            </dl>
        @endif
    </div>

    <div data-dp-swap="filters" data-mode="{{ $mode }}">
        @if ($mode === 'list' && ($stats['total'] > 0 || $filtering))
            <form method="GET" action="{{ route('admin.document-templates.index') }}" class="bd-ix__filters" role="search" data-dp-filters>
                <label class="bd-ix__search">
                    <span class="bd-ix__sr">Cari nama, kode, atau deskripsi template</span>
                    @include('admin.tools.partials.icon', ['name' => 'search'])
                    <input class="bd-ix__input" type="search" name="search" value="{{ request('search') }}" maxlength="160" placeholder="Cari nama atau kode…" autocomplete="off" data-dp-search>
                </label>
                <select class="bd-ix__select bd-ix__select--filter" name="jenis" aria-label="Filter jenis dokumen" data-dp-auto>
                    <option value="">Semua jenis</option>
                    @foreach ($types as $type)
                        <option value="{{ $type }}" @selected(request('jenis') === $type)>{{ $type }}</option>
                    @endforeach
                </select>
                <select class="bd-ix__select bd-ix__select--filter" name="status" aria-label="Filter status" data-dp-auto>
                    <option value="">Semua status</option>
                    @foreach ($statusFilters as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <select class="bd-ix__select bd-ix__select--filter" name="sort" aria-label="Urutkan" data-dp-auto>
                    @foreach (['updated' => 'Terbaru diperbarui', 'name' => 'Nama A–Z'] as $value => $label)
                        <option value="{{ $value }}" @selected(request('sort', 'updated') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <button type="submit" class="bd-ix__btn">Cari</button>
                <a href="{{ route('admin.document-templates.index') }}" class="bd-ix__btn bd-ix__btn--quiet" data-dp-reset @unless ($filtering) hidden @endunless>Atur ulang</a>
            </form>
        @endif
    </div>

    <div data-dp-swap="body" data-mode="{{ $mode }}">
        @switch($mode)
            @case('list')
                @include('admin.document-templates.partials.list')
                @break
            @case('new')
                @include('admin.document-templates.partials.new')
                @break
            @default
                @include('admin.document-templates.partials.editor')
        @endswitch
    </div>

    <dialog class="bd-ix__dialog bd-ix__dialog--small" data-dp-confirm aria-labelledby="dp-confirm-title">
        <div class="bd-ix__dialog-body">
            <h2 id="dp-confirm-title" data-dp-confirm-title>Konfirmasi</h2>
            <div class="bd-ix__confirm-text" data-dp-confirm-text></div>
            <div class="bd-ix__dialog-actions">
                <button type="button" class="bd-ix__btn" data-dp-confirm-cancel>Batal</button>
                <button type="button" class="bd-ix__btn bd-ix__btn--primary" data-dp-confirm-ok>Lanjutkan</button>
            </div>
        </div>
    </dialog>
</section>
@endsection
