{{-- Ikon garis 24px untuk Impor Sumber. $name memilih path; ukuran diatur CSS pada pembungkus. --}}
@php
    $paths = [
        'search' => 'M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14Z M20 20l-4-4',
        'refresh' => 'M20 11a8 8 0 0 0-14.5-3.5L4 9 M4 4v5h5 M4 13a8 8 0 0 0 14.5 3.5L20 15 M20 20v-5h-5',
        'chevron-down' => 'm6 9 6 6 6-6',
        'x' => 'M6 6l12 12M18 6L6 18',
        'external' => 'M14 4h6v6 M20 4l-9 9 M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5',
        'check' => 'm5 12.5 4.5 4.5L19 7.5',
    ];
@endphp
<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="{{ $paths[$name] ?? $paths['check'] }}" /></svg>
