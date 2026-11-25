@php
    $stateClass = ['active' => 'active', 'draft' => 'draft', 'legacy' => 'draft', 'archived' => 'archived'][$state['key']];
    $activeV = collect($versions)->firstWhere('status', 'active');
@endphp
<header class="bd-dp__head">
    <a href="{{ route('admin.document-templates.index') }}" class="bd-dp__back" data-dp-nav>@include('admin.tools.partials.icon', ['name' => 'arrow']) Semua template</a>
    <div class="bd-dp__head-main">
        <div>
            <h1>{{ $template->name }}</h1>
            <p class="bd-dp__meta">
                <span class="bd-ix__state bd-ix__state--{{ $stateClass }}" data-dp-state="{{ $state['key'] }}">{{ $state['label'] }}</span>
                <span>{{ $template->document_type }}</span>
                @if ($template->is_default)<span>default untuk jenis ini</span>@endif
                @if ($activeV)<span>v{{ $activeV['v'] }} dipakai dokumen baru</span>@endif
                <span>{{ $generations }} dokumen dibuat</span>
            </p>
            @if ($template->description)<p class="bd-ix__muted bd-dp__lead">{{ $template->description }}</p>@endif
        </div>
        <div class="bd-dp__head-actions">
            <button type="button" class="bd-ix__btn" data-dp-open="copy">Salin</button>
            @if ($template->status === 'active' && ! $template->is_default)
                <form method="POST" action="{{ route('admin.document-templates.default', $template) }}" data-dp-form data-dp-ask-title="Jadikan default untuk {{ $template->document_type }}?" data-dp-ask-text="Generator memilih template default lebih dulu untuk jenis ini pada dokumen baru. Default sebelumnya tidak diarsipkan, hanya tidak lagi default. Dokumen yang sudah dibuat tidak berubah." data-dp-ask-ok="Jadikan default">
                    @csrf
                    <button type="submit" class="bd-ix__btn">Jadikan default</button>
                </form>
            @endif
            @if ($template->status === 'inactive')
                <form method="POST" action="{{ route('admin.document-templates.restore', $template) }}" data-dp-form data-dp-ask-title="Pulihkan template?" data-dp-ask-text="Template kembali dapat dipakai sesuai statusnya (aktif bila punya versi aktif, selain itu draf)." data-dp-ask-ok="Pulihkan">
                    @csrf
                    <button type="submit" class="bd-ix__btn">Pulihkan</button>
                </form>
            @else
                <form method="POST" action="{{ route('admin.document-templates.destroy', $template) }}" data-dp-form data-dp-ask-title="Arsipkan template ini?" data-dp-ask-text="Template tidak dipilih untuk dokumen baru. Semua versi tetap tersimpan, dan dokumen yang sudah dibuat tetap dapat ditelusuri ke template ini." data-dp-ask-ok="Arsipkan" data-dp-ask-danger>
                    @csrf @method('DELETE')
                    <button type="submit" class="bd-ix__btn bd-ix__btn--quiet" @disabled($template->is_default) @if ($template->is_default) title="Default tidak dapat diarsipkan sebelum default pengganti dipilih" @endif>Arsipkan</button>
                </form>
            @endif
        </div>
    </div>
</header>

<dialog class="bd-ix__dialog bd-ix__dialog--small" data-dp-dialog="copy" aria-labelledby="pf-copy-title">
    <form method="POST" action="{{ route('admin.document-templates.store') }}" novalidate data-dp-form data-dp-close-on-success>
        @csrf
        <input type="hidden" name="source" value="copy">
        <input type="hidden" name="copy_from" value="{{ $template->id }}">
        <div class="bd-ix__dialog-head"><h2 id="pf-copy-title">Salin template</h2><button type="button" class="bd-ix__icon-btn" data-dp-close aria-label="Tutup">@include('admin.tools.partials.icon', ['name' => 'x'])</button></div>
        <div class="bd-ix__fields">
            <div class="bd-ix__field">
                <label class="bd-ix__label" for="pf-copy-name-d">Nama salinan <small>wajib</small></label>
                <input class="bd-ix__input" id="pf-copy-name-d" name="name" maxlength="180" value="{{ $template->name }} (salinan)" required data-dp-field>
                <p class="bd-ix__note" data-error-for="name"></p>
            </div>
            <p class="bd-ix__hint">Dibuat sebagai draf v1 baru dari layout versi aktif (atau terbaru). Tidak berbagi versi dengan template ini.</p>
        </div>
        <p class="bd-ix__alert" data-dp-error role="alert" hidden></p>
        <div class="bd-ix__dialog-actions"><button type="button" class="bd-ix__btn" data-dp-close>Batal</button><button type="submit" class="bd-ix__btn bd-ix__btn--primary" data-dp-save>Salin sebagai draf</button></div>
    </form>
</dialog>
