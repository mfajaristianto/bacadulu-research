@php
    $field = $item['field'];
    $fmt = function ($v) {
        if (is_float($v) || (is_string($v) && str_contains($v, '.') && is_numeric($v))) {
            return rtrim(rtrim(number_format((float) $v, 4, '.', ''), '0'), '.') ?: '0';
        }

        return (string) $v;
    };
    $value = $fmt($item['value']);
    $default = $fmt($item['default']);
    $err = 'st-err-'.$field;
    $hint = 'st-hint-'.$field;
    $control = $item['control'];
@endphp
<div class="bd-st__row {{ $item['changed'] ? 'is-changed' : '' }}" data-st-row="{{ $field }}" data-st-label="{{ $item['label'] }}" data-st-keywords="{{ mb_strtolower($item['label'].' '.$item['help'].' '.$item['used'].' '.$item['key']) }}">
    <div class="bd-st__row-copy">
        <label class="bd-st__label" @if ($control !== 'segmented') for="st-f-{{ $field }}" @endif id="st-l-{{ $field }}">{{ $item['label'] }}</label>
        <p class="bd-st__desc" id="{{ $hint }}">{{ $item['help'] }}</p>
        <p class="bd-st__meta">
            <span class="bd-st__src" data-st-src>{{ $item['changed'] ? 'Diubah admin' : 'Bawaan server' }}</span>
            <span>Bawaan: <strong>{{ $default === '' ? '—' : $default }}{{ ($item['unit'] ?? '') !== '' ? ' '.$item['unit'] : '' }}</strong></span>
            <span>Dipakai oleh: {{ $item['used'] }}</span>
            @if ($item['updatedAt'])<span>Terakhir disimpan {{ $item['updatedAt']->locale('id')->translatedFormat('j M Y, H.i') }} WIB{{ '' }}@if ($item['updatedBy']) oleh {{ $item['updatedBy'] }}@endif</span>@endif
        </p>
    </div>

    <div class="bd-st__ctl">
        @if ($control === 'range')
            <div class="bd-st__rangewrap">
                <input type="range" class="bd-st__range" id="st-f-{{ $field }}" data-st-range="{{ $field }}" min="{{ $item['min'] }}" max="{{ $item['max'] }}" step="{{ $item['step'] }}" value="{{ $value }}" aria-describedby="{{ $hint }} {{ $err }}" aria-labelledby="st-l-{{ $field }}" style="--pct: {{ $item['max'] > $item['min'] ? round(((float) $value - $item['min']) / ($item['max'] - $item['min']) * 100, 2) : 0 }}%">
                <span class="bd-st__numwrap"><input type="number" class="bd-ix__input bd-st__num" name="{{ $field }}" data-st-input data-default="{{ $default }}" data-st-numfor="{{ $field }}" min="{{ $item['min'] }}" max="{{ $item['max'] }}" step="{{ $item['step'] }}" value="{{ $value }}" inputmode="decimal" aria-label="{{ $item['label'] }} (angka)" aria-describedby="{{ $hint }} {{ $err }}">@if (($item['unit'] ?? '') !== '')<span class="bd-st__unit">{{ $item['unit'] }}</span>@endif</span>
            </div>
            <p class="bd-st__rangeends" aria-hidden="true"><span>{{ $item['min'] }}</span><span>{{ $item['max'] }}</span></p>
        @elseif ($control === 'number')
            <span class="bd-st__numwrap"><input type="number" class="bd-ix__input bd-st__num" id="st-f-{{ $field }}" name="{{ $field }}" data-st-input data-default="{{ $default }}" min="{{ $item['min'] }}" max="{{ $item['max'] }}" step="{{ $item['step'] }}" value="{{ $value }}" inputmode="numeric" aria-describedby="{{ $hint }} {{ $err }}">@if (($item['unit'] ?? '') !== '')<span class="bd-st__unit">{{ $item['unit'] }}</span>@endif</span>
            @if ($field === 'upload_limit_kb')<p class="bd-st__aside" data-st-kb-hint>≈ {{ number_format((float) $value / 1024, 0, ',', '.') }} MB</p>@endif
        @elseif ($control === 'select')
            <select class="bd-ix__select" id="st-f-{{ $field }}" name="{{ $field }}" data-st-input data-default="{{ $default }}" aria-describedby="{{ $hint }} {{ $err }}">
                @foreach ($item['choices'] as $choice)<option value="{{ $choice }}" @selected($choice === $value)>{{ $choice }}</option>@endforeach
                @unless (in_array($value, $item['choices'], true))<option value="{{ $value }}" selected>{{ $value }} (tidak ada dalam daftar)</option>@endunless
            </select>
        @elseif ($control === 'segmented')
            <fieldset class="bd-st__seg" aria-labelledby="st-l-{{ $field }}" aria-describedby="{{ $hint }} {{ $err }}">
                @foreach ($item['choices'] as $i => $choice)
                    <label class="bd-st__seg-opt"><input type="radio" name="{{ $field }}" value="{{ $choice }}" data-st-input data-default="{{ $default }}" @checked($choice === $value)><span>{{ $choice }}</span></label>
                @endforeach
            </fieldset>
        @else
            <input type="text" class="bd-ix__input" id="st-f-{{ $field }}" name="{{ $field }}" data-st-input data-default="{{ $default }}" maxlength="{{ $item['maxlength'] ?? 80 }}" value="{{ $value }}" autocomplete="off" spellcheck="false" aria-describedby="{{ $hint }} {{ $err }}">
        @endif

        <p class="bd-st__error" id="{{ $err }}" data-st-error role="alert" hidden></p>
        <button type="button" class="bd-st__reset" data-st-reset="{{ $field }}" @if ($value === $default) hidden @endif>Pulihkan bawaan ({{ $default }})</button>
    </div>
</div>
