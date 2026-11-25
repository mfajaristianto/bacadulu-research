{{-- Isian template. $t = template (atau null untuk baru), $p = prefiks id unik di halaman. --}}
@php
    $val = fn (string $k, $d = '') => old($k, $t?->{$k} ?? $d);
@endphp
<div class="bd-ix__fields">
    <div class="bd-ix__field">
        <label class="bd-ix__label" for="{{ $p }}-name">Nama template <small>wajib</small></label>
        <input class="bd-ix__input" id="{{ $p }}-name" name="name" maxlength="180" value="{{ $val('name') }}" required data-dp-field>
        <p class="bd-ix__note" data-error-for="name">{{ $errors->first('name') }}</p>
    </div>
    <div class="bd-ix__grid">
        <div class="bd-ix__field">
            <label class="bd-ix__label" for="{{ $p }}-type">Jenis dokumen</label>
            <select class="bd-ix__input bd-ix__select" id="{{ $p }}-type" name="document_type" data-dp-field data-dp-type>
                @foreach ($types as $value => $label)
                    <option value="{{ $value }}" @selected($val('document_type', 'book_chapter') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <p class="bd-ix__note" data-error-for="document_type">{{ $errors->first('document_type') }}</p>
        </div>
        <div class="bd-ix__field" data-dp-type-label @if ($val('document_type', 'book_chapter') !== 'other') hidden @endif>
            <label class="bd-ix__label" for="{{ $p }}-type-label">Nama jenis dokumen</label>
            <input class="bd-ix__input" id="{{ $p }}-type-label" name="type_label" maxlength="80" value="{{ $val('type_label') }}" placeholder="mis. Skripsi" data-dp-field>
            <p class="bd-ix__note" data-error-for="type_label">{{ $errors->first('type_label') }}</p>
        </div>
    </div>
    <div class="bd-ix__field">
        <label class="bd-ix__label" for="{{ $p }}-desc">Deskripsi <small>opsional</small></label>
        <textarea class="bd-ix__input bd-ix__textarea" id="{{ $p }}-desc" name="description" rows="3" maxlength="2000" data-dp-field>{{ $val('description') }}</textarea>
        <p class="bd-ix__note" data-error-for="description">{{ $errors->first('description') }}</p>
    </div>
    <div class="bd-ix__grid bd-dp__grid3">
        <div class="bd-ix__field">
            <label class="bd-ix__label" for="{{ $p }}-lang">Bahasa naskah</label>
            <select class="bd-ix__input bd-ix__select" id="{{ $p }}-lang" name="language" data-dp-field>
                @foreach ($languages as $value => $label)
                    <option value="{{ $value }}" @selected($val('language', 'id') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="bd-ix__field">
            <label class="bd-ix__label" for="{{ $p }}-style">Gaya sitasi</label>
            <select class="bd-ix__input bd-ix__select" id="{{ $p }}-style" name="citation_style" data-dp-field>
                @foreach ($styles as $style)
                    <option value="{{ $style }}" @selected($val('citation_style', 'APA 7') === $style)>{{ $style }}</option>
                @endforeach
            </select>
        </div>
        <div class="bd-ix__field">
            <label class="bd-ix__label" for="{{ $p }}-qa">Pertanyaan dan jawaban</label>
            <select class="bd-ix__input bd-ix__select" id="{{ $p }}-qa" name="qa_mode" data-dp-field aria-describedby="{{ $p }}-qa-h">
                @foreach ($qaModes as $value => $label)
                    <option value="{{ $value }}" @selected($val('qa_mode', 'none') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <p class="bd-ix__hint" id="{{ $p }}-qa-h">Penempatan Q&amp;A diatur per template: tidak otomatis ditambahkan ke semua buku atau artikel.</p>
</div>
