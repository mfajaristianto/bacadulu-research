<?php

namespace App\Services\Research;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MockResearchProvider implements AiProvider
{
    public function generate(
        array $payload
    ): array {
        $evidence = collect(
            $payload['evidence']
            ?? []
        )
            ->filter(
                fn ($item) =>
                    is_array($item)
            )
            ->values();

        $question = trim(
            (string) (
                $payload[
                    'user_question'
                ]
                ?? ''
            )
        );

        $toolCode = Str::lower(
            trim(
                (string) (
                    $payload[
                        'tool_code'
                    ]
                    ?? ''
                )
            )
        );

        $toolName = trim(
            (string) (
                $payload[
                    'tool_name'
                ]
                ?? 'Research Tool'
            )
        );

        $taskPrompt = trim(
            (string) (
                $payload[
                    'task_prompt'
                ]
                ?? ''
            )
        );

        $retrievalInstructions = trim(
            (string) (
                $payload[
                    'retrieval_instructions'
                ]
                ?? ''
            )
        );

        $outputInstructions = trim(
            (string) (
                $payload[
                    'output_instructions'
                ]
                ?? ''
            )
        );

        $systemPrompt = trim(
            (string) (
                $payload[
                    'system_prompt'
                ]
                ?? ''
            )
        );

        $compiledPrompt = trim(
            (string) (
                $payload[
                    'compiled_prompt'
                ]
                ?? ''
            )
        );

        $instructionCorpus =
            Str::lower(
                implode(
                    "\n",
                    array_filter([
                        $taskPrompt,

                        $retrievalInstructions,

                        $outputInstructions,
                    ])
                )
            );

        /*
        |--------------------------------------------------------------------------
        | Interpret Prompt Behaviour
        |--------------------------------------------------------------------------
        |
        | Mock tidak berpura-pura menjadi LLM.
        |
        | Ia membaca Prompt Admin secara deterministik dan memilih behaviour
        | berdasarkan intent/format yang dapat dikenali.
        |
        | Artinya perubahan:
        |
        | - Task Prompt
        | - Retrieval Instructions
        | - Output Instructions
        |
        | benar-benar memengaruhi hasil local development.
        |
        */

        $intent = $this->resolveIntent(
            $toolCode,
            $toolName,
            $instructionCorpus
        );

        $format = $this->resolveFormat(
            $outputInstructions
        );

        $language =
            $this->resolveLanguage(
                $question,
                $outputInstructions
            );

        $selectedEvidence =
            $this->selectEvidence(
                $evidence,
                $question,
                $retrievalInstructions,
                $intent
            );

        /*
        |--------------------------------------------------------------------------
        | Safety Fallback
        |--------------------------------------------------------------------------
        */

        if (
            $selectedEvidence
                ->isEmpty()
        ) {
            return [
                'answer' =>
                    $language === 'en'
                        ? 'The available evidence is insufficient to produce a mock synthesis with verifiable citations.'
                        : 'Evidence yang tersedia belum cukup untuk menghasilkan sintesis mock dengan citation yang dapat diverifikasi.',

                'citations' => [],

                'next_action' =>
                    $language === 'en'
                        ? 'Add or retrieve relevant sources, then run the analysis again.'
                        : 'Tambahkan atau retrieve source yang relevan, lalu jalankan analisis kembali.',

                'provider_meta' =>
                    $this->providerMeta(
                        $payload,
                        $intent,
                        $format,
                        $language,
                        $systemPrompt,
                        $taskPrompt,
                        $retrievalInstructions,
                        $outputInstructions,
                        $compiledPrompt,
                        0
                    ),
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Answer Evidence
        |--------------------------------------------------------------------------
        |
        | Format concise hanya memakai maksimum 2 evidence supaya citation array
        | selalu identik dengan citation marker yang muncul di final answer.
        |
        */

        $answerEvidence =
            $format === 'concise'
                ? $selectedEvidence
                    ->take(2)
                    ->values()
                : $selectedEvidence;

        /*
        |--------------------------------------------------------------------------
        | Structured Citations
        |--------------------------------------------------------------------------
        */

        $citations =
            $answerEvidence
                ->map(
                    fn (
                        array $item
                    ): array => [
                        'citation_key' =>
                            (string) $item[
                                'citation_key'
                            ],

                        'claim_label' =>
                            $this->claimLabel(
                                $item,
                                $intent,
                                $language
                            ),

                        /*
                         * Exact substring dari evidence excerpt.
                         *
                         * CitationValidator akan mengecek quote ini
                         * terhadap document chunk yang diretrieve.
                         */
                        'quote_snippet' =>
                            $this
                                ->exactQuoteSnippet(
                                    (string) (
                                        $item[
                                            'text'
                                        ]
                                        ?? ''
                                    )
                                ),
                    ]
                )
                ->all();

        /*
        |--------------------------------------------------------------------------
        | Answer
        |--------------------------------------------------------------------------
        */

        $answer =
            $this->buildAnswer(
                $intent,
                $format,
                $language,
                $toolName,
                $question,
                $answerEvidence,
                $taskPrompt,
                $retrievalInstructions,
                $outputInstructions
            );

        return [
            'answer' =>
                $answer,

            'citations' =>
                $citations,

            'next_action' =>
                $this->nextAction(
                    $intent,
                    $language
                ),

            'provider_meta' =>
                $this->providerMeta(
                    $payload,
                    $intent,
                    $format,
                    $language,
                    $systemPrompt,
                    $taskPrompt,
                    $retrievalInstructions,
                    $outputInstructions,
                    $compiledPrompt,
                    $answerEvidence
                        ->count()
                ),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Prompt Intent
    |--------------------------------------------------------------------------
    */

    private function resolveIntent(
        string $toolCode,
        string $toolName,
        string $instructionCorpus
    ): string {
        $knownCodes = [
            'literature-synthesis',
            'research-gap',
            'theoretical-mapping',
            'conceptual-framework',
            'comparative-analysis',
            'variable-identification',
        ];

        /*
         * Canonical Research Tool code adalah signal utama.
         */

        if (
            in_array(
                $toolCode,
                $knownCodes,
                true
            )
        ) {
            return $toolCode;
        }

        /*
         * Fallback:
         *
         * Kalau nanti Admin membuat tool baru atau code berbeda,
         * mock mencoba membaca Task Prompt + Retrieval + Output.
         */

        $haystack = Str::lower(
            $toolName
            .' '
            .$instructionCorpus
        );

        $rules = [
            'research-gap' => [
                'research gap',
                'limitation',
                'limitations',
                'inconsistent',
                'inconsistency',
                'underexplored',
                'under-explored',
                'future research',
            ],

            'theoretical-mapping' => [
                'theory',
                'theoretical',
                'theoretical lens',
                'theoretical mapping',
            ],

            'conceptual-framework' => [
                'conceptual framework',
                'framework',
                'proposition',
                'construct relationship',
            ],

            'comparative-analysis' => [
                'compare',
                'comparison',
                'comparative',
                'similarities',
                'differences',
            ],

            'variable-identification' => [
                'variable',
                'variables',
                'construct',
                'operationalisation',
                'operationalization',
                'mediator',
                'moderator',
            ],

            'literature-synthesis' => [
                'synthesis',
                'synthesise',
                'synthesize',
                'literature',
                'recurring pattern',
                'main findings',
            ],
        ];

        $scores = [];

        foreach (
            $rules as
            $intent => $keywords
        ) {
            $scores[$intent] =
                collect(
                    $keywords
                )
                    ->filter(
                        fn (
                            string $keyword
                        ): bool =>
                            str_contains(
                                $haystack,
                                $keyword
                            )
                    )
                    ->count();
        }

        arsort(
            $scores
        );

        $intent =
            array_key_first(
                $scores
            );

        return (
            $intent
            &&
            (
                $scores[$intent]
                ?? 0
            ) > 0
        )
            ? $intent
            : 'generic-research';
    }

    /*
    |--------------------------------------------------------------------------
    | Output Format
    |--------------------------------------------------------------------------
    */

    private function resolveFormat(
        string $outputInstructions
    ): string {
        $instructions =
            Str::lower(
                $outputInstructions
            );

        if (
            str_contains(
                $instructions,
                'table'
            )
            ||
            str_contains(
                $instructions,
                'tabel'
            )
        ) {
            return 'table';
        }

        if (
            str_contains(
                $instructions,
                'bullet'
            )
            ||
            str_contains(
                $instructions,
                'points'
            )
            ||
            str_contains(
                $instructions,
                'poin'
            )
        ) {
            return 'bullets';
        }

        if (
            str_contains(
                $instructions,
                'concise'
            )
            ||
            str_contains(
                $instructions,
                'ringkas'
            )
            ||
            str_contains(
                $instructions,
                'brief'
            )
        ) {
            return 'concise';
        }

        return 'sections';
    }

    /*
    |--------------------------------------------------------------------------
    | Output Language
    |--------------------------------------------------------------------------
    */

    private function resolveLanguage(
        string $question,
        string $outputInstructions
    ): string {
        $haystack =
            Str::lower(
                $question
                .' '
                .$outputInstructions
            );

        if (
            str_contains(
                $haystack,
                'in english'
            )
            ||
            str_contains(
                $haystack,
                'english language'
            )
            ||
            str_contains(
                $haystack,
                'bahasa inggris'
            )
        ) {
            return 'en';
        }

        return 'id';
    }

    /*
    |--------------------------------------------------------------------------
    | Evidence Selection
    |--------------------------------------------------------------------------
    |
    | Retriever utama tetap menentukan eligible chunks.
    |
    | Mock hanya meranking evidence yang SUDAH diberikan pipeline.
    | Jadi mock tidak boleh mengambil document baru sendiri.
    |
    */

    private function selectEvidence(
        Collection $evidence,
        string $question,
        string $retrievalInstructions,
        string $intent
    ): Collection {
        $focusTerms =
            $this->focusTerms(
                implode(
                    ' ',
                    [
                        $question,

                        $retrievalInstructions,

                        str_replace(
                            '-',
                            ' ',
                            $intent
                        ),
                    ]
                )
            );

        return $evidence
            ->map(
                function (
                    array $item,
                    int $index
                ) use (
                    $focusTerms
                ): array {
                    $text =
                        Str::lower(
                            (string) (
                                $item[
                                    'text'
                                ]
                                ?? ''
                            )
                        );

                    $matches =
                        collect(
                            $focusTerms
                        )
                            ->filter(
                                fn (
                                    string $term
                                ): bool =>
                                    str_contains(
                                        $text,
                                        $term
                                    )
                            )
                            ->count();

                    $baseScore =
                        (float) (
                            $item[
                                'score'
                            ]
                            ?? 0
                        );

                    return [
                        ...$item,

                        '_mock_focus_score' =>
                            ($matches * 10)
                            + $baseScore
                            - (
                                $index
                                * 0.0001
                            ),
                    ];
                }
            )
            ->sortByDesc(
                '_mock_focus_score'
            )
            ->take(3)
            ->map(
                function (
                    array $item
                ): array {
                    unset(
                        $item[
                            '_mock_focus_score'
                        ]
                    );

                    return $item;
                }
            )
            ->values();
    }

    /**
     * @return array<int, string>
     */
    private function focusTerms(
        string $text
    ): array {
        preg_match_all(
            '/[\p{L}\p{N}]{4,}/u',
            Str::lower(
                $text
            ),
            $matches
        );

        $stopWords = [
            'yang',
            'dan',
            'dengan',
            'dari',
            'untuk',
            'pada',
            'dalam',
            'atau',
            'berdasarkan',
            'penelitian',
            'research',
            'using',
            'only',
            'retrieved',
            'eligible',
            'sources',
            'source',
            'evidence',
            'available',
            'user',
            'question',
            'relevant',
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
            ->unique()
            ->take(20)
            ->values()
            ->all();
    }

    /*
    |--------------------------------------------------------------------------
    | Build Final Answer
    |--------------------------------------------------------------------------
    */

    private function buildAnswer(
        string $intent,
        string $format,
        string $language,
        string $toolName,
        string $question,
        Collection $evidence,
        string $taskPrompt,
        string $retrievalInstructions,
        string $outputInstructions
    ): string {
        $sections =
            $this->sectionPlan(
                $intent,
                $language
            );

        $intro =
            $language === 'en'
                ? sprintf(
                    '%s — mock analysis for: %s',
                    $toolName,
                    $question
                )
                : sprintf(
                    '%s — analisis mock untuk: %s',
                    $toolName,
                    $question
                );

        /*
        |--------------------------------------------------------------------------
        | Table
        |--------------------------------------------------------------------------
        */

        if (
            $format === 'table'
            &&
            $evidence->count()
                >= 2
        ) {
            return $this->tableAnswer(
                $intro,
                $intent,
                $language,
                $evidence
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Bullets
        |--------------------------------------------------------------------------
        */

        if (
            $format === 'bullets'
        ) {
            return $this->bulletAnswer(
                $intro,
                $intent,
                $language,
                $evidence
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Concise
        |--------------------------------------------------------------------------
        */

        if (
            $format === 'concise'
        ) {
            return $this->conciseAnswer(
                $intro,
                $intent,
                $language,
                $evidence
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Structured Sections
        |--------------------------------------------------------------------------
        */

        $lines = [
            $intro,
            '',
        ];

        foreach (
            $sections as
            $index => $section
        ) {
            $item =
                $evidence[
                    $index
                    %
                    $evidence
                        ->count()
                ];

            $lines[] =
                '## '.$section;

            $lines[] =
                $this->evidenceSentence(
                    $item,
                    $intent,
                    $language,
                    $index
                );

            $lines[] = '';
        }

        $lines[] =
            $language === 'en'
                ? 'This development-mode output is deterministic. Its structure is selected from the active Task Prompt, Retrieval Instructions, and Output Instructions; it is not a replacement for the real LLM.'
                : 'Output development ini bersifat deterministik. Struktur jawabannya dipilih dari Task Prompt, Retrieval Instructions, dan Output Instructions aktif; hasil ini bukan pengganti LLM sebenarnya.';

        return trim(
            implode(
                "\n",
                $lines
            )
        );
    }

    /**
     * @return array<int, string>
     */
    private function sectionPlan(
        string $intent,
        string $language
    ): array {
        $plans = [
            'id' => [
                'literature-synthesis' => [
                    'Temuan Utama',
                    'Pola dan Persamaan',
                    'Perbedaan yang Perlu Diperhatikan',
                ],

                'research-gap' => [
                    'Temuan yang Sudah Tersedia',
                    'Keterbatasan atau Inkonsistensi',
                    'Peluang Research Gap',
                ],

                'theoretical-mapping' => [
                    'Teori atau Konsep yang Teridentifikasi',
                    'Kaitan Teori dengan Topik',
                    'Area yang Belum Terpetakan',
                ],

                'conceptual-framework' => [
                    'Konstruk atau Variabel Utama',
                    'Dasar Hubungan Konseptual',
                    'Arah Framework yang Dapat Diusulkan',
                ],

                'comparative-analysis' => [
                    'Dimensi Perbandingan',
                    'Persamaan Antar-Source',
                    'Perbedaan Antar-Source',
                ],

                'variable-identification' => [
                    'Variabel atau Konstruk',
                    'Peran dan Hubungan',
                    'Operationalization yang Tersedia',
                ],

                'generic-research' => [
                    'Analisis Evidence',
                    'Pola yang Terlihat',
                    'Batas Interpretasi',
                ],
            ],

            'en' => [
                'literature-synthesis' => [
                    'Main Findings',
                    'Recurring Patterns and Similarities',
                    'Important Differences',
                ],

                'research-gap' => [
                    'Existing Findings',
                    'Limitations or Inconsistencies',
                    'Potential Research Gap',
                ],

                'theoretical-mapping' => [
                    'Identified Theory or Concepts',
                    'Connection to the Topic',
                    'Areas Not Yet Mapped',
                ],

                'conceptual-framework' => [
                    'Main Constructs or Variables',
                    'Conceptual Relationship Basis',
                    'Defensible Framework Direction',
                ],

                'comparative-analysis' => [
                    'Comparison Dimensions',
                    'Cross-source Similarities',
                    'Cross-source Differences',
                ],

                'variable-identification' => [
                    'Variables or Constructs',
                    'Roles and Relationships',
                    'Available Operationalisation',
                ],

                'generic-research' => [
                    'Evidence Analysis',
                    'Observed Pattern',
                    'Interpretation Boundary',
                ],
            ],
        ];

        return $plans[
            $language
        ][
            $intent
        ]
            ?? $plans[
                $language
            ][
                'generic-research'
            ];
    }

    /*
    |--------------------------------------------------------------------------
    | Bullet Format
    |--------------------------------------------------------------------------
    */

    private function bulletAnswer(
        string $intro,
        string $intent,
        string $language,
        Collection $evidence
    ): string {
        $lines = [
            $intro,
            '',
        ];

        foreach (
            $evidence as
            $index => $item
        ) {
            $lines[] =
                '- '
                .$this
                    ->evidenceSentence(
                        $item,
                        $intent,
                        $language,
                        $index
                    );
        }

        return trim(
            implode(
                "\n",
                $lines
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Concise Format
    |--------------------------------------------------------------------------
    */

    private function conciseAnswer(
        string $intro,
        string $intent,
        string $language,
        Collection $evidence
    ): string {
        $sentences =
            $evidence
                ->take(2)
                ->values()
                ->map(
                    fn (
                        array $item,
                        int $index
                    ): string =>
                        $this
                            ->evidenceSentence(
                                $item,
                                $intent,
                                $language,
                                $index
                            )
                )
                ->all();

        return trim(
            $intro
            ."\n\n"
            .implode(
                ' ',
                $sentences
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Table Format
    |--------------------------------------------------------------------------
    */

    private function tableAnswer(
        string $intro,
        string $intent,
        string $language,
        Collection $evidence
    ): string {
        $lines = [
            $intro,
            '',

            $language === 'en'
                ? '| Source | Evidence-based observation |'
                : '| Source | Observasi berbasis evidence |',

            '| --- | --- |',
        ];

        foreach (
            $evidence as
            $index => $item
        ) {
            $title =
                str_replace(
                    '|',
                    '\|',
                    trim(
                        (string) (
                            $item[
                                'title'
                            ]
                            ?? 'Internal Source'
                        )
                    )
                );

            $sentence =
                str_replace(
                    '|',
                    '\|',
                    $this
                        ->evidenceSentence(
                            $item,
                            $intent,
                            $language,
                            $index
                        )
                );

            $lines[] =
                sprintf(
                    '| %s | %s |',
                    $title !== ''
                        ? $title
                        : 'Internal Source',
                    $sentence
                );
        }

        return trim(
            implode(
                "\n",
                $lines
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Evidence Sentence
    |--------------------------------------------------------------------------
    */

    private function evidenceSentence(
        array $item,
        string $intent,
        string $language,
        int $index
    ): string {
        $citationKey =
            (string) (
                $item[
                    'citation_key'
                ]
                ?? ''
            );

        $title = trim(
            (string) (
                $item[
                    'title'
                ]
                ?? ''
            )
        );

        if (
            $title === ''
        ) {
            $title =
                $language === 'en'
                    ? 'the retrieved source'
                    : 'source yang diretrieve';
        }

        $observation =
            $this->observationText(
                $item,
                $intent,
                $language
            );

        if (
            $language === 'en'
        ) {
            return sprintf(
                '%s provides evidence relevant to this analysis: %s [%s]',
                $title,
                $observation,
                $citationKey
            );
        }

        return sprintf(
            '%s menyediakan evidence yang relevan untuk analisis ini: %s [%s]',
            $title,
            $observation,
            $citationKey
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Extract Useful Sentence
    |--------------------------------------------------------------------------
    */

    private function observationText(
        array $item,
        string $intent,
        string $language
    ): string {
        $text =
            Str::squish(
                (string) (
                    $item[
                        'text'
                    ]
                    ?? ''
                )
            );

        if (
            $text === ''
        ) {
            return $language === 'en'
                ? 'the excerpt contains retrievable material, but no concise text could be extracted'
                : 'excerpt mengandung materi yang diretrieve, tetapi tidak ada teks ringkas yang dapat diambil';
        }

        $keywords =
            $this->intentKeywords(
                $intent
            );

        $sentences =
            preg_split(
                '/(?<=[.!?])\s+/u',
                $text
            )
            ?: [];

        $best =
            collect(
                $sentences
            )
                ->map(
                    fn (
                        string $sentence
                    ): string =>
                        trim(
                            $sentence
                        )
                )
                ->filter()
                ->sortByDesc(
                    function (
                        string $sentence
                    ) use (
                        $keywords
                    ): int {
                        $lower =
                            Str::lower(
                                $sentence
                            );

                        return collect(
                            $keywords
                        )
                            ->filter(
                                fn (
                                    string $keyword
                                ): bool =>
                                    str_contains(
                                        $lower,
                                        $keyword
                                    )
                            )
                            ->count();
                    }
                )
                ->first();

        if (
            ! $best
        ) {
            $best =
                $text;
        }

        return Str::limit(
            $best,
            260,
            '…'
        );
    }

    /**
     * @return array<int, string>
     */
    private function intentKeywords(
        string $intent
    ): array {
        return match (
            $intent
        ) {
            'research-gap' => [
                'limitation',
                'limitations',
                'inconsistent',
                'inconsistency',
                'future research',
                'recommendation',
                'gap',
                'restricted',
                'scope',
            ],

            'theoretical-mapping' => [
                'theory',
                'theoretical',
                'framework',
                'lens',
                'concept',
            ],

            'conceptual-framework' => [
                'variable',
                'construct',
                'relationship',
                'mediation',
                'moderation',
                'framework',
            ],

            'comparative-analysis' => [
                'method',
                'sample',
                'result',
                'finding',
                'variable',
                'object',
            ],

            'variable-identification' => [
                'variable',
                'construct',
                'measure',
                'measurement',
                'indicator',
                'mediator',
                'moderator',
                'dependent',
                'independent',
            ],

            default => [
                'finding',
                'result',
                'study',
                'research',
                'evidence',
            ],
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Citation Label
    |--------------------------------------------------------------------------
    */

    private function claimLabel(
        array $item,
        string $intent,
        string $language
    ): string {
        $citationKey =
            (string) (
                $item[
                    'citation_key'
                ]
                ?? 'Evidence'
            );

        $label =
            match (
                $intent
            ) {
                'research-gap' =>
                    $language === 'en'
                        ? 'Gap-related evidence'
                        : 'Evidence terkait research gap',

                'theoretical-mapping' =>
                    $language === 'en'
                        ? 'Theory-related evidence'
                        : 'Evidence terkait teori',

                'conceptual-framework' =>
                    $language === 'en'
                        ? 'Framework-related evidence'
                        : 'Evidence terkait framework',

                'comparative-analysis' =>
                    $language === 'en'
                        ? 'Comparison evidence'
                        : 'Evidence perbandingan',

                'variable-identification' =>
                    $language === 'en'
                        ? 'Variable-related evidence'
                        : 'Evidence terkait variabel',

                default =>
                    $language === 'en'
                        ? 'Synthesis evidence'
                        : 'Evidence sintesis',
            };

        return $label
            .' '
            .$citationKey;
    }

    /*
    |--------------------------------------------------------------------------
    | Exact Quote
    |--------------------------------------------------------------------------
    */

    private function exactQuoteSnippet(
        string $text
    ): string {
        $text = trim(
            $text
        );

        if (
            $text === ''
        ) {
            return '';
        }

        /*
         * Tanpa suffix.
         *
         * Jadi hasil tetap exact substring
         * dari excerpt yang diretrieve.
         */

        return Str::limit(
            $text,
            180,
            ''
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Suggested Next Action
    |--------------------------------------------------------------------------
    */

    private function nextAction(
        string $intent,
        string $language
    ): string {
        if (
            $language === 'en'
        ) {
            return match (
                $intent
            ) {
                'research-gap' =>
                    'Use the identified opportunity as a candidate research problem, then test it against additional sources.',

                'theoretical-mapping' =>
                    'Continue to Conceptual Framework to connect the mapped theory with relevant constructs.',

                'conceptual-framework' =>
                    'Continue to Variable Identification to verify the role and operationalisation of each construct.',

                'comparative-analysis' =>
                    'Refine the comparison dimensions or continue to Research Gap to inspect unresolved differences.',

                'variable-identification' =>
                    'Verify definitions and measurements across additional sources before building the final model.',

                default =>
                    'Use a follow-up question to narrow the synthesis or continue to the next Research Tool.',
            };
        }

        return match (
            $intent
        ) {
            'research-gap' =>
                'Gunakan peluang yang teridentifikasi sebagai kandidat research problem, lalu uji lagi dengan source tambahan.',

            'theoretical-mapping' =>
                'Lanjutkan ke Conceptual Framework untuk menghubungkan teori yang dipetakan dengan konstruk yang relevan.',

            'conceptual-framework' =>
                'Lanjutkan ke Variable Identification untuk memeriksa peran dan operationalization setiap konstruk.',

            'comparative-analysis' =>
                'Persempit dimensi perbandingan atau lanjutkan ke Research Gap untuk melihat perbedaan yang belum terjelaskan.',

            'variable-identification' =>
                'Verifikasi definisi dan measurement pada source tambahan sebelum menyusun model akhir.',

            default =>
                'Gunakan follow-up untuk mempersempit sintesis atau lanjutkan ke Research Tool berikutnya.',
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Provider Metadata
    |--------------------------------------------------------------------------
    |
    | Isi prompt internal TIDAK disimpan pada provider_meta.
    |
    | Hanya SHA-256 hash.
    |
    | Jadi Admin dapat membuktikan bahwa Prompt Version A dan Prompt Version B
    | memang menghasilkan input provider yang berbeda tanpa mengekspos isi
    | instruction ke user.
    |
    */

    private function providerMeta(
        array $payload,
        string $intent,
        string $format,
        string $language,
        string $systemPrompt,
        string $taskPrompt,
        string $retrievalInstructions,
        string $outputInstructions,
        string $compiledPrompt,
        int $evidenceUsed
    ): array {
        return [
            'provider' =>
                'mock',

            'model' =>
                'prompt-aware-deterministic-v2',

            'tool_code' =>
                (string) (
                    $payload[
                        'tool_code'
                    ]
                    ?? ''
                ),

            'prompt_id' =>
                (int) (
                    $payload[
                        'prompt_id'
                    ]
                    ?? 0
                ),

            'prompt_version' =>
                (int) (
                    $payload[
                        'prompt_version'
                    ]
                    ?? 0
                ),

            'interpreted_intent' =>
                $intent,

            'interpreted_format' =>
                $format,

            'language' =>
                $language,

            'evidence_used' =>
                $evidenceUsed,

            'system_prompt_hash' =>
                hash(
                    'sha256',
                    $systemPrompt
                ),

            'task_prompt_hash' =>
                hash(
                    'sha256',
                    $taskPrompt
                ),

            'retrieval_instructions_hash' =>
                hash(
                    'sha256',
                    $retrievalInstructions
                ),

            'output_instructions_hash' =>
                hash(
                    'sha256',
                    $outputInstructions
                ),

            'compiled_prompt_hash' =>
                hash(
                    'sha256',
                    $compiledPrompt
                ),
        ];
    }
}