@extends('layouts.admin')

{{-- HtmlString: judul lewat e() dua kali (section + layout), sehingga karakter khusus harus dilindungi. --}}
@section('title', new \Illuminate\Support\HtmlString('Prompt Dokumen'))
@section('page_title', new \Illuminate\Support\HtmlString('Prompt Dokumen'))

@php
    $fmt = fn ($n): string => number_format((int) $n, 0, ',', '.');
    $wib = fn ($date) => $date?->copy()->timezone('Asia/Jakarta')->locale('id')->translatedFormat('j M Y, H.i');
@endphp

@section('content')
<section
    class="admin-page bd-ix bd-dp"
    data-dp
    data-mode="{{ $mode }}"
    data-index-url="{{ route('admin.prompt-documents.index') }}"
>
    <div data-dp-swap="head" data-mode="{{ $mode }}">
        @if ($mode === 'editor')
            @include('admin.document-prompts.partials.editor-head')
        @else
            <header class="bd-ix__head">
                <div>
                    @if (in_array($mode, ['new', 'import', 'word'], true))
                        <a href="{{ route('admin.prompt-documents.index') }}" class="bd-dp__back" data-dp-nav>@include('admin.tools.partials.icon', ['name' => 'arrow']) Prompt Dokumen</a>
                    @endif
                    <h1>{{ ['new' => 'Buat Template Prompt', 'import' => 'Pratinjau preset', 'word' => 'Impor dari Word'][$mode] ?? 'Prompt Dokumen' }}</h1>
                    <p>{{ $mode === 'word' ? 'Unggah dokumen Word (.docx) berisi instruksi penulisan. Isinya dibaca, dibagi menjadi bagian-bagian, lalu Anda tinjau dan ubah sebelum disimpan sebagai draf.' : ($mode === 'new' ? 'Mulai dari template kosong atau impor preset yang sudah disiapkan. Template baru selalu berstatus draf.' : ($mode === 'import' ? 'Baca seluruh isi sebelum menyimpan. Tidak ada yang tersimpan atau aktif sampai Anda menekan Simpan sebagai Draf.' : 'Kelola struktur dan instruksi penulisan untuk riset, artikel ilmiah, book chapter, dan naskah akademik.')) }}</p>
                </div>
                @if ($mode === 'list')
                    <div class="bd-dp__head-actions">
                        <a href="{{ route('admin.prompt-documents.index', ['word' => 1]) }}" class="bd-ix__btn" data-dp-nav>@include('admin.tools.partials.icon', ['name' => 'upload']) Impor dari Word</a>
                        <a href="{{ route('admin.prompt-documents.index', ['baru' => 1]) }}" class="bd-ix__btn bd-ix__btn--primary" data-dp-nav>@include('admin.tools.partials.icon', ['name' => 'plus']) Buat Template Prompt</a>
                    </div>
                @endif
            </header>
            @if ($mode === 'list')
                <p class="bd-ix__hint">Status integrasi: template di sini baru disimpan dan dikelola. Pembuatan dokumen pengguna saat ini dirakit tanpa AI dari hasil riset memakai Format Cetak, jadi Prompt Dokumen belum dibaca oleh alur pembuatan dokumen.</p>
            @endif
        @endif
    </div>

    <div class="bd-ix__toast {{ session('success') ? 'is-ok' : '' }}" data-dp-toast role="status" aria-live="polite" @unless (session('success')) hidden @endunless>
        <p data-dp-toast-text>{{ session('success') }}</p>
        <button type="button" class="bd-ix__icon-btn" data-dp-toast-close aria-label="Tutup pesan">@include('admin.tools.partials.icon', ['name' => 'x'])</button>
    </div>

    <div data-dp-swap="stats">
        @if ($mode === 'list' && ($stats['total'] > 0 || $filtering))
            <dl class="bd-ix__stats" aria-label="Ringkasan template">
                <div class="bd-ix__stat"><dt>Total template</dt><dd>{{ $fmt($stats['total']) }}</dd></div>
                <div class="bd-ix__stat"><dt>Siap digunakan</dt><dd>{{ $fmt($stats['ready']) }}</dd></div>
                <div class="bd-ix__stat"><dt>Draf</dt><dd>{{ $fmt($stats['draft']) }}</dd></div>
                <div class="bd-ix__stat {{ $stats['incomplete'] > 0 ? 'is-warn' : '' }}"><dt>Perlu dilengkapi</dt><dd>{{ $fmt($stats['incomplete']) }}</dd><dd class="bd-ix__stat-note">aktif, tetapi syarat belum terpenuhi</dd></div>
            </dl>
        @endif
    </div>

    <div data-dp-swap="filters" data-mode="{{ $mode }}">
        @if ($mode === 'list' && ($stats['total'] > 0 || $filtering))
            <form method="GET" action="{{ route('admin.prompt-documents.index') }}" class="bd-ix__filters" role="search" data-dp-filters>
                <label class="bd-ix__search">
                    <span class="bd-ix__sr">Cari nama atau deskripsi template</span>
                    @include('admin.tools.partials.icon', ['name' => 'search'])
                    <input class="bd-ix__input" type="search" name="search" value="{{ request('search') }}" maxlength="160" placeholder="Cari nama atau deskripsi…" autocomplete="off" data-dp-search>
                </label>
                <select class="bd-ix__select bd-ix__select--filter" name="jenis" aria-label="Filter jenis dokumen" data-dp-auto>
                    <option value="">Semua jenis</option>
                    @foreach ($types as $value => $label)
                        <option value="{{ $value }}" @selected(request('jenis') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <select class="bd-ix__select bd-ix__select--filter" name="status" aria-label="Filter status" data-dp-auto>
                    <option value="">Semua status</option>
                    @foreach ($statusFilters as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <select class="bd-ix__select bd-ix__select--filter" name="sort" aria-label="Urutkan" data-dp-auto>
                    @foreach (['updated' => 'Terbaru diperbarui', 'name' => 'Nama A–Z', 'sections' => 'Bagian terbanyak'] as $value => $label)
                        <option value="{{ $value }}" @selected(request('sort', 'updated') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <button type="submit" class="bd-ix__btn">Cari</button>
                <a href="{{ route('admin.prompt-documents.index') }}" class="bd-ix__btn bd-ix__btn--quiet" data-dp-reset @unless ($filtering) hidden @endunless>Atur ulang</a>
            </form>
        @endif
    </div>

    <div data-dp-swap="body" data-mode="{{ $mode }}">
        @switch($mode)
            @case('unavailable')
                @include('admin.document-prompts.partials.unavailable')
                @break
            @case('list')
                @include('admin.document-prompts.partials.list')
                @break
            @case('new')
                @include('admin.document-prompts.partials.new')
                @break
            @case('word')
                @include('admin.document-prompts.partials.word')
                @break
            @case('import')
                @include('admin.document-prompts.partials.import')
                @break
            @default
                @include('admin.document-prompts.partials.editor')
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
