{{-- Cadangan untuk 4xx tanpa view khusus (mis. 401, 405, 422). Pesan exception tidak pernah ditampilkan. --}}
@php
    $status = $exception->getStatusCode();
@endphp

@if ($status === 422)
    <x-error-page code="422" mascot="input-error" heading="Ada bagian yang perlu diperiksa lagi." description="Periksa data yang kamu masukkan lalu coba kembali." primary="back" secondary="home" />
@elseif ($status === 401)
    <x-error-page code="401" mascot="locked" heading="Kamu perlu masuk dulu." description="Masuk untuk melanjutkan." primary="login" secondary="beranda" />
@else
    <x-error-page :code="$status" mascot="confused" heading="Permintaan ini tidak bisa diproses." description="Coba kembali ke halaman sebelumnya." primary="back" secondary="home" />
@endif
