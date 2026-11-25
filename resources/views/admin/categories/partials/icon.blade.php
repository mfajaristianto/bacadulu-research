{{-- Ikon garis 24px untuk Kategori. $name memilih path; ukuran diatur CSS pada pembungkus. --}}
@php
    $paths = [
        'search' => 'M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14Z M20 20l-4-4',
        'plus' => 'M12 5v14M5 12h14',
        'chevron-down' => 'm6 9 6 6 6-6',
        'x' => 'M6 6l12 12M18 6L6 18',
        'arrow' => 'M5 12h14 M13 6l6 6-6 6',
    ];
@endphp
<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="{{ $paths[$name] ?? $paths['plus'] }}" /></svg>
