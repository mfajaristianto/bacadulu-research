<?php

namespace Tests\Feature;

use App\Models\AiRun;
use App\Models\AiRunResult;
use App\Models\AiTool;
use App\Models\PromptTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminConsoleLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_login_page_renders_without_remember_me(): void
    {
        $this->get(route('admin.login'))
            ->assertOk()
            ->assertSee('Masuk ke Admin BacaDulu')
            ->assertSee('data-admin-password-toggle', false)
            ->assertDontSee('name="remember"', false)
            ->assertDontSee('<script>', false);
    }

    public function test_dashboard_renders_needs_attention_and_plain_language_sidebar(): void
    {
        config(['research.ai_provider' => 'mock']);

        $this->withSession(['research_admin_authenticated' => true])
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Perlu tindakan')
            ->assertSee('Layanan AI masih mode uji (mock)')
            ->assertSee('Template &amp; Format Cetak', false)
            ->assertSee(route('admin.document-templates.index'), false)
            ->assertDontSee('System online')
            ->assertDontSee('<script>', false);
    }

    public function test_dashboard_counts_only_tools_with_their_own_active_prompt(): void
    {
        AiTool::query()->delete();

        $tool = fn (string $code) => AiTool::create(['code' => $code, 'name' => $code, 'status' => 'active']);
        $prompt = fn (AiTool $owner, string $status) => PromptTemplate::create([
            'ai_tool_id' => $owner->id, 'name' => 'P', 'version' => 1, 'task_prompt' => 'x', 'status' => $status,
        ]);

        $valid = $tool('valid');
        $valid->update(['active_prompt_template_id' => $prompt($valid, 'active')->id]);

        $draft = $tool('draft-prompt');
        $draft->update(['active_prompt_template_id' => $prompt($draft, 'draft')->id]);

        $tool('foreign-prompt')->update(['active_prompt_template_id' => $valid->active_prompt_template_id]);

        $this->withSession(['research_admin_authenticated' => true])
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('1 dari 3 alat aktif')
            ->assertSee('2 perlu konfigurasi');
    }

    public function test_dashboard_always_shows_usage_and_academic_sections_with_honest_empty_state(): void
    {
        config(['research.ai_provider' => 'mock']);

        $this->withSession(['research_admin_authenticated' => true])
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSeeInOrder(['Total Tokens', 'Input Tokens', 'Output Tokens', 'Cached Input Tokens', 'Total Riset', 'Biaya AI · IDR', 'Biaya AI · USD'])
            ->assertSeeInOrder(['Penggunaan AI', 'Statistik Prompt', 'Sumber Terbit', 'Riset'])
            ->assertSee('Belum tercatat')
            ->assertSee('Biaya belum tersedia')
            ->assertSee('Jurnal &amp; Makalah', false)
            ->assertSee('Buku / Book Chapter')
            ->assertSee('Klasifikasi khusus belum tersedia')
            ->assertDontSee('Rp ')
            ->assertDontSee('$0');
    }

    public function test_dashboard_sums_provider_usage_and_ignores_mock_and_invalid_period(): void
    {
        config(['research.ai_provider' => 'openai']);

        $user = User::factory()->create();
        $run = fn (string $provider, ?array $usage) => AiRunResult::create([
            'ai_run_id' => AiRun::create([
                'uuid' => (string) Str::uuid(), 'user_id' => $user->id, 'status' => 'completed', 'model_provider' => $provider,
            ])->id,
            'result_type' => 'primary',
            'structured_output_json' => ['provider_meta' => ['usage' => $usage]],
        ]);

        $run('openai', ['input_tokens' => 1200, 'output_tokens' => 300]);
        $run('openai', ['input_tokens' => 800, 'output_tokens' => 100]);
        $run('openai', null);
        $run('mock', ['input_tokens' => 99999, 'output_tokens' => 99999]);

        $this->withSession(['research_admin_authenticated' => true])
            ->get(route('admin.dashboard', ['periode' => 'bukan-periode']))
            ->assertOk()
            ->assertSee('2.000')
            ->assertSee('400')
            ->assertSee('Parsial · dari 2 dari 3 run provider nyata')
            ->assertSee('1 run mode uji (mock)')
            ->assertDontSee('99.999')
            ->assertSee('Token dihitung dari 2 dari 3 run provider nyata');
    }
}
