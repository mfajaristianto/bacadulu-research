<?php

namespace App\Services\ExternalSources;

/*
 * Penjaga sumber daya lokal. Dicek sebelum setiap batch: kampanye yang melewati ambang berhenti dengan alasan jelas
 * (dan bisa dilanjutkan), bukan mengisi disk atau menghabiskan memori sampai proses mati.
 */
final class ResourceGuard
{
    /** Folder yang dicek ruang bebasnya: konfigurasi eksplisit, atau disk storage aplikasi sebagai pendekatan. */
    public static function diskPath(): string
    {
        $path = (string) config('external-sources.budget.disk_path', '');

        return $path !== '' && is_dir($path) ? $path : storage_path();
    }

    public static function freeDiskMb(): ?int
    {
        $free = @disk_free_space(self::diskPath());

        return $free === false ? null : (int) floor($free / 1024 / 1024);
    }

    public static function memoryLimitBytes(): ?int
    {
        // Batas memori yang bisa diganti lewat konfigurasi (MB): dipakai uji agar penjaga memori deterministik; null = pakai memory_limit PHP.
        if ($mb = (int) config('external-sources.budget.memory_limit_override_mb', 0)) {
            return $mb * 1024 * 1024;
        }

        $raw = trim((string) ini_get('memory_limit'));
        if ($raw === '' || $raw === '-1') {
            return null;
        }

        $n = (int) $raw;

        return match (strtolower(substr($raw, -1))) {
            'g' => $n * 1024 ** 3,
            'm' => $n * 1024 ** 2,
            'k' => $n * 1024,
            default => $n,
        };
    }

    public static function memoryUsedPct(): ?float
    {
        $limit = self::memoryLimitBytes();

        return $limit ? round(memory_get_usage(true) / $limit * 100, 1) : null;
    }

    /** @return string|null 'disk' | 'memory' bila ambang terlewati. */
    public static function breach(): ?string
    {
        $min = (int) config('external-sources.budget.min_free_disk_mb', 2048);
        $free = self::freeDiskMb();
        if ($min > 0 && $free !== null && $free < $min) {
            return 'disk';
        }

        $soft = (int) config('external-sources.budget.memory_soft_pct', 70); // 0 = penjaga memori dinonaktifkan
        $pct = self::memoryUsedPct();
        if ($soft > 0 && $pct !== null && $pct >= $soft) {
            return 'memory';
        }

        return null;
    }
}
