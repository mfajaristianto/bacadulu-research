<?php

namespace App\Services\Research;

use App\Models\AiRun;
use App\Models\AiRunCitation;
use App\Models\AiRunRetrieval;
use Illuminate\Support\Collection;

class CitationValidator
{
    /**
     * @param  array<int, array<string, mixed>>  $citations
     * @return Collection<int, AiRunCitation>
     */
    public function validateAndStore(
        AiRun $run,
        array $citations,
        ?int $aiRunResultId = null
    ): Collection {
        /*
         * Kalau validator dipanggil ulang untuk result
         * yang sama, jangan menggandakan record.
         */
        if ($aiRunResultId !== null) {
            AiRunCitation::query()
                ->where(
                    'ai_run_id',
                    $run->id
                )
                ->where(
                    'ai_run_result_id',
                    $aiRunResultId
                )
                ->delete();
        }

        $runSourceIds = $run
            ->sources()
            ->pluck(
                'documents.id'
            )
            ->map(
                fn ($id) => (int) $id
            )
            ->all();

        $retrievals =
            AiRunRetrieval::query()
                ->where(
                    'ai_run_id',
                    $run->id
                )
                ->with(
                    'documentChunk'
                )
                ->get()
                ->keyBy(
                    'citation_key'
                );

        $stored = collect();

        foreach (
            $citations as $citation
        ) {
            $citationKey = trim(
                (string) (
                    $citation[
                        'citation_key'
                    ] ?? ''
                )
            );

            if ($citationKey === '') {
                continue;
            }

            /** @var AiRunRetrieval|null $retrieval */
            $retrieval =
                $retrievals->get(
                    $citationKey
                );

            $isValid = true;

            $messages = [];

            /*
             * Citation harus menunjuk retrieval
             * yang benar-benar terjadi.
             */
            if (! $retrieval) {
                $isValid = false;

                $messages[] =
                    'Citation key tidak ditemukan pada retrieval AI Run.';
            }

            /*
             * Citation harus berasal dari document
             * yang termasuk run_sources.
             */
            if (
                $retrieval
                && ! in_array(
                    (int) $retrieval
                        ->document_id,
                    $runSourceIds,
                    true
                )
            ) {
                $isValid = false;

                $messages[] =
                    'Document citation berada di luar run_sources.';
            }

            $quoteSnippet = trim(
                (string) (
                    $citation[
                        'quote_snippet'
                    ] ?? ''
                )
            );

            /*
             * Kalau output AI memberikan quote/excerpt,
             * validator memastikan teks tersebut memang
             * terdapat di source chunk.
             */
            if (
                $retrieval
                && $quoteSnippet !== ''
                && ! $this
                    ->quoteExistsInChunk(
                        $quoteSnippet,
                        (string) $retrieval
                            ->documentChunk
                            ?->content
                    )
            ) {
                $isValid = false;

                $messages[] =
                    'Quote snippet tidak ditemukan pada source chunk.';
            }

            $record =
                AiRunCitation::create([
                    'ai_run_id' => $run->id,

                    'ai_run_result_id' => $aiRunResultId,

                    /*
                     * Nullable kalau citation key
                     * ternyata invalid.
                     */
                    'document_id' => $retrieval
                        ?->document_id,

                    'document_chunk_id' => $retrieval
                        ?->document_chunk_id,

                    'citation_key' => $citationKey,

                    'claim_label' => filled(
                        $citation[
                            'claim_label'
                        ] ?? null
                    )
                            ? trim(
                                (string) $citation[
                                    'claim_label'
                                ]
                            )
                            : null,

                    'quote_snippet' => $quoteSnippet !== ''
                            ? $quoteSnippet
                            : null,

                    'is_valid' => $isValid,

                    'validation_message' => $messages !== []
                            ? implode(
                                ' ',
                                $messages
                            )
                            : 'Citation valid.',

                    'metadata_json' => [
                        'validator' => 'internal-v1',
                    ],
                ]);

            $stored->push(
                $record
            );
        }

        return $stored;
    }

    private function quoteExistsInChunk(
        string $quote,
        string $chunkContent
    ): bool {
        /*
         * Excerpt UI dapat memakai ellipsis.
         * Hilangkan ellipsis tepi sebelum compare.
         */
        $quote = trim(
            $this->normalizeText(
                $quote
            ),
            " …\t\n\r\0\x0B"
        );

        $chunkContent =
            $this->normalizeText(
                $chunkContent
            );

        if ($quote === '') {
            return true;
        }

        return str_contains(
            $chunkContent,
            $quote
        );
    }

    private function normalizeText(
        string $value
    ): string {
        return mb_strtolower(
            trim(
                preg_replace(
                    '/\s+/u',
                    ' ',
                    $value
                ) ?? $value
            )
        );
    }
}
