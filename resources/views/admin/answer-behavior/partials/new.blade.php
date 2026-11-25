@php $fieldNames = ['title' => 'Nama aturan']; @endphp
<div class="bd-ab__new">
    <form method="POST" action="{{ route('admin.answer-behavior.store') }}" class="bd-ix__form" novalidate data-dp-form data-dp-dirty>
        @csrf
        <h2 class="bd-ix__side-title">Mulai dari kosong</h2>
        <div class="bd-ix__fields">
            <div class="bd-ix__field">
                <label class="bd-ix__label" for="ab-new-title">Nama aturan <small>wajib</small></label>
                <input class="bd-ix__input" id="ab-new-title" name="title" maxlength="120" required data-dp-field>
                <p class="bd-ix__note" data-error-for="title"></p>
            </div>
            <div class="bd-ix__field">
                <label class="bd-ix__label" for="ab-new-desc">Deskripsi <small>opsional</small></label>
                <textarea class="bd-ix__input bd-ix__textarea" id="ab-new-desc" name="description" rows="2" maxlength="400" data-dp-field></textarea>
                <p class="bd-ix__note" data-error-for="description"></p>
            </div>
            <div class="bd-ix__field">
                <label class="bd-ix__label" for="ab-new-group">Kelompok perilaku</label>
                <select class="bd-ix__input bd-ix__select" id="ab-new-group" name="group" data-dp-field aria-describedby="ab-new-group-h">
                    @foreach ($groups as $value => $g)
                        <option value="{{ $value }}">{{ $g['label'] }}</option>
                    @endforeach
                </select>
                <p class="bd-ix__note" data-error-for="group"></p>
                <ul class="bd-ab__hints" id="ab-new-group-h">
                    @foreach ($groups as $g)
                        <li><strong>{{ $g['label'] }}</strong> — {{ $g['hint'] }}</li>
                    @endforeach
                </ul>
            </div>
            <div class="bd-ix__grid">
                <div class="bd-ix__field">
                    <label class="bd-ix__label" for="ab-new-scope">Cakupan</label>
                    <select class="bd-ix__input bd-ix__select" id="ab-new-scope" name="scope" data-dp-field>
                        @foreach ($scopes as $value => $label)
                            <option value="{{ $value }}" @selected($value === 'research')>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="bd-ix__note" data-error-for="scope"></p>
                </div>
                <div class="bd-ix__field">
                    <label class="bd-ix__label" for="ab-new-tool">Alat <small>hanya untuk cakupan alat tertentu</small></label>
                    <select class="bd-ix__input bd-ix__select" id="ab-new-tool" name="tool_code" data-dp-field>
                        <option value="">—</option>
                        @foreach ($tools as $code => $name)
                            <option value="{{ $code }}">{{ $name }}</option>
                        @endforeach
                    </select>
                    <p class="bd-ix__note" data-error-for="tool_code"></p>
                </div>
            </div>
            <p class="bd-ix__hint">Kelompok tidak dapat diubah setelah aturan dibuat. Isi dan pengaturannya diisi di editor, tersimpan sebagai draf v1.</p>
        </div>
        <p class="bd-ix__alert" data-dp-error role="alert" hidden></p>
        <div class="bd-ix__actions"><button type="submit" class="bd-ix__btn bd-ix__btn--primary" data-dp-save>Buat sebagai draf</button></div>
    </form>

    <section class="bd-ab__presets" aria-labelledby="ab-presets">
        <h2 id="ab-presets" class="bd-ix__side-title">Atau pasang preset awal sebagai draf</h2>
        <p class="bd-ix__muted">Preset hanya titik mulai yang netral topik. Dipasang sebagai draf v1 dan tidak pernah aktif sendiri; baca dan ubah dulu di editor.</p>
        <ul class="bd-ix__list">
            @foreach ($presets as $p)
                @php $done = in_array(mb_strtolower($p['title']), $installed, true); $toolMissing = $p['scope'] === 'tool' && ! array_key_exists((string) $p['tool_code'], $tools); @endphp
                <li class="bd-ix__item bd-ab__preset">
                    <div class="bd-ix__text">
                        <strong class="bd-ix__title">{{ $p['title'] }}</strong>
                        <span class="bd-ix__desc">{{ $p['description'] }}</span>
                        <span class="bd-ix__sub">{{ $groups[$p['group']]['label'] }} · {{ $scopes[$p['scope']] }}@if ($p['tool_code']) · {{ $tools[$p['tool_code']] ?? $p['tool_code'] }}@endif</span>
                    </div>
                    @if ($done)
                        <span class="bd-ix__muted">Sudah ada</span>
                    @elseif ($toolMissing)
                        <span class="bd-ix__muted">Alat tidak tersedia</span>
                    @else
                        <form method="POST" action="{{ route('admin.answer-behavior.preset', $p['key']) }}" data-dp-form>
                            @csrf
                            <button type="submit" class="bd-ix__btn bd-ix__btn--small" aria-label="Pasang preset {{ $p['title'] }} sebagai draf">Pasang sebagai draf</button>
                        </form>
                    @endif
                </li>
            @endforeach
        </ul>
    </section>
</div>
