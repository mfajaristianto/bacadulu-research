<?php

namespace App\Services\Research\Sync;

interface SourceConnector
{
    public function code(): string;

    /**
     * @return array<int, array{
     *     external_id: string,
     *     title: string,
     *     checksum: string|null,
     *     updated_at: string|null,
     *     metadata: array<string, mixed>
     * }>
     */
    public function discover(): array;

    /**
     * @return array{
     *     external_id: string,
     *     title: string,
     *     checksum: string,
     *     updated_at: string|null,
     *     extracted_text: string,
     *     metadata: array<string, mixed>
     * }
     */
    public function fetch(
        string $externalId
    ): array;
}