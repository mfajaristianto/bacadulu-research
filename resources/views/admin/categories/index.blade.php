@extends('layouts.admin')

@section('title', 'Kategori Riset')
@section('page_title', 'Kategori Riset')

@php
    $fmt = fn (int $n): string => number_format($n, 0, ',', '.');
@endphp

@section('content')
<section
    class="admin-page bd-cat"
    data-cat
    data-index-url="{{ route('admin.categories.index') }}"
    data-store-url="{{ route('admin.categories.store') }}"
    data-update-url="{{ route('admin.categories.update', '__ID__') }}"
    data-check-url="{{ route('admin.categories.check') }}"
>
    <header class="bd-cat__head">
        <div>
            <h1>Kategori Riset</h1>
            <p>Kelola pengelompokan sumber akademik agar pustaka lebih terstruktur.</p>
        </div>
        <a href="{{ route('admin.categories.create') }}" class="bd-cat__btn bd-cat__btn--primary" data-cat-new>@include('admin.categories.partials.icon', ['name' => 'plus']) Tambah Kategori</a>
    </header>

    <dl class="bd-cat__stats" aria-label="Ringkasan kategori" data-cat-swap="stats">
        <div class="bd-cat__stat"><dt>Total kategori</dt><dd>{{ $fmt($stats['total']) }}</dd></div>
        <div class="bd-cat__stat"><dt>Aktif</dt><dd>{{ $fmt($stats['active']) }}</dd></div>
        <div class="bd-cat__stat"><dt>Subkategori</dt><dd>{{ $fmt($stats['sub']) }}</dd></div>
        <div class="bd-cat__stat"><dt>Memiliki sumber</dt><dd>{{ $fmt($stats['used']) }}</dd></div>
    </dl>

    <div class="bd-cat__toast" data-cat-toast role="status" aria-live="polite" hidden>
        <p data-cat-toast-text></p>
        <button type="button" class="bd-cat__icon-btn" data-cat-toast-close aria-label="Tutup pesan">@include('admin.categories.partials.icon', ['name' => 'x'])</button>
    </div>

    <form method="GET" action="{{ route('admin.categories.index') }}" class="bd-cat__filters" role="search" data-cat-filters>
        <label class="bd-cat__search">
            <span class="bd-cat__sr">Cari nama atau slug kategori</span>
            @include('admin.categories.partials.icon', ['name' => 'search'])
            <input class="bd-cat__input" type="search" name="search" value="{{ request('search') }}" maxlength="160" placeholder="Cari nama atau slug…" autocomplete="off" data-cat-search>
        </label>
        <select class="bd-cat__select bd-cat__select--filter" name="status" aria-label="Filter status" data-cat-auto>
            <option value="">Semua status</option>
            <option value="active" @selected(request('status') === 'active')>Aktif</option>
            <option value="archived" @selected(request('status') === 'archived')>Diarsipkan</option>
        </select>
        <select class="bd-cat__select bd-cat__select--filter" name="level" aria-label="Filter tingkat" data-cat-auto>
            <option value="">Semua tingkat</option>
            <option value="main" @selected(request('level') === 'main')>Kategori utama</option>
            <option value="sub" @selected(request('level') === 'sub')>Subkategori</option>
        </select>
        <button type="submit" class="bd-cat__btn">Cari</button>
        <span data-cat-swap="reset" style="display:contents">
            @if ($filtering)
                <a href="{{ route('admin.categories.index') }}" class="bd-cat__btn bd-cat__btn--quiet" data-cat-reset>Atur ulang</a>
            @endif
        </span>
    </form>

    <div data-cat-swap="list" aria-live="polite">
        @if (count($tree) === 0)
            <div class="bd-cat__empty">
                @if ($filtering)
                    <h2>Tidak ada kategori yang cocok</h2>
                    <p>Coba kata kunci lain atau atur ulang filter.</p>
                    <a href="{{ route('admin.categories.index') }}" class="bd-cat__btn" data-cat-reset>Atur ulang filter</a>
                @else
                    <h2>Belum ada kategori</h2>
                    <p>Buat kategori pertama agar sumber di Pustaka dapat dikelompokkan.</p>
                    <a href="{{ route('admin.categories.create') }}" class="bd-cat__btn bd-cat__btn--primary" data-cat-new>@include('admin.categories.partials.icon', ['name' => 'plus']) Tambah kategori pertama</a>
                @endif
            </div>
        @else
            <div class="bd-cat__list-head" aria-hidden="true">
                <span>Kategori</span><span>Sumber</span><span>Status</span><span></span>
            </div>
            <ul class="bd-cat__tree" aria-label="Daftar kategori">
                @foreach ($tree as $node)
                    @include('admin.categories.partials.node', ['node' => $node, 'depth' => 0])
                @endforeach
            </ul>
            @if ($filtering)
                <p class="bd-cat__muted">{{ $matchCount }} kategori cocok. Induk yang tidak cocok ditampilkan redup agar hierarkinya tetap terbaca.</p>
            @endif
        @endif
    </div>

    <dialog class="bd-cat__dialog" data-cat-dialog aria-labelledby="cat-dialog-title">
        <form method="POST" action="{{ route('admin.categories.store') }}" novalidate data-cat-form>
            @csrf
            <input type="hidden" name="_method" value="PUT" data-cat-method disabled>
            <div class="bd-cat__dialog-head">
                <div>
                    <h2 id="cat-dialog-title" data-cat-title>Tambah kategori</h2>
                    <p data-cat-subtitle>Nama, induk, dan status. Slug dibuat otomatis dan bisa Anda ubah.</p>
                </div>
                <button type="button" class="bd-cat__icon-btn" data-cat-close aria-label="Tutup">@include('admin.categories.partials.icon', ['name' => 'x'])</button>
            </div>

            <div class="bd-cat__fields">
                @include('admin.categories.partials.fields', ['category' => new \App\Models\Category, 'parents' => $parents, 'blocked' => [], 'modal' => true])
            </div>

            <p class="bd-cat__alert" data-cat-error role="alert" hidden></p>

            <div class="bd-cat__dialog-actions">
                <button type="button" class="bd-cat__btn bd-cat__btn--quiet" data-cat-cancel>Batal</button>
                <button type="submit" class="bd-cat__btn bd-cat__btn--primary" data-cat-save>Simpan kategori</button>
            </div>
        </form>
    </dialog>

    <dialog class="bd-cat__dialog bd-cat__dialog--small" data-cat-discard aria-labelledby="cat-discard-title">
        <div class="bd-cat__dialog-body">
            <h2 id="cat-discard-title">Buang perubahan?</h2>
            <p>Perubahan yang belum disimpan akan hilang.</p>
            <div class="bd-cat__dialog-actions">
                <button type="button" class="bd-cat__btn" data-cat-keep>Lanjut mengedit</button>
                <button type="button" class="bd-cat__btn bd-cat__btn--primary" data-cat-drop>Buang</button>
            </div>
        </div>
    </dialog>
</section>
@endsection
