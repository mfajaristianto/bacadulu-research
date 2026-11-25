<?php

namespace App\Services\ExternalSources\Adapters;

use App\Services\ExternalSources\ExternalPage;
use App\Services\ExternalSources\ExternalSourceException;
use App\Services\ExternalSources\OaiPmhParser;
use App\Services\ExternalSources\Normalize;

/*
 * DOAJ Public Search API (https://doaj.org/api/docs): GET /api/search/articles/{query}?page=&pageSize=. Hanya artikel
 * jurnal open access (tidak ada filter tipe: seluruh indeks adalah artikel jurnal). Batas penyedia: 2 permintaan/detik. Kueri memakai sintaks query-string (tanda kutip/escape
 * dibersihkan lebih dulu di SyncScope::cleanTerm). Tidak butuh API key untuk pencarian.
 * Catatan: DOAJ juga menyediakan OAI-PMH (/oai.article) untuk harvest berbasis tanggal; belum dipakai di sini.
 */
class DoajAdapter extends BaseAdapter
{
    public function code(): string
    {
        return 'doaj';
    }

    protected function hosts(): array
    {
        return ['doaj.org'];
    }

    public function info(): array
    {
        return [
            'name' => 'DOAJ',
            'kinds' => 'Artikel jurnal open access',
            'description' => 'Direktori jurnal open access yang dikurasi; semua artikel berstatus open access.',
            'host' => 'doaj.org',
            'docs_url' => 'https://doaj.org/api/docs',
            'key' => 'none',
            'key_note' => 'Tidak perlu key untuk pencarian. Batas penyedia 2 permintaan per detik.',
            'filters' => ['year', 'language'],
            'types' => ['article'],
            'min_interval_ms' => 600,
            'page_size' => 50,
            'search_cap' => 10000,
            'modes' => ['quick', 'category', 'all_mapped', 'corpus', 'incremental'],
            'mode_notes' => [
                'corpus' => 'Korpus per subjek (set LCC) lewat OAI-PMH resmi DOAJ, 300 rekaman/halaman dengan resumptionToken. Feed gratis tertunda hingga 30 hari dari data langsung.',
                'incremental' => 'Perubahan sejak tanggal tertentu lewat OAI-PMH (`from`), per subjek. Feed gratis tertunda hingga 30 hari; DOAJ Premium (API key) lebih segar dan belum dipakai.',
            ],
            'bulk' => 'OAI-PMH (oai.article, format oai_doaj). Public data dump DOAJ hanya lewat permintaan manual ke DOAJ, sehingga tidak diotomatisasi.',
        ];
    }

    public function fetchPage(string $term, array $filters, ?string $cursor, int $pageSize, array $settings): ExternalPage
    {
        if (in_array($filters['mode'] ?? '', ['corpus', 'incremental'], true)) {
            return $this->fetchOai($filters, $cursor, $settings);
        }

        // Dua fase deterministik (urutan bawaan DOAJ bukan skor relevansi, jadi bobot kueri tidak berpengaruh):
        // T = frasa ada di JUDUL; A = frasa hanya ada di ABSTRAK. Tidak ada yang dibuang, hanya diurutkan: abstrak-saja datang setelah judul habis.
        [$phase, $page] = preg_match('/^([TA]):(\d+)$/', (string) $cursor, $m) ? [$m[1], max(1, (int) $m[2])] : ['T', 1];
        $size = max(1, min(50, $pageSize));

        $filter = '';
        if (! empty($filters['year_from']) || ! empty($filters['year_to'])) {
            $filter .= ' AND bibjson.year:['.($filters['year_from'] ?? 1000).' TO '.($filters['year_to'] ?? 2200).']';
        }
        if (! empty($filters['language'])) {
            $filter .= ' AND bibjson.journal.language:"'.strtoupper(preg_replace('/[^a-z]/', '', $filters['language'])).'"';
        }

        for ($i = 0; $i < 2; $i++) {
            if ($page * $size <= 10000) {
                $q = ($phase === 'T' ? '(bibjson.title:"'.$term.'")' : '(bibjson.abstract:"'.$term.'" AND NOT bibjson.title:"'.$term.'")').$filter;
                $body = $this->http()->get('https://doaj.org/api/search/articles/'.rawurlencode($q), ['page' => $page, 'pageSize' => $size], $this->noToken($settings))->json() ?? [];
                $results = $body['results'] ?? [];
            } else {
                $body = [];
                $results = [];
            }

            if ($results === [] && $phase === 'T') {
                [$phase, $page] = ['A', 1];

                continue;
            }
            break;
        }

        $records = collect($results)->map(fn (array $r) => $this->map($r))->filter()->values()->all();
        // shortcut: total mengikuti fase yang sedang dibaca (judul, lalu abstrak-saja), bukan jumlah gabungan keduanya.
        $total = isset($body['total']) ? (int) $body['total'] : null;
        $more = count($results) >= $size && ($total === null || $page * $size < $total);

        return new ExternalPage($records, $more ? "{$phase}:".($page + 1) : ($phase === 'T' ? 'A:1' : null), $total);
    }

    /**
     * OAI-PMH ListRecords (oai_doaj). Kursor = resumptionToken persis dari penyedia; hanya set (subjek) dan `from` (incremental)
     * yang dikirim pada permintaan pertama. noRecordsMatch = hasil kosong yang sah; badResumptionToken (kedaluwarsa 24 jam) = berhenti jelas.
     */
    private function fetchOai(array $filters, ?string $cursor, array $settings): ExternalPage
    {
        $query = $cursor
            ? ['verb' => 'ListRecords', 'resumptionToken' => $cursor]
            : array_filter(['verb' => 'ListRecords', 'metadataPrefix' => 'oai_doaj', 'set' => $filters['set'] ?? null, 'from' => $filters['since'] ?? null]);

        $xml = $this->http()->get('https://doaj.org/oai.article', $query, $this->noToken($settings), 'text/xml')->body();
        $parsed = OaiPmhParser::parseArticles($xml);

        if ($parsed['error']) {
            if ($parsed['error']['code'] === 'noRecordsMatch') {
                return new ExternalPage([], null, 0);
            }

            throw new ExternalSourceException('OAI-PMH DOAJ: '.$parsed['error']['code'].($parsed['error']['code'] === 'badResumptionToken' ? ' (token kedaluwarsa; mulai ulang kampanye, dedup menjaga data tetap aman)' : ''), 'bad_request');
        }

        $records = collect($parsed['records'])->map(fn (array $r) => Normalize::finish($this->code(), $r))->filter()->values()->all();

        return new ExternalPage($records, $parsed['token'], $parsed['total'], 1, $parsed['skipped'] + (count($parsed['records']) - count($records)));
    }

    /** @return array<int, array{spec: string, name: string}> subjek (set LCC) yang tersedia di DOAJ; di-cache 1 hari. */
    public function listSets(array $settings = []): array
    {
        return \Illuminate\Support\Facades\Cache::remember('external-sources:doaj:sets', now()->addDay(), function () use ($settings) {
            $xml = $this->http()->get('https://doaj.org/oai.article', ['verb' => 'ListSets'], $this->noToken($settings), 'text/xml')->body();
            $parsed = OaiPmhParser::parseSets($xml);

            if ($parsed['error']) {
                throw new ExternalSourceException('OAI-PMH DOAJ ListSets: '.$parsed['error']['code'], 'failed');
            }

            return $parsed['sets'];
        });
    }

    private function noToken(array $settings): array
    {
        unset($settings['api_key']);

        return $settings;
    }

    private function map(array $r): ?array
    {
        $b = $r['bibjson'] ?? [];
        $ids = collect($b['identifier'] ?? []);
        $journal = $b['journal'] ?? [];
        $link = collect($b['link'] ?? [])->firstWhere('type', 'fulltext')['url'] ?? ($b['link'][0]['url'] ?? null);
        $month = (int) ($b['month'] ?? 0);
        $year = Normalize::year($b['year'] ?? null);

        return Normalize::finish($this->code(), [
            'external_id' => (string) ($r['id'] ?? ''),
            'doi' => $ids->firstWhere('type', 'doi')['id'] ?? null,
            'issn' => $ids->filter(fn ($i) => in_array($i['type'] ?? '', ['pissn', 'eissn'], true))->pluck('id')->all(),
            'title' => $b['title'] ?? null,
            'authors' => collect($b['author'] ?? [])->pluck('name')->filter()->all(),
            'year' => $year,
            'published_date' => $year && $month >= 1 && $month <= 12 ? sprintf('%04d-%02d-01', $year, $month) : null,
            'publisher' => $journal['publisher'] ?? null,
            'container_title' => $journal['title'] ?? null,
            'abstract' => $b['abstract'] ?? null,
            'keywords' => $b['keywords'] ?? [],
            'language' => $journal['language'][0] ?? null,
            // Indeks DOAJ hanya berisi artikel jurnal; tanpa blok jurnal rekaman tidak diakui sebagai artikel (tidak dibuang, hanya bertipe 'other').
            'type' => ! empty($journal['title']) ? 'article' : 'other',
            'landing_url' => ! empty($r['id']) ? 'https://doaj.org/article/'.$r['id'] : null,
            'fulltext_url' => $link,
            'license' => $journal['license'][0]['type'] ?? null,
            'oa_status' => 'open',
        ]);
    }
}
