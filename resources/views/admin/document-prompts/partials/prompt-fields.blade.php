{{-- Isian prompt satu versi. $prompt = versi (atau null untuk v1 baru). --}}
@php
    $defs = [
        'task_prompt' => ['Isi prompt', 'Instruksi utama untuk bagian ini. Wajib.', 16, 30000],
        'system_prompt' => ['Peran dan batasan', 'Peran AI dan batasan umum bagian ini. Opsional.', 5, 20000],
        'retrieval_instructions' => ['Penggunaan sumber', 'Cara memakai dan merujuk sumber Pustaka. Opsional.', 6, 20000],
        'output_instructions' => ['Format keluaran', 'Bentuk dan struktur hasil tulisan. Opsional.', 5, 20000],
    ];
@endphp
<div class="bd-dp__vars" aria-label="Variabel dinamis">
    <p class="bd-ix__hint" id="dp-vars-h">Variabel dinamis: klik untuk menyisipkan di posisi kursor. Hanya variabel ini yang dikenali.</p>
    <div class="bd-dp__chips" role="group" aria-describedby="dp-vars-h">
        @foreach ($placeholders as $name => [$label, $meaning])
            <button type="button" class="bd-dp__chip" data-dp-insert="{{ $name }}" title="{{ $label }}: {{ $meaning }}">{{ '{'.$name.'}' }}</button>
        @endforeach
    </div>
</div>
<div class="bd-ix__fields">
    @foreach ($defs as $key => [$label, $help, $rows, $max])
        @php $open = $key === 'task_prompt' || filled(old($key, $prompt?->{$key})); @endphp
        <details class="bd-ix__group" @if ($open) open @endif>
            <summary class="bd-ix__group-title">{{ $label }}@if ($key === 'task_prompt') <small>wajib</small>@endif</summary>
            <div class="bd-ix__field">
                <p class="bd-ix__hint" id="dp-h-{{ $key }}">{{ $help }}</p>
                <label class="bd-ix__sr" for="dp-f-{{ $key }}">{{ $label }}</label>
                <textarea class="bd-ix__input bd-ix__textarea is-mono" id="dp-f-{{ $key }}" name="{{ $key }}" rows="{{ $rows }}" maxlength="{{ $max }}" spellcheck="false" data-dp-field data-dp-text data-max="{{ $max }}" aria-describedby="dp-h-{{ $key }}">{{ old($key, $prompt?->{$key}) }}</textarea>
                <div class="bd-ix__under">
                    <p class="bd-ix__note" data-error-for="{{ $key }}"></p>
                    <span class="bd-ix__count" data-dp-count="{{ $key }}" aria-hidden="true"></span>
                </div>
            </div>
        </details>
    @endforeach
</div>
