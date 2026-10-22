<?php

namespace App\Services\Research;

use App\Models\DocumentChunk;
use Illuminate\Support\Collection;

class KeywordRetriever
{
    /**
     * @return Collection<int, array{
     *     chunk: DocumentChunk,
     *     score: float
     * }>
     */
    public function retrieve(
        array $documentIds,
        string $query
    ): Collection {
        $documentIds =
            collect(
                $documentIds
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

        if (
            $documentIds === []
        ) {
            return collect();
        }

        $terms =
            $this->queryTerms(
                $query
            );

        if (
            $terms === []
        ) {
            return collect();
        }

        $candidateLimit =
            max(
                50,
                (int) config(
                    'research.retrieval.candidate_limit',
                    400
                )
            );

        $evidenceLimit =
            max(
                1,
                (int) config(
                    'research.retrieval.evidence_limit',
                    8
                )
            );

        $maximumPerDocument =
            max(
                1,
                (int) config(
                    'research.retrieval.max_per_document',
                    3
                )
            );

        $minimumScore =
            max(
                0,
                (float) config(
                    'research.retrieval.minimum_score',
                    0.15
                )
            );

        /*
        |--------------------------------------------------------------------------
        | Minimum Distinct Topic Matches
        |--------------------------------------------------------------------------
        |
        | Query:
        |
        | ESG disclosure firm value
        |
        | memiliki 4 terms.
        |
        | Chunk tidak boleh lolos cuma karena menemukan 1 kata generik.
        |
        */

        $minimumMatchedTerms =
            count($terms) >= 3
                ? 2
                : 1;

        /*
        |--------------------------------------------------------------------------
        | SQL Candidate Search
        |--------------------------------------------------------------------------
        */

        $searchTerms =
            array_slice(
                $terms,
                0,
                12
            );

        $candidates =
            DocumentChunk::query()
                ->whereIn(
                    'document_id',
                    $documentIds
                )
                ->where(
                    function (
                        $builder
                    ) use (
                        $searchTerms
                    ): void {
                        foreach (
                            $searchTerms as $term
                        ) {
                            $builder
                                ->orWhere(
                                    'content',
                                    'like',
                                    '%'.$term.'%'
                                );
                        }
                    }
                )
                ->with([
                    'document.category',
                ])
                ->limit(
                    $candidateLimit
                )
                ->get()
                ->map(
                    function (
                        DocumentChunk $chunk
                    ) use (
                        $terms,
                        $query,
                        $minimumMatchedTerms
                    ): array {
                        $analysis =
                            $this
                                ->analyseMatch(
                                    $chunk->content,
                                    $terms,
                                    $query
                                );

                        return [
                            'chunk' =>
                                $chunk,

                            'score' =>
                                $analysis[
                                    'score'
                                ],

                            'matched_terms' =>
                                $analysis[
                                    'matched_terms'
                                ],

                            'matched_term_count' =>
                                $analysis[
                                    'matched_term_count'
                                ],

                            'minimum_matched_terms' =>
                                $minimumMatchedTerms,
                        ];
                    }
                )
                ->filter(
                    fn (
                        array $item
                    ): bool =>
                        $item[
                            'matched_term_count'
                        ]
                        >=
                        $item[
                            'minimum_matched_terms'
                        ]
                        &&
                        $item[
                            'score'
                        ]
                        >=
                        $minimumScore
                )
                ->sortByDesc(
                    'score'
                )
                ->values();

        /*
        |--------------------------------------------------------------------------
        | Diversification
        |--------------------------------------------------------------------------
        */

        $selected =
            collect();

        $perDocument = [];

        foreach (
            $candidates as
            $candidate
        ) {
            /** @var DocumentChunk $chunk */
            $chunk =
                $candidate[
                    'chunk'
                ];

            $documentId =
                (int)
                    $chunk
                        ->document_id;

            $usedForDocument =
                $perDocument[
                    $documentId
                ]
                ?? 0;

            if (
                $usedForDocument
                >=
                $maximumPerDocument
            ) {
                continue;
            }

            /*
             * Debug scoring tidak perlu ikut keluar
             * ke pipeline sekarang.
             */

            unset(
                $candidate[
                    'matched_terms'
                ],
                $candidate[
                    'matched_term_count'
                ],
                $candidate[
                    'minimum_matched_terms'
                ]
            );

            $selected->push(
                $candidate
            );

            $perDocument[
                $documentId
            ] =
                $usedForDocument
                + 1;

            if (
                $selected
                    ->count()
                >=
                $evidenceLimit
            ) {
                break;
            }
        }

        return $selected
            ->values();
    }


    /**
     * @return array<int, string>
     */
    private function queryTerms(
        string $query
    ): array {
        $query =
            mb_strtolower(
                trim(
                    $query
                )
            );

        preg_match_all(
            '/[\p{L}\p{N}]{3,}/u',
            $query,
            $matches
        );

        /*
        |--------------------------------------------------------------------------
        | Generic / Instruction Stopwords
        |--------------------------------------------------------------------------
        |
        | Yang kita cari untuk lexical retrieval adalah SUBJECT/TOPIC,
        | bukan kata perintah Research Tool.
        |
        */

        $stopWords = [
            /*
             * Indonesian grammar.
             */
            'yang',
            'dan',
            'dengan',
            'dari',
            'untuk',
            'pada',
            'dalam',
            'atau',
            'terhadap',
            'antara',
            'adalah',
            'ini',
            'itu',
            'apa',
            'bagaimana',
            'oleh',
            'sebagai',
            'agar',
            'jika',
            'secara',

            /*
             * Indonesian research instructions.
             */
            'berdasarkan',
            'mengenai',
            'identifikasi',
            'mengidentifikasi',
            'analisis',
            'menganalisis',
            'susun',
            'menyusun',
            'petakan',
            'memetakan',
            'bandingkan',
            'membandingkan',
            'jelaskan',
            'menjelaskan',
            'fokuskan',
            'fokus',
            'research',
            'penelitian',
            'studi',
            'evidence',
            'bukti',
            'source',
            'sumber',
            'tersedia',
            'temuan',
            'hasil',
            'utama',
            'keterbatasan',
            'inkonsistensi',
            'hubungan',
            'belum',
            'banyak',
            'diuji',
            'peluang',
            'lanjutan',
            'teori',
            'kerangka',
            'konseptual',
            'variabel',
            'konstruk',
            'literatur',
            'sintesis',
            'perbandingan',
            'gap',

            /*
             * English grammar.
             */
            'the',
            'and',
            'for',
            'with',
            'from',
            'into',
            'about',
            'what',
            'how',
            'using',
            'based',
            'this',
            'that',
            'these',
            'those',

            /*
             * English research instructions.
             */
            'identify',
            'analyse',
            'analyze',
            'analysis',
            'explain',
            'compare',
            'comparison',
            'map',
            'mapping',
            'build',
            'develop',
            'focus',
            'research',
            'study',
            'studies',
            'evidence',
            'source',
            'sources',
            'available',
            'finding',
            'findings',
            'result',
            'results',
            'limitation',
            'limitations',
            'inconsistency',
            'inconsistencies',
            'relationship',
            'relationships',
            'opportunity',
            'opportunities',
            'future',
            'theory',
            'theoretical',
            'framework',
            'conceptual',
            'variable',
            'variables',
            'construct',
            'constructs',
            'literature',
            'synthesis',
            'gap',
        ];

        return collect(
            $matches[0]
            ?? []
        )
            ->map(
                fn (
                    string $term
                ): string =>
                    trim(
                        $term
                    )
            )
            ->reject(
                fn (
                    string $term
                ): bool =>
                    in_array(
                        $term,
                        $stopWords,
                        true
                    )
            )
            ->filter(
                fn (
                    string $term
                ): bool =>
                    mb_strlen(
                        $term
                    )
                    >= 3
            )
            ->unique()
            ->take(20)
            ->values()
            ->all();
    }


    /**
     * @return array{
     *     score: float,
     *     matched_terms: array<int,string>,
     *     matched_term_count: int
     * }
     */
    private function analyseMatch(
        string $content,
        array $terms,
        string $query
    ): array {
        $content =
            mb_strtolower(
                $content
            );

        $matchedTerms = [];

        $totalOccurrences = 0;

        foreach (
            $terms as
            $term
        ) {
            $occurrences =
                mb_substr_count(
                    $content,
                    $term
                );

            if (
                $occurrences <= 0
            ) {
                continue;
            }

            $matchedTerms[] =
                $term;

            $totalOccurrences +=
                $occurrences;
        }

        $matchedTermCount =
            count(
                $matchedTerms
            );

        if (
            $matchedTermCount === 0
        ) {
            return [
                'score' =>
                    0.0,

                'matched_terms' =>
                    [],

                'matched_term_count' =>
                    0,
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Coverage
        |--------------------------------------------------------------------------
        */

        $coverage =
            $matchedTermCount
            /
            max(
                1,
                count(
                    $terms
                )
            );

        /*
        |--------------------------------------------------------------------------
        | Frequency
        |--------------------------------------------------------------------------
        */

        $frequency =
            min(
                $totalOccurrences,
                12
            )
            /
            12;

        /*
        |--------------------------------------------------------------------------
        | Pair Bonus
        |--------------------------------------------------------------------------
        |
        | Jika minimal dua subject terms muncul,
        | ranking dinaikkan.
        |
        */

        $pairBonus =
            $matchedTermCount >= 2
                ? 1.0
                : 0.0;

        /*
        |--------------------------------------------------------------------------
        | Exact Query Bonus
        |--------------------------------------------------------------------------
        */

        $normalizedQuery =
            mb_strtolower(
                trim(
                    preg_replace(
                        '/\s+/u',
                        ' ',
                        $query
                    )
                    ??
                    $query
                )
            );

        $phraseBonus =
            0.0;

        if (
            mb_strlen(
                $normalizedQuery
            )
            >= 8
            &&
            mb_strlen(
                $normalizedQuery
            )
            <= 160
            &&
            str_contains(
                $content,
                $normalizedQuery
            )
        ) {
            $phraseBonus =
                1.0;
        }

        /*
        |--------------------------------------------------------------------------
        | Deterministic lexical score v2
        |--------------------------------------------------------------------------
        */

        $score =
            (0.65 * $coverage)
            +
            (0.15 * $frequency)
            +
            (0.15 * $pairBonus)
            +
            (0.05 * $phraseBonus);

        return [
            'score' =>
                round(
                    min(
                        1.0,
                        $score
                    ),
                    6
                ),

            'matched_terms' =>
                $matchedTerms,

            'matched_term_count' =>
                $matchedTermCount,
        ];
    }


    /*
     * Compatibility method kalau ada code lama
     * yang masih memanggil score().
     */
    private function score(
        string $content,
        array $terms,
        string $query
    ): float {
        return $this
            ->analyseMatch(
                $content,
                $terms,
                $query
            )[
                'score'
            ];
    }
}