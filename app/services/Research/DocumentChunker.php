<?php

namespace App\Services\Research;

use Illuminate\Support\Str;

class DocumentChunker
{
    /**
     * @return array<int, array{
     *     content: string,
     *     metadata: array<string, mixed>
     * }>
     */
    public function chunk(
        string $text,
        string $extractionMethod = 'unknown',
        ?int $sourceFileId = null
    ): array {
        $text = $this->normalize(
            $text
        );

        if ($text === '') {
            return [];
        }

        $chunkWords = max(
            100,
            (int) config(
                'research.ingestion.chunk_words',
                500
            )
        );

        $overlapWords = max(
            0,
            min(
                $chunkWords - 1,
                (int) config(
                    'research.ingestion.chunk_overlap_words',
                    75
                )
            )
        );

        $minimumWords = max(
            20,
            (int) config(
                'research.ingestion.minimum_chunk_words',
                60
            )
        );

        /*
         * pdftotext mempertahankan form-feed (\f)
         * sebagai pemisah halaman.
         *
         * Manual text biasanya hanya menjadi page 1.
         */
        $pages = preg_split(
            '/\f/u',
            $text
        ) ?: [$text];

        $chunks = [];

        foreach (
            $pages as $pageIndex => $page
        ) {
            $page = trim(
                preg_replace(
                    '/[ \t]+/u',
                    ' ',
                    $page
                ) ?? $page
            );

            if ($page === '') {
                continue;
            }

            $words = preg_split(
                '/\s+/u',
                $page,
                -1,
                PREG_SPLIT_NO_EMPTY
            ) ?: [];

            if ($words === []) {
                continue;
            }

            /*
             * Chunk menggunakan word-window agar aman
             * untuk Bahasa Indonesia maupun English.
             */
            $step = max(
                1,
                $chunkWords - $overlapWords
            );

            $wordCount = count(
                $words
            );

            for (
                $offset = 0;
                $offset < $wordCount;
                $offset += $step
            ) {
                $slice = array_slice(
                    $words,
                    $offset,
                    $chunkWords
                );

                if ($slice === []) {
                    break;
                }

                /*
                 * Jangan membuat tail chunk sangat kecil
                 * jika sudah ada chunk untuk halaman tersebut.
                 */
                if (
                    count($slice) < $minimumWords
                    && $offset > 0
                ) {
                    break;
                }

                $content = Str::squish(
                    implode(
                        ' ',
                        $slice
                    )
                );

                if ($content === '') {
                    continue;
                }

                $chunks[] = [
                    'content' => $content,

                    'metadata' => [
                        'page' => $pageIndex + 1,

                        'word_start' => $offset + 1,

                        'word_end' => min(
                            $offset
                                + count($slice),
                            $wordCount
                        ),

                        'word_count' => count($slice),

                        'extraction_method' => $extractionMethod,

                        'source_file_id' => $sourceFileId,
                    ],
                ];

                if (
                    $offset + $chunkWords
                    >= $wordCount
                ) {
                    break;
                }
            }
        }

        return $chunks;
    }

    private function normalize(
        string $text
    ): string {
        $text = str_replace(
            [
                "\r\n",
                "\r",
                "\0",
            ],
            [
                "\n",
                "\n",
                '',
            ],
            $text
        );

        /*
         * Pertahankan \f karena digunakan
         * sebagai page separator.
         */
        $text = preg_replace(
            '/[^\P{C}\n\t\f]/u',
            '',
            $text
        ) ?? $text;

        $text = preg_replace(
            "/\n{4,}/u",
            "\n\n\n",
            $text
        ) ?? $text;

        return trim(
            $text
        );
    }
}
