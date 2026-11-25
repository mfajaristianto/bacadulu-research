@extends('layouts.admin')

@php
    $editing = $document->exists;
    $maxMb = (int) round(config('research.ingestion.max_upload_kb', 102400) / 1024);
    $statusLabels = ['draft' => 'Draf', 'processing' => 'Diproses', 'ready' => 'Siap terbit', 'published' => 'Terbit', 'failed' => 'Gagal', 'archived' => 'Diarsipkan'];
    $status = $document->status ?: 'draft';
    $current = collect($history)->firstWhere('is_active', true) ?? ($history[0] ?? null);
    $size = fn (int $bytes): string => $bytes >= 1048576
        ? number_format($bytes / 1048576, 1, ',', '.').' MB'
        : number_format(max($bytes, 1) / 1024, 0, ',', '.').' KB';
    $ready = $latestFile !== null;
    $published = $status === 'published' && $chunkCount > 0;
@endphp

@section('title', $editing ? 'Detail Sumber' : 'Tambah Sumber')
@section('page_title', $editing ? 'Detail Sumber' : 'Tambah Sumber')

@section('content')
<section class="admin-page bd-lib" data-lib>

    <header>
        <a href="{{ route('admin.library.index') }}" class="bd-lib__back">@include('admin.library.partials.icon', ['name' => 'arrow']) Pustaka</a>
        <div class="bd-lib__head">
            <div>
                <h1>{{ $editing ? $document->title : 'Tambah Sumber' }}</h1>
                <p>
                    @if ($editing)
                        SRC-{{ str_pad((string) $document->id, 4, '0', STR_PAD_LEFT) }} · diperbarui {{ $document->updated_at?->timezone('Asia/Jakarta')->locale('id')->translatedFormat('j F Y, H.i') }} WIB
                    @else
                        Judul, kategori, dan satu PDF. Sistem memproses file menjadi potongan teks sebelum sumber bisa diterbitkan.
                    @endif
                </p>
            </div>
            @if ($editing)
                <span data-lib-swap="chip" style="display:contents"><span class="bd-lib__status bd-lib__status--{{ $status }}" @if ($status === 'processing') data-lib-watch="{{ route('admin.library.ingestion-status', $document) }}" @endif>{{ $statusLabels[$status] ?? ucfirst($status) }}</span></span>
            @endif
        </div>
    </header>

    @if ($categories->isEmpty())
        <p class="bd-lib__alert">Belum ada kategori aktif. <a href="{{ route('admin.categories.create') }}" style="text-decoration:underline">Buat kategori</a> sebelum menambahkan sumber.</p>
    @endif

    <div class="bd-lib__layout">
        <form
            class="bd-lib__form"
            method="POST"
            action="{{ $editing ? route('admin.library.update', $document) : route('admin.library.store') }}"
            enctype="multipart/form-data"
            novalidate
            data-lib-form
            data-max-bytes="{{ $maxMb * 1048576 }}"
            data-max-mb="{{ $maxMb }}"
            data-check-url="{{ route('admin.library.check-duplicate') }}"
            data-document-id="{{ $editing ? $document->id : '' }}"
        >
            @csrf
            @if ($editing) @method('PUT') @endif
            <input type="hidden" name="duplicate_decision" value="" data-lib-decision>
            <input type="hidden" name="duplicate_target" value="" data-lib-target>

            <div class="bd-lib__fields">
                <div class="bd-lib__field is-wide">
                    <label class="bd-lib__label" for="source-title">Judul sumber</label>
                    <input class="bd-lib__input" id="source-title" name="title" type="text" maxlength="255" required value="{{ old('title', $document->title) }}" placeholder="Mis. ESG Disclosure and Firm Value" aria-describedby="err-title" @if ($errors->has('title')) aria-invalid="true" @endif data-lib-title>
                    <p class="bd-lib__error" id="err-title" data-error-for="title">{{ $errors->first('title') }}</p>
                </div>

                <div class="bd-lib__field">
                    <label class="bd-lib__label" for="source-category">Kategori</label>
                    <select class="bd-lib__select bd-lib__select--block" id="source-category" name="category_id" required aria-describedby="err-category" @if ($errors->has('category_id')) aria-invalid="true" @endif>
                        <option value="">Pilih kategori</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) old('category_id', $document->category_id) === (string) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    <p class="bd-lib__error" id="err-category" data-error-for="category_id">{{ $errors->first('category_id') }}</p>
                </div>

                <div class="bd-lib__field">
                    <label class="bd-lib__label" for="source-doi">DOI <small>opsional</small></label>
                    <input class="bd-lib__input" id="source-doi" name="doi" type="text" maxlength="190" inputmode="url" autocomplete="off" value="{{ old('doi', $document->doi) }}" placeholder="10.1234/contoh.2024" aria-describedby="err-doi hint-doi" @if ($errors->has('doi')) aria-invalid="true" @endif data-lib-doi>
                    <p class="bd-lib__hint" id="hint-doi">Dipakai untuk mengenali sumber yang sama. Boleh ditempel sebagai URL doi.org.</p>
                    <p class="bd-lib__error" id="err-doi" data-error-for="doi">{{ $errors->first('doi') }}</p>
                </div>
            </div>

            <div class="bd-lib__field">
                <span class="bd-lib__label" id="file-label">{{ $editing ? 'Versi file baru' : 'File PDF' }} @if ($editing)<small>opsional</small>@endif</span>

                @if ($editing && $current)
                    <p class="bd-lib__muted">
                        File aktif: <strong>{{ $current['name'] }}</strong> ({{ $current['label'] }}@if ($current['size']), {{ $size($current['size']) }}@endif).
                        Mengunggah file lain membuat versi baru; versi sebelumnya tetap tersimpan dan sumber diproses ulang.
                    </p>
                @endif

                <div class="bd-lib__drop" data-lib-drop>
                    <input id="source-file" type="file" name="source_file" accept="application/pdf,.pdf" aria-labelledby="file-label" aria-describedby="err-file" @required(! $editing) data-lib-file>
                    <label class="bd-lib__drop-area" for="source-file" data-lib-area>
                        <span class="bd-lib__drop-icon">@include('admin.library.partials.icon', ['name' => 'upload'])</span>
                        <strong>Tarik PDF ke sini atau klik untuk memilih</strong>
                        <span>Hanya PDF · maksimal {{ $maxMb }} MB</span>
                    </label>
                </div>

                <div class="bd-lib__file" data-lib-card hidden>
                    <span class="bd-lib__pdf" aria-hidden="true">PDF</span>
                    <div style="min-width:0">
                        <p class="bd-lib__file-name" data-lib-name></p>
                        <p class="bd-lib__file-meta" data-lib-meta role="status"></p>
                    </div>
                    <button type="button" class="bd-lib__btn bd-lib__btn--quiet bd-lib__btn--small" data-lib-clear>@include('admin.library.partials.icon', ['name' => 'x']) Hapus pilihan</button>
                </div>

                <div class="bd-lib__progress" data-lib-progress hidden>
                    <div class="bd-lib__progress-bar" role="progressbar" aria-label="Progres unggah" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" data-lib-bar><i></i></div>
                    <div class="bd-lib__progress-text"><span data-lib-progress-text>Mengunggah…</span><button type="button" class="bd-lib__btn bd-lib__btn--quiet bd-lib__btn--small" data-lib-cancel>Batalkan</button></div>
                </div>

                <p class="bd-lib__error" id="err-file" data-error-for="source_file" role="alert">{{ $errors->first('source_file') }}</p>
            </div>

            <div class="bd-lib__form-actions" data-lib-swap="actions">
                <button class="bd-lib__btn bd-lib__btn--primary" type="submit" name="action" value="save" data-lib-submit @disabled($categories->isEmpty())>{{ $editing ? 'Simpan perubahan' : 'Simpan dan proses' }}</button>

                @if ($editing)
                    <button class="bd-lib__btn" type="submit" name="action" value="reprocess">Proses ulang</button>

                    @if ($document->status === 'published')
                        <button class="bd-lib__btn" type="submit" name="action" value="unpublish">Batalkan terbit</button>
                    @else
                        <button class="bd-lib__btn" type="submit" name="action" value="publish" @disabled($chunkCount < 1) @if ($chunkCount < 1) title="Belum ada potongan teks; selesaikan pemrosesan dulu" @endif>Terbitkan</button>
                    @endif

                    <button class="bd-lib__btn bd-lib__btn--danger" type="submit" name="action" value="archive" data-confirm="Arsipkan sumber ini? Sumber tidak lagi dipakai sebagai sumber aktif; file dan riwayat versi tetap tersimpan.">Arsipkan</button>
                @endif
            </div>
        </form>

        <aside class="bd-lib__aside" aria-label="Status dan riwayat" data-lib-swap="aside">
            <section>
                <h2 class="bd-lib__heading">Pemrosesan</h2>
                <div class="bd-lib__steps">
                    <div class="bd-lib__step {{ $ready ? 'is-done' : '' }}"><i>{{ $ready ? '✓' : '1' }}</i><span>PDF tersimpan</span><strong>{{ $ready ? 'Ya' : 'Menunggu' }}</strong></div>
                    <div class="bd-lib__step {{ $chunkCount > 0 ? 'is-done' : '' }}"><i>{{ $chunkCount > 0 ? '✓' : '2' }}</i><span>Potongan teks</span><strong>{{ number_format($chunkCount, 0, ',', '.') }}</strong></div>
                    <div class="bd-lib__step {{ $published ? 'is-done' : '' }}"><i>{{ $published ? '✓' : '3' }}</i><span>Siap dipakai riset</span><strong>{{ $published ? 'Ya' : 'Belum' }}</strong></div>
                </div>
                @if ($editing && $status === 'processing')
                    <p class="bd-lib__muted" data-lib-stage role="status">PDF sedang diproses · {{ (int) $document->processing_progress }}%. Status diperbarui otomatis tanpa memuat ulang halaman.</p>
                @elseif ($editing && $status === 'failed' && filled($document->processing_error))
                    <p class="bd-lib__alert" role="alert">Ingestion PDF gagal: {{ $document->processing_error }}</p>
                @endif
                <p class="bd-lib__muted">Draf → Siap terbit → Terbit. Arsip dipakai sebagai pengganti hapus agar riwayat tetap aman.</p>
            </section>

            @if ($editing)
                <section>
                    <h2 class="bd-lib__heading">Riwayat versi</h2>
                    @if ($history)
                        @include('admin.library.partials.versions', ['document' => $document, 'history' => $history])
                    @else
                        <p class="bd-lib__muted">Belum ada file tersimpan untuk sumber ini.</p>
                    @endif
                </section>
            @endif
        </aside>
    </div>

    <dialog class="bd-lib__dialog" data-lib-duplicate aria-labelledby="lib-dup-title">
        <div class="bd-lib__dialog-body">
            <h2 id="lib-dup-title" data-dup-title>Dokumen mungkin sudah ada</h2>
            <p data-dup-text></p>
            <div class="bd-lib__dialog-match"><span class="bd-lib__muted">Sumber yang ditemukan</span><b data-dup-match></b><span class="bd-lib__muted" data-dup-detail></span></div>
            <div class="bd-lib__dialog-actions">
                <button type="button" class="bd-lib__btn bd-lib__btn--quiet" data-dup-cancel>Batalkan unggahan</button>
                <a class="bd-lib__btn" data-dup-view href="#">Lihat sumber yang ada</a>
                <button type="button" class="bd-lib__btn bd-lib__btn--primary" data-dup-version hidden>Unggah sebagai versi baru</button>
            </div>
        </div>
    </dialog>

    @include('admin.library.partials.dialogs')
</section>
@endsection
