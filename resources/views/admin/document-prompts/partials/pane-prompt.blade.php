@php
    $wibShort = fn ($date) => $date?->copy()->timezone('Asia/Jakarta')->locale('id')->translatedFormat('j M Y, H.i');
    $vlabel = fn ($v) => ['active' => 'Aktif', 'draft' => 'Draf', 'archived' => 'Diarsipkan'][$v->status] ?? $v->status;
    $vurl = fn ($v) => route('admin.prompt-documents.show', ['template' => $template->id, 'bagian' => $selected->key, 'v' => $v->id]);
    $fieldDefs = [
        'task_prompt' => ['Isi prompt', 'Instruksi utama untuk bagian ini. Wajib.', 16, 30000],
        'system_prompt' => ['Peran dan batasan', 'Peran AI dan batasan umum bagian ini. Opsional.', 5, 20000],
        'retrieval_instructions' => ['Penggunaan sumber', 'Cara memakai dan merujuk sumber Pustaka. Opsional.', 6, 20000],
        'output_instructions' => ['Format keluaran', 'Bentuk dan struktur hasil tulisan. Opsional.', 5, 20000],
    ];
    $active = $versions->firstWhere('id', $selected->active_prompt_template_id);
    $active = $active && $active->status === 'active' ? $active : null;
    $editable = $version && ! $lock;
    $used = $version ? collect($fieldDefs)->keys()->flatMap(fn ($k) => app(\App\Services\Research\DocumentPrompts::class)->placeholdersIn([$version->{$k}]))->unique()->values() : collect();
@endphp

@if ($versions->isEmpty())
    <div class="bd-dp__first">
        <p class="bd-ix__lock is-draft">Bagian ini belum punya instruksi. Tulis instruksinya; disimpan sebagai draf v1 dan belum aktif sampai Anda mengaktifkannya.</p>
        <form method="POST" action="{{ route('admin.prompt-documents.prompt.store', [$template->id, $selected->id]) }}" class="bd-ix__form" novalidate data-dp-form data-dp-dirty data-dp-kind="prompt">
            @csrf
            @include('admin.document-prompts.partials.prompt-fields', ['prompt' => null])
            <p class="bd-ix__alert" data-dp-error role="alert" hidden></p>
            <div class="bd-ix__actions"><button type="submit" class="bd-ix__btn bd-ix__btn--primary" data-dp-save>Simpan draf v1</button></div>
        </form>
    </div>
@else
    <div class="bd-dp__vbar">
        <ol class="bd-dp__vchips" aria-label="Versi instruksi {{ $selected->title }}">
            @foreach ($versions as $v)
                <li><a href="{{ $vurl($v) }}" data-dp-nav class="bd-dp__vchip bd-dp__vchip--{{ $v->status }} {{ $version && $v->id === $version->id ? 'is-on' : '' }}" @if ($version && $v->id === $version->id) aria-current="true" @endif>v{{ $v->version }} <span>{{ $vlabel($v) }}</span></a></li>
            @endforeach
        </ol>
        <a class="bd-ix__link" href="{{ route('admin.prompt-documents.show', ['template' => $template->id, 'bagian' => $selected->key, 'tab' => 'versi']) }}" data-dp-nav>Riwayat dan perbandingan</a>
    </div>

    @if ($version)
        <header class="bd-dp__vhead">
            <div>
                <h3 class="bd-ix__vtitle">v{{ $version->version }} <span class="bd-ix__vstate bd-ix__vstate--{{ $version->status }}">{{ $vlabel($version) }}</span></h3>
                <p class="bd-ix__muted">Diperbarui {{ $wibShort($version->updated_at) }}@if ($version->creator) · oleh {{ $version->creator->name }}@endif</p>
            </div>
            <div class="bd-ix__vactions">
                @if ($version->status === 'active')
                    <span class="bd-ix__active-tag">@include('admin.tools.partials.icon', ['name' => 'check']) Sedang aktif</span>
                @else
                    <form method="POST" action="{{ route('admin.prompt-documents.prompt.activate', [$template->id, $selected->id, $version->id]) }}" data-dp-form data-dp-kind="activate" data-dp-ask-title="Jadikan v{{ $version->version }} aktif?" data-dp-ask-text="{{ $active ? '“'.$selected->title.'”: v'.$active->version.' akan diarsipkan (tetap tersimpan) dan v'.$version->version.' menjadi versi yang dipakai.' : '“'.$selected->title.'” akan memakai v'.$version->version.'.' }} Bagian lain tidak berubah." data-dp-ask-ok="Jadikan aktif">
                        @csrf
                        <button type="submit" class="bd-ix__btn bd-ix__btn--primary" data-dp-activate>Jadikan Aktif</button>
                    </form>
                @endif
                <form method="POST" action="{{ route('admin.prompt-documents.prompt.revise', [$template->id, $selected->id, $version->id]) }}" data-dp-form data-dp-ask-title="Buat revisi dari v{{ $version->version }}?" data-dp-ask-text="Draf baru dibuat dari v{{ $version->version }}. Versi aktif tidak berubah." data-dp-ask-ok="Buat revisi">
                    @csrf
                    <button type="submit" class="bd-ix__btn" data-dp-revise>Buat revisi</button>
                </form>
                @if ($version->status === 'draft')
                    <form method="POST" action="{{ route('admin.prompt-documents.prompt.archive', [$template->id, $selected->id, $version->id]) }}" data-dp-form data-dp-ask-title="Arsipkan draf v{{ $version->version }}?" data-dp-ask-text="Isinya tetap tersimpan dan bisa dijadikan aktif atau direvisi lagi nanti." data-dp-ask-ok="Arsipkan draf">
                        @csrf
                        <button type="submit" class="bd-ix__btn bd-ix__btn--quiet">Arsipkan draf</button>
                    </form>
                @endif
            </div>
        </header>

        @if ($lock)
            <p class="bd-ix__lock">@include('admin.tools.partials.icon', ['name' => 'lock']) {{ $lock }}</p>
        @else
            <p class="bd-ix__lock is-draft">Draf ini belum dipakai pekerjaan mana pun. Perubahan disimpan sebagai draf dan tidak mengubah versi aktif.</p>
        @endif

        @if ($editable)
            <form method="POST" action="{{ route('admin.prompt-documents.prompt.update', [$template->id, $selected->id, $version->id]) }}" class="bd-ix__form" novalidate data-dp-form data-dp-dirty data-dp-kind="prompt">
                @csrf @method('PUT')
                @include('admin.document-prompts.partials.prompt-fields', ['prompt' => $version])
                <p class="bd-ix__alert" data-dp-error role="alert" hidden></p>
                <div class="bd-ix__actions">
                    <button type="button" class="bd-ix__btn bd-ix__btn--quiet" data-dp-revert>Batalkan perubahan</button>
                    <button type="submit" class="bd-ix__btn bd-ix__btn--primary" data-dp-save>Simpan draf</button>
                </div>
            </form>
        @else
            <div class="bd-ix__reader">
                @foreach ($fieldDefs as $key => [$label])
                    <section class="bd-ix__read">
                        <h4>{{ $label }}</h4>
                        @if (filled($version->{$key}))
                            <pre class="bd-ix__prompt" tabindex="0">{{ $version->{$key} }}</pre>
                        @else
                            <p class="bd-ix__muted">Tidak diisi.</p>
                        @endif
                    </section>
                @endforeach
                @if ($used->isNotEmpty())
                    <p class="bd-dp__chips">Variabel dipakai: @foreach ($used as $name) <code>{{ '{'.$name.'}' }}</code> @endforeach</p>
                @endif
            </div>
        @endif
    @endif
@endif
