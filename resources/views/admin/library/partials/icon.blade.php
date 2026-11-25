{{-- Ikon garis 24px untuk Pustaka. $name memilih path; ukuran diatur CSS pada pembungkus. --}}
@php
    $paths = [
        'search' => 'M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14Z M20 20l-4-4',
        'eye' => 'M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z',
        'download' => 'M12 4v11 M7.5 11l4.5 4.5 4.5-4.5 M5 20h14',
        'chevron-down' => 'm6 9 6 6 6-6',
        'plus' => 'M12 5v14M5 12h14',
        'arrow' => 'M5 12h14 M13 6l6 6-6 6',
        'upload' => 'M12 16V5 M7.5 9.5L12 5l4.5 4.5 M5 19h14',
        'x' => 'M6 6l12 12M18 6L6 18',
        'file' => 'M7 3h7l4 4v14H7z M14 3v4h4',
        'external' => 'M14 4h6v6 M20 4l-9 9 M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5',
    ];
@endphp
<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="{{ $paths[$name] ?? $paths['file'] }}" /></svg>
