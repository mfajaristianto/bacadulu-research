<?php

namespace App\Services\FullText;

use Closure;

/**
 * Pelindung SSRF untuk pengunduh. Sebuah URL hanya lolos bila: HTTPS, tanpa kredensial, port 443, nama host (bukan literal IP
 * atau bentuk numerik tersamar), cocok daftar host (bila dikonfigurasi), dan SEMUA alamat hasil DNS (IPv4 dan IPv6) publik.
 * Alamat hasil resolusi dikembalikan agar pengunduh MENGUNCI koneksi ke alamat itu (anti DNS-rebinding).
 * Dipanggil ulang untuk setiap lompatan redirect.
 */
final class UrlGuard
{
    /** @param  (Closure(string): list<string>)|null  $resolver  host -> daftar IP (diinjeksi pada tes) */
    public function __construct(private ?Closure $resolver = null) {}

    /** @return array{url: string, host: string, ip: string, ips: list<string>} */
    public function validate(string $url): array
    {
        $url = trim($url);
        if ($url === '' || strlen($url) > 2000 || preg_match('/[\x00-\x20\x7f\\\\]/', $url)) {
            throw new FullTextException('bad_url', 'Alamat tidak valid.');
        }

        $p = parse_url($url);
        if ($p === false || ! isset($p['scheme'])) {
            throw new FullTextException('bad_url', 'Alamat tidak valid.');
        }
        if (strtolower($p['scheme']) !== 'https') {
            throw new FullTextException('bad_scheme', 'Hanya HTTPS yang diizinkan.');
        }
        if (! isset($p['host'])) {
            throw new FullTextException('bad_url', 'Alamat tidak valid.');
        }
        if (isset($p['user']) || isset($p['pass'])) {
            throw new FullTextException('bad_url', 'Alamat dengan kredensial ditolak.');
        }
        if (isset($p['port']) && (int) $p['port'] !== 443) {
            throw new FullTextException('bad_port', 'Hanya port HTTPS standar (443) yang diizinkan.');
        }

        $host = rtrim(strtolower($p['host']), '.');
        if (function_exists('idn_to_ascii') && preg_match('/[^\x20-\x7e]/', $host)) {
            $host = (string) (idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) ?: $host);
        }
        $this->assertHostname($host);
        $this->assertAllowedHost($host);

        $ips = $this->resolve($host);
        if ($ips === []) {
            throw new FullTextException('dns_failed', 'Nama host tidak dapat di-resolve.', true);
        }
        foreach ($ips as $ip) {
            if (! self::isPublicIp($ip)) {
                throw new FullTextException('blocked_address', 'Alamat tujuan internal/privat diblokir.');
            }
        }

        // Utamakan IPv4 (CURLOPT_RESOLVE lebih andal); semuanya sudah terbukti publik.
        usort($ips, fn ($a, $b) => (int) str_contains($a, ':') <=> (int) str_contains($b, ':'));

        return ['url' => $url, 'host' => $host, 'ip' => $ips[0], 'ips' => $ips];
    }

    private function assertHostname(string $host): void
    {
        $bad = $host === '' || strlen($host) > 253
            || str_contains($host, ':') || str_contains($host, '[')
            || filter_var($host, FILTER_VALIDATE_IP) !== false
            // Bentuk numerik tersamar (desimal 2130706433, heks 0x7f.1, oktal 0177.0.0.1).
            || preg_match('/^(0x[0-9a-f]+|\d+)(\.(0x[0-9a-f]+|\d+))*$/i', $host)
            || ! preg_match('/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z][a-z0-9-]{1,62}$/', $host)
            || in_array($host, ['localhost'], true)
            || preg_match('/\.(localhost|local|internal|localdomain|lan|home|corp|intranet)$/', $host);

        if ($bad) {
            throw new FullTextException('blocked_host', 'Nama host ditolak (lokal, IP literal, atau tidak valid).');
        }
    }

    private function assertAllowedHost(string $host): void
    {
        $allowed = (array) config('external-sources.fulltext.allowed_hosts', []);
        if ($allowed === []) {
            return;
        }
        foreach ($allowed as $a) {
            $a = strtolower(trim((string) $a));
            if ($a !== '' && ($host === $a || str_ends_with($host, '.'.$a))) {
                return;
            }
        }

        throw new FullTextException('host_not_allowed', 'Host tidak ada di daftar host yang diizinkan.');
    }

    /** @return list<string> */
    private function resolve(string $host): array
    {
        if ($this->resolver) {
            return array_values(array_unique(array_map('strval', ($this->resolver)($host))));
        }

        $ips = [];
        foreach (@dns_get_record($host, DNS_A | DNS_AAAA) ?: [] as $r) {
            $ip = $r['ip'] ?? $r['ipv6'] ?? null;
            if ($ip) {
                $ips[] = $ip;
            }
        }

        return array_values(array_unique($ips));
    }

    public static function isPublicIp(string $ip): bool
    {
        $bin = @inet_pton($ip);
        if ($bin === false) {
            return false;
        }

        if (strlen($bin) === 16) {
            // IPv4 yang dipetakan/dibungkus: nilai IPv4-nya yang dinilai.
            foreach (['::ffff:0:0/96' => 12, '64:ff9b::/96' => 12] as $prefix => $offset) {
                if (self::inCidr($bin, $prefix)) {
                    return self::isPublicIp(inet_ntop(substr($bin, $offset, 4)));
                }
            }
            if (self::inCidr($bin, '2002::/16')) { // 6to4
                return self::isPublicIp(inet_ntop(substr($bin, 2, 4)));
            }
            foreach (['::/128', '::1/128', '100::/64', '2001:db8::/32', '2001::/32', '2001:10::/28', 'fc00::/7', 'fe80::/10', 'fec0::/10', 'ff00::/8'] as $cidr) {
                if (self::inCidr($bin, $cidr)) {
                    return false;
                }
            }
            // Hanya ruang unicast global (2000::/3) yang dianggap publik.
            return self::inCidr($bin, '2000::/3');
        }

        foreach (['0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16', '172.16.0.0/12', '192.0.0.0/24', '192.0.2.0/24',
            '192.88.99.0/24', '192.168.0.0/16', '198.18.0.0/15', '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4'] as $cidr) {
            if (self::inCidr($bin, $cidr)) {
                return false;
            }
        }

        return true;
    }

    private static function inCidr(string $bin, string $cidr): bool
    {
        [$net, $bits] = explode('/', $cidr);
        $netBin = inet_pton($net);
        if (strlen($netBin) !== strlen($bin)) {
            return false;
        }
        $bits = (int) $bits;
        $bytes = intdiv($bits, 8);
        if ($bytes > 0 && substr($bin, 0, $bytes) !== substr($netBin, 0, $bytes)) {
            return false;
        }
        $rest = $bits % 8;
        if ($rest === 0) {
            return true;
        }
        $mask = (0xFF << (8 - $rest)) & 0xFF;

        return (ord($bin[$bytes]) & $mask) === (ord($netBin[$bytes]) & $mask);
    }
}
