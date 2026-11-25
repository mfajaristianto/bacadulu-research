<?php

namespace App\Services\Synthesis;

use App\Services\Evidence\ResearchEvidenceBundle;

/**
 * Pemeriksa kesesuaian klaim terhadap bukti yang disitasinya. SynthesisValidator hanya menjamin ID, halaman, level, dan keberadaan kutipan;
 * kontrak ini adalah titik sambung untuk pemeriksaan kesesuaian ISI. Implementasi bawaan (LexicalClaimEvidenceVerifier) bersifat
 * DETERMINISTIK dan hanya struktural: ia BUKAN entailment semantik. Implementasi di masa depan (mis. LLM judge) dapat dipasang
 * tanpa mengubah kontrak ini, tetapi tidak ada yang berjalan pada tahap ini.
 */
interface ClaimEvidenceVerifier
{
    public const VERIFIED_STRUCTURALLY = 'verified_structurally';

    public const NEEDS_SEMANTIC_REVIEW = 'needs_semantic_review';

    /**
     * @param  list<array<string, mixed>>  $claims  klaim hasil SynthesisValidator (hanya yang valid yang diperiksa)
     * @return array<int, array{status: string, reasons: list<string>, overlap: ?float}> menurut indeks klaim
     */
    public function verify(ResearchEvidenceBundle $bundle, array $claims): array;
}
