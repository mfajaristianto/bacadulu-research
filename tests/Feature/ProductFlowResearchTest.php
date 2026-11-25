<?php

namespace Tests\Feature;

use App\Models\AiRun;
use App\Models\Category;
use App\Models\DocumentGeneration;
use App\Services\Research\AiProvider;
use App\Services\Research\AiProviderManager;
use App\Services\ResearchFlow\DimensionExtractor;
use App\Services\ResearchFlow\ResearchResultComposer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\ProductFlowFixtures;
use Tests\TestCase;

/** Product Flow V1: Research tanpa AI (alur, komposer, buku, isolasi kategori, tutorial). */
class ProductFlowResearchTest extends TestCase
{
    use ProductFlowFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFlow();
        // Bukti: setiap pemanggilan penyedia AI melempar. Research tidak boleh menyentuhnya.
        $this->app->bind(AiProviderManager::class, fn () => throw new \LogicException('Research memanggil AiProviderManager'));
        $this->app->bind(AiProvider::class, fn () => throw new \LogicException('Research memanggil AiProvider'));
    }

    private function researchPage(array $q = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->member())->get(route('research.result', $q + ['category_id' => $this->cat->id, 'prompt_template_id' => $this->tool->id, 'q' => 'green accounting terhadap kinerja keuangan perusahaan']));
    }

    public function test_workspace_keeps_the_original_visual_with_the_new_research_contract(): void
    {
        // Pengguna dengan riwayat melihat Workspace asli: topbar, panel 01/02/03, kartu selector, kartu pertanyaan, tombol panah.
        $user = $this->member();
        \App\Models\Conversation::create(['user_id' => $user->id, 'category_id' => $this->cat->id, 'title' => 'Riwayat lama', 'status' => 'active']);
        $res = $this->actingAs($user)->get(route('workspace'))->assertOk();

        foreach (['brx-topbar', 'Personal Workspace', 'research-start-guide', 'Pilih Alat Riset', 'Tulis pertanyaan', 'Periksa sumber', 'research-new-composer-card',
            'research-select-shell', 'research-prompt-picker', 'research-question-panel', 'research-send', 'KATEGORI', 'ALAT RISET', 'TOPIK ATAU PERTANYAAN RISET', 'data-rf-book'] as $marker) {
            $res->assertSee($marker, false);
        }
        // Kontrak backend baru: GET ke hasil Research tanpa AI, field q, tanpa tombol teks generik dan tanpa heading pengganti.
        $res->assertSee('action="'.route('research.result').'"', false)->assertSee('method="GET"', false)->assertSee('name="q"', false);
        foreach (['Cari referensi dulu', 'Output AI bukan evidence', 'Pilih Research Tool', 'RESEARCH TOOL / PROMPT', 'Evidence Engine', 'rf-steps', 'Tulis pertanyaan research Anda', 'name="message"'] as $banned) {
            $res->assertDontSee($banned, false);
        }
    }

    public function test_first_run_workspace_also_posts_to_the_new_research_flow(): void
    {
        $res = $this->actingAs($this->member())->get(route('workspace'))->assertOk();
        $res->assertSee('data-first-research', false)->assertSee('action="'.route('research.result').'"', false)->assertSee('name="q"', false)->assertDontSee('name="message"', false);
        foreach (['Cari referensi dulu', 'Output AI bukan evidence', 'Research Tool'] as $banned) {
            $res->assertDontSee($banned, false);
        }
    }

    public function test_book_tool_is_marked_so_the_workspace_can_show_the_problem_statement_fields(): void
    {
        $user = $this->member();
        \App\Models\Conversation::create(['user_id' => $user->id, 'category_id' => $this->cat->id, 'title' => 'Riwayat lama', 'status' => 'active']);
        $this->actingAs($user)->get(route('workspace'))->assertSee('data-prompt-mode="book"', false)->assertSee('data-prompt-mode="standard"', false);
        // Alat buku terpilih lewat ?prompt_template_id: panel rumusan masalah tampil (tanpa atribut hidden) dengan 8 isian bertingkat.
        $html = $this->get(route('workspace', ['prompt_template_id' => $this->bookTool->id, 'questions' => ['RM satu', 'RM dua']]))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<div class="research-question-panel rf-book" data-rf-book\s*>/', $html);
        $this->assertStringContainsString('value="RM satu"', $html);
        $this->assertSame(8, substr_count($html, 'name="questions[]"'));
    }

    public function test_normal_research_never_calls_ai_and_creates_no_run_or_document(): void
    {
        $this->seedGreenAccounting();
        $res = $this->researchPage()->assertOk();

        $res->assertSee('Gambaran riset')->assertSee('Perbandingan penelitian')->assertSee('Referensi')->assertSee('Generate Document');
        $res->assertDontSee('Output AI bukan evidence')->assertDontSee('Cari referensi dulu');
        Http::assertNothingSent();
        $this->assertSame(0, AiRun::count());
        $this->assertSame(0, DocumentGeneration::count());
    }

    public function test_research_works_when_every_ai_provider_is_off(): void
    {
        config(['research.ai_provider' => 'unconfigured', 'research.synthesis.provider' => 'none', 'research.synthesis.live_enabled' => false]);
        $this->seedGreenAccounting();
        $this->researchPage()->assertOk()->assertSee('sumber relevan');
    }

    public function test_composer_is_deterministic_and_marks_missing_dimensions_without_inventing(): void
    {
        $this->seedGreenAccounting();
        $in = ['category_id' => $this->cat->id, 'topic' => 'green accounting terhadap kinerja keuangan perusahaan', 'ruleset' => $this->tool->rulesetText()];
        $a = app(ResearchResultComposer::class)->compose($in);
        $b = app(ResearchResultComposer::class)->compose($in);
        $this->assertSame($a, $b);
        $this->assertFalse($a['ai_used']);
        $this->assertSame(['objek', 'sampel', 'metode', 'variabel', 'teori', 'hasil'], array_column($a['dimensions'], 'key'));

        $rich = collect($a['comparison'])->first(fn ($r) => str_contains($r['title'], 'manufaktur'));
        $meta = collect($a['comparison'])->first(fn ($r) => str_contains($r['title'], 'tinjauan'));
        $this->assertTrue($rich['cells']['sampel']['available']);
        $this->assertStringContainsString('45 perusahaan', $rich['cells']['sampel']['value']);
        $this->assertTrue($rich['cells']['metode']['available']);
        $this->assertFalse($rich['cells']['teori']['available']);          // tidak ada di evidence -> tidak dikarang
        $this->assertNull($rich['cells']['teori']['value']);
        foreach ($meta['cells'] as $cell) {                                  // metadata saja tidak punya teks evidence
            $this->assertFalse($cell['available']);
        }
        $this->assertSame(DimensionExtractor::UNAVAILABLE, $a['unavailable_text']);
        $this->assertSame(1, $a['overview']['with_abstract']);
        $this->assertSame(1, $a['overview']['metadata_only']);

        $this->researchPage()->assertSee('Data tidak tersedia pada evidence.');
    }

    public function test_ruleset_dimensions_follow_the_tool_text(): void
    {
        $this->assertSame(['metode', 'hasil'], DimensionExtractor::fromRuleset('Bandingkan metode dan hasil penelitian.'));
        $this->assertCount(6, DimensionExtractor::fromRuleset('Tidak menyebut dimensi apa pun.'));
    }

    public function test_empty_result_is_honest(): void
    {
        $this->researchPage()->assertOk()->assertSee('Belum ada sumber yang cocok')->assertDontSee('id="generate-document"', false);
    }

    public function test_category_isolation(): void
    {
        $other = Category::create(['name' => 'Kesehatan', 'slug' => 'kes', 'status' => 'active']);
        $this->ext('Green accounting dan kinerja keuangan perusahaan di rumah sakit', 'Penelitian green accounting terhadap kinerja keuangan perusahaan.', $other);
        $this->researchPage()->assertSee('Belum ada sumber yang cocok');
        $this->actingAs($this->member())->get(route('research.result', ['category_id' => $other->id, 'prompt_template_id' => $this->tool->id, 'q' => 'green accounting kinerja keuangan perusahaan']))->assertSee('rumah sakit');
    }

    public function test_book_research_gives_one_result_per_question_and_deterministic_aggregate(): void
    {
        $this->seedGreenAccounting();
        $this->ext('Pengungkapan lingkungan dan nilai perusahaan', 'Penelitian ini menguji pengungkapan lingkungan terhadap nilai perusahaan dengan metode regresi.');
        $res = $this->actingAs($this->member())->get(route('research.result', [
            'category_id' => $this->cat->id, 'prompt_template_id' => $this->bookTool->id, 'q' => 'akuntansi lingkungan',
            'questions' => ['green accounting kinerja keuangan perusahaan', 'pengungkapan lingkungan nilai perusahaan', 'topik yang tidak ada sama sekali xyzzy'],
        ]))->assertOk();
        $res->assertSee('Rumusan Masalah 1')->assertSee('Rumusan Masalah 2')->assertSee('Rumusan Masalah 3')->assertSee('Ringkasan keseluruhan');
        $res->assertSee('Belum ada sumber untuk Rumusan Masalah 3');
        Http::assertNothingSent();

        $r = app(ResearchResultComposer::class)->compose(['category_id' => $this->cat->id, 'topic' => 'akuntansi lingkungan', 'questions' => ['green accounting kinerja keuangan perusahaan', 'pengungkapan lingkungan nilai perusahaan', 'xyzzy tidak ada'], 'ruleset' => '']);
        $this->assertSame('book', $r['mode']);
        $this->assertCount(3, $r['questions']);
        $this->assertSame([3], $r['aggregate']['empty_questions']);
        $this->assertSame(2, $r['aggregate']['covered_questions']);
        $this->assertGreaterThanOrEqual(2, $r['aggregate']['unique_sources']);
    }

    public function test_book_tool_requires_at_least_one_question_and_standard_tool_ignores_questions(): void
    {
        $this->actingAs($this->member())->get(route('research.result', ['category_id' => $this->cat->id, 'prompt_template_id' => $this->bookTool->id, 'q' => 'akuntansi lingkungan']))
            ->assertRedirect(route('workspace'))->assertSessionHasErrors('questions');

        $this->seedGreenAccounting();
        $r = $this->researchPage(['questions' => ['abaikan saya']])->assertOk();
        $r->assertDontSee('Rumusan Masalah 1');
    }

    public function test_research_requires_login_and_valid_input(): void
    {
        $this->get(route('research.result', ['category_id' => 1]))->assertRedirect(route('login'));
        $this->actingAs($this->member())->get(route('research.result', ['category_id' => $this->cat->id]))->assertSessionHasErrors(['prompt_template_id', 'q']);
    }

    public function test_first_login_tutorial_complete_and_admin_reset(): void
    {
        $user = $this->member();
        $this->actingAs($user)->get(route('workspace'))->assertSee('data-first-tour', false)->assertSee('Pilih kategori dan alat riset');

        $this->postJson(route('onboarding.complete'))->assertOk();
        $this->assertNotNull($user->fresh()->onboarding_completed_at);
        $this->get(route('workspace'))->assertDontSee('data-first-tour', false);

        // Admin: Reset Tutorial. Data riset tidak dihapus.
        config(['research.admin_email' => 'admin@uji.test']);
        $admin = $this->member(['email' => 'admin@uji.test']);
        AiRun::create(['uuid' => (string) \Illuminate\Support\Str::uuid(), 'user_id' => $user->id, 'status' => 'completed', 'user_parameters_json' => [], 'model_provider' => 'mock', 'model_name' => 'x']);
        $this->withSession(['research_admin_authenticated' => true, 'research_admin_user_id' => $admin->id])
            ->post(route('admin.users.tutorial-reset', $user))->assertRedirect();
        $this->assertNull($user->fresh()->onboarding_completed_at);
        $this->assertSame(1, AiRun::where('user_id', $user->id)->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.tutorial_reset', 'auditable_id' => $user->id]);

        $this->actingAs($user->fresh())->get(route('workspace'))->assertSee('data-first-tour', false);
    }

    public function test_tutorial_video_is_shown_only_when_configured(): void
    {
        config(['research.tutorial_video_url' => 'https://cdn.example.org/tutorial.mp4']);
        $this->actingAs($this->member())->get(route('workspace'))->assertSee('https://cdn.example.org/tutorial.mp4', false);
    }
}
