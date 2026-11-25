<?php

namespace App\Services\FullText;

use RuntimeException;

/** Galat pipeline full-text. `code` stabil (disimpan), `transient` = boleh dicoba lagi, `retryAfter` detik (Retry-After). */
class FullTextException extends RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message, public readonly bool $transient = false, public readonly ?int $retryAfter = null)
    {
        parent::__construct($message);
    }
}
