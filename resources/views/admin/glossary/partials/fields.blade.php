{{--
    Field Istilah, dipakai dialog (index) dan halaman form biasa. $term, $categories, $modal (true: nilai diisi admin-glossary.js).
    Alias: dengan JavaScript berupa chip (aliases[]); tanpa JavaScript tetap satu kolom teks dipisah koma/baris.
--}}
@php
    $v = fn (string $key, $default = '') => $modal ? '' : old($key, $term->{$key} ?? $default);
    $suffix = $modal ? '-m' : '';
    $aliasText = $modal ? '' : old('aliases', implode(', ', (array) ($term->aliases_json ?? [])));
    $aliasText = is_array($aliasText) ? implode(', ', $aliasText) : $aliasText;
@endphp
<div class="bd-gl__field">
    <label class="bd-gl__label" for="gl-term{{ $suffix }}">Istilah</label>
    <input class="bd-gl__input" id="gl-term{{ $suffix }}" name="term" type="text" maxlength="180" required autocomplete="off" value="{{ $v('term') }}" placeholder="Mis. Environmental, Social, and Governance" aria-describedby="gl-term-note{{ $suffix }}" data-gl-term @if ($errors->has('term')) aria-invalid="true" @endif>
    <p class="bd-gl__note" id="gl-term-note{{ $suffix }}" data-error-for="term" role="status">{{ $errors->first('term') }}</p>
</div>

<div class="bd-gl__field">
    <label class="bd-gl__label" for="gl-definition{{ $suffix }}">Definisi</label>
    <textarea class="bd-gl__input bd-gl__textarea" id="gl-definition{{ $suffix }}" name="definition" rows="4" maxlength="5000" required placeholder="Jelaskan istilah ini secara singkat dan netral" aria-describedby="gl-definition-note{{ $suffix }}" data-gl-definition @if ($errors->has('definition')) aria-invalid="true" @endif>{{ $v('definition') }}</textarea>
    <p class="bd-gl__note" id="gl-definition-note{{ $suffix }}" data-error-for="definition" role="status">{{ $errors->first('definition') }}</p>
</div>

<div class="bd-gl__field">
    <label class="bd-gl__label" for="gl-alias{{ $suffix }}">Alias atau sinonim <small>opsional</small></label>
    @if ($modal)
        <div class="bd-gl__chipbox" data-gl-chipbox>
            <ul class="bd-gl__chips" data-gl-chips aria-label="Alias yang ditambahkan"></ul>
            <input class="bd-gl__chip-input" id="gl-alias{{ $suffix }}" type="text" maxlength="120" autocomplete="off" placeholder="Ketik alias lalu tekan Enter" aria-describedby="gl-alias-note{{ $suffix }}" data-gl-alias>
        </div>
    @else
        <textarea class="bd-gl__input bd-gl__textarea" id="gl-alias{{ $suffix }}" name="aliases" rows="2" placeholder="Pisahkan dengan koma atau baris baru" aria-describedby="gl-alias-note{{ $suffix }}">{{ $aliasText }}</textarea>
    @endif
    <p class="bd-gl__hint">Enter atau koma menambah alias; Backspace pada kolom kosong menghapus yang terakhir. Alias yang sama dengan istilah lain ditolak.</p>
    <p class="bd-gl__note" id="gl-alias-note{{ $suffix }}" data-error-for="aliases" role="status">{{ $errors->first('aliases') }}</p>
</div>

<div class="bd-gl__row2">
    <div class="bd-gl__field">
        <label class="bd-gl__label" for="gl-category{{ $suffix }}">Berlaku untuk</label>
        <select class="bd-gl__select" id="gl-category{{ $suffix }}" name="category_id" data-gl-category aria-describedby="gl-category-note{{ $suffix }}">
            <option value="">Semua kategori (umum)</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected(! $modal && (string) old('category_id', $term->category_id) === (string) $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
        <p class="bd-gl__note" id="gl-category-note{{ $suffix }}" data-error-for="category_id" role="status">{{ $errors->first('category_id') }}</p>
    </div>
    <div class="bd-gl__field">
        <label class="bd-gl__label" for="gl-status{{ $suffix }}">Status</label>
        <select class="bd-gl__select" id="gl-status{{ $suffix }}" name="status" required data-gl-status>
            <option value="active" @selected(! $modal && old('status', $term->status ?: 'active') === 'active')>Aktif</option>
            <option value="inactive" @selected(! $modal && old('status', $term->status) === 'inactive')>Nonaktif</option>
        </select>
        <p class="bd-gl__note" data-error-for="status" role="status">{{ $errors->first('status') }}</p>
    </div>
</div>

<div class="bd-gl__field">
    <label class="bd-gl__label" for="gl-note{{ $suffix }}">Catatan konteks <small>opsional</small></label>
    <textarea class="bd-gl__input bd-gl__textarea" id="gl-note{{ $suffix }}" name="context_note" rows="2" maxlength="5000" placeholder="Batasan pemakaian, mis. berlaku untuk akuntansi keuangan" data-gl-note>{{ $v('context_note') }}</textarea>
    <p class="bd-gl__hint">Istilah aktif dikirim ke AI sebagai konteks terminologi. Glosarium bukan sumber sitasi.</p>
    <p class="bd-gl__note" data-error-for="context_note" role="status">{{ $errors->first('context_note') }}</p>
</div>
