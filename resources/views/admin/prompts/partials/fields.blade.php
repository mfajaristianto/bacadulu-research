{{--
  Kolom isi sebuah versi. $prompt = versi yang ditampilkan (atau baru), $readonly = tampil baca saja.
  Satu daftar kolom (PromptController::FIELD_GROUPS) dipakai untuk mode baca, edit, dan buat baru.
--}}
@php
    $limits = ['system_prompt' => 20000, 'task_prompt' => 30000, 'retrieval_instructions' => 20000, 'output_instructions' => 20000];
    $mono = ['system_prompt', 'task_prompt', 'retrieval_instructions', 'output_instructions', 'question_template'];
    $rows = ['task_prompt' => 14, 'system_prompt' => 6, 'retrieval_instructions' => 6, 'output_instructions' => 6];
@endphp

@if ($readonly)
    @foreach ([['indonesian_name', 'Nama Indonesia'], ['short_description', 'Deskripsi singkat']] as [$key, $label])
        @if (filled($prompt->{$key}))
            <section class="bd-ix__read"><h3>{{ $label }}</h3><p class="bd-ix__read-text">{{ $prompt->{$key} }}</p></section>
        @endif
    @endforeach
    @foreach ($fieldGroups as $group => $fields)
        <div class="bd-ix__group">
            <h3 class="bd-ix__group-title">{{ $group }}</h3>
            @foreach ($fields as $key => [$label])
                <section class="bd-ix__read">
                    <h4>{{ $label }}</h4>
                    @if (filled($prompt->{$key}))
                        <pre class="bd-ix__prompt {{ in_array($key, $mono, true) ? '' : 'is-prose' }}" tabindex="0">{{ $prompt->{$key} }}</pre>
                    @else
                        <p class="bd-ix__muted">Tidak diisi.</p>
                    @endif
                </section>
            @endforeach
        </div>
    @endforeach
@else
    <div class="bd-ix__grid">
        <div class="bd-ix__field">
            <label class="bd-ix__label" for="ix-f-indonesian_name">Nama Indonesia <small>opsional</small></label>
            <input class="bd-ix__input" id="ix-f-indonesian_name" name="indonesian_name" maxlength="180" value="{{ old('indonesian_name', $prompt->indonesian_name) }}" data-ix-field>
            <p class="bd-ix__note" data-error-for="indonesian_name">{{ $errors->first('indonesian_name') }}</p>
        </div>
        <div class="bd-ix__field">
            <label class="bd-ix__label" for="ix-f-short_description">Deskripsi singkat <small>opsional</small></label>
            <input class="bd-ix__input" id="ix-f-short_description" name="short_description" maxlength="1000" value="{{ old('short_description', $prompt->short_description) }}" data-ix-field>
            <p class="bd-ix__note" data-error-for="short_description">{{ $errors->first('short_description') }}</p>
        </div>
    </div>

    @if (\Illuminate\Support\Facades\Schema::hasColumn('prompt_templates', 'input_mode'))
        <div class="bd-ix__field">
            <label class="bd-ix__label" for="ix-f-input_mode">Jenis input pengguna</label>
            <p class="bd-ix__hint" id="ix-h-input_mode">Standar: kategori, alat, dan satu topik. Riset buku: topik ditambah beberapa rumusan masalah yang diisi pengguna (tanpa AI).</p>
            <select class="bd-ix__input" id="ix-f-input_mode" name="input_mode" aria-describedby="ix-h-input_mode" data-ix-field>
                <option value="standard" @selected(old('input_mode', $prompt->input_mode ?? 'standard') !== 'book')>Riset biasa (satu topik)</option>
                <option value="book" @selected(old('input_mode', $prompt->input_mode ?? 'standard') === 'book')>Riset buku (topik + rumusan masalah)</option>
            </select>
        </div>
    @endif

    @foreach ($fieldGroups as $group => $fields)
        @php $secondary = $group !== 'Instruksi internal'; @endphp
        <details class="bd-ix__group" @if (! $secondary || collect($fields)->keys()->contains(fn ($k) => filled(old($k, $prompt->{$k})) || $errors->has($k))) open @endif>
            <summary class="bd-ix__group-title">{{ $group }}</summary>
            <div class="bd-ix__fields">
                @foreach ($fields as $key => [$label, $help])
                    @php $max = $limits[$key] ?? 5000; @endphp
                    <div class="bd-ix__field">
                        <label class="bd-ix__label" for="ix-f-{{ $key }}">{{ $label }}@if ($key === 'task_prompt') <small>wajib</small>@endif</label>
                        @if ($help)<p class="bd-ix__hint" id="ix-h-{{ $key }}">{{ $help }}</p>@endif
                        <textarea class="bd-ix__input bd-ix__textarea {{ in_array($key, $mono, true) ? 'is-mono' : '' }}" id="ix-f-{{ $key }}" name="{{ $key }}" rows="{{ $rows[$key] ?? 4 }}" maxlength="{{ $max }}" spellcheck="false" data-ix-field data-max="{{ $max }}" @if ($help) aria-describedby="ix-h-{{ $key }}" @endif>{{ old($key, $prompt->{$key}) }}</textarea>
                        <div class="bd-ix__under">
                            <p class="bd-ix__note" data-error-for="{{ $key }}">{{ $errors->first($key) }}</p>
                            <span class="bd-ix__count" data-ix-count="{{ $key }}" aria-hidden="true"></span>
                        </div>
                    </div>
                @endforeach
            </div>
        </details>
    @endforeach
@endif
