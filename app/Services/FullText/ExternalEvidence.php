<?php

namespace App\Services\FullText;

use App\Models\ExternalFulltext;
use App\Models\ExternalFulltextChunk;
use App\Models\ExternalPublication;
use App\Services\Research\KeywordRetriever;
use Illuminate\Support\Collection;

/**
 * Bukti (evidence) dari full-text eksternal untuk sintesis AI di tahap berikutnya. TIDAK memanggil AI dan belum disambungkan ke
 * pipeline research. Menyediakan: (1) pengambilan chunk relevan tanpa bias urutan ID, (2) paket bukti per publikasi dengan tingkat
 * ketersediaan, lokasi persis, dan batas klaim yang boleh dibuat.
 *
 * Tingkat: full_text (chunk terindeks), abstract (hanya isi abstrak), metadata (identitas bibliografi saja).
 */
final class ExternalEvidence
{
    public function __construct(private KeywordRetriever $scorer) {}

    /**
     * Chunk relevan (bukan bagian daftar pustaka), terbaik per publikasi. Seluruh chunk terindeks yang cocok dinilai (streaming);
     * hanya yang berstatus INDEXED yang dipakai.
     *
     * $rescore (opsional): fn(string $content, float $base, int $publicationId): float menyempurnakan skor SEBELUM pemotongan per publikasi
     * (skor dasar jenuh di 0,95 sehingga banyak chunk setara). Urutan setara: halaman lebih awal, lalu nomor chunk; ID hanya penentu akhir.
     *
     * @param  list<int>  $publicationIds
     * @param  (callable(string, float, int): float)|null  $rescore
     * @return Collection<int, array{chunk: ExternalFulltextChunk, score: float}>
     */
    public function retrieve(array $publicationIds, string $query, int $perPublication = 3, int $limit = 8, ?callable $rescore = null): Collection
    {
        $terms = $this->scorer->queryTerms($query);
        if ($publicationIds === [] || $terms === [] || ! FullTextService::ready()) {
            return collect();
        }

        $min = max(0.0, (float) config('research.retrieval.minimum_score', 0.15));
        $needTerms = count($terms) >= 3 ? 2 : 1;
        $perPub = [];
        $order = fn ($x, $y) => $y['score'] <=> $x['score'] ?: ($x['page'] <=> $y['page']) ?: ($x['idx'] <=> $y['idx']) ?: ($x['id'] <=> $y['id']);

        $stream = ExternalFulltextChunk::query()->select(['id', 'publication_id', 'content', 'page_start', 'chunk_index'])
            ->whereIn('publication_id', $publicationIds)->where('is_references', false)
            ->whereIn('fulltext_id', ExternalFulltext::query()->select('id')->where('state', FullTextState::INDEXED))
            ->where(function ($q) use ($terms): void {
                foreach (array_slice($terms, 0, 12) as $t) {
                    $q->orWhere('content', 'like', '%'.$t.'%');
                }
            })->lazyById(500);

        foreach ($stream as $c) {
            $a = $this->scorer->analyseMatch($c->content, $terms, $query);
            if ($a['matched_term_count'] < $needTerms || $a['score'] < $min) {
                continue;
            }
            $score = $rescore ? $rescore($c->content, (float) $a['score'], (int) $c->publication_id) : (float) $a['score'];
            $bucket = &$perPub[$c->publication_id];
            $bucket[] = ['id' => (int) $c->id, 'score' => $score, 'page' => $c->page_start ?? PHP_INT_MAX, 'idx' => (int) $c->chunk_index];
            if (count($bucket) > $perPublication) {
                usort($bucket, $order);
                $bucket = array_slice($bucket, 0, $perPublication);
            }
            unset($bucket);
        }

        $kept = collect($perPub)->flatten(1)->sort($order)->take($limit)->values();
        $models = ExternalFulltextChunk::query()->with('fulltext:id,url,provider,version,license_code,sha256')->whereIn('id', $kept->pluck('id')->all())->get()->keyBy('id');

        return $kept->map(fn ($r) => ['chunk' => $models[$r['id']], 'score' => $r['score']])->values();
    }

    /**
     * Paket bukti satu publikasi. `supported_claims` sengaja kosong: sintesis AI nanti mengisinya, dan tiap klaim wajib menunjuk
     * chunk_id yang ada di paket ini (lihat supportsCitation).
     *
     * @return array<string, mixed>
     */
    public function bundle(ExternalPublication $p, string $query = ''): array
    {
        $indexed = FullTextService::ready()
            ? ExternalFulltext::query()->where('publication_id', $p->id)->where('state', FullTextState::INDEXED)->orderBy('id')->first()
            : null;
        $hasAbstract = mb_strlen(trim((string) $p->abstract)) >= 40;
        $level = $indexed ? 'full_text' : ($hasAbstract ? 'abstract' : 'metadata');

        $chunks = [];
        if ($indexed && $query !== '') {
            foreach ($this->retrieve([$p->id], $query) as $r) {
                /** @var ExternalFulltextChunk $c */
                $c = $r['chunk'];
                $chunks[] = [
                    'chunk_id' => $c->id, 'fulltext_id' => $c->fulltext_id, 'section' => $c->section, 'page_start' => $c->page_start, 'page_end' => $c->page_end,
                    'locator' => self::locator($c), 'text' => $c->content, 'content_sha' => $c->content_sha, 'source_checksum' => $c->source_checksum, 'score' => $r['score'],
                ];
            }
        }

        return [
            'publication' => ['id' => $p->id, 'doi' => $p->doi, 'title' => $p->title, 'authors' => (array) $p->authors_json, 'year' => $p->publication_year, 'container' => $p->container_title],
            'citation' => [
                'identifier' => $p->doi ? 'doi:'.$p->doi : 'ext:'.$p->id,
                'openalex_id' => $p->sources()->where('provider', 'openalex')->value('external_id'),
                'url' => $p->landing_url, 'license' => $indexed?->license_code, 'attribution' => $indexed?->attribution,
            ],
            'level' => $level,
            'claim_scope' => match ($level) {
                'full_text' => 'Klaim boleh merujuk teks chunk yang dikutip, dengan lokasi (halaman/bagian) tertera.',
                'abstract' => 'Klaim dibatasi pada isi abstrak; jangan menyimpulkan metode, hasil rinci, atau kesimpulan di luar abstrak.',
                default => 'Hanya identitas bibliografi; jangan membuat klaim tentang metode, hasil, atau kesimpulan.',
            },
            'abstract' => $level === 'metadata' ? null : ($hasAbstract ? $p->abstract : null),
            'chunks' => $chunks,
            'supported_claims' => [],
        ];
    }

    /** Klaim hanya sah bila semua chunk yang dikutip ada di paket dan tingkat bukti full_text. */
    public static function supportsCitation(array $bundle, array $chunkIds): bool
    {
        if (($bundle['level'] ?? null) !== 'full_text' || $chunkIds === []) {
            return false;
        }
        $have = array_column($bundle['chunks'] ?? [], 'chunk_id');

        return array_diff($chunkIds, $have) === [];
    }

    public static function locator(ExternalFulltextChunk $c): string
    {
        $page = $c->page_start === null ? null : ($c->page_end && $c->page_end !== $c->page_start ? "hlm. {$c->page_start}–{$c->page_end}" : "hlm. {$c->page_start}");

        return implode(', ', array_filter([$page, $c->section ? str_replace('_', ' ', $c->section) : null])) ?: 'lokasi tidak diketahui';
    }
}
