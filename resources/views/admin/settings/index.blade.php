@extends('layouts.admin')

@section('title', 'Pengaturan')
@section('page_title', 'Pengaturan')

@php
    $nf = fn ($n) => number_format((int) $n, 0, ',', '.');
    $iconPath = [
        'riset' => 'M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14Z M20 20l-4-4',
        'sumber' => 'M12 16V4 M7 9l5-5 5 5 M5 20h14',
        'dokumen' => 'M7 3h7l4 4v14H7z M14 3v4h4 M10 12h5 M10 16h5',
        'operasional' => 'M4 7h10 M18 7h2 M4 17h2 M10 17h10 M14 5v4 M6 15v4',
        'sistem' => 'M7 11V8a5 5 0 0 1 10 0v3 M6 11h12v9H6z',
    ];
    $byCat = collect($items)->groupBy('cat');
@endphp

@section('content')
{{-- Formulir tunggal untuk semua kelompok (draf tidak hilang saat berpindah kelompok). Tanpa JavaScript semua kelompok tampil berurutan dan formulir tetap tersimpan lewat PUT biasa. --}}
<section class="admin-page bd-ix bd-st is-entering" data-st data-st-url="{{ route('admin.settings.index') }}" data-st-revision="{{ $revision }}">
    <header class="bd-ix__head bd-st__head">
        <div>
            <h1>Pengaturan</h1>
            <p>Kelola konfigurasi operasional dan preferensi sistem BacaDulu Research.</p>
        </div>
        <p class="bd-st__headmeta" data-st-swap="headmeta">
            {{ $nf($overview['managed']) }} pengaturan di {{ $overview['groups'] }} kelompok
            · @if ($overview['updatedAt']) diperbarui <time datetime="{{ $overview['updatedAt']->toIso8601String() }}">{{ $overview['updatedAt']->locale('id')->translatedFormat('j M Y, H.i') }} WIB</time>@else belum pernah diubah dari bawaan server @endif
        </p>
    </header>

    @if (session('success'))
        <div class="bd-ix__toast is-ok" role="status"><p>{{ session('success') }}</p></div>
    @endif
    @if ($errors->has('revision'))
        <div class="bd-ix__toast is-bad" role="alert"><p>{{ $errors->first('revision') }}</p></div>
    @endif

    <div data-st-swap="overview">
        @include('admin.settings.partials.overview')
    </div>

    <div class="bd-st__conflict" data-st-conflict role="alert" hidden>
        <p data-st-conflict-text>Pengaturan diubah oleh admin lain.</p>
        <button type="button" class="bd-ix__btn" data-st-reload>Muat ulang halaman</button>
    </div>

    <div class="bd-st__layout">
        <aside class="bd-st__side" aria-label="Kelompok pengaturan">
            <label class="bd-ix__search bd-st__search">
                <span class="bd-ix__sr">Cari pengaturan</span>
                @include('admin.tools.partials.icon', ['name' => 'search'])
                <input class="bd-ix__input" type="search" placeholder="Cari pengaturan…" autocomplete="off" data-st-search aria-controls="st-editor">
            </label>
            <p class="bd-st__found" data-st-found role="status" aria-live="polite" hidden></p>

            <div class="bd-st__nav" role="tablist" aria-label="Kelompok pengaturan" aria-orientation="vertical" data-st-tabs>
                <span class="bd-st__nav-ind" aria-hidden="true" data-st-ind></span>
                @foreach ($categories as $key => [$name, $desc])
                    <button type="button" role="tab" id="st-tab-{{ $key }}" class="bd-st__tab {{ $category === $key ? 'is-on' : '' }}" aria-selected="{{ $category === $key ? 'true' : 'false' }}" aria-controls="st-panel-{{ $key }}" tabindex="{{ $category === $key ? 0 : -1 }}" data-st-tab="{{ $key }}">
                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="{{ $iconPath[$key] }}" /></svg>
                        <span class="bd-st__tab-copy">
                            <strong>{{ $name }}</strong>
                            <small>{{ $desc }}</small>
                        </span>
                        <span class="bd-st__tab-meta" data-st-tabmeta="{{ $key }}">
                            @if ($key === 'sistem')hanya baca @else{{ $overview['byCategory'][$key]['n'] ?? 0 }} @endif
                        </span>
                    </button>
                @endforeach
            </div>
        </aside>

        <form method="POST" action="{{ route('admin.settings.update') }}" class="bd-st__editor" id="st-editor" data-st-form novalidate>
            @csrf @method('PUT')
            <input type="hidden" name="revision" value="{{ $revision }}" data-st-revision-input>

            @foreach ($categories as $key => [$name, $desc])
                <section class="bd-st__panel" role="tabpanel" id="st-panel-{{ $key }}" aria-labelledby="st-tab-{{ $key }}" data-st-panel="{{ $key }}" @if ($category !== $key) data-st-inactive @endif>
                    <header class="bd-st__panel-head">
                        <h2>{{ $name }}</h2>
                        <p>{{ $desc }}</p>
                    </header>

                    @if ($key === 'sistem')
                        @include('admin.settings.partials.system')
                    @else
                        @php
                            $rows = $byCat[$key] ?? collect();
                            $main = $rows->reject(fn ($i) => $i['advanced'] ?? false);
                            $adv = $rows->filter(fn ($i) => $i['advanced'] ?? false);
                        @endphp
                        <div class="bd-st__rows">
                            @foreach ($main as $item)
                                @include('admin.settings.partials.row', ['item' => $item])
                            @endforeach
                        </div>

                        @if ($adv->isNotEmpty())
                            <details class="bd-st__adv" data-st-adv>
                                <summary><span>Pengaturan lanjutan</span> <small>{{ $adv->count() }} pengaturan</small></summary>
                                <div class="bd-st__rows">
                                    @foreach ($adv as $item)
                                        @include('admin.settings.partials.row', ['item' => $item])
                                    @endforeach
                                </div>
                            </details>
                        @endif

                        @if ($key === 'sumber' && $upload)
                            <div class="bd-st__util">
                                <h3 class="bd-st__h">Penggunaan nyata batas unggah</h3>
                                <div class="bd-st__meter" role="img" aria-label="Berkas terbesar {{ $nf($upload['maxKb']) }} KB dari batas {{ $nf($upload['limitKb']) }} KB">
                                    <span style="--w: {{ max(2, (float) $upload['pct']) }}%"></span>
                                </div>
                                <p>Berkas terbesar yang tercatat: <strong>{{ number_format($upload['maxKb'] / 1024, 1, ',', '.') }} MB</strong> dari batas <strong>{{ number_format($upload['limitKb'] / 1024, 0, ',', '.') }} MB</strong> ({{ str_replace('.', ',', (string) $upload['pct']) }}%), dari {{ $nf($upload['count']) }} berkas. Dihitung dari tabel berkas sumber.</p>
                            </div>
                        @endif

                        <aside class="bd-st__preview" data-st-preview="{{ $key }}" aria-live="polite">
                            <h3 class="bd-st__h">Ringkasan efek <span>· dari draf, belum tersimpan</span></h3>
                            <p data-st-preview-text></p>
                            <p class="bd-st__preview-note" data-st-preview-warn hidden></p>
                            <p class="bd-ix__hint">Ini penjelasan aturan, bukan simulasi jawaban AI. Tidak ada yang ditulis ke database sebelum Anda menyimpan.</p>
                        </aside>

                        @if ($key === 'operasional')
                            <div class="bd-st__foreign">
                                <h3 class="bd-st__h">Tersimpan tetapi belum dipakai</h3>
                                @foreach ($unused as $u)
                                    <div class="bd-st__ro">
                                        <div><strong>{{ $u['label'] }}</strong><p>{{ $u['note'] }}</p></div>
                                        <span class="bd-st__rovalue">{{ $u['value'] }} {{ $u['unit'] }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    @endif
                </section>
            @endforeach
        </form>
    </div>

    <div data-st-swap="history">
        @include('admin.settings.partials.history')
    </div>

    {{-- Di luar <form> agar tetap menempel sampai akhir halaman; tombol simpan terhubung ke formulir lewat atribut form. --}}
        <div class="bd-st__savebar" data-st-savebar hidden role="region" aria-label="Perubahan belum disimpan">
            <p><strong data-st-count>0</strong> <span data-st-count-label>perubahan belum disimpan</span></p>
            <div class="bd-st__saveactions">
                <button type="button" class="bd-ix__btn bd-ix__btn--quiet" data-st-cancel>Batalkan</button>
                <button type="submit" form="st-editor" class="bd-ix__btn bd-ix__btn--primary" data-st-save>Simpan perubahan</button>
            </div>
        </div>

    <div class="bd-st__toast" data-st-toast role="status" aria-live="polite" hidden></div>

    <dialog class="bd-st__dialog" data-st-dialog aria-labelledby="st-dialog-title">
        <form method="dialog" class="bd-st__dialog-box">
            <h2 id="st-dialog-title">Tinjau perubahan berdampak besar</h2>
            <p class="bd-st__dialog-lead">Perubahan berikut memengaruhi cara sistem bekerja untuk pengguna. Berlaku untuk riset baru; data lama tidak diubah.</p>
            <ul data-st-dialog-list></ul>
            <div class="bd-st__dialog-actions">
                <button type="submit" value="cancel" class="bd-ix__btn bd-ix__btn--quiet">Kembali mengedit</button>
                <button type="submit" value="confirm" class="bd-ix__btn bd-ix__btn--primary" data-st-confirm>Ya, simpan</button>
            </div>
        </form>
    </dialog>
</section>
@endsection
