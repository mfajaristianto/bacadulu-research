<?php

namespace App\Services\Evidence;

/**
 * Validator sitasi untuk Evidence Bundle. Murni dan tanpa penyimpanan (CitationValidator Research yang lama tetap hanya
 * untuk chunk Pustaka pada run riset dan tidak diubah). AI hanya boleh mengutip identifier yang ada di `citation_map`.
 *
 * Sitasi: ['id' => 'EXT:929:C12:P8-9', 'claim_type' => 'fulltext_statement', 'page' => 8 (opsional), 'quote' => '...' (opsional)]
 * Kode penolakan: malformed_id, unknown_source, unknown_evidence, page_mismatch, unknown_claim_type, claim_not_supported,
 *                 metadata_only, quote_not_found.
 */
final class EvidenceCitationValidator
{
    /**
     * Hanya keberadaan: apakah identifier ini benar-benar ada di peta sitasi bundle (tanpa menilai jenis klaim).
     *
     * @return array{valid: bool, code: ?string, reason: ?string, support: ?string}
     */
    public function exists(ResearchEvidenceBundle $bundle, string $id): array
    {
        $parsed = CitationId::parse(trim($id));
        if ($parsed === null) {
            return $this->no('malformed_id', 'Format identifier sitasi tidak sah.');
        }

        // Sumber harus ada di bundle (publikasi/dokumen yang tidak ada atau tidak terpilih ditolak).
        $sourceId = CitationId::source($parsed['kind'], $parsed['id']);
        if ($bundle->sourceById($sourceId) === null) {
            return $this->no('unknown_source', 'Sumber tidak ada di Evidence Bundle.');
        }

        $entry = $bundle->citation(trim($id));
        if ($entry === null) {
            // Sumber ada tetapi identifier evidence/halaman ini tidak pernah diberikan: evidence atau halaman karangan.
            $pageMismatch = $parsed['page_start'] !== null && $this->chunkExists($bundle, $sourceId, $parsed['chunk']);

            return $this->no($pageMismatch ? 'page_mismatch' : 'unknown_evidence', $pageMismatch
                ? 'Halaman pada identifier tidak sama dengan halaman evidence sebenarnya.'
                : 'Evidence tidak ada di Evidence Bundle.');
        }

        return ['valid' => true, 'code' => null, 'reason' => null, 'support' => $entry['claim_support']];
    }

    /** @return array{valid: bool, code: ?string, reason: ?string, support: ?string} */
    public function validate(ResearchEvidenceBundle $bundle, array $citation): array
    {
        $id = trim((string) ($citation['id'] ?? ''));
        $claim = (string) ($citation['claim_type'] ?? '');

        $exists = $this->exists($bundle, $id);
        if (! $exists['valid']) {
            return $exists;
        }
        $entry = $bundle->citation($id);

        if (! in_array($claim, Evidence::CLAIM_TYPES, true)) {
            return $this->no('unknown_claim_type', 'Jenis klaim tidak dikenal.');
        }

        // Halaman yang diklaim terpisah dari identifier harus berada di rentang halaman evidence.
        if (isset($citation['page']) && $entry['page_start'] !== null) {
            $p = (int) $citation['page'];
            if ($p < $entry['page_start'] || $p > $entry['page_end']) {
                return $this->no('page_mismatch', "Halaman {$p} di luar halaman evidence ({$entry['page_start']}–{$entry['page_end']}).");
            }
        } elseif (isset($citation['page']) && $entry['type'] === 'evidence') {
            return $this->no('page_mismatch', 'Evidence ini tidak memiliki nomor halaman; halaman tidak boleh diklaim.');
        }

        if (! in_array($claim, $entry['allowed_claims'], true)) {
            // Metadata/sumber saja dipakai untuk klaim isi = pelanggaran paling berat.
            if ($entry['evidence_level'] === Evidence::METADATA_ONLY && $claim !== Evidence::CLAIM_BIBLIOGRAPHIC) {
                return $this->no('metadata_only', 'Sumber hanya berisi metadata; tidak boleh dipakai sebagai bukti isi, metode, hasil, atau kesimpulan.');
            }

            return $this->no('claim_not_supported', $claim === Evidence::CLAIM_FULLTEXT
                ? 'Klaim isi artikel hanya sah bila dikutip dari potongan teks lengkap, bukan dari abstrak atau metadata.'
                : 'Level bukti ini tidak mendukung jenis klaim tersebut.');
        }

        // Kutipan harus benar-benar ada di teks evidence.
        if (isset($citation['quote']) && trim((string) $citation['quote']) !== '') {
            $item = $bundle->itemByCitation($id);
            if ($item === null || ! str_contains($this->norm($item['text']), $this->norm((string) $citation['quote']))) {
                return $this->no('quote_not_found', 'Kutipan tidak ditemukan pada teks evidence.');
            }
        }

        return ['valid' => true, 'code' => null, 'reason' => null, 'support' => $entry['claim_support']];
    }

    /**
     * @param  list<array<string, mixed>>  $citations
     * @return list<array{citation: array<string, mixed>, valid: bool, code: ?string, reason: ?string, support: ?string}>
     */
    public function validateMany(ResearchEvidenceBundle $bundle, array $citations): array
    {
        return array_map(fn ($c) => ['citation' => $c] + $this->validate($bundle, $c), $citations);
    }

    private function chunkExists(ResearchEvidenceBundle $bundle, string $sourceId, ?int $chunk): bool
    {
        return $chunk !== null && array_filter($bundle->items(), fn ($i) => $i['source_id'] === $sourceId && str_contains($i['citation_id'], ":C{$chunk}")) !== [];
    }

    private function norm(string $t): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $t)));
    }

    /** @return array{valid: bool, code: string, reason: string, support: null} */
    private function no(string $code, string $reason): array
    {
        return ['valid' => false, 'code' => $code, 'reason' => $reason, 'support' => null];
    }
}
