@extends('layouts.admin')

@section('title', 'Kamus Istilah Pencarian')
@section('page_title', 'Kamus Istilah Pencarian')

@php
    $fmt = fn (int $n): string => number_format($n, 0, ',', '.');
    $wib = fn ($date) => $date?->copy()->timezone('Asia/Jakarta')->locale('id')->translatedFormat('j F Y, H.i');
    $statusLabels = ['active' => 'Aktif', 'inactive' => 'Nonaktif'];
    $sortLabels = ['alpha' => 'Abjad A–Z', 'updated' => 'Terakhir diperbarui'];
@endphp

@section('content')
<section
    class="admin-page bd-gl"
    data-gl
    data-index-url="{{ route('admin.glossary.index') }}"
    data-store-url="{{ route('admin.glossary.store') }}"
    data-update-url="{{ route('admin.glossary.update', '__ID__') }}"
    data-check-url="{{ route('admin.glossary.check') }}"
>
    <header class="bd-gl__head">
        <div>
            <h1>Kamus Istilah Pencarian</h1>
            <p>Padanan dan sinonim untuk pencarian dwibahasa dan perluasan kata kunci, mis. nilai perusahaan = firm value = company value.</p>
        </div>
        <a href="{{ route('admin.glossary.create') }}" class="bd-gl__btn bd-gl__btn--primary" data-gl-new>@include('admin.glossary.partials.icon', ['name' => 'plus']) Tambah Istilah</a>
    </header>

    <dl class="bd-gl__stats" aria-label="Ringkasan glosarium" data-gl-swap="stats">
        <div class="bd-gl__stat"><dt>Total istilah</dt><dd>{{ $fmt($stats['total']) }}</dd></div>
        <div class="bd-gl__stat"><dt>Istilah aktif</dt><dd>{{ $fmt($stats['active']) }}</dd></div>
        <div class="bd-gl__stat"><dt>Kategori terpakai</dt><dd>{{ $fmt($stats['categories']) }}</dd></div>
        <div class="bd-gl__stat"><dt>Alias</dt><dd>{{ $fmt($stats['aliases']) }}</dd></div>
    </dl>

    <p class="bd-gl__muted">Istilah aktif yang muncul pada pertanyaan atau bukti dikirim ke AI sebagai konteks terminologi. Glosarium bukan sumber evidence dan tidak dapat disitasi.</p>

    <div class="bd-gl__toast" data-gl-toast role="status" aria-live="polite" hidden>
        <p data-gl-toast-text></p>
        <button type="button" class="bd-gl__icon-btn" data-gl-toast-close aria-label="Tutup pesan">@include('admin.glossary.partials.icon', ['name' => 'x'])</button>
    </div>

    <form method="GET" action="{{ route('admin.glossary.index') }}" class="bd-gl__filters" role="search" data-gl-filters>
        <label class="bd-gl__search">
            <span class="bd-gl__sr">Cari istilah, alias, atau definisi</span>
            @include('admin.glossary.partials.icon', ['name' => 'search'])
            <input class="bd-gl__input" type="search" name="search" value="{{ request('search') }}" maxlength="160" placeholder="Cari istilah, alias, atau definisi…" autocomplete="off" data-gl-search>
        </label>
        <select class="bd-gl__select bd-gl__select--filter" name="category" aria-label="Filter kategori" data-gl-auto>
            <option value="">Semua kategori</option>
            <option value="general" @selected(request('category') === 'general')>Umum</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected((string) request('category') === (string) $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
        <select class="bd-gl__select bd-gl__select--filter" name="status" aria-label="Filter status" data-gl-auto>
            <option value="">Semua status</option>
            @foreach ($statusLabels as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <select class="bd-gl__select bd-gl__select--filter" name="sort" aria-label="Urutkan" data-gl-auto>
            @foreach ($sortLabels as $value => $label)
                <option value="{{ $value }}" @selected(request('sort', 'alpha') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <button type="submit" class="bd-gl__btn">Cari</button>
        <span data-gl-swap="reset" style="display:contents">
            @if ($filtering)
                <a href="{{ route('admin.glossary.index') }}" class="bd-gl__btn bd-gl__btn--quiet" data-gl-reset>Atur ulang</a>
            @endif
        </span>
    </form>

    <div data-gl-swap="list" aria-live="polite">
        @if ($terms->isEmpty())
            <div class="bd-gl__empty">
                @if ($filtering)
                    <h2>Tidak ada istilah yang cocok</h2>
                    <p>Coba kata kunci lain atau atur ulang filter.</p>
                    <a href="{{ route('admin.glossary.index') }}" class="bd-gl__btn" data-gl-reset>Atur ulang filter</a>
                @else
                    <h2>Glosarium masih kosong</h2>
                    <p>Tambahkan istilah, definisi, dan sinonimnya agar AI memahami terminologi riset Anda. Istilah ini hanya konteks, bukan sumber sitasi.</p>
                    <a href="{{ route('admin.glossary.create') }}" class="bd-gl__btn bd-gl__btn--primary" data-gl-new>@include('admin.glossary.partials.icon', ['name' => 'plus']) Tambah istilah pertama</a>
                @endif
            </div>
        @else
            <div class="bd-gl__list-head" aria-hidden="true"><span>Istilah</span><span>Kategori</span><span>Status</span><span></span></div>
            <ul class="bd-gl__list" aria-label="Daftar istilah">
                @foreach ($terms as $term)
                    @php $aliases = (array) ($term->aliases_json ?? []); @endphp
                    <li class="bd-gl__item" data-gl-item data-id="{{ $term->id }}">
                        <div class="bd-gl__row">
                            <div class="bd-gl__name">
                                <button type="button" class="bd-gl__twist" aria-expanded="false" aria-controls="gl-panel-{{ $term->id }}" aria-label="Tampilkan detail {{ $term->term }}" data-gl-toggle>@include('admin.glossary.partials.icon', ['name' => 'chevron-down'])</button>
                                <div class="bd-gl__text">
                                    <strong class="bd-gl__title">{{ $term->term }}</strong>
                                    <span class="bd-gl__def">{{ $term->definition }}</span>
                                    @if ($aliases)
                                        <span class="bd-gl__alias-line">Alias: {{ implode(' · ', array_slice($aliases, 0, 3)) }}@if (count($aliases) > 3) · +{{ count($aliases) - 3 }} lainnya @endif</span>
                                    @endif
                                </div>
                            </div>
                            <span class="bd-gl__cat">{{ $term->category?->name ?? 'Umum' }}</span>
                            <span class="bd-gl__state bd-gl__state--{{ $term->status }}">{{ $statusLabels[$term->status] ?? $term->status }}</span>
                            <div class="bd-gl__actions">
                                <a href="{{ route('admin.glossary.edit', $term) }}" class="bd-gl__btn bd-gl__btn--quiet bd-gl__btn--small" data-gl-edit
                                   data-id="{{ $term->id }}" data-term="{{ $term->term }}" data-definition="{{ $term->definition }}" data-aliases="{{ json_encode($aliases) }}" data-category="{{ $term->category_id }}" data-status="{{ $term->status }}" data-note="{{ $term->context_note }}">Edit<span class="bd-gl__sr"> {{ $term->term }}</span></a>
                            </div>
                        </div>

                        <div class="bd-gl__panel" id="gl-panel-{{ $term->id }}">
                            <div inert>
                                <dl class="bd-gl__facts">
                                    <div class="is-wide"><dt>Definisi</dt><dd class="bd-gl__text-block">{{ $term->definition }}</dd></div>
                                    <div class="is-wide">
                                        <dt>Alias</dt>
                                        <dd>
                                            @if ($aliases)
                                                <ul class="bd-gl__chips bd-gl__chips--static">@foreach ($aliases as $alias)<li>{{ $alias }}</li>@endforeach</ul>
                                            @else
                                                <span class="bd-gl__muted">Tanpa alias</span>
                                            @endif
                                        </dd>
                                    </div>
                                    <div><dt>Berlaku untuk</dt><dd>{{ $term->category?->name ?? 'Semua kategori (umum)' }}</dd></div>
                                    <div><dt>Status</dt><dd>{{ $statusLabels[$term->status] ?? $term->status }}@if ($term->status === 'inactive') · tidak dikirim ke AI @endif</dd></div>
                                    @if ($term->context_note)
                                        <div class="is-wide"><dt>Catatan konteks</dt><dd class="bd-gl__text-block">{{ $term->context_note }}</dd></div>
                                    @endif
                                    <div><dt>Diperbarui</dt><dd>{{ $wib($term->updated_at) }} WIB</dd></div>
                                </dl>
                                @if ($term->status === 'active')
                                    <div class="bd-gl__panel-actions">
                                        <button type="button" class="bd-gl__btn bd-gl__btn--quiet bd-gl__btn--small bd-gl__btn--danger" data-gl-deactivate data-url="{{ route('admin.glossary.destroy', $term) }}" data-term="{{ $term->term }}">Nonaktifkan</button>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>

            @if ($terms->hasPages())
                <nav class="bd-gl__pager" aria-label="Halaman istilah">
                    <p class="bd-gl__muted">{{ $terms->firstItem() }}–{{ $terms->lastItem() }} dari {{ $terms->total() }} istilah</p>
                    <div>
                        @if ($terms->onFirstPage())
                            <span class="bd-gl__btn bd-gl__btn--quiet bd-gl__btn--small" aria-disabled="true">Sebelumnya</span>
                        @else
                            <a class="bd-gl__btn bd-gl__btn--small" href="{{ $terms->previousPageUrl() }}" data-gl-nav rel="prev">Sebelumnya</a>
                        @endif
                        @if ($terms->hasMorePages())
                            <a class="bd-gl__btn bd-gl__btn--small" href="{{ $terms->nextPageUrl() }}" data-gl-nav rel="next">Berikutnya</a>
                        @else
                            <span class="bd-gl__btn bd-gl__btn--quiet bd-gl__btn--small" aria-disabled="true">Berikutnya</span>
                        @endif
                    </div>
                </nav>
            @endif
        @endif
    </div>

    <dialog class="bd-gl__dialog" data-gl-dialog aria-labelledby="gl-dialog-title">
        <form method="POST" action="{{ route('admin.glossary.store') }}" novalidate data-gl-form>
            @csrf
            <input type="hidden" name="_method" value="PUT" data-gl-method disabled>
            <div class="bd-gl__dialog-head">
                <div>
                    <h2 id="gl-dialog-title" data-gl-title>Tambah istilah</h2>
                    <p data-gl-subtitle>Istilah, definisi, dan sinonimnya. Alias membantu AI mengenali istilah yang sama dengan penulisan berbeda.</p>
                </div>
                <button type="button" class="bd-gl__icon-btn" data-gl-close aria-label="Tutup">@include('admin.glossary.partials.icon', ['name' => 'x'])</button>
            </div>

            <div class="bd-gl__fields">
                @include('admin.glossary.partials.fields', ['term' => new \App\Models\GlossaryTerm(['status' => 'active']), 'categories' => $categories, 'modal' => true])
            </div>

            <p class="bd-gl__alert" data-gl-error role="alert" hidden></p>

            <div class="bd-gl__dialog-actions">
                <button type="button" class="bd-gl__btn bd-gl__btn--quiet" data-gl-cancel>Batal</button>
                <button type="submit" class="bd-gl__btn bd-gl__btn--primary" data-gl-save>Simpan istilah</button>
            </div>
        </form>
    </dialog>

    <dialog class="bd-gl__dialog bd-gl__dialog--small" data-gl-confirm aria-labelledby="gl-confirm-title">
        <div class="bd-gl__dialog-body">
            <h2 id="gl-confirm-title" data-gl-confirm-title>Konfirmasi</h2>
            <p data-gl-confirm-text></p>
            <div class="bd-gl__dialog-actions">
                <button type="button" class="bd-gl__btn" data-gl-confirm-cancel>Batal</button>
                <button type="button" class="bd-gl__btn bd-gl__btn--primary" data-gl-confirm-ok>Lanjutkan</button>
            </div>
        </div>
    </dialog>
</section>
@endsection
