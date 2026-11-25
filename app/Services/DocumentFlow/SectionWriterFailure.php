<?php

namespace App\Services\DocumentFlow;

use RuntimeException;

/** Kegagalan penulis bagian. `state` menentukan apakah penyedia berikutnya boleh mengambil alih bagian yang sama. */
final class SectionWriterFailure extends RuntimeException
{
    /** Keadaan yang boleh dilimpahkan ke penyedia berikutnya. 'invalid' = keluaran tidak lolos validasi sitasi/kosong. */
    public const FAILOVER_STATES = ['rate_limit', 'quota', 'timeout', 'unavailable', 'invalid'];

    public function __construct(public readonly string $state, string $message = '')
    {
        parent::__construct($message !== '' ? $message : "Penulis bagian gagal: {$state}");
    }

    public function failsOver(): bool
    {
        return in_array($this->state, self::FAILOVER_STATES, true);
    }
}
