<?php

namespace App\Services\ExternalSources;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

/*
 * Membedakan "format DOI valid" dari "DOI benar-benar terdaftar" lewat API handle resmi doi.org (GET gratis).
 * Setiap pemeriksaan = satu permintaan jaringan, jadi: hanya atas permintaan admin untuk SATU rekaman, hasil di-cache
 * (bawaan 30 hari), dan dibatasi kuota per jam. Tidak pernah dijalankan massal otomatis.
 */
final class DoiResolutionCheck
{
    /** @return array{status: string, checked_at: ?string, cached: bool} status: registered | not_found | unknown | throttled */
    public function check(string $doi, bool $force = false): array
    {
        $doi = (string) Normalize::doi($doi);
        if ($doi === '') {
            return ['status' => 'unknown', 'checked_at' => null, 'cached' => false];
        }

        $key = 'external-doi-check:'.sha1($doi);
        if (! $force && ($hit = Cache::get($key))) {
            return $hit + ['cached' => true];
        }

        // Kuota global per jam: melindungi doi.org dan mencegah pemeriksaan massal tak sengaja.
        if (! RateLimiter::attempt('external-doi-check', (int) config('external-sources.doi_check.per_hour', 60), fn () => true, 3600)) {
            return ['status' => 'throttled', 'checked_at' => null, 'cached' => false];
        }

        $path = implode('/', array_map('rawurlencode', explode('/', $doi)));

        try {
            $body = (new SafeHttp(['doi.org']))->get("https://doi.org/api/handles/{$path}")->json() ?? [];
            $status = ($body['responseCode'] ?? null) === 1 ? 'registered' : 'unknown';
        } catch (ExternalSourceException $e) {
            $status = $e->kind === 'not_found' ? 'not_found' : 'unknown';
        }

        $result = ['status' => $status, 'checked_at' => now()->toIso8601String()];
        // "unknown" (galat sementara) tidak di-cache lama agar bisa dicoba lagi.
        Cache::put($key, $result, $status === 'unknown' ? now()->addMinutes(10) : now()->addDays((int) config('external-sources.doi_check.ttl_days', 30)));

        return $result + ['cached' => false];
    }

    public function cached(string $doi): ?array
    {
        $doi = (string) Normalize::doi($doi);

        return $doi === '' ? null : Cache::get('external-doi-check:'.sha1($doi));
    }
}
