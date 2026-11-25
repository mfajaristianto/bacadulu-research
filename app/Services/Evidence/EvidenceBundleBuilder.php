<?php

namespace App\Services\Evidence;

use App\Models\Category;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\ExternalFulltext;
use App\Models\ExternalPublication;
use App\Services\Discovery\QueryAnalyzer;
use App\Services\Discovery\SourceDiscovery;
use App\Services\FullText\ExternalEvidence;
use App\Services\FullText\FullTextService;
use App\Services\FullText\FullTextState;
use App\Services\Research\KeywordRetriever;
use Illuminate\Support\Collection;

/**
 * Membangun Evidence Bundle dari Source Discovery. Alur (tanpa AI, tanpa unduhan, tanpa Full-Text Acquisition otomatis):
 *   1. Source Discovery (analisis query deterministik + peringkat RELEVANSI, Pustaka dan eksternal bersaing apa adanya)
 *   2. ambil sumber teratas (urutan relevansi tidak diubah oleh ketersediaan PDF)
 *   3. per sumber: chunk teks (Pustaka / full-text eksternal terindeks) -> jika tidak ada, abstrak -> jika tidak ada, metadata saja
 *      (metadata TIDAK pernah menghasilkan teks evidence)
 *   4. anggaran: bergiliran antar sumber (satu sumber tidak membanjiri), dibatasi jumlah chunk, per sumber, dan karakter
 *   5. peta sitasi + keterbatasan otomatis
 */
final class EvidenceBundleBuilder
{
    public function __construct(
        private SourceDiscovery $discovery,
        private QueryAnalyzer $analyzer,
        private KeywordRetriever $keyword,
        private ExternalEvidence $external,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>|null  $discovered  hasil SourceDiscovery::discover bila sudah ada (menghindari pencarian ganda)
     */
    public function build(int $categoryId, string $query, array $filters = [], ?array $discovered = null): ResearchEvidenceBundle
    {
        $cfg = (array) config('research.evidence');
        $query = trim($query);
        $category = Category::query()->where('status', 'active')->find($categoryId);

        $result = $category && $query !== '' ? ($discovered ?? $this->discovery->discover($categoryId, $query, $filters)) : ['results' => [], 'analysis' => $this->analyzer->analyze($query), 'counts' => []];
        $analysis = $result['analysis'] ?? $this->analyzer->analyze($query);

        [$sources, $duplicates] = $this->pick($result['results'] ?? [], $categoryId, (int) $cfg['max_publications']);

        // ---- kandidat evidence per sumber ---------------------------------------------------------------------------------
        $candidates = [];   // source_id => list<item>
        $info = [];         // source_id => keterangan tambahan (lisensi, tersedia teks lengkap, catatan)
        $scanLimited = false;

        $internal = array_values(array_filter($sources, fn ($s) => $s['kind'] === 'internal'));
        $external = array_values(array_filter($sources, fn ($s) => $s['kind'] === 'external'));

        // Pustaka Internal
        if ($internal !== []) {
            $docs = Document::query()->whereIn('id', array_column($internal, 'id'))->get()->keyBy('id');
            $files = DocumentFile::query()->whereIn('document_id', $docs->keys())->orderBy('id')->get(['document_id', 'checksum'])->groupBy('document_id')->map(fn ($g) => $g->last()->checksum);
            $hits = $this->keyword->retrieve($docs->keys()->all(), $query);
            $scanLimited = $this->keyword->wasScanLimited();
            $byDoc = $hits->groupBy(fn ($h) => (int) $h['chunk']->document_id);

            foreach ($internal as $s) {
                $doc = $docs[$s['id']];
                $sid = CitationId::source('internal', $s['id']);
                $info[$sid] = ['fulltext_indexed' => true, 'license' => null, 'note' => null];
                foreach ($byDoc->get($s['id'], collect()) as $h) {
                    $c = $h['chunk'];
                    $page = isset($c->metadata_json['page']) ? (int) $c->metadata_json['page'] : null;
                    $candidates[$sid][] = $this->item($s, Evidence::KIND_INTERNAL, Evidence::FULL_TEXT, (string) $c->content, [
                        'chunk' => (int) $c->chunk_index, 'page_start' => $page, 'page_end' => $page, 'section' => null,
                        'score' => $this->rescore((string) $c->content, (float) $h['score'], $analysis, (string) $s['title']),
                        'source_checksum' => $files->get($s['id']) ?: $doc->external_checksum, 'provider' => 'pustaka',
                        'provenance' => ['provider' => 'pustaka', 'document_id' => $doc->id, 'chunk_id' => $c->id, 'retrieval' => 'keyword'],
                    ]);
                }
                if (empty($candidates[$sid]) && $s['evidence'] !== 'metadata' && mb_strlen(trim((string) $s['abstract'])) >= 40) {
                    $candidates[$sid][] = $this->abstractItem($s, Evidence::KIND_INTERNAL, $doc->external_checksum, 'pustaka');
                }
            }
        }

        // Sumber Eksternal
        if ($external !== []) {
            $ids = array_column($external, 'id');
            $pubs = ExternalPublication::query()->whereIn('id', $ids)->get(['id', 'checksum'])->keyBy('id');
            $indexed = FullTextService::ready()
                ? ExternalFulltext::query()->whereIn('publication_id', $ids)->where('state', FullTextState::INDEXED)->orderBy('id')->get()->unique('publication_id')->keyBy('publication_id')
                : collect();

            $titles = [];
            foreach ($external as $s) {
                $titles[$s['id']] = (string) $s['title'];
            }
            $hits = $indexed->isEmpty() ? collect() : $this->external->retrieve(
                $indexed->keys()->all(), $query, (int) $cfg['max_per_source'], count($indexed) * (int) $cfg['max_per_source'],
                fn (string $content, float $base, int $pid) => $this->rescore($content, $base, $analysis, $titles[$pid] ?? ''),
            )->groupBy(fn ($h) => (int) $h['chunk']->publication_id);

            foreach ($external as $s) {
                $sid = CitationId::source('external', $s['id']);
                $ft = $indexed->get($s['id']);
                $info[$sid] = ['fulltext_indexed' => $ft !== null, 'license' => $ft?->license_code, 'note' => null];
                $checksum = $pubs->get($s['id'])?->checksum;

                foreach (($hits->get($s['id']) ?? collect()) as $h) {
                    $c = $h['chunk'];
                    $candidates[$sid][] = $this->item($s, Evidence::KIND_EXTERNAL_FULLTEXT, Evidence::FULL_TEXT, (string) $c->content, [
                        'chunk' => (int) $c->chunk_index, 'page_start' => $c->page_start, 'page_end' => $c->page_end, 'section' => $c->section,
                        'score' => round((float) $h['score'], 3), 'source_checksum' => $c->source_checksum, 'chunk_checksum' => $c->content_sha, 'provider' => $ft->provider, 'license' => $ft->license_code,
                        'provenance' => ['provider' => $ft->provider, 'fulltext_id' => $ft->id, 'chunk_id' => $c->id, 'extraction_method' => $ft->extraction_method, 'version' => $ft->version, 'retrieval' => 'keyword'],
                    ]);
                }
                if (empty($candidates[$sid])) {
                    if ($s['evidence'] === 'abstract') {
                        $candidates[$sid][] = $this->abstractItem($s, Evidence::KIND_EXTERNAL_ABSTRACT, $checksum, (string) ($s['providers'][0] ?? 'external'));
                        $ft && $info[$sid]['note'] = 'Teks lengkap terindeks, tetapi tidak ada bagian yang relevan dengan pertanyaan; hanya abstrak yang dipakai.';
                    }
                }
            }
        }

        // ---- anggaran: bergiliran antar sumber, urutan = urutan relevansi sumber --------------------------------------------
        [$selected, $dropped, $chars] = $this->budget($sources, $candidates, $cfg);

        // ---- evidence_id, sumber, peta sitasi -----------------------------------------------------------------------------
        $terms = array_values(array_unique([...($analysis['phrases'] ?? []), ...($analysis['core'] ?? [])]));
        $items = [];
        foreach ($selected as $n => $it) {
            $it['snippet'] = self::snippet($it['text'], $terms, (int) $cfg['snippet_chars']);
            $items[] = ['evidence_id' => sprintf('EV%02d', $n + 1)] + $it;
        }

        $outSources = [];
        $map = [];
        foreach ($sources as $s) {
            $sid = CitationId::source($s['kind'], $s['id']);
            $mine = array_values(array_filter($items, fn ($i) => $i['source_id'] === $sid));
            $level = $mine === [] ? Evidence::METADATA_ONLY : (in_array(Evidence::FULL_TEXT, array_column($mine, 'evidence_level'), true) ? Evidence::FULL_TEXT : Evidence::ABSTRACT);
            $support = Evidence::supportFor($level);
            $pages = array_values(array_filter(array_unique(array_map(fn ($i) => Evidence::pageLabel($i['page_start'], $i['page_end']), $mine))));

            $outSources[] = [
                'source_id' => $sid, 'kind' => $s['kind'], 'id' => $s['id'], 'key' => $s['key'], 'rank' => $s['rank'], 'relevance_score' => $s['score'],
                'title' => $s['title'], 'authors' => $s['authors'], 'year' => $s['year'], 'container' => $s['container'] ?? null, 'doi' => $s['doi'],
                'url' => $s['doi_url'] ?? $s['landing_url'] ?? null, 'providers' => $s['providers'],
                'availability' => ['abstract' => $s['evidence'] !== 'metadata', 'full_text_indexed' => $info[$sid]['fulltext_indexed'] ?? false],
                'evidence_level' => $level, 'evidence_label' => Evidence::LEVEL_LABELS[$level], 'claim_support' => $support, 'claim_support_label' => Evidence::SUPPORT_LABELS[$support],
                'evidence_ids' => array_column($mine, 'evidence_id'), 'pages' => $pages, 'license' => $info[$sid]['license'] ?? null, 'note' => $info[$sid]['note'] ?? null,
                'also_available_as' => $duplicates[$s['key']] ?? [],
            ];

            $map[$sid] = ['type' => 'source', 'source_id' => $sid, 'evidence_level' => $level, 'claim_support' => $support, 'allowed_claims' => [Evidence::CLAIM_BIBLIOGRAPHIC], 'page_start' => null, 'page_end' => null];
            foreach ($mine as $i) {
                $map[$i['citation_id']] = ['type' => 'evidence', 'source_id' => $sid, 'evidence_id' => $i['evidence_id'], 'evidence_level' => $i['evidence_level'], 'claim_support' => Evidence::supportFor($i['evidence_level']),
                    'allowed_claims' => Evidence::claimsFor($i['evidence_level']), 'page_start' => $i['page_start'], 'page_end' => $i['page_end']];
            }
        }

        $summary = $this->summary($outSources, $items, $chars);

        return new ResearchEvidenceBundle([
            'query' => $query,
            'category' => $category ? ['id' => $category->id, 'name' => $category->name] : null,
            'analysis' => ['core' => $analysis['core'] ?? [], 'phrases' => $analysis['phrases'] ?? []],
            'selected_sources' => $outSources,
            'evidence_items' => $items,
            'citation_map' => $map,
            'summary' => $summary,
            'limitations' => $this->limitations($outSources, $items, $dropped, $scanLimited, $result['counts']['exact'] ?? true, $category !== null && $query !== ''),
            'limitations_must_be_preserved' => true,
            'budget' => ['max_publications' => (int) $cfg['max_publications'], 'max_chunks' => (int) $cfg['max_chunks'], 'max_per_source' => (int) $cfg['max_per_source'], 'max_chars' => (int) $cfg['max_chars'], 'used_chars' => $chars, 'dropped_relevant_chunks' => $dropped],
        ]);
    }

    /**
     * Sumber teratas menurut RELEVANSI (urutan Source Discovery, tidak diubah ketersediaan PDF), tanpa duplikat (DOI atau kunci sama).
     * Dokumen Pustaka yang tidak lagi layak untuk riset (privat, draf, kategori lain) dibuang.
     *
     * @return array{0: list<array<string, mixed>>, 1: array<string, list<string>>}
     */
    private function pick(array $results, int $categoryId, int $max): array
    {
        $internalIds = array_column(array_filter($results, fn ($r) => $r['kind'] === 'internal'), 'id');
        $eligible = $internalIds === [] ? [] : Document::query()->eligibleForResearch()->where('category_id', $categoryId)->whereIn('id', $internalIds)->pluck('id')->flip()->all();

        $picked = [];
        $seen = [];
        $dups = [];
        foreach ($results as $r) {
            if ($r['kind'] === 'internal' && ! isset($eligible[$r['id']])) {
                continue;
            }
            $dedupe = $r['doi'] ? 'doi:'.strtolower((string) $r['doi']) : $r['key'];
            if (isset($seen[$dedupe])) {
                $dups[$seen[$dedupe]][] = CitationId::source($r['kind'], $r['id']);

                continue;
            }
            if (count($picked) >= $max) {
                break;
            }
            $seen[$dedupe] = $r['key'];
            $picked[] = ['rank' => count($picked) + 1] + $r;
        }

        return [$picked, $dups];
    }

    /** @return array<string, mixed> */
    private function item(array $s, string $kind, string $level, string $text, array $o): array
    {
        $text = trim($text);

        return [
            'source_kind' => $kind, 'source_id' => CitationId::source($s['kind'], $s['id']), 'publication_id' => $s['id'],
            'citation_id' => $o['chunk'] !== null ? CitationId::chunk($s['kind'], $s['id'], (int) $o['chunk'], $o['page_start'], $o['page_end']) : CitationId::abstract($s['kind'], $s['id']),
            'title' => $s['title'], 'authors' => $s['authors'], 'year' => $s['year'], 'doi' => $s['doi'], 'provider' => $o['provider'] ?? ($s['providers'][0] ?? null),
            'source_url' => $s['doi_url'] ?? $s['landing_url'] ?? null, 'evidence_level' => $level, 'section' => $o['section'] ?? null,
            'page_start' => $o['page_start'], 'page_end' => $o['page_end'], 'text' => $text, 'snippet' => '',
            'source_checksum' => $o['source_checksum'] ?? null, 'chunk_checksum' => $o['chunk_checksum'] ?? sha1($text), 'license' => $o['license'] ?? null,
            'provenance' => $o['provenance'] ?? [], 'relevance' => ['source_rank' => $s['rank'], 'source_score' => $s['score'], 'chunk_score' => $o['score'] ?? null],
            'claim_support' => Evidence::supportFor($level), 'allowed_claims' => Evidence::claimsFor($level),
        ];
    }

    /** @return array<string, mixed> */
    private function abstractItem(array $s, string $kind, ?string $checksum, string $provider): array
    {
        $text = trim((string) $s['abstract']);
        $it = $this->item($s, $kind, Evidence::ABSTRACT, $text, [
            'chunk' => null, 'page_start' => null, 'page_end' => null, 'section' => 'abstract', 'score' => null, 'source_checksum' => $checksum, 'provider' => $provider,
            'provenance' => ['provider' => $provider, 'retrieval' => 'abstract'],
        ]);
        $it['citation_id'] = CitationId::abstract($s['kind'], $s['id']);

        return $it;
    }

    /**
     * Skor chunk relatif (bukan probabilitas): skor dasar retriever + bonus frasa persis (maks 3 x 0,05) + konteks judul (maks 0,03).
     * Menjinakkan skor dasar yang jenuh di 0,95. Bagian artikel TIDAK dipakai (label bagian sering tidak terpercaya; halaman yang dipakai).
     */
    private function rescore(string $content, float $base, array $analysis, string $title): float
    {
        $c = mb_strtolower(preg_replace('/\s+/u', ' ', $content) ?? $content);
        $phrase = count(array_filter($analysis['phrases'] ?? [], fn ($p) => mb_strpos($c, (string) $p) !== false));
        $t = mb_strtolower($title);
        $titleTerms = array_values(array_filter($analysis['core'] ?? [], fn ($w) => mb_strpos($t, (string) $w) !== false));
        $ctx = $titleTerms === [] ? 0.0 : count(array_filter($titleTerms, fn ($w) => mb_strpos($c, (string) $w) !== false)) / count($titleTerms);

        return round($base + 0.05 * min(3, $phrase) + 0.03 * $ctx, 3);
    }

    /**
     * @param  list<array<string, mixed>>  $sources
     * @param  array<string, list<array<string, mixed>>>  $candidates
     * @return array{0: list<array<string, mixed>>, 1: int, 2: int} item terpilih, jumlah chunk relevan yang tidak muat, karakter terpakai
     */
    private function budget(array $sources, array $candidates, array $cfg): array
    {
        $perSource = [];
        $total = 0;
        foreach ($sources as $s) {
            $sid = CitationId::source($s['kind'], $s['id']);
            $list = $candidates[$sid] ?? [];
            usort($list, fn ($a, $b) => ($b['relevance']['chunk_score'] ?? 0) <=> ($a['relevance']['chunk_score'] ?? 0)
                ?: ($a['page_start'] ?? PHP_INT_MAX) <=> ($b['page_start'] ?? PHP_INT_MAX) ?: strcmp($a['citation_id'], $b['citation_id']));
            $perSource[$sid] = array_values($list);
            $total += count($list);
        }

        $selected = [];
        $chars = 0;
        $maxChunks = (int) $cfg['max_chunks'];
        $maxChars = (int) $cfg['max_chars'];
        for ($round = 0; $round < (int) $cfg['max_per_source']; $round++) {
            foreach ($sources as $s) {
                $sid = CitationId::source($s['kind'], $s['id']);
                $it = $perSource[$sid][$round] ?? null;
                if ($it === null || count($selected) >= $maxChunks) {
                    continue;
                }
                $len = mb_strlen($it['text']);
                if ($chars + $len > $maxChars) {
                    continue; // tidak muat anggaran karakter
                }
                $it['_round'] = $round;
                $selected[] = $it;
                $chars += $len;
            }
        }

        // Urutan akhir: menurut urutan relevansi sumber, lalu urutan dalam sumber.
        usort($selected, fn ($a, $b) => $a['relevance']['source_rank'] <=> $b['relevance']['source_rank'] ?: $a['_round'] <=> $b['_round']);
        $selected = array_map(function ($i) {
            unset($i['_round']);

            return $i;
        }, $selected);

        $cap = (int) $cfg['max_per_source'];
        $considered = array_sum(array_map(fn ($l) => min(count($l), $cap), $perSource));

        return [$selected, max(0, $considered - count($selected)), $chars];
    }

    /** @return array<string, mixed> */
    private function summary(array $sources, array $items, int $chars): array
    {
        $count = fn (string $level) => count(array_filter($sources, fn ($s) => $s['evidence_level'] === $level));
        $full = $count(Evidence::FULL_TEXT);
        $abs = $count(Evidence::ABSTRACT);
        $meta = $count(Evidence::METADATA_ONLY);
        $n = count($sources);
        $parts = array_filter([$full ? "{$full} teks lengkap" : null, $abs ? "{$abs} abstrak" : null, $meta ? "{$meta} hanya metadata" : null]);

        return [
            'sources' => $n, 'full_text' => $full, 'abstract' => $abs, 'metadata_only' => $meta,
            'internal' => count(array_filter($sources, fn ($s) => $s['kind'] === 'internal')), 'external' => count(array_filter($sources, fn ($s) => $s['kind'] === 'external')),
            'evidence_items' => count($items), 'chars' => $chars,
            'text' => $n === 0 ? 'Tidak ada referensi relevan.' : "{$n} referensi ditemukan; bukti: ".implode(', ', $parts).'.',
        ];
    }

    /** @return list<string> */
    private function limitations(array $sources, array $items, int $dropped, bool $scanLimited, bool $exact, bool $searched): array
    {
        $n = count($sources);
        $by = fn (string $level) => count(array_filter($sources, fn ($s) => $s['evidence_level'] === $level));
        $out = [];

        if ($n === 0) {
            return [$searched ? 'Tidak ada referensi relevan pada kategori ini; tidak ada bukti yang dapat diberikan.' : 'Pertanyaan atau kategori belum valid; tidak ada bukti yang dapat diberikan.'];
        }
        if (($a = $by(Evidence::ABSTRACT)) > 0) {
            $out[] = "{$a} dari {$n} sumber hanya tersedia dalam bentuk abstrak: klaim dibatasi pada apa yang ditulis di abstrak, bukan isi lengkap artikel.";
        }
        if (($m = $by(Evidence::METADATA_ONLY)) > 0) {
            $out[] = "{$m} dari {$n} sumber hanya memiliki metadata dan tidak digunakan untuk klaim substansial (metode, hasil, atau kesimpulan); hanya boleh dirujuk sebagai informasi bibliografi.";
        }
        $fullItems = count(array_filter($items, fn ($i) => $i['evidence_level'] === Evidence::FULL_TEXT));
        if (($f = $by(Evidence::FULL_TEXT)) > 0) {
            $out[] = "Teks lengkap tersedia untuk {$f} sumber ({$fullItems} potongan teks); klaim isi hanya boleh merujuk potongan yang dikutip beserta halamannya.";
        } else {
            $out[] = 'Tidak ada teks lengkap yang dipakai sebagai bukti; sintesis mendalam tidak boleh bersandar pada isi artikel di luar abstrak.';
        }
        if (($k = count(array_filter($sources, fn ($s) => $s['note'] !== null))) > 0) {
            $out[] = "{$k} sumber memiliki teks lengkap terindeks tetapi tidak ada bagian relevan dengan pertanyaan; hanya abstraknya yang dipakai.";
        }
        if (($noPage = count(array_filter($items, fn ($i) => $i['evidence_level'] === Evidence::FULL_TEXT && $i['page_start'] === null))) > 0) {
            $out[] = "{$noPage} potongan teks tidak memiliki nomor halaman; sitasinya memakai nomor potongan, bukan halaman.";
        }
        if ($dropped > 0) {
            $out[] = "Bukti dibatasi anggaran: {$dropped} potongan relevan lain tidak disertakan.";
        }
        if ($scanLimited) {
            $out[] = 'Pemindaian teks Pustaka mencapai batas pengaman; bukti mungkin belum lengkap.';
        }
        if (! $exact) {
            $out[] = 'Pencarian sangat luas; daftar referensi mungkin belum lengkap.';
        }

        return $out;
    }

    /** Cuplikan relevan (untuk tampilan) di sekitar istilah pertanyaan pertama yang ditemukan. */
    public static function snippet(string $text, array $terms, int $length): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        $lower = mb_strtolower($text);
        $pos = null;
        foreach ($terms as $t) {
            if (($p = mb_strpos($lower, mb_strtolower((string) $t))) !== false && ($pos === null || $p < $pos)) {
                $pos = $p;
            }
        }
        $start = max(0, min(($pos ?? 0) - (int) ($length / 4), mb_strlen($text) - $length));
        $slice = mb_substr($text, $start, $length);
        // Potong di batas kata, bukan di tengah kata.
        if ($start > 0 && ($sp = mb_strpos($slice, ' ')) !== false && $sp < 24) {
            $slice = mb_substr($slice, $sp + 1);
        }
        if ($start + $length < mb_strlen($text) && ($sp = mb_strrpos($slice, ' ')) !== false && $sp > $length * 0.7) {
            $slice = mb_substr($slice, 0, $sp);
        }
        $slice = trim($slice);

        return ($start > 0 ? '… ' : '').$slice.($start + $length < mb_strlen($text) ? ' …' : '');
    }
}
