{{--
    Field alat riset, dipakai dialog (index) dan halaman form biasa. $tool, $modal (true: nilai diisi admin-tools.js).
    Slug (code) pada alat yang sudah ada dikunci: dialog memakai tombol "Ubah slug" + konfirmasi, halaman biasa memakai kotak centang.
--}}
@php
    $v = fn (string $key, $default = '') => $modal ? '' : old($key, $tool->{$key} ?? $default);
    $s = $modal ? '-m' : '';
    $editing = ! $modal && $tool->exists;
@endphp
<div class="bd-tl__field">
    <label class="bd-tl__label" for="tl-name{{ $s }}">Nama alat</label>
    <input class="bd-tl__input" id="tl-name{{ $s }}" name="name" type="text" maxlength="180" required autocomplete="off" value="{{ $v('name') }}" placeholder="Mis. Research Gap" aria-describedby="tl-name-note{{ $s }}" data-tl-name @if ($errors->has('name')) aria-invalid="true" @endif>
    <p class="bd-tl__note" id="tl-name-note{{ $s }}" data-error-for="name" role="status">{{ $errors->first('name') }}</p>
</div>

<div class="bd-tl__field">
    <label class="bd-tl__label" for="tl-code{{ $s }}">Slug <small>identitas sistem</small></label>
    <div class="bd-tl__codebox">
        <input class="bd-tl__input bd-tl__input--code" id="tl-code{{ $s }}" name="code" type="text" maxlength="120" required autocomplete="off" spellcheck="false" value="{{ $v('code') }}" placeholder="research-gap" aria-describedby="tl-code-note{{ $s }} tl-code-hint{{ $s }}" data-tl-code @if ($errors->has('code')) aria-invalid="true" @endif>
        @if ($modal)
            <button type="button" class="bd-tl__btn bd-tl__btn--small" data-tl-unlock hidden>Ubah slug</button>
        @endif
    </div>
    <p class="bd-tl__hint" id="tl-code-hint{{ $s }}" data-tl-code-hint>
        @if ($modal)
            Dibuat dari nama; huruf kecil, angka, dan tanda hubung.
        @elseif ($editing)
            Dipakai logika riset dan keluaran. Jangan ubah kecuali perlu.
        @else
            Huruf kecil, angka, dan tanda hubung. Tidak bisa diubah sembarangan setelah dibuat.
        @endif
    </p>
    @if ($editing)
        <label class="bd-tl__confirm"><input type="checkbox" name="confirm_code_change" value="1" @checked(old('confirm_code_change'))> Saya mengerti, ubah slug alat ini</label>
    @endif
    <p class="bd-tl__note" id="tl-code-note{{ $s }}" data-error-for="code" role="status">{{ $errors->first('code') }}</p>
</div>

<div class="bd-tl__field">
    <label class="bd-tl__label" for="tl-description{{ $s }}">Deskripsi <small>opsional</small></label>
    <textarea class="bd-tl__input bd-tl__textarea" id="tl-description{{ $s }}" name="description" rows="3" maxlength="2000" placeholder="Pekerjaan riset yang dicakup alat ini" data-tl-description>{{ $v('description') }}</textarea>
    <p class="bd-tl__note" data-error-for="description" role="status">{{ $errors->first('description') }}</p>
</div>

<div class="bd-tl__field">
    <label class="bd-tl__label" for="tl-status{{ $s }}">Status</label>
    <select class="bd-tl__select" id="tl-status{{ $s }}" name="status" required aria-describedby="tl-status-hint{{ $s }}" data-tl-status>
        <option value="active" @selected(! $modal && old('status', $tool->status ?: 'active') === 'active')>Aktif</option>
        <option value="archived" @selected(! $modal && old('status', $tool->status) === 'archived')>Diarsipkan</option>
    </select>
    <p class="bd-tl__hint" id="tl-status-hint{{ $s }}" data-tl-status-hint>Alat diarsipkan tidak tersedia untuk pengguna. Instruksi dan versinya tetap tersimpan.</p>
    <p class="bd-tl__note" data-error-for="status" role="status">{{ $errors->first('status') }}</p>
</div>

@unless ($modal)
    <input type="hidden" name="icon" value="{{ old('icon', $tool->icon) }}">
@endunless
