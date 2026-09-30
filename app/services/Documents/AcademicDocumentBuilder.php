<?php

namespace App\Services\Documents;

use App\Models\AiRun;
use RuntimeException;

class AcademicDocumentBuilder
{
    public function build(
        AiRun $run,
        string $documentType,
        string $citationStyle,
        ?string $customTitle = null
    ): array {
        $run->loadMissing([
            'conversation',
            'primaryResult',
            'retrievals.document',
            'sources',
            'citations',
            'aiTool',
            'promptTemplate',
        ]);

        $result = $run->primaryResult;

        if (! $result) {
            throw new RuntimeException(
                'AI Run belum memiliki final research result.'
            );
        }

        if ($run->status !== 'completed') {
            throw new RuntimeException(
                'Hanya AI Run yang sudah completed yang dapat dijadikan dokumen.'
            );
        }

        $structured =
            is_array(
                $result->structured_output_json
            )
                ? $result->structured_output_json
                : [];

        $answer = trim(
            (string) (
                $structured['answer']
                ?? $result->content
                ?? ''
            )
        );

        if ($answer === '') {
            throw new RuntimeException(
                'Final research result kosong.'
            );
        }

        $evidence = collect(
            $structured['evidence']
            ?? []
        )
            ->filter(
                fn ($item) =>
                    is_array($item)
            )
            ->values()
            ->all();

        $sources = collect(
            $structured['sources']
            ?? []
        );

        if ($sources->isEmpty()) {
            $sources = $run
                ->sources
                ->map(
                    fn ($source): array => [
                        'document_id'
                            => $source->id,

                        'title'
                            => $source->title,

                        'publisher'
                            => $source->publisher,

                        'year'
                            => $source->publication_year,

                        'doi'
                            => $source->doi,

                        'isbn'
                            => $source->isbn,

                        'source_label'
                            => $source->source_label,
                    ]
                );
        }

        $sources = $sources
            ->filter(
                fn ($item) =>
                    is_array($item)
            )
            ->values()
            ->all();

        $title = trim(
            (string) $customTitle
        );

        if ($title === '') {
            $title = $this->defaultTitle(
                $documentType,
                $run
            );
        }

        $sections =
            $this->buildSections(
                $documentType,
                $answer,
                $evidence,
                $run
            );

        $references =
            $this->references(
                $sources,
                $citationStyle
            );

        $citationKeys =
            $run
                ->citations()
                ->where(
                    'is_valid',
                    true
                )
                ->pluck(
                    'citation_key'
                )
                ->unique()
                ->sort()
                ->values()
                ->all();

        return [
            'title'
                => $title,

            'document_type'
                => $documentType,

            'citation_style'
                => $citationStyle,

            'sections'
                => $sections,

            'references'
                => $references,

            'provenance'
                => [
                    'conversation_id'
                        => $run
                            ->conversation_id,

                    'ai_run_id'
                        => $run->id,

                    'ai_run_uuid'
                        => $run->uuid,

                    'research_tool'
                        => $run
                            ->aiTool
                            ?->name,

                    'research_tool_code'
                        => $run
                            ->aiTool
                            ?->code,

                    'prompt'
                        => $run
                            ->promptTemplate
                            ?->name,

                    'prompt_version'
                        => $run
                            ->promptTemplate
                            ?->version,

                    'provider'
                        => $run
                            ->model_provider,

                    'model'
                        => $run
                            ->model_name,

                    'citation_keys'
                        => $citationKeys,

                    'source_ids'
                        => collect(
                            $sources
                        )
                            ->pluck(
                                'document_id'
                            )
                            ->filter()
                            ->unique()
                            ->values()
                            ->all(),

                    'source_count'
                        => count(
                            $sources
                        ),

                    'evidence_count'
                        => count(
                            $evidence
                        ),

                    'validated_result'
                        => true,

                    'generated_at'
                        => now()
                            ->toIso8601String(),
                ],
        ];
    }

    private function defaultTitle(
        string $documentType,
        AiRun $run
    ): string {
        $question = trim(
            (string) data_get(
                $run
                    ->user_parameters_json,
                'user_message',
                ''
            )
        );

        if ($question !== '') {
            return $this->upper(
                $question
            );
        }

        return $this->upper(
            $documentType
            .' — BacaDulu Research'
        );
    }

    private function buildSections(
        string $documentType,
        string $answer,
        array $evidence,
        AiRun $run
    ): array {
        $definition =
            $this->sectionDefinitions(
                $documentType
            );

        $sections = [];

        foreach (
            $definition
            as $section
        ) {
            $role =
                $section['role'];

            $body = match ($role) {
                'research_result'
                    => $answer,

                'evidence'
                    => $this
                        ->evidenceSection(
                            $evidence
                        ),

                'method_note'
                    => $this
                        ->methodNote(
                            $run
                        ),

                'conclusion'
                    => $this
                        ->conclusionNote(
                            $answer
                        ),

                'draft'
                    => $this
                        ->draftNotice(
                            $section[
                                'heading'
                            ]
                        ),

                default
                    => '',
            };

            $sections[] = [
                'heading'
                    => $section[
                        'heading'
                    ],

                'body'
                    => $body,

                /*
                 * Metadata internal editor.
                 * Tidak wajib ditampilkan ke reader.
                 */
                'section_role'
                    => $role,
            ];
        }

        return $sections;
    }

    private function sectionDefinitions(
        string $documentType
    ): array {
        return match ($documentType) {
            'Research Article'
                => [
                    [
                        'heading'
                            => 'A. PENDAHULUAN',
                        'role'
                            => 'draft',
                    ],
                    [
                        'heading'
                            => 'B. LATAR BELAKANG',
                        'role'
                            => 'draft',
                    ],
                    [
                        'heading'
                            => 'C. TINJAUAN PUSTAKA',
                        'role'
                            => 'evidence',
                    ],
                    [
                        'heading'
                            => 'D. METODE PENELITIAN',
                        'role'
                            => 'method_note',
                    ],
                    [
                        'heading'
                            => 'E. HASIL DAN PEMBAHASAN',
                        'role'
                            => 'research_result',
                    ],
                    [
                        'heading'
                            => 'KESIMPULAN',
                        'role'
                            => 'conclusion',
                    ],
                ],

            'Literature Review'
                => [
                    [
                        'heading'
                            => 'A. PENDAHULUAN',
                        'role'
                            => 'draft',
                    ],
                    [
                        'heading'
                            => 'B. RUANG LINGKUP LITERATUR',
                        'role'
                            => 'evidence',
                    ],
                    [
                        'heading'
                            => 'C. SINTESIS TEMUAN',
                        'role'
                            => 'research_result',
                    ],
                    [
                        'heading'
                            => 'D. EVIDENCE MAP',
                        'role'
                            => 'evidence',
                    ],
                    [
                        'heading'
                            => 'E. IMPLIKASI DAN RESEARCH GAP',
                        'role'
                            => 'research_result',
                    ],
                    [
                        'heading'
                            => 'KESIMPULAN',
                        'role'
                            => 'conclusion',
                    ],
                ],

            'Research Proposal'
                => [
                    [
                        'heading'
                            => 'A. PENDAHULUAN',
                        'role'
                            => 'draft',
                    ],
                    [
                        'heading'
                            => 'B. LATAR BELAKANG',
                        'role'
                            => 'research_result',
                    ],
                    [
                        'heading'
                            => 'C. RUMUSAN MASALAH',
                        'role'
                            => 'draft',
                    ],
                    [
                        'heading'
                            => 'D. TINJAUAN PUSTAKA',
                        'role'
                            => 'evidence',
                    ],
                    [
                        'heading'
                            => 'E. KERANGKA KONSEPTUAL',
                        'role'
                            => 'draft',
                    ],
                    [
                        'heading'
                            => 'F. METODE PENELITIAN',
                        'role'
                            => 'method_note',
                    ],
                ],

            'Research Background'
                => [
                    [
                        'heading'
                            => 'A. LATAR BELAKANG',
                        'role'
                            => 'research_result',
                    ],
                    [
                        'heading'
                            => 'B. EVIDENCE UTAMA',
                        'role'
                            => 'evidence',
                    ],
                    [
                        'heading'
                            => 'C. IMPLIKASI PENELITIAN',
                        'role'
                            => 'conclusion',
                    ],
                ],

            'Research Gap'
                => [
                    [
                        'heading'
                            => 'A. ANALISIS RESEARCH GAP',
                        'role'
                            => 'research_result',
                    ],
                    [
                        'heading'
                            => 'B. SUPPORTING EVIDENCE',
                        'role'
                            => 'evidence',
                    ],
                    [
                        'heading'
                            => 'C. CATATAN INTERPRETASI',
                        'role'
                            => 'conclusion',
                    ],
                ],

            'Theory Review'
                => [
                    [
                        'heading'
                            => 'A. THEORETICAL CONTEXT',
                        'role'
                            => 'research_result',
                    ],
                    [
                        'heading'
                            => 'B. SUPPORTING EVIDENCE',
                        'role'
                            => 'evidence',
                    ],
                    [
                        'heading'
                            => 'C. IMPLIKASI TEORETIS',
                        'role'
                            => 'conclusion',
                    ],
                ],

            'Variable Definition'
                => [
                    [
                        'heading'
                            => 'A. IDENTIFIKASI VARIABEL',
                        'role'
                            => 'research_result',
                    ],
                    [
                        'heading'
                            => 'B. SUPPORTING EVIDENCE',
                        'role'
                            => 'evidence',
                    ],
                    [
                        'heading'
                            => 'C. CATATAN OPERASIONALISASI',
                        'role'
                            => 'conclusion',
                    ],
                ],

            'Hypothesis Development'
                => [
                    [
                        'heading'
                            => 'A. DASAR TEORETIS DAN EMPIRIS',
                        'role'
                            => 'research_result',
                    ],
                    [
                        'heading'
                            => 'B. SUPPORTING EVIDENCE',
                        'role'
                            => 'evidence',
                    ],
                    [
                        'heading'
                            => 'C. PENGEMBANGAN HIPOTESIS',
                        'role'
                            => 'draft',
                    ],
                ],

            'Conference Paper'
                => [
                    [
                        'heading'
                            => 'A. PENDAHULUAN',
                        'role'
                            => 'draft',
                    ],
                    [
                        'heading'
                            => 'B. LITERATURE REVIEW',
                        'role'
                            => 'evidence',
                    ],
                    [
                        'heading'
                            => 'C. METHOD',
                        'role'
                            => 'method_note',
                    ],
                    [
                        'heading'
                            => 'D. RESULTS AND DISCUSSION',
                        'role'
                            => 'research_result',
                    ],
                    [
                        'heading'
                            => 'KESIMPULAN',
                        'role'
                            => 'conclusion',
                    ],
                ],

            'Book Chapter'
                => [
                    [
                        'heading'
                            => 'A. INTRODUCTION',
                        'role'
                            => 'draft',
                    ],
                    [
                        'heading'
                            => 'B. RESEARCH EVIDENCE',
                        'role'
                            => 'evidence',
                    ],
                    [
                        'heading'
                            => 'C. SYNTHESIS AND DISCUSSION',
                        'role'
                            => 'research_result',
                    ],
                    [
                        'heading'
                            => 'KESIMPULAN',
                        'role'
                            => 'conclusion',
                    ],
                ],

            'Research Report'
                => [
                    [
                        'heading'
                            => 'A. EXECUTIVE SUMMARY',
                        'role'
                            => 'conclusion',
                    ],
                    [
                        'heading'
                            => 'B. RESEARCH EVIDENCE',
                        'role'
                            => 'evidence',
                    ],
                    [
                        'heading'
                            => 'C. ANALYSIS',
                        'role'
                            => 'research_result',
                    ],
                    [
                        'heading'
                            => 'D. NEXT RESEARCH ACTION',
                        'role'
                            => 'conclusion',
                    ],
                ],

            default
                => [
                    [
                        'heading'
                            => 'A. RESEARCH RESULT',
                        'role'
                            => 'research_result',
                    ],
                    [
                        'heading'
                            => 'B. EVIDENCE',
                        'role'
                            => 'evidence',
                    ],
                    [
                        'heading'
                            => 'KESIMPULAN',
                        'role'
                            => 'conclusion',
                    ],
                ],
        };
    }

    private function evidenceSection(
        array $evidence
    ): string {
        if ($evidence === []) {
            return 'Tidak ada evidence yang tersedia untuk bagian ini.';
        }

        return collect(
            $evidence
        )
            ->map(
                function (
                    array $item
                ): string {
                    $key = trim(
                        (string) (
                            $item[
                                'citation_key'
                            ] ?? ''
                        )
                    );

                    $title = trim(
                        (string) (
                            $item['title']
                            ?? 'Internal Source'
                        )
                    );

                    $year =
                        $item['year']
                        ?? null;

                    $text = trim(
                        (string) (
                            $item['text']
                            ?? ''
                        )
                    );

                    $source = '['
                        .$key
                        .'] '
                        .$title;

                    if ($year) {
                        $source .=
                            ' ('.$year.')';
                    }

                    if ($text !== '') {
                        $source .=
                            "\n".$text;
                    }

                    return $source;
                }
            )
            ->implode(
                "\n\n"
            );
    }

    private function methodNote(
        AiRun $run
    ): string {
        return implode(
            "\n\n",
            [
                'Dokumen ini dibangun dari AI Research Run yang menggunakan source internal terkurasi dan evidence retrieval.',

                'Bagian metode penelitian empiris belum dapat ditentukan hanya dari hasil literature-based AI analysis ini. Lengkapi desain penelitian, populasi/sampel, teknik pengumpulan data, pengukuran variabel, dan metode analisis sesuai penelitian yang akan dilakukan.',

                'Research Tool: '
                    .(
                        $run
                            ->aiTool
                            ?->name
                        ?: '—'
                    )
                    .'.',
            ]
        );
    }

    private function conclusionNote(
        string $answer
    ): string {
        $paragraphs = preg_split(
            '/\n{2,}/u',
            trim($answer)
        ) ?: [];

        $paragraphs = collect(
            $paragraphs
        )
            ->map(
                fn ($paragraph) =>
                    trim(
                        (string) $paragraph
                    )
            )
            ->filter()
            ->values();

        if ($paragraphs->isEmpty()) {
            return 'Kesimpulan perlu dikembangkan berdasarkan hasil research dan evidence yang tersedia.';
        }

        /*
         * Ambil bagian akhir jawaban sebagai draft conclusion.
         * Tidak menghasilkan klaim baru.
         */
        return $paragraphs
            ->slice(
                max(
                    0,
                    $paragraphs->count() - 2
                )
            )
            ->implode(
                "\n\n"
            );
    }

    private function draftNotice(
        string $heading
    ): string {
        return 'Bagian '
            .$heading
            .' belum dapat diisi secara defensible hanya dari research result saat ini. '
            .'Lengkapi bagian ini menggunakan konteks penelitian, tujuan penelitian, desain penelitian, dan informasi akademik yang sesuai. '
            .'Sistem tidak mengisi bagian ini dengan klaim yang tidak memiliki evidence.';
    }

    private function references(
        array $sources,
        string $citationStyle
    ): array {
        return collect(
            $sources
        )
            ->map(
                function (
                    array $source,
                    int $index
                ) use (
                    $citationStyle
                ): string {
                    $title = trim(
                        (string) (
                            $source['title']
                            ?? 'Untitled source'
                        )
                    );

                    $year =
                        $source['year']
                        ?? null;

                    $publisher = trim(
                        (string) (
                            $source['publisher']
                            ?? $source[
                                'source_label'
                            ]
                            ?? ''
                        )
                    );

                    $doi = trim(
                        (string) (
                            $source['doi']
                            ?? ''
                        )
                    );

                    $isbn = trim(
                        (string) (
                            $source['isbn']
                            ?? ''
                        )
                    );

                    $style =
                        strtoupper(
                            $citationStyle
                        );

                    if ($style === 'IEEE') {
                        $reference =
                            '['
                            .($index + 1)
                            .'] “'
                            .$title
                            .'”';

                        if ($publisher !== '') {
                            $reference .=
                                ', '
                                .$publisher;
                        }

                        if ($year) {
                            $reference .=
                                ', '
                                .$year;
                        }

                        $reference .= '.';
                    } elseif (
                        $style === 'HARVARD'
                    ) {
                        $reference =
                            $title;

                        if ($year) {
                            $reference .=
                                ' ('
                                .$year
                                .')';
                        }

                        if ($publisher !== '') {
                            $reference .=
                                '. '
                                .$publisher;
                        }

                        $reference .= '.';
                    } else {
                        /*
                         * APA-like fallback ketika author
                         * metadata belum tersedia.
                         *
                         * Jangan invent author.
                         */
                        $reference =
                            $title
                            .'. ';

                        if ($year) {
                            $reference .=
                                '('
                                .$year
                                .'). ';
                        }

                        if ($publisher !== '') {
                            $reference .=
                                $publisher
                                .'.';
                        }
                    }

                    if ($doi !== '') {
                        $reference .=
                            ' DOI: '
                            .$doi;
                    }

                    if (
                        $doi === ''
                        && $isbn !== ''
                    ) {
                        $reference .=
                            ' ISBN: '
                            .$isbn;
                    }

                    return trim(
                        $reference
                    );
                }
            )
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function upper(
        string $value
    ): string {
        return function_exists(
            'mb_strtoupper'
        )
            ? mb_strtoupper(
                $value,
                'UTF-8'
            )
            : strtoupper(
                $value
            );
    }
}