@extends('layouts.admin')

@section('title', 'Koleksi')
@section('page_title', 'Koleksi')

@php
    $fmt = fn (int $n): string => number_format($n, 0, ',', '.');
    $wib = fn ($date) => $date?->copy()->timezone('Asia/Jakarta')->locale('id')->translatedFormat('j M Y');
    $statusLabels = ['active' => 'Aktif', 'archived' => 'Diarsipkan'];
    $sortLabels = ['updated' => 'Terakhir diperbarui', 'name' => 'Nama A–Z', 'sources' => 'Sumber terbanyak'];
@endphp

@section('content')
<section
    class="admin-page bd-col"
    data-col
    data-index-url="{{ route('admin.collections.index') }}"
    data-store-url="{{ route('admin.collections.store') }}"
    data-update-url="{{ route('admin.collections.update', '__ID__') }}"
    data-members-url="{{ route('admin.collections.members', '__ID__') }}"
    data-candidates-url="{{ route('admin.collections.candidates', '__ID__') }}"
    data-attach-url="{{ route('admin.collections.attach', '__ID__') }}"
>
    <header class="bd-col__head">
        <div>
            <h1>Koleksi</h1>
            <p>Kelompokkan sumber akademik berdasarkan topik dan kebutuhan riset.</p>
        </div>
        <a href="{{ route('admin.collections.create') }}" class="bd-col__btn bd-col__btn--primary" data-col-new>@include('admin.collections.partials.icon', ['name' => 'plus']) Tambah Koleksi</a>
    </header>

    <dl class="bd-col__stats" aria-label="Ringkasan koleksi" data-col-swap="stats">
        <div class="bd-col__stat"><dt>Total koleksi</dt><dd>{{ $fmt($stats['total']) }}</dd></div>
        <div class="bd-col__stat"><dt>Sumber dalam koleksi</dt><dd>{{ $fmt($stats['members']) }}</dd></div>
        @if ($stats['newest'])
            <div class="bd-col__stat bd-col__stat--text"><dt>Koleksi terbaru</dt><dd title="{{ $stats['newest']->name }}">{{ $stats['newest']->name }}</dd><small>dibuat {{ $wib($stats['newest']->created_at) }}</small></div>
        @endif
    </dl>

    <div class="bd-col__toast" data-col-toast role="status" aria-live="polite" hidden>
        <p data-col-toast-text></p>
        <button type="button" class="bd-col__icon-btn" data-col-toast-close aria-label="Tutup pesan">@include('admin.collections.partials.icon', ['name' => 'x'])</button>
    </div>

    <form method="GET" action="{{ route('admin.collections.index') }}" class="bd-col__filters" role="search" data-col-filters>
        <label class="bd-col__search">
            <span class="bd-col__sr">Cari nama atau deskripsi koleksi</span>
            @include('admin.collections.partials.icon', ['name' => 'search'])
            <input class="bd-col__input" type="search" name="search" value="{{ request('search') }}" maxlength="160" placeholder="Cari nama atau deskripsi…" autocomplete="off" data-col-search>
        </label>
        <select class="bd-col__select bd-col__select--filter" name="status" aria-label="Filter status" data-col-auto>
            <option value="">Semua status</option>
            @foreach ($statusLabels as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <select class="bd-col__select bd-col__select--filter" name="sort" aria-label="Urutkan" data-col-auto>
            @foreach ($sortLabels as $value => $label)
                <option value="{{ $value }}" @selected(request('sort', 'updated') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <button type="submit" class="bd-col__btn">Cari</button>
        <span data-col-swap="reset" style="display:contents">
            @if ($filtering)
                <a href="{{ route('admin.collections.index') }}" class="bd-col__btn bd-col__btn--quiet" data-col-reset>Atur ulang</a>
            @endif
        </span>
    </form>

    <div data-col-swap="list" aria-live="polite">
        @if ($collections->isEmpty())
            <div class="bd-col__empty">
                @if ($filtering)
                    <h2>Tidak ada koleksi yang cocok</h2>
                    <p>Coba kata kunci lain atau atur ulang filter.</p>
                    <a href="{{ route('admin.collections.index') }}" class="bd-col__btn" data-col-reset>Atur ulang filter</a>
                @else
                    <h2>Belum ada koleksi</h2>
                    <p>Buat koleksi untuk mengelompokkan sumber Pustaka berdasarkan topik riset. Satu sumber boleh masuk ke beberapa koleksi tanpa digandakan.</p>
                    <a href="{{ route('admin.collections.create') }}" class="bd-col__btn bd-col__btn--primary" data-col-new>@include('admin.collections.partials.icon', ['name' => 'plus']) Tambah koleksi pertama</a>
                @endif
            </div>
        @else
            <div class="bd-col__list-head" aria-hidden="true"><span>Koleksi</span><span>Sumber</span><span>Diperbarui</span><span>Status</span><span></span></div>
            <ul class="bd-col__list" aria-label="Daftar koleksi">
                @foreach ($collections as $collection)
                    <li class="bd-col__item" data-col-item data-id="{{ $collection->id }}">
                        <div class="bd-col__row">
                            <div class="bd-col__name">
                                <button type="button" class="bd-col__twist" aria-expanded="false" aria-controls="col-panel-{{ $collection->id }}" aria-label="Tampilkan sumber di {{ $collection->name }}" data-col-toggle>@include('admin.collections.partials.icon', ['name' => 'chevron-down'])</button>
                                <div class="bd-col__text">
                                    <strong class="bd-col__title">{{ $collection->name }}</strong>
                                    <span class="bd-col__desc">{{ $collection->description ?: 'Tanpa deskripsi' }}</span>
                                </div>
                            </div>
                            <span class="bd-col__count" data-col-count>{{ $fmt($collection->documents_count) }} sumber</span>
                            <time class="bd-col__date" datetime="{{ $collection->updated_at?->toIso8601String() }}" title="{{ $collection->updated_at?->timezone('Asia/Jakarta')->locale('id')->translatedFormat('j F Y, H.i') }} WIB">{{ $wib($collection->updated_at) }}</time>
                            <span class="bd-col__state bd-col__state--{{ $collection->status }}">{{ $statusLabels[$collection->status] ?? $collection->status }}</span>
                            <div class="bd-col__actions">
                                <a href="{{ route('admin.collections.edit', $collection) }}" class="bd-col__btn bd-col__btn--quiet bd-col__btn--small" data-col-edit data-id="{{ $collection->id }}" data-name="{{ $collection->name }}" data-description="{{ $collection->description }}" data-status="{{ $collection->status }}">Edit<span class="bd-col__sr"> {{ $collection->name }}</span></a>
                            </div>
                        </div>
                        <div class="bd-col__panel" id="col-panel-{{ $collection->id }}" data-col-panel>
                            <div inert><div class="bd-col__panel-body" data-col-body></div></div>
                        </div>
                    </li>
                @endforeach
            </ul>

            @if ($collections->hasPages())
                <nav class="bd-col__pager" aria-label="Halaman koleksi">
                    <p class="bd-col__muted">{{ $collections->firstItem() }}–{{ $collections->lastItem() }} dari {{ $collections->total() }} koleksi</p>
                    <div>
                        @if ($collections->onFirstPage())
                            <span class="bd-col__btn bd-col__btn--quiet bd-col__btn--small" aria-disabled="true">Sebelumnya</span>
                        @else
                            <a class="bd-col__btn bd-col__btn--small" href="{{ $collections->previousPageUrl() }}" data-col-nav rel="prev">Sebelumnya</a>
                        @endif
                        @if ($collections->hasMorePages())
                            <a class="bd-col__btn bd-col__btn--small" href="{{ $collections->nextPageUrl() }}" data-col-nav rel="next">Berikutnya</a>
                        @else
                            <span class="bd-col__btn bd-col__btn--quiet bd-col__btn--small" aria-disabled="true">Berikutnya</span>
                        @endif
                    </div>
                </nav>
            @endif
        @endif
    </div>

    {{-- Form tambah/edit --}}
    <dialog class="bd-col__dialog" data-col-dialog aria-labelledby="col-dialog-title">
        <form method="POST" action="{{ route('admin.collections.store') }}" novalidate data-col-form>
            @csrf
            <input type="hidden" name="_method" value="PUT" data-col-method disabled>
            <div class="bd-col__dialog-head">
                <div>
                    <h2 id="col-dialog-title" data-col-title>Tambah koleksi</h2>
                    <p data-col-subtitle>Beri nama dan deskripsi singkat. Sumber ditambahkan setelah koleksi dibuat.</p>
                </div>
                <button type="button" class="bd-col__icon-btn" data-col-close aria-label="Tutup">@include('admin.collections.partials.icon', ['name' => 'x'])</button>
            </div>

            <div class="bd-col__fields">
                <div class="bd-col__field">
                    <label class="bd-col__label" for="col-name">Nama koleksi</label>
                    <input class="bd-col__input" id="col-name" name="name" type="text" maxlength="180" required autocomplete="off" placeholder="Mis. ESG dan Nilai Perusahaan" aria-describedby="col-name-note" data-col-name>
                    <p class="bd-col__note" id="col-name-note" data-error-for="name" role="status"></p>
                </div>
                <div class="bd-col__field">
                    <label class="bd-col__label" for="col-description">Deskripsi <small>opsional</small></label>
                    <textarea class="bd-col__input bd-col__textarea" id="col-description" name="description" rows="3" maxlength="2000" placeholder="Topik atau pertanyaan riset yang dicakup koleksi ini" aria-describedby="col-description-note" data-col-description></textarea>
                    <p class="bd-col__note" id="col-description-note" data-error-for="description" role="status"></p>
                </div>
                <div class="bd-col__field">
                    <label class="bd-col__label" for="col-status">Status</label>
                    <select class="bd-col__select" id="col-status" name="status" required data-col-status>
                        <option value="active">Aktif</option>
                        <option value="archived">Diarsipkan</option>
                    </select>
                    <p class="bd-col__hint">Mengarsipkan koleksi tidak menghapus sumbernya.</p>
                    <p class="bd-col__note" data-error-for="status" role="status"></p>
                </div>
            </div>

            <p class="bd-col__alert" data-col-error role="alert" hidden></p>

            <div class="bd-col__dialog-actions">
                <button type="button" class="bd-col__btn bd-col__btn--quiet" data-col-cancel>Batal</button>
                <button type="submit" class="bd-col__btn bd-col__btn--primary" data-col-save>Simpan koleksi</button>
            </div>
        </form>
    </dialog>

    {{-- Pemilih sumber dari Pustaka --}}
    <dialog class="bd-col__dialog bd-col__dialog--wide" data-col-picker aria-labelledby="col-picker-title">
        <div class="bd-col__picker">
            <div class="bd-col__dialog-head">
                <div>
                    <h2 id="col-picker-title">Tambah sumber ke <span data-col-picker-name></span></h2>
                    <p>Pilih satu atau beberapa sumber dari Pustaka. Sumber yang sudah ada di koleksi ini tidak ditampilkan.</p>
                </div>
                <button type="button" class="bd-col__icon-btn" data-col-picker-close aria-label="Tutup">@include('admin.collections.partials.icon', ['name' => 'x'])</button>
            </div>
            <label class="bd-col__search">
                <span class="bd-col__sr">Cari judul atau DOI</span>
                @include('admin.collections.partials.icon', ['name' => 'search'])
                <input class="bd-col__input" type="search" maxlength="160" placeholder="Cari judul atau DOI…" autocomplete="off" data-col-picker-search>
            </label>
            <div class="bd-col__picker-list" data-col-picker-list aria-live="polite"></div>
            <p class="bd-col__alert" data-col-picker-error role="alert" hidden></p>
            <div class="bd-col__dialog-actions">
                <span class="bd-col__muted bd-col__picker-count" data-col-picker-count role="status">Belum ada yang dipilih</span>
                <button type="button" class="bd-col__btn bd-col__btn--quiet" data-col-picker-cancel>Batal</button>
                <button type="button" class="bd-col__btn bd-col__btn--primary" data-col-picker-add disabled>Tambahkan</button>
            </div>
        </div>
    </dialog>

    {{-- Konfirmasi: buang perubahan / lepas sumber --}}
    <dialog class="bd-col__dialog bd-col__dialog--small" data-col-confirm aria-labelledby="col-confirm-title">
        <div class="bd-col__dialog-body">
            <h2 id="col-confirm-title" data-col-confirm-title>Konfirmasi</h2>
            <p data-col-confirm-text></p>
            <div class="bd-col__dialog-actions">
                <button type="button" class="bd-col__btn" data-col-confirm-cancel>Batal</button>
                <button type="button" class="bd-col__btn bd-col__btn--primary" data-col-confirm-ok>Lanjutkan</button>
            </div>
        </div>
    </dialog>

    {{-- Penampil PDF milik Pustaka (satu-satunya; admin-library.js mengikatnya). Pembungkus .bd-lib hanya membawa token warna. --}}
    <div class="bd-lib" data-lib style="display:contents">
        @include('admin.library.partials.dialogs')
    </div>
</section>
@endsection
