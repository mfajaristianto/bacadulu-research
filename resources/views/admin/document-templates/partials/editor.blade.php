@php
    $tabUrl = fn ($t) => route('admin.document-templates.edit', array_filter(['documentTemplate' => $template->id, 'tab' => $t !== 'format' ? $t : null, 'v' => $selected['v'] ?? null]));
    $wibShort = fn ($iso) => $iso ? \Illuminate\Support\Carbon::parse($iso)->timezone('Asia/Jakarta')->locale('id')->translatedFormat('j M Y, H.i') : null;
@endphp
<div class="bd-dp__pane bd-pf__pane" id="dp-pane" tabindex="-1" data-dp-editor data-pf data-pf-preview-url="{{ $previewUrl }}" data-pf-version="{{ $selected['v'] ?? '' }}">
    <div class="bd-dp__tabs" role="tablist" aria-label="Bagian editor">
        <a href="{{ $tabUrl('format') }}" role="tab" class="bd-dp__tab {{ $tab === 'format' ? 'is-on' : '' }}" aria-selected="{{ $tab === 'format' ? 'true' : 'false' }}" data-dp-nav>Format</a>
        <a href="{{ $tabUrl('versi') }}" role="tab" class="bd-dp__tab {{ $tab === 'versi' ? 'is-on' : '' }}" aria-selected="{{ $tab === 'versi' ? 'true' : 'false' }}" data-dp-nav>Riwayat versi</a>
    </div>

    @if ($tab === 'versi')
        @include('admin.document-templates.partials.versions')
    @elseif (! $selected)
        {{-- Template lama: tetap berfungsi dengan format bawaan generator sampai admin memilih mengatur format cetak. --}}
        <section class="bd-pf__legacy">
            <h2 class="bd-ix__side-title">Format lama</h2>
            <p class="bd-ix__lock">@include('admin.tools.partials.icon', ['name' => 'lock']) Template ini memakai format bawaan generator (A4, margin 1 inci, tanpa header, footer, nomor halaman, atau daftar isi). Dokumen yang dibuat darinya tidak berubah.</p>
            <dl class="bd-ab__dl">
                @foreach ([['Ukuran huruf judul', 'title_font_size', 'pt'], ['Ukuran huruf heading', 'heading_font_size', 'pt'], ['Ukuran huruf isi', 'body_font_size', 'pt'], ['Ukuran huruf daftar pustaka', 'reference_font_size', 'pt'], ['Spasi baris', 'line_spacing', '×'], ['Indentasi baris pertama', 'first_line_indent_cm', 'cm']] as [$label, $key, $unitLabel])
                    @if (isset($legacyFormat[$key]))<div><dt>{{ $label }}</dt><dd>{{ $legacyFormat[$key] }} {{ $unitLabel }}</dd></div>@endif
                @endforeach
                <div><dt>Bagian</dt><dd>{{ count($sections) }} bagian pemetaan hasil riset (tidak diubah di sini)</dd></div>
            </dl>
            <form method="POST" action="{{ route('admin.document-templates.adopt', $template) }}" data-dp-form data-dp-ask-title="Mulai mengatur format cetak?" data-dp-ask-text="Dibuat draf v1 dari nilai format lama. Template tetap memakai format lama sampai v1 Anda aktifkan." data-dp-ask-ok="Buat draf v1">
                @csrf
                <button type="submit" class="bd-ix__btn bd-ix__btn--primary">Mulai atur format cetak</button>
            </form>
        </section>
    @else
        @php
            $editable = ! $lock;
            $unit = $layout['unit'];
            $mm = fn (string $path) => (float) data_get($layout, $path);
            $len = fn (string $path) => \App\Services\Documents\PrintLayout::fromMm($mm($path), $unit);
        @endphp

        <div class="bd-dp__vbar">
            <ol class="bd-dp__vchips" aria-label="Versi format {{ $template->name }}">
                @foreach ($versions as $v)
                    <li><a href="{{ route('admin.document-templates.edit', ['documentTemplate' => $template->id, 'v' => $v['v']]) }}" data-dp-nav class="bd-dp__vchip bd-dp__vchip--{{ $v['status'] }} {{ $v['v'] === $selected['v'] ? 'is-on' : '' }}" @if ($v['v'] === $selected['v']) aria-current="true" @endif>v{{ $v['v'] }} <span>{{ ['active' => 'Aktif', 'draft' => 'Draf', 'archived' => 'Diarsipkan'][$v['status']] }}</span></a></li>
                @endforeach
            </ol>
            <a class="bd-ix__link" href="{{ $tabUrl('versi') }}" data-dp-nav>Riwayat dan perbandingan</a>
        </div>

        <header class="bd-dp__vhead">
            <div>
                <h3 class="bd-ix__vtitle">v{{ $selected['v'] }} <span class="bd-ix__vstate bd-ix__vstate--{{ $selected['status'] }}">{{ ['active' => 'Aktif', 'draft' => 'Draf', 'archived' => 'Diarsipkan'][$selected['status']] }}</span></h3>
                <p class="bd-ix__muted">Dibuat {{ $wibShort($selected['created_at']) }}@if ($selected['note']) · {{ $selected['note'] }}@endif</p>
            </div>
            <div class="bd-ix__vactions">
                @if ($selected['status'] === 'active' && $template->status === 'active')
                    <span class="bd-ix__active-tag">@include('admin.tools.partials.icon', ['name' => 'check']) Sedang aktif</span>
                @else
                    <form method="POST" action="{{ route('admin.document-templates.activate', [$template, $selected['v']]) }}" data-dp-form data-dp-kind="activate" data-dp-ask-title="Jadikan v{{ $selected['v'] }} aktif?" data-dp-ask-text="{{ $selected['status'] === 'active' ? 'Template diaktifkan kembali.' : (collect($versions)->firstWhere('status', 'active') ? 'v'.collect($versions)->firstWhere('status', 'active')['v'].' diarsipkan (tetap tersimpan) dan v'.$selected['v'].' dipakai.' : 'Template mulai dipakai generator.') }} Berlaku untuk dokumen baru; dokumen yang sudah dibuat tidak berubah." data-dp-ask-ok="Jadikan aktif">
                        @csrf
                        <button type="submit" class="bd-ix__btn bd-ix__btn--primary" data-dp-activate>Jadikan Aktif</button>
                    </form>
                @endif
                <form method="POST" action="{{ route('admin.document-templates.revise', [$template, $selected['v']]) }}" data-dp-form data-dp-ask-title="Buat revisi dari v{{ $selected['v'] }}?" data-dp-ask-text="Draf baru dibuat dari v{{ $selected['v'] }}. Versi aktif tidak berubah." data-dp-ask-ok="Buat revisi">
                    @csrf
                    <button type="submit" class="bd-ix__btn" data-dp-revise>Buat Revisi</button>
                </form>
                @if ($selected['status'] === 'draft')
                    <form method="POST" action="{{ route('admin.document-templates.archive-version', [$template, $selected['v']]) }}" data-dp-form data-dp-ask-title="Arsipkan draf v{{ $selected['v'] }}?" data-dp-ask-text="Isinya tetap tersimpan dan bisa dijadikan aktif atau direvisi lagi nanti." data-dp-ask-ok="Arsipkan draf">
                        @csrf
                        <button type="submit" class="bd-ix__btn bd-ix__btn--quiet">Arsipkan draf</button>
                    </form>
                @endif
            </div>
        </header>

        @if ($lock)
            <p class="bd-ix__lock">@include('admin.tools.partials.icon', ['name' => 'lock']) {{ $lock }}</p>
        @else
            <p class="bd-ix__lock is-draft">Draf ini belum dipakai dokumen mana pun. Perubahan disimpan sebagai draf dan tidak mengubah versi aktif.</p>
        @endif

        <div class="bd-pf__workspace">
            <form method="POST" action="{{ route('admin.document-templates.update', $template) }}" class="bd-ix__form bd-pf__form" novalidate data-dp-form data-dp-dirty data-dp-kind="prompt" data-pf-form>
                @csrf @method('PUT')
                <input type="hidden" name="v" value="{{ $selected['v'] }}">
                <fieldset class="bd-pf__fieldset" @disabled(! $editable)>
                    <legend class="bd-ix__sr">Pengaturan format v{{ $selected['v'] }}</legend>
                    @include('admin.document-templates.partials.fields')
                </fieldset>
                @if ($editable)
                    <p class="bd-ix__alert" data-dp-error role="alert" hidden></p>
                    <div class="bd-ix__actions bd-pf__savebar">
                        <button type="button" class="bd-ix__btn bd-ix__btn--quiet" data-dp-revert>Batalkan perubahan</button>
                        <button type="submit" class="bd-ix__btn bd-ix__btn--primary" data-dp-save>Simpan draf</button>
                    </div>
                @endif
            </form>

            @include('admin.document-templates.partials.preview')
        </div>
    @endif
</div>
