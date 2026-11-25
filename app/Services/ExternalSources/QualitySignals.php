<?php

namespace App\Services\ExternalSources;

use App\Models\ExternalPublication;

/*
 * SINYAL KUALITAS (bukan filter). HARD FILTER = syarat yang benar-benar diwajibkan saat sync (tahun, tipe, bahasa, open access, istilah).
 * Sinyal di sini hanya INFORMASI untuk ditinjau admin: tidak ada rekaman yang dibuang karena metadatanya tidak lengkap.
 * Semuanya dihitung dari data tersimpan (tanpa migration, tanpa AI).
 */
final class QualitySignals
{
    private const TYPE_LABELS = ['article' => 'Artikel jurnal', 'book' => 'Buku', 'chapter' => 'Bab buku', 'proceedings' => 'Prosiding', 'dataset' => 'Dataset', 'thesis' => 'Tesis/disertasi', 'other' => 'Jenis lain (mis. entri rujukan)'];

    /**
     * @param  array{title?: ?string, abstract?: ?string, authors?: ?array, year?: mixed, doi?: ?string, type?: ?string}  $r
     * @return array{flags: array<int, array{key: string, label: string, level: string}>, match: ?array{key: string, label: string, level: string}}
     */
    public static function evaluate(array $r, ?string $term): array
    {
        $flags = [];

        foreach ([
            ['missing_authors', 'Tanpa penulis', empty($r['authors']), 'warn'],
            ['missing_year', 'Tanpa tahun', empty($r['year']), 'warn'],
            ['missing_doi', 'Tanpa DOI', empty($r['doi']), 'warn'],
            ['missing_abstract', 'Tanpa abstrak', empty($r['abstract']), 'info'],
        ] as [$key, $label, $missing, $level]) {
            if ($missing) {
                $flags[] = ['key' => $key, 'label' => $label, 'level' => $level];
            }
        }

        $type = $r['type'] ?? null;
        if ($type && $type !== 'article') {
            $flags[] = ['key' => 'non_article', 'label' => self::TYPE_LABELS[$type] ?? $type, 'level' => 'info'];
        }

        return ['flags' => $flags, 'match' => self::keywordMatch($r, $term)];
    }

    public static function forModel(ExternalPublication $p): array
    {
        return self::evaluate([
            'title' => $p->title, 'abstract' => $p->abstract, 'authors' => $p->authors_json ?? [], 'year' => $p->publication_year,
            'doi' => $p->doi, 'type' => $p->publication_type,
        ], $p->relationLoaded('categoryLinks') ? $p->categoryLinks->first()?->query_term : null);
    }

    private static function keywordMatch(array $r, ?string $term): ?array
    {
        $term = mb_strtolower(trim((string) $term));
        if ($term === '') {
            return null;
        }

        $tokens = array_values(array_filter(preg_split('/[^\p{L}\p{N}]+/u', $term) ?: []));
        $title = mb_strtolower((string) ($r['title'] ?? ''));
        $abstract = mb_strtolower((string) ($r['abstract'] ?? ''));
        $all = fn (string $text) => $tokens !== [] && collect($tokens)->every(fn ($t) => str_contains($text, $t));

        return match (true) {
            str_contains($title, $term) => ['key' => 'title_exact', 'label' => 'Frasa persis di judul', 'level' => 'ok'],
            $all($title) => ['key' => 'title_related', 'label' => 'Semua kata kunci di judul', 'level' => 'ok'],
            str_contains($abstract, $term) => ['key' => 'abstract_exact', 'label' => 'Frasa persis di abstrak', 'level' => 'info'],
            $all($abstract) => ['key' => 'abstract_related', 'label' => 'Semua kata kunci di abstrak', 'level' => 'info'],
            default => ['key' => 'none', 'label' => 'Istilah tidak ada di judul/abstrak', 'level' => 'warn'],
        };
    }
}
