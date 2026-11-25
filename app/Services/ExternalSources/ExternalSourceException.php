<?php

namespace App\Services\ExternalSources;

use RuntimeException;

/** Galat penyedia eksternal. $kind: rate_limited | auth | bad_request | failed. Pesan sudah aman ditampilkan (tanpa kunci). */
class ExternalSourceException extends RuntimeException
{
    public function __construct(string $message, public readonly string $kind = 'failed', public readonly int $retryAfter = 0)
    {
        parent::__construct($message);
    }

    public static function rateLimited(int $retryAfter): self
    {
        return new self('Penyedia membatasi laju permintaan (HTTP 429). Coba lagi dalam '.max(1, $retryAfter).' detik.', 'rate_limited', max(1, $retryAfter));
    }

    public static function auth(int $status): self
    {
        return new self("Penyedia menolak kredensial atau akses (HTTP {$status}). Periksa API key di Pengaturan.", 'auth');
    }
}
