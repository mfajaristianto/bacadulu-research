<?php

namespace Tests\Feature;

use App\Models\AiRun;
use App\Models\AiTool;
use App\Models\PromptTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminInstructionsTest extends TestCase
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

    private function tool(string $code = 'research-gap', string $status = 'active'): AiTool
    {
        return AiTool::create(['code' => $code, 'name' => ucwords(str_replace('-', ' ', $code)), 'status' => $status]);
    }

    private function version(AiTool $tool, int $n, string $status, string $name = 'Analisis Celah', string $task = 'Analisis bukti.'): PromptTemplate
    {
        $prompt = PromptTemplate::create(['ai_tool_id' => $tool->id, 'name' => $name, 'version' => $n, 'task_prompt' => $task.' v'.$n, 'status' => $status, 'created_by' => $this->admin->id]);

        if ($status === 'active') {
            $tool->update(['active_prompt_template_id' => $prompt->id]);
        }

        return $prompt;
    }

    private function usedBy(PromptTemplate $prompt): AiRun
    {
        return AiRun::create(['uuid' => (string) Str::uuid(), 'user_id' => $this->admin->id, 'ai_tool_id' => $prompt->ai_tool_id, 'prompt_template_id' => $prompt->id, 'status' => 'completed']);
    }

    /** research-gap dengan v1 arsip, v2 aktif, v3 draf: skenario di brief. */
    private function scenario(): array
    {
        $tool = $this->tool();

        return [$tool, $this->version($tool, 1, 'archived'), $this->version($tool, 2, 'active'), $this->version($tool, 3, 'draft')];
    }

    public function test_list_groups_versions_into_instructions_and_states_what_is_ready(): void
    {
        [$tool] = $this->scenario();
        $archivedTool = $this->tool('trend-explorer', 'archived');
        $this->version($archivedTool, 1, 'active', 'Tren');
        $noActive = $this->tool('literature-insight');
        $this->version($noActive, 1, 'draft', 'Wawasan');

        $this->as()->get(route('admin.prompts.index'))->assertOk()
            ->assertSee('Kelola instruksi AI, riwayat perubahan, dan versi yang digunakan oleh alat riset.')
            ->assertSeeInOrder(['Total instruksi', '3', 'Instruksi aktif', '2', 'Punya draf', '2', 'Perlu ditinjau', '1'])
            ->assertSee('Siap dipakai')                              // research-gap: aktif, alat aktif
            ->assertSee('Alat diarsipkan, tidak tersedia untuk pengguna')
            ->assertSee('Belum ada versi aktif')
            ->assertSee('3 versi');                                  // v1+v2+v3 jadi SATU instruksi
    }

    public function test_search_filters_and_sorting(): void
    {
        [$gap] = $this->scenario();
        $trend = $this->tool('trend-explorer');
        $this->version($trend, 1, 'archived', 'Tren Topik');

        $has = fn (string $name) => 'bd-ix__title">'.$name;
        $index = fn (array $q) => $this->as()->get(route('admin.prompts.index', $q))->assertOk();

        $index(['search' => 'Celah'])->assertSee($has('Analisis Celah'), false)->assertDontSee($has('Tren Topik'), false);
        $index(['search' => 'trend-explorer'])->assertSee($has('Tren Topik'), false)->assertDontSee($has('Analisis Celah'), false); // kode alat
        $index(['status' => 'active'])->assertSee($has('Analisis Celah'), false)->assertDontSee($has('Tren Topik'), false);
        $index(['status' => 'draft'])->assertSee($has('Analisis Celah'), false)->assertDontSee($has('Tren Topik'), false);
        $index(['status' => 'archived'])->assertSee($has('Tren Topik'), false)->assertDontSee($has('Analisis Celah'), false);
        $index(['tool' => $trend->id])->assertSee($has('Tren Topik'), false)->assertDontSee($has('Analisis Celah'), false);
        $index(['sort' => 'name'])->assertSeeInOrder([$has('Analisis Celah'), $has('Tren Topik')], false);
        $index(['search' => 'tidak-ada'])->assertSee('Tidak ada instruksi yang cocok');
        $this->as()->get(route('admin.prompts.index', ['sort' => 'x']))->assertSessionHasErrors('sort');
    }

    public function test_detail_lists_every_version_and_locks_active_and_archived_ones(): void
    {
        [, $v1, $v2, $v3] = $this->scenario();

        $this->as()->get(route('admin.prompts.index', ['instruksi' => $v2->id]))->assertOk()
            ->assertSee('Riwayat versi')->assertSee('v1')->assertSee('v2')->assertSee('v3')
            ->assertSee('Sedang Aktif')->assertSee('Versi aktif dikunci')->assertSee('Buat revisi dari v2')
            ->assertDontSee('data-ix-save', false);

        $this->as()->get(route('admin.prompts.index', ['instruksi' => $v2->id, 'v' => $v1->id]))->assertSee('Versi arsip dikunci')->assertSee('Jadikan Aktif');
        $this->as()->get(route('admin.prompts.index', ['instruksi' => $v2->id, 'v' => $v3->id]))->assertSee('data-ix-save', false)->assertSee('Arsipkan draf');
    }

    public function test_create_makes_a_draft_v1_and_never_touches_the_tools_active_instruction(): void
    {
        [$tool, , $v2] = $this->scenario();
        $other = $this->tool('trend-explorer');

        $this->as()->postJson(route('admin.prompts.store'), ['ai_tool_id' => $other->id, 'name' => '  Tren   Topik ', 'task_prompt' => "Baris 1\r\nBaris 2"])
            ->assertOk()->assertJsonPath('ok', true)->assertJsonStructure(['url']);

        $new = PromptTemplate::query()->where('ai_tool_id', $other->id)->firstOrFail();
        $this->assertSame(['Tren Topik', 1, 'draft', "Baris 1\nBaris 2"], [$new->name, $new->version, $new->status, $new->task_prompt]);
        $this->assertNull($other->fresh()->active_prompt_template_id);
        $this->assertSame($v2->id, $tool->fresh()->active_prompt_template_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'prompt.created', 'auditable_id' => $new->id]);

        $this->as()->postJson(route('admin.prompts.store'), ['ai_tool_id' => $tool->id, 'name' => 'analisis celah', 'task_prompt' => 'x'])
            ->assertStatus(422)->assertJsonValidationErrors('name')->assertJsonPath('existing.url', route('admin.prompts.index', ['instruksi' => $v2->id]));
        $this->as()->postJson(route('admin.prompts.store'), ['name' => 'X', 'task_prompt' => 'x'])->assertStatus(422)->assertJsonValidationErrors('ai_tool_id');
        $this->as()->postJson(route('admin.prompts.store'), ['ai_tool_id' => $tool->id, 'name' => 'Baru', 'task_prompt' => ''])->assertStatus(422)->assertJsonValidationErrors('task_prompt');
    }

    public function test_only_an_unused_draft_can_be_edited_and_only_its_content(): void
    {
        [$tool, $v1, $v2, $v3] = $this->scenario();

        $this->as()->putJson(route('admin.prompts.update', $v3), [
            'task_prompt' => 'Isi baru', 'system_prompt' => 'Peran', 'name' => 'Diganti', 'status' => 'active', 'version' => 9, 'ai_tool_id' => 999,
        ])->assertOk();

        $v3->refresh();
        $this->assertSame(['Isi baru', 'Peran', 'Analisis Celah', 'draft', 3, $tool->id], [$v3->task_prompt, $v3->system_prompt, $v3->name, $v3->status, $v3->version, $v3->ai_tool_id]);
        $this->assertSame($v2->id, $tool->fresh()->active_prompt_template_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'prompt.updated', 'auditable_id' => $v3->id]);

        foreach ([$v2, $v1] as $locked) { // aktif dan arsip: ditolak server, apa pun yang dikirim form
            $before = $locked->task_prompt;
            $this->as()->putJson(route('admin.prompts.update', $locked), ['task_prompt' => 'Ditimpa'])->assertStatus(409);
            $this->assertSame($before, $locked->fresh()->task_prompt);
        }

        // Draf yang sudah pernah dipakai run ikut dikunci, supaya run lama tidak ikut berubah.
        $used = $this->version($tool, 4, 'draft');
        $this->usedBy($used);
        $this->as()->putJson(route('admin.prompts.update', $used), ['task_prompt' => 'Ditimpa'])->assertStatus(409);
        $this->assertStringNotContainsString('Ditimpa', $used->fresh()->task_prompt);
    }

    public function test_revise_creates_the_next_draft_and_leaves_the_source_untouched(): void
    {
        [, , $v2] = $this->scenario();

        $this->as()->postJson(route('admin.prompts.revise', $v2))->assertOk();

        $copy = PromptTemplate::query()->where('version', 4)->firstOrFail();
        $this->assertSame(['draft', $v2->task_prompt, $v2->name], [$copy->status, $copy->task_prompt, $copy->name]);
        $this->assertSame('active', $v2->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'prompt.duplicated', 'auditable_id' => $copy->id]);
    }

    public function test_activating_v3_then_v1_keeps_exactly_one_active_version_and_every_version(): void
    {
        [$tool, $v1, $v2, $v3] = $this->scenario();

        $this->as()->patchJson(route('admin.prompts.activate', $v3))->assertOk()->assertJsonPath('ok', true);

        $this->assertSame(['archived', 'archived', 'active'], [$v1->fresh()->status, $v2->fresh()->status, $v3->fresh()->status]);
        $this->assertSame($v3->id, $tool->fresh()->active_prompt_template_id);
        $this->assertSame(1, PromptTemplate::query()->where('ai_tool_id', $tool->id)->where('status', 'active')->count());
        $this->assertSame(3, PromptTemplate::query()->where('ai_tool_id', $tool->id)->count()); // tidak ada yang dihapus
        $this->assertDatabaseHas('audit_logs', ['action' => 'prompt.activated', 'auditable_id' => $v3->id]);

        // Kembali ke versi sebelumnya.
        $this->as()->patchJson(route('admin.prompts.activate', $v1))->assertOk();
        $this->assertSame(['active', 'archived', 'archived'], [$v1->fresh()->status, $v2->fresh()->status, $v3->fresh()->status]);
        $this->assertSame($v1->id, $tool->fresh()->active_prompt_template_id);

        // Mengaktifkan yang sudah aktif tidak menulis audit kedua.
        $count = fn () => \App\Models\AuditLog::query()->where('action', 'prompt.activated')->count();
        $before = $count();
        $this->as()->patchJson(route('admin.prompts.activate', $v1))->assertOk();
        $this->assertSame($before, $count());
    }

    public function test_old_runs_keep_pointing_at_the_exact_version_and_text_they_used(): void
    {
        [, , $v2, $v3] = $this->scenario();
        $run = $this->usedBy($v2);
        $text = $v2->task_prompt;

        $this->as()->patchJson(route('admin.prompts.activate', $v3))->assertOk();
        $this->as()->putJson(route('admin.prompts.update', $v2), ['task_prompt' => 'Mencoba menimpa'])->assertStatus(409);

        $run->refresh();
        $this->assertSame($v2->id, $run->prompt_template_id);
        $this->assertSame($text, $run->promptTemplate->task_prompt);
        $this->assertSame(2, $run->promptTemplate->version);
    }

    public function test_activating_an_instruction_never_reactivates_an_archived_tool(): void
    {
        $tool = $this->tool('trend-explorer', 'archived');
        $draft = $this->version($tool, 1, 'draft', 'Tren');

        $this->as()->patchJson(route('admin.prompts.activate', $draft))->assertOk()->assertJsonPath('message', fn ($m) => str_contains($m, 'masih diarsipkan'));

        $this->assertSame('archived', $tool->fresh()->status);
        $this->as()->get(route('admin.tools.index'))->assertDontSee('Siap dipakai</span>', false);

        // Pengguna tetap tidak melihatnya: prompt aktif dari alat arsip tidak masuk daftar riset.
        $visible = PromptTemplate::query()->where('status', 'active')->whereHas('aiTool', fn ($q) => $q->where('status', 'active'))->count();
        $this->assertSame(0, $visible);
    }

    public function test_an_instruction_without_a_tool_cannot_be_activated(): void
    {
        $orphan = PromptTemplate::create(['name' => 'Yatim', 'version' => 1, 'task_prompt' => 'x', 'status' => 'draft']);

        $this->as()->patchJson(route('admin.prompts.activate', $orphan))->assertStatus(422);
        $this->assertSame('draft', $orphan->fresh()->status);
    }

    public function test_archiving_a_draft_keeps_it_and_the_active_version_cannot_be_archived(): void
    {
        [, , $v2, $v3] = $this->scenario();

        $this->as()->patchJson(route('admin.prompts.archive', $v2))->assertStatus(409);
        $this->assertSame('active', $v2->fresh()->status);

        $this->as()->patchJson(route('admin.prompts.archive', $v3))->assertOk();
        $this->assertDatabaseHas('prompt_templates', ['id' => $v3->id, 'status' => 'archived']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'prompt.archived', 'auditable_id' => $v3->id]);
    }

    public function test_compare_shows_additions_and_removals_without_changing_data_and_escapes_text(): void
    {
        [$tool, , $v2, $v3] = $this->scenario();
        $v2->forceFill(['task_prompt' => "Satu\nDua lama\nTiga"])->save();
        $v3->forceFill(['task_prompt' => "Satu\nDua baru <script>alert(1)</script>\nTiga\nEmpat"])->save();
        $snapshot = PromptTemplate::query()->orderBy('id')->get()->toArray();

        $page = $this->as()->get(route('admin.prompts.index', ['instruksi' => $v3->id, 'v' => $v3->id, 'a' => $v2->id, 'b' => $v3->id]))
            ->assertOk()->assertSee('Hanya membaca')->assertSee('Ditambahkan')->assertSee('Dihapus')
            ->assertSee('<mark class="is-ins">', false)->assertSee('<mark class="is-del">', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);

        $this->assertStringContainsString('Empat', $page->getContent());
        $this->assertSame($snapshot, PromptTemplate::query()->orderBy('id')->get()->toArray()); // membandingkan tidak mengubah apa pun

        // Bandingkan dengan dirinya sendiri atau versi instruksi lain jatuh kembali ke tampilan biasa.
        $this->as()->get(route('admin.prompts.index', ['instruksi' => $v3->id, 'a' => $v3->id, 'b' => $v3->id]))->assertOk()->assertDontSee('Hanya membaca');
        $foreign = $this->version($this->tool('lain'), 1, 'draft', 'Lain');
        $this->as()->get(route('admin.prompts.index', ['instruksi' => $v3->id, 'a' => $foreign->id, 'b' => $v3->id]))->assertOk()->assertDontSee('Hanya membaca');
    }

    public function test_the_page_shows_the_tool_it_belongs_to_and_the_catalog_reads_the_new_active_version(): void
    {
        [$tool, , , $v3] = $this->scenario();

        $this->as()->get(route('admin.prompts.index', ['instruksi' => $v3->id]))->assertSee('Katalog Alat AI / '.$tool->name)->assertSee('Siap dipakai pengguna');

        $this->as()->patchJson(route('admin.prompts.activate', $v3))->assertOk();
        $this->as()->get(route('admin.tools.index'))->assertOk()->assertSee('v3');
    }

    public function test_legacy_routes_still_work_and_use_the_same_rules(): void
    {
        [$tool, , $v2, $v3] = $this->scenario();

        $this->as()->get(route('admin.prompt-versions.index', ['tool' => $tool->id]))->assertRedirect(route('admin.prompts.index', ['tool' => $tool->id]));
        $this->as()->get(route('admin.prompts.edit', $v2))->assertRedirect(route('admin.prompts.index', ['instruksi' => $v2->id, 'v' => $v2->id]));
        $this->as()->get(route('admin.prompts.create'))->assertRedirect(route('admin.prompts.index', ['baru' => 1]));

        $this->as()->patch(route('admin.prompt-versions.activate', $v3))->assertRedirect();
        $this->assertSame($v3->id, $tool->fresh()->active_prompt_template_id);
        $this->assertSame('archived', $v2->fresh()->status);

        $this->as()->post(route('admin.prompt-versions.duplicate', $v3))->assertRedirect();
        $this->assertSame(4, PromptTemplate::query()->max('version'));
    }

    public function test_sidebar_uses_the_new_labels_and_one_instruction_entry(): void
    {
        $page = $this->as()->get(route('admin.prompts.index'))->assertOk()->assertSee('Katalog Alat AI')->assertSee('Instruksi &amp; Versi', false);

        $this->assertStringNotContainsString('>Versi Instruksi<', $page->getContent());
        $this->assertStringNotContainsString('>Instruksi AI<', $page->getContent());
    }

    public function test_no_form_page_without_javascript_is_a_dead_end(): void
    {
        [, , , $v3] = $this->scenario();

        $this->as()->get(route('admin.prompts.index', ['baru' => 1]))->assertOk()->assertSee('Buat instruksi')->assertSee('Simpan sebagai draf');
        $this->as()->from(route('admin.prompts.index', ['instruksi' => $v3->id]))->put(route('admin.prompts.update', $v3), ['task_prompt' => ''])->assertSessionHasErrors('task_prompt');
        $this->as()->put(route('admin.prompts.update', $v3), ['task_prompt' => 'Tersimpan'])->assertRedirect()->assertSessionHas('success');
    }

    public function test_guests_cannot_read_or_change_anything(): void
    {
        [, , $v2, $v3] = $this->scenario();

        $this->get(route('admin.prompts.index'))->assertRedirect(route('admin.login'));
        $this->postJson(route('admin.prompts.store'), [])->assertRedirect(route('admin.login'));
        $this->putJson(route('admin.prompts.update', $v3), ['task_prompt' => 'x'])->assertRedirect(route('admin.login'));
        $this->postJson(route('admin.prompts.revise', $v2))->assertRedirect(route('admin.login'));
        $this->patchJson(route('admin.prompts.activate', $v3))->assertRedirect(route('admin.login'));
        $this->patchJson(route('admin.prompts.archive', $v3))->assertRedirect(route('admin.login'));
        $this->assertSame('draft', $v3->fresh()->status);
    }
}
