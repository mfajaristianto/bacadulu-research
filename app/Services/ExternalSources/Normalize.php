<?php

namespace App\Services\ExternalSources;

use Illuminate\Support\Str;

/** Pembersih bersama untuk rekaman dari semua penyedia. Teks dari luar tidak pernah dianggap HTML. */
final class Normalize
{
    public static function text(?string $value, int $max = 1000): ?string
    {
        if ($value === null) {
            return null;
        }

        // Dua putaran: abstrak penyedia sering ter-encode ganda (mis. "&amp;#x0D;"). Tag dibuang lagi setelah tiap decode.
        // ENT_HTML5 tidak mau men-decode &#xD; (CR), jadi karakter spasi numerik dijadikan spasi lebih dulu.
        $value = preg_replace('/&(?:amp;)*#(?:x0*[9aAdD]|0*(?:9|10|13));/', ' ', $value) ?? $value;
        for ($i = 0; $i < 2; $i++) {
            $value = strip_tags(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        return $value === '' ? null : Str::limit($value, $max, '');
    }

    public static function doi(?string $value): ?string
    {
        $value = strtolower(trim((string) $value));
        $value = preg_replace('#^(https?://(dx\.)?doi\.org/|doi:)#', '', $value) ?? '';

        return preg_match('#^10\.\d{4,9}/\S+$#', $value) ? $value : null;
    }

    public static function year(mixed $value): ?int
    {
        $year = (int) $value;

        return $year >= 1000 && $year <= 2200 ? $year : null;
    }

    public static function url(?string $value): ?string
    {
        $value = trim((string) $value);

        return preg_match('#^https?://[^\s<>"]+$#i', $value) && strlen($value) <= 1000 ? $value : null;
    }

    /** @param  array<int, mixed>  $values */
    public static function isbns(array $values): array
    {
        return collect($values)->map(fn ($v) => preg_replace('/[^0-9Xx]/', '', (string) $v))
            ->filter(fn ($v) => in_array(strlen($v), [10, 13], true))->map('strtoupper')->unique()->values()->all();
    }

    public static function titleHash(string $title): string
    {
        return sha1(preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower($title)) ?? '');
    }

    /** Reproduksi struktur "inverted index" OpenAlex menjadi teks biasa. */
    public static function invertedAbstract(?array $index): ?string
    {
        if (! $index) {
            return null;
        }

        $words = [];
        foreach ($index as $word => $positions) {
            foreach ((array) $positions as $position) {
                $words[(int) $position] = (string) $word;
            }
        }
        ksort($words);

        return self::text(implode(' ', $words), 4000);
    }

    /**
     * Ketersediaan dihitung dari data yang benar-benar ada. FULL_TEXT_INDEXED sengaja tidak pernah diberikan dari
     * sini: hanya dokumen Pustaka internal yang memiliki chunk teks penuh.
     */
    public static function availability(array $record): string
    {
        return match (true) {
            ($record['oa_status'] ?? 'unknown') === 'open' && ! empty($record['fulltext_url']) => 'OPEN_ACCESS_LINK',
            ! empty($record['abstract']) => 'ABSTRACT_AVAILABLE',
            ($record['oa_status'] ?? 'unknown') === 'closed' => 'RESTRICTED',
            default => 'METADATA_ONLY',
        };
    }

    public static function checksum(array $record): string
    {
        return sha1(json_encode([
            $record['doi'] ?? null, $record['title'] ?? null, $record['authors'] ?? [], $record['year'] ?? null,
            $record['abstract'] ?? null, $record['landing_url'] ?? null, $record['fulltext_url'] ?? null,
            $record['oa_status'] ?? null, $record['container_title'] ?? null, $record['publisher'] ?? null,
        ], JSON_UNESCAPED_UNICODE));
    }

    /** Lengkapi rekaman mentah adapter menjadi bentuk baku yang dipakai penyimpanan dan pratinjau. */
    public static function finish(string $provider, array $r): ?array
    {
        $title = self::text($r['title'] ?? null, 1000);
        $externalId = trim((string) ($r['external_id'] ?? ''));

        if ($title === null || $externalId === '') {
            return null;
        }

        $record = [
            'provider' => $provider,
            'external_id' => Str::limit($externalId, 255, ''),
            'doi' => self::doi($r['doi'] ?? null),
            'isbn' => self::isbns((array) ($r['isbn'] ?? [])),
            'issn' => collect((array) ($r['issn'] ?? []))->map(fn ($v) => self::text((string) $v, 20))->filter()->unique()->take(5)->values()->all(),
            'title' => $title,
            'authors' => collect((array) ($r['authors'] ?? []))->map(fn ($v) => self::text((string) $v, 200))->filter()->take(30)->values()->all(),
            'year' => self::year($r['year'] ?? null),
            'published_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($r['published_date'] ?? '')) ? $r['published_date'] : null,
            'publisher' => self::text($r['publisher'] ?? null, 255),
            'container_title' => self::text($r['container_title'] ?? null, 500),
            'abstract' => self::text($r['abstract'] ?? null, 4000),
            'keywords' => collect((array) ($r['keywords'] ?? []))->map(fn ($v) => self::text((string) $v, 80))->filter()->unique()->take(15)->values()->all(),
            'language' => filled($r['language'] ?? null) ? strtolower(substr((string) $r['language'], 0, 12)) : null,
            'type' => in_array($r['type'] ?? null, [...SyncScope::TYPES, 'other'], true) ? $r['type'] : 'other',
            'landing_url' => self::url($r['landing_url'] ?? null),
            'fulltext_url' => self::url($r['fulltext_url'] ?? null),
            'license' => self::text($r['license'] ?? null, 120),
            'oa_status' => in_array($r['oa_status'] ?? null, ['open', 'closed'], true) ? $r['oa_status'] : 'unknown',
        ];

        $record['availability'] = self::availability($record);
        $record['checksum'] = self::checksum($record);

        return $record;
    }
}
