<?php

namespace Tests\Feature;

use App\Http\Controllers\ResearchFlowController;
use App\Models\AiTool;
use App\Models\PromptTemplate;
use App\Services\Research\PromptVersions;
use Database\Seeders\BookResearchToolSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Seeder Riset Buku: dibuat sekali, idempoten per ai_tools.code, tidak menimpa suntingan Admin, tidak menyentuh alat lain. */
class BookResearchToolSeederTest extends TestCase
{
    use RefreshDatabase;

    private function seedTool(): void
    {
        $this->seed(BookResearchToolSeeder::class);
    }

    private function counts(): array
    {
        $tool = AiTool::where('code', 'book-research')->first();

        return [
            'tools' => AiTool::where('code', 'book-research')->count(),
            'prompts' => $tool ? PromptTemplate::where('ai_tool_id', $tool->id)->count() : 0,
            'active' => $tool ? PromptTemplate::where('ai_tool_id', $tool->id)->where('status', 'active')->count() : 0,
        ];
    }

    public function test_first_run_creates_the_active_book_tool_with_the_ruleset(): void
    {
        $this->seedTool();

        $tool = AiTool::where('code', 'book-research')->firstOrFail();
        $this->assertSame('Riset Buku', $tool->name);
        $this->assertSame('RB', $tool->icon);
        $this->assertSame('active', $tool->status);

        $p = PromptTemplate::findOrFail($tool->active_prompt_template_id);
        $this->assertSame(['Riset Buku', 'active', 1, 'book'], [$p->name, $p->status, $p->version, $p->input_mode_name]);
        $this->assertSame(BookResearchToolSeeder::RULESET, $p->task_prompt);
        $this->assertStringContainsString('Telusuri dan bandingkan penelitian mengenai {topik} untuk menjawab setiap rumusan masalah', $p->rulesetText());
        $this->assertSame(['tools' => 1, 'prompts' => 1, 'active' => 1], $this->counts());

        // Muncul di alur Research sebagai alat bermode buku.
        $this->assertSame('book', ResearchFlowController::tools()->firstWhere('id', $p->id)?->input_mode_name);
    }

    public function test_running_again_creates_no_duplicates(): void
    {
        $this->seedTool();
        $first = AiTool::where('code', 'book-research')->firstOrFail();
        $tools = AiTool::count();
        $prompts = PromptTemplate::count();

        $this->seedTool();
        $this->seedTool();

        $this->assertSame(['tools' => 1, 'prompts' => 1, 'active' => 1], $this->counts());
        $this->assertSame($tools, AiTool::count());
        $this->assertSame($prompts, PromptTemplate::count());
        $this->assertSame($first->id, AiTool::where('code', 'book-research')->value('id'));   // id yang sama, tanpa membuat ulang
    }

    public function test_admin_edits_and_new_versions_are_never_overwritten_or_duplicated(): void
    {
        $this->seedTool();
        $tool = AiTool::where('code', 'book-research')->firstOrFail();
        $v1 = PromptTemplate::findOrFail($tool->active_prompt_template_id);

        $tool->update(['name' => 'Riset Buku (Admin)', 'icon' => 'XX']);
        $v2 = app(PromptVersions::class)->revise($v1, null);
        $v2->update(['task_prompt' => 'Aturan versi 2 suntingan Admin.']);
        app(PromptVersions::class)->activate($v2);

        $this->seedTool();

        $tool->refresh();
        $this->assertSame(['Riset Buku (Admin)', 'XX'], [$tool->name, $tool->icon]);
        $this->assertSame($v2->id, (int) $tool->active_prompt_template_id);
        $this->assertSame(['tools' => 1, 'prompts' => 2, 'active' => 1], $this->counts());      // tidak ada versi ketiga, aktif tetap satu
        $this->assertSame('Aturan versi 2 suntingan Admin.', PromptTemplate::findOrFail($v2->id)->task_prompt);
    }

    public function test_seeder_does_not_touch_any_other_tool(): void
    {
        $snapshot = fn () => md5(json_encode([AiTool::where('code', '!=', 'book-research')->orderBy('id')->get()->toArray(), PromptTemplate::whereHas('aiTool', fn ($q) => $q->where('code', '!=', 'book-research'))->orderBy('id')->get()->toArray()]));
        $before = $snapshot();

        $this->seedTool();
        $this->seedTool();

        $this->assertSame($before, $snapshot());
        $this->assertSame(0, PromptTemplate::whereHas('aiTool', fn ($q) => $q->where('code', '!=', 'book-research'))->where('input_mode', 'book')->count());   // alat lain tetap standard
    }

    public function test_database_seeder_includes_it_idempotently(): void
    {
        config(['app.env' => 'testing']);
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(['tools' => 1, 'prompts' => 1, 'active' => 1], $this->counts());
    }
}
