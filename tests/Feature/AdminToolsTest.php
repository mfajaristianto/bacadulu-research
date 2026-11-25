<?php

namespace Tests\Feature;

use App\Models\AiTool;
use App\Models\PromptTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminToolsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Migrasi menanam alat dan instruksi kanonik; tes ini memakai himpunan sendiri agar angka bisa dipastikan.
        PromptTemplate::query()->delete();
        AiTool::query()->delete();

        $this->admin = User::factory()->create();
    }

    private function as(): static
    {
        return $this->withSession(['research_admin_authenticated' => true, 'research_admin_user_id' => $this->admin->id]);
    }

    private function tool(string $code, string $status = 'active', array $extra = []): AiTool
    {
        return AiTool::create($extra + ['code' => $code, 'name' => ucwords(str_replace('-', ' ', $code)), 'status' => $status, 'description' => 'Deskripsi '.$code]);
    }

    /** Alat dengan satu instruksi; $link=true menjadikannya instruksi aktif alat. */
    private function prompt(AiTool $tool, string $status = 'active', bool $link = true, int $version = 1): PromptTemplate
    {
        $prompt = PromptTemplate::create(['ai_tool_id' => $tool->id, 'name' => 'Instruksi '.$tool->code, 'version' => $version, 'task_prompt' => 'Analisis bukti.', 'status' => $status, 'created_by' => $this->admin->id]);

        if ($link) {
            $tool->update(['active_prompt_template_id' => $prompt->id]);
        }

        return $prompt;
    }

    public function test_index_separates_ready_tools_from_active_tools_without_instructions(): void
    {
        $ready = $this->tool('research-gap');
        $this->prompt($ready);
        $this->tool('trend-explorer'); // aktif tetapi tanpa instruksi
        $draft = $this->tool('variable-identification');
        $this->prompt($draft, 'draft'); // instruksi terhubung tetapi bukan active: bukan "siap"
        $this->tool('literature-insight', 'archived');

        $this->as()->get(route('admin.tools.index'))
            ->assertOk()
            ->assertSee('Kelola alat, konfigurasi, dan instruksi AI yang digunakan dalam proses penelitian.')
            ->assertSeeInOrder(['Total alat', '4', 'Aktif', '3', 'Siap dipakai', '1', 'Aktif tanpa instruksi', '2'])
            ->assertSee('Belum ada instruksi aktif')
            ->assertSee('Instruksi terhubung berstatus Draf')
            ->assertSee('Tidak tersedia untuk pengguna')
            ->assertSee(route('admin.prompt-versions.index', ['tool' => $ready->id]), false);
    }

    public function test_empty_state_and_no_fake_numbers(): void
    {
        $this->as()->get(route('admin.tools.index'))->assertOk()->assertSee('Belum ada alat riset')->assertSeeInOrder(['Total alat', '0', 'Siap dipakai', '0']);
    }

    public function test_search_status_instruction_filters_and_sorting(): void
    {
        $gap = $this->tool('research-gap', extra: ['name' => 'Research Gap', 'description' => 'Mencari celah penelitian']);
        $this->prompt($gap);
        $this->tool('trend-explorer', extra: ['name' => 'Trend Explorer']);
        $this->tool('research-summary', 'archived', ['name' => 'Research Summary']);

        $has = fn (string $name) => 'data-name="'.$name.'"';
        $index = fn (array $q) => $this->as()->get(route('admin.tools.index', $q))->assertOk();

        $index(['search' => 'celah'])->assertSee($has('Research Gap'), false)->assertDontSee($has('Trend Explorer'), false);
        $index(['search' => 'trend-explorer'])->assertSee($has('Trend Explorer'), false)->assertDontSee($has('Research Gap'), false); // slug
        $index(['status' => 'archived'])->assertSee($has('Research Summary'), false)->assertDontSee($has('Research Gap'), false);
        $index(['instruction' => 'ready'])->assertSee($has('Research Gap'), false)->assertDontSee($has('Trend Explorer'), false);
        $index(['instruction' => 'missing'])->assertSee($has('Trend Explorer'), false)->assertDontSee($has('Research Gap'), false);
        $index([])->assertSeeInOrder([$has('Research Gap'), $has('Trend Explorer'), $has('Research Summary')], false); // aktif dulu
        $index(['sort' => 'name'])->assertSeeInOrder([$has('Research Gap'), $has('Research Summary'), $has('Trend Explorer')], false);
        $index(['search' => 'tidak-ada'])->assertSee('Tidak ada alat yang cocok');
        $this->as()->get(route('admin.tools.index', ['instruction' => 'x']))->assertSessionHasErrors('instruction');
    }

    public function test_create_validates_the_slug_and_records_an_audit_entry(): void
    {
        $this->as()->postJson(route('admin.tools.store'), ['name' => '  Tool   Baru ', 'code' => 'tool-baru', 'status' => 'active'])
            ->assertOk()->assertJsonPath('ok', true)->assertJsonStructure(['manage_url']);

        $tool = AiTool::query()->firstOrFail();
        $this->assertSame(['Tool Baru', null], [$tool->name, $tool->active_prompt_template_id]); // tidak membuat instruksi apa pun
        $this->assertSame(0, PromptTemplate::query()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'tool.created']);

        foreach (['Tool Baru', 'tool_baru', '-x', 'x--y'] as $bad) {
            $this->as()->postJson(route('admin.tools.store'), ['name' => 'X', 'code' => $bad, 'status' => 'active'])->assertStatus(422)->assertJsonValidationErrors('code');
        }

        $this->as()->postJson(route('admin.tools.store'), ['name' => '', 'code' => 'ok-slug', 'status' => 'active'])->assertStatus(422)->assertJsonValidationErrors('name');
        $this->as()->postJson(route('admin.tools.store'), ['name' => 'X', 'code' => 'ok-slug', 'status' => 'dihapus'])->assertStatus(422)->assertJsonValidationErrors('status');
    }

    public function test_a_taken_slug_points_to_the_existing_tool(): void
    {
        $existing = $this->tool('research-gap');

        $this->as()->postJson(route('admin.tools.store'), ['name' => 'Lain', 'code' => 'research-gap', 'status' => 'active'])
            ->assertStatus(422)->assertJsonValidationErrors('code')->assertJsonPath('existing.url', route('admin.tools.edit', $existing));
        $this->assertSame(1, AiTool::query()->count());

        $this->as()->getJson(route('admin.tools.check', ['code' => 'Research Gap']))->assertOk()->assertJsonPath('code.id', $existing->id);
        $this->as()->getJson(route('admin.tools.check', ['code' => 'research-gap', 'ignore' => $existing->id]))->assertOk()->assertJsonPath('code', null);
    }

    public function test_editing_the_description_never_touches_the_slug_or_the_instruction_link(): void
    {
        $tool = $this->tool('research-gap', extra: ['icon' => 'search', 'input_schema_json' => ['a' => 1]]);
        $prompt = $this->prompt($tool);

        $this->as()->putJson(route('admin.tools.update', $tool), ['name' => 'Research Gap Baru', 'code' => 'research-gap', 'description' => 'Deskripsi baru', 'status' => 'active'])->assertOk();

        $tool->refresh();
        $this->assertSame(['research-gap', 'Deskripsi baru', 'search', $prompt->id, ['a' => 1]], [$tool->code, $tool->description, $tool->icon, $tool->active_prompt_template_id, $tool->input_schema_json]);
        $this->assertDatabaseHas('prompt_templates', ['id' => $prompt->id, 'status' => 'active', 'task_prompt' => 'Analisis bukti.']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'tool.updated']);
    }

    public function test_changing_the_slug_of_an_existing_tool_needs_explicit_confirmation(): void
    {
        $tool = $this->tool('research-gap');
        $payload = ['name' => 'Research Gap', 'code' => 'celah-riset', 'status' => 'active'];

        $this->as()->putJson(route('admin.tools.update', $tool), $payload)->assertStatus(422)->assertJsonValidationErrors('code');
        $this->assertSame('research-gap', $tool->fresh()->code);

        $this->as()->putJson(route('admin.tools.update', $tool), $payload + ['confirm_code_change' => 1])->assertOk();
        $this->assertSame('celah-riset', $tool->fresh()->code);

        // Alat lain tidak bisa mengambil slug yang sudah dipakai, sekalipun dikonfirmasi.
        $other = $this->tool('trend-explorer');
        $this->as()->putJson(route('admin.tools.update', $other), ['name' => 'T', 'code' => 'celah-riset', 'status' => 'active', 'confirm_code_change' => 1])->assertStatus(422);
    }

    public function test_archiving_keeps_every_instruction_and_version(): void
    {
        $tool = $this->tool('research-gap');
        $active = $this->prompt($tool);
        $old = $this->prompt($tool, 'archived', false, 2);

        $this->as()->putJson(route('admin.tools.update', $tool), ['name' => 'Research Gap', 'code' => 'research-gap', 'status' => 'archived'])->assertOk();

        $this->assertSame('archived', $tool->fresh()->status);
        $this->assertSame($active->id, $tool->fresh()->active_prompt_template_id);
        $this->assertSame(2, PromptTemplate::query()->where('ai_tool_id', $tool->id)->count());
        $this->assertDatabaseHas('prompt_templates', ['id' => $old->id, 'status' => 'archived']);

        // Arsip menyembunyikan alat dari pengguna, tetapi halaman admin tetap jujur soal itu.
        $this->as()->get(route('admin.tools.index'))->assertSee('Tidak tersedia untuk pengguna')->assertDontSee('Siap dipakai</span>', false);
        $this->as()->putJson(route('admin.tools.update', $tool), ['name' => 'Research Gap', 'code' => 'research-gap', 'status' => 'active'])->assertOk();
        $this->as()->get(route('admin.tools.index'))->assertSee('bd-tl__sub bd-tl__sub--ok', false);
    }

    public function test_plain_form_pages_and_posts_work_without_javascript(): void
    {
        $tool = $this->tool('research-gap');

        $this->as()->get(route('admin.tools.create'))->assertOk()->assertSee('Nama alat');
        $this->as()->get(route('admin.tools.edit', $tool))->assertOk()->assertSee('Saya mengerti, ubah slug alat ini')->assertSee('Belum ada instruksi aktif');

        $this->as()->from(route('admin.tools.edit', $tool))->put(route('admin.tools.update', $tool), ['name' => 'RG', 'code' => 'baru', 'status' => 'active'])
            ->assertRedirect(route('admin.tools.edit', $tool))->assertSessionHasErrors('code');

        $this->as()->put(route('admin.tools.update', $tool), ['name' => 'RG', 'code' => 'baru', 'status' => 'active', 'confirm_code_change' => 1])
            ->assertRedirect(route('admin.tools.edit', $tool))->assertSessionHas('success');
    }

    public function test_guests_are_sent_to_the_admin_login(): void
    {
        $tool = $this->tool('research-gap');

        $this->get(route('admin.tools.index'))->assertRedirect(route('admin.login'));
        $this->postJson(route('admin.tools.store'), ['name' => 'X'])->assertRedirect(route('admin.login'));
        $this->putJson(route('admin.tools.update', $tool), ['name' => 'X'])->assertRedirect(route('admin.login'));
        $this->getJson(route('admin.tools.check', ['code' => 'x']))->assertRedirect(route('admin.login'));
    }
}
