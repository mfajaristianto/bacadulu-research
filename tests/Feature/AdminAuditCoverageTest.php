<?php

namespace Tests\Feature;

use App\Models\AiRun;
use App\Models\AiRunResult;
use App\Models\AiTool;
use App\Models\Category;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\DocumentGeneration;
use App\Models\DocumentTemplate;
use App\Models\PromptTemplate;
use App\Models\User;
use App\Services\Documents\DocumentRenderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class AdminAuditCoverageTest extends TestCase
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

        config()->set(
            'source-sync.provider',
            'mock'
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    /*
    |--------------------------------------------------------------------------
    | Settings Audit
    |--------------------------------------------------------------------------
    */

    public function test_settings_update_is_audited_without_storing_secret_fields(): void
    {
        $admin =
            User::factory()
                ->create();

        $response =
            $this
                ->withSession(
                    $this
                        ->adminSession(
                            $admin
                        )
                )
                ->put(
                    route(
                        'admin.settings.update'
                    ),
                    [
                        'ai_provider' =>
                            'mock',

                        'ai_model' =>
                            '',

                        'ai_timeout' =>
                            90,

                        'max_output_tokens' =>
                            3500,

                        'context_messages' =>
                            8,

                        'upload_limit_kb' =>
                            102400,

                        'chunk_words' =>
                            500,

                        'chunk_overlap_words' =>
                            75,

                        'minimum_chunk_words' =>
                            60,

                        'candidate_limit' =>
                            400,

                        'evidence_limit' =>
                            8,

                        'max_per_document' =>
                            3,

                        'minimum_evidence' =>
                            2,

                        'minimum_score' =>
                            0.15,

                        'excerpt_length' =>
                            900,

                        'profile_gate' =>
                            80,

                        'research_queue' =>
                            'research',

                        'ingestion_queue' =>
                            'ingestion',

                        'live_refresh_seconds' =>
                            2,

                        'default_document_type' =>
                            'Research Article',

                        'default_citation_style' =>
                            'APA 7',

                        /*
                         * Field liar/secret.
                         * Tidak boleh masuk database.
                         */
                        'openai_api_key' =>
                            'NEVER-STORE-THIS',
                    ]
                );

        $response
            ->assertRedirect();

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'action' =>
                    'settings.updated',

                'user_id' =>
                    $admin->id,
            ]
        );

        $this->assertDatabaseMissing(
            'research_settings',
            [
                'key' =>
                    'openai_api_key',
            ]
        );

        $this->assertDatabaseMissing(
            'research_settings',
            [
                'value' =>
                    'NEVER-STORE-THIS',
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Prompt Audit
    |--------------------------------------------------------------------------
    */

    public function test_prompt_activation_updates_tool_pointer_and_writes_audit_log(): void
    {
        $admin =
            User::factory()
                ->create();

        $tool =
            AiTool::create([
                'code' =>
                    'final-audit-tool-'
                    .Str::lower(
                        Str::random(
                            8
                        )
                    ),

                'name' =>
                    'Final Audit Tool',

                'description' =>
                    'Regression test tool.',

                'status' =>
                    'active',
            ]);

        $prompt =
            PromptTemplate::create([
                'ai_tool_id' =>
                    $tool->id,

                'name' =>
                    'Final Audit Prompt',

                'version' =>
                    1,

                'task_prompt' =>
                    'Analyze the supplied evidence only.',

                'status' =>
                    'draft',

                'created_by' =>
                    $admin->id,
            ]);

        $this
            ->withSession(
                $this
                    ->adminSession(
                        $admin
                    )
            )
            ->patch(
                route(
                    'admin.prompt-versions.activate',
                    $prompt
                )
            )
            ->assertRedirect();

        $prompt->refresh();
        $tool->refresh();

        $this->assertSame(
            'active',
            $prompt->status
        );

        $this->assertSame(
            $prompt->id,
            (int)
                $tool
                    ->active_prompt_template_id
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'action' =>
                    'prompt.activated',

                'auditable_type' =>
                    PromptTemplate::class,

                'auditable_id' =>
                    $prompt->id,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Manual Publish Audit
    |--------------------------------------------------------------------------
    */

    public function test_manual_source_publish_is_audited_and_only_then_becomes_retrieval_eligible(): void
    {
        $admin =
            User::factory()
                ->create();

        $category =
            Category::create([
                'name' =>
                    'Audit Source Category',

                'slug' =>
                    'audit-source-'
                    .Str::lower(
                        Str::random(
                            8
                        )
                    ),

                'status' =>
                    'active',
            ]);

        $document =
            Document::create([
                'uuid' =>
                    (string)
                        Str::uuid(),

                'title' =>
                    'Audit Ready Source',

                'category_id' =>
                    $category->id,

                'document_type' =>
                    'pdf',

                'language' =>
                    'en',

                'status' =>
                    'ready',

                'visibility' =>
                    'public',

                'uploaded_by' =>
                    $admin->id,
            ]);

        DocumentChunk::create([
            'document_id' =>
                $document->id,

            'chunk_index' =>
                0,

            'content' =>
                'Validated source chunk for manual publish audit.',
        ]);

        /*
         * Ready belum retrieval eligible.
         */

        $this->assertFalse(
            Document::query()
                ->eligibleForResearch()
                ->whereKey(
                    $document->id
                )
                ->exists()
        );

        $this
            ->withSession(
                $this
                    ->adminSession(
                        $admin
                    )
            )
            ->put(
                route(
                    'admin.library.update',
                    $document
                ),
                [
                    'title' =>
                        $document
                            ->title,

                    'category_id' =>
                        $category
                            ->id,

                    'action' =>
                        'publish',
                ]
            )
            ->assertRedirect(
                route(
                    'admin.library.edit',
                    $document
                )
            );

        $document->refresh();

        $this->assertSame(
            'published',
            $document->status
        );

        /*
         * Baru sekarang boleh retrieval.
         */

        $this->assertTrue(
            Document::query()
                ->eligibleForResearch()
                ->whereKey(
                    $document->id
                )
                ->exists()
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'action' =>
                    'source.publish',

                'auditable_type' =>
                    Document::class,

                'auditable_id' =>
                    $document->id,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Source Sync Audit
    |--------------------------------------------------------------------------
    */

    public function test_source_sync_scan_is_audited(): void
    {
        $admin =
            User::factory()
                ->create();

        $this
            ->withSession(
                $this
                    ->adminSession(
                        $admin
                    )
            )
            ->post(
                route(
                    'admin.source-sync.scan'
                )
            )
            ->assertRedirect();

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'action' =>
                    'source_sync.scanned',

                'user_id' =>
                    $admin->id,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Document Generation Audit + Provenance
    |--------------------------------------------------------------------------
    */

    public function test_document_generation_records_template_provenance_and_audit_event(): void
    {
        $user =
            User::factory()
                ->create();

        $run =
            $this->completedRun(
                $user
            );

        /*
         * Version tinggi supaya dipilih sebagai
         * default active template pada test.
         */

        $template =
            DocumentTemplate::create([
                'code' =>
                    'final-audit-document-template',

                'name' =>
                    'Final Audit Document Template',

                'document_type' =>
                    'Research Report',

                'description' =>
                    'Document audit regression template.',

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
                    99,

                'is_default' =>
                    true,

                'status' =>
                    'active',

                'created_by' =>
                    $user->id,

                'updated_by' =>
                    $user->id,
            ]);

        /*
         * Kita tidak perlu benar-benar render ZIP/PDF
         * untuk menguji controller + provenance + audit.
         */

        $renderer =
            Mockery::mock(
                DocumentRenderService::class
            );

        $renderer
            ->shouldReceive(
                'render'
            )
            ->once()
            ->andReturnUsing(
                function (
                    DocumentGeneration $generation
                ): DocumentGeneration {
                    $generation->update([
                        'status' =>
                            'completed',

                        'docx_disk' =>
                            'research_outputs',

                        'docx_path' =>
                            'tests/document.docx',

                        'pdf_disk' =>
                            'research_outputs',

                        'pdf_path' =>
                            'tests/document.pdf',

                        'generated_at' =>
                            now(),
                    ]);

                    return $generation
                        ->fresh();
                }
            );

        $this->app->instance(
            DocumentRenderService::class,
            $renderer
        );

        $response =
            $this
                ->actingAs(
                    $user
                )
                ->post(
                    route(
                        'documents.store'
                    ),
                    [
                        'ai_run_id' =>
                            $run->id,

                        'document_type' =>
                            'Research Report',

                        'citation_style' =>
                            'APA 7',

                        'title' =>
                            'Final Audit Generated Document',
                    ]
                );

        $generation =
            DocumentGeneration::query()
                ->latest(
                    'id'
                )
                ->firstOrFail();

        $response
            ->assertRedirect(
                route(
                    'documents.show',
                    $generation
                )
            );

        $this->assertSame(
            $template->id,
            $generation
                ->document_template_id
        );

        $this->assertSame(
            99,
            $generation
                ->template_version
        );

        $this->assertSame(
            'final-audit-document-template',
            data_get(
                $generation
                    ->template_snapshot_json,
                'code'
            )
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'action' =>
                    'document.generated',

                'auditable_type' =>
                    DocumentGeneration::class,

                'auditable_id' =>
                    $generation->id,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function adminSession(
        User $admin
    ): array {
        return [
            'research_admin_authenticated' =>
                true,

            'research_admin_email' =>
                $admin->email,

            'research_admin_user_id' =>
                $admin->id,
        ];
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
                        'Analisis evidence untuk research report.',
                ],

                'model_provider' =>
                    'mock',

                'model_name' =>
                    'final-audit',

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
                'Final research output for document audit.',

            'structured_output_json' => [
                'answer' =>
                    'Final research output for document audit.',

                'evidence' =>
                    [],

                'sources' =>
                    [],

                'glossary_context' =>
                    [],
            ],

            'citations_json' =>
                [],
        ]);

        return $run
            ->fresh();
    }
}