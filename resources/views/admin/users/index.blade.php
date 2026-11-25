@extends('layouts.admin')

@section('title', 'Pengguna')
@section('page_title', 'Pengguna')

@php
    // URL halaman ini dengan filter aktif dikurangi/ditambah $over. Halaman dan panel detail tidak ikut dibawa saat filter berubah.
    $u = function (array $over = []) use ($f): string {
        $q = array_replace([
            'periode' => $f['periode'], 'dari' => $f['dari'], 'sampai' => $f['sampai'], 'q' => $f['q'], 'peran' => $f['peran'], 'status' => $f['status'],
            'verifikasi' => $f['verifikasi'], 'daftar' => $f['daftar'], 'riset' => $f['riset'], 'urut' => $f['urut'],
        ], $over);

        if (($q['periode'] ?? null) === '30-hari') {
            unset($q['periode']);
        }

        if (($q['urut'] ?? null) === 'email') {
            unset($q['urut']);
        }

        return route('admin.users.index', array_filter($q, fn ($v) => $v !== null && $v !== ''));
    };
@endphp

@section('content')
{{-- Kerangka: region [data-us-swap] diganti dari salinan URL yang sama (tanpa muat ulang dokumen). Semua tautan dan form juga bekerja tanpa JavaScript. --}}
<section class="admin-page bd-ix bd-us is-entering" data-us data-us-url="{{ route('admin.users.index') }}">
    <div data-us-swap="head">
        @include('admin.users.partials.head')
    </div>

    <div class="bd-us__notice" data-us-notice role="status" aria-live="polite" @if (! $notice) hidden @endif>{{ $notice }}</div>

    @if (session('success'))
        <div class="bd-ix__toast is-ok" role="status"><p>{{ session('success') }}</p></div>
    @endif
    @if ($errors->has('status'))
        <div class="bd-ix__toast is-bad" role="alert"><p>{{ $errors->first('status') }}</p></div>
    @endif

    <div data-us-swap="kpi">
        @include('admin.users.partials.kpi')
    </div>
    <div data-us-swap="viz">
        @include('admin.users.partials.viz')
    </div>
    <div data-us-swap="activity">
        @include('admin.users.partials.activity')
    </div>
    <div data-us-swap="filters">
        @include('admin.users.partials.filters')
    </div>
    <div data-us-swap="list" aria-live="polite">
        @include('admin.users.partials.list')
    </div>

    <div class="bd-us__error" data-us-error role="alert" hidden>
        <p data-us-error-text>Data tidak dapat dimuat.</p>
        <button type="button" class="bd-ix__btn" data-us-retry>Coba lagi</button>
    </div>

    {{-- Toast melayang (tidak menggeser tata letak) untuk hasil aksi akun. --}}
    <div class="bd-us__toast" data-us-toast role="status" aria-live="polite" hidden></div>

    <dialog class="bd-us__drawer" data-us-drawer aria-labelledby="us-drawer-title" @if ($detail) open @endif>
        <div class="bd-us__drawer-head">
            <h2 id="us-drawer-title">Detail pengguna</h2>
            <button type="button" class="bd-ix__icon-btn" data-us-close aria-label="Tutup detail">@include('admin.tools.partials.icon', ['name' => 'x'])</button>
        </div>
        <div class="bd-us__drawer-body" data-us-drawer-body tabindex="-1">
            @if ($detail)
                @include('admin.users.partials.detail', ['d' => $detail])
            @endif
        </div>
    </dialog>
</section>
@endsection
