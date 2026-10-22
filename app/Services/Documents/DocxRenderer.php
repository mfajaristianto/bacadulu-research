<?php

namespace App\Services\Documents;

use Phar;
use PharData;
use RuntimeException;

class DocxRenderer
{
    public function render(
        array $content,
        string $absolutePath
    ): void {
        @unlink(
            $absolutePath
        );

        try {
            $archive =
                new PharData(
                    $absolutePath,
                    0,
                    null,
                    Phar::ZIP
                );

            $archive[
                '[Content_Types].xml'
            ] =
                $this
                    ->contentTypes();

            $archive[
                '_rels/.rels'
            ] =
                $this
                    ->rootRelationships();

            $archive[
                'word/document.xml'
            ] =
                $this
                    ->documentXml(
                        $content
                    );

            $archive[
                'word/styles.xml'
            ] =
                $this
                    ->stylesXml(
                        $content
                    );

            $archive[
                'word/_rels/document.xml.rels'
            ] =
                $this
                    ->documentRelationships();

            unset(
                $archive
            );
        } catch (
            \Throwable $exception
        ) {
            @unlink(
                $absolutePath
            );

            throw new RuntimeException(
                'DOCX renderer gagal: '
                .$exception
                    ->getMessage(),
                previous:
                    $exception
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
                'DOCX gagal dibuat.'
            );
        }
    }

    private function documentXml(
        array $content
    ): string {
        $body = [];

        $format =
            $this
                ->format(
                    $content
                );

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

        $body[] =
            $this->paragraph(
                $title,
                'Title'
            );

        $body[] =
            $this->paragraph(
                (string) ($content['document_type'] ?? 'Research Document'),
                'DocumentMeta'
            );

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

                $body[] =
                    $this->paragraph(
                        PrintableText::clean($heading),
                        'Heading1'
                    );
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

                $body[] =
                    $this->paragraph(
                        PrintableText::clean($paragraph),
                        'BodyText'
                    );
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
            $body[] =
                $this->paragraph(
                    'DAFTAR PUSTAKA',
                    'Heading1'
                );

            foreach (
                $references as
                $reference
            ) {
                $body[] =
                    $this->paragraph(
                        (string)
                            $reference,
                        'Reference'
                    );
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
            $body[] =
                $this->paragraph(
                    'DOCUMENT PROVENANCE',
                    'Heading1'
                );

            foreach (
                $this
                    ->provenanceLines(
                        $provenance
                    ) as
                $line
            ) {
                $body[] =
                    $this->paragraph(
                        $line,
                        'Provenance'
                    );
            }
        }

        $body[] =
            '<w:sectPr>'
            .'<w:pgSz w:w="11906" w:h="16838"/>'
            .'<w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440"/>'
            .'</w:sectPr>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .'<w:body>'
            .implode(
                '',
                $body
            )
            .'</w:body>'
            .'</w:document>';
    }

    private function paragraph(
        string $text,
        string $style
    ): string {
        $escaped =
            htmlspecialchars(
                $text,
                ENT_XML1
                |
                ENT_QUOTES,
                'UTF-8'
            );

        return '<w:p>'
            .'<w:pPr>'
            .'<w:pStyle w:val="'
            .$style
            .'"/>'
            .'</w:pPr>'
            .'<w:r>'
            .'<w:t xml:space="preserve">'
            .$escaped
            .'</w:t>'
            .'</w:r>'
            .'</w:p>';
    }

    private function stylesXml(
        array $content
    ): string {
        $format =
            $this
                ->format(
                    $content
                );

        $titleSize =
            $this
                ->halfPoints(
                    $format[
                        'title_font_size'
                    ]
                );

        $headingSize =
            $this
                ->halfPoints(
                    $format[
                        'heading_font_size'
                    ]
                );

        $bodySize =
            $this
                ->halfPoints(
                    $format[
                        'body_font_size'
                    ]
                );

        $referenceSize =
            $this
                ->halfPoints(
                    $format[
                        'reference_font_size'
                    ]
                );

        $provenanceSize =
            $this
                ->halfPoints(
                    $format[
                        'provenance_font_size'
                    ]
                );

        /*
         * Word:
         *
         * single = 240
         * 1.5    = 360
         */

        $bodyLine =
            max(
                240,
                (int)
                    round(
                        240
                        *
                        $format[
                            'line_spacing'
                        ]
                    )
            );

        /*
         * 1 cm ≈ 567 twips.
         */

        $firstLine =
            max(
                0,
                (int)
                    round(
                        567
                        *
                        $format[
                            'first_line_indent_cm'
                        ]
                    )
            );

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'

            .'<w:style w:type="paragraph" w:default="1" w:styleId="Normal">'
            .'<w:name w:val="Normal"/>'
            .'<w:rPr>'
            .'<w:sz w:val="'
            .$bodySize
            .'"/>'
            .'</w:rPr>'
            .'</w:style>'

            .'<w:style w:type="paragraph" w:styleId="Title">'
            .'<w:name w:val="Title"/>'
            .'<w:pPr>'
            .'<w:jc w:val="center"/>'
            .'<w:spacing w:after="480"/>'
            .'</w:pPr>'
            .'<w:rPr>'
            .'<w:b/>'
            .'<w:sz w:val="'
            .$titleSize
            .'"/>'
            .'</w:rPr>'
            .'</w:style>'

            .'<w:style w:type="paragraph" w:styleId="DocumentMeta">'
            .'<w:name w:val="Document Meta"/>'
            .'<w:pPr>'
            .'<w:jc w:val="center"/>'
            .'<w:spacing w:after="360"/>'
            .'</w:pPr>'
            .'<w:rPr>'
            .'<w:color w:val="666666"/>'
            .'<w:sz w:val="'
            .$provenanceSize
            .'"/>'
            .'</w:rPr>'
            .'</w:style>'

            .'<w:style w:type="paragraph" w:styleId="Heading1">'
            .'<w:name w:val="Heading 1"/>'
            .'<w:pPr>'
            .'<w:spacing w:before="360" w:after="180"/>'
            .'</w:pPr>'
            .'<w:rPr>'
            .'<w:b/>'
            .'<w:sz w:val="'
            .$headingSize
            .'"/>'
            .'</w:rPr>'
            .'</w:style>'

            .'<w:style w:type="paragraph" w:styleId="BodyText">'
            .'<w:name w:val="Body Text"/>'
            .'<w:pPr>'
            .'<w:jc w:val="both"/>'
            .'<w:spacing w:line="'
            .$bodyLine
            .'" w:lineRule="auto" w:after="120"/>'
            .'<w:ind w:firstLine="'
            .$firstLine
            .'"/>'
            .'</w:pPr>'
            .'<w:rPr>'
            .'<w:sz w:val="'
            .$bodySize
            .'"/>'
            .'</w:rPr>'
            .'</w:style>'

            .'<w:style w:type="paragraph" w:styleId="Reference">'
            .'<w:name w:val="Reference"/>'
            .'<w:pPr>'
            .'<w:spacing w:line="330" w:lineRule="auto" w:after="80"/>'
            .'<w:ind w:left="283" w:hanging="283"/>'
            .'</w:pPr>'
            .'<w:rPr>'
            .'<w:sz w:val="'
            .$referenceSize
            .'"/>'
            .'</w:rPr>'
            .'</w:style>'

            .'<w:style w:type="paragraph" w:styleId="Provenance">'
            .'<w:name w:val="Provenance"/>'
            .'<w:pPr>'
            .'<w:spacing w:after="60"/>'
            .'</w:pPr>'
            .'<w:rPr>'
            .'<w:color w:val="666666"/>'
            .'<w:sz w:val="'
            .$provenanceSize
            .'"/>'
            .'</w:rPr>'
            .'</w:style>'

            .'</w:styles>';
    }

    private function provenanceLines(
        array $provenance
    ): array {
        $lines = [];

        $map = [
            'conversation_id' =>
                'Conversation ID',

            'ai_run_id' =>
                'AI Run ID',

            'ai_run_uuid' =>
                'AI Run UUID',

            'research_tool' =>
                'Research Tool',

            'prompt' =>
                'Prompt',

            'prompt_version' =>
                'Prompt Version',

            'provider' =>
                'AI Provider',

            'model' =>
                'AI Model',

            'document_template_code' =>
                'Document Template',

            'document_template_version' =>
                'Template Version',

            'source_count' =>
                'Source Count',

            'evidence_count' =>
                'Evidence Count',

            'glossary_term_count' =>
                'Glossary Terms',

            'generated_at' =>
                'Generated At',
        ];

        foreach (
            $map as
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

    private function halfPoints(
        float|int $points
    ): int {
        return max(
            14,
            (int)
                round(
                    (
                        (float)
                            $points
                    )
                    *
                    2
                )
        );
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            .'<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
            .'</Types>';
    }

    private function rootRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            .'</Relationships>';
    }

    private function documentRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
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