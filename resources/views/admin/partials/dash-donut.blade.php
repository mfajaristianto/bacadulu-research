{{-- Donut SVG native untuk distribusi status akun. $segments: [['key','label','count','share'], ...], $total: int, $label: teks pembaca layar.
     Interaksi (hover, fokus, klik legenda) ada di resources/js/admin-dashboard.js lewat data-seg. --}}
@php
    $slices = collect($segments)->filter(fn ($segment) => $segment['count'] > 0)->values();
    // Celah antar irisan hanya bila ada lebih dari satu irisan; ujung butt (bukan bulat) agar irisan kecil tidak tampak lebih besar.
    $gap = $slices->count() > 1 ? 0.8 : 0;
    $cursor = 0;

    $arcs = $slices->map(function ($segment) use (&$cursor, $total, $gap) {
        $share = $segment['count'] / $total * 100;
        // Irisan sangat kecil diberi panjang minimum agar tetap terlihat; angka pastinya ada di legenda.
        $length = max($share - $gap, 0.8);
        $offset = $cursor + $gap / 2;
        $cursor += $share;

        return $segment + [
            'dash' => round($length, 3).' '.round(100 - $length, 3),
            'offset' => ($offset > 0 ? '-' : '').round($offset, 3),
        ];
    });

    $totalText = number_format($total, 0, ',', '.');
    // Angka panjang mengecil agar tetap muat di lubang donut (tanpa memotong atau menimpa cincin).
    $fit = match (true) {
        strlen($totalText) >= 12 => ' bd-dash__donut-total--xlong',
        strlen($totalText) >= 9 => ' bd-dash__donut-total--long',
        strlen($totalText) >= 7 => ' bd-dash__donut-total--mid',
        default => '',
    };
@endphp
<div class="bd-dash__donut" data-dash-donut data-total="{{ $totalText }}">
    <svg viewBox="0 0 36 36" role="group" aria-label="{{ $label }}" focusable="false">
        <circle class="bd-dash__donut-track" cx="18" cy="18" r="15.9155" pathLength="100" />
        @foreach ($arcs as $arc)
            <circle
                class="bd-dash__donut-seg bd-dash__donut-seg--{{ $arc['key'] }}"
                cx="18" cy="18" r="15.9155" pathLength="100"
                stroke-dasharray="{{ $arc['dash'] }}" stroke-dashoffset="{{ $arc['offset'] }}"
                data-seg="{{ $arc['key'] }}"
                data-seg-label="{{ $arc['label'] }}"
                data-seg-count="{{ number_format($arc['count'], 0, ',', '.') }}"
                data-seg-share="{{ $arc['share'] }}"
                tabindex="0"
                role="img"
                aria-label="{{ $arc['label'] }}: {{ number_format($arc['count'], 0, ',', '.') }} akun ({{ $arc['share'] }})"
            />
        @endforeach
    </svg>
    <span class="bd-dash__donut-total{{ $fit }}" aria-hidden="true">
        <b data-donut-value>{{ $totalText }}</b>
        <small data-donut-caption>total akun</small>
    </span>
    <span class="bd-dash__tip" data-donut-tip role="status" hidden></span>
</div>
