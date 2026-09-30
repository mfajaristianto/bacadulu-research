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
        $documentIds = collect(
            $documentIds
        )
            ->map(
                fn ($id) => (int) $id
            )
            ->filter(
                fn (int $id) => $id > 0
            )
            ->unique()
            ->values()
            ->all();

        if ($documentIds === []) {
            return collect();
        }

        $terms = $this->queryTerms(
            $query
        );

        if ($terms === []) {
            return collect();
        }

        $candidateLimit = max(
            50,
            (int) config(
                'research.retrieval.candidate_limit',
                400
            )
        );

        $evidenceLimit = max(
            1,
            (int) config(
                'research.retrieval.evidence_limit',
                8
            )
        );

        $maximumPerDocument = max(
            1,
            (int) config(
                'research.retrieval.max_per_document',
                3
            )
        );

        $minimumScore = max(
            0,
            (float) config(
                'research.retrieval.minimum_score',
                0.15
            )
        );

        /*
         * Batasi keyword SQL supaya query tidak terlalu besar.
         */
        $searchTerms = array_slice(
            $terms,
            0,
            10
        );

        $candidates = DocumentChunk::query()
            ->whereIn(
                'document_id',
                $documentIds
            )
            ->where(
                function ($builder) use (
                    $searchTerms
                ): void {
                    foreach (
                        $searchTerms as $term
                    ) {
                        /*
                         * $term berasal dari regex huruf/angka,
                         * bukan raw SQL.
                         *
                         * Query Builder tetap menggunakan binding.
                         */
                        $builder->orWhere(
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
                fn (DocumentChunk $chunk) => [
                    'chunk' => $chunk,

                    'score' => $this->score(
                        $chunk->content,
                        $terms,
                        $query
                    ),
                ]
            )
            ->filter(
                fn (array $item) => $item['score']
                    >= $minimumScore
            )
            ->sortByDesc(
                'score'
            )
            ->values();

        /*
         * Diversification.
         *
         * Jangan sampai top evidence hanya berasal
         * dari satu document jika source lain relevan.
         */
        $selected = collect();

        $perDocument = [];

        foreach (
            $candidates as $candidate
        ) {
            /** @var DocumentChunk $chunk */
            $chunk =
                $candidate['chunk'];

            $documentId =
                (int) $chunk->document_id;

            $usedForDocument =
                $perDocument[
                    $documentId
                ] ?? 0;

            if (
                $usedForDocument
                >= $maximumPerDocument
            ) {
                continue;
            }

            $selected->push(
                $candidate
            );

            $perDocument[
                $documentId
            ] =
                $usedForDocument + 1;

            if (
                $selected->count()
                >= $evidenceLimit
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
        $query = mb_strtolower(
            trim($query)
        );

        preg_match_all(
            '/[\p{L}\p{N}]{3,}/u',
            $query,
            $matches
        );

        /*
         * Stopwords minimal Indonesia + English.
         *
         * Tidak perlu dependency NLP tambahan dulu.
         */
        $stopWords = [
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
            'berdasarkan',
            'penelitian',
            'research',
            'study',
            'studies',
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
        ];

        return collect(
            $matches[0] ?? []
        )
            ->map(
                fn (string $term) => trim($term)
            )
            ->reject(
                fn (string $term) => in_array(
                    $term,
                    $stopWords,
                    true
                )
            )
            ->unique()
            ->take(20)
            ->values()
            ->all();
    }

    private function score(
        string $content,
        array $terms,
        string $query
    ): float {
        $content = mb_strtolower(
            $content
        );

        $matchedTerms = 0;

        $totalOccurrences = 0;

        foreach (
            $terms as $term
        ) {
            $occurrences =
                substr_count(
                    $content,
                    $term
                );

            if ($occurrences > 0) {
                $matchedTerms++;

                $totalOccurrences +=
                    $occurrences;
            }
        }

        if ($matchedTerms === 0) {
            return 0.0;
        }

        /*
         * Coverage:
         * berapa keyword penting user
         * yang ditemukan dalam chunk.
         */
        $coverage =
            $matchedTerms
            / max(
                1,
                count($terms)
            );

        /*
         * Frequency:
         * seberapa sering keyword ditemukan,
         * tetapi dibatasi supaya repetition
         * tidak mendominasi ranking.
         */
        $frequency = min(
            $totalOccurrences,
            12
        ) / 12;

        $normalizedQuery =
            mb_strtolower(
                trim(
                    preg_replace(
                        '/\s+/u',
                        ' ',
                        $query
                    ) ?? $query
                )
            );

        $phraseBonus = 0.0;

        if (
            mb_strlen(
                $normalizedQuery
            ) >= 8
            && mb_strlen(
                $normalizedQuery
            ) <= 160
            && str_contains(
                $content,
                $normalizedQuery
            )
        ) {
            $phraseBonus = 1.0;
        }

        /*
         * Deterministic ranking v1.
         *
         * Nanti vector similarity dari Qdrant
         * bisa masuk sebagai retrieval_type lain
         * tanpa membuang provenance schema ini.
         */
        return round(
            (0.70 * $coverage)
            + (0.25 * $frequency)
            + (0.05 * $phraseBonus),
            6
        );
    }
}
