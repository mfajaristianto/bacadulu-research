<?php

namespace App\Services\Discovery;

use App\Models\PromptTemplate;

/**
 * Kesiapan analisis AI DAN kontrak input sintesis yang berlandaskan sumber. Kelas ini TIDAK memanggil provider AI.
 *
 * Kontrak membedakan tiga jenis bukti dan tidak pernah mengubah metadata menjadi "isi penelitian":
 *   metadata           -> hanya identitas publikasi (boleh dikutip sebagai keberadaan sumber, bukan temuan)
 *   abstract           -> ringkasan tertulis penulis/penerbit (bukan isi lengkap)
 *   full_text_indexed  -> potongan teks yang benar-benar tersimpan di Pustaka
 * Sitasi hanya sah bila merujuk citation_id yang ada di kontrak (sitasi karangan ditolak). CitationValidator Research
 * yang sudah ada tidak diubah: ia tetap hanya menerima chunk Pustaka pada run riset.
 */
final class AnalysisContract
{
    /** Kode Katalog Alat AI -> label aksi. Hanya alat yang AKTIF dan punya prompt aktif yang ditawarkan. */
    public const ACTION_LABELS = [
        'literature-synthesis' => 'Analisis Lebih Mendalam',
        'research-gap' => 'Susun Research Gap',
        'theoretical-mapping' => 'Petakan Teori',
        'conceptual-framework' => 'Susun Kerangka Konseptual',
    ];

    private const CLAIMS = [
        'metadata' => ['identity'],
        'abstract' => ['identity', 'abstract_statement'],
        'full_text_indexed' => ['identity', 'abstract_statement', 'text_evidence'],
    ];

    /** @return array{available: bool, provider: string, reason: ?string} */
    public function aiStatus(): array
    {
        $provider = strtolower((string) config('research.ai_provider', 'mock'));
        $key = trim((string) config('services.openai.api_key', ''));

        return match (true) {
            $provider === 'openai' && $key !== '' => ['available' => true, 'provider' => $provider, 'reason' => null],
            $provider === 'openai' => ['available' => false, 'provider' => $provider, 'reason' => 'Provider AI dipilih, tetapi kunci API belum dikonfigurasi oleh admin.'],
            $provider === 'mock' || $provider === '' => ['available' => false, 'provider' => 'mock', 'reason' => 'Analisis AI belum tersedia: sistem berjalan dalam mode uji. Referensi di atas tetap dapat dibuka dan dipakai.'],
            default => ['available' => false, 'provider' => $provider, 'reason' => 'Provider AI belum dikenali sistem.'],
        };
    }

    /** @return array<int, array{prompt_template_id: int, code: string, label: string}> */
    public function actions(): array
    {
        return PromptTemplate::query()->toolInstructions()->where('status', 'active')
            ->whereHas('aiTool', fn ($q) => $q->where('status', 'active')->whereIn('code', array_keys(self::ACTION_LABELS)))
            ->with('aiTool:id,code')->orderBy('id')->get()
            ->unique(fn ($p) => $p->aiTool->code)
            ->map(fn ($p) => ['prompt_template_id' => $p->id, 'code' => $p->aiTool->code, 'label' => self::ACTION_LABELS[$p->aiTool->code]])
            ->sortBy(fn ($a) => array_search($a['code'], array_keys(self::ACTION_LABELS), true))->values()->all();
    }

    /**
     * @param  array<int, array>  $sources  hasil SourceDiscovery
     * @return array{sources: array<int, array>, evidence_summary: array<string,int>, limitations: array<int,string>}
     */
    public function build(array $sources): array
    {
        $out = [];
        $summary = ['metadata' => 0, 'abstract' => 0, 'full_text_indexed' => 0];

        foreach (array_values($sources) as $i => $s) {
            $type = $s['evidence'];
            $summary[$type]++;
            $content = match ($type) {
                'abstract' => $s['abstract'],
                'full_text_indexed' => $s['snippet'] ?? null,
                default => null, // metadata tidak membawa isi: tidak ada chunk palsu
            };

            $out[] = [
                'source_id' => $s['key'], 'citation_id' => 'S'.($i + 1), 'title' => $s['title'],
                'bibliographic' => ['authors' => $s['authors'], 'year' => $s['year'], 'container' => $s['container'], 'publisher' => $s['publisher'], 'type' => $s['type_label'], 'language' => $s['language']],
                'doi' => $s['doi'], 'url' => $s['doi_url'] ?? $s['landing_url'],
                'provenance' => $s['provenance'],
                'evidence' => ['type' => $type, 'content' => $content],
                'claims_allowed' => self::CLAIMS[$type],
            ];
        }

        $limits = [];
        if ($summary['metadata'] > 0) {
            $limits[] = $summary['metadata'].' sumber hanya berisi metadata: analisis tidak boleh mengklaim isi, metode, atau temuannya.';
        }
        if ($summary['abstract'] > 0) {
            $limits[] = $summary['abstract'].' sumber hanya berisi abstrak: klaim terbatas pada apa yang ditulis di abstrak, bukan isi lengkap artikel.';
        }
        if ($summary['full_text_indexed'] === 0) {
            $limits[] = 'Tidak ada teks lengkap yang tersedia untuk diproses; sintesis mendalam tidak dapat didukung bukti teks.';
        }

        return ['sources' => $out, 'evidence_summary' => $summary, 'limitations' => $limits];
    }

    /** @return array<int,string> citation_id yang TIDAK ada di kontrak (harus ditolak) */
    public function unknownCitations(array $citationIds, array $contract): array
    {
        $known = array_flip(array_column($contract['sources'], 'citation_id'));

        return array_values(array_filter($citationIds, fn ($id) => ! isset($known[$id])));
    }

    /** Klaim jenis tertentu hanya sah untuk sumber yang bukti-nya mendukung. */
    public function claimAllowed(array $contract, string $citationId, string $claim): bool
    {
        foreach ($contract['sources'] as $s) {
            if ($s['citation_id'] === $citationId) {
                return in_array($claim, $s['claims_allowed'], true);
            }
        }

        return false;
    }
}
