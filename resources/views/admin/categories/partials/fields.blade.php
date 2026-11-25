{{--
    Field Kategori, dipakai modal (index) dan halaman form biasa. $category, $parents (semua kategori), $blocked (id yang tidak boleh
    jadi induk), $modal (true: nilai diisi admin-categories.js, bukan old()).
--}}
@php
    $value = fn (string $key, $default = '') => $modal ? '' : old($key, $category->{$key} ?? $default);
@endphp
<div class="bd-cat__field">
    <label class="bd-cat__label" for="cat-name{{ $modal ? '-m' : '' }}">Nama kategori</label>
    <input class="bd-cat__input" id="cat-name{{ $modal ? '-m' : '' }}" name="name" type="text" maxlength="180" required autocomplete="off" value="{{ $value('name') }}" placeholder="Mis. Akuntansi Keuangan" aria-describedby="cat-name-note{{ $modal ? '-m' : '' }}" data-cat-name @if ($errors->has('name')) aria-invalid="true" @endif>
    <p class="bd-cat__note" id="cat-name-note{{ $modal ? '-m' : '' }}" data-error-for="name" role="status">{{ $errors->first('name') }}</p>
</div>

<div class="bd-cat__field">
    <label class="bd-cat__label" for="cat-slug{{ $modal ? '-m' : '' }}">Slug <small>tautan pendek</small></label>
    <input class="bd-cat__input" id="cat-slug{{ $modal ? '-m' : '' }}" name="slug" type="text" maxlength="190" autocomplete="off" spellcheck="false" value="{{ $value('slug') }}" placeholder="dibuat dari nama" aria-describedby="cat-slug-note{{ $modal ? '-m' : '' }}" data-cat-slug @if ($errors->has('slug')) aria-invalid="true" @endif>
    <p class="bd-cat__note" id="cat-slug-note{{ $modal ? '-m' : '' }}" data-error-for="slug" role="status">{{ $errors->first('slug') }}</p>
    <p class="bd-cat__hint" data-cat-slug-hint>
        @if ($modal)
            <span data-cat-slug-text></span>
            <button type="button" class="bd-cat__link" data-cat-slug-suggest hidden>Pakai saran</button>
        @else
            {{ $category->exists ? 'Slug lama dipertahankan saat nama berubah. Ubah hanya jika perlu; tautan yang memakainya ikut berubah.' : 'Kosongkan agar dibuat otomatis dari nama.' }}
        @endif
    </p>
</div>

<div class="bd-cat__field">
    <label class="bd-cat__label" for="cat-parent{{ $modal ? '-m' : '' }}">Kategori induk <small>opsional</small></label>
    <select class="bd-cat__select" id="cat-parent{{ $modal ? '-m' : '' }}" name="parent_id" aria-describedby="cat-parent-note{{ $modal ? '-m' : '' }}" data-cat-parent @if ($errors->has('parent_id')) aria-invalid="true" @endif>
        <option value="">Tidak ada (kategori utama)</option>
        @foreach ($parents as $parent)
            @continue(! $modal && ($parent->id === $category->id || in_array($parent->id, $blocked, true)))
            @continue(! $modal && $parent->status !== 'active' && $parent->id !== $category->parent_id)
            <option value="{{ $parent->id }}" data-status="{{ $parent->status }}" @selected(! $modal && (string) old('parent_id', $category->parent_id) === (string) $parent->id)>{{ $parent->name }}{{ $parent->status !== 'active' ? ' (diarsipkan)' : '' }}</option>
        @endforeach
    </select>
    <p class="bd-cat__note" id="cat-parent-note{{ $modal ? '-m' : '' }}" data-error-for="parent_id" role="status">{{ $errors->first('parent_id') }}</p>
</div>

<div class="bd-cat__field">
    <label class="bd-cat__label" for="cat-status{{ $modal ? '-m' : '' }}">Status</label>
    <select class="bd-cat__select" id="cat-status{{ $modal ? '-m' : '' }}" name="status" required aria-describedby="cat-status-note{{ $modal ? '-m' : '' }}" data-cat-status>
        <option value="active" @selected(! $modal && old('status', $category->status ?: 'active') === 'active')>Aktif</option>
        <option value="archived" @selected(! $modal && old('status', $category->status) === 'archived')>Diarsipkan</option>
    </select>
    <p class="bd-cat__hint" id="cat-status-note{{ $modal ? '-m' : '' }}" data-cat-status-hint>Kategori diarsipkan tidak muncul sebagai pilihan baru. Sumber yang sudah memakainya tetap aman.</p>
    <p class="bd-cat__note" data-error-for="status" role="status">{{ $errors->first('status') }}</p>
</div>
