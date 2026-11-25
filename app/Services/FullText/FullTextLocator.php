<?php

namespace App\Services\FullText;

use App\Models\ExternalPublication;
use App\Services\ExternalSources\ExternalSourceSettings;
use App\Services\ExternalSources\Normalize;
use App\Services\ExternalSources\SafeHttp;

/**
 * Menemukan LOKASI full-text dari metadata. Hanya menemukan dan mencatat bukti; keputusan izin ada di LicensePolicy.
 *
 *  stored()   : dari data yang sudah tersimpan, tanpa jaringan.
 *  openAlex() : 1 permintaan ke OpenAlex (best_oa_location + locations: pdf_url, landing_page_url, lisensi, versi, is_oa).
 *  crossref() : 1 permintaan ke Crossref (license + link). Tautan Crossref TIDAK dianggap izin unduh: bertipe `tdm` bila untuk
 *               text-mining, dan status open access tidak dikonfirmasi (oa = null).
 *
 * Bentuk lokasi: provider, url, landing_url, kind(pdf|xml|landing|tdm), license, license_url, license_source, license_scope,
 * version, oa(bool|null), evidence(array).
 */
final class FullTextLocator
{
    public function __construct(private ExternalSourceSettings $settings) {}

    /** @return list<array<string, mixed>> */
    public function stored(ExternalPublication $p): array
    {
        $provider = (string) ($p->relationLoaded('sources') ? $p->sources : $p->sources()->get())->sortBy('id')->first()?->provider;
        $oa = $p->oa_status === 'open' ? true : ($p->oa_status === 'closed' ? false : null);
        $out = [];

        if ($url = Normalize::url($p->fulltext_url)) {
            $isLicenseUrl = preg_match('#^https?://#i', (string) $p->license) === 1;
            $out[] = [
                'provider' => $provider ?: 'stored', 'url' => $url, 'landing_url' => Normalize::url($p->landing_url), 'kind' => self::kindOf($url),
                'license' => $isLicenseUrl ? null : $p->license, 'license_url' => $isLicenseUrl ? $p->license : null,
                'license_source' => 'stored.'.($provider ?: 'metadata'), 'license_scope' => $provider === 'doaj' ? 'journal' : 'article',
                'version' => null, 'oa' => $oa, 'evidence' => ['origin' => 'stored_metadata', 'oa_status' => $p->oa_status],
            ];
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    public function openAlex(ExternalPublication $p): array
    {
        $id = $p->sources()->where('provider', 'openalex')->value('external_id');
        $ref = $id ?: ($p->doi ? 'https://doi.org/'.$p->doi : null);
        if (! $ref) {
            return [];
        }

        $body = (new SafeHttp(['api.openalex.org']))->get(
            'https://api.openalex.org/works/'.rawurlencode((string) $ref),
            ['select' => 'id,doi,open_access,best_oa_location,locations'],
            $this->settings->forAdapter('openalex'),
        )->json() ?? [];

        $seen = [];
        $out = [];
        $best = $body['best_oa_location']['pdf_url'] ?? ($body['best_oa_location']['landing_page_url'] ?? null);

        foreach (array_merge([$body['best_oa_location'] ?? null], $body['locations'] ?? []) as $l) {
            if (! is_array($l)) {
                continue;
            }
            $pdf = Normalize::url($l['pdf_url'] ?? null);
            $landing = Normalize::url($l['landing_page_url'] ?? null);
            $url = $pdf ?: $landing;
            if (! $url || isset($seen[$url]) || ($pdf === null && ($l['is_oa'] ?? false) !== true)) {
                continue; // halaman arahan tertutup bukan "teks lengkap"
            }
            $seen[$url] = true;

            $out[] = [
                'provider' => 'openalex', 'url' => $url, 'landing_url' => $landing, 'kind' => $pdf ? self::kindOf($pdf, true) : 'landing',
                'license' => $l['license'] ?? null, 'license_url' => null, 'license_source' => 'openalex.locations', 'license_scope' => 'article',
                'version' => $l['version'] ?? null, 'oa' => isset($l['is_oa']) ? (bool) $l['is_oa'] : null,
                'evidence' => ['origin' => 'openalex', 'is_best' => $url === $best, 'is_oa' => $l['is_oa'] ?? null, 'license' => $l['license'] ?? null,
                    'version' => $l['version'] ?? null, 'source' => $l['source']['display_name'] ?? null, 'host_type' => $l['source']['type'] ?? null],
            ];
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    public function crossref(ExternalPublication $p): array
    {
        if (! $p->doi) {
            return [];
        }

        $w = (new SafeHttp(['api.crossref.org']))->get(
            'https://api.crossref.org/works/'.rawurlencode($p->doi), [], $this->settings->forAdapter('crossref'), // endpoint satu-karya menolak `select` (HTTP 400, terbukti live)
        )->json('message') ?? [];

        // Lisensi tingkat artikel: hanya yang berlaku segera (delay 0). Versi dari content-version (vor = diterbitkan, am = diterima).
        $licenses = collect($w['license'] ?? [])->filter(fn ($l) => (int) ($l['delay-in-days'] ?? 0) <= 0 && ($l['content-version'] ?? '') !== 'tdm');
        $out = [];

        foreach ($w['link'] ?? [] as $link) {
            $url = Normalize::url($link['URL'] ?? null);
            if (! $url) {
                continue;
            }
            $app = (string) ($link['intended-application'] ?? 'unspecified');
            $ver = (string) ($link['content-version'] ?? 'unspecified');
            $tdm = in_array($app, ['text-mining', 'similarity-checking'], true) || $ver === 'tdm';
            $lic = $licenses->first(fn ($l) => ($l['content-version'] ?? '') === $ver) ?? $licenses->first();
            $isPdf = str_contains(strtolower((string) ($link['content-type'] ?? '')), 'pdf');

            $out[] = [
                'provider' => 'crossref', 'url' => $url, 'landing_url' => null, 'kind' => $tdm ? 'tdm' : ($isPdf ? 'pdf' : 'landing'),
                'license' => null, 'license_url' => Normalize::url($lic['URL'] ?? null), 'license_source' => 'crossref.license', 'license_scope' => 'article',
                'version' => ['vor' => 'publishedVersion', 'am' => 'acceptedVersion'][$ver] ?? null, 'oa' => null,
                'evidence' => ['origin' => 'crossref', 'intended_application' => $app, 'content_version' => $ver, 'content_type' => $link['content-type'] ?? null,
                    'license_url' => $lic['URL'] ?? null],
            ];
        }

        return $out;
    }

    /** Hanya tautan yang jelas berkas (akhiran .pdf atau segmen /pdf) yang dianggap pdf; sisanya halaman arahan (tidak ditebak). */
    public static function kindOf(string $url, bool $declaredPdf = false): string
    {
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));

        return $declaredPdf || str_ends_with($path, '.pdf') || preg_match('#/pdf(/|$)#', $path) ? 'pdf' : 'landing';
    }
}
