<?php

namespace App\Services\ExternalSources\Adapters;

use App\Services\ExternalSources\ExternalPage;
use App\Services\ExternalSources\Normalize;

/*
 * Crossref REST API (https://api.crossref.org). Paging memakai `offset` + sort=relevance, BUKAN cursor: dengan
 * cursor=* Crossref tidak mengurutkan menurut relevansi (terbukti: "financial reporting" mengembalikan riset alergi),
 * sedangkan offset mempertahankan urutan skor. Batas offset penyedia 10.000, jauh di atas batas keras kita (1000/run).
 * Crossref tidak punya filter bahasa dan tidak menandai status open access secara andal, jadi keduanya tidak ditawarkan.
 * Alamat kontak (mailto) hanya dikirim bila admin mengisinya.
 */
class CrossrefAdapter extends BaseAdapter
{
    private const TYPES = ['article' => 'journal-article', 'book' => 'book', 'chapter' => 'book-chapter', 'proceedings' => 'proceedings-article', 'dataset' => 'dataset', 'thesis' => 'dissertation'];

    private const SELECT = 'DOI,title,author,issued,container-title,publisher,type,ISSN,ISBN,license,URL,abstract,subject,link';

    public function code(): string
    {
        return 'crossref';
    }

    protected function hosts(): array
    {
        return ['api.crossref.org'];
    }

    public function info(): array
    {
        return [
            'name' => 'Crossref',
            'kinds' => 'Artikel, buku, bab, prosiding, dataset, disertasi',
            'description' => 'Registri DOI terbesar: metadata resmi dari penerbit, termasuk ISSN/ISBN dan lisensi.',
            'host' => 'api.crossref.org',
            'docs_url' => 'https://www.crossref.org/documentation/retrieve-metadata/rest-api/',
            'key' => 'none',
            'key_note' => 'Tidak perlu key. Mengisi alamat kontak di Pengaturan memasukkan permintaan ke pool yang lebih sopan.',
            'filters' => ['year', 'type'],
            'types' => array_keys(self::TYPES),
            'min_interval_ms' => 150,
            'page_size' => 100,
            'search_cap' => 10000,
            'modes' => ['quick', 'category', 'all_mapped', 'incremental'],
            'mode_notes' => [
                'corpus' => 'Korpus Crossref (Public Data File) berukuran ~200 GB (torrent / AWS requester-pays): diblokir untuk disk dan hosting kecil. Gunakan Category sync (maks 10.000, urutan relevansi).',
                'incremental' => 'Incremental resmi lewat filter REST `from-index-date` (dengan istilah pencarian). Urutan tetap relevansi.',
            ],
            'bulk' => 'Public Data File tahunan (~200 GB, JSON-lines) atau REST dengan cursor rows=1000. Kampanye kita memakai offset+relevansi (maks 10.000 per run) agar urutan relevansi terjaga.',
        ];
    }

    public function fetchPage(string $term, array $filters, ?string $cursor, int $pageSize, array $settings): ExternalPage
    {
        // Kursor = offset berikutnya (string angka); null = awal. shortcut: offset dibatasi 10.000 oleh penyedia, aman karena hard max 1000/run.
        $offset = $cursor !== null && ctype_digit($cursor) ? (int) $cursor : 0;
        $rows = max(1, min(100, $pageSize));

        if ($offset + $rows > 10000) {
            return new ExternalPage([], null);
        }

        $filter = [];
        if (! empty($filters['year_from'])) {
            $filter[] = 'from-pub-date:'.$filters['year_from'];
        }
        if (! empty($filters['year_to'])) {
            $filter[] = 'until-pub-date:'.$filters['year_to'];
        }
        if (($filters['mode'] ?? '') === 'incremental' && ! empty($filters['since'])) {
            // Fitur incremental resmi Crossref: item yang dibuat/diubah sejak tanggal ini.
            $filter[] = 'from-index-date:'.$filters['since'];
        }
        if (! empty($filters['type']) && isset(self::TYPES[$filters['type']])) {
            $filter[] = 'type:'.self::TYPES[$filters['type']];
        }

        $query = ['query' => $term, 'rows' => $rows, 'offset' => $offset, 'sort' => 'relevance', 'order' => 'desc', 'select' => self::SELECT];
        if ($filter) {
            $query['filter'] = implode(',', $filter);
        }
        if (filled($settings['contact'] ?? null)) {
            $query['mailto'] = $settings['contact'];
        }

        $message = ($this->http()->get('https://api.crossref.org/works', $query, $this->noToken($settings))->json() ?? [])['message'] ?? [];
        $items = $message['items'] ?? [];
        $records = collect($items)->map(fn (array $i) => $this->map($i))->filter()->values()->all();
        $total = isset($message['total-results']) ? (int) $message['total-results'] : null;
        $read = $offset + count($items);
        $more = count($items) >= $rows && ($total === null || $read < $total);

        return new ExternalPage($records, $more ? (string) $read : null, $total);
    }

    /** Crossref tidak memakai bearer token. */
    private function noToken(array $settings): array
    {
        unset($settings['api_key']);

        return $settings;
    }

    private function map(array $i): ?array
    {
        $date = $i['issued']['date-parts'][0] ?? [];
        $license = collect($i['license'] ?? [])->pluck('URL')->first();
        $type = array_search($i['type'] ?? '', self::TYPES, true);
        $doi = $i['DOI'] ?? null;

        return Normalize::finish($this->code(), [
            'external_id' => (string) $doi,
            'doi' => $doi,
            'isbn' => $i['ISBN'] ?? [],
            'issn' => $i['ISSN'] ?? [],
            'title' => $i['title'][0] ?? null,
            'authors' => collect($i['author'] ?? [])->map(fn ($a) => trim(($a['given'] ?? '').' '.($a['family'] ?? ($a['name'] ?? ''))))->filter()->all(),
            'year' => $date[0] ?? null,
            'published_date' => count($date) === 3 ? sprintf('%04d-%02d-%02d', ...$date) : null,
            'publisher' => $i['publisher'] ?? null,
            'container_title' => $i['container-title'][0] ?? null,
            'abstract' => $i['abstract'] ?? null,
            'keywords' => $i['subject'] ?? [],
            'type' => $type === false ? 'other' : $type,
            'landing_url' => $i['URL'] ?? ($doi ? 'https://doi.org/'.$doi : null),
            'fulltext_url' => null,
            'license' => $license,
            'oa_status' => 'unknown',
        ]);
    }
}
