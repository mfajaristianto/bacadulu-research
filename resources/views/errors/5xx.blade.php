{{-- Cadangan untuk 5xx tanpa view khusus (502, 504, dst.). Pesan exception tidak pernah ditampilkan. --}}
@php
    $status = $exception->getStatusCode();

    try {
        $signedIn = auth()->check();
    } catch (\Throwable $e) {
        $signedIn = false;
    }
@endphp

@if (in_array($status, [502, 504], true))
    <x-error-page :code="$status" mascot="connection-error" heading="Koneksi ke layanan sedang terganggu." description="Coba lagi beberapa saat lagi." primary="retry" secondary="back" />
@else
    <x-error-page :code="$status" mascot="system-error" heading="Ada masalah di sistem BacaDulu." :description="$signedIn ? 'Coba muat ulang atau kembali ke Workspace.' : 'Coba muat ulang atau kembali ke beranda.'" primary="retry" secondary="home" />
@endif
