<?php

namespace Tests\Feature;

use App\Models\AiRun;
use App\Models\AiRunResult;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\PromptTemplate;
use App\Models\ResearchMessage;
use App\Models\User;
use App\Services\Research\AiProviderManager;
use App\Services\Research\OpenAiResponsesProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * Kesiapan menerima API key sungguhan: seluruh panggilan HTTP di-fake, tidak ada jaringan dan tidak ada biaya.
 * Yang diuji adalah kontrak dengan provider (permintaan, pemetaan galat, kunci tidak bocor), bukan kualitas jawaban AI.
 */
class OpenAiProviderReadinessTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'sk-test-SECRET-KEY-1234567890';

    private const SNIPPET = 'ESG disclosure quality is positively associated with firm value';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('research.ai_provider', 'openai');
        config()->set('research.ai_model', 'gpt-test');
        config()->set('services.openai.api_key', self::KEY);
        config()->set('services.openai.base_url', 'https://api.openai.test/v1');
        config()->set('research.require_email_verification', false);
        config()->set('research.retrieval.minimum_evidence', 1);
    }

    private function payload(): array
    {
        return ['system_prompt' => 'Gunakan hanya evidence.', 'compiled_prompt' => 'Pertanyaan: uji', 'answer_behavior' => ['follow_up' => ['enabled' => true]]];
    }

    private function okBody(array $override = []): array
    {
        return array_replace([
            'id' => 'resp_123',
            'model' => 'gpt-test-2026',
            'output_text' => json_encode([
                'answer' => 'ESG disclosure berkaitan dengan nilai perusahaan. [E01]',
                'citations' => [['citation_key' => 'E01', 'claim_label' => 'ESG dan nilai', 'quote_snippet' => self::SNIPPET]],
                'next_action' => 'Perdalam dengan Research Gap.',
                'follow_ups' => ['Apa mediatornya?', 7],
            ]),
            'usage' => ['input_tokens' => 120, 'output_tokens' => 45, 'total_tokens' => 165],
        ], $override);
    }

    public function test_manager_selects_openai_and_mock_by_configuration(): void
    {
        $this->assertInstanceOf(OpenAiResponsesProvider::class, app(AiProviderManager::class)->driver());

        config()->set('research.ai_provider', 'mock');
        $this->assertNotInstanceOf(OpenAiResponsesProvider::class, app(AiProviderManager::class)->driver());

        config()->set('research.ai_provider', 'anthropic');
        $this->expectException(RuntimeException::class);
        app(AiProviderManager::class)->driver();
    }

    public function test_request_uses_key_model_structured_output_and_does_not_store_on_provider(): void
    {
        Http::fake(['api.openai.test/*' => Http::response($this->okBody())]);

        $out = app(OpenAiResponsesProvider::class)->generate($this->payload());

        Http::assertSent(function (Request $request) {
            $body = $request->data();

            return $request->url() === 'https://api.openai.test/v1/responses'
                && $request->hasHeader('Authorization', 'Bearer '.self::KEY)
                && $body['model'] === 'gpt-test'
                && $body['store'] === false
                && $body['text']['format']['type'] === 'json_schema'
                && $body['text']['format']['strict'] === true
                && in_array('follow_ups', $body['text']['format']['schema']['required'], true)
                && $body['input'][0]['role'] === 'system'
                && $body['max_output_tokens'] >= 500;
        });

        $this->assertSame('ESG disclosure berkaitan dengan nilai perusahaan. [E01]', $out['answer']);
        $this->assertSame(['Apa mediatornya?'], $out['follow_ups']);
        $this->assertSame('openai', $out['provider_meta']['provider']);
        $this->assertSame(165, $out['provider_meta']['usage']['total_tokens']);
        $this->assertStringNotContainsString(self::KEY, json_encode($out));
    }

    public function test_missing_key_or_model_fails_before_any_network_call(): void
    {
        Http::fake();

        config()->set('services.openai.api_key', '');

        try {
            app(OpenAiResponsesProvider::class)->generate($this->payload());
            $this->fail('Harus gagal tanpa kunci.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('OPENAI_API_KEY', $e->getMessage());
        }

        config()->set('services.openai.api_key', self::KEY);
        config()->set('research.ai_model', '');

        try {
            app(OpenAiResponsesProvider::class)->generate($this->payload());
            $this->fail('Harus gagal tanpa model.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('AI_MODEL', $e->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_provider_errors_are_mapped_to_exceptions_without_leaking_the_key(): void
    {
        $current = null;
        Http::fake(['api.openai.test/*' => function () use (&$current) {
            return Http::response($current[1], $current[0]);
        }]);

        foreach ([
            [401, ['error' => ['message' => 'Incorrect API key provided: '.self::KEY]]],
            [429, ['error' => ['message' => 'You exceeded your current quota']]],
            [500, []],
        ] as $current) {
            [$status] = $current;

            try {
                app(OpenAiResponsesProvider::class)->generate($this->payload());
                $this->fail("HTTP {$status} harus melempar.");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('AI provider request gagal', $e->getMessage());
                $this->assertStringNotContainsString(self::KEY, $e->getMessage());
            }
        }
    }

    public function test_refusal_empty_invalid_and_incomplete_outputs_are_rejected(): void
    {
        $cases = [
            'menolak' => ['output' => [['type' => 'message', 'content' => [['type' => 'refusal', 'refusal' => 'Tidak bisa membantu']]]]],
            'tidak mengembalikan text output' => ['output' => []],
            'bukan JSON valid' => ['output_text' => '{"answer": "terpotong'],
            'field answer' => ['output_text' => json_encode(['answer' => '   ', 'citations' => []])],
            'citations tidak valid' => ['output_text' => json_encode(['answer' => 'x', 'citations' => 'salah'])],
        ];

        $body = [];
        Http::fake(['api.openai.test/*' => function () use (&$body) {
            return Http::response(['id' => 'r', 'model' => 'gpt-test'] + $body);
        }]);

        foreach ($cases as $needle => $body) {

            try {
                app(OpenAiResponsesProvider::class)->generate($this->payload());
                $this->fail("Kasus '{$needle}' harus ditolak.");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString($needle, $e->getMessage());
            }
        }
    }

    public function test_full_run_with_openai_completes_records_provider_usage_and_validates_citations(): void
    {
        Http::fake(['api.openai.test/*' => Http::response($this->okBody())]);
        [$user, $category, $prompt] = $this->seed1();

        $this->actingAs($user)
            ->post(route('research.run'), ['category_id' => $category->id, 'prompt_template_id' => $prompt->id, 'message' => 'ESG disclosure dan firm value'])
            ->assertRedirect();

        $run = AiRun::query()->latest('id')->firstOrFail();
        $this->assertSame('completed', $run->status);
        $this->assertSame('openai', $run->model_provider);
        $this->assertSame('gpt-test', $run->model_name);
        $this->assertSame(1, $run->citations()->count());

        $meta = AiRunResult::query()->where('ai_run_id', $run->id)->where('result_type', 'primary')->firstOrFail()->structured_output_json['provider_meta'];
        $this->assertSame(165, $meta['usage']['total_tokens']);
        $this->assertSame(1, collect(Http::recorded())->count(), 'Satu panggilan provider per run riset.');
        $this->assertStringNotContainsString(self::KEY, json_encode(AuditLog::all()->toArray()));
        $this->assertStringNotContainsString(self::KEY, json_encode(AiRunResult::all()->toArray()));
    }

    public function test_hallucinated_citation_from_openai_is_blocked_as_citation_failed(): void
    {
        Http::fake(['api.openai.test/*' => Http::response($this->okBody([
            'output_text' => json_encode([
                'answer' => 'Klaim palsu. [E01]',
                'citations' => [['citation_key' => 'E01', 'claim_label' => 'x', 'quote_snippet' => 'kalimat yang tidak ada di dokumen mana pun']],
                'next_action' => '',
            ]),
        ]))]);
        [$user, $category, $prompt] = $this->seed1();

        $this->actingAs($user)
            ->post(route('research.run'), ['category_id' => $category->id, 'prompt_template_id' => $prompt->id, 'message' => 'ESG disclosure dan firm value'])
            ->assertRedirect();

        $this->assertSame('citation_failed', AiRun::query()->latest('id')->firstOrFail()->status);
        $this->assertStringNotContainsString('Klaim palsu', (string) ResearchMessage::query()->where('role', 'assistant')->latest('id')->value('content'));
    }

    public function test_provider_failure_never_becomes_a_500_or_leaks_details_to_the_user(): void
    {
        Http::fake(['api.openai.test/*' => Http::response(['error' => ['message' => 'Incorrect API key provided: '.self::KEY]], 401)]);
        [$user, $category, $prompt] = $this->seed1();

        $this->actingAs($user)
            ->post(route('research.run'), ['category_id' => $category->id, 'prompt_template_id' => $prompt->id, 'message' => 'ESG disclosure dan firm value'])
            ->assertRedirect();

        $run = AiRun::query()->latest('id')->firstOrFail();
        $this->assertSame('failed', $run->status);
        $this->assertStringNotContainsString(self::KEY, (string) $run->error_message);
        $this->assertStringNotContainsString(self::KEY, json_encode(AuditLog::all()->toArray()));

        $visible = (string) ResearchMessage::query()->where('role', 'assistant')->latest('id')->value('content');
        $this->assertStringNotContainsString('API key', $visible);
        $this->assertStringNotContainsString('401', $visible);
        $this->assertStringNotContainsString(self::KEY, $visible);

        $this->assertTrue(AuditLog::query()->where('action', 'research.failed')->exists());
    }

    /** @return array{0: User, 1: Category, 2: PromptTemplate} */
    private function seed1(): array
    {
        $user = User::factory()->create(['phone' => '081234567890', 'institution' => 'Lab', 'address' => 'Jakarta', 'date_of_birth' => '1995-01-01', 'status' => 'active', 'email_verified_at' => now()]);
        $category = Category::create(['name' => 'Uji', 'slug' => 'uji-'.Str::lower(Str::random(8)), 'status' => 'active']);
        $prompt = PromptTemplate::query()->where('status', 'active')->whereHas('aiTool', fn ($q) => $q->where('code', 'research-gap')->where('status', 'active'))->firstOrFail();

        $doc = Document::create([
            'uuid' => (string) Str::uuid(), 'title' => 'ESG Disclosure and Firm Value', 'category_id' => $category->id, 'document_type' => 'pdf',
            'publication_year' => 2026, 'language' => 'en', 'source_label' => 'Curated Source', 'status' => 'published', 'visibility' => 'public',
            'uploaded_by' => $user->id, 'published_at' => now(),
        ]);
        DocumentChunk::create([
            'document_id' => $doc->id, 'chunk_index' => 0,
            'content' => 'The study reports that '.self::SNIPPET.'. Greater ESG disclosure may reduce information asymmetry between firms and investors in emerging markets.',
            'metadata_json' => ['page' => 1],
        ]);

        return [$user, $category, $prompt];
    }
}
