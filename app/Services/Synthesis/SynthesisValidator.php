<?php

namespace App\Services\Synthesis;

use App\Services\Evidence\Evidence;
use App\Services\Evidence\EvidenceCitationValidator;
use App\Services\Evidence\ResearchEvidenceBundle;

/**
 * Validasi hasil sintesis terhadap Evidence Bundle yang BENAR-BENAR diberikan. Tidak memperbaiki apa pun: setiap pelanggaran dicatat
 * sebagai isu dengan kode, dan status akhir ditentukan dari isu itu.
 *
 *  fatal  (-> invalid): JSON/skema rusak, sitasi yang tidak ada (sumber/evidence/halaman karangan), sources_used di luar bundle, tanpa klaim sah.
 *  claim  (-> partial bila masih ada klaim sah lain, selain itu invalid): jenis klaim tidak dikenal, klaim tidak didukung level bukti,
 *         metadata dipakai untuk klaim isi, kutipan tidak ada di teks bukti.
 *  soft   (-> partial): keterbatasan tidak disalin, penanda jawaban tidak sama dengan klaim, sources_used tidak lengkap.
 */
final class SynthesisValidator
{
    public const VALID = 'valid';

    public const PARTIAL = 'partial';

    public const INVALID = 'invalid';

    private const FATAL = ['malformed_id', 'unknown_source', 'unknown_evidence', 'page_mismatch'];

    private const RANK = [Evidence::BIBLIOGRAPHIC_ONLY => 1, Evidence::ABSTRACT_SUPPORTED => 2, Evidence::FULLTEXT_SUPPORTED => 3];

    public function __construct(private readonly EvidenceCitationValidator $citations) {}

    /**
     * @param  array{ok: bool, data: ?array, errors: list<array{code: string, message: string}>, notes: list<string>}  $parsed
     * @return array{status: string, issues: list<array<string, mixed>>, claims: list<array<string, mixed>>, markers: list<array<string, mixed>>, limitations: array<string, mixed>, sources_used: array<string, mixed>, counts: array<string, int>}
     */
    public function validate(ResearchEvidenceBundle $bundle, array $parsed): array
    {
        $issues = [];
        if (! $parsed['ok'] || $parsed['data'] === null) {
            foreach ($parsed['errors'] as $e) {
                $issues[] = $this->issue('fatal', $e['code'], $e['message']);
            }

            return $this->result(self::INVALID, $issues, [], [], ['required' => $bundle->limitations(), 'missing' => $bundle->limitations(), 'preserved' => false], ['declared' => [], 'verified' => [], 'unknown' => []]);
        }

        $d = $parsed['data'];
        $claims = [];
        $citedByValid = [];
        foreach ($d['claims'] as $k => $c) {
            $results = [];
            $support = null;
            $claimIssues = [];
            foreach ($c['citations'] as $cid) {
                $r = $this->citations->validate($bundle, ['id' => $cid, 'claim_type' => $c['claim_type']]);
                $results[] = ['id' => $cid] + $r;
                if (! $r['valid']) {
                    $kind = in_array($r['code'], self::FATAL, true) ? 'fatal' : 'claim';
                    $claimIssues[] = $this->issue($kind, $r['code'], (string) $r['reason'], $k, $cid);
                } elseif ($support === null || self::RANK[$r['support']] < self::RANK[$support]) {
                    $support = $r['support']; // klaim sekuat bukti TERLEMAHnya
                }
            }
            if ($c['quote'] !== null && $claimIssues === [] && ! $this->quoteInCited($bundle, $c)) {
                $claimIssues[] = $this->issue('claim', 'quote_not_found', 'Kutipan tidak ditemukan pada teks bukti yang disitasi.', $k);
            }
            $valid = $claimIssues === [];
            $valid && array_push($citedByValid, ...$c['citations']);
            array_push($issues, ...$claimIssues);
            $claims[] = $c + ['index' => $k, 'valid' => $valid, 'support' => $valid ? $support : null, 'citation_results' => $results];
        }

        $markers = [];
        preg_match_all('/\[((?:INT:DOC-|EXT:)[^\]\s]*)\]/u', $d['answer'], $m);
        foreach (array_values(array_unique($m[1])) as $id) {
            $r = $this->citations->exists($bundle, $id);
            $markers[] = ['id' => $id, 'valid' => $r['valid'], 'code' => $r['code']];
            if (! $r['valid']) {
                $issues[] = $this->issue(in_array($r['code'], self::FATAL, true) ? 'fatal' : 'claim', $r['code'], 'Penanda di jawaban: '.$r['reason'], null, $id);
            }
        }
        if ($markers === []) {
            $issues[] = $this->issue('soft', 'answer_without_markers', 'Jawaban tidak memuat penanda sitasi apa pun.');
        }
        $claimIds = array_values(array_unique(array_merge([], ...array_column($d['claims'], 'citations'))));
        foreach (array_diff(array_column($markers, 'id'), $claimIds) as $id) {
            $issues[] = $this->issue('soft', 'marker_not_in_claims', 'Penanda di jawaban tidak dirujuk oleh klaim terstruktur mana pun.', null, $id);
        }
        foreach (array_diff($claimIds, array_column($markers, 'id')) as $id) {
            $issues[] = $this->issue('soft', 'claim_marker_missing', 'Sitasi klaim terstruktur tidak muncul sebagai penanda di jawaban.', null, $id);
        }

        $lim = $this->limitations($bundle, $d['limitations']);
        if (! $lim['preserved']) {
            $issues[] = $this->issue('soft', 'limitations_omitted', count($lim['missing']).' keterbatasan wajib tidak disalin oleh model; keterbatasan sistem tetap dilampirkan pada hasil.');
        }

        $used = $this->sourcesUsed($bundle, $d['sources_used'], $citedByValid, $issues);

        return $this->finish($issues, $claims, $markers, $lim, $used);
    }

    /** @param list<array<string, mixed>> $issues */
    private function finish(array $issues, array $claims, array $markers, array $lim, array $used): array
    {
        $kinds = array_column($issues, 'severity');
        $valid = count(array_filter($claims, fn ($c) => $c['valid']));
        $status = match (true) {
            in_array('fatal', $kinds, true), $valid === 0 => self::INVALID,
            $issues !== [] => self::PARTIAL,
            default => self::VALID,
        };
        if ($claims === [] && ! in_array('fatal', $kinds, true)) {
            $issues[] = $this->issue('fatal', 'no_claims', 'Jawaban tidak memuat klaim terstruktur sehingga tidak ada yang dapat diverifikasi.');
        }

        return $this->result($status, $issues, $claims, $markers, $lim, $used);
    }

    /** @param list<array<string, mixed>> $issues */
    private function result(string $status, array $issues, array $claims, array $markers, array $lim, array $used): array
    {
        $valid = count(array_filter($claims, fn ($c) => $c['valid']));

        return ['status' => $status, 'issues' => $issues, 'claims' => $claims, 'markers' => $markers, 'limitations' => $lim, 'sources_used' => $used,
            'counts' => ['claims' => count($claims), 'valid_claims' => $valid, 'invalid_claims' => count($claims) - $valid, 'issues' => count($issues)]];
    }

    private function quoteInCited(ResearchEvidenceBundle $bundle, array $claim): bool
    {
        foreach ($claim['citations'] as $cid) {
            $r = $this->citations->validate($bundle, ['id' => $cid, 'claim_type' => $claim['claim_type'], 'quote' => $claim['quote']]);
            if ($r['valid']) {
                return true;
            }
        }

        return false;
    }

    /** @return array{required: list<string>, missing: list<string>, preserved: bool} */
    private function limitations(ResearchEvidenceBundle $bundle, array $given): array
    {
        $norm = fn (string $t) => mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $t)));
        $have = array_map($norm, $given);
        $missing = array_values(array_filter($bundle->limitations(), fn ($l) => ! in_array($norm($l), $have, true)));

        return ['required' => $bundle->limitations(), 'missing' => $missing, 'preserved' => $missing === []];
    }

    /** @return array{declared: list<string>, verified: list<string>, unknown: list<string>} */
    private function sourcesUsed(ResearchEvidenceBundle $bundle, array $declared, array $citedByValid, array &$issues): array
    {
        $verified = array_values(array_unique(array_map(fn ($id) => (string) preg_replace('/:(C\d+|ABS).*$/', '', $id), $citedByValid)));
        $unknown = array_values(array_filter($declared, fn ($id) => $bundle->sourceById($id) === null));
        foreach ($unknown as $id) {
            $issues[] = $this->issue('fatal', 'source_not_in_bundle', 'sources_used memuat sumber yang tidak ada di Evidence Bundle.', null, $id);
        }
        if ($unknown === [] && (array_diff($verified, $declared) !== [] || array_diff($declared, $verified) !== [])) {
            $issues[] = $this->issue('soft', 'sources_used_mismatch', 'sources_used tidak sama dengan sumber yang benar-benar disitasi klaim sah.');
        }

        return ['declared' => $declared, 'verified' => $verified, 'unknown' => $unknown];
    }

    /** @return array{severity: string, code: string, message: string, claim: ?int, citation: ?string} */
    private function issue(string $severity, string $code, string $message, ?int $claim = null, ?string $citation = null): array
    {
        return ['severity' => $severity, 'code' => $code, 'message' => $message, 'claim' => $claim, 'citation' => $citation];
    }
}
