@extends('layouts.admin')

{{-- HtmlString: judul lewat e() dua kali (section + layout), sehingga karakter khusus harus dilindungi. --}}
@section('title', new \Illuminate\Support\HtmlString('Perilaku Jawaban AI'))
@section('page_title', new \Illuminate\Support\HtmlString('Perilaku Jawaban AI'))

@php
    $fmt = fn ($n): string => number_format((int) $n, 0, ',', '.');
    $wib = fn ($date) => $date ? \Illuminate\Support\Carbon::parse($date)->timezone('Asia/Jakarta')->locale('id')->translatedFormat('j M Y, H.i') : null;
@endphp

@section('content')
{{-- Memakai kerangka interaksi yang sama dengan Prompt Dokumen (data-dp-*): swap region, riwayat, form JSON, penjaga perubahan. --}}
<section
    class="admin-page bd-ix bd-dp bd-ab"
    data-dp
    data-mode="{{ $mode }}"
    data-index-url="{{ route('admin.answer-behavior.index') }}"
>
    <div data-dp-swap="head" data-mode="{{ $mode }}">
        @if ($mode === 'editor')
            @include('admin.answer-behavior.partials.editor-head')
        @else
            <header class="bd-ix__head">
                <div>
                    @if ($mode === 'new')
                        <a href="{{ route('admin.answer-behavior.index') }}" class="bd-dp__back" data-dp-nav>@include('admin.tools.partials.icon', ['name' => 'arrow']) Perilaku Jawaban AI</a>
                    @endif
                    <h1>{{ $mode === 'new' ? 'Buat Aturan Jawaban' : 'Perilaku Jawaban AI' }}</h1>
                    <p>{{ $mode === 'new' ? 'Aturan baru selalu berstatus draf. Tidak ada yang berlaku untuk pengguna sampai Anda mengaktifkannya.' : 'Atur bagaimana AI menyusun jawaban, menggunakan referensi, menangani keterbatasan, dan menyarankan langkah penelitian berikutnya.' }}</p>
                </div>
                @if ($mode === 'list')
                    <div class="bd-dp__head-actions">
                        <a href="{{ route('admin.answer-behavior.index', ['baru' => 1]) }}" class="bd-ix__btn bd-ix__btn--primary" data-dp-nav>@include('admin.tools.partials.icon', ['name' => 'plus']) Buat Aturan Jawaban</a>
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
            <dl class="bd-ix__stats" aria-label="Ringkasan aturan">
                <div class="bd-ix__stat"><dt>Total aturan</dt><dd>{{ $fmt($stats['total']) }}</dd></div>
                <div class="bd-ix__stat"><dt>Aturan aktif</dt><dd>{{ $fmt($stats['active']) }}</dd></div>
                <div class="bd-ix__stat"><dt>Aturan draf</dt><dd>{{ $fmt($stats['draft']) }}</dd><dd class="bd-ix__stat-note">belum punya versi aktif</dd></div>
                <div class="bd-ix__stat {{ $stats['review'] > 0 ? 'is-warn' : '' }}"><dt>Perlu ditinjau</dt><dd>{{ $fmt($stats['review']) }}</dd><dd class="bd-ix__stat-note">draf menunggu keputusan, atau alat tidak aktif</dd></div>
            </dl>
        @endif
    </div>

    <div data-dp-swap="filters" data-mode="{{ $mode }}">
        @if ($mode === 'list' && ($stats['total'] > 0 || $filtering))
            <form method="GET" action="{{ route('admin.answer-behavior.index') }}" class="bd-ix__filters" role="search" data-dp-filters>
                <input type="hidden" name="cakupan" value="{{ request('cakupan') }}">
                <label class="bd-ix__search">
                    <span class="bd-ix__sr">Cari nama atau deskripsi aturan</span>
                    @include('admin.tools.partials.icon', ['name' => 'search'])
                    <input class="bd-ix__input" type="search" name="search" value="{{ request('search') }}" maxlength="160" placeholder="Cari nama atau deskripsi…" autocomplete="off" data-dp-search>
                </label>
                <select class="bd-ix__select bd-ix__select--filter" name="kelompok" aria-label="Filter kelompok perilaku" data-dp-auto>
                    <option value="">Semua kelompok</option>
                    @foreach ($groups as $value => $g)
                        <option value="{{ $value }}" @selected(request('kelompok') === $value)>{{ $g['label'] }}</option>
                    @endforeach
                </select>
                <select class="bd-ix__select bd-ix__select--filter" name="alat" aria-label="Filter alat riset" data-dp-auto>
                    <option value="">Semua alat</option>
                    @foreach ($tools as $code => $name)
                        <option value="{{ $code }}" @selected(request('alat') === $code)>{{ $name }}</option>
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
                <a href="{{ route('admin.answer-behavior.index') }}" class="bd-ix__btn bd-ix__btn--quiet" data-dp-reset @unless ($filtering) hidden @endunless>Atur ulang</a>
            </form>
        @endif
    </div>

    <div data-dp-swap="body" data-mode="{{ $mode }}">
        @switch($mode)
            @case('list')
                @include('admin.answer-behavior.partials.list')
                @break
            @case('new')
                @include('admin.answer-behavior.partials.new')
                @break
            @default
                @include('admin.answer-behavior.partials.editor')
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
