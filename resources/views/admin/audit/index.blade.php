@extends('layouts.admin')

@section('title', 'Log Aktivitas')
@section('page_title', 'Log Aktivitas')

@php
    // URL halaman ini dengan filter aktif dikurangi/ditambah $over. Halaman dan detail tidak ikut dibawa saat filter berubah.
    $u = function (array $over = []) use ($f): string {
        $q = array_replace([
            'periode' => $f['periode'], 'dari' => $f['dari'], 'sampai' => $f['sampai'], 'q' => $f['q'], 'aktor' => $f['aktor'], 'tipe' => $f['tipe'],
            'aksi' => $f['aksi'], 'entitas' => $f['entitas'], 'kategori' => $f['kategori'], 'urut' => $f['urut'],
        ], $over);

        if (($q['periode'] ?? null) === '30-hari') {
            unset($q['periode']);
        }

        if (($q['urut'] ?? null) === 'terbaru') {
            unset($q['urut']);
        }

        return route('admin.audit.index', array_filter($q, fn ($v) => $v !== null && $v !== ''));
    };
@endphp

@section('content')
{{-- Kerangka: region [data-au-swap] diganti dari salinan URL yang sama (tanpa muat ulang dokumen). Semua tautan dan form juga bekerja tanpa JavaScript. --}}
<section class="admin-page bd-ix bd-au" data-au data-au-url="{{ route('admin.audit.index') }}">
    <div data-au-swap="head">
        @include('admin.audit.partials.head')
    </div>

    <div class="bd-au__notice" data-au-notice role="status" aria-live="polite" @if (! $notice) hidden @endif>{{ $notice }}</div>

    @if (! $ready)
        <div class="bd-ix__empty">
            <h2>Tabel audit belum tersedia</h2>
            <p>Tabel <code>audit_logs</code> belum ada di database ini, jadi belum ada jejak yang dapat ditampilkan.</p>
        </div>
    @else
        <div data-au-swap="stats">
            @include('admin.audit.partials.stats')
        </div>
        <div data-au-swap="insight">
            @include('admin.audit.partials.insight')
        </div>
        <div data-au-swap="filters">
            @include('admin.audit.partials.filters')
        </div>
        <div data-au-swap="list" aria-live="polite">
            @include('admin.audit.partials.list')
        </div>
    @endif

    <div class="bd-au__error" data-au-error role="alert" hidden>
        <p data-au-error-text>Data tidak dapat dimuat.</p>
        <button type="button" class="bd-ix__btn" data-au-retry>Coba lagi</button>
    </div>

    <dialog class="bd-au__drawer" data-au-drawer aria-labelledby="au-drawer-title" @if ($detail) open @endif>
        <div class="bd-au__drawer-head">
            <h2 id="au-drawer-title">Detail aktivitas</h2>
            <button type="button" class="bd-ix__icon-btn" data-au-close aria-label="Tutup detail">@include('admin.tools.partials.icon', ['name' => 'x'])</button>
        </div>
        <div class="bd-au__drawer-body" data-au-drawer-body tabindex="-1">
            @if ($detail)
                @include('admin.audit.partials.detail', ['d' => $detail])
            @endif
        </div>
    </dialog>
</section>
@endsection
