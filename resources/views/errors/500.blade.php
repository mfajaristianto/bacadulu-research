@php
    try {
        $signedIn = auth()->check();
    } catch (\Throwable $e) {
        $signedIn = false;
    }
@endphp
<x-error-page code="500" mascot="system-error" heading="Ada masalah di sistem BacaDulu." :description="$signedIn ? 'Coba muat ulang atau kembali ke Workspace.' : 'Coba muat ulang atau kembali ke beranda.'" primary="retry" secondary="home" />
