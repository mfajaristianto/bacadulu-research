<?php

namespace App\Services\ExternalSources\Adapters;

use App\Services\ExternalSources\ExternalPage;
use App\Services\ExternalSources\Normalize;

/*
 * OpenAlex REST API (https://help.openalex.org). Filter yang dipakai hanya yang tersedia di paket gratis:
 * publication_year, type, language, is_oa, title_and_abstract.search. Filter created_date/updated_date (berbayar)
 * TIDAK dipakai. Tanpa API key: paging `page` (maks 10.000 hasil, anggaran harian sangat kecil dan dibagi per IP).
 * Dengan API key gratis: paging cursor. Untuk bulk besar, penyedia menganjurkan snapshot resmi, bukan crawl API.
 */
class OpenAlexAdapter extends BaseAdapter
{
    private const TYPES = ['article' => 'article', 'book' => 'book', 'chapter' => 'book-chapter', 'dataset' => 'dataset', 'thesis' => 'dissertation'];

    private const SELECT = 'id,doi,title,publication_year,publication_date,language,type,open_access,primary_location,authorships,keywords,abstract_inverted_index,biblio';

    public function code(): string
    {
        return 'openalex';
    }

    protected function hosts(): array
    {
        return ['api.openalex.org'];
    }

    public function info(): array
    {
        return [
            'name' => 'OpenAlex',
            'kinds' => 'Artikel, buku, bab buku, dataset, tesis',
            'description' => 'Katalog terbuka karya ilmiah global (metadata CC0) dengan abstrak, penulis, dan status open access.',
            'host' => 'api.openalex.org',
            'docs_url' => 'https://help.openalex.org/how-to-use-the-api/api-overview',
            'key' => 'optional',
            'key_note' => 'API key gratis dianjurkan: tanpa key anggaran harian kecil dan dibagi per alamat IP.',
            'filters' => ['year', 'language', 'type', 'oa'],
            'types' => array_keys(self::TYPES),
            'min_interval_ms' => 120,
            'page_size' => 100,
            'search_cap' => null,
            'modes' => ['quick', 'category', 'all_mapped'],
            'mode_notes' => [
                'corpus' => 'Snapshot resmi OpenAlex ~745 GB terkompresi (Works ~615 GB), jauh di atas disk lokal: DIBLOKIR sampai ada kapasitas dan persetujuan. Jangan crawl lewat API.',
                'incremental' => 'Filter tanggal pembaruan/pembuatan OpenAlex adalah fitur berbayar: tidak ditawarkan.',
            ],
            'bulk' => 'Snapshot S3 (JSONL/Parquet, kuartalan). Tanpa API key paging dibatasi 10.000 hasil; dengan key gratis cursor tidak dibatasi (tetap dibatasi batas kampanye).',
        ];
    }

    public function fetchPage(string $term, array $filters, ?string $cursor, int $pageSize, array $settings): ExternalPage
    {
        $hasKey = filled($settings['api_key'] ?? null);
        $parts = ['title_and_abstract.search:"'.$term.'"'];

        $from = $filters['year_from'] ?? null;
        $to = $filters['year_to'] ?? null;
        if ($from && $to) {
            $parts[] = "publication_year:{$from}-{$to}";
        } elseif ($from) {
            $parts[] = 'publication_year:>'.($from - 1);
        } elseif ($to) {
            $parts[] = 'publication_year:<'.($to + 1);
        }
        if (! empty($filters['type']) && isset(self::TYPES[$filters['type']])) {
            $parts[] = 'type:'.self::TYPES[$filters['type']];
        }
        if (! empty($filters['language'])) {
            $parts[] = 'language:'.preg_replace('/[^a-z]/', '', $filters['language']);
        }
        if (! empty($filters['open_access_only'])) {
            $parts[] = 'is_oa:true';
        }

        $query = ['filter' => implode(',', $parts), 'per_page' => max(1, min(100, $pageSize)), 'select' => self::SELECT];

        // Kursor "p:N" = paging halaman (tanpa key); selain itu kursor OpenAlex asli.
        if ($hasKey) {
            $query['cursor'] = $cursor ?: '*';
        } else {
            $page = $cursor && str_starts_with($cursor, 'p:') ? (int) substr($cursor, 2) : 1;
            if ($page * $query['per_page'] > 10000) {
                return new ExternalPage([], null);
            }
            $query['page'] = $page;
        }

        $body = $this->http()->get('https://api.openalex.org/works', $query, $settings)->json() ?? [];
        $records = collect($body['results'] ?? [])->map(fn (array $w) => $this->map($w))->filter()->values()->all();
        $total = isset($body['meta']['count']) ? (int) $body['meta']['count'] : null;

        $next = null;
        if ($records !== []) {
            $more = $total === null || ($query['page'] ?? 1) * $query['per_page'] < $total;
            $next = $hasKey ? ($body['meta']['next_cursor'] ?? null) : ($more ? 'p:'.(($query['page'] ?? 1) + 1) : null);
        }

        return new ExternalPage($records, $next, $total);
    }

    private function map(array $w): ?array
    {
        $loc = $w['primary_location'] ?? [];
        $source = $loc['source'] ?? [];
        $oa = $w['open_access'] ?? [];
        $doi = $w['doi'] ?? null;
        $type = array_search($w['type'] ?? '', self::TYPES, true);

        return Normalize::finish($this->code(), [
            'external_id' => basename((string) ($w['id'] ?? '')),
            'doi' => $doi,
            'issn' => $source['issn'] ?? [],
            'title' => $w['title'] ?? null,
            'authors' => collect($w['authorships'] ?? [])->map(fn ($a) => $a['author']['display_name'] ?? null)->filter()->all(),
            'year' => $w['publication_year'] ?? null,
            'published_date' => $w['publication_date'] ?? null,
            'publisher' => $source['host_organization_name'] ?? null,
            'container_title' => $source['display_name'] ?? null,
            'abstract' => Normalize::invertedAbstract($w['abstract_inverted_index'] ?? null),
            'keywords' => collect($w['keywords'] ?? [])->map(fn ($k) => is_array($k) ? ($k['display_name'] ?? null) : $k)->filter()->all(),
            'language' => $w['language'] ?? null,
            'type' => $type === false ? 'other' : $type,
            'landing_url' => $loc['landing_page_url'] ?? ($doi ?: null),
            'fulltext_url' => $oa['oa_url'] ?? null,
            'license' => $loc['license'] ?? null,
            'oa_status' => isset($oa['is_oa']) ? ($oa['is_oa'] ? 'open' : 'closed') : 'unknown',
        ]);
    }
}
