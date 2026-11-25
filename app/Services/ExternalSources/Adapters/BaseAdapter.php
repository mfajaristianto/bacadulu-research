<?php

namespace App\Services\ExternalSources\Adapters;

use App\Services\ExternalSources\ExternalSourceException;
use App\Services\ExternalSources\SafeHttp;

abstract class BaseAdapter implements ExternalSourceAdapter
{
    /** Host yang boleh dihubungi adapter ini (tetap, bukan input pengguna). */
    abstract protected function hosts(): array;

    protected function http(): SafeHttp
    {
        return new SafeHttp($this->hosts());
    }

    public function ping(array $settings): array
    {
        try {
            $page = $this->fetchPage('research', [], null, 1, $settings);

            return ['ok' => true, 'message' => 'Terhubung'.($page->total !== null ? ' ('.number_format($page->total, 0, ',', '.').' rekaman pada kueri uji)' : '').'.'];
        } catch (ExternalSourceException $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }
}
