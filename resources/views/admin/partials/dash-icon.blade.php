{{-- Ikon garis 24px untuk Beranda admin. $name memilih path; ukuran diatur CSS pada wrapper. --}}
@php
    $icons = [
        'prompt' => 'M5 5h14v10H9l-4 4V5Z M9 9h6 M9 12h4',
        'users' => 'M16 19v-1.5a3.5 3.5 0 0 0-3.5-3.5h-3A3.5 3.5 0 0 0 6 17.5V19 M11 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z M18 8.5a2.5 2.5 0 0 1 0 4.5 M20 19v-1a3 3 0 0 0-2-2.8',
        'library' => 'M5 4h4v16H5z M11 4h4v16h-4z M17.5 5.5l3 .8-3.5 13.7-3-.8',
        'chart' => 'M5 20V10 M12 20V4 M19 20v-7',
        'shield' => 'M12 3l7 3v5c0 4.5-3 8-7 10-4-2-7-5.5-7-10V6l7-3Z M9 12l2.2 2.2L15.5 10',
        'file' => 'M7 3h7l4 4v14H7z M14 3v4h4 M10 12h5 M10 16h5',
        'book' => 'M5 5c3-1 5-1 7 1v14c-2-2-4-2-7-1V5Z M19 5c-3-1-5-1-7 1v14c2-2 4-2 7-1V5Z',
        'sync' => 'M4 12a8 8 0 0 1 13.7-5.6L20 9 M20 4v5h-5 M20 12a8 8 0 0 1-13.7 5.6L4 15 M4 20v-5h5',
        'alert' => 'M12 4 3 19h18L12 4Z M12 10v4 M12 17h.01',
        'info' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z M12 11v5 M12 8h.01',
        'check' => 'M5 12.5l4.5 4.5L19 7.5',
        'user' => 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z M5 20a7 7 0 0 1 14 0',
        'search' => 'M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14Z M20 20l-4-4',
        'archive' => 'M4 5h16v4H4z M6 9v10h12V9 M10 13h4',
        'settings' => 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z M19 12l2-1-2-3-2.2.6-1.2-1L15 5h-4l-.6 2.6-1.2 1L7 8 5 11l2 1v1l-2 1 2 3 2.2-.6 1.2 1L11 19h4l.4-2.6 1.2-1 2.2.6 2-3-2-1v-1Z',
        'arrow' => 'M5 12h14 M13 6l6 6-6 6',
        'plus' => 'M12 5v14M5 12h14',
    ];
@endphp
<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="{{ $icons[$name] ?? $icons['info'] }}" /></svg>
