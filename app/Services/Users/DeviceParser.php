<?php

namespace App\Services\Users;

/**
 * Parser User-Agent sederhana: jenis perangkat, sistem operasi, browser. Hanya dari header yang dikirim browser; TIDAK
 * dapat mengetahui model perangkat dan tidak melakukan fingerprinting.
 */
final class DeviceParser
{
    /** @return array{device_type: string, os: string, browser: string} */
    public static function parse(?string $ua): array
    {
        $ua = trim((string) $ua);
        if ($ua === '') {
            return ['device_type' => 'unknown', 'os' => 'Tidak diketahui', 'browser' => 'Tidak diketahui'];
        }

        $os = match (true) {
            (bool) preg_match('/Windows/i', $ua) => 'Windows',
            (bool) preg_match('/iPhone|iPad|iPod/i', $ua) => 'iOS',
            (bool) preg_match('/Android/i', $ua) => 'Android',
            (bool) preg_match('/Mac OS X|Macintosh/i', $ua) => 'macOS',
            (bool) preg_match('/CrOS/i', $ua) => 'ChromeOS',
            (bool) preg_match('/Linux|X11/i', $ua) => 'Linux',
            default => 'Tidak diketahui',
        };

        // Urutan penting: Edge/Opera/Samsung membawa token "Chrome"; Chrome membawa "Safari".
        $browser = match (true) {
            (bool) preg_match('/Edg(?:e|A|iOS)?\//i', $ua) => 'Edge',
            (bool) preg_match('/OPR\/|Opera/i', $ua) => 'Opera',
            (bool) preg_match('/SamsungBrowser/i', $ua) => 'Samsung Internet',
            (bool) preg_match('/Firefox|FxiOS/i', $ua) => 'Firefox',
            (bool) preg_match('/Chrome|CriOS|Chromium/i', $ua) => 'Chrome',
            (bool) preg_match('/Safari/i', $ua) => 'Safari',
            default => 'Tidak diketahui',
        };

        $device = match (true) {
            (bool) preg_match('/bot|crawl|spider|curl|wget|python-requests/i', $ua) => 'bot',
            (bool) preg_match('/iPad|Tablet/i', $ua) || (str_contains($ua, 'Android') && ! str_contains($ua, 'Mobile')) => 'tablet',
            (bool) preg_match('/Mobi|iPhone|iPod|Android/i', $ua) => 'mobile',
            $os === 'Tidak diketahui' => 'unknown',
            default => 'desktop',
        };

        return ['device_type' => $device, 'os' => $os, 'browser' => $browser];
    }

    public static function label(?string $deviceType, ?string $os, ?string $browser): string
    {
        $names = ['desktop' => 'Desktop', 'mobile' => 'Mobile', 'tablet' => 'Tablet', 'bot' => 'Bot', 'unknown' => 'Perangkat tidak dikenal'];

        return implode(' · ', array_filter([$names[$deviceType ?? 'unknown'] ?? 'Perangkat tidak dikenal', $os, $browser]));
    }
}
