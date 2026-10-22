<?php

namespace App\Services\Documents;

use RuntimeException;

class PdfRenderer
{
    public function render(
        array $content,
        string $absolutePath
    ): void {
        $format =
            $this
                ->format(
                    $content
                );

        $blocks =
            $this
                ->documentBlocks(
                    $content,
                    $format
                );

        $pages =
            $this
                ->paginate(
                    $blocks,
                    $format
                );

        if (
            $pages === []
        ) {
            $pages = [
                [
                    [
                        'text' =>
                            'EMPTY DOCUMENT',

                        'type' =>
                            'body',

                        'first' =>
                            true,
                    ],
                ],
            ];
        }

        $objects = [];

        $pageObjectIds = [];

        $nextId =
            5;

        foreach (
            $pages as
            $pageBlocks
        ) {
            $pageId =
                $nextId++;

            $contentId =
                $nextId++;

            $pageObjectIds[] =
                $pageId;

            $stream =
                $this
                    ->pageStream(
                        $pageBlocks,
                        $format
                    );

            $objects[
                $contentId
            ] =
                '<< /Length '
                .strlen(
                    $stream
                )
                ." >>\nstream\n"
                .$stream
                ."\nendstream";

            $objects[
                $pageId
            ] =
                '<< /Type /Page '
                .'/Parent 2 0 R '
                .'/MediaBox [0 0 595 842] '
                .'/Resources << '
                .'/Font << '
                .'/F1 3 0 R '
                .'/F2 4 0 R '
                .'>> '
                .'>> '
                .'/Contents '
                .$contentId
                .' 0 R >>';
        }

        $objects[
            1
        ] =
            '<< /Type /Catalog /Pages 2 0 R >>';

        $kids =
            implode(
                ' ',
                array_map(
                    fn (
                        int $id
                    ): string =>
                        $id
                        .' 0 R',
                    $pageObjectIds
                )
            );

        $objects[
            2
        ] =
            '<< /Type /Pages /Count '
            .count(
                $pageObjectIds
            )
            .' /Kids ['
            .$kids
            .'] >>';

        $objects[
            3
        ] =
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

        $objects[
            4
        ] =
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';

        ksort(
            $objects
        );

        $pdf =
            "%PDF-1.4\n";

        $offsets = [
            0 => 0,
        ];

        foreach (
            $objects as
            $id =>
            $object
        ) {
            $offsets[
                $id
            ] =
                strlen(
                    $pdf
                );

            $pdf .=
                $id
                ." 0 obj\n"
                .$object
                ."\nendobj\n";
        }

        $xref =
            strlen(
                $pdf
            );

        $maxId =
            max(
                array_keys(
                    $objects
                )
            );

        $pdf .=
            "xref\n0 "
            .(
                $maxId
                +
                1
            )
            ."\n";

        $pdf .=
            "0000000000 65535 f \n";

        for (
            $id = 1;
            $id <= $maxId;
            $id++
        ) {
            $pdf .=
                sprintf(
                    "%010d 00000 n \n",
                    $offsets[
                        $id
                    ]
                    ??
                    0
                );
        }

        $pdf .=
            'trailer << /Size '
            .(
                $maxId
                +
                1
            )
            .' /Root 1 0 R >>'
            ."\n";

        $pdf .=
            "startxref\n"
            .$xref
            ."\n%%EOF";

        if (
            file_put_contents(
                $absolutePath,
                $pdf
            )
            ===
            false
        ) {
            throw new RuntimeException(
                'PDF gagal dibuat.'
            );
        }

        if (
            ! is_file(
                $absolutePath
            )
            ||
            filesize(
                $absolutePath
            )
            <=
            0
        ) {
            throw new RuntimeException(
                'PDF output tidak valid.'
            );
        }
    }

    private function documentBlocks(
        array $content,
        array $format
    ): array {
        $blocks = [];

        $title =
            (string) (
                $content[
                    'title'
                ]
                ??
                'RESEARCH DOCUMENT'
            );

        if (
            $format[
                'title_uppercase'
            ]
        ) {
            $title =
                $this->upper(
                    $title
                );
        }

        $blocks[] = [
            'type' =>
                'title',

            'text' =>
                $title,
        ];

        $blocks[] = [
            'type' =>
                'meta',

            'text' =>
                (string) ($content['document_type'] ?? 'Research Document'),
        ];

        foreach (
            $content[
                'sections'
            ]
            ??
            [] as
            $section
        ) {
            $heading =
                trim(
                    (string) (
                        $section[
                            'heading'
                        ]
                        ??
                        ''
                    )
                );

            if (
                $heading !== ''
            ) {
                if (
                    $format[
                        'heading_uppercase'
                    ]
                ) {
                    $heading =
                        $this->upper(
                            $heading
                        );
                }

                $blocks[] = [
                    'type' =>
                        'heading',

                    'text' =>
                        PrintableText::clean($heading),
                ];
            }

            $paragraphs =
                preg_split(
                    '/\n{2,}/u',
                    (string) (
                        $section[
                            'body'
                        ]
                        ??
                        ''
                    )
                )
                ?:
                [];

            foreach (
                $paragraphs as
                $paragraph
            ) {
                $paragraph =
                    trim(
                        $paragraph
                    );

                if (
                    $paragraph === ''
                ) {
                    continue;
                }

                $blocks[] = [
                    'type' =>
                        'body',

                    'text' =>
                        PrintableText::clean($paragraph),
                ];
            }
        }

        $references =
            $content[
                'references'
            ]
            ??
            [];

        if (
            $references !== []
        ) {
            $blocks[] = [
                'type' =>
                    'heading',

                'text' =>
                    'DAFTAR PUSTAKA',
            ];

            foreach (
                $references as
                $reference
            ) {
                $blocks[] = [
                    'type' =>
                        'reference',

                    'text' =>
                        (string)
                            $reference,
                ];
            }
        }

        $provenance =
            $content[
                'provenance'
            ]
            ??
            [];

        if (
            $provenance !== []
        ) {
            $blocks[] = [
                'type' =>
                    'heading',

                'text' =>
                    'DOCUMENT PROVENANCE',
            ];

            foreach (
                $this
                    ->provenanceLines(
                        $provenance
                    ) as
                $line
            ) {
                $blocks[] = [
                    'type' =>
                        'meta',

                    'text' =>
                        $line,
                ];
            }
        }

        return $blocks;
    }

    private function paginate(
        array $blocks,
        array $format
    ): array {
        $pages = [];

        $page = [];

        $usedUnits =
            0.0;

        $maximumUnits =
            48.0;

        foreach (
            $blocks as
            $block
        ) {
            $wrapped =
                $this
                    ->wrappedBlock(
                        $block,
                        $format
                    );

            $needed =
                collect(
                    $wrapped
                )
                    ->sum(
                        fn (
                            array $line
                        ): float =>
                            $this
                                ->lineUnit(
                                    $line[
                                        'type'
                                    ],
                                    $format
                                )
                    );

            if (
                $page !== []
                &&
                (
                    $usedUnits
                    +
                    $needed
                )
                >
                $maximumUnits
            ) {
                $pages[] =
                    $page;

                $page = [];

                $usedUnits =
                    0.0;
            }

            foreach (
                $wrapped as
                $line
            ) {
                $page[] =
                    $line;

                $usedUnits +=
                    $this
                        ->lineUnit(
                            $line[
                                'type'
                            ],
                            $format
                        );
            }
        }

        if (
            $page !== []
        ) {
            $pages[] =
                $page;
        }

        return $pages;
    }

    private function wrappedBlock(
        array $block,
        array $format
    ): array {
        $type =
            $block[
                'type'
            ]
            ??
            'body';

        $text =
            trim(
                (string) (
                    $block[
                        'text'
                    ]
                    ??
                    ''
                )
            );

        if (
            $text === ''
        ) {
            return [];
        }

        $width =
            match (
                $type
            ) {
                'title' =>
                    54,

                'heading' =>
                    70,

                'reference' =>
                    82,

                'meta' =>
                    90,

                default =>
                    82,
            };

        if (
            $format[
                'body_font_size'
            ]
            >
            12
        ) {
            $width -=
                4;
        }

        $lines =
            $this->wrap(
                $text,
                max(
                    45,
                    $width
                )
            );

        return collect(
            $lines
        )
            ->map(
                fn (
                    string $line,
                    int $index
                ): array => [
                    'type' =>
                        $type,

                    'text' =>
                        $line,

                    'first' =>
                        $index
                        ===
                        0,
                ]
            )
            ->values()
            ->all();
    }

    private function pageStream(
        array $blocks,
        array $format
    ): string {
        $commands = [
            'BT',
            '50 790 Td',
        ];

        $firstLine =
            true;

        foreach (
            $blocks as
            $block
        ) {
            $type =
                $block[
                    'type'
                ]
                ??
                'body';

            $line =
                $this
                    ->encodePdfText(
                        (string) (
                            $block[
                                'text'
                            ]
                            ??
                            ''
                        )
                    );

            $style =
                $this
                    ->style(
                        $type,
                        $format
                    );

            if (
                ! $firstLine
            ) {
                $commands[] =
                    '0 -'
                    .$style[
                        'leading'
                    ]
                    .' Td';
            }

            $commands[] =
                '/'
                .$style[
                    'font'
                ]
                .' '
                .$style[
                    'size'
                ]
                .' Tf';

            if (
                $type
                ===
                'body'
                &&
                (
                    $block[
                        'first'
                    ]
                    ??
                    false
                )
                &&
                $format[
                    'first_line_indent_cm'
                ]
                >
                0
            ) {
                $indentPoints =
                    round(
                        $format[
                            'first_line_indent_cm'
                        ]
                        *
                        28.3465,
                        2
                    );

                $commands[] =
                    $indentPoints
                    .' 0 Td';

                $commands[] =
                    '('
                    .$line
                    .') Tj';

                $commands[] =
                    (
                        -1
                        *
                        $indentPoints
                    )
                    .' 0 Td';
            } else {
                $commands[] =
                    '('
                    .$line
                    .') Tj';
            }

            $firstLine =
                false;
        }

        $commands[] =
            'ET';

        return implode(
            "\n",
            $commands
        );
    }

    private function style(
        string $type,
        array $format
    ): array {
        return match (
            $type
        ) {
            'title' => [
                'font' =>
                    'F2',

                'size' =>
                    $format[
                        'title_font_size'
                    ],

                'leading' =>
                    max(
                        22,
                        (int)
                            round(
                                $format[
                                    'title_font_size'
                                ]
                                *
                                1.35
                            )
                    ),
            ],

            'heading' => [
                'font' =>
                    'F2',

                'size' =>
                    $format[
                        'heading_font_size'
                    ],

                'leading' =>
                    max(
                        18,
                        (int)
                            round(
                                $format[
                                    'heading_font_size'
                                ]
                                *
                                1.45
                            )
                    ),
            ],

            'meta' => [
                'font' =>
                    'F1',

                'size' =>
                    $format[
                        'provenance_font_size'
                    ],

                'leading' =>
                    max(
                        12,
                        (int)
                            round(
                                $format[
                                    'provenance_font_size'
                                ]
                                *
                                1.35
                            )
                    ),
            ],

            'reference' => [
                'font' =>
                    'F1',

                'size' =>
                    $format[
                        'reference_font_size'
                    ],

                'leading' =>
                    max(
                        13,
                        (int)
                            round(
                                $format[
                                    'reference_font_size'
                                ]
                                *
                                1.3
                            )
                    ),
            ],

            default => [
                'font' =>
                    'F1',

                'size' =>
                    $format[
                        'body_font_size'
                    ],

                'leading' =>
                    max(
                        15,
                        (int)
                            round(
                                $format[
                                    'body_font_size'
                                ]
                                *
                                $format[
                                    'line_spacing'
                                ]
                            )
                    ),
            ],
        };
    }

    private function lineUnit(
        string $type,
        array $format
    ): float {
        $style =
            $this
                ->style(
                    $type,
                    $format
                );

        return max(
            0.7,
            $style[
                'leading'
            ]
            /
            16
        );
    }

    private function wrap(
        string $text,
        int $width
    ): array {
        $text =
            preg_replace(
                '/[ \t]+/u',
                ' ',
                trim(
                    $text
                )
            )
            ??
            trim(
                $text
            );

        $paragraphLines =
            preg_split(
                '/\R/u',
                $text
            )
            ?:
            [];

        $result = [];

        foreach (
            $paragraphLines as
            $paragraph
        ) {
            $paragraph =
                trim(
                    $paragraph
                );

            if (
                $paragraph === ''
            ) {
                continue;
            }

            $wrapped =
                explode(
                    "\n",
                    wordwrap(
                        $paragraph,
                        $width,
                        "\n",
                        true
                    )
                );

            foreach (
                $wrapped as
                $line
            ) {
                $result[] =
                    $line;
            }
        }

        return $result !== []
            ? $result
            : [''];
    }

    private function encodePdfText(
        string $text
    ): string {
        $encoded =
            iconv(
                'UTF-8',
                'Windows-1252//TRANSLIT//IGNORE',
                $text
            );

        if (
            $encoded === false
        ) {
            $encoded =
                preg_replace(
                    '/[^\x20-\x7E]/',
                    '?',
                    $text
                )
                ??
                $text;
        }

        return str_replace(
            [
                '\\',
                '(',
                ')',
            ],
            [
                '\\\\',
                '\\(',
                '\\)',
            ],
            $encoded
        );
    }

    private function provenanceLines(
        array $provenance
    ): array {
        $lines = [];

        $fields = [
            'ai_run_uuid' =>
                'AI Run',

            'research_tool' =>
                'Research Tool',

            'prompt_version' =>
                'Prompt Version',

            'provider' =>
                'Provider',

            'model' =>
                'Model',

            'document_template_code' =>
                'Template',

            'document_template_version' =>
                'Template Version',

            'source_count' =>
                'Sources',

            'evidence_count' =>
                'Evidence',

            'glossary_term_count' =>
                'Glossary Terms',
        ];

        foreach (
            $fields as
            $key =>
            $label
        ) {
            $value =
                $provenance[
                    $key
                ]
                ??
                null;

            if (
                $value === null
                ||
                $value === ''
            ) {
                continue;
            }

            $lines[] =
                $label
                .': '
                .$value;
        }

        $citations =
            $provenance[
                'citation_keys'
            ]
            ??
            [];

        if (
            is_array(
                $citations
            )
            &&
            $citations !== []
        ) {
            $lines[] =
                'Validated Citations: '
                .implode(
                    ', ',
                    $citations
                );
        }

        return $lines;
    }

    private function format(
        array $content
    ): array {
        $format =
            is_array(
                $content[
                    'format'
                ]
                ??
                null
            )
                ? $content[
                    'format'
                ]
                : [];

        return [
            'title_font_size' =>
                $this->number(
                    $format[
                        'title_font_size'
                    ]
                    ??
                    null,
                    18,
                    12,
                    28
                ),

            'heading_font_size' =>
                $this->number(
                    $format[
                        'heading_font_size'
                    ]
                    ??
                    null,
                    12,
                    9,
                    20
                ),

            'body_font_size' =>
                $this->number(
                    $format[
                        'body_font_size'
                    ]
                    ??
                    null,
                    12,
                    9,
                    16
                ),

            'reference_font_size' =>
                $this->number(
                    $format[
                        'reference_font_size'
                    ]
                    ??
                    null,
                    11,
                    8,
                    14
                ),

            'provenance_font_size' =>
                $this->number(
                    $format[
                        'provenance_font_size'
                    ]
                    ??
                    null,
                    9,
                    7,
                    12
                ),

            'line_spacing' =>
                $this->number(
                    $format[
                        'line_spacing'
                    ]
                    ??
                    null,
                    1.5,
                    1,
                    2
                ),

            'first_line_indent_cm' =>
                $this->number(
                    $format[
                        'first_line_indent_cm'
                    ]
                    ??
                    null,
                    0.5,
                    0,
                    2
                ),

            'title_uppercase' =>
                (bool) (
                    $format[
                        'title_uppercase'
                    ]
                    ??
                    true
                ),

            'heading_uppercase' =>
                (bool) (
                    $format[
                        'heading_uppercase'
                    ]
                    ??
                    true
                ),
        ];
    }

    private function number(
        mixed $value,
        float $default,
        float $minimum,
        float $maximum
    ): float|int {
        if (
            ! is_numeric(
                $value
            )
        ) {
            return $default;
        }

        $number =
            max(
                $minimum,
                min(
                    $maximum,
                    (float)
                        $value
                )
            );

        return floor(
            $number
        )
        ===
        $number
            ? (int)
                $number
            : $number;
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