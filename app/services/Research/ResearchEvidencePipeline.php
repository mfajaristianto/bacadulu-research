<?php

namespace App\Services\Research;

use App\Models\AiRun;
use App\Models\AiRunRetrieval;
use App\Models\AiRunResult;
use App\Models\PromptTemplate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ResearchEvidencePipeline
{
    public function __construct(
        private readonly KeywordRetriever $retriever
    ) {
    }

    public function prepare(
        AiRun $run,
        PromptTemplate $prompt,
        array $sourceIds,
        string $userMessage
    ): array {
        /*
        |--------------------------------------------------------------------------
        | Retrieval State
        |--------------------------------------------------------------------------
        */

        $run->update([
            'status'
                => 'retrieving',

            'error_message'
                => null,

            'finished_at'
                => null,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Candidate Source Scope
        |--------------------------------------------------------------------------
        |
        | sourceIds di sini adalah kandidat source eligible.
        | Belum berarti semuanya dipakai sebagai evidence.
        |
        */

        $candidateSourceIds = collect(
            $sourceIds
        )
            ->map(
                fn ($id) =>
                    (int) $id
            )
            ->filter(
                fn (int $id) =>
                    $id > 0
            )
            ->unique()
            ->values()
            ->all();

        /*
        |--------------------------------------------------------------------------
        | Retrieve Relevant Chunks
        |--------------------------------------------------------------------------
        */

        $rankedChunks =
            $this->retriever
                ->retrieve(
                    $candidateSourceIds,
                    $userMessage
                );

        /*
        |--------------------------------------------------------------------------
        | Persist Exact Retrieval
        |--------------------------------------------------------------------------
        */

        $retrievals =
            DB::transaction(
                function () use (
                    $run,
                    $rankedChunks,
                    $userMessage
                ): Collection {
                    /*
                     * Aman ketika AI Run diproses ulang.
                     */
                    AiRunRetrieval::query()
                        ->where(
                            'ai_run_id',
                            $run->id
                        )
                        ->delete();

                    return $rankedChunks
                        ->values()
                        ->map(
                            function (
                                array $item,
                                int $index
                            ) use (
                                $run,
                                $userMessage
                            ): AiRunRetrieval {
                                $chunk =
                                    $item[
                                        'chunk'
                                    ];

                                return AiRunRetrieval::create([
                                    'ai_run_id'
                                        => $run->id,

                                    'document_id'
                                        => $chunk
                                            ->document_id,

                                    'document_chunk_id'
                                        => $chunk
                                            ->id,

                                    'rank_no'
                                        => $index + 1,

                                    'score'
                                        => $item[
                                            'score'
                                        ],

                                    'retrieval_type'
                                        => 'keyword',

                                    'citation_key'
                                        => sprintf(
                                            'E%02d',
                                            $index + 1
                                        ),

                                    'excerpt'
                                        => $this
                                            ->makeExcerpt(
                                                (string)
                                                    $chunk
                                                        ->content,
                                                $userMessage
                                            ),

                                    'metadata_json'
                                        => [
                                            'chunk_index'
                                                => $chunk
                                                    ->chunk_index,

                                            'chunk_metadata'
                                                => $chunk
                                                    ->metadata_json,
                                        ],
                                ]);
                            }
                        );
                }
            );

        $retrievals->load([
            'document.category',
            'documentChunk',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Exact Retrieved Documents
        |--------------------------------------------------------------------------
        */

        $retrievedDocumentIds =
            $retrievals
                ->pluck(
                    'document_id'
                )
                ->map(
                    fn ($id) =>
                        (int) $id
                )
                ->unique()
                ->values()
                ->all();

        /*
         * Ini yang memperbaiki provenance.
         *
         * Pivot ai_run_sources TIDAK lagi menyimpan
         * seluruh candidate source.
         *
         * Hanya source yang benar-benar memberikan
         * relevant chunk/evidence.
         */
        $run->sources()->sync(
            $retrievedDocumentIds
        );

        /*
        |--------------------------------------------------------------------------
        | Store Candidate vs Retrieved Metadata
        |--------------------------------------------------------------------------
        */

        $parameters =
            is_array(
                $run->user_parameters_json
            )
                ? $run
                    ->user_parameters_json
                : [];

        $parameters[
            'candidate_document_ids'
        ] = $candidateSourceIds;

        $parameters[
            'retrieved_document_ids'
        ] = $retrievedDocumentIds;

        $parameters[
            'retrieved_chunk_ids'
        ] = $retrievals
            ->pluck(
                'document_chunk_id'
            )
            ->map(
                fn ($id) =>
                    (int) $id
            )
            ->values()
            ->all();

        /*
        |--------------------------------------------------------------------------
        | Evidence Payload
        |--------------------------------------------------------------------------
        */

        $evidence =
            $retrievals
                ->map(
                    fn (
                        AiRunRetrieval $retrieval
                    ) =>
                        $this
                            ->evidencePayload(
                                $retrieval
                            )
                )
                ->values()
                ->all();

        $sources =
            $this->sourcePayload(
                $retrievals
            );

        /*
        |--------------------------------------------------------------------------
        | Evidence Sufficiency
        |--------------------------------------------------------------------------
        */

        $minimumEvidence =
            max(
                1,
                (int) config(
                    'research.retrieval.minimum_evidence',
                    2
                )
            );

        $minimumDocuments =
            $this->minimumDocuments(
                $prompt
            );

        $distinctDocuments =
            count(
                $retrievedDocumentIds
            );

        $sufficient =
            $retrievals->count()
                >= $minimumEvidence
            &&
            $distinctDocuments
                >= $minimumDocuments;

        $status =
            $sufficient
                ? 'evidence_ready'
                : 'insufficient_evidence';

        $message =
            $sufficient
                ? 'Evidence relevan sudah ditemukan dan siap dianalisis.'
                : 'Data pada Library belum cukup untuk menjawab pertanyaan ini dengan yakin.';

        $nextAction =
            $sufficient
                ? 'Lanjutkan analisis menggunakan evidence yang tersedia.'
                : 'Persempit pertanyaan, gunakan istilah yang lebih spesifik, atau pilih Research Tool lain.';

        /*
        |--------------------------------------------------------------------------
        | Retrieval Result
        |--------------------------------------------------------------------------
        */

        AiRunResult::query()
            ->where(
                'ai_run_id',
                $run->id
            )
            ->where(
                'result_type',
                'retrieval'
            )
            ->delete();

        AiRunResult::create([
            'ai_run_id'
                => $run->id,

            'result_type'
                => 'retrieval',

            'content'
                => $message,

            'structured_output_json'
                => [
                    'status'
                        => $status,

                    'candidate_source_count'
                        => count(
                            $candidateSourceIds
                        ),

                    'evidence_count'
                        => count(
                            $evidence
                        ),

                    'source_count'
                        => count(
                            $sources
                        ),

                    'minimum_evidence'
                        => $minimumEvidence,

                    'minimum_documents'
                        => $minimumDocuments,

                    'evidence'
                        => $evidence,

                    'sources'
                        => $sources,

                    'next_action'
                        => $nextAction,
                ],

            'citations_json'
                => collect(
                    $evidence
                )
                    ->pluck(
                        'citation_key'
                    )
                    ->values()
                    ->all(),
        ]);

        /*
        |--------------------------------------------------------------------------
        | Run State
        |--------------------------------------------------------------------------
        */

        $run->update([
            'status'
                => $status,

            'user_parameters_json'
                => $parameters,

            /*
             * Evidence-ready belum dianggap finished.
             * Setelah ini masih ada AI synthesis +
             * citation validation.
             */
            'finished_at'
                => $sufficient
                    ? null
                    : now(),
        ]);

        return [
            'status'
                => $status,

            'message'
                => $message,

            'evidence'
                => $evidence,

            'sources'
                => $sources,

            'next_action'
                => $nextAction,
        ];
    }

    private function minimumDocuments(
        PromptTemplate $prompt
    ): int {
        $toolCode =
            $prompt
                ->aiTool
                ?->code;

        /*
         * Dua tool ini secara konsep membutuhkan
         * multiple source agar comparison/synthesis
         * defensible.
         */
        return in_array(
            $toolCode,
            [
                'literature-synthesis',
                'comparative-analysis',
            ],
            true
        )
            ? 2
            : 1;
    }

    private function evidencePayload(
        AiRunRetrieval $retrieval
    ): array {
        $document =
            $retrieval->document;

        return [
            'citation_key'
                => $retrieval
                    ->citation_key,

            'text'
                => $retrieval
                    ->excerpt,

            'score'
                => $retrieval
                    ->score,

            'rank'
                => $retrieval
                    ->rank_no,

            'document_id'
                => $retrieval
                    ->document_id,

            'chunk_id'
                => $retrieval
                    ->document_chunk_id,

            'chunk_index'
                => data_get(
                    $retrieval
                        ->metadata_json,
                    'chunk_index'
                ),

            'title'
                => $document
                    ?->title,

            'source_label'
                => $document
                    ?->source_label
                    ?: 'Curated Source',

            'publisher'
                => $document
                    ?->publisher,

            'year'
                => $document
                    ?->publication_year,

            'doi'
                => $document
                    ?->doi,

            'isbn'
                => $document
                    ?->isbn,
        ];
    }

    private function sourcePayload(
        Collection $retrievals
    ): array {
        return $retrievals
            ->unique(
                'document_id'
            )
            ->map(
                function (
                    AiRunRetrieval $retrieval
                ): array {
                    $document =
                        $retrieval
                            ->document;

                    return [
                        'document_id'
                            => $retrieval
                                ->document_id,

                        'title'
                            => $document
                                ?->title,

                        'source_label'
                            => $document
                                ?->source_label
                                ?: 'Curated Source',

                        'publisher'
                            => $document
                                ?->publisher,

                        'year'
                            => $document
                                ?->publication_year,

                        'doi'
                            => $document
                                ?->doi,

                        'isbn'
                            => $document
                                ?->isbn,

                        'category'
                            => $document
                                ?->category
                                ?->name,
                    ];
                }
            )
            ->values()
            ->all();
    }

    private function makeExcerpt(
        string $content,
        string $query
    ): string {
        $content =
            Str::squish(
                $content
            );

        if ($content === '') {
            return '';
        }

        $length =
            max(
                300,
                (int) config(
                    'research.retrieval.excerpt_length',
                    900
                )
            );

        preg_match_all(
            '/[\p{L}\p{N}]{4,}/u',
            mb_strtolower(
                $query
            ),
            $matches
        );

        $terms =
            collect(
                $matches[0]
                ?? []
            )
                ->unique()
                ->values();

        $lowerContent =
            mb_strtolower(
                $content
            );

        $firstPosition =
            null;

        foreach (
            $terms
            as $term
        ) {
            $position =
                mb_stripos(
                    $lowerContent,
                    $term
                );

            if (
                $position
                === false
            ) {
                continue;
            }

            if (
                $firstPosition
                    === null
                ||
                $position
                    < $firstPosition
            ) {
                $firstPosition =
                    $position;
            }
        }

        if (
            $firstPosition
            === null
        ) {
            return Str::limit(
                $content,
                $length,
                '…'
            );
        }

        $start =
            max(
                0,
                $firstPosition
                    - (int) floor(
                        $length / 3
                    )
            );

        $excerpt =
            mb_substr(
                $content,
                $start,
                $length
            );

        if ($start > 0) {
            $excerpt =
                '…'.$excerpt;
        }

        if (
            $start + $length
            <
            mb_strlen(
                $content
            )
        ) {
            $excerpt .= '…';
        }

        return $excerpt;
    }
}