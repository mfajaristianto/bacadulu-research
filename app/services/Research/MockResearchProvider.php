<?php

namespace App\Services\Research;

use Illuminate\Support\Str;

class MockResearchProvider implements AiProvider
{
    public function generate(
        array $payload
    ): array {
        $evidence = collect(
            $payload['evidence'] ?? []
        )->values();

        $citations = $evidence
            ->take(3)
            ->map(
                function (
                    array $item
                ): array {
                    $snippet = trim(
                        (string) (
                            $item['text']
                            ?? ''
                        )
                    );

                    return [
                        'citation_key' => (string)
                                $item[
                                    'citation_key'
                                ],

                        'claim_label' => 'Evidence from '
                                .$item[
                                    'citation_key'
                                ],

                        /*
                         * Harus exact substring.
                         * CitationValidator nanti mengecek
                         * text ini benar-benar ada pada chunk.
                         */
                        'quote_snippet' => Str::limit(
                            $snippet,
                            180,
                            ''
                        ),
                    ];
                }
            )
            ->all();

        $tool = (string) (
            $payload['tool_name']
            ?? 'Research Tool'
        );

        $question = trim(
            (string) (
                $payload[
                    'user_question'
                ] ?? ''
            )
        );

        $lines = [
            'Analisis ini dibuat dalam mode development/mock untuk menguji alur evidence dan citation.',
            '',
            'Pertanyaan: '.$question,
            '',
            $tool.' menunjukkan bahwa evidence yang diretrieve perlu dibaca sebagai dasar analisis, bukan sebagai output AI yang berdiri sendiri.',
        ];

        foreach (
            $citations as $citation
        ) {
            $lines[] = sprintf(
                '%s mendukung bagian analisis ini [%s].',
                $citation[
                    'claim_label'
                ],
                $citation[
                    'citation_key'
                ]
            );
        }

        return [
            'answer' => implode(
                "\n",
                $lines
            ),

            'citations' => $citations,

            'next_action' => 'Gunakan follow-up untuk mempersempit analisis atau lanjutkan ke Generate Document.',

            'provider_meta' => [
            'provider' => 'mock',

            'model' => 'deterministic-test',
            ],
        ];
    }
}
