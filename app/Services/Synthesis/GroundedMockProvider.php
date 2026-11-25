<?php

namespace App\Services\Synthesis;

use App\Services\Evidence\Evidence;

/**
 * Provider uji deterministik untuk integrasi end-to-end. BUKAN model bahasa dan tidak berpura-pura menjadi satu: ia menyusun jawaban
 * dari kalimat evidence yang diberikan (kutipan verbatim), tanpa jaringan. Skenario (config research.synthesis.mock_scenario) sengaja
 * menghasilkan jawaban BURUK agar validator terbukti menangkapnya; skenario bukan opsi pengguna.
 */
final class GroundedMockProvider implements GroundedAiProvider
{
    public const SCENARIOS = ['valid', 'fake_citation', 'wrong_page', 'abstract_as_fulltext', 'metadata_substantive', 'missing_limitations',
        'malformed_json', 'schema_violation', 'source_not_in_bundle', 'uncited_marker', 'wrong_claim_type'];

    public function complete(ResearchSynthesisRequest $request): array
    {
        $payload = $request->toProviderPayload();
        $scenario = (string) config('research.synthesis.mock_scenario', 'valid');
        $scenario = in_array($scenario, self::SCENARIOS, true) ? $scenario : 'valid';
        $out = $this->valid($payload);

        $out = match ($scenario) {
            'fake_citation' => $this->addClaim($out, 'Studi lain menunjukkan hasil yang sama.', Evidence::CLAIM_ABSTRACT, 'EXT:999999:C1:P1'),
            'wrong_page' => $this->wrongPage($out),
            'abstract_as_fulltext' => $this->abstractAsFulltext($out, $payload),
            'metadata_substantive' => $this->metadataSubstantive($out, $payload),
            'missing_limitations' => ['limitations' => []] + $out,
            'source_not_in_bundle' => ['sources_used' => [...$out['sources_used'], 'EXT:424242']] + $out,
            'uncited_marker' => $this->uncitedMarker($out, $payload),
            'wrong_claim_type' => $this->addClaim($out, 'Temuan utama terbukti.', 'finding', $payload['citation_ids'][0] ?? 'EXT:1'),
            'schema_violation' => ['claims' => 'bukan-daftar', 'answer' => null] + $out,
            default => $out,
        };

        $raw = json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if ($scenario === 'malformed_json') {
            $raw = substr($raw, 0, (int) (strlen($raw) / 2));
        }

        return ['raw' => $raw, 'meta' => [
            'provider' => 'mock', 'model' => 'grounded-mock-v1', 'test_mode' => true, 'scenario' => $scenario,
            'network' => false, 'evidence_in' => count($payload['evidence'] ?? []),
        ]];
    }

    /** @return array{answer: string, claims: list<array<string, mixed>>, limitations: list<string>, sources_used: list<string>} */
    private function valid(array $p): array
    {
        $claims = [];
        $terms = $this->terms((string) ($p['user_question'] ?? ''));
        $withEvidence = [];

        foreach (array_slice($p['evidence'] ?? [], 0, 8) as $i) {
            $quote = $this->pickSentence((string) $i['text'], $terms);
            if ($quote === '') {
                continue;
            }
            $full = $i['evidence_level'] === Evidence::FULL_TEXT;
            $page = Evidence::pageLabel($i['page_start'], $i['page_end']);
            $claims[] = [
                'text' => ($full ? 'Potongan teks lengkap' : 'Abstrak')." «{$i['title']}»".($page ? " ({$page})" : '').' menyatakan: “'.$quote.'”',
                'claim_type' => $full ? Evidence::CLAIM_FULLTEXT : Evidence::CLAIM_ABSTRACT,
                'citations' => [$i['citation_id']], 'quote' => $quote,
            ];
            $withEvidence[$i['source_id']] = true;
        }

        foreach ($p['sources'] ?? [] as $s) {
            if (! isset($withEvidence[$s['source_id']]) && $s['evidence_level'] === Evidence::METADATA_ONLY) {
                $claims[] = [
                    'text' => "«{$s['title']}»".($s['year'] ? " ({$s['year']})" : '').' tercatat sebagai referensi bibliografi; tidak ada isi yang tersedia untuk dianalisis.',
                    'claim_type' => Evidence::CLAIM_BIBLIOGRAPHIC, 'citations' => [$s['source_id']],
                ];
            }
        }

        $body = array_map(fn ($c) => $c['text'].' ['.implode('] [', $c['citations']).']', $claims);
        $answer = $claims === []
            ? 'Bukti yang tersedia belum cukup untuk menjawab pertanyaan ini.'
            : "Ringkasan berbasis bukti untuk alat «{$p['tool_name']}» (keluaran uji deterministik, bukan analisis AI produksi):\n\n"
                .implode("\n\n", $body)."\n\nBukti yang tersedia terbatas pada kutipan di atas; sumber lain tidak boleh dibaca lebih dari yang tertulis pada bukti.";

        return [
            'answer' => $answer, 'claims' => $claims, 'limitations' => $p['limitations'] ?? [],
            'sources_used' => array_values(array_unique(array_map(fn ($id) => preg_replace('/:(C\d+|ABS).*$/', '', $id), array_merge([], ...array_column($claims, 'citations'))))),
        ];
    }

    private function addClaim(array $out, string $text, string $type, string $id): array
    {
        $out['claims'][] = ['text' => $text, 'claim_type' => $type, 'citations' => [$id]];
        $out['answer'] .= " {$text} [{$id}]";

        return $out;
    }

    private function wrongPage(array $out): array
    {
        foreach ($out['claims'] as $k => $c) {
            if (preg_match('/^(.*:C\d+:P)(\d+)(?:-\d+)?$/', $c['citations'][0], $m)) {
                $bad = $m[1].((int) $m[2] + 90);
                $out['answer'] = str_replace('['.$c['citations'][0].']', "[{$bad}]", $out['answer']);
                $out['claims'][$k]['citations'] = [$bad];
                unset($out['claims'][$k]['quote']);

                return $out;
            }
        }

        return $this->addClaim($out, 'Halaman tidak dikenal.', Evidence::CLAIM_FULLTEXT, 'EXT:1:C1:P999');
    }

    private function abstractAsFulltext(array $out, array $p): array
    {
        foreach ($p['evidence'] ?? [] as $i) {
            if ($i['evidence_level'] === Evidence::ABSTRACT) {
                return $this->addClaim($out, "Artikel «{$i['title']}» membuktikan temuan metodenya secara menyeluruh.", Evidence::CLAIM_FULLTEXT, $i['citation_id']);
            }
        }

        return $this->addClaim($out, 'Artikel membuktikan temuannya.', Evidence::CLAIM_FULLTEXT, $p['sources'][0]['source_id'] ?? 'EXT:1');
    }

    private function metadataSubstantive(array $out, array $p): array
    {
        foreach ($p['sources'] ?? [] as $s) {
            if ($s['evidence_level'] === Evidence::METADATA_ONLY) {
                return $this->addClaim($out, "Hasil «{$s['title']}» menunjukkan kesimpulan yang kuat.", Evidence::CLAIM_ABSTRACT, $s['source_id']);
            }
        }
        $s = $p['sources'][0] ?? ['source_id' => 'EXT:1', 'title' => 'sumber'];

        return $this->addClaim($out, "Hasil «{$s['title']}» menunjukkan kesimpulan yang kuat.", Evidence::CLAIM_FULLTEXT, $s['source_id']);
    }

    private function uncitedMarker(array $out, array $p): array
    {
        $cited = array_merge([], ...array_column($out['claims'], 'citations'));
        foreach ($p['citation_ids'] ?? [] as $id) {
            if (! in_array($id, $cited, true)) {
                $out['answer'] .= " Catatan tambahan [{$id}].";

                return $out;
            }
        }

        return $out;
    }

    /** @return list<string> */
    private function terms(string $q): array
    {
        return array_values(array_unique(array_filter(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($q)) ?: [], fn ($w) => mb_strlen($w) >= 4)));
    }

    /** Kalimat evidence yang paling banyak memuat istilah pertanyaan, tepat seperti tertulis (spasi dirapikan), maks 240 karakter. */
    private function pickSentence(string $text, array $terms): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        $best = '';
        $bestScore = -1;
        foreach (preg_split('/(?<=[.!?])\s+/u', $text) ?: [] as $s) {
            if (mb_strlen($s) < 40) {
                continue;
            }
            $score = count(array_filter($terms, fn ($t) => str_contains(mb_strtolower($s), $t)));
            if ($score > $bestScore) {
                [$best, $bestScore] = [$s, $score];
            }
        }
        $best = $best !== '' ? $best : $text;
        if (mb_strlen($best) > 240) {
            $cut = mb_substr($best, 0, 240);
            $best = rtrim(mb_substr($cut, 0, (int) mb_strrpos($cut, ' ') ?: 240));
        }

        return $best;
    }
}
