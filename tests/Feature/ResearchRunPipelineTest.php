<?php

namespace Tests\Feature;

use App\Models\AiRun;
use App\Models\AiTool;
use App\Models\Category;
use App\Models\Conversation;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\PromptTemplate;
use App\Models\ResearchMessage;
use App\Models\User;
use App\Services\Research\AiProvider;
use App\Services\Research\AiProviderManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ResearchRunPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        /*
        |--------------------------------------------------------------------------
        | TEST CONFIGURATION
        |--------------------------------------------------------------------------
        |
        | Automated test harus selalu local/deterministic.
        |
        | Walaupun .env production nanti memakai OpenAI,
        | test ini tetap memakai mock.
        |
        */

        config()->set(
            'research.ai_provider',
            'mock'
        );

        config()->set(
            'research.ai_model',
            ''
        );

        config()->set(
            'research.require_email_verification',
            false
        );

        config()->set(
            'research.profile_gate',
            80
        );

        config()->set(
            'research.retrieval.minimum_evidence',
            2
        );

        config()->set(
            'research.retrieval.evidence_limit',
            8
        );

        config()->set(
            'research.retrieval.max_per_document',
            3
        );

        config()->set(
            'research.retrieval.minimum_score',
            0.15
        );
    }


    /*
    |--------------------------------------------------------------------------
    | TEST 01
    | COMPLETED RUN
    |--------------------------------------------------------------------------
    */

    public function test_research_run_completes_with_validated_citations_and_prompt_provenance(): void
    {
        $user =
            $this->completeUser();

        $category =
            $this->activeCategory();

        [
            $tool,
            $prompt,
        ] =
            $this->researchGapPrompt(
                $user,
                7
            );

        $document =
            $this->publishedDocument(
                $user,
                $category,
                'ESG Disclosure and Firm Value'
            );

        $this->addRelevantEsgChunks(
            $document
        );

        $question =
            'Identifikasi research gap mengenai ESG disclosure dan firm value berdasarkan evidence yang tersedia.';


        $response =
            $this
                ->actingAs(
                    $user
                )
                ->post(
                    route(
                        'research.run'
                    ),
                    [
                        'category_id' =>
                            $category->id,

                        'prompt_template_id' =>
                            $prompt->id,

                        'message' =>
                            $question,
                    ]
                );


        $conversation =
            Conversation::query()
                ->firstOrFail();


        $response
            ->assertRedirect(
                route(
                    'workspace.conversation',
                    $conversation
                )
            );


        $run =
            AiRun::query()
                ->latest(
                    'id'
                )
                ->firstOrFail();


        /*
        |--------------------------------------------------------------------------
        | FINAL STATE
        |--------------------------------------------------------------------------
        */

        $this->assertSame(
            'completed',
            $run->status
        );

        $this->assertSame(
            'mock',
            $run->model_provider
        );

        $this->assertNull(
            $run->error_message
        );


        /*
        |--------------------------------------------------------------------------
        | DIRECT PROVENANCE
        |--------------------------------------------------------------------------
        */

        $this->assertSame(
            $user->id,
            $run->user_id
        );

        $this->assertSame(
            $conversation->id,
            $run->conversation_id
        );

        $this->assertSame(
            $tool->id,
            $run->ai_tool_id
        );

        $this->assertSame(
            $prompt->id,
            $run->prompt_template_id
        );


        /*
        |--------------------------------------------------------------------------
        | PROMPT VERSION PROVENANCE
        |--------------------------------------------------------------------------
        */

        $parameters =
            $run
                ->user_parameters_json;


        $this->assertSame(
            7,
            data_get(
                $parameters,
                'prompt_version'
            )
        );

        $this->assertSame(
            'research-gap',
            data_get(
                $parameters,
                'research_tool_code'
            )
        );

        $this->assertSame(
            $question,
            data_get(
                $parameters,
                'user_message'
            )
        );


        /*
        |--------------------------------------------------------------------------
        | RETRIEVAL
        |--------------------------------------------------------------------------
        */

        $this->assertGreaterThanOrEqual(
            2,
            $run
                ->retrievals()
                ->count()
        );

        $this->assertSame(
            1,
            $run
                ->sources()
                ->count()
        );


        /*
        |--------------------------------------------------------------------------
        | RESULTS
        |--------------------------------------------------------------------------
        */

        $this->assertTrue(
            $run
                ->results()
                ->where(
                    'result_type',
                    'retrieval'
                )
                ->exists()
        );

        $this->assertTrue(
            $run
                ->results()
                ->where(
                    'result_type',
                    'primary'
                )
                ->exists()
        );


        /*
        |--------------------------------------------------------------------------
        | CITATIONS
        |--------------------------------------------------------------------------
        */

        $citationCount =
            $run
                ->citations()
                ->count();

        $validCitationCount =
            $run
                ->citations()
                ->where(
                    'is_valid',
                    true
                )
                ->count();


        $this->assertGreaterThanOrEqual(
            2,
            $citationCount
        );

        $this->assertSame(
            $citationCount,
            $validCitationCount
        );


        /*
        |--------------------------------------------------------------------------
        | PROVIDER META
        |--------------------------------------------------------------------------
        */

        $primaryResult =
            $run
                ->primaryResult()
                ->firstOrFail();


        $providerMeta =
            data_get(
                $primaryResult
                    ->structured_output_json,
                'provider_meta',
                []
            );


        $this->assertSame(
            'mock',
            data_get(
                $providerMeta,
                'provider'
            )
        );

        $this->assertSame(
            'prompt-aware-deterministic-v2',
            data_get(
                $providerMeta,
                'model'
            )
        );

        $this->assertSame(
            'research-gap',
            data_get(
                $providerMeta,
                'interpreted_intent'
            )
        );

        $this->assertSame(
            7,
            data_get(
                $providerMeta,
                'prompt_version'
            )
        );


        /*
        |--------------------------------------------------------------------------
        | INTERNAL PROMPT HASHES
        |--------------------------------------------------------------------------
        */

        $this->assertNotEmpty(
            data_get(
                $providerMeta,
                'system_prompt_hash'
            )
        );

        $this->assertNotEmpty(
            data_get(
                $providerMeta,
                'task_prompt_hash'
            )
        );

        $this->assertNotEmpty(
            data_get(
                $providerMeta,
                'retrieval_instructions_hash'
            )
        );

        $this->assertNotEmpty(
            data_get(
                $providerMeta,
                'output_instructions_hash'
            )
        );

        $this->assertNotEmpty(
            data_get(
                $providerMeta,
                'compiled_prompt_hash'
            )
        );


        /*
        |--------------------------------------------------------------------------
        | CONVERSATION
        |--------------------------------------------------------------------------
        */

        $this->assertSame(
            2,
            $conversation
                ->messages()
                ->count()
        );


        $this->assertDatabaseHas(
            'research_messages',
            [
                'conversation_id' =>
                    $conversation->id,

                'role' =>
                    'user',

                'content' =>
                    $question,
            ]
        );


        $assistant =
            $conversation
                ->messages()
                ->where(
                    'role',
                    'assistant'
                )
                ->firstOrFail();


        $this->assertSame(
            $run->id,
            $assistant->ai_run_id
        );

        $this->assertSame(
            'completed',
            data_get(
                $assistant->metadata,
                'status'
            )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | TEST 02
    | IRRELEVANT SOURCE MUST FAIL CLOSED
    |--------------------------------------------------------------------------
    */

    public function test_irrelevant_chunks_return_insufficient_evidence_instead_of_fake_answer(): void
    {
        $user =
            $this->completeUser();

        $category =
            $this->activeCategory();

        [
            ,
            $prompt,
        ] =
            $this->researchGapPrompt(
                $user
            );


        /*
         * Title sengaja terlihat seperti source yang relevan.
         *
         * Tetapi isi sebenarnya PMBOK.
         *
         * Ini mereplikasi kasus local Library kita.
         */

        $document =
            $this->publishedDocument(
                $user,
                $category,
                'Firm Value'
            );


        DocumentChunk::create([
            'document_id' =>
                $document->id,

            'chunk_index' =>
                0,

            'content' =>
                'Project Management Body of Knowledge discusses project scope, schedule, cost, risk management, project charter, stakeholders, execution, monitoring, and project lifecycle.',

            'metadata_json' => [
                'page' => 1,
            ],
        ]);


        DocumentChunk::create([
            'document_id' =>
                $document->id,

            'chunk_index' =>
                1,

            'content' =>
                'Project management planning includes time management, budgeting, procurement, project proposals, quality management, risk response, and project closure.',

            'metadata_json' => [
                'page' => 2,
            ],
        ]);


        $response =
            $this
                ->actingAs(
                    $user
                )
                ->post(
                    route(
                        'research.run'
                    ),
                    [
                        'category_id' =>
                            $category->id,

                        'prompt_template_id' =>
                            $prompt->id,

                        'message' =>
                            'Identifikasi research gap mengenai ESG disclosure dan firm value berdasarkan evidence yang tersedia.',
                    ]
                );


        $conversation =
            Conversation::query()
                ->firstOrFail();


        $response
            ->assertRedirect(
                route(
                    'workspace.conversation',
                    $conversation
                )
            );


        $run =
            AiRun::query()
                ->latest(
                    'id'
                )
                ->firstOrFail();


        /*
         * Ini hasil yang benar.
         *
         * Jangan membuat jawaban dari source tidak relevan.
         */

        $this->assertSame(
            'insufficient_evidence',
            $run->status
        );

        $this->assertNull(
            $run->error_message
        );


        $this->assertSame(
            0,
            $run
                ->retrievals()
                ->count()
        );


        $this->assertSame(
            0,
            $run
                ->citations()
                ->count()
        );


        $this->assertFalse(
            $run
                ->results()
                ->where(
                    'result_type',
                    'primary'
                )
                ->exists()
        );


        $this->assertTrue(
            $run
                ->results()
                ->where(
                    'result_type',
                    'retrieval'
                )
                ->exists()
        );


        $assistant =
            $conversation
                ->messages()
                ->where(
                    'role',
                    'assistant'
                )
                ->firstOrFail();


        $this->assertSame(
            'insufficient_evidence',
            data_get(
                $assistant->metadata,
                'status'
            )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | TEST 03
    | INVALID CITATION MUST FAIL CLOSED
    |--------------------------------------------------------------------------
    */

    public function test_invalid_citation_changes_run_to_citation_failed_and_hides_answer(): void
    {
        $user =
            $this->completeUser();

        $category =
            $this->activeCategory();

        [
            ,
            $prompt,
        ] =
            $this->researchGapPrompt(
                $user
            );


        $document =
            $this->publishedDocument(
                $user,
                $category,
                'ESG Disclosure and Firm Value'
            );


        $this->addRelevantEsgChunks(
            $document
        );


        /*
        |--------------------------------------------------------------------------
        | FAKE INVALID PROVIDER
        |--------------------------------------------------------------------------
        |
        | Retrieval nantinya mempunyai E01/E02/E03.
        |
        | Provider sengaja mengirim E99.
        |
        | Citation validator wajib menolaknya.
        |
        */

        $fakeManager =
            new class extends AiProviderManager
            {
                public function driver(): AiProvider
                {
                    return new class implements AiProvider
                    {
                        public function generate(
                            array $payload
                        ): array {
                            return [
                                'answer' =>
                                    'Claim ini sengaja memakai citation yang tidak pernah diretrieve. [E99]',

                                'citations' => [
                                    [
                                        'citation_key' =>
                                            'E99',

                                        'claim_label' =>
                                            'Invalid citation',

                                        'quote_snippet' =>
                                            'teks yang tidak pernah ada di source',
                                    ],
                                ],

                                'next_action' =>
                                    'Tidak ada.',

                                'provider_meta' => [
                                    'provider' =>
                                        'invalid-test-provider',
                                ],
                            ];
                        }
                    };
                }
            };


        $this->app->instance(
            AiProviderManager::class,
            $fakeManager
        );


        $response =
            $this
                ->actingAs(
                    $user
                )
                ->post(
                    route(
                        'research.run'
                    ),
                    [
                        'category_id' =>
                            $category->id,

                        'prompt_template_id' =>
                            $prompt->id,

                        'message' =>
                            'Identifikasi research gap mengenai ESG disclosure dan firm value berdasarkan evidence yang tersedia.',
                    ]
                );


        $conversation =
            Conversation::query()
                ->firstOrFail();


        $response
            ->assertRedirect(
                route(
                    'workspace.conversation',
                    $conversation
                )
            );


        $run =
            AiRun::query()
                ->latest(
                    'id'
                )
                ->firstOrFail();


        $this->assertSame(
            'citation_failed',
            $run->status
        );


        $this->assertNotNull(
            $run->error_message
        );


        $this->assertStringContainsString(
            'citation',
            mb_strtolower(
                $run->error_message
            )
        );


        /*
         * Primary result boleh tersimpan sebagai audit trail.
         *
         * Tetapi run TIDAK boleh completed.
         */

        $this->assertTrue(
            $run
                ->results()
                ->where(
                    'result_type',
                    'primary'
                )
                ->exists()
        );


        $invalidCitation =
            $run
                ->citations()
                ->where(
                    'citation_key',
                    'E99'
                )
                ->firstOrFail();


        $this->assertFalse(
            $invalidCitation
                ->is_valid
        );


        $this->assertStringContainsString(
            'tidak ditemukan',
            mb_strtolower(
                (string)
                    $invalidCitation
                        ->validation_message
            )
        );


        /*
         * Invalid raw answer tidak ditampilkan ke user.
         */

        $assistant =
            $conversation
                ->messages()
                ->where(
                    'role',
                    'assistant'
                )
                ->firstOrFail();


        $this->assertSame(
            'citation_failed',
            data_get(
                $assistant->metadata,
                'status'
            )
        );


        $this->assertSame(
            'Jawaban AI tidak ditampilkan karena citation validation gagal.',
            $assistant->content
        );
    }


    /*
    |--------------------------------------------------------------------------
    | TEST 04
    | CONVERSATION OWNERSHIP / IDOR
    |--------------------------------------------------------------------------
    */

    public function test_user_cannot_continue_another_users_conversation(): void
    {
        $owner =
            $this->completeUser();

        $intruder =
            $this->completeUser();

        $category =
            $this->activeCategory();

        [
            ,
            $prompt,
        ] =
            $this->researchGapPrompt(
                $intruder
            );


        $document =
            $this->publishedDocument(
                $intruder,
                $category,
                'ESG Disclosure Study'
            );


        $this->addRelevantEsgChunks(
            $document
        );


        $foreignConversation =
            Conversation::create([
                'user_id' =>
                    $owner->id,

                'category_id' =>
                    $category->id,

                'title' =>
                    'Private Research Owner',

                'status' =>
                    'active',
            ]);


        $response =
            $this
                ->actingAs(
                    $intruder
                )
                ->post(
                    route(
                        'research.run'
                    ),
                    [
                        'conversation_id' =>
                            $foreignConversation
                                ->id,

                        'category_id' =>
                            $category->id,

                        'prompt_template_id' =>
                            $prompt->id,

                        'message' =>
                            'Identifikasi research gap mengenai ESG disclosure dan firm value.',
                    ]
                );


        $response
            ->assertNotFound();


        /*
         * Tidak boleh menghasilkan run/message.
         */

        $this->assertSame(
            0,
            AiRun::query()
                ->count()
        );


        $this->assertSame(
            0,
            ResearchMessage::query()
                ->count()
        );
    }


    /*
    |--------------------------------------------------------------------------
    | TEST 05
    | FOLLOW-UP SAME CONVERSATION
    |--------------------------------------------------------------------------
    */

    public function test_follow_up_creates_new_ai_run_inside_the_same_conversation(): void
    {
        $user =
            $this->completeUser();

        $category =
            $this->activeCategory();

        [
            ,
            $prompt,
        ] =
            $this->researchGapPrompt(
                $user
            );


        $document =
            $this->publishedDocument(
                $user,
                $category,
                'ESG Disclosure and Firm Value'
            );


        $this->addRelevantEsgChunks(
            $document
        );


        /*
        |--------------------------------------------------------------------------
        | FIRST REQUEST
        |--------------------------------------------------------------------------
        */

        $firstResponse =
            $this
                ->actingAs(
                    $user
                )
                ->post(
                    route(
                        'research.run'
                    ),
                    [
                        'category_id' =>
                            $category->id,

                        'prompt_template_id' =>
                            $prompt->id,

                        'message' =>
                            'Identifikasi research gap mengenai ESG disclosure dan firm value.',
                    ]
                );


        $conversation =
            Conversation::query()
                ->firstOrFail();


        $firstResponse
            ->assertRedirect(
                route(
                    'workspace.conversation',
                    $conversation
                )
            );


        /*
        |--------------------------------------------------------------------------
        | FOLLOW-UP
        |--------------------------------------------------------------------------
        |
        | Tetap sertakan topic term.
        |
        | Keyword retriever memang sengaja tidak memakai conversation
        | context sebagai academic evidence.
        |
        */

        $secondResponse =
            $this
                ->actingAs(
                    $user
                )
                ->post(
                    route(
                        'research.run'
                    ),
                    [
                        'conversation_id' =>
                            $conversation->id,

                        'category_id' =>
                            $category->id,

                        'prompt_template_id' =>
                            $prompt->id,

                        'message' =>
                            'Untuk ESG disclosure dan firm value, evidence mana yang menunjukkan inkonsistensi atau peluang penelitian lanjutan?',
                    ]
                );


        $secondResponse
            ->assertRedirect(
                route(
                    'workspace.conversation',
                    $conversation
                )
            );


        /*
         * Tidak membuat conversation baru.
         */

        $this->assertSame(
            1,
            Conversation::query()
                ->count()
        );


        /*
         * Dua request = dua AI Run.
         */

        $this->assertSame(
            2,
            AiRun::query()
                ->count()
        );


        $this->assertSame(
            2,
            AiRun::query()
                ->where(
                    'conversation_id',
                    $conversation->id
                )
                ->count()
        );


        /*
         * Dua request:
         *
         * user + assistant
         * user + assistant
         */

        $this->assertSame(
            4,
            ResearchMessage::query()
                ->where(
                    'conversation_id',
                    $conversation->id
                )
                ->count()
        );


        $this->assertSame(
            2,
            ResearchMessage::query()
                ->where(
                    'conversation_id',
                    $conversation->id
                )
                ->where(
                    'role',
                    'user'
                )
                ->count()
        );


        $this->assertSame(
            2,
            ResearchMessage::query()
                ->where(
                    'conversation_id',
                    $conversation->id
                )
                ->where(
                    'role',
                    'assistant'
                )
                ->count()
        );


        /*
         * Keduanya harus completed.
         */

        $this->assertSame(
            2,
            AiRun::query()
                ->where(
                    'conversation_id',
                    $conversation->id
                )
                ->where(
                    'status',
                    'completed'
                )
                ->count()
        );
    }


    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */


    private function completeUser(): User
    {
        return User::factory()
            ->create([
                'phone' =>
                    '081234567890',

                'institution' =>
                    'BacaDulu Research Lab',

                'address' =>
                    'Jakarta',

                'date_of_birth' =>
                    '1995-01-01',

                'status' =>
                    'active',

                'email_verified_at' =>
                    now(),
            ]);
    }


    private function activeCategory(): Category
    {
        return Category::create([
            'name' =>
                'Technology & AI Test',

            /*
             * Random slug mencegah collision
             * dengan category lain.
             */
            'slug' =>
                'technology-ai-test-'
                .Str::lower(
                    Str::random(10)
                ),

            'status' =>
                'active',
        ]);
    }


    /**
     * @return array{
     *     0: AiTool,
     *     1: PromptTemplate
     * }
     */
    private function researchGapPrompt(
        User $creator,
        int $version = 1
    ): array {
        /*
        |--------------------------------------------------------------------------
        | IMPORTANT
        |--------------------------------------------------------------------------
        |
        | Migration:
        |
        | 2026_09_28_000003_seed_canonical_research_tools.php
        |
        | SUDAH membuat:
        |
        | code = research-gap
        |
        | Jadi test TIDAK BOLEH AiTool::create() lagi.
        |
        */

        $tool =
            AiTool::query()
                ->where(
                    'code',
                    'research-gap'
                )
                ->firstOrFail();


        /*
        |--------------------------------------------------------------------------
        | Only One Active Prompt
        |--------------------------------------------------------------------------
        */

        PromptTemplate::query()
            ->where(
                'ai_tool_id',
                $tool->id
            )
            ->where(
                'status',
                'active'
            )
            ->update([
                'status' =>
                    'draft',
            ]);


        /*
        |--------------------------------------------------------------------------
        | Reuse Version 1 / Create Other Version
        |--------------------------------------------------------------------------
        |
        | Migration juga sudah membuat:
        |
        | Research Gap v1
        |
        | Jadi:
        |
        | version = 1  → UPDATE existing
        | version = 7  → CREATE test version
        |
        | updateOrCreate menangani keduanya tanpa melanggar:
        |
        | UNIQUE(ai_tool_id, name, version)
        |
        */

        $prompt =
            PromptTemplate::query()
                ->updateOrCreate(
                    [
                        'ai_tool_id' =>
                            $tool->id,

                        'name' =>
                            'Research Gap',

                        'version' =>
                            $version,
                    ],
                    [
                        'indonesian_name' =>
                            'Kesenjangan Penelitian',

                        'short_description' =>
                            'Menganalisis keterbatasan, hasil yang belum konsisten, dan peluang penelitian berdasarkan evidence yang tersedia.',

                        'question_template' =>
                            'Identifikasi research gap mengenai {topik} berdasarkan evidence yang tersedia.',

                        'meaning' =>
                            'Mencari ruang penelitian yang belum dijawab secara memadai oleh evidence yang tersedia.',

                        'when_to_use' =>
                            'Ketika researcher ingin menemukan peluang penelitian berdasarkan source yang tersedia.',

                        'when_not_to_use' =>
                            'Ketika researcher hanya memerlukan ringkasan sederhana.',

                        'example_text' =>
                            'Apa research gap mengenai ESG disclosure dan firm value?',

                        'expected_output' =>
                            'Temuan, keterbatasan, inkonsistensi, peluang penelitian, evidence, dan sources.',

                        'system_prompt' =>
                            'You are BacaDulu Research. Work only with retrieved eligible evidence supplied by the platform. Never invent sources or citations.',

                        'task_prompt' =>
                            'Using only retrieved evidence, identify documented limitations, inconsistent findings, underexplored relationships, and defensible research opportunities.',

                        'retrieval_instructions' =>
                            'Prioritise evidence directly related to ESG disclosure, firm value, limitations, inconsistent findings, and future research.',

                        'output_instructions' =>
                            'Use structured sections. Every substantive evidence-based claim must include a valid citation marker.',

                        'status' =>
                            'active',

                        'created_by' =>
                            $creator->id,
                    ]
                );


        /*
        |--------------------------------------------------------------------------
        | Point Canonical Tool To Test Prompt Version
        |--------------------------------------------------------------------------
        */

        $tool->update([
            'name' =>
                'Research Gap',

            'description' =>
                'Mengidentifikasi research gap berdasarkan evidence.',

            'status' =>
                'active',

            'active_prompt_template_id' =>
                $prompt->id,
        ]);


        return [
            $tool,
            $prompt,
        ];
    }


    private function publishedDocument(
        User $uploader,
        Category $category,
        string $title
    ): Document {
        return Document::create([
            'uuid' =>
                (string)
                    Str::uuid(),

            'title' =>
                $title,

            'abstract' =>
                null,

            'category_id' =>
                $category->id,

            'document_type' =>
                'pdf',

            'publication_year' =>
                2026,

            'language' =>
                'en',

            'doi' =>
                null,

            'isbn' =>
                null,

            'publisher' =>
                'BacaDulu Automated Test',

            'source_label' =>
                'Curated Source',

            'status' =>
                'published',

            'visibility' =>
                'public',

            'uploaded_by' =>
                $uploader->id,

            'published_at' =>
                now(),
        ]);
    }


    private function addRelevantEsgChunks(
        Document $document
    ): void {
        $chunks = [
            /*
             * E01 candidate
             */
            'The study reports that ESG disclosure quality is positively associated with firm value. Greater ESG disclosure and sustainability transparency may reduce information asymmetry between firms and investors.',

            /*
             * E02 candidate
             */
            'Evidence indicates that the relationship between ESG disclosure and firm value may differ according to corporate governance quality, institutional setting, and investor response.',

            /*
             * E03 candidate
             */
            'Research concerning ESG disclosure and firm value remains limited in several emerging market contexts. This limitation creates opportunities for future research on mediating and moderating mechanisms.',
        ];


        foreach (
            $chunks as
            $index => $content
        ) {
            DocumentChunk::create([
                'document_id' =>
                    $document->id,

                'chunk_index' =>
                    $index,

                'content' =>
                    $content,

                'metadata_json' => [
                    'page' =>
                        $index + 1,

                    'source' =>
                        'automated-test',
                ],
            ]);
        }
    }
}