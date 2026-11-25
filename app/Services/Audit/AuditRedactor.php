<?php

namespace App\Services\Audit;

/*
 * Redaksi payload audit di SERVER sebelum apa pun dikirim ke browser. Dua lapis: nama kunci yang sensitif, dan pola nilai (kunci API, token Bearer, JWT,
 * kredensial pada URL/DSN, pasangan password=..., path berkas). Nilai panjang dipotong dan struktur dibatasi kedalaman/jumlahnya.
 */
final class AuditRedactor
{
    public const MASK = '[DIREDAKSI]';

    private const KEY = '/pass(word|wd)?|secret|token|api[_-]?key|authorization|cookie|credential|private[_-]?key|bearer|signature|session[_-]?id/i';

    private const KEY_ALLOW = '/(input|output|total|prompt|completion|max)_tokens?$/i';

    private const MAX_STRING = 600;

    private const MAX_ITEMS = 60;

    private const MAX_DEPTH = 6;

    /** Jumlah nilai yang diredaksi sejak instans dibuat. */
    public int $redacted = 0;

    public bool $truncated = false;

    public function redact(mixed $value, string $key = '', int $depth = 0): mixed
    {
        if ($key !== '' && preg_match(self::KEY, $key) && ! preg_match(self::KEY_ALLOW, $key) && $value !== null && $value !== '') {
            $this->redacted++;

            return self::MASK;
        }

        if (is_array($value)) {
            if ($depth >= self::MAX_DEPTH) {
                $this->truncated = true;

                return '[terlalu dalam]';
            }

            $out = [];
            $n = 0;

            foreach ($value as $k => $v) {
                if (++$n > self::MAX_ITEMS) {
                    $this->truncated = true;
                    $out['…'] = (count($value) - self::MAX_ITEMS).' item lain tidak ditampilkan';

                    break;
                }

                $out[$k] = $this->redact($v, (string) $k, $depth + 1);
            }

            return $out;
        }

        if (is_string($value)) {
            return $this->text($value);
        }

        if (is_scalar($value) || $value === null) {
            return $value;
        }

        return '[tidak dapat ditampilkan]';
    }

    public function text(string $s): string
    {
        $mask = fn (string $replacement) => function (array $m) use ($replacement): string {
            $this->redacted++;

            return $replacement;
        };

        $s = preg_replace_callback('/\b(?:sk|pk|rk|xox[abp])-[A-Za-z0-9_\-]{10,}/', $mask(self::MASK), $s) ?? $s;
        $s = preg_replace_callback('/Bearer\s+[A-Za-z0-9._~+\/=\-]{8,}/i', $mask('Bearer '.self::MASK), $s) ?? $s;
        $s = preg_replace_callback('/eyJ[A-Za-z0-9_\-]{8,}\.[A-Za-z0-9_\-]{8,}\.[A-Za-z0-9_\-]*/', $mask(self::MASK), $s) ?? $s;
        $s = preg_replace_callback('/(?<=:\/\/)[^\s\/:@]+:[^\s\/@]+(?=@)/', $mask(self::MASK), $s) ?? $s;
        $s = preg_replace_callback('/\b(password|passwd|pwd|secret|token|api[_-]?key)\b(\s*[=:]\s*)[^\s,;&"\']+/i', function (array $m): string {
            $this->redacted++;

            return $m[1].$m[2].self::MASK;
        }, $s) ?? $s;
        $s = preg_replace_callback('/[A-Za-z]:\\\\[^\s\'"<>|]+/', $mask('[path]'), $s) ?? $s;
        $s = preg_replace_callback('/(?<![\w.:\/])\/(?:var|home|usr|etc|srv|opt|app|storage|tmp|Users|mnt)\/[^\s\'"<>|]+/', $mask('[path]'), $s) ?? $s;

        if (mb_strlen($s) > self::MAX_STRING) {
            $this->truncated = true;
            $s = mb_substr($s, 0, self::MAX_STRING).'… (dipotong)';
        }

        return $s;
    }

    /** Alamat IP ditampilkan sebagian: IPv4 -> tiga oktet pertama, IPv6 -> empat kelompok pertama. */
    public static function maskIp(?string $ip): ?string
    {
        if (! filled($ip)) {
            return null;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return implode('.', array_slice(explode('.', $ip), 0, 3)).'.•';
        }

        return implode(':', array_slice(explode(':', $ip), 0, 4)).':•';
    }

    public static function maskEmail(?string $email): ?string
    {
        if (! filled($email) || ! str_contains($email, '@')) {
            return $email;
        }

        [$local, $domain] = explode('@', $email, 2);

        return mb_substr($local, 0, 2).'•••@'.$domain;
    }
}
