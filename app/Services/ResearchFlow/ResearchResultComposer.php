<?php

namespace App\Services\ResearchFlow;

use App\Models\Category;
use App\Services\Discovery\QueryAnalyzer;
use App\Services\Discovery\SourceDiscovery;
use App\Services\Evidence\EvidenceBundleBuilder;

/**
 * Menyusun hasil Research TANPA AI: gabungan Pustaka Internal + Sumber Eksternal (Source Discovery + Evidence Bundle) menjadi
 * gambaran ringkas, perbandingan per dimensi, dan referensi. Deterministik: input sama + data sama = keluaran sama.
 * Tidak memakai provider AI, tidak membuat AiRun, tidak menulis ke database. Dimensi tanpa evidence ditandai tidak tersedia.
 */
final class ResearchResultComposer
{
    public const MAX_QUESTIONS = 8;

    public function __construct(
        private SourceDiscovery $discovery,
        private EvidenceBundleBuilder $bundles,
        private DimensionExtractor $extractor,
        private QueryAnalyzer $analyzer,
    ) {}

    /**
     * @param  array{category_id: int, topic: string, questions?: list<string>, ruleset?: string, filters?: array}  $in
     * @return array<string, mixed>
     */
    public function compose(array $in): array
    {
        $topic = trim((string) $in['topic']);
        $questions = array_slice(array_values(array_filter(array_map(fn ($q) => trim((string) $q), (array) ($in['questions'] ?? [])), fn ($q) => $q !== '')), 0, self::MAX_QUESTIONS);
        $dimensions = DimensionExtractor::fromRuleset((string) ($in['ruleset'] ?? ''));
        $filters = (array) ($in['filters'] ?? []);
        $categoryId = (int) $in['category_id'];

        $main = $this->one($categoryId, $topic, $dimensions, $filters);
        $result = [
            'mode' => $questions === [] ? 'standard' : 'book',
            'category' => Category::query()->whereKey($categoryId)->value('name'),
            'topic' => $topic,
            'dimensions' => array_map(fn ($d) => ['key' => $d, 'label' => DimensionExtractor::DIMENSIONS[$d]['label']], $dimensions),
            'unavailable_text' => DimensionExtractor::UNAVAILABLE,
            'ai_used' => false,
        ] + $main;

        if ($questions === []) {
            return $result;
        }

        $perQuestion = [];
        foreach ($questions as $i => $q) {
            $sub = $this->one($categoryId, $this->subQuery($topic, $q), $dimensions, $filters);
            $perQuestion[] = ['number' => $i + 1, 'question' => $q] + $sub;
        }
        $result['questions'] = $perQuestion;
        $result['aggregate'] = $this->aggregate($perQuestion);

        return $result;
    }

    /** Sub-query satu rumusan masalah; yang terlalu pendek (< 2 istilah inti) diberi konteks topik agar tidak kosong makna. */
    public function subQuery(string $topic, string $question): string
    {
        return count($this->analyzer->analyze($question)['core'] ?? []) >= 2 ? $question : trim($topic.' '.$question);
    }

    /** @return array<string, mixed> */
    private function one(int $categoryId, string $query, array $dimensions, array $filters): array
    {
        $found = $this->discovery->discover($categoryId, $query, $filters);
        $all = $found['results'];
        $bundle = $all === [] ? null : $this->bundles->build($categoryId, $query, $filters, $found)->toArray();

        $levels = ['metadata' => 0, 'abstract' => 0, 'full_text_indexed' => 0];
        foreach ($all as $r) {
            $levels[$r['evidence']] = ($levels[$r['evidence']] ?? 0) + 1;
        }

        $byUnit = [];
        foreach (($bundle['evidence_items'] ?? []) as $it) {
            $byUnit[$it['source_id']][] = ['text' => $it['text'], 'citation_id' => $it['citation_id']];
        }

        $rows = [];
        $coverage = array_fill_keys($dimensions, 0);
        foreach (($bundle['selected_sources'] ?? []) as $s) {
            $cells = $this->extractor->extract($dimensions, $byUnit[$s['source_id']] ?? []);
            foreach ($cells as $d => $c) {
                $coverage[$d] += (int) $c['available'];
            }
            $rows[] = [
                'source_id' => $s['source_id'], 'key' => $s['key'], 'rank' => $s['rank'], 'title' => $s['title'], 'authors' => $s['authors'], 'year' => $s['year'],
                'container' => $s['container'], 'doi' => $s['doi'], 'url' => $s['url'], 'evidence_level' => $s['evidence_level'], 'evidence_label' => $s['evidence_label'],
                'citation_ids' => array_values(array_unique([$s['source_id'], ...array_column($byUnit[$s['source_id']] ?? [], 'citation_id')])), 'cells' => $cells,
            ];
        }

        $compared = count($rows);

        return [
            'query' => $query,
            'overview' => [
                'relevant' => count($all),
                'internal' => count(array_filter($all, fn ($r) => $r['kind'] === 'internal')),
                'external' => count(array_filter($all, fn ($r) => $r['kind'] === 'external')),
                'with_abstract' => $levels['abstract'], 'with_fulltext' => $levels['full_text_indexed'], 'metadata_only' => $levels['metadata'],
                'compared' => $compared, 'truncated' => (bool) ($found['counts']['truncated'] ?? false),
            ],
            'findings' => [
                'years' => $found['years'],
                'coverage' => array_map(fn ($d) => ['key' => $d, 'label' => DimensionExtractor::DIMENSIONS[$d]['label'], 'available' => $coverage[$d], 'of' => $compared], $dimensions),
                'by_level' => array_values(array_filter([
                    ['label' => 'Full-text terindeks', 'titles' => $this->titles($all, 'full_text_indexed')],
                    ['label' => 'Memiliki abstrak', 'titles' => $this->titles($all, 'abstract')],
                    ['label' => 'Metadata saja', 'titles' => $this->titles($all, 'metadata')],
                ], fn ($g) => $g['titles'] !== [])),
            ],
            'comparison' => $rows,
            'references' => array_map(fn ($r) => ['rank' => $r['rank'], 'title' => $r['title'], 'authors' => $r['authors'], 'year' => $r['year'], 'container' => $r['container'], 'doi' => $r['doi'], 'url' => $r['url'], 'citation_id' => $r['source_id'], 'evidence_label' => $r['evidence_label']], $rows),
            'limitations' => $bundle['limitations'] ?? [],
            'empty' => $all === [],
        ];
    }

    /** @return list<string> */
    private function titles(array $all, string $level): array
    {
        return array_values(array_slice(array_map(fn ($r) => (string) $r['title'], array_filter($all, fn ($r) => $r['evidence'] === $level)), 0, 5));
    }

    /** Agregasi deterministik: sumber unik lintas rumusan masalah (kunci DOI/ID) dan jumlah per tingkat bukti. */
    private function aggregate(array $perQuestion): array
    {
        $keys = [];
        $levels = ['FULL_TEXT' => 0, 'ABSTRACT' => 0, 'METADATA_ONLY' => 0];
        foreach ($perQuestion as $q) {
            foreach ($q['comparison'] as $r) {
                if (! isset($keys[$r['key']])) {
                    $keys[$r['key']] = true;
                    $levels[$r['evidence_level']] = ($levels[$r['evidence_level']] ?? 0) + 1;
                }
            }
        }

        return [
            'unique_sources' => count($keys),
            'levels' => $levels,
            'empty_questions' => array_values(array_map(fn ($q) => $q['number'], array_filter($perQuestion, fn ($q) => $q['empty']))),
            'covered_questions' => count(array_filter($perQuestion, fn ($q) => ! $q['empty'])),
            'total_questions' => count($perQuestion),
        ];
    }
}
