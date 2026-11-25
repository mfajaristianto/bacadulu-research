{{-- Dialog bersama Pustaka: penampil PDF, konfirmasi, dan peringatan duplikat. Konten diisi admin-library.js; PDF hanya dimuat saat dibuka. --}}
<dialog class="bd-lib__dialog bd-lib__viewer" data-lib-viewer aria-labelledby="lib-viewer-title">
    <div class="bd-lib__viewer-bar">
        <div class="bd-lib__viewer-title">
            <strong id="lib-viewer-title" data-viewer-title>Dokumen</strong>
            <span data-viewer-version></span>
        </div>
        <div class="bd-lib__viewer-tools">
            <a class="bd-lib__btn bd-lib__btn--quiet bd-lib__btn--small" data-viewer-download href="#">@include('admin.library.partials.icon', ['name' => 'download']) Unduh</a>
            <a class="bd-lib__btn bd-lib__btn--quiet bd-lib__btn--small" data-viewer-open href="#" target="_blank" rel="noopener">@include('admin.library.partials.icon', ['name' => 'external']) Tab baru</a>
            <button type="button" class="bd-lib__icon-btn" data-viewer-close aria-label="Tutup penampil PDF">@include('admin.library.partials.icon', ['name' => 'x'])</button>
        </div>
    </div>
    <div class="bd-lib__viewer-frame">
        <iframe title="Pratinjau PDF" data-viewer-frame></iframe>
        <p class="bd-lib__viewer-fallback">Penampil bawaan browser: nomor halaman dan zoom ada di toolbar PDF. Jika PDF tidak tampil di perangkat ini, gunakan Tab baru atau Unduh.</p>
    </div>
</dialog>

<dialog class="bd-lib__dialog" data-lib-confirm-dialog aria-labelledby="lib-confirm-title">
    <div class="bd-lib__dialog-body">
        <h2 id="lib-confirm-title" data-confirm-title>Konfirmasi</h2>
        <p data-confirm-text></p>
        <div class="bd-lib__dialog-actions">
            <button type="button" class="bd-lib__btn" data-confirm-cancel>Batal</button>
            <button type="button" class="bd-lib__btn bd-lib__btn--primary" data-confirm-ok>Lanjutkan</button>
        </div>
    </div>
</dialog>
