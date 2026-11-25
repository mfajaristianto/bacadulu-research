@extends('layouts.admin')

@section('title', 'Keamanan')
@section('page_title', 'Keamanan')

@php
    // URL halaman ini dengan filter aktif dikurangi/ditambah $over. Halaman dan panel detail tidak ikut dibawa saat filter berubah.
    $u = function (array $over = []) use ($f): string {
        $q = array_replace([
            'periode' => $f['periode'], 'dari' => $f['dari'], 'sampai' => $f['sampai'], 'q' => $f['q'], 'jenis' => $f['jenis'], 'hasil' => $f['hasil'],
            'aktor' => $f['aktor'], 'urut' => $f['urut'], 'skala' => $f['skala'],
        ], $over);

        foreach (['periode' => '30-hari', 'urut' => 'terbaru', 'skala' => 'hari'] as $key => $default) {
            if (($q[$key] ?? null) === $default) {
                unset($q[$key]);
            }
        }

        return route('admin.security.index', array_filter($q, fn ($v) => $v !== null && $v !== ''));
    };
@endphp

@section('content')
{{-- Kerangka: region [data-sc-swap] diganti dari salinan URL yang sama (tanpa muat ulang dokumen). Semua tautan dan form juga bekerja tanpa JavaScript. --}}
<section class="admin-page bd-ix bd-sc is-entering" data-sc data-sc-url="{{ route('admin.security.index') }}">
    <div data-sc-swap="head">
        @include('admin.security.partials.head')
    </div>

    <div class="bd-sc__notice" data-sc-notice role="status" aria-live="polite" @if (! $notice) hidden @endif>{{ $notice }}</div>

    @if (! $ready)
        <div class="bd-ix__empty">
            <h2>Belum ada pencatatan</h2>
            <p>Tabel <code>audit_logs</code> belum ada di database ini, jadi belum ada event akses yang dapat ditampilkan. Ini berbeda dengan "0 kejadian".</p>
        </div>
    @else
        <div data-sc-swap="kpi">
            @include('admin.security.partials.kpi')
        </div>
        <div data-sc-swap="viz">
            @include('admin.security.partials.viz')
        </div>
        <div data-sc-swap="insights">
            @include('admin.security.partials.insights')
        </div>
        <div data-sc-swap="filters">
            @include('admin.security.partials.filters')
        </div>
        <div data-sc-swap="list" aria-live="polite">
            @include('admin.security.partials.timeline')
        </div>
    @endif

    <div data-sc-swap="controls">
        @include('admin.security.partials.controls')
    </div>

    <div class="bd-sc__error" data-sc-error role="alert" hidden>
        <p data-sc-error-text>Data tidak dapat dimuat.</p>
        <button type="button" class="bd-ix__btn" data-sc-retry>Coba lagi</button>
    </div>

    <dialog class="bd-sc__drawer" data-sc-drawer aria-labelledby="sc-drawer-title" @if ($detail) open @endif>
        <div class="bd-sc__drawer-head">
            <h2 id="sc-drawer-title">Detail event keamanan</h2>
            <button type="button" class="bd-ix__icon-btn" data-sc-close aria-label="Tutup detail">@include('admin.tools.partials.icon', ['name' => 'x'])</button>
        </div>
        <div class="bd-sc__drawer-body" data-sc-drawer-body tabindex="-1">
            @if ($detail)
                @include('admin.security.partials.detail', ['d' => $detail])
            @endif
        </div>
    </dialog>
</section>
@endsection
