<?php

namespace App\Services\ExternalSources;

use Illuminate\Support\Facades\Cache;

/*
 * Gerbang laju per penyedia yang DIBAGI antar proses (worker, kampanye paralel): jeda antar-permintaan dipesan lewat
 * cache atomik, jadi dua worker tidak bisa menembak penyedia yang sama lebih cepat dari interval sopan. Cooldown
 * (hasil 429 / Retry-After) juga bersifat per penyedia: semua kampanye penyedia itu ikut menunggu.
 */
final class RateGate
{
    /** Memesan slot permintaan berikutnya. @return int mikrodetik yang harus ditunggu pemanggil sebelum memanggil penyedia. */
    public static function reserve(string $provider, int $intervalMs): int
    {
        if ($intervalMs <= 0) {
            return 0;
        }

        $key = "external-rate:{$provider}";
        $reserve = function () use ($key, $intervalMs): int {
            $now = microtime(true);
            $next = (float) Cache::get($key, 0.0);
            $start = max($now, $next);
            Cache::put($key, $start + $intervalMs / 1000, 3600);

            return (int) round(max(0.0, $start - $now) * 1_000_000);
        };

        try {
            return Cache::lock("{$key}:lock", 5)->block(3, $reserve);
        } catch (\Throwable) {
            // Tanpa kunci atomik, jeda minimal tetap dihormati oleh proses ini.
            return $intervalMs * 1000;
        }
    }

    /** Menunggu slot (tidak tidur saat tes unit agar suite tetap cepat). */
    public static function wait(string $provider, int $intervalMs): int
    {
        $micro = self::reserve($provider, $intervalMs);

        if ($micro > 0 && ! app()->runningUnitTests()) {
            usleep($micro);
        }

        return $micro;
    }

    public static function setCooldown(string $provider, int $seconds): void
    {
        Cache::put("external-cooldown:{$provider}", time() + max(1, $seconds), max(1, $seconds) + 60);
    }

    /** Detik tersisa sebelum penyedia boleh dipanggil lagi (0 = boleh). */
    public static function cooldownRemaining(string $provider): int
    {
        return max(0, (int) Cache::get("external-cooldown:{$provider}", 0) - time());
    }
}
