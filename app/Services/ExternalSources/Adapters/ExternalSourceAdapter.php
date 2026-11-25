<?php

namespace App\Services\ExternalSources\Adapters;

use App\Services\ExternalSources\ExternalPage;

/**
 * Kontrak seragam konektor. Adapter hanya tahu cara bicara dengan satu penyedia; penyimpanan, antrean, dan
 * dedup ada di service. Satu panggilan fetchPage memproses SATU istilah (service yang berganti istilah).
 */
interface ExternalSourceAdapter
{
    public function code(): string;

    /** Metadata statis untuk direktori konektor. */
    public function info(): array;

    /** Uji ringan (satu permintaan kecil). @return array{ok: bool, message: string} */
    public function ping(array $settings): array;

    /**
     * @param  array{terms?: array, year_from?: ?int, year_to?: ?int, language?: ?string, type?: ?string, open_access_only?: bool}  $filters
     * @param  array{api_key?: ?string, contact?: ?string}  $settings
     */
    public function fetchPage(string $term, array $filters, ?string $cursor, int $pageSize, array $settings): ExternalPage;
}
