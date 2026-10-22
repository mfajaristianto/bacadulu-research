{{--
    Design system v2 button.

    <x-ui.button href="{{ route('home') }}">Kembali</x-ui.button>
    <x-ui.button type="submit" variant="secondary" size="sm" :loading="$busy">Simpan</x-ui.button>

    variant: primary | secondary | ghost | danger | light
    size:    md | sm | lg
    href:    renders <a>; otherwise <button>
--}}
@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'type' => 'button',
    'block' => false,
    'icon' => false,
    'loading' => false,
])

@php
    $classes = collect([
        'btn',
        'btn--'.$variant,
        $size !== 'md' ? 'btn--'.$size : null,
        $block ? 'btn--block' : null,
        $icon ? 'btn--icon' : null,
    ])->filter()->implode(' ');
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }} @if ($loading) aria-busy="true" @endif>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }} @if ($loading) aria-busy="true" @endif>{{ $slot }}</button>
@endif
