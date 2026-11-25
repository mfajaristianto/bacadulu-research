@extends('layouts.admin')

@section('title', 'Katalog Alat AI')
@section('page_title', 'Katalog Alat AI')

@php
    $fmt = fn (int $n): string => number_format($n, 0, ',', '.');
    $wib = fn ($date) => $date?->copy()->timezone('Asia/Jakarta')->locale('id')->translatedFormat('j F Y, H.i');
    $statusLabels = ['active' => 'Aktif', 'archived' => 'Diarsipkan'];
    $promptLabels = ['draft' => 'Draf', 'active' => 'Aktif', 'archived' => 'Diarsipkan'];
    $sortLabels = ['status' => 'Aktif lebih dulu', 'name' => 'Nama A–Z', 'updated' => 'Terakhir diperbarui'];
@endphp

@section('content')
<section
    class="admin-page bd-tl"
    data-tl
    data-index-url="{{ route('admin.tools.index') }}"
    data-store-url="{{ route('admin.tools.store') }}"
    data-update-url="{{ route('admin.tools.update', '__ID__') }}"
    data-check-url="{{ route('admin.tools.check') }}"
>
    <header class="bd-tl__head">
        <div>
            <h1>Katalog Alat AI</h1>
            <p>Kelola alat, konfigurasi, dan instruksi AI yang digunakan dalam proses penelitian.</p>
        </div>
        <a href="{{ route('admin.tools.create') }}" class="bd-tl__btn bd-tl__btn--primary" data-tl-new>@include('admin.tools.partials.icon', ['name' => 'plus']) Tambah Alat</a>
    </header>

    <dl class="bd-tl__stats" aria-label="Ringkasan alat riset" data-tl-swap="stats">
        <div class="bd-tl__stat"><dt>Total alat</dt><dd>{{ $fmt($stats['total']) }}</dd></div>
        <div class="bd-tl__stat"><dt>Aktif</dt><dd>{{ $fmt($stats['active']) }}</dd></div>
        <div class="bd-tl__stat"><dt>Siap dipakai</dt><dd>{{ $fmt($stats['ready']) }}</dd></div>
        <div class="bd-tl__stat {{ $stats['missing'] > 0 ? 'is-warn' : '' }}"><dt>Aktif tanpa instruksi</dt><dd>{{ $fmt($stats['missing']) }}</dd></div>
    </dl>

    <p class="bd-tl__muted">Alat hanya mendefinisikan jenis pekerjaan riset. Instruksi AI (prompt) dan riwayat versinya dikelola terpisah; alat baru siap dipakai pengguna bila aktif dan punya instruksi aktif.</p>

    <div class="bd-tl__toast" data-tl-toast role="status" aria-live="polite" hidden>
        <p data-tl-toast-text></p>
        <a class="bd-tl__btn bd-tl__btn--quiet bd-tl__btn--small" data-tl-toast-link href="#" hidden>Kelola instruksi</a>
        <button type="button" class="bd-tl__icon-btn" data-tl-toast-close aria-label="Tutup pesan">@include('admin.tools.partials.icon', ['name' => 'x'])</button>
    </div>

    <form method="GET" action="{{ route('admin.tools.index') }}" class="bd-tl__filters" role="search" data-tl-filters>
        <label class="bd-tl__search">
            <span class="bd-tl__sr">Cari nama, slug, atau deskripsi alat</span>
            @include('admin.tools.partials.icon', ['name' => 'search'])
            <input class="bd-tl__input" type="search" name="search" value="{{ request('search') }}" maxlength="160" placeholder="Cari nama, slug, atau deskripsi…" autocomplete="off" data-tl-search>
        </label>
        <select class="bd-tl__select bd-tl__select--filter" name="status" aria-label="Filter status" data-tl-auto>
            <option value="">Semua status</option>
            @foreach ($statusLabels as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <select class="bd-tl__select bd-tl__select--filter" name="instruction" aria-label="Filter instruksi" data-tl-auto>
            <option value="">Semua instruksi</option>
            <option value="ready" @selected(request('instruction') === 'ready')>Punya instruksi aktif</option>
            <option value="missing" @selected(request('instruction') === 'missing')>Belum ada instruksi aktif</option>
        </select>
        <select class="bd-tl__select bd-tl__select--filter" name="sort" aria-label="Urutkan" data-tl-auto>
            @foreach ($sortLabels as $value => $label)
                <option value="{{ $value }}" @selected(request('sort', 'status') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <button type="submit" class="bd-tl__btn">Cari</button>
        <span data-tl-swap="reset" style="display:contents">
            @if ($filtering)
                <a href="{{ route('admin.tools.index') }}" class="bd-tl__btn bd-tl__btn--quiet" data-tl-reset>Atur ulang</a>
            @endif
        </span>
    </form>

    <div data-tl-swap="list" aria-live="polite">
        @if ($tools->isEmpty())
            <div class="bd-tl__empty">
                @if ($filtering)
                    <h2>Tidak ada alat yang cocok</h2>
                    <p>Coba kata kunci lain atau atur ulang filter.</p>
                    <a href="{{ route('admin.tools.index') }}" class="bd-tl__btn" data-tl-reset>Atur ulang filter</a>
                @else
                    <h2>Belum ada alat riset</h2>
                    <p>Alat riset kanonik biasanya dibuat lewat seeding. Anda juga bisa menambahkannya di sini, lalu menghubungkan instruksi AI-nya.</p>
                    <a href="{{ route('admin.tools.create') }}" class="bd-tl__btn bd-tl__btn--primary" data-tl-new>@include('admin.tools.partials.icon', ['name' => 'plus']) Tambah alat pertama</a>
                @endif
            </div>
        @else
            <div class="bd-tl__list-head" aria-hidden="true"><span>Alat riset</span><span>Slug</span><span>Instruksi aktif</span><span>Status</span><span></span></div>
            <ul class="bd-tl__list" aria-label="Daftar alat riset">
                @foreach ($tools as $tool)
                    @php
                        $prompt = $tool->activePromptTemplate;
                        $hasPrompt = $prompt && $prompt->status === 'active';
                        $ready = $tool->status === 'active' && $hasPrompt;
                        $manage = route('admin.prompt-versions.index', ['tool' => $tool->id]);
                    @endphp
                    <li class="bd-tl__item" data-tl-item data-id="{{ $tool->id }}">
                        <div class="bd-tl__row">
                            <div class="bd-tl__name">
                                <button type="button" class="bd-tl__twist" aria-expanded="false" aria-controls="tl-panel-{{ $tool->id }}" aria-label="Tampilkan detail {{ $tool->name }}" data-tl-toggle>@include('admin.tools.partials.icon', ['name' => 'chevron-down'])</button>
                                <div class="bd-tl__text">
                                    <strong class="bd-tl__title">{{ $tool->name }}</strong>
                                    <span class="bd-tl__desc">{{ $tool->description ?: 'Tanpa deskripsi' }}</span>
                                </div>
                            </div>
                            <code class="bd-tl__code">{{ $tool->code }}</code>
                            <div class="bd-tl__prompt">
                                @if ($hasPrompt)
                                    <strong>{{ $prompt->name }}</strong>
                                    <span class="bd-tl__sub">v{{ $prompt->version }} · {{ $tool->prompt_templates_count }} {{ $tool->prompt_templates_count === 1 ? 'versi' : 'versi' }}</span>
                                @elseif ($prompt)
                                    <strong>{{ $prompt->name }}</strong>
                                    <span class="bd-tl__warn">Instruksi terhubung berstatus {{ $promptLabels[$prompt->status] ?? $prompt->status }}</span>
                                @else
                                    <span class="bd-tl__warn">Belum ada instruksi aktif</span>
                                    <span class="bd-tl__sub">{{ $tool->prompt_templates_count }} versi tersimpan</span>
                                @endif
                            </div>
                            <div class="bd-tl__status">
                                <span class="bd-tl__state bd-tl__state--{{ $tool->status }}">{{ $statusLabels[$tool->status] ?? $tool->status }}</span>
                                @if ($ready)
                                    <span class="bd-tl__sub bd-tl__sub--ok">Siap dipakai</span>
                                @elseif ($tool->status === 'archived')
                                    <span class="bd-tl__sub">Tidak tersedia untuk pengguna</span>
                                @else
                                    <span class="bd-tl__warn">Belum siap dipakai</span>
                                @endif
                            </div>
                            <div class="bd-tl__actions">
                                <a href="{{ route('admin.tools.edit', $tool) }}" class="bd-tl__btn bd-tl__btn--quiet bd-tl__btn--small" data-tl-edit
                                   data-id="{{ $tool->id }}" data-name="{{ $tool->name }}" data-code="{{ $tool->code }}" data-description="{{ $tool->description }}" data-status="{{ $tool->status }}" data-manage="{{ $manage }}">Edit<span class="bd-tl__sr"> {{ $tool->name }}</span></a>
                            </div>
                        </div>

                        <div class="bd-tl__panel" id="tl-panel-{{ $tool->id }}">
                            <div inert>
                                <dl class="bd-tl__facts">
                                    <div class="is-wide"><dt>Deskripsi</dt><dd class="bd-tl__text-block">{{ $tool->description ?: 'Tanpa deskripsi.' }}</dd></div>
                                    <div class="is-wide">
                                        <dt>Instruksi AI terhubung</dt>
                                        <dd>
                                            @if ($prompt)
                                                <a href="{{ route('admin.prompts.index', ['instruksi' => $prompt->id]) }}">{{ $prompt->name }}</a> · versi {{ $prompt->version }} · {{ $promptLabels[$prompt->status] ?? $prompt->status }}
                                                @if ($prompt->short_description)<br><span class="bd-tl__sub">{{ $prompt->short_description }}</span>@endif
                                            @else
                                                Belum ada. Alat ini tidak akan muncul bagi pengguna sampai ada instruksi aktif.
                                            @endif
                                        </dd>
                                    </div>
                                    <div><dt>Versi instruksi</dt><dd>{{ $tool->prompt_templates_count }}</dd></div>
                                    <div><dt>Diperbarui</dt><dd>{{ $wib($tool->updated_at) }} WIB</dd></div>
                                    <div class="is-wide">
                                        <dt>Ketersediaan</dt>
                                        <dd>
                                            @if ($ready) Aktif dan punya instruksi aktif: dapat dipakai pengguna.
                                            @elseif ($tool->status === 'archived') Diarsipkan: disembunyikan dari pengguna. Instruksi dan versinya tetap tersimpan.
                                            @else Aktif, tetapi belum ada instruksi aktif: belum dapat dipakai pengguna.
                                            @endif
                                        </dd>
                                    </div>
                                </dl>
                                <div class="bd-tl__panel-actions">
                                    <a class="bd-tl__btn bd-tl__btn--small" href="{{ $manage }}">Kelola instruksi dan versi</a>
                                </div>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>

            @if ($tools->hasPages())
                <nav class="bd-tl__pager" aria-label="Halaman alat riset">
                    <p class="bd-tl__muted">{{ $tools->firstItem() }}–{{ $tools->lastItem() }} dari {{ $tools->total() }} alat</p>
                    <div>
                        @if ($tools->onFirstPage())
                            <span class="bd-tl__btn bd-tl__btn--quiet bd-tl__btn--small" aria-disabled="true">Sebelumnya</span>
                        @else
                            <a class="bd-tl__btn bd-tl__btn--small" href="{{ $tools->previousPageUrl() }}" data-tl-nav rel="prev">Sebelumnya</a>
                        @endif
                        @if ($tools->hasMorePages())
                            <a class="bd-tl__btn bd-tl__btn--small" href="{{ $tools->nextPageUrl() }}" data-tl-nav rel="next">Berikutnya</a>
                        @else
                            <span class="bd-tl__btn bd-tl__btn--quiet bd-tl__btn--small" aria-disabled="true">Berikutnya</span>
                        @endif
                    </div>
                </nav>
            @endif
        @endif
    </div>

    <dialog class="bd-tl__dialog" data-tl-dialog aria-labelledby="tl-dialog-title">
        <form method="POST" action="{{ route('admin.tools.store') }}" novalidate data-tl-form>
            @csrf
            <input type="hidden" name="_method" value="PUT" data-tl-method disabled>
            <input type="hidden" name="confirm_code_change" value="1" data-tl-confirm-code disabled>
            <div class="bd-tl__dialog-head">
                <div>
                    <h2 id="tl-dialog-title" data-tl-title>Tambah alat</h2>
                    <p data-tl-subtitle>Nama, slug, deskripsi, dan status. Instruksi AI dihubungkan di Versi Instruksi.</p>
                </div>
                <button type="button" class="bd-tl__icon-btn" data-tl-close aria-label="Tutup">@include('admin.tools.partials.icon', ['name' => 'x'])</button>
            </div>

            <div class="bd-tl__fields">
                @include('admin.tools.partials.fields', ['tool' => new \App\Models\AiTool(['status' => 'active']), 'modal' => true])
            </div>

            <p class="bd-tl__alert" data-tl-error role="alert" hidden></p>

            <div class="bd-tl__dialog-actions">
                <a class="bd-tl__link bd-tl__manage" data-tl-manage href="#" hidden>Kelola instruksi alat ini</a>
                <button type="button" class="bd-tl__btn bd-tl__btn--quiet" data-tl-cancel>Batal</button>
                <button type="submit" class="bd-tl__btn bd-tl__btn--primary" data-tl-save>Simpan alat</button>
            </div>
        </form>
    </dialog>

    <dialog class="bd-tl__dialog bd-tl__dialog--small" data-tl-confirm aria-labelledby="tl-confirm-title">
        <div class="bd-tl__dialog-body">
            <h2 id="tl-confirm-title" data-tl-confirm-title>Konfirmasi</h2>
            <p data-tl-confirm-text></p>
            <div class="bd-tl__dialog-actions">
                <button type="button" class="bd-tl__btn" data-tl-confirm-cancel>Batal</button>
                <button type="button" class="bd-tl__btn bd-tl__btn--primary" data-tl-confirm-ok>Lanjutkan</button>
            </div>
        </div>
    </dialog>
</section>
@endsection
