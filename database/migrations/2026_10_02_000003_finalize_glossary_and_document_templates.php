<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Glossary
        |--------------------------------------------------------------------------
        */

        if (! Schema::hasTable('glossary_terms')) {
            Schema::create(
                'glossary_terms',
                function (
                    Blueprint $table
                ): void {
                    $table->id();

                    $table
                        ->foreignId(
                            'category_id'
                        )
                        ->nullable()
                        ->constrained(
                            'categories'
                        )
                        ->nullOnDelete();

                    $table->string(
                        'term',
                        180
                    );

                    $table
                        ->string(
                            'slug',
                            220
                        )
                        ->unique();

                    $table->text(
                        'definition'
                    );

                    $table
                        ->json(
                            'aliases_json'
                        )
                        ->nullable();

                    $table
                        ->text(
                            'context_note'
                        )
                        ->nullable();

                    $table
                        ->string(
                            'status',
                            30
                        )
                        ->default(
                            'active'
                        )
                        ->index();

                    $table
                        ->foreignId(
                            'created_by'
                        )
                        ->nullable()
                        ->constrained(
                            'users'
                        )
                        ->nullOnDelete();

                    $table
                        ->foreignId(
                            'updated_by'
                        )
                        ->nullable()
                        ->constrained(
                            'users'
                        )
                        ->nullOnDelete();

                    $table->timestamps();

                    $table->index([
                        'category_id',
                        'status',
                    ]);

                    $table->index([
                        'term',
                        'status',
                    ]);
                }
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Document Template Versioning
        |--------------------------------------------------------------------------
        */

        if (
            Schema::hasTable(
                'document_templates'
            )
        ) {
            Schema::table(
                'document_templates',
                function (
                    Blueprint $table
                ): void {
                    if (
                        ! Schema::hasColumn(
                            'document_templates',
                            'version'
                        )
                    ) {
                        $table
                            ->unsignedInteger(
                                'version'
                            )
                            ->default(
                                1
                            );
                    }

                    if (
                        ! Schema::hasColumn(
                            'document_templates',
                            'is_default'
                        )
                    ) {
                        $table
                            ->boolean(
                                'is_default'
                            )
                            ->default(
                                false
                            )
                            ->index();
                    }

                    if (
                        ! Schema::hasColumn(
                            'document_templates',
                            'updated_by'
                        )
                    ) {
                        $table
                            ->foreignId(
                                'updated_by'
                            )
                            ->nullable()
                            ->constrained(
                                'users'
                            )
                            ->nullOnDelete();
                    }
                }
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Generation Provenance Snapshots
        |--------------------------------------------------------------------------
        */

        if (
            Schema::hasTable(
                'document_generations'
            )
        ) {
            Schema::table(
                'document_generations',
                function (
                    Blueprint $table
                ): void {
                    if (
                        ! Schema::hasColumn(
                            'document_generations',
                            'template_version'
                        )
                    ) {
                        $table
                            ->unsignedInteger(
                                'template_version'
                            )
                            ->nullable();
                    }

                    if (
                        ! Schema::hasColumn(
                            'document_generations',
                            'template_snapshot_json'
                        )
                    ) {
                        $table
                            ->json(
                                'template_snapshot_json'
                            )
                            ->nullable();
                    }

                    if (
                        ! Schema::hasColumn(
                            'document_generations',
                            'settings_snapshot_json'
                        )
                    ) {
                        $table
                            ->json(
                                'settings_snapshot_json'
                            )
                            ->nullable();
                    }

                    if (
                        ! Schema::hasColumn(
                            'document_generations',
                            'glossary_snapshot_json'
                        )
                    ) {
                        $table
                            ->json(
                                'glossary_snapshot_json'
                            )
                            ->nullable();
                    }
                }
            );
        }

        $this->seedCanonicalTemplates();
    }

    public function down(): void
    {
        if (
            Schema::hasTable(
                'document_generations'
            )
        ) {
            $drop =
                collect([
                    'template_version',
                    'template_snapshot_json',
                    'settings_snapshot_json',
                    'glossary_snapshot_json',
                ])
                    ->filter(
                        fn (
                            string $column
                        ): bool =>
                            Schema::hasColumn(
                                'document_generations',
                                $column
                            )
                    )
                    ->values()
                    ->all();

            if ($drop !== []) {
                Schema::table(
                    'document_generations',
                    function (
                        Blueprint $table
                    ) use (
                        $drop
                    ): void {
                        $table->dropColumn(
                            $drop
                        );
                    }
                );
            }
        }

        if (
            Schema::hasTable(
                'document_templates'
            )
        ) {
            Schema::table(
                'document_templates',
                function (
                    Blueprint $table
                ): void {
                    if (
                        Schema::hasColumn(
                            'document_templates',
                            'updated_by'
                        )
                    ) {
                        $table
                            ->dropConstrainedForeignId(
                                'updated_by'
                            );
                    }

                    if (
                        Schema::hasColumn(
                            'document_templates',
                            'is_default'
                        )
                    ) {
                        $table->dropColumn(
                            'is_default'
                        );
                    }

                    if (
                        Schema::hasColumn(
                            'document_templates',
                            'version'
                        )
                    ) {
                        $table->dropColumn(
                            'version'
                        );
                    }
                }
            );
        }

        Schema::dropIfExists(
            'glossary_terms'
        );
    }

    private function seedCanonicalTemplates(): void
    {
        if (
            ! Schema::hasTable(
                'document_templates'
            )
        ) {
            return;
        }

        foreach (
            $this->canonicalTemplates()
            as
            $template
        ) {
            $code =
                $template[
                    'code'
                ];

            $schema =
                $template[
                    'schema_json'
                ];

            unset(
                $template['code'],
                $template['schema_json']
            );

            $values =
                $template
                +
                [
                    'schema_json' =>
                        json_encode(
                            $schema,
                            JSON_UNESCAPED_UNICODE
                            |
                            JSON_UNESCAPED_SLASHES
                            |
                            JSON_THROW_ON_ERROR
                        ),

                    'updated_at' =>
                        now(),
                ];

            $existing =
                DB::table(
                    'document_templates'
                )
                    ->where(
                        'code',
                        $code
                    )
                    ->exists();

            if ($existing) {
                DB::table(
                    'document_templates'
                )
                    ->where(
                        'code',
                        $code
                    )
                    ->update(
                        $values
                    );
            } else {
                DB::table(
                    'document_templates'
                )
                    ->insert(
                        [
                            'code' =>
                                $code,

                            'created_at' =>
                                now(),
                        ]
                        +
                        $values
                    );
            }
        }
    }

    private function canonicalTemplates(): array
    {
        /*
        |--------------------------------------------------------------------------
        | Default Academic Format
        |--------------------------------------------------------------------------
        |
        | Judul       18 pt
        | Heading     12 pt
        | Body        12 pt
        | References  11 pt
        | Provenance   9 pt
        | Line spacing 1.5
        | First indent 0.5 cm
        |
        */

        $format = [
            'title_font_size' =>
                18,

            'heading_font_size' =>
                12,

            'body_font_size' =>
                12,

            'reference_font_size' =>
                11,

            'provenance_font_size' =>
                9,

            'figure_font_size' =>
                10,

            'line_spacing' =>
                1.5,

            'first_line_indent_cm' =>
                0.5,

            'title_uppercase' =>
                true,

            'heading_uppercase' =>
                true,
        ];

        $templates = [

            'Research Article' => [
                [
                    'heading' =>
                        'A. PENDAHULUAN',

                    'role' =>
                        'draft',
                ],

                [
                    'heading' =>
                        'B. LATAR BELAKANG',

                    'role' =>
                        'draft',
                ],

                [
                    'heading' =>
                        'C. TINJAUAN PUSTAKA',

                    'role' =>
                        'evidence',
                ],

                [
                    'heading' =>
                        'D. METODE PENELITIAN',

                    'role' =>
                        'method_note',
                ],

                [
                    'heading' =>
                        'E. HASIL DAN PEMBAHASAN',

                    'role' =>
                        'research_result',
                ],

                [
                    'heading' =>
                        'KESIMPULAN',

                    'role' =>
                        'conclusion',
                ],
            ],

            'Literature Review' => [
                [
                    'heading' =>
                        'A. PENDAHULUAN',

                    'role' =>
                        'draft',
                ],

                [
                    'heading' =>
                        'B. RUANG LINGKUP LITERATUR',

                    'role' =>
                        'evidence',
                ],

                [
                    'heading' =>
                        'C. SINTESIS TEMUAN',

                    'role' =>
                        'research_result',
                ],

                [
                    'heading' =>
                        'D. EVIDENCE MAP',

                    'role' =>
                        'evidence',
                ],

                [
                    'heading' =>
                        'E. IMPLIKASI DAN RESEARCH GAP',

                    'role' =>
                        'research_result',
                ],

                [
                    'heading' =>
                        'KESIMPULAN',

                    'role' =>
                        'conclusion',
                ],
            ],

            'Research Proposal' => [
                [
                    'heading' =>
                        'A. PENDAHULUAN',

                    'role' =>
                        'draft',
                ],

                [
                    'heading' =>
                        'B. LATAR BELAKANG',

                    'role' =>
                        'research_result',
                ],

                [
                    'heading' =>
                        'C. RUMUSAN MASALAH',

                    'role' =>
                        'draft',
                ],

                [
                    'heading' =>
                        'D. TINJAUAN PUSTAKA',

                    'role' =>
                        'evidence',
                ],

                [
                    'heading' =>
                        'E. KERANGKA KONSEPTUAL',

                    'role' =>
                        'draft',
                ],

                [
                    'heading' =>
                        'F. METODE PENELITIAN',

                    'role' =>
                        'method_note',
                ],
            ],

            'Research Background' => [
                [
                    'heading' =>
                        'A. LATAR BELAKANG',

                    'role' =>
                        'research_result',
                ],

                [
                    'heading' =>
                        'B. EVIDENCE UTAMA',

                    'role' =>
                        'evidence',
                ],

                [
                    'heading' =>
                        'C. IMPLIKASI PENELITIAN',

                    'role' =>
                        'conclusion',
                ],
            ],

            'Research Gap' => [
                [
                    'heading' =>
                        'A. ANALISIS RESEARCH GAP',

                    'role' =>
                        'research_result',
                ],

                [
                    'heading' =>
                        'B. SUPPORTING EVIDENCE',

                    'role' =>
                        'evidence',
                ],

                [
                    'heading' =>
                        'C. CATATAN INTERPRETASI',

                    'role' =>
                        'conclusion',
                ],
            ],

            'Theory Review' => [
                [
                    'heading' =>
                        'A. THEORETICAL CONTEXT',

                    'role' =>
                        'research_result',
                ],

                [
                    'heading' =>
                        'B. SUPPORTING EVIDENCE',

                    'role' =>
                        'evidence',
                ],

                [
                    'heading' =>
                        'C. IMPLIKASI TEORETIS',

                    'role' =>
                        'conclusion',
                ],
            ],

            'Variable Definition' => [
                [
                    'heading' =>
                        'A. IDENTIFIKASI VARIABEL',

                    'role' =>
                        'research_result',
                ],

                [
                    'heading' =>
                        'B. SUPPORTING EVIDENCE',

                    'role' =>
                        'evidence',
                ],

                [
                    'heading' =>
                        'C. CATATAN OPERASIONALISASI',

                    'role' =>
                        'conclusion',
                ],
            ],

            'Hypothesis Development' => [
                [
                    'heading' =>
                        'A. DASAR TEORETIS DAN EMPIRIS',

                    'role' =>
                        'research_result',
                ],

                [
                    'heading' =>
                        'B. SUPPORTING EVIDENCE',

                    'role' =>
                        'evidence',
                ],

                [
                    'heading' =>
                        'C. PENGEMBANGAN HIPOTESIS',

                    'role' =>
                        'draft',
                ],
            ],

            'Conference Paper' => [
                [
                    'heading' =>
                        'A. PENDAHULUAN',

                    'role' =>
                        'draft',
                ],

                [
                    'heading' =>
                        'B. LITERATURE REVIEW',

                    'role' =>
                        'evidence',
                ],

                [
                    'heading' =>
                        'C. METHOD',

                    'role' =>
                        'method_note',
                ],

                [
                    'heading' =>
                        'D. RESULTS AND DISCUSSION',

                    'role' =>
                        'research_result',
                ],

                [
                    'heading' =>
                        'KESIMPULAN',

                    'role' =>
                        'conclusion',
                ],
            ],

            'Book Chapter' => [
                [
                    'heading' =>
                        'A. INTRODUCTION',

                    'role' =>
                        'draft',
                ],

                [
                    'heading' =>
                        'B. RESEARCH EVIDENCE',

                    'role' =>
                        'evidence',
                ],

                [
                    'heading' =>
                        'C. SYNTHESIS AND DISCUSSION',

                    'role' =>
                        'research_result',
                ],

                [
                    'heading' =>
                        'KESIMPULAN',

                    'role' =>
                        'conclusion',
                ],
            ],

            'Research Report' => [
                [
                    'heading' =>
                        'A. EXECUTIVE SUMMARY',

                    'role' =>
                        'conclusion',
                ],

                [
                    'heading' =>
                        'B. RESEARCH EVIDENCE',

                    'role' =>
                        'evidence',
                ],

                [
                    'heading' =>
                        'C. ANALYSIS',

                    'role' =>
                        'research_result',
                ],

                [
                    'heading' =>
                        'D. NEXT RESEARCH ACTION',

                    'role' =>
                        'conclusion',
                ],
            ],
        ];

        return collect(
            $templates
        )
            ->map(
                function (
                    array $sections,
                    string $type
                ) use (
                    $format
                ): array {
                    return [
                        'code' =>
                            'default-'
                            .Str::slug(
                                $type
                            ),

                        'name' =>
                            'Default '
                            .$type,

                        'document_type' =>
                            $type,

                        'description' =>
                            'Template bawaan BacaDulu Research untuk '
                            .$type
                            .'.',

                        'schema_json' => [
                            'sections' =>
                                $sections,

                            'format' =>
                                $format,
                        ],

                        'version' =>
                            1,

                        'is_default' =>
                            true,

                        'status' =>
                            'active',

                        'updated_by' =>
                            null,
                    ];
                }
            )
            ->values()
            ->all();
    }
};