<?php

namespace App\Services\Synthesis;

use RuntimeException;

/** Permintaan ditolak SEBELUM ada AiRun atau pemanggilan provider (anggaran, kuota, laju, permintaan ganda). */
final class SynthesisRejected extends RuntimeException
{
    public function __construct(public readonly string $state, public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}
