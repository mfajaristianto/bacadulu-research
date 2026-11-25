@php
    $earlier = $sections->filter(fn ($s) => $s->position < $selected->position && $s->id !== $selected->id);
    $dep = collect(old('depends_on', $selected->depends_on ?? []));
    $num = fn ($k) => old($k, $selected->{$k});
@endphp
<form method="POST" action="{{ route('admin.prompt-documents.sections.update', [$template->id, $selected->id]) }}" class="bd-ix__form" novalidate data-dp-form data-dp-dirty data-dp-kind="section">
    @csrf @method('PUT')
    <input type="hidden" name="tab" value="konfigurasi">
    <div class="bd-ix__fields">
        <div class="bd-ix__field">
            <label class="bd-ix__label" for="dp-c-title">Nama bagian <small>wajib</small></label>
            <input class="bd-ix__input" id="dp-c-title" name="title" maxlength="180" value="{{ old('title', $selected->title) }}" required data-dp-field>
            <p class="bd-ix__note" data-error-for="title"></p>
        </div>
        <div class="bd-ix__field">
            <label class="bd-ix__label" for="dp-c-desc">Deskripsi <small>opsional</small></label>
            <input class="bd-ix__input" id="dp-c-desc" name="description" maxlength="1000" value="{{ old('description', $selected->description) }}" data-dp-field>
            <p class="bd-ix__note" data-error-for="description"></p>
        </div>
        <div class="bd-ix__field">
            <label class="bd-ix__label" for="dp-c-purpose">Tujuan bagian <small>opsional</small></label>
            <textarea class="bd-ix__input bd-ix__textarea" id="dp-c-purpose" name="purpose" rows="2" maxlength="1000" data-dp-field>{{ old('purpose', $selected->purpose) }}</textarea>
            <p class="bd-ix__note" data-error-for="purpose"></p>
        </div>

        <div class="bd-ix__grid">
            <div class="bd-ix__field">
                <label class="bd-ix__label" for="dp-c-role">Peran bagian</label>
                <select class="bd-ix__input bd-ix__select" id="dp-c-role" name="role" data-dp-field>
                    @foreach ($roles as $value => $label)
                        <option value="{{ $value }}" @selected(old('role', $selected->role) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="bd-dp__toggles">
                <label class="bd-dp__check"><input type="hidden" name="is_required" value="0"><input type="checkbox" name="is_required" value="1" data-dp-field @checked(old('is_required', $selected->is_required))> Wajib</label>
                <label class="bd-dp__check"><input type="hidden" name="is_enabled" value="0"><input type="checkbox" name="is_enabled" value="1" data-dp-field @checked(old('is_enabled', $selected->is_enabled))> Aktif (ikut ditulis)</label>
            </div>
        </div>

        <fieldset class="bd-dp__fieldset">
            <legend>Jumlah kata</legend>
            <div class="bd-dp__grid3">
                @foreach (['min_words' => 'Minimum', 'max_words' => 'Maksimum', 'target_words' => 'Target'] as $key => $label)
                    <div class="bd-ix__field">
                        <label class="bd-ix__label" for="dp-c-{{ $key }}">{{ $label }}</label>
                        <input class="bd-ix__input" id="dp-c-{{ $key }}" name="{{ $key }}" type="number" inputmode="numeric" min="0" max="200000" step="1" value="{{ $num($key) }}" placeholder="Tanpa batas" data-dp-field>
                        <p class="bd-ix__note" data-error-for="{{ $key }}"></p>
                    </div>
                @endforeach
            </div>
            <p class="bd-ix__hint">Batas disimpan sebagai persyaratan. Hasil tulisan tetap perlu diperiksa terhadap batas ini; menyimpannya tidak menjamin AI memenuhinya.</p>
        </fieldset>

        <div class="bd-ix__grid">
            <div class="bd-ix__field">
                <label class="bd-ix__label" for="dp-c-items">Jumlah butir <small>mis. pertanyaan Q&amp;A</small></label>
                <input class="bd-ix__input" id="dp-c-items" name="item_count" type="number" inputmode="numeric" min="1" max="200" step="1" value="{{ $num('item_count') }}" placeholder="Tidak diatur" data-dp-field>
                <p class="bd-ix__note" data-error-for="item_count"></p>
            </div>
            <div class="bd-ix__field">
                <label class="bd-ix__label" for="dp-c-sources">Sumber dibutuhkan <small>minimal</small></label>
                <input class="bd-ix__input" id="dp-c-sources" name="min_sources" type="number" inputmode="numeric" min="0" max="500" step="1" value="{{ $num('min_sources') }}" placeholder="Tidak diatur" data-dp-field>
                <p class="bd-ix__note" data-error-for="min_sources"></p>
            </div>
        </div>

        <div class="bd-ix__field">
            <label class="bd-ix__label" for="dp-c-req">Persyaratan khusus <small>satu per baris</small></label>
            <textarea class="bd-ix__input bd-ix__textarea" id="dp-c-req" name="requirements" rows="4" maxlength="3000" data-dp-field>{{ old('requirements', $selected->requirements) }}</textarea>
            <p class="bd-ix__note" data-error-for="requirements"></p>
        </div>

        <fieldset class="bd-dp__fieldset">
            <legend>Bagian prasyarat</legend>
            @if ($earlier->isEmpty())
                <p class="bd-ix__muted">Belum ada bagian sebelum ini yang bisa menjadi prasyarat.</p>
            @else
                <p class="bd-ix__hint">Bagian ini memakai hasil tulisan bagian yang dipilih sebagai konteks ({{ '{previous_section}' }}). Hanya bagian yang berada lebih awal yang bisa dipilih.</p>
                <div class="bd-dp__checks">
                    @foreach ($earlier as $s)
                        <label class="bd-dp__check"><input type="checkbox" name="depends_on[]" value="{{ $s->key }}" data-dp-field @checked($dep->contains($s->key))> {{ $s->title }}</label>
                    @endforeach
                </div>
            @endif
            <p class="bd-ix__note" data-error-for="depends_on"></p>
        </fieldset>
    </div>
    <p class="bd-ix__alert" data-dp-error role="alert" hidden></p>
    <div class="bd-ix__actions">
        <button type="button" class="bd-ix__btn bd-ix__btn--quiet" data-dp-revert>Batalkan perubahan</button>
        <button type="submit" class="bd-ix__btn bd-ix__btn--primary" data-dp-save>Simpan konfigurasi</button>
    </div>
    <p class="bd-ix__muted">Mengubah konfigurasi tidak mengubah versi instruksi mana pun. Bagian tidak dihapus: nonaktifkan bila tidak dipakai, sehingga riwayat versinya tetap utuh.</p>
</form>
