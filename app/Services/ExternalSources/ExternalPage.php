<?php

namespace App\Services\ExternalSources;

/**
 * Satu halaman hasil: rekaman baku (Normalize::finish), kursor berikutnya (null = habis), total menurut penyedia bila ada,
 * jumlah permintaan HTTP yang dipakai (untuk anggaran), dan jumlah rekaman yang dilewati penyedia (mis. OAI "deleted").
 */
final class ExternalPage
{
    /** @param  array<int, array<string, mixed>>  $records */
    public function __construct(
        public readonly array $records,
        public readonly ?string $nextCursor,
        public readonly ?int $total = null,
        public readonly int $requests = 1,
        public readonly int $skipped = 0,
    ) {}
}
