{{--
    Design system v2 status badge. Always carries a text label, never colour alone.

    <x-ui.badge tone="success">Terbit</x-ui.badge>

    tone: neutral | info | success | warning | danger
--}}
@props(['tone' => 'neutral'])

<span {{ $attributes->class(['badge', 'badge--'.$tone]) }}>{{ $slot }}</span>
