<?php

namespace App\Services\DocumentFlow;

/**
 * Seluruh masukan satu bagian. Identik untuk setiap penyedia yang mencoba bagian yang sama, sehingga pergantian penyedia
 * tidak mengubah outline, evidence, bagian sebelumnya, atau gaya dokumen.
 */
final class SectionRequest
{
    /**
     * @param  list<string>  $outline  judul seluruh bagian terpilih, berurutan
     * @param  list<array{citation_id: string, title: ?string, text: string, evidence_level: string}>  $evidence
     * @param  list<array{heading: string, body: string}>  $previous  bagian sebelumnya yang SELESAI dan lolos validasi
     * @param  list<string>  $questions
     */
    public function __construct(
        public readonly string $key,
        public readonly string $heading,
        public readonly string $instruction,
        public readonly string $documentType,
        public readonly string $topic,
        public readonly array $questions,
        public readonly array $outline,
        public readonly array $evidence,
        public readonly array $previous,
        public readonly string $language,
        public readonly string $length,
        public readonly int $targetWords,
        public readonly string $citationStyle,
        public readonly ?string $focusQuestion = null,
    ) {}

    /** @return list<string> */
    public function allowedCitations(): array
    {
        return array_values(array_unique(array_column($this->evidence, 'citation_id')));
    }
}
