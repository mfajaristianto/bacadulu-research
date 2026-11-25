@extends('layouts.admin')

{{-- HtmlString: judul ini lewat e() dua kali (section + layout), jadi "&" harus dilindungi agar tidak tampil sebagai &amp;. --}}
@section('title', new \Illuminate\Support\HtmlString('Instruksi & Versi'))
@section('page_title', new \Illuminate\Support\HtmlString('Instruksi & Versi'))

@php
    $fmt = fn (int $n): string => number_format($n, 0, ',', '.');
    $wib = fn ($date) => $date?->copy()->timezone('Asia/Jakarta')->locale('id')->translatedFormat('j F Y, H.i');
    $sortLabels = ['updated' => 'Terbaru diperbarui', 'name' => 'Nama A–Z'];
    $filterLabels = ['active' => 'Punya versi aktif', 'draft' => 'Punya draf', 'archived' => 'Hanya arsip'];
@endphp

@section('content')
<section
    class="admin-page bd-ix"
    data-ix
    data-mode="{{ $mode }}"
    data-index-url="{{ route('admin.prompts.index') }}"
    data-store-url="{{ route('admin.prompts.store') }}"
>
    <header class="bd-ix__head">
        <div>
            <h1>Instruksi &amp; Versi</h1>
            <p>Kelola instruksi AI, riwayat perubahan, dan versi yang digunakan oleh alat riset.</p>
        </div>
        <a href="{{ route('admin.prompts.index', array_filter(['baru' => 1, 'tool' => request('tool')])) }}" class="bd-ix__btn bd-ix__btn--primary" data-ix-nav data-ix-new>@include('admin.tools.partials.icon', ['name' => 'plus']) Buat Instruksi</a>
    </header>

    <div class="bd-ix__toast {{ session('success') ? 'is-ok' : '' }}" data-ix-toast role="status" aria-live="polite" @unless (session('success')) hidden @endunless>
        <p data-ix-toast-text>{{ session('success') }}</p>
        <button type="button" class="bd-ix__icon-btn" data-ix-toast-close aria-label="Tutup pesan">@include('admin.tools.partials.icon', ['name' => 'x'])</button>
    </div>

    <div data-ix-swap="stats">
        @if ($mode === 'list')
            <dl class="bd-ix__stats" aria-label="Ringkasan instruksi">
                <div class="bd-ix__stat"><dt>Total instruksi</dt><dd>{{ $fmt($stats['total']) }}</dd></div>
                <div class="bd-ix__stat"><dt>Instruksi aktif</dt><dd>{{ $fmt($stats['active']) }}</dd></div>
                <div class="bd-ix__stat"><dt>Punya draf</dt><dd>{{ $fmt($stats['draft']) }}</dd></div>
                <div class="bd-ix__stat {{ $stats['review'] > 0 ? 'is-warn' : '' }}"><dt>Perlu ditinjau</dt><dd>{{ $fmt($stats['review']) }}</dd><dd class="bd-ix__stat-note">alat aktif tanpa versi aktif</dd></div>
            </dl>
        @endif
    </div>

    <div data-ix-swap="filters" data-mode="{{ $mode }}">
        @if ($mode === 'list')
            <form method="GET" action="{{ route('admin.prompts.index') }}" class="bd-ix__filters" role="search" data-ix-filters>
                <label class="bd-ix__search">
                    <span class="bd-ix__sr">Cari nama instruksi, alat, atau kode alat</span>
                    @include('admin.tools.partials.icon', ['name' => 'search'])
                    <input class="bd-ix__input" type="search" name="search" value="{{ request('search') }}" maxlength="160" placeholder="Cari nama instruksi, alat, atau kode…" autocomplete="off" data-ix-search>
                </label>
                <select class="bd-ix__select bd-ix__select--filter" name="status" aria-label="Filter status" data-ix-auto>
                    <option value="">Semua status</option>
                    @foreach ($filterLabels as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <select class="bd-ix__select bd-ix__select--filter" name="tool" aria-label="Filter alat" data-ix-auto>
                    <option value="">Semua alat</option>
                    @foreach ($tools as $tool)
                        <option value="{{ $tool->id }}" @selected((string) request('tool') === (string) $tool->id)>{{ $tool->name }}</option>
                    @endforeach
                </select>
                <select class="bd-ix__select bd-ix__select--filter" name="sort" aria-label="Urutkan" data-ix-auto>
                    @foreach ($sortLabels as $value => $label)
                        <option value="{{ $value }}" @selected(request('sort', 'updated') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <button type="submit" class="bd-ix__btn">Cari</button>
                <a href="{{ route('admin.prompts.index') }}" class="bd-ix__btn bd-ix__btn--quiet" data-ix-reset @unless ($filtering) hidden @endunless>Atur ulang</a>
            </form>
        @endif
    </div>

    <div data-ix-swap="body" data-mode="{{ $mode }}">
        @if ($mode === 'list')
            @include('admin.prompts.partials.list')
        @elseif ($mode === 'detail')
            @include('admin.prompts.partials.detail')
        @else
            @include('admin.prompts.partials.new')
        @endif
    </div>

    <dialog class="bd-ix__dialog bd-ix__dialog--small" data-ix-confirm aria-labelledby="ix-confirm-title">
        <div class="bd-ix__dialog-body">
            <h2 id="ix-confirm-title" data-ix-confirm-title>Konfirmasi</h2>
            <div class="bd-ix__confirm-text" data-ix-confirm-text></div>
            <div class="bd-ix__dialog-actions">
                <button type="button" class="bd-ix__btn" data-ix-confirm-cancel>Batal</button>
                <button type="button" class="bd-ix__btn bd-ix__btn--primary" data-ix-confirm-ok>Lanjutkan</button>
            </div>
        </div>
    </dialog>
</section>
@endsection
