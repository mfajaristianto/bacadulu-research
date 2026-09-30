<?php

namespace App\Services\Documents;

use RuntimeException;

class PdfRenderer
{
    public function render(
        array $content,
        string $absolutePath
    ): void {
        $blocks =
            $this->documentBlocks(
                $content
            );

        $pages =
            $this->paginate(
                $blocks
            );

        if ($pages === []) {
            $pages = [
                [
                    [
                        'text'
                            => 'EMPTY DOCUMENT',
                        'type'
                            => 'body',
                    ],
                ],
            ];
        }

        $objects = [];

        $pageObjectIds = [];

        $nextId = 5;

        foreach (
            $pages
            as $pageBlocks
        ) {
            $pageId =
                $nextId++;

            $contentId =
                $nextId++;

            $pageObjectIds[] =
                $pageId;

            $stream =
                $this->pageStream(
                    $pageBlocks
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

        $objects[1] =
            '<< /Type /Catalog '
            .'/Pages 2 0 R >>';

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

        $objects[2] =
            '<< /Type /Pages '
            .'/Count '
            .count(
                $pageObjectIds
            )
            .' /Kids ['
            .$kids
            .'] >>';

        $objects[3] =
            '<< /Type /Font '
            .'/Subtype /Type1 '
            .'/BaseFont /Helvetica >>';

        $objects[4] =
            '<< /Type /Font '
            .'/Subtype /Type1 '
            .'/BaseFont /Helvetica-Bold >>';

        ksort(
            $objects
        );

        $pdf =
            "%PDF-1.4\n";

        $offsets = [
            0 => 0,
        ];

        foreach (
            $objects
            as $id => $object
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
            .($maxId + 1)
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
                    ] ?? 0
                );
        }

        $pdf .=
            'trailer << /Size '
            .($maxId + 1)
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
            ) === false
        ) {
            throw new RuntimeException(
                'PDF gagal dibuat.'
            );
        }

        if (
            ! is_file(
                $absolutePath
            )
            || filesize(
                $absolutePath
            ) <= 0
        ) {
            throw new RuntimeException(
                'PDF output tidak valid.'
            );
        }
    }

    private function documentBlocks(
        array $content
    ): array {
        $blocks = [];

        $blocks[] = [
            'type'
                => 'title',

            'text'
                => $this->upper(
                    (string) (
                        $content['title']
                        ?? 'RESEARCH DOCUMENT'
                    )
                ),
        ];

        $blocks[] = [
            'type'
                => 'meta',

            'text'
                => 'Generated with BacaDulu Research',
        ];

        foreach (
            $content['sections']
            ?? []
            as $section
        ) {
            $heading = trim(
                (string) (
                    $section['heading']
                    ?? ''
                )
            );

            if ($heading !== '') {
                $blocks[] = [
                    'type'
                        => 'heading',

                    'text'
                        => $this->upper(
                            $heading
                        ),
                ];
            }

            $paragraphs =
                preg_split(
                    '/\n{2,}/u',
                    (string) (
                        $section['body']
                        ?? ''
                    )
                ) ?: [];

            foreach (
                $paragraphs
                as $paragraph
            ) {
                $paragraph =
                    trim(
                        $paragraph
                    );

                if ($paragraph === '') {
                    continue;
                }

                $blocks[] = [
                    'type'
                        => 'body',

                    'text'
                        => $paragraph,
                ];
            }
        }

        $references =
            $content[
                'references'
            ] ?? [];

        if ($references !== []) {
            $blocks[] = [
                'type'
                    => 'heading',

                'text'
                    => 'DAFTAR PUSTAKA',
            ];

            foreach (
                $references
                as $reference
            ) {
                $blocks[] = [
                    'type'
                        => 'reference',

                    'text'
                        => (string)
                            $reference,
                ];
            }
        }

        $provenance =
            $content[
                'provenance'
            ] ?? [];

        if ($provenance !== []) {
            $blocks[] = [
                'type'
                    => 'heading',

                'text'
                    => 'DOCUMENT PROVENANCE',
            ];

            foreach (
                $this
                    ->provenanceLines(
                        $provenance
                    )
                as $line
            ) {
                $blocks[] = [
                    'type'
                        => 'meta',

                    'text'
                        => $line,
                ];
            }
        }

        return $blocks;
    }

    private function paginate(
        array $blocks
    ): array {
        $pages = [];

        $page = [];

        $usedLines = 0;

        $maximumLines = 48;

        foreach (
            $blocks
            as $block
        ) {
            $wrapped =
                $this->wrappedBlock(
                    $block
                );

            $needed =
                count(
                    $wrapped
                );

            if (
                $page !== []
                &&
                $usedLines
                    + $needed
                > $maximumLines
            ) {
                $pages[] =
                    $page;

                $page = [];

                $usedLines = 0;
            }

            foreach (
                $wrapped
                as $line
            ) {
                $page[] =
                    $line;

                $usedLines++;
            }
        }

        if ($page !== []) {
            $pages[] =
                $page;
        }

        return $pages;
    }

    private function wrappedBlock(
        array $block
    ): array {
        $type =
            $block['type']
            ?? 'body';

        $text =
            trim(
                (string) (
                    $block['text']
                    ?? ''
                )
            );

        if ($text === '') {
            return [];
        }

        $width = match ($type) {
            'title'
                => 56,

            'heading'
                => 72,

            'reference'
                => 86,

            default
                => 88,
        };

        $lines =
            $this->wrap(
                $text,
                $width
            );

        return collect(
            $lines
        )
            ->map(
                fn (
                    string $line,
                    int $index
                ): array => [
                    'type'
                        => $type,

                    'text'
                        => $line,

                    'first'
                        => $index === 0,
                ]
            )
            ->values()
            ->all();
    }

    private function pageStream(
        array $blocks
    ): string {
        $commands = [
            'BT',
            '50 790 Td',
        ];

        $firstLine = true;

        foreach (
            $blocks
            as $block
        ) {
            $type =
                $block['type']
                ?? 'body';

            $line =
                $this->encodePdfText(
                    (string) (
                        $block['text']
                        ?? ''
                    )
                );

            $style = match ($type) {
                'title'
                    => [
                        'font'
                            => 'F2',
                        'size'
                            => 16,
                        'leading'
                            => 22,
                    ],

                'heading'
                    => [
                        'font'
                            => 'F2',
                        'size'
                            => 11,
                        'leading'
                            => 18,
                    ],

                'meta'
                    => [
                        'font'
                            => 'F1',
                        'size'
                            => 8,
                        'leading'
                            => 12,
                    ],

                'reference'
                    => [
                        'font'
                            => 'F1',
                        'size'
                            => 9,
                        'leading'
                            => 13,
                    ],

                default
                    => [
                        'font'
                            => 'F1',
                        'size'
                            => 11,
                        'leading'
                            => 16,
                    ],
            };

            if (! $firstLine) {
                $commands[] =
                    '0 -'
                    .$style[
                        'leading'
                    ]
                    .' Td';
            }

            $commands[] =
                '/'
                .$style['font']
                .' '
                .$style['size']
                .' Tf';

            $commands[] =
                '('
                .$line
                .') Tj';

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
            ?? trim(
                $text
            );

        $paragraphLines =
            preg_split(
                '/\R/u',
                $text
            ) ?: [];

        $result = [];

        foreach (
            $paragraphLines
            as $paragraph
        ) {
            $paragraph =
                trim(
                    $paragraph
                );

            if ($paragraph === '') {
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
                $wrapped
                as $line
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

        if ($encoded === false) {
            $encoded =
                preg_replace(
                    '/[^\x20-\x7E]/',
                    '?',
                    $text
                )
                ?? $text;
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
            'ai_run_uuid'
                => 'AI Run',

            'research_tool'
                => 'Research Tool',

            'prompt_version'
                => 'Prompt Version',

            'provider'
                => 'Provider',

            'model'
                => 'Model',

            'source_count'
                => 'Sources',

            'evidence_count'
                => 'Evidence',
        ];

        foreach (
            $fields
            as $key => $label
        ) {
            $value =
                $provenance[$key]
                ?? null;

            if (
                $value === null
                || $value === ''
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
            ] ?? [];

        if (
            is_array(
                $citations
            )
            && $citations !== []
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