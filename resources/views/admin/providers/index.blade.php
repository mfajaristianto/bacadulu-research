@extends('layouts.admin')

{{-- HtmlString: judul melewati e() dua kali (section + layout), jadi "&" dilindungi agar tidak tampil sebagai &amp;. --}}
@section('title', new \Illuminate\Support\HtmlString('Provider & Analitik AI'))
@section('page_title', new \Illuminate\Support\HtmlString('Provider & Analitik AI'))

@php
    $int = fn (?int $n): string => $n === null ? '—' : number_format($n, 0, ',', '.');
    $usd = fn (?float $n): string => $n === null ? '—' : '$'.number_format($n, $n < 1 ? 4 : 2, '.', ',');
    $idr = fn (?float $n): string => $n === null ? '—' : 'Rp '.number_format($n, 0, ',', '.');
    $when = fn ($date) => $date ? \Illuminate\Support\Carbon::parse($date, config('app.timezone'))->setTimezone('Asia/Jakarta')->locale('id')->translatedFormat('j M Y, H.i') : null;
    $statusFilters = ['enabled' => 'Dipakai untuk riset', 'disabled' => 'Tidak dipakai', 'configured' => 'Terkonfigurasi', 'missing' => 'Belum dikonfigurasi'];
    $sortLabels = ['name' => 'Nama A–Z', 'usage' => 'Penggunaan terbanyak', 'cost' => 'Biaya tertinggi', 'recent' => 'Terakhir digunakan'];
@endphp

@section('content')
<section
    class="admin-page bd-pv"
    data-pv
    data-index-url="{{ route('admin.providers.index') }}"
    data-config-url="{{ route('admin.providers.config') }}"
    data-test-url="{{ route('admin.providers.test', '__ID__') }}"
    data-metric="{{ $metric }}"
>
    <header class="bd-pv__head">
        <div>
            <h1>Provider &amp; Analitik AI</h1>
            <p>Kelola koneksi model AI serta pantau penggunaan token dan biaya operasional.</p>
        </div>
    </header>

    <div class="bd-pv__toast {{ session('success') ? 'is-ok' : '' }}" data-pv-toast role="status" aria-live="polite" @unless (session('success')) hidden @endunless>
        <p data-pv-toast-text>{{ session('success') }}</p>
        <button type="button" class="bd-pv__icon-btn" data-pv-toast-close aria-label="Tutup pesan">@include('admin.tools.partials.icon', ['name' => 'x'])</button>
    </div>

    <div data-pv-swap="stats">
        @include('admin.providers.partials.stats')
    </div>

    <section class="bd-pv__section" aria-labelledby="pv-providers">
        <div class="bd-pv__section-head">
            <h2 id="pv-providers">Provider dan model</h2>
            <p>Hanya provider yang sudah terintegrasi di aplikasi. Status "Terhubung" berasal dari tes koneksi, bukan dari adanya kunci API.</p>
        </div>

        <form method="GET" action="{{ route('admin.providers.index') }}" class="bd-pv__filters" role="search" data-pv-filters>
            <label class="bd-pv__search">
                <span class="bd-pv__sr">Cari provider atau model</span>
                @include('admin.tools.partials.icon', ['name' => 'search'])
                <input class="bd-pv__input" type="search" name="search" value="{{ request('search') }}" maxlength="120" placeholder="Cari provider atau model…" autocomplete="off" data-pv-search>
            </label>
            <select class="bd-pv__select bd-pv__select--filter" name="status" aria-label="Filter status" data-pv-auto>
                <option value="">Semua status</option>
                @foreach ($statusFilters as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <select class="bd-pv__select bd-pv__select--filter" name="sort" aria-label="Urutkan" data-pv-auto>
                @foreach ($sortLabels as $value => $label)
                    <option value="{{ $value }}" @selected(request('sort', 'name') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            {{-- Periode ikut terbawa agar filter tabel tidak mengubah rentang angka. --}}
            @foreach (['periode', 'dari', 'sampai', 'metrik'] as $keep)
                @if (request()->filled($keep))<input type="hidden" name="{{ $keep }}" value="{{ request($keep) }}" data-pv-keep>@endif
            @endforeach
            <button type="submit" class="bd-pv__btn">Cari</button>
            <a href="{{ route('admin.providers.index', request()->only(['periode', 'dari', 'sampai', 'metrik'])) }}" class="bd-pv__btn bd-pv__btn--quiet" data-pv-reset @unless ($filtering) hidden @endunless>Atur ulang</a>
        </form>

        <div data-pv-swap="table">
            @include('admin.providers.partials.table')
        </div>
    </section>

    <div data-pv-swap="chart">
        @include('admin.providers.partials.chart')
    </div>

    <section class="bd-pv__section" id="konfigurasi" aria-labelledby="pv-config">
        <div class="bd-pv__section-head">
            <h2 id="pv-config">Konfigurasi tugas AI</h2>
            <p>Pilih provider dan model untuk tugas yang memang memanggil AI. Perubahan berlaku untuk riset baru; riset yang sudah berjalan tidak berubah.</p>
        </div>
        <div data-pv-swap="config">
            @include('admin.providers.partials.config')
        </div>
    </section>

    @include('admin.providers.partials.synthesis')

    <div data-pv-swap="drawer">
        @include('admin.providers.partials.drawer')
    </div>

    <dialog class="bd-pv__dialog bd-pv__dialog--small" data-pv-confirm aria-labelledby="pv-confirm-title">
        <div class="bd-pv__dialog-body">
            <h2 id="pv-confirm-title" data-pv-confirm-title>Konfirmasi</h2>
            <div class="bd-pv__confirm-text" data-pv-confirm-text></div>
            <div class="bd-pv__dialog-actions">
                <button type="button" class="bd-pv__btn" data-pv-confirm-cancel>Batal</button>
                <button type="button" class="bd-pv__btn bd-pv__btn--primary" data-pv-confirm-ok>Lanjutkan</button>
            </div>
        </div>
    </dialog>
</section>
@endsection
