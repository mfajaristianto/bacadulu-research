<?php

namespace App\Services\FullText;

use App\Services\ExternalSources\RateGate;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Pengunduh backend-saja. Tiap lompatan (termasuk redirect) divalidasi UrlGuard dan koneksi dikunci ke IP yang sudah terbukti publik.
 * Streaming ke berkas sementara (bukan memori), batas ukuran dijaga lewat header, progres, dan pemeriksaan akhir. Isi harus benar-benar
 * PDF (magic byte + penutup %%EOF); halaman HTML (login/CAPTCHA/paywall) ditolak, TIDAK dilewati. Tidak ada cookie, tidak ada kredensial.
 */
final class SecureDownloader
{
    private const PDF_TYPES = ['application/pdf', 'application/x-pdf', 'application/octet-stream', 'binary/octet-stream', 'application/download'];

    private const XML_TYPES = ['application/xml', 'text/xml', 'application/tei+xml', 'application/jats+xml'];

    public function __construct(private UrlGuard $guard) {}

    /**
     * Setiap lompatan memakai berkas sementara BERBEDA ($tmpPath.h{n}): di Windows handle respons redirect masih mengunci berkasnya,
     * sehingga menghapus lalu memakai ulang jalur yang sama gagal (terbukti live pada redirect 303 Springer).
     *
     * @return array{bytes: int, sha256: string, content_type: string, final_url: string, path: string} berkas selesai di `path`
     */
    public function download(string $url, string $tmpPath, string $kind = 'pdf'): array
    {
        $cfg = (array) config('external-sources.fulltext.download');
        $max = (int) $cfg['max_bytes'];
        $current = $url;

        try {
            for ($hop = 0; $hop <= (int) $cfg['max_redirects']; $hop++) {
                $target = $this->guard->validate($current);
                $gate = 'fulltext:'.$target['host'];

                if (($left = RateGate::cooldownRemaining($gate)) > 0) {
                    throw new FullTextException('rate_limited', 'Penyedia meminta jeda; coba lagi nanti.', true, $left);
                }
                RateGate::wait($gate, (int) $cfg['host_interval_ms']);

                $sink = $tmpPath.'.h'.$hop;
                $response = $this->request($target, $sink, $max, $cfg);
                $status = $response->status();

                if (in_array($status, [301, 302, 303, 307, 308], true)) {
                    $next = trim((string) $response->header('Location'));
                    $response = null; // lepas handle sink sebelum menghapus
                    @unlink($sink);
                    if ($next === '') {
                        throw new FullTextException('bad_redirect', 'Redirect tanpa tujuan.');
                    }
                    $current = $this->absolute($current, $next);

                    continue;
                }

                return $this->accept($response, $sink, $kind, $max, $current, $gate);
            }

            throw new FullTextException('too_many_redirects', 'Terlalu banyak redirect.');
        } catch (FullTextException $e) {
            $response = null;
            self::cleanup($tmpPath);
            throw $e;
        }
    }

    /** Menghapus semua berkas sementara sebuah unduhan (satu per lompatan). */
    public static function cleanup(string $tmpPath): void
    {
        foreach (glob($tmpPath.'.h*') ?: [] as $f) {
            @unlink($f);
        }
        @unlink($tmpPath);
    }

    private function request(array $target, string $tmpPath, int $max, array $cfg): Response
    {
        $curl = [];
        if (defined('CURLOPT_RESOLVE')) {
            $ip = str_contains($target['ip'], ':') ? '['.$target['ip'].']' : $target['ip'];
            $curl[CURLOPT_RESOLVE] = ["{$target['host']}:443:{$ip}"]; // kunci koneksi ke IP yang sudah divalidasi
            $curl[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
            $curl[CURLOPT_REDIR_PROTOCOLS] = CURLPROTO_HTTPS;
        }

        $contact = trim((string) config('external-sources.contact', ''));

        try {
            return Http::withUserAgent('BacaDuluResearch/1.0'.($contact !== '' ? " (mailto:{$contact})" : ''))
                ->accept('application/pdf,application/xml;q=0.8,*/*;q=0.1')
                ->timeout((int) $cfg['timeout'])->connectTimeout((int) $cfg['connect_timeout'])
                ->withoutRedirecting()
                ->withOptions([
                    'sink' => $tmpPath, 'verify' => true, 'curl' => $curl,
                    'on_headers' => function ($response) use ($max): void {
                        if ((int) $response->getHeaderLine('Content-Length') > $max) {
                            throw new FullTextException('too_large', 'Berkas melebihi batas ukuran.');
                        }
                    },
                    'progress' => function ($total, $now) use ($max): void {
                        if ($now > $max) {
                            throw new FullTextException('too_large', 'Berkas melebihi batas ukuran.');
                        }
                    },
                ])->get($target['url']);
        } catch (ConnectionException $e) {
            throw $this->unwrap($e) ?? new FullTextException('timeout', 'Tidak dapat terhubung atau waktu habis.', true);
        } catch (Throwable $e) {
            throw $this->unwrap($e) ?? new FullTextException('request_failed', 'Permintaan unduhan gagal.', true);
        }
    }

    private function unwrap(Throwable $e): ?FullTextException
    {
        for ($i = 0; $e && $i < 6; $i++, $e = $e->getPrevious()) {
            if ($e instanceof FullTextException) {
                return $e;
            }
        }

        return null;
    }

    /** @return array{bytes: int, sha256: string, content_type: string, final_url: string} */
    private function accept(Response $response, string $tmpPath, string $kind, int $max, string $finalUrl, string $gate): array
    {
        $status = $response->status();

        if ($status === 429 || $status === 503) {
            $after = $this->retryAfter($response->header('Retry-After'));
            RateGate::setCooldown($gate, $after);
            throw new FullTextException('rate_limited', "Penyedia meminta jeda (HTTP {$status}).", true, $after);
        }
        if ($status >= 500) {
            throw new FullTextException('server_error', "Server penyedia bermasalah (HTTP {$status}).", true);
        }
        if (in_array($status, [401, 402, 403], true)) {
            throw new FullTextException('access_denied', 'Penerbit menolak akses otomatis (login, paywall, atau anti-bot). Tidak dilewati.');
        }
        if (in_array($status, [404, 410], true)) {
            throw new FullTextException('not_found', 'Berkas tidak ditemukan di lokasi ini.');
        }
        if ($status < 200 || $status >= 300) {
            throw new FullTextException('http_error', "Penyedia membalas HTTP {$status}.");
        }

        $type = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));
        $allowed = $kind === 'xml' ? self::XML_TYPES : self::PDF_TYPES;
        if ($type !== '' && ! in_array($type, $allowed, true)) {
            throw new FullTextException('not_pdf', $type === 'text/html'
                ? 'Server mengembalikan halaman web (HTML), bukan berkas. Bisa berarti login, CAPTCHA, atau paywall; tidak dilewati.'
                : "Tipe konten {$type} bukan berkas yang diharapkan.");
        }

        clearstatcache(true, $tmpPath);
        $size = is_file($tmpPath) ? (int) filesize($tmpPath) : 0;
        if ($size <= 0) {
            throw new FullTextException('empty_file', 'Berkas kosong.');
        }
        if ($size > $max) {
            throw new FullTextException('too_large', 'Berkas melebihi batas ukuran.');
        }

        $this->assertSignature($tmpPath, $kind, $size);

        return ['bytes' => $size, 'sha256' => (string) hash_file('sha256', $tmpPath), 'content_type' => $type !== '' ? $type : 'application/octet-stream', 'final_url' => $finalUrl, 'path' => $tmpPath];
    }

    private function assertSignature(string $path, string $kind, int $size): void
    {
        $h = fopen($path, 'rb');
        $head = (string) fread($h, 1024);
        $tail = '';
        if ($kind !== 'xml') {
            fseek($h, max(0, $size - 2048));
            $tail = (string) fread($h, 2048);
        }
        fclose($h);

        if ($kind === 'xml') {
            if (! preg_match('/^\xEF\xBB\xBF?\s*<(\?xml|TEI|article|!--)/i', $head)) {
                throw new FullTextException('not_xml', 'Isi bukan dokumen XML yang dikenali.');
            }

            return;
        }
        if (! str_contains($head, '%PDF-')) {
            throw new FullTextException('not_pdf', 'Isi berkas bukan PDF (tanda %PDF tidak ditemukan).');
        }
        if (! str_contains($tail, '%%EOF')) {
            throw new FullTextException('truncated_pdf', 'PDF tidak lengkap (akhir berkas tidak ditemukan); unduhan mungkin terpotong.', true);
        }
    }

    private function retryAfter(?string $value): int
    {
        $value = trim((string) $value);
        $s = ctype_digit($value) ? (int) $value : (($t = strtotime($value)) ? $t - time() : 60);

        return max(30, min(3600, $s));
    }

    /** Menyelesaikan Location (absolut atau relatif) terhadap URL saat ini. */
    private function absolute(string $base, string $location): string
    {
        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $location)) {
            return $location; // skema apa pun diteruskan ke UrlGuard, yang menolak selain https
        }
        $b = parse_url($base);
        $origin = 'https://'.$b['host'];

        if (str_starts_with($location, '//')) {
            return 'https:'.$location;
        }
        if (str_starts_with($location, '/')) {
            return $origin.$location;
        }

        return $origin.rtrim(dirname($b['path'] ?? '/'), '/').'/'.$location;
    }
}
