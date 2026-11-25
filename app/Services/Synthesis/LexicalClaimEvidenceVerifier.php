<?php

namespace App\Services\Synthesis;

use App\Services\Evidence\ResearchEvidenceBundle;

/**
 * Baseline deterministik: keberadaan teks bukti, tumpang-tindih kata isi, angka yang tidak ada di bukti, dan ketidakcocokan negasi.
 * Hasil "verified_structurally" berarti klaim lolos pemeriksaan struktural ini SAJA; bukan bukti bahwa makna klaim diturunkan dari
 * kutipan. Kegagalan mana pun menghasilkan "needs_semantic_review" (peninjauan manusia), bukan penolakan otomatis.
 */
final class LexicalClaimEvidenceVerifier implements ClaimEvidenceVerifier
{
    public const OVERLAP_THRESHOLD = 0.5;

    private const STOP = ['yang', 'dengan', 'untuk', 'dalam', 'pada', 'dari', 'atau', 'adalah', 'bahwa', 'serta', 'oleh', 'juga', 'this', 'that', 'with', 'from', 'have', 'been', 'which', 'were', 'their', 'there', 'about', 'into', 'than', 'also',
        // kata meta penyaji klaim (bukan isi bukti)
        'potongan', 'teks', 'lengkap', 'abstrak', 'menyatakan', 'menurut', 'artikel', 'sumber', 'tercatat', 'referensi', 'bibliografi', 'tersedia', 'dianalisis', 'isi', 'halaman', 'penelitian', 'studi'];

    private const NEGATIONS = ['tidak', 'bukan', 'tanpa', 'belum', 'not', 'never', 'without', 'cannot', 'neither', 'nor', 'none'];

    public function verify(ResearchEvidenceBundle $bundle, array $claims): array
    {
        $out = [];
        foreach ($claims as $c) {
            if (! ($c['valid'] ?? false)) {
                continue;
            }
            $out[(int) $c['index']] = $this->one($bundle, $c);
        }

        return $out;
    }

    /** @return array{status: string, reasons: list<string>, overlap: ?float} */
    private function one(ResearchEvidenceBundle $bundle, array $claim): array
    {
        $evidence = $this->evidenceText($bundle, $claim['citations']);
        $reasons = [];

        $content = $claim['claim_type'] !== 'bibliographic';
        if (trim($evidence) === '') {
            return $this->result($content ? ['empty_evidence'] : [], null);
        }

        $plain = $this->plain($claim['text']);
        $tokens = $this->tokens($plain);
        $overlap = null;
        // Klaim bibliografi hanya dibandingkan dengan identitas sumber (judul/tahun/penulis); kata isi dan negasi tidak bermakna di sana.
        if ($content && count($tokens) >= 2) {
            $ev = $this->tokens($evidence);
            $hits = count(array_filter($tokens, fn ($t) => $this->present($t, $ev)));
            $overlap = round($hits / count($tokens), 3);
            if ($overlap < self::OVERLAP_THRESHOLD) {
                $reasons[] = 'low_lexical_overlap';
            }
            if ($this->negated($plain) !== $this->negated($this->bestSentence($evidence, $tokens))) {
                $reasons[] = 'possible_negation_mismatch';
            }
        }

        if ($this->unsupportedNumbers($plain, $evidence) !== []) {
            $reasons[] = 'unsupported_number';
        }

        return $this->result($reasons, $overlap);
    }

    /** @return array{status: string, reasons: list<string>, overlap: ?float} */
    private function result(array $reasons, ?float $overlap): array
    {
        return ['status' => $reasons === [] ? self::VERIFIED_STRUCTURALLY : self::NEEDS_SEMANTIC_REVIEW, 'reasons' => $reasons, 'overlap' => $overlap];
    }

    private function evidenceText(ResearchEvidenceBundle $bundle, array $citations): string
    {
        $parts = [];
        foreach ($citations as $id) {
            if (($item = $bundle->itemByCitation($id)) !== null) {
                $parts[] = $item['text'];
            } elseif (($src = $bundle->sourceById($id)) !== null) {
                $parts[] = trim(($src['title'] ?? '').' '.($src['year'] ?? '').' '.implode(' ', (array) ($src['authors'] ?? [])).' '.($src['doi'] ?? ''));
            }
        }

        return implode("\n", $parts);
    }

    /** Buang kutipan (keberadaannya sudah diperiksa validator), judul dalam tanda kutip, penanda sitasi, dan label halaman. */
    private function plain(string $text): string
    {
        return trim((string) preg_replace(['/[“"«„][^”"»]*[”"»]/u', '/\[[^\]]*\]/u', '/\(?\b(?:hlm|halaman|page|pp?)\.?\s*\d+(?:\s*[–-]\s*\d+)?\)?/iu'], ' ', $text));
    }

    /** @return list<string> */
    private function tokens(string $text): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text)) ?: [];

        return array_values(array_unique(array_filter($words, fn ($w) => mb_strlen($w) >= 4 && ! in_array($w, self::STOP, true) && ! ctype_digit($w))));
    }

    /** @param list<string> $evidenceTokens */
    private function present(string $t, array $evidenceTokens): bool
    {
        $stem = mb_strlen($t) >= 6 ? mb_substr($t, 0, mb_strlen($t) - 2) : $t;
        foreach ($evidenceTokens as $e) {
            if ($e === $t || str_starts_with($e, $stem)) {
                return true;
            }
        }

        return false;
    }

    private function negated(string $text): bool
    {
        $lower = mb_strtolower($text);

        return array_intersect(preg_split('/[^\p{L}]+/u', $lower) ?: [], self::NEGATIONS) !== [] || str_contains($lower, "n't");
    }

    /** Kalimat bukti yang paling banyak memuat kata klaim. @param list<string> $tokens */
    private function bestSentence(string $evidence, array $tokens): string
    {
        $best = '';
        $score = -1;
        foreach (preg_split('/(?<=[.!?])\s+/u', $evidence) ?: [] as $s) {
            $n = count(array_filter($tokens, fn ($t) => str_contains(mb_strtolower($s), mb_substr($t, 0, max(4, mb_strlen($t) - 2)))));
            if ($n > $score) {
                [$best, $score] = [$s, $n];
            }
        }

        return $best;
    }

    /** @return list<string> angka pada klaim yang tidak muncul di bukti */
    private function unsupportedNumbers(string $claim, string $evidence): array
    {
        preg_match_all('/\d+(?:[.,]\d+)?/u', $claim, $m);
        $ev = str_replace(',', '.', $evidence);
        $missing = [];
        foreach (array_unique($m[0]) as $n) {
            $n = str_replace(',', '.', $n);
            if (! preg_match('/(?<![\d.])'.preg_quote($n, '/').'(?!\d)/u', $ev)) {
                $missing[] = $n;
            }
        }

        return $missing;
    }
}
