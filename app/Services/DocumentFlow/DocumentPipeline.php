<?php

namespace App\Services\DocumentFlow;

use App\Models\DocumentGeneration;
use App\Services\Documents\AcademicDocumentBuilder;
use App\Services\Documents\DocumentRenderService;
use App\Services\Evidence\EvidenceBundleBuilder;
use App\Services\Evidence\ResearchEvidenceBundle;
use App\Services\ResearchFlow\ResearchResultComposer;
use Throwable;

/**
 * Generate Document per BAGIAN (bukan satu permintaan raksasa). Satu-satunya tempat AI dipanggil pada alur produk.
 *
 *  1. bundel evidence (topik + satu per rumusan masalah) dihitung ulang di server dari input pengguna;
 *  2. outline = judul bagian terpilih (checkpoint "outline", deterministik);
 *  3. tiap bagian: SectionWriterRouter (gratis dulu) -> bersihkan markdown -> validasi sitasi -> checkpoint tersimpan di
 *     DocumentGeneration (content_json berisi bagian SELESAI; settings_snapshot_json.generation berisi status, penyedia, model);
 *  4. bagian yang gagal di semua penyedia menghentikan dokumen: keluaran parsial bagian itu dibuang, bagian sebelumnya tetap;
 *  5. semua bagian selesai => render format pilihan pengguna (DOCX/PDF) lewat renderer existing.
 */
final class DocumentPipeline
{
    public function __construct(
        private EvidenceBundleBuilder $bundles,
        private ResearchResultComposer $composer,
        private SectionPlanner $planner,
        private SectionWriterRouter $router,
        private AcademicDocumentBuilder $builder,
        private DocumentRenderService $renderer,
    ) {}

    /**
     * @param  array{category_id: int, topic: string, questions: list<string>, document_type: string, citation_style: string, language: string, length: string, sections: list<string>, output_format: string, research_tool?: ?string}  $p
     */
    public function run(DocumentGeneration $g, array $p): DocumentGeneration
    {
        $mode = $p['questions'] === [] ? 'standard' : 'book';
        $categoryId = (int) $p['category_id'];

        // 1. evidence
        /** @var array<string, ResearchEvidenceBundle> $bundles */
        $bundles = ['topic' => $this->bundles->build($categoryId, $p['topic'])];
        foreach ($p['questions'] as $i => $q) {
            $bundles['q:'.($i + 1)] = $this->bundles->build($categoryId, $this->composer->subQuery($p['topic'], $q));
        }

        // 2. rencana + outline
        $plan = $this->planner->select($this->planner->plan($mode, count($p['questions'])), $p['sections']);
        $outline = array_column($plan, 'heading');
        $state = ['state' => 'generating', 'outline' => ['status' => 'done', 'provider' => 'deterministic', 'items' => $outline], 'sections' => [], 'ai_used' => true];
        $this->checkpoint($g, $p, [], [], $state, 'generating');

        $done = [];
        $previous = [];
        foreach ($plan as $s) {
            $bundle = $bundles[$s['scope']] ?? $bundles['topic'];
            $request = new SectionRequest(
                $s['key'], $s['heading'], $s['instruction'], $p['document_type'], $p['topic'], $p['questions'], $outline,
                $this->evidenceFor($bundle), $previous, $p['language'], $p['length'], SectionPlanner::LENGTHS[$p['length']] ?? 300, $p['citation_style'],
                str_starts_with($s['scope'], 'q:') ? ($p['questions'][((int) substr($s['scope'], 2)) - 1] ?? null) : null,
            );
            $res = $this->router->write($request, array_values($bundles));

            if (! $res['ok']) {
                // Keluaran parsial bagian ini TIDAK masuk dokumen; hanya bagian sebelumnya yang tersimpan.
                $state['sections'][] = ['key' => $s['key'], 'heading' => $s['heading'], 'status' => 'failed', 'attempts' => $res['attempts'], 'validation' => 'failed', 'completed_at' => now()->toIso8601String()];
                $state['state'] = 'failed';
                $this->checkpoint($g, $p, $done, $bundles, $state, 'failed', "Bagian \"{$s['heading']}\" gagal ditulis oleh semua penyedia AI yang tersedia. Bagian sebelumnya tetap tersimpan; coba lagi nanti.");

                return $g->refresh();
            }

            $done[] = ['heading' => $s['heading'], 'body' => $res['text'], 'section_role' => 'draft'];
            $previous[] = ['heading' => $s['heading'], 'body' => $res['text']];
            $state['sections'][] = [
                'key' => $s['key'], 'heading' => $s['heading'], 'status' => 'done', 'provider' => $res['provider'], 'model' => $res['model'], 'attempts' => $res['attempts'],
                'citations' => $this->citationsIn($res['text']), 'words' => str_word_count($res['text']),
                'validation' => 'passed', 'completed_at' => now()->toIso8601String(), // sitasi + keluaran divalidasi Router sebelum bagian dianggap selesai
            ];
            $this->checkpoint($g, $p, $done, $bundles, $state, 'generating');
        }

        // 5. render format pilihan
        $state['state'] = 'done';
        $this->checkpoint($g, $p, $done, $bundles, $state, 'draft');
        try {
            $this->renderer->render($g->refresh());
            $this->renderer->keepOnly($g->refresh(), $p['output_format']);
            $g->refresh()->update(['generated_at' => now()]);
        } catch (Throwable $e) {
            report($e);
            $g->update(['status' => 'failed', 'error_message' => 'Dokumen selesai ditulis tetapi gagal dicetak. Teks tersimpan; coba cetak ulang dari Dokumen Saya.']);
        }

        return $g->refresh();
    }

    /** @return list<array{citation_id: string, title: ?string, text: string, evidence_level: string}> */
    private function evidenceFor(ResearchEvidenceBundle $b): array
    {
        return array_map(fn ($i) => ['citation_id' => $i['citation_id'], 'title' => $i['title'] ?? null, 'text' => (string) $i['text'], 'evidence_level' => $i['evidence_level']], $b->items());
    }

    /** @return list<string> */
    private function citationsIn(string $text): array
    {
        preg_match_all('/\[((?:EXT|INT):[^\]\s]+)\]/', $text, $m);

        return array_values(array_unique($m[1]));
    }

    private function checkpoint(DocumentGeneration $g, array $p, array $done, array $bundles, array $state, string $status, ?string $error = null): void
    {
        $settings = (array) $g->settings_snapshot_json;
        $settings['generation'] = $state + ['updated_at' => now()->toIso8601String()];
        $update = ['settings_snapshot_json' => $settings, 'status' => $status, 'error_message' => $error];

        if ($done !== []) {
            $update['content_json'] = $this->builder->buildFromSections(
                (string) $g->title, $p['document_type'], $p['citation_style'], $done, $this->references($bundles, $p['citation_style']),
                [
                    'ai_used' => true, 'mode' => $p['questions'] === [] ? 'standard' : 'book', 'research_tool' => $p['research_tool'] ?? null, 'query' => $p['topic'],
                    'language' => $p['language'], 'providers' => array_values(array_unique(array_filter(array_column($state['sections'], 'provider')))),
                ],
            );
        }
        $g->update($update);
    }

    /** Daftar pustaka dari sumber bundel yang benar-benar dikutip bagian selesai; urutan kemunculan. @return list<string> */
    private function references(array $bundles, string $style): array
    {
        $seen = [];
        $out = [];
        foreach ($bundles as $b) {
            foreach ($b->sources() as $s) {
                if (isset($seen[$s['source_id']])) {
                    continue;
                }
                $seen[$s['source_id']] = true;
                $authors = implode(', ', array_map('strval', (array) ($s['authors'] ?? [])));
                $year = $s['year'] ?: 't.t.';
                $tail = trim(implode('. ', array_filter([$s['container'] ?? null])).($s['doi'] ? ' https://doi.org/'.$s['doi'] : ''));
                $n = count($out) + 1;
                $out[] = $style === 'IEEE'
                    ? trim("[{$n}] ".($authors !== '' ? $authors.', ' : '')."\"{$s['title']},\" {$tail}, {$year}.")
                    : trim(($authors !== '' ? $authors.' ' : '')."({$year}). {$s['title']}. {$tail}");
            }
        }

        return $out;
    }
}
