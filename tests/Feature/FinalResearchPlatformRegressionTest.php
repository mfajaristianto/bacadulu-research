<?php

namespace Tests\Feature;

use App\Models\AiRun;
use App\Models\AiRunResult;
use App\Models\Category;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\DocumentGeneration;
use App\Models\DocumentTemplate;
use App\Models\GlossaryTerm;
use App\Models\User;
use App\Services\Documents\AcademicDocumentBuilder;
use App\Services\Research\CitationValidator;
use App\Services\Research\GlossaryContextService;
use App\Services\Research\ResearchSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class FinalResearchPlatformRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set(
            'research.profile_gate',
            0
        );

        config()->set(
            'research.require_email_verification',
            false
        );
    }

    /*
    |--------------------------------------------------------------------------
    | User Library Retirement
    |--------------------------------------------------------------------------
    */

    public function test_legacy_user_library_only_redirects_to_workspace_and_never_exposes_raw_source(): void
    {
        $user =
            User::factory()
                ->create();

        $document =
            $this->document(
                'published',
                $user
            );

        $this->actingAs(
            $user
        )
            ->get(
                route(
                    'library.index'
                )
            )
            ->assertRedirect(
                route(
                    'workspace'
                )
            );

        $this->actingAs(
            $user
        )
            ->get(
                route(
                    'library.show',
                    $document
                )
            )
            ->assertRedirect(
                route(
                    'workspace'
                )
            );

        /*
         * Login user biasa bukan Admin session.
         */

        $this->actingAs(
            $user
        )
            ->get(
                route(
                    'admin.library.index'
                )
            )
            ->assertRedirect(
                route(
                    'admin.login'
                )
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Retrieval Eligibility
    |--------------------------------------------------------------------------
    */

    public function test_only_published_public_source_with_chunks_is_eligible_for_research(): void
    {
        $user =
            User::factory()
                ->create();

        $processing =
            $this->document(
                'processing',
                $user
            );

        $ready =
            $this->document(
                'ready',
                $user
            );

        $published =
            $this->document(
                'published',
                $user
            );

        foreach (
            [
                $processing,
                $ready,
                $published,
            ] as
            $document
        ) {
            DocumentChunk::create([
                'document_id' =>
                    $document->id,

                'chunk_index' =>
                    0,

                'content' =>
                    'ESG disclosure firm value evidence for final regression test.',

                'metadata_json' => [
                    'test' =>
                        true,
                ],
            ]);
        }

        $eligibleIds =
            Document::query()
                ->eligibleForResearch()
                ->pluck(
                    'id'
                )
                ->map(
                    fn (
                        $id
                    ) =>
                        (int)
                            $id
                )
                ->all();

        $this->assertNotContains(
            $processing->id,
            $eligibleIds
        );

        $this->assertNotContains(
            $ready->id,
            $eligibleIds
        );

        $this->assertContains(
            $published->id,
            $eligibleIds
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Runtime Settings + Secret Guard
    |--------------------------------------------------------------------------
    */

    public function test_runtime_settings_override_config_but_unknown_secret_keys_are_not_persisted(): void
    {
        $admin =
            User::factory()
                ->create();

        $service =
            app(
                ResearchSettingsService::class
            );

        $service->persist(
            [
                'retrieval.minimum_evidence' =>
                    3,

                'queue.live_refresh_seconds' =>
                    4,

                /*
                 * Tidak ada di whitelist.
                 */
                'openai.api_key' =>
                    'SHOULD-NOT-BE-STORED',

                'source_sync.token' =>
                    'SHOULD-NOT-BE-STORED',
            ],
            $admin->id
        );

        $this->assertDatabaseHas(
            'research_settings',
            [
                'key' =>
                    'retrieval.minimum_evidence',

                'value' =>
                    '3',
            ]
        );

        $this->assertDatabaseMissing(
            'research_settings',
            [
                'key' =>
                    'openai.api_key',
            ]
        );

        $this->assertDatabaseMissing(
            'research_settings',
            [
                'key' =>
                    'source_sync.token',
            ]
        );

        /*
         * Simulasi config base berubah.
         * DB override harus diterapkan kembali.
         */

        config()->set(
            'research.retrieval.minimum_evidence',
            2
        );

        $service
            ->applyOverrides();

        $this->assertSame(
            3,
            (int)
                config(
                    'research.retrieval.minimum_evidence'
                )
        );

        $this->assertSame(
            4,
            (int)
                config(
                    'research.queue.live_refresh_seconds'
                )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Glossary ≠ Evidence
    |--------------------------------------------------------------------------
    */

    public function test_glossary_is_context_only_and_cannot_be_validated_as_academic_citation(): void
    {
        $user =
            User::factory()
                ->create();

        $term =
            GlossaryTerm::create([
                'term' =>
                    'ESG',

                'slug' =>
                    'esg',

                'definition' =>
                    'Environmental, Social, and Governance.',

                'aliases_json' => [
                    'ESG disclosure',
                ],

                'context_note' =>
                    'Bedakan ESG disclosure dari ESG performance.',

                'status' =>
                    'active',

                'created_by' =>
                    $user->id,

                'updated_by' =>
                    $user->id,
            ]);

        $context =
            app(
                GlossaryContextService::class
            )
                ->resolve(
                    'Bagaimana ESG disclosure memengaruhi firm value?'
                );

        $this->assertCount(
            1,
            $context
        );

        $this->assertSame(
            $term->id,
            $context[
                0
            ][
                'id'
            ]
        );

        $this->assertArrayNotHasKey(
            'citation_key',
            $context[
                0
            ]
        );

        $this->assertArrayNotHasKey(
            'document_id',
            $context[
                0
            ]
        );

        /*
         * Provider tidak boleh mengubah glossary
         * menjadi citation valid.
         */

        $run =
            AiRun::create([
                'uuid' =>
                    (string)
                        Str::uuid(),

                'user_id' =>
                    $user->id,

                'status' =>
                    'completed',

                'model_provider' =>
                    'mock',

                'model_name' =>
                    'final-regression',
            ]);

        $citations =
            app(
                CitationValidator::class
            )
                ->validateAndStore(
                    $run,
                    [
                        [
                            'citation_key' =>
                                'GLOSSARY-ESG',

                            'claim_label' =>
                                'Glossary must never be evidence',

                            'quote_snippet' =>
                                'Environmental, Social, and Governance.',
                        ],
                    ]
                );

        $this->assertCount(
            1,
            $citations
        );

        $this->assertFalse(
            $citations
                ->first()
                ->is_valid
        );

        $this->assertNull(
            $citations
                ->first()
                ->document_id
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Immutable Document Template Provenance
    |--------------------------------------------------------------------------
    */

    public function test_document_template_snapshot_stays_immutable_after_template_is_edited(): void
    {
        $user =
            User::factory()
                ->create();

        $run =
            $this->completedRun(
                $user
            );

        $template =
            DocumentTemplate::create([
                'code' =>
                    'final-regression-template',

                'name' =>
                    'Final Regression Template',

                'document_type' =>
                    'Research Report',

                'description' =>
                    'Template for final regression test.',

                'schema_json' => [
                    'sections' => [
                        [
                            'heading' =>
                                'A. ANALYSIS',

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

                    'format' => [
                        'body_font_size' =>
                            12,

                        'line_spacing' =>
                            1.5,
                    ],
                ],

                'version' =>
                    1,

                'is_default' =>
                    true,

                'status' =>
                    'active',

                'created_by' =>
                    $user->id,

                'updated_by' =>
                    $user->id,
            ]);

        $content =
            app(
                AcademicDocumentBuilder::class
            )
                ->build(
                    $run,
                    'Research Report',
                    'APA 7',
                    'FINAL REGRESSION DOCUMENT',
                    $template
                );

        $generation =
            DocumentGeneration::create([
                'uuid' =>
                    (string)
                        Str::uuid(),

                'user_id' =>
                    $user->id,

                'ai_run_id' =>
                    $run->id,

                'document_template_id' =>
                    $template->id,

                'template_version' =>
                    $template->version,

                'document_type' =>
                    'Research Report',

                'citation_style' =>
                    'APA 7',

                'title' =>
                    $content[
                        'title'
                    ],

                'content_json' =>
                    $content,

                'template_snapshot_json' =>
                    $content[
                        'template'
                    ],

                'settings_snapshot_json' => [
                    'format' =>
                        $content[
                            'format'
                        ],
                ],

                'glossary_snapshot_json' =>
                    $content[
                        'glossary_context'
                    ],

                'status' =>
                    'draft',
            ]);

        $originalHash =
            data_get(
                $generation
                    ->template_snapshot_json,
                'schema_hash'
            );

        /*
         * Template berubah SETELAH generation.
         */

        $template->update([
            'version' =>
                2,

            'schema_json' => [
                'sections' => [
                    [
                        'heading' =>
                            'A. CHANGED LATER',

                        'role' =>
                            'draft',
                    ],
                ],

                'format' => [
                    'body_font_size' =>
                        11,
                ],
            ],
        ]);

        $generation->refresh();

        /*
         * Historical generation tidak boleh berubah.
         */

        $this->assertSame(
            1,
            $generation
                ->template_version
        );

        $this->assertSame(
            1,
            (int)
                data_get(
                    $generation
                        ->template_snapshot_json,
                    'version'
                )
        );

        $this->assertSame(
            $originalHash,
            data_get(
                $generation
                    ->template_snapshot_json,
                'schema_hash'
            )
        );

        $this->assertSame(
            'A. ANALYSIS',
            data_get(
                $generation
                    ->content_json,
                'sections.0.heading'
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Document Generation Guard
    |--------------------------------------------------------------------------
    */

    public function test_document_builder_refuses_non_completed_ai_run(): void
    {
        $user =
            User::factory()
                ->create();

        $run =
            $this->completedRun(
                $user
            );

        $run->update([
            'status' =>
                'generating',
        ]);

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Hanya AI Run yang sudah completed'
        );

        app(
            AcademicDocumentBuilder::class
        )
            ->build(
                $run
                    ->fresh(),
                'Research Report',
                'APA 7'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Document IDOR
    |--------------------------------------------------------------------------
    */

    public function test_other_user_cannot_open_or_download_someone_elses_generated_document(): void
    {
        $owner =
            User::factory()
                ->create();

        $attacker =
            User::factory()
                ->create();

        $generation =
            DocumentGeneration::create([
                'uuid' =>
                    (string)
                        Str::uuid(),

                'user_id' =>
                    $owner->id,

                'document_type' =>
                    'Research Report',

                'citation_style' =>
                    'APA 7',

                'title' =>
                    'Private Research Output',

                'content_json' => [
                    'title' =>
                        'Private Research Output',

                    'sections' => [
                        [
                            'heading' =>
                                'A. RESULT',

                            'body' =>
                                'Private content.',
                        ],
                    ],
                ],

                'status' =>
                    'completed',

                'docx_disk' =>
                    'local',

                'docx_path' =>
                    'private-owner/document.docx',

                'pdf_disk' =>
                    'local',

                'pdf_path' =>
                    'private-owner/document.pdf',
            ]);

        $this->actingAs(
            $attacker
        )
            ->get(
                route(
                    'documents.show',
                    $generation
                )
            )
            ->assertNotFound();

        $this->actingAs(
            $attacker
        )
            ->get(
                route(
                    'documents.download',
                    [
                        $generation,
                        'pdf',
                    ]
                )
            )
            ->assertNotFound();
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function document(
        string $status,
        User $user
    ): Document {
        static $counter =
            0;

        $counter++;

        $category =
            Category::create([
                'name' =>
                    'Final Regression Category '
                    .$counter,

                'slug' =>
                    'final-regression-category-'
                    .$counter
                    .'-'
                    .Str::lower(
                        Str::random(
                            6
                        )
                    ),

                'status' =>
                    'active',
            ]);

        return Document::create([
            'uuid' =>
                (string)
                    Str::uuid(),

            'title' =>
                'Final Regression Source '
                .$counter,

            'category_id' =>
                $category->id,

            'document_type' =>
                'pdf',

            'publication_year' =>
                2026,

            'language' =>
                'en',

            'publisher' =>
                'BacaDulu Test',

            'source_label' =>
                'Curated Source',

            'status' =>
                $status,

            'visibility' =>
                'public',

            'uploaded_by' =>
                $user->id,

            'published_at' =>
                $status
                ===
                'published'
                    ? now()
                    : null,
        ]);
    }

    private function completedRun(
        User $user
    ): AiRun {
        $run =
            AiRun::create([
                'uuid' =>
                    (string)
                        Str::uuid(),

                'user_id' =>
                    $user->id,

                'status' =>
                    'completed',

                'user_parameters_json' => [
                    'user_message' =>
                        'Analisis ESG disclosure dan firm value.',
                ],

                'model_provider' =>
                    'mock',

                'model_name' =>
                    'final-regression',

                'started_at' =>
                    now()
                        ->subSecond(),

                'finished_at' =>
                    now(),
            ]);

        AiRunResult::create([
            'ai_run_id' =>
                $run->id,

            'result_type' =>
                'primary',

            'content' =>
                'ESG disclosure menunjukkan peluang penelitian lanjutan.',

            'structured_output_json' => [
                'answer' =>
                    'ESG disclosure menunjukkan peluang penelitian lanjutan.',

                'evidence' =>
                    [],

                'sources' =>
                    [],

                'glossary_context' => [
                    [
                        'id' =>
                            999,

                        'term' =>
                            'ESG',

                        'definition' =>
                            'Environmental, Social, and Governance.',
                    ],
                ],
            ],

            'citations_json' =>
                [],
        ]);

        return $run
            ->fresh();
    }
}