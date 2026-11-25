@php
    $tabs = ['prompt' => 'Instruksi', 'konfigurasi' => 'Konfigurasi', 'versi' => 'Versi'];
    $sectionUrl = fn ($s, $t = null) => route('admin.prompt-documents.show', array_filter(['template' => $template->id, 'bagian' => $s->key, 'tab' => $t && $t !== 'prompt' ? $t : null]));
    $limits = fn ($s) => collect([$s->min_words ? 'min '.number_format($s->min_words, 0, ',', '.') : null, $s->max_words ? 'maks '.number_format($s->max_words, 0, ',', '.') : null])->filter()->implode(' · ');
@endphp
<div class="bd-dp__workspace" data-dp-editor data-template="{{ $template->id }}">
    <nav class="bd-dp__sections" aria-labelledby="dp-sections-title">
        <div class="bd-dp__sections-head">
            <h2 id="dp-sections-title" class="bd-ix__side-title">Bagian dokumen</h2>
            <button type="button" class="bd-ix__btn bd-ix__btn--small" data-dp-open="add">@include('admin.tools.partials.icon', ['name' => 'plus']) Tambah</button>
        </div>
        @if ($sections->isEmpty())
            <p class="bd-ix__muted">Belum ada bagian. Tambahkan bagian pertama, mis. Pendahuluan, lalu tulis instruksinya.</p>
        @else
            <ol class="bd-dp__section-list">
                @foreach ($sections as $i => $s)
                    @php $info = $sectionInfo[$s->id]; @endphp
                    <li class="bd-dp__section {{ $selected && $selected->id === $s->id ? 'is-selected' : '' }} {{ $s->is_enabled ? '' : 'is-off' }}">
                        <a href="{{ $sectionUrl($s, $tab) }}" data-dp-nav @if ($selected && $selected->id === $s->id) aria-current="true" @endif>
                            <span class="bd-dp__num">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>
                            <span class="bd-dp__section-text">
                                <strong>{{ $s->title }}</strong>
                                <span class="bd-ix__sub">
                                    @if (! $s->is_enabled) Nonaktif @else {{ $s->is_required ? 'Wajib' : 'Opsional' }} @endif
                                    @if ($limits($s)) · {{ $limits($s) }} kata @endif
                                </span>
                                <span class="bd-dp__ver {{ $info['active'] ? 'is-active' : ($info['draft'] ? 'is-draft' : 'is-none') }}">
                                    @if ($info['active']) v{{ $info['active']->version }} aktif @if ($info['draft']) · draf v{{ $info['draft']->version }} @endif
                                    @elseif ($info['draft']) Draf v{{ $info['draft']->version }}, belum aktif
                                    @else Belum ada instruksi @endif
                                </span>
                            </span>
                        </a>
                        @if ($sections->count() > 1)
                            <div class="bd-dp__move">
                                @foreach (['up' => 'Naikkan', 'down' => 'Turunkan'] as $dir => $verb)
                                    <form method="POST" action="{{ route('admin.prompt-documents.sections.move', [$template->id, $s->id]) }}" data-dp-form data-dp-quiet>
                                        @csrf <input type="hidden" name="direction" value="{{ $dir }}"><input type="hidden" name="tab" value="{{ $tab }}">
                                        <button type="submit" class="bd-ix__icon-btn bd-dp__move-btn bd-dp__move-btn--{{ $dir }}" aria-label="{{ $verb }} {{ $s->title }}" @disabled(($dir === 'up' && $i === 0) || ($dir === 'down' && $i === $sections->count() - 1))>@include('admin.tools.partials.icon', ['name' => 'chevron-down'])</button>
                                    </form>
                                @endforeach
                            </div>
                        @endif
                    </li>
                @endforeach
            </ol>
        @endif
    </nav>

    <div class="bd-dp__pane" id="dp-pane" tabindex="-1">
        @if (! $selected)
            <div class="bd-ix__empty">
                <h2>Template belum punya bagian</h2>
                <p>Bagian menentukan apa yang ditulis AI dan dalam urutan apa. Tambahkan satu per satu, atau salin template yang sudah ada.</p>
                <button type="button" class="bd-ix__btn bd-ix__btn--primary" data-dp-open="add">@include('admin.tools.partials.icon', ['name' => 'plus']) Tambah bagian pertama</button>
            </div>
        @else
            <header class="bd-dp__pane-head">
                <div>
                    <h2>{{ $selected->title }}</h2>
                    @if ($selected->description)<p class="bd-ix__muted">{{ $selected->description }}</p>@endif
                </div>
            </header>
            <div class="bd-dp__tabs" role="tablist" aria-label="Pengaturan bagian">
                @foreach ($tabs as $key => $label)
                    <a href="{{ $sectionUrl($selected, $key) }}" role="tab" class="bd-dp__tab {{ $tab === $key ? 'is-on' : '' }}" aria-selected="{{ $tab === $key ? 'true' : 'false' }}" data-dp-nav>{{ $label }}</a>
                @endforeach
            </div>
            @include('admin.document-prompts.partials.pane-'.$tab)
        @endif
    </div>
</div>

{{-- Dialog: satu tingkat saja, tidak ada dialog di dalam dialog. --}}
<dialog class="bd-ix__dialog" data-dp-dialog="settings" aria-labelledby="dp-settings-title">
    <form method="POST" action="{{ route('admin.prompt-documents.update', $template->id) }}" novalidate data-dp-form data-dp-dirty data-dp-kind="settings" data-dp-close-on-success>
        @csrf @method('PUT')
        <input type="hidden" name="bagian" value="{{ $selected?->key }}">
        <div class="bd-ix__dialog-head"><h2 id="dp-settings-title">Pengaturan template</h2><button type="button" class="bd-ix__icon-btn" data-dp-close aria-label="Tutup">@include('admin.tools.partials.icon', ['name' => 'x'])</button></div>
        @php $p = 'dp-set'; $t = $template; @endphp
        @include('admin.document-prompts.partials.template-fields')
        <p class="bd-ix__alert" data-dp-error role="alert" hidden></p>
        <div class="bd-ix__dialog-actions"><button type="button" class="bd-ix__btn" data-dp-close>Batal</button><button type="submit" class="bd-ix__btn bd-ix__btn--primary" data-dp-save>Simpan pengaturan</button></div>
    </form>
</dialog>

<dialog class="bd-ix__dialog bd-ix__dialog--small" data-dp-dialog="add" aria-labelledby="dp-add-title">
    <form method="POST" action="{{ route('admin.prompt-documents.sections.store', $template->id) }}" novalidate data-dp-form data-dp-kind="section-add" data-dp-close-on-success>
        @csrf
        <div class="bd-ix__dialog-head"><h2 id="dp-add-title">Tambah bagian</h2><button type="button" class="bd-ix__icon-btn" data-dp-close aria-label="Tutup">@include('admin.tools.partials.icon', ['name' => 'x'])</button></div>
        <div class="bd-ix__fields">
            <div class="bd-ix__field">
                <label class="bd-ix__label" for="dp-add-title-input">Nama bagian <small>wajib</small></label>
                <input class="bd-ix__input" id="dp-add-title-input" name="title" maxlength="180" required data-dp-field>
                <p class="bd-ix__note" data-error-for="title"></p>
            </div>
            <div class="bd-ix__field">
                <label class="bd-ix__label" for="dp-add-role">Peran</label>
                <select class="bd-ix__input bd-ix__select" id="dp-add-role" name="role" data-dp-field>@foreach ($roles as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>
            </div>
            <label class="bd-dp__check"><input type="hidden" name="is_required" value="0"><input type="checkbox" name="is_required" value="1" checked> Bagian wajib</label>
            <p class="bd-ix__hint">Ditambahkan di urutan terakhir. Batas kata, prasyarat, dan instruksinya diatur setelah dibuat.</p>
        </div>
        <p class="bd-ix__alert" data-dp-error role="alert" hidden></p>
        <div class="bd-ix__dialog-actions"><button type="button" class="bd-ix__btn" data-dp-close>Batal</button><button type="submit" class="bd-ix__btn bd-ix__btn--primary" data-dp-save>Tambah bagian</button></div>
    </form>
</dialog>

<dialog class="bd-ix__dialog bd-dp__preview" data-dp-dialog="preview" aria-labelledby="dp-preview-title">
    @include('admin.document-prompts.partials.preview')
</dialog>
