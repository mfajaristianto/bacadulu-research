@php $sizeLabel = fn ($l) => \App\Services\Documents\PrintLayout::SIZES[$l['page']['size']]['label'].($l['page']['size'] === 'custom' ? ' '.$l['page']['width'].'×'.$l['page']['height'].' mm' : ''); @endphp
<div class="bd-pf__new">
    <section aria-labelledby="pf-new-preset">
        <h2 id="pf-new-preset" class="bd-ix__side-title">Mulai dari preset</h2>
        <p class="bd-ix__muted">Preset hanya titik awal. Dibuat sebagai draf v1 dan semua pengaturannya dapat diperiksa dan diubah di editor; tidak ada yang terkunci dan tidak ada yang aktif sendiri.</p>
        <ul class="bd-ix__list">
            @foreach ($presets as $key => $p)
                <li class="bd-ix__item bd-pf__preset">
                    <div class="bd-ix__text">
                        <strong class="bd-ix__title">{{ $p['name'] }}</strong>
                        <span class="bd-ix__desc">{{ $p['description'] }}</span>
                        <span class="bd-ix__sub">{{ $sizeLabel($p['layout']) }} · {{ $p['document_type'] }} · {{ $categories[$p['layout']['category']] }}</span>
                    </div>
                    <form method="POST" action="{{ route('admin.document-templates.store') }}" class="bd-pf__inline" novalidate data-dp-form>
                        @csrf
                        <input type="hidden" name="source" value="preset">
                        <input type="hidden" name="preset" value="{{ $key }}">
                        <label class="bd-ix__sr" for="pf-preset-{{ $key }}">Nama template untuk {{ $p['name'] }}</label>
                        <input class="bd-ix__input" id="pf-preset-{{ $key }}" name="name" value="{{ $p['name'] }}" maxlength="180" required data-dp-field>
                        <button type="submit" class="bd-ix__btn bd-ix__btn--small" aria-label="Buat template dari preset {{ $p['name'] }}">Buat draf</button>
                        <p class="bd-ix__note" data-error-for="name"></p>
                        <p class="bd-ix__note" data-error-for="preset"></p>
                    </form>
                </li>
            @endforeach
        </ul>
    </section>

    <div class="bd-pf__new-side">
        <form method="POST" action="{{ route('admin.document-templates.store') }}" class="bd-ix__form" novalidate data-dp-form>
            @csrf
            <input type="hidden" name="source" value="blank">
            <h2 class="bd-ix__side-title">Buat dari awal</h2>
            <div class="bd-ix__fields">
                <div class="bd-ix__field">
                    <label class="bd-ix__label" for="pf-blank-name">Nama template <small>wajib</small></label>
                    <input class="bd-ix__input" id="pf-blank-name" name="name" maxlength="180" required data-dp-field>
                    <p class="bd-ix__note" data-error-for="name"></p>
                </div>
                <div class="bd-ix__field">
                    <label class="bd-ix__label" for="pf-blank-type">Jenis dokumen</label>
                    <select class="bd-ix__input bd-ix__select" id="pf-blank-type" name="document_type" data-dp-field>
                        @foreach ($types as $type)<option value="{{ $type }}">{{ $type }}</option>@endforeach
                    </select>
                    <p class="bd-ix__note" data-error-for="document_type"></p>
                </div>
                <p class="bd-ix__hint">Nilai awal: A4, margin 25,4 mm, Times 12 pt, spasi 1,5. Ubah semuanya di editor.</p>
            </div>
            <p class="bd-ix__alert" data-dp-error role="alert" hidden></p>
            <div class="bd-ix__actions"><button type="submit" class="bd-ix__btn bd-ix__btn--primary" data-dp-save>Buat sebagai draf</button></div>
        </form>

        @if ($existing->isNotEmpty())
            <form method="POST" action="{{ route('admin.document-templates.store') }}" class="bd-ix__form" novalidate data-dp-form>
                @csrf
                <input type="hidden" name="source" value="copy">
                <h2 class="bd-ix__side-title">Salin template yang ada</h2>
                <div class="bd-ix__fields">
                    <div class="bd-ix__field">
                        <label class="bd-ix__label" for="pf-copy-from">Template sumber</label>
                        <select class="bd-ix__input bd-ix__select" id="pf-copy-from" name="copy_from" data-dp-field>
                            @foreach ($existing as $t)<option value="{{ $t->id }}">{{ $t->name }} ({{ $t->document_type }})</option>@endforeach
                        </select>
                        <p class="bd-ix__note" data-error-for="copy_from"></p>
                    </div>
                    <div class="bd-ix__field">
                        <label class="bd-ix__label" for="pf-copy-name">Nama salinan <small>wajib</small></label>
                        <input class="bd-ix__input" id="pf-copy-name" name="name" maxlength="180" required data-dp-field>
                        <p class="bd-ix__note" data-error-for="name"></p>
                    </div>
                    <p class="bd-ix__hint">Salinan memakai layout versi aktif (atau terbaru) sebagai draf v1 dan tidak berbagi versi dengan sumbernya.</p>
                </div>
                <p class="bd-ix__alert" data-dp-error role="alert" hidden></p>
                <div class="bd-ix__actions"><button type="submit" class="bd-ix__btn" data-dp-save>Salin sebagai draf</button></div>
            </form>
        @endif
    </div>
</div>
