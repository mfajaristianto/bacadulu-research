<?php

namespace App\Services\ExternalSources;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/*
 * Satu-satunya pintu keluar HTTP untuk sumber eksternal. Host hanya dari allowlist per adapter (bukan input pengguna),
 * wajib HTTPS, bukan alamat IP/localhost, tanpa mengikuti redirect, ada batas waktu dan batas ukuran respons.
 * Kunci dikirim lewat header Authorization (tidak masuk URL/log).
 */
class SafeHttp
{
    /** @param  array<int, string>  $allowedHosts */
    public function __construct(private array $allowedHosts) {}

    public function get(string $url, array $query = [], array $settings = [], string $accept = 'application/json'): Response
    {
        $parts = parse_url($url) ?: [];
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (($parts['scheme'] ?? '') !== 'https' || ! in_array($host, $this->allowedHosts, true) || filter_var($host, FILTER_VALIDATE_IP) !== false) {
            throw new ExternalSourceException('Alamat penyedia ditolak oleh daftar host yang diizinkan.', 'bad_request');
        }

        $max = (int) config('external-sources.http.max_bytes', 5 * 1024 * 1024);
        $contact = trim((string) ($settings['contact'] ?? ''));
        $agent = 'BacaDuluResearch/1.0'.($contact !== '' ? " (mailto:{$contact})" : '');

        $request = Http::withUserAgent($agent)
            ->accept($accept)
            ->timeout((int) config('external-sources.http.timeout', 20))
            ->withoutRedirecting()
            ->withOptions(['on_headers' => function ($response) use ($max): void {
                if ((int) $response->getHeaderLine('Content-Length') > $max) {
                    throw new ExternalSourceException('Respons penyedia melebihi batas ukuran.', 'failed');
                }
            }]);

        if (filled($settings['api_key'] ?? null)) {
            $request = $request->withToken((string) $settings['api_key']);
        }

        try {
            $response = $request->get($url, $query);
        } catch (ConnectionException) {
            throw new ExternalSourceException('Tidak dapat terhubung ke penyedia (waktu habis atau jaringan).', 'failed');
        }

        $status = $response->status();

        return match (true) {
            $status === 429 => throw ExternalSourceException::rateLimited((int) ($response->header('Retry-After') ?: 60)),
            in_array($status, [401, 403], true) => throw ExternalSourceException::auth($status),
            $status >= 300 && $status < 400 => throw new ExternalSourceException('Penyedia mengarahkan ulang permintaan; ditolak demi keamanan.', 'bad_request'),
            $status === 404 => throw new ExternalSourceException('Penyedia membalas HTTP 404 (tidak ditemukan).', 'not_found'),
            $status >= 400 => throw new ExternalSourceException("Penyedia membalas HTTP {$status}.", $status === 400 ? 'bad_request' : 'failed'),
            strlen($response->body()) > $max => throw new ExternalSourceException('Respons penyedia melebihi batas ukuran.', 'failed'),
            default => $response,
        };
    }
}
