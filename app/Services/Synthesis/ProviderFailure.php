<?php

namespace App\Services\Synthesis;

use RuntimeException;

/** Kegagalan provider yang sudah diklasifikasi. getMessage() = pesan AMAN tetap; galat mentah provider tidak pernah dibawa. */
final class ProviderFailure extends RuntimeException
{
    /** @param array<string, mixed> $meta usage/durasi/percobaan yang aman dicatat */
    public function __construct(
        public readonly string $state,
        public readonly bool $retryable = false,
        public readonly ?int $httpStatus = null,
        public readonly ?string $providerCode = null,
        public readonly array $meta = [],
    ) {
        parent::__construct(SynthesisFailure::message($state));
    }
}
