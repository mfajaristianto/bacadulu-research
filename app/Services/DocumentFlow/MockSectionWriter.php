<?php

namespace App\Services\DocumentFlow;

/**
 * Penulis uji tanpa jaringan: menyusun teks dari evidence yang diberikan (kutipan + sitasi), deterministik. Dipakai untuk
 * pengembangan, QA, dan pengujian; BUKAN kualitas tulisan AI sungguhan.
 */
final class MockSectionWriter implements SectionWriter
{
    public function id(): string
    {
        return 'mock';
    }

    public function tier(): string
    {
        return 'mock';
    }

    public function write(SectionRequest $r): array
    {
        $lines = ["Bagian ini disusun dari evidence yang tersedia untuk topik \"{$r->topic}\"."];
        foreach (array_slice($r->evidence, 0, max(1, min(6, intdiv($r->targetWords, 80)))) as $e) {
            $text = trim((string) preg_replace('/\s+/u', ' ', $e['text']));
            $excerpt = mb_strlen($text) > 220 ? rtrim(mb_substr($text, 0, 217)).'…' : $text;
            $lines[] = "{$excerpt} [{$e['citation_id']}]";
        }
        if ($r->evidence === []) {
            $lines[] = 'Evidence yang tersedia tidak memuat teks untuk bagian ini; hanya metadata sumber yang dapat dirujuk.';
        }

        return ['text' => implode("\n\n", $lines), 'model' => 'mock-sections-1', 'meta' => ['test_mode' => true]];
    }
}
