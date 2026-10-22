{{--
    Design system v2 form field: label + control + hint + validation error.

    <x-ui.field name="title" label="Judul penelitian" required hint="10–220 karakter" />
    <x-ui.field name="context" label="Konteks" as="textarea" rows="4" />
    <x-ui.field name="category_id" label="Bidang" as="select">
        <option value="1">Ekonomi</option>
    </x-ui.field>

    The error message is read from the validation error bag for `name`
    (dot notation supported, e.g. name="questions[0]" error="questions.0").
--}}
@props([
    'name',
    'label',
    'as' => 'input',
    'type' => 'text',
    'id' => null,
    'value' => null,
    'hint' => null,
    'error' => null,
    'required' => false,
])

@php
    $id ??= 'field-'.\Illuminate\Support\Str::slug(str_replace(['[', ']'], '-', $name));
    $errorKey = $error ?? str_replace(['[', ']'], ['.', ''], $name);
    $message = $errors->first($errorKey);
    $describedBy = collect([
        $hint ? $id.'-hint' : null,
        $message ? $id.'-error' : null,
    ])->filter()->implode(' ');
    $controlAttributes = $attributes->class('field__control')->merge(array_filter([
        'id' => $id,
        'name' => $name,
        'required' => $required,
        'aria-invalid' => $message ? 'true' : null,
        'aria-describedby' => $describedBy ?: null,
    ]));
@endphp

<div class="field">
    <label class="field__label" for="{{ $id }}">
        {{ $label }}@if ($required)<span class="field__required" aria-hidden="true">*</span>@endif
    </label>

    @if ($as === 'textarea')
        <textarea {{ $controlAttributes }}>{{ old($name, $value) }}</textarea>
    @elseif ($as === 'select')
        <select {{ $controlAttributes }}>{{ $slot }}</select>
    @else
        <input type="{{ $type }}" value="{{ $type === 'password' ? '' : old($name, $value) }}" {{ $controlAttributes }}>
    @endif

    @if ($hint)
        <p class="field__hint" id="{{ $id }}-hint">{{ $hint }}</p>
    @endif

    @if ($message)
        <p class="field__error" id="{{ $id }}-error">{{ $message }}</p>
    @endif
</div>
