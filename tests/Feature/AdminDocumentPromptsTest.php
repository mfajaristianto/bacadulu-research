<?php

namespace Tests\Feature;

use App\Models\AiRun;
use App\Models\AiTool;
use App\Models\AuditLog;
use App\Models\DocumentPromptSection;
use App\Models\DocumentPromptTemplate;
use App\Models\PromptTemplate;
use App\Models\User;
use App\Services\Research\DocumentPromptPresets;
use App\Services\Research\DocumentPrompts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminDocumentPromptsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Migrasi menanam alat dan instruksi kanonik; himpunan ini dikosongkan agar angka tiap tes bisa dipastikan.
        PromptTemplate::query()->delete();
        AiTool::query()->delete();

        $this->admin = User::factory()->create();
    }

    private function as(): static
    {
        return $this->withSession(['research_admin_authenticated' => true, 'research_admin_user_id' => $this->admin->id]);
    }

    private function docs(): DocumentPrompts
    {
        return app(DocumentPrompts::class);
    }

    private function fajar(): DocumentPromptTemplate
    {
        return $this->docs()->importPreset(DocumentPromptPresets::find('book-chapter-fajar'), $this->admin->id);
    }

    private function section(DocumentPromptTemplate $t, string $key): DocumentPromptSection
    {
        return $t->sections()->where('key', $key)->firstOrFail();
    }

    private function draftOf(DocumentPromptSection $s, int $version = 1): PromptTemplate
    {
        return PromptTemplate::query()->where('name', $s->promptName())->where('version', $version)->firstOrFail();
    }

    private function url(string $suffix = ''): string
    {
        return '/'.config('research.admin_path').'/prompt-documents'.$suffix;
    }

    private function blank(array $over = []): array
    {
        return $over + ['name' => 'Riset Akademik', 'document_type' => 'research', 'language' => 'id', 'citation_style' => 'APA 7', 'qa_mode' => 'none'];
    }

    private function manual(string $name = 'Riset Akademik', string $type = 'research'): DocumentPromptTemplate
    {
        return DocumentPromptTemplate::create(['uuid' => (string) Str::uuid(), 'name' => $name, 'slug' => $this->docs()->slug($name), 'document_type' => $type, 'status' => 'draft']);
    }

    private function addSection(DocumentPromptTemplate $t, string $key, int $pos, array $over = []): DocumentPromptSection
    {
        return DocumentPromptSection::create(['uuid' => (string) Str::uuid(), 'document_prompt_template_id' => $t->id, 'key' => $key, 'title' => ucfirst($key), 'position' => $pos] + $over);
    }

    private function promptFor(DocumentPromptSection $s, int $version, string $status, string $task = 'Tulis bagian ini.'): PromptTemplate
    {
        $p = PromptTemplate::create(['ai_tool_id' => null, 'name' => $s->promptName(), 'version' => $version, 'status' => $status, 'task_prompt' => $task]);

        if ($status === 'active') {
            $s->update(['active_prompt_template_id' => $p->id]);
        }

        return $p;
    }

    /* ------------------------------------------------------------------------------------------ akses, sidebar */

    public function test_sidebar_has_prompt_documents_once_and_page_opens(): void
    {
        $html = $this->as()->get($this->url())->assertOk()->assertSee('Prompt Dokumen')->getContent();

        $this->assertSame(1, substr_count($html, 'href="'.url($this->url()).'"'), 'tautan sidebar muncul sekali');
        $this->assertStringContainsString('Kelola struktur dan instruksi penulisan', $html);
        $this->as()->get('/'.config('research.admin_path').'/prompts')->assertOk()->assertSee('Instruksi &amp; Versi', false);
        $this->as()->get('/'.config('research.admin_path').'/ai-providers')->assertOk()->assertSee('Prompt Dokumen');
    }

    public function test_guests_cannot_reach_any_route(): void
    {
        $t = $this->fajar();
        $s = $this->section($t, 'pendahuluan');
        $p = $this->draftOf($s);

        foreach ([['get', ''], ['get', "/{$t->id}"], ['post', ''], ['post', '/import'], ['put', "/{$t->id}"], ['post', "/{$t->id}/status"], ['post', "/{$t->id}/duplicate"],
            ['post', "/{$t->id}/sections/{$s->id}/prompt/{$p->id}/activate"], ['put', "/{$t->id}/sections/{$s->id}/prompt/{$p->id}"]] as [$verb, $path]) {
            $this->{$verb}($this->url($path))->assertRedirect();
        }

        $this->assertSame('draft', $p->refresh()->status);
    }

    /* ----------------------------------------------------------------------------------------------- daftar */

    public function test_empty_state_has_no_invented_data(): void
    {
        $this->as()->get($this->url())->assertOk()->assertSee('Belum ada template prompt')->assertSee('Buat template pertama')->assertDontSee('bd-ix__item', false);

        $this->assertSame(0, DocumentPromptTemplate::count());
    }

    public function test_list_filters_search_sort_and_computed_stats(): void
    {
        $fajar = $this->fajar();
        $this->manual('Artikel Ilmiah', 'article');
        $archived = $this->manual('Buku Akademik', 'book');
        $archived->update(['status' => 'archived']);

        $page = $this->as()->get($this->url())->assertOk();
        $page->assertSee('Book Chapter Akademik — Fajar')->assertSee('Artikel Ilmiah')->assertSee('Buku Akademik');
        $page->assertSeeInOrder(['Total template', '3']);

        $this->as()->get($this->url('?search=artikel'))->assertSee('Artikel Ilmiah')->assertDontSee('Book Chapter Akademik');
        $this->as()->get($this->url('?jenis=book'))->assertSee('Buku Akademik')->assertDontSee('Artikel Ilmiah');
        $this->as()->get($this->url('?status=archived'))->assertSee('Buku Akademik')->assertDontSee('Book Chapter Akademik');
        $this->as()->get($this->url('?status=draft'))->assertSee('Book Chapter Akademik')->assertDontSee('Buku Akademik');
        $this->as()->get($this->url('?sort=name'))->assertOk();
        $this->as()->get($this->url('?search=tidak-ada-yang-cocok'))->assertSee('Tidak ada template yang cocok');
        $this->as()->get($this->url('?status=ready'))->assertOk()->assertSee('Tidak ada template yang cocok');
        $this->as()->get($this->url('?jenis=bukan-jenis'))->assertSessionHasErrors('jenis');
        $this->assertNotNull($fajar->id);
    }

    /* ---------------------------------------------------------------------------------------- buat template */

    public function test_create_template_is_draft_and_persists_after_reload(): void
    {
        $this->as()->postJson($this->url(), $this->blank(['description' => 'Untuk riset.']))->assertOk()->assertJson(['ok' => true]);

        $t = DocumentPromptTemplate::firstOrFail();
        $this->assertSame('draft', $t->status);
        $this->assertNull($t->activated_at);
        $this->assertSame('riset-akademik', $t->slug);
        $this->assertSame($this->admin->id, $t->created_by);

        // "buka ulang" = permintaan baru; data harus datang dari database.
        $this->as()->get($this->url("/{$t->id}"))->assertOk()->assertSee('Riset Akademik')->assertSee('Untuk riset.')->assertSee('Template belum punya bagian');
        $this->assertDatabaseHas('audit_logs', ['action' => 'document_prompt.template.created', 'auditable_id' => $t->id]);
    }

    public function test_template_validation_and_duplicate_names(): void
    {
        $this->as()->postJson($this->url(), $this->blank())->assertOk();

        $dup = $this->as()->postJson($this->url(), $this->blank(['name' => '  riset   AKADEMIK ']))->assertStatus(422);
        $this->assertStringContainsString('Sudah ada template bernama', $dup->json('message'));
        $this->assertNotEmpty($dup->json('existing.url'));

        $this->as()->postJson($this->url(), $this->blank(['name' => '']))->assertStatus(422)->assertJsonValidationErrors('name');
        $this->as()->postJson($this->url(), $this->blank(['name' => '—!—']))->assertStatus(422);
        $this->as()->postJson($this->url(), $this->blank(['name' => 'Lain', 'document_type' => 'other']))->assertStatus(422)->assertJsonValidationErrors('type_label');
        $this->as()->postJson($this->url(), $this->blank(['name' => 'Lain', 'document_type' => 'tesis']))->assertStatus(422)->assertJsonValidationErrors('document_type');
        $this->as()->postJson($this->url(), $this->blank(['name' => 'Lain', 'citation_style' => 'Karangan']))->assertStatus(422)->assertJsonValidationErrors('citation_style');
        $this->as()->postJson($this->url(), $this->blank(['name' => 'Skripsi', 'document_type' => 'other', 'type_label' => 'Skripsi']))->assertOk();

        $this->assertSame(2, DocumentPromptTemplate::count());
    }

    public function test_update_template_settings_keeps_unique_names_and_status(): void
    {
        $a = $this->manual('A');
        $b = $this->manual('B');

        $this->as()->putJson($this->url("/{$a->id}"), $this->blank(['name' => 'B']))->assertStatus(422);
        $this->as()->putJson($this->url("/{$a->id}"), $this->blank(['name' => 'A baru', 'qa_mode' => 'appendix', 'status' => 'active']))->assertOk();

        $a->refresh();
        $this->assertSame('A baru', $a->name);
        $this->assertSame('appendix', $a->qa_mode);
        $this->assertSame('draft', $a->status, 'status tidak bisa diubah lewat form pengaturan');
        $this->assertSame('B', $b->refresh()->name);
    }

    /* ----------------------------------------------------------------------------------- impor template Fajar */

    public function test_preview_page_shows_full_content_and_writes_nothing(): void
    {
        $page = $this->as()->get($this->url('?impor=book-chapter-fajar'))->assertOk();
        $page->assertSee('Book Chapter Akademik — Fajar')->assertSee('Simpan sebagai Draf')->assertSee('Fenomena gap')->assertSee('minimal 1.000 kata', false);

        $this->assertSame(0, DocumentPromptTemplate::count());
        $this->assertSame(0, PromptTemplate::count());
        $this->as()->get($this->url('?impor=tidak-ada'))->assertNotFound();
    }

    public function test_import_creates_draft_template_with_draft_prompts_and_nothing_active(): void
    {
        $this->as()->postJson($this->url('/import'), ['preset' => 'book-chapter-fajar'])->assertOk()->assertJson(['ok' => true]);

        $t = DocumentPromptTemplate::firstOrFail();
        $this->assertSame('Book Chapter Akademik — Fajar', $t->name);
        $this->assertSame('book_chapter', $t->document_type);
        $this->assertSame('draft', $t->status);
        $this->assertNull($t->activated_at);
        $this->assertSame('book-chapter-fajar', $t->source_preset);
        $this->assertSame(['pendahuluan', 'rumusan-masalah', 'tujuan-penelitian', 'hasil-dan-pembahasan', 'kesimpulan-dan-saran', 'daftar-pustaka', 'pertanyaan-dan-jawaban'], $t->sections->pluck('key')->all());
        $this->assertSame(range(1, 7), $t->sections->pluck('position')->all());

        foreach ($t->sections as $s) {
            $this->assertNull($s->active_prompt_template_id, $s->key.' tidak aktif otomatis');
            $p = $this->draftOf($s);
            $this->assertSame('draft', $p->status);
            $this->assertNull($p->ai_tool_id);
            $this->assertNotSame('', trim($p->task_prompt));
        }

        $this->assertSame(0, PromptTemplate::where('status', 'active')->count());
        $this->assertSame(7, PromptTemplate::count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'document_prompt.template.imported', 'auditable_id' => $t->id]);

        $dup = $this->as()->postJson($this->url('/import'), ['preset' => 'book-chapter-fajar'])->assertStatus(422);
        $this->assertStringContainsString('sudah ada', $dup->json('message'));
        $this->assertSame(1, DocumentPromptTemplate::count());
        $this->assertSame(7, PromptTemplate::count());
    }

    public function test_fajar_word_limits_and_rules_match_the_source_document(): void
    {
        $t = $this->fajar();

        $this->assertSame(1000, $this->section($t, 'pendahuluan')->min_words);
        $this->assertSame(2000, $this->section($t, 'hasil-dan-pembahasan')->min_words);
        $this->assertSame(500, $this->section($t, 'kesimpulan-dan-saran')->max_words);
        $this->assertSame(5, $this->section($t, 'pertanyaan-dan-jawaban')->item_count);
        $this->assertSame('references', $this->section($t, 'daftar-pustaka')->role);
        $this->assertSame('qa', $this->section($t, 'pertanyaan-dan-jawaban')->role);
        $this->assertFalse($this->section($t, 'pertanyaan-dan-jawaban')->is_required);
        $this->assertSame('APA 7', $t->citation_style);

        $intro = $this->draftOf($this->section($t, 'pendahuluan'))->task_prompt;

        foreach (['dua paragraf', 'Fenomena gap', 'Urgensi penelitian', 'Penelitian terdahulu', 'Rumusan masalah', 'Tujuan penelitian', 'Implikasi praktis dan akademik', 'Keterbaruan'] as $needle) {
            $this->assertStringContainsStringIgnoringCase($needle, $intro);
        }
    }

    public function test_fajar_prompts_are_reusable_and_use_only_known_placeholders(): void
    {
        $t = $this->fajar();
        $known = array_keys(DocumentPrompts::PLACEHOLDERS);

        foreach ($t->sections as $s) {
            $p = $this->draftOf($s);
            $used = $this->docs()->placeholdersIn($p->only(DocumentPrompts::PROMPT_FIELDS));

            $this->assertSame([], array_diff($used, $known), $s->key.' memakai placeholder tidak dikenal');
            $this->assertSame([], $this->docs()->promptProblems($p), $s->key.' valid');

            foreach (['syariah', 'maqasid', 'fajar', 'keuangan'] as $topic) {
                $this->assertStringNotContainsStringIgnoringCase($topic, implode(' ', $p->only(DocumentPrompts::PROMPT_FIELDS)), $s->key.' tidak boleh mengunci topik "'.$topic.'"');
            }
        }

        $this->assertStringContainsString('{research_questions}', $this->draftOf($this->section($t, 'rumusan-masalah'))->task_prompt);
        $this->assertStringContainsString('{selected_sources}', $this->draftOf($this->section($t, 'hasil-dan-pembahasan'))->retrieval_instructions);
        $this->assertStringContainsStringIgnoringCase('kajian konseptual', $this->draftOf($this->section($t, 'hasil-dan-pembahasan'))->output_instructions);
        $this->assertStringContainsString('Syariah', $t->example_json['title'], 'judul Fajar hanya menjadi metadata contoh');
    }

    /* ------------------------------------------------------------------------------------------- editor */

    public function test_editor_shows_sections_versions_and_selected_prompt(): void
    {
        $t = $this->fajar();

        $page = $this->as()->get($this->url("/{$t->id}?bagian=hasil-dan-pembahasan"))->assertOk();
        $page->assertSee('Hasil dan Pembahasan')->assertSee('Draf v1, belum aktif')->assertSee('Minimal 2.000', false)->assertSee('{research_questions}', false)
            ->assertSee('Pratinjau Struktur')->assertSee('Salin')->assertSee('Aktifkan template')->assertSee('hal perlu dilengkapi');

        $this->as()->get($this->url("/{$t->id}?bagian=tidak-ada"))->assertOk()->assertSee('Pendahuluan');
        $this->as()->get($this->url("/{$t->id}?bagian=pendahuluan&tab=konfigurasi"))->assertOk()->assertSee('Jumlah kata')->assertSee('Bagian prasyarat');
        $this->as()->get($this->url("/{$t->id}?bagian=pendahuluan&tab=versi"))->assertOk()->assertSee('Riwayat perubahan bagian ini');
        $this->as()->get($this->url('/999999'))->assertNotFound();
    }

    public function test_preview_structure_lists_everything_without_inventing_text(): void
    {
        $t = $this->fajar();
        $html = $this->as()->get($this->url("/{$t->id}"))->getContent();

        $this->assertStringContainsString('Pratinjau struktur', $html);
        $this->assertStringContainsString('tidak ada naskah contoh dan tidak ada panggilan AI', $html);
        $this->assertStringContainsString('Maksimal 500 kata', $html);
        $this->assertStringContainsString('5 butir', $html);
        $this->assertStringContainsString('Membutuhkan: Rumusan Masalah, Tujuan Penelitian', $html);
        $this->assertStringContainsString('Opsional', $html);
    }

    public function test_user_content_is_escaped_everywhere(): void
    {
        $t = $this->manual('<script>alert(1)</script> Tpl');
        $s = $this->addSection($t, 'x', 1, ['title' => '<img src=x onerror=alert(2)>']);
        $this->promptFor($s, 1, 'draft', '<script>alert(3)</script> {research_topic}');

        $html = $this->as()->get($this->url("/{$t->id}?bagian=x"))->assertOk()->getContent();
        $this->assertStringNotContainsString('<script>alert', $html);
        $this->assertStringNotContainsString('<img src=x onerror', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(3)', $html);

        $this->as()->get($this->url())->assertDontSee('<script>alert(1)', false);
    }

    /* ------------------------------------------------------------------------------- bagian: tambah, ubah */

    public function test_add_section_appends_unique_key_and_rejects_duplicate_titles(): void
    {
        $t = $this->manual();

        $this->as()->postJson($this->url("/{$t->id}/sections"), ['title' => 'Latar Belakang', 'role' => 'body'])->assertOk();
        $this->as()->postJson($this->url("/{$t->id}/sections"), ['title' => 'Pembahasan Umum', 'role' => 'body', 'is_required' => '0'])->assertOk();
        $this->as()->postJson($this->url("/{$t->id}/sections"), ['title' => 'latar   belakang', 'role' => 'body'])->assertStatus(422)->assertJsonValidationErrors('title');
        $this->as()->postJson($this->url("/{$t->id}/sections"), ['title' => '', 'role' => 'body'])->assertStatus(422);
        $this->as()->postJson($this->url("/{$t->id}/sections"), ['title' => 'Salah', 'role' => 'rahasia'])->assertStatus(422);

        $this->assertSame(['latar-belakang', 'pembahasan-umum'], $t->sections()->pluck('key')->all());
        $this->assertSame([1, 2], $t->sections()->pluck('position')->all());
        $this->assertFalse($t->sections()->where('key', 'pembahasan-umum')->first()->is_required);
        $this->assertNull($t->sections()->first()->active_prompt_template_id);
        $this->assertSame(0, PromptTemplate::count(), 'menambah bagian tidak membuat prompt');
    }

    public function test_section_config_validation_word_limits_and_dependencies(): void
    {
        $t = $this->manual();
        $a = $this->addSection($t, 'a', 1);
        $b = $this->addSection($t, 'b', 2);
        $c = $this->addSection($t, 'c', 3);
        $base = ['title' => 'B', 'role' => 'body', 'is_required' => '1', 'is_enabled' => '1'];
        $put = fn ($over) => $this->as()->putJson($this->url("/{$t->id}/sections/{$b->id}"), $over + $base);

        $put(['min_words' => -5])->assertStatus(422)->assertJsonValidationErrors('min_words');
        $put(['max_words' => -1])->assertStatus(422);
        $put(['min_words' => 'abc'])->assertStatus(422);
        $put(['min_words' => 500, 'max_words' => 100])->assertStatus(422)->assertJsonValidationErrors('min_words');
        $put(['min_words' => 100, 'max_words' => 200, 'target_words' => 999])->assertStatus(422)->assertJsonValidationErrors('target_words');
        $put(['depends_on' => ['c']])->assertStatus(422)->assertJsonValidationErrors('depends_on');
        $put(['depends_on' => ['ghost']])->assertStatus(422)->assertJsonValidationErrors('depends_on');
        $put(['title' => 'A'])->assertStatus(422)->assertJsonValidationErrors('title');
        $this->assertNull($b->refresh()->min_words);

        $put(['min_words' => 1000, 'max_words' => null, 'depends_on' => ['a'], 'requirements' => "Satu\r\nDua", 'description' => 'D', 'item_count' => 5])->assertOk();
        $b->refresh();
        $this->assertSame(1000, $b->min_words);
        $this->assertNull($b->max_words);
        $this->assertSame(['a'], $b->depends_on);
        $this->assertSame("Satu\nDua", $b->requirements);
        $this->assertSame(5, $b->item_count);

        $put(['min_words' => 0, 'max_words' => 0])->assertOk();
        $this->assertSame(0, $b->refresh()->max_words, 'nol adalah batas yang sah, bukan "tanpa batas"');

        $put(['is_enabled' => '0', 'is_required' => '0'])->assertOk();
        $this->assertFalse($b->refresh()->is_enabled);
        $this->assertFalse($b->is_required);
        $this->assertDatabaseHas('audit_logs', ['action' => 'document_prompt.section.updated', 'auditable_id' => $t->id]);
        $this->assertNotNull($a->id.$c->id);
    }

    public function test_move_sections_and_reject_orders_that_break_dependencies(): void
    {
        $t = $this->manual();
        $a = $this->addSection($t, 'a', 1);
        $b = $this->addSection($t, 'b', 2, ['depends_on' => ['a']]);
        $c = $this->addSection($t, 'c', 3);
        $move = fn ($s, $dir) => $this->as()->postJson($this->url("/{$t->id}/sections/{$s->id}/move"), ['direction' => $dir]);

        $move($b, 'up')->assertStatus(422);
        $this->assertSame(['a', 'b', 'c'], $t->sections()->pluck('key')->all());

        $move($c, 'up')->assertOk();
        $this->assertSame(['a', 'c', 'b'], $t->sections()->pluck('key')->all());
        $this->assertSame([1, 2, 3], $t->sections()->pluck('position')->all());

        $this->assertStringContainsString('ujung', $move($a, 'up')->json('message'));
        $move($a, 'sideways')->assertStatus(422);
        $this->assertNotNull($a->id);
    }

    /* ------------------------------------------------------------------------------ versi dan aktivasi */

    public function test_create_first_prompt_then_edit_draft_and_count_words_consistently(): void
    {
        $t = $this->manual();
        $s = $this->addSection($t, 'a', 1);
        $base = $this->url("/{$t->id}/sections/{$s->id}/prompt");

        $this->as()->postJson($base, ['task_prompt' => ''])->assertStatus(422)->assertJsonValidationErrors('task_prompt');
        $this->as()->postJson($base, ['task_prompt' => 'Tulis {research_topic}.', 'retrieval_instructions' => "Pakai {selected_sources}.\r\nJangan mengarang."])->assertOk();

        $v1 = $this->draftOf($s);
        $this->assertSame('draft', $v1->status);
        $this->assertNull($v1->ai_tool_id);
        $this->assertSame($s->promptName(), $v1->name);
        $this->assertSame("Pakai {selected_sources}.\nJangan mengarang.", $v1->retrieval_instructions);
        $this->assertNull($s->refresh()->active_prompt_template_id);

        $this->as()->postJson($base, ['task_prompt' => 'lagi'])->assertStatus(422);
        $this->as()->putJson("{$base}/{$v1->id}", ['task_prompt' => 'Ubah {research_topic} sekarang.'])->assertOk();
        $this->assertSame('Ubah {research_topic} sekarang.', $v1->refresh()->task_prompt);
        $this->assertSame(1, PromptTemplate::count());

        $this->assertSame(4, $this->docs()->wordCount("Hak-hak asasi 3 kata's"));
        $this->assertSame(0, $this->docs()->wordCount(''));
        $this->assertSame(5, $this->docs()->wordCount("Satu, dua; tiga!\nempat\tlima"));
    }

    public function test_unknown_placeholders_are_rejected_and_never_executed(): void
    {
        $t = $this->manual();
        $s = $this->addSection($t, 'a', 1);
        $base = $this->url("/{$t->id}/sections/{$s->id}/prompt");

        $bad = $this->as()->postJson($base, ['task_prompt' => 'Halo {nama_rahasia} dan {research_topic}'])->assertStatus(422);
        $this->assertStringContainsString('{nama_rahasia}', $bad->json('message'));
        $this->assertSame(0, PromptTemplate::count());

        // Bukan placeholder (bukan identifier): tidak dianggap placeholder.
        $this->as()->postJson($base, ['task_prompt' => 'JSON seperti {"a": 1} dan {} serta {{x}} aman. {research_topic}'])->assertStatus(422);
        $this->as()->postJson($base, ['task_prompt' => 'JSON seperti {"a": 1} dan {} aman. {research_topic}'])->assertOk();

        // Versi yang lolos ke database lewat jalur lain tetap tidak bisa diaktifkan.
        $p = PromptTemplate::query()->where('name', $s->promptName())->firstOrFail();
        $p->forceFill(['task_prompt' => 'Rusak {tidak_ada}'])->save();
        $this->as()->postJson("{$base}/{$p->id}/activate")->assertStatus(422);
        $this->assertSame('draft', $p->refresh()->status);
    }

    public function test_activating_one_section_never_touches_another_and_old_versions_are_kept(): void
    {
        $t = $this->fajar();
        $intro = $this->section($t, 'pendahuluan');
        $disc = $this->section($t, 'hasil-dan-pembahasan');
        $act = fn ($s, $p) => $this->as()->postJson($this->url("/{$t->id}/sections/{$s->id}/prompt/{$p->id}/activate"));

        $act($intro, $this->draftOf($intro))->assertOk();
        $act($disc, $this->draftOf($disc))->assertOk();

        $this->assertSame('active', $this->draftOf($intro)->status);
        $this->assertSame($this->draftOf($intro)->id, $intro->refresh()->active_prompt_template_id);
        $this->assertSame('draft', $this->draftOf($this->section($t, 'rumusan-masalah'))->status, 'bagian lain tetap draf');
        $this->assertNull($this->section($t, 'rumusan-masalah')->active_prompt_template_id);

        // revisi pendahuluan: v2 draf; hasil & pembahasan tidak ikut berubah
        $revise = $this->as()->postJson($this->url("/{$t->id}/sections/{$intro->id}/prompt/{$this->draftOf($intro)->id}/revise"))->assertOk();
        $this->assertStringContainsString('v2', $revise->json('message'));
        $v2 = $this->draftOf($intro, 2);
        $this->assertSame('draft', $v2->status);
        $this->assertSame($this->draftOf($intro)->task_prompt, $v2->task_prompt);
        $this->assertSame('active', $this->draftOf($intro)->status, 'revisi tidak mengubah versi aktif');

        $act($intro, $v2)->assertOk();
        $this->assertSame('archived', $this->draftOf($intro, 1)->status, 'v1 diarsipkan, bukan dihapus');
        $this->assertSame('active', $v2->refresh()->status);
        $this->assertSame($v2->id, $intro->refresh()->active_prompt_template_id);
        $this->assertSame('active', $this->draftOf($disc)->status, 'aktivasi pendahuluan tidak menyentuh pembahasan');
        $this->assertSame(1, PromptTemplate::where('name', $disc->promptName())->count());

        // rollback ke versi lama
        $act($intro, $this->draftOf($intro, 1))->assertOk();
        $this->assertSame('active', $this->draftOf($intro, 1)->status);
        $this->assertSame('archived', $v2->refresh()->status);
        $this->assertSame(2, PromptTemplate::where('name', $intro->promptName())->count(), 'tidak ada versi yang terhapus');
        $this->assertSame(1, PromptTemplate::where('name', $intro->promptName())->where('status', 'active')->count(), 'satu versi aktif per bagian');

        $again = $act($intro, $this->draftOf($intro, 1))->assertOk();
        $this->assertStringContainsString('sudah aktif', $again->json('message'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'document_prompt.prompt.activated', 'auditable_id' => $t->id]);
    }

    public function test_active_archived_and_used_versions_are_locked_but_revisable(): void
    {
        $t = $this->fajar();
        $s = $this->section($t, 'pendahuluan');
        $v1 = $this->draftOf($s);
        $url = $this->url("/{$t->id}/sections/{$s->id}/prompt/{$v1->id}");

        $this->as()->postJson("{$url}/activate")->assertOk();
        $original = $v1->refresh()->task_prompt;

        $locked = $this->as()->putJson($url, ['task_prompt' => 'Timpa diam-diam'])->assertStatus(409);
        $this->assertStringContainsString('dikunci', $locked->json('message'));
        $this->assertSame($original, $v1->refresh()->task_prompt);

        $this->as()->postJson("{$url}/archive")->assertStatus(409);

        $v2 = $this->as()->postJson("{$url}/revise")->assertOk();
        $this->assertNotEmpty($v2->json('url'));
        $v2Row = $this->draftOf($s, 2);
        $this->as()->postJson($this->url("/{$t->id}/sections/{$s->id}/prompt/{$v2Row->id}/archive"))->assertOk();
        $this->assertSame('archived', $v2Row->refresh()->status);
        $this->as()->putJson($this->url("/{$t->id}/sections/{$s->id}/prompt/{$v2Row->id}"), ['task_prompt' => 'x'])->assertStatus(409);

        // draf yang pernah dipakai sebuah run juga dikunci
        $v3 = $this->docs()->revise($s, $v1, $this->admin->id);
        AiRun::create(['uuid' => (string) Str::uuid(), 'user_id' => $this->admin->id, 'prompt_template_id' => $v3->id, 'status' => 'completed']);
        $this->as()->putJson($this->url("/{$t->id}/sections/{$s->id}/prompt/{$v3->id}"), ['task_prompt' => 'x'])->assertStatus(409)->assertJsonFragment(['message' => 'Versi ini sudah pernah dipakai riset, jadi isinya dikunci. Buat revisi untuk mengubahnya.']);
    }

    public function test_a_prompt_cannot_be_driven_through_a_different_section_or_template(): void
    {
        $t = $this->fajar();
        $other = $this->fajar2();
        $intro = $this->section($t, 'pendahuluan');
        $disc = $this->section($t, 'hasil-dan-pembahasan');
        $p = $this->draftOf($intro);

        $this->as()->postJson($this->url("/{$t->id}/sections/{$disc->id}/prompt/{$p->id}/activate"))->assertNotFound();
        $this->as()->putJson($this->url("/{$t->id}/sections/{$disc->id}/prompt/{$p->id}"), ['task_prompt' => 'x'])->assertNotFound();
        $this->as()->postJson($this->url("/{$other->id}/sections/{$intro->id}/prompt/{$p->id}/activate"))->assertNotFound();
        $this->as()->postJson($this->url("/{$t->id}/sections/{$intro->id}/move"), ['direction' => 'down'])->assertOk();
        $this->assertSame('draft', $p->refresh()->status);
        $this->assertNull($disc->refresh()->active_prompt_template_id);
    }

    private function fajar2(): DocumentPromptTemplate
    {
        return $this->docs()->importPreset(['name' => 'Lain'] + DocumentPromptPresets::find('book-chapter-fajar'), $this->admin->id);
    }

    public function test_compare_versions_is_read_only_and_escaped(): void
    {
        $t = $this->manual();
        $s = $this->addSection($t, 'a', 1);
        $one = $this->promptFor($s, 1, 'active', "Baris satu\nBaris dua");
        $two = $this->promptFor($s, 2, 'draft', "Baris satu\nBaris <b>tiga</b>");

        $html = $this->as()->get($this->url("/{$t->id}?bagian=a&tab=versi&a={$one->id}&b={$two->id}"))->assertOk()->assertSee('Hanya membaca')->getContent();
        $this->assertStringContainsString('&lt;b&gt;', $html);
        $this->assertStringNotContainsString('<b>tiga', $html);
        $this->assertSame('Baris dua', $one->refresh()->task_prompt === "Baris satu\nBaris dua" ? 'Baris dua' : 'berubah');
    }

    /* --------------------------------------------------------------------------------------- kesiapan */

    public function test_readiness_blocks_activation_until_required_sections_have_active_prompts(): void
    {
        $t = $this->fajar();
        $status = fn ($to) => $this->as()->postJson($this->url("/{$t->id}/status"), ['to' => $to]);

        $blocked = $status('active')->assertStatus(422);
        $this->assertStringContainsString('belum bisa diaktifkan', $blocked->json('message'));
        $this->assertSame('draft', $t->refresh()->status);

        $r = $this->docs()->readiness($t->refresh());
        $this->assertCount(6, $r['issues'], 'enam bagian wajib belum punya versi aktif');
        $this->assertTrue($r['contract']);
        $this->assertFalse($r['ready']);
        $this->assertSame('draft', $this->docs()->state($t, $r)['key']);

        $this->as()->postJson($this->url("/{$t->id}/activate-drafts"))->assertOk();
        $this->assertSame(7, PromptTemplate::where('status', 'active')->count());
        $this->assertSame([], $this->docs()->readiness($t->refresh())['issues']);
        $this->assertSame('draft', $t->status, 'mengaktifkan draf tidak mengaktifkan template');

        $this->as()->postJson($this->url("/{$t->id}/activate-drafts"))->assertOk()->assertJsonFragment(['message' => 'Tidak ada draf yang bisa diaktifkan. Bagian yang sudah aktif tidak diubah.']);

        $status('active')->assertOk();
        $t->refresh();
        $this->assertSame('active', $t->status);
        $this->assertNotNull($t->activated_at);
        $this->assertSame('ready', $this->docs()->state($t, $this->docs()->readiness($t))['key']);
        $this->as()->get($this->url())->assertSee('Siap digunakan');
        $this->as()->get($this->url('?status=ready'))->assertSee('Book Chapter Akademik — Fajar');
    }

    public function test_active_template_becomes_incomplete_when_structure_changes(): void
    {
        $t = $this->fajar();
        $this->docs()->activateDrafts($t);
        $this->as()->postJson($this->url("/{$t->id}/status"), ['to' => 'active'])->assertOk();

        $this->as()->postJson($this->url("/{$t->id}/sections"), ['title' => 'Metode', 'role' => 'body'])->assertOk();

        $r = $this->docs()->readiness($t->refresh());
        $this->assertSame('incomplete', $this->docs()->state($t, $r)['key']);
        $this->assertSame('Metode', collect($r['issues'])->first()['section'] === 'metode' ? 'Metode' : '?');
        $this->assertSame('active', $t->status, 'status tersimpan tidak berubah diam-diam');
        $this->as()->get($this->url('?status=incomplete'))->assertSee('Perlu dilengkapi');
    }

    public function test_readiness_checks_dependencies_previous_section_and_disabled_sections(): void
    {
        $t = $this->manual();
        $a = $this->addSection($t, 'a', 1);
        $b = $this->addSection($t, 'b', 2);
        $this->promptFor($a, 1, 'active', 'Tulis {research_topic}.');
        $this->promptFor($b, 1, 'active', 'Lanjutkan {previous_section}.');

        $messages = fn () => collect($this->docs()->readiness($t->refresh())['issues'])->pluck('message')->implode(' | ');

        $this->assertStringContainsString('belum menentukan bagian prasyarat', $messages());

        $b->update(['depends_on' => ['a']]);
        $this->assertSame('', $messages());

        $a->update(['is_enabled' => false]);
        $this->assertStringContainsString('dinonaktifkan', $messages());

        $a->update(['is_enabled' => true, 'position' => 3]);
        $this->assertStringContainsString('harus berada setelah', $messages());

        $t->sections()->update(['is_enabled' => false]);
        $this->assertStringContainsString('Belum ada bagian yang aktif', $messages());
    }

    public function test_optional_and_qa_sections_follow_template_settings(): void
    {
        $t = $this->manual();
        $a = $this->addSection($t, 'a', 1);
        $qa = $this->addSection($t, 'qa', 2, ['role' => 'qa', 'is_required' => true]);
        $opt = $this->addSection($t, 'opsi', 3, ['is_required' => false]);
        $this->promptFor($a, 1, 'active');

        $t->update(['qa_mode' => 'none']);
        $r = $this->docs()->readiness($t->refresh());
        $this->assertSame([], $r['issues'], 'Q&A tidak dipakai dan bagian opsional tanpa prompt bukan kewajiban');
        $this->assertCount(2, $r['notes']);

        $t->update(['qa_mode' => 'appendix']);
        $this->assertCount(1, $this->docs()->readiness($t->refresh())['issues'], 'Q&A dipakai dan wajib: perlu prompt');

        $contract = $this->docs()->contract($t->refresh());
        $this->assertCount(3, $contract['sections']);
        $this->assertTrue(collect($contract['sections'])->firstWhere('key', 'qa')['included']);
        $t->update(['qa_mode' => 'none']);
        $this->assertFalse(collect($this->docs()->contract($t->refresh())['sections'])->firstWhere('key', 'qa')['included']);
        $this->assertNotNull($qa->id.$opt->id);
    }

    public function test_unsupported_document_types_are_never_labelled_ready(): void
    {
        $t = $this->manual('Artikel Ilmiah', 'article');
        $a = $this->addSection($t, 'a', 1);
        $this->promptFor($a, 1, 'active');

        $this->as()->postJson($this->url("/{$t->id}/status"), ['to' => 'active'])->assertOk();
        $r = $this->docs()->readiness($t->refresh());

        $this->assertSame([], $r['issues']);
        $this->assertFalse($r['contract']);
        $this->assertFalse($r['ready']);
        $this->assertSame('unsupported', $this->docs()->state($t, $r)['key']);
        $this->as()->get($this->url('?status=ready'))->assertDontSee('Artikel Ilmiah');
    }

    public function test_archive_and_restore_template_keeps_all_versions(): void
    {
        $t = $this->fajar();
        $count = PromptTemplate::count();

        $this->as()->postJson($this->url("/{$t->id}/status"), ['to' => 'archived'])->assertOk();
        $this->assertSame('archived', $t->refresh()->status);
        $this->assertSame($count, PromptTemplate::count());
        $this->as()->postJson($this->url("/{$t->id}/status"), ['to' => 'active'])->assertStatus(422);
        $this->as()->get($this->url('?status=archived'))->assertSee('Diarsipkan');

        $this->as()->postJson($this->url("/{$t->id}/status"), ['to' => 'draft'])->assertOk();
        $this->assertSame('draft', $t->refresh()->status);
        $this->as()->postJson($this->url("/{$t->id}/status"), ['to' => 'meledak'])->assertStatus(422);
    }

    /* --------------------------------------------------------------------------------------------- salin */

    public function test_duplicate_makes_independent_draft_copy(): void
    {
        $t = $this->fajar();
        $this->docs()->activateDrafts($t);
        $this->as()->postJson($this->url("/{$t->id}/status"), ['to' => 'active'])->assertOk();
        $before = PromptTemplate::count();

        $this->as()->postJson($this->url("/{$t->id}/duplicate"))->assertOk();
        $this->as()->postJson($this->url("/{$t->id}/duplicate"))->assertOk();

        $copies = DocumentPromptTemplate::where('id', '!=', $t->id)->orderBy('id')->get();
        $this->assertCount(2, $copies);
        $this->assertSame('Salinan dari Book Chapter Akademik — Fajar', $copies[0]->name);
        $this->assertSame('Salinan dari Book Chapter Akademik — Fajar (2)', $copies[1]->name);
        $this->assertSame('draft', $copies[0]->status);
        $this->assertNull($copies[0]->source_preset);
        $this->assertCount(7, $copies[0]->sections);
        $this->assertSame($before + 14, PromptTemplate::count());

        foreach ($copies[0]->sections as $cs) {
            $this->assertNull($cs->active_prompt_template_id, 'salinan tidak mengaktifkan apa pun');
            $this->assertNotSame($this->section($t, $cs->key)->promptName(), $cs->promptName(), 'identitas instruksi baru');
            $this->assertSame('draft', $this->draftOf($cs)->status);
            $this->assertSame($this->draftOf($this->section($t, $cs->key))->task_prompt, $this->draftOf($cs)->task_prompt);
        }

        $this->assertSame('active', $t->refresh()->status, 'sumber tidak berubah');
        $this->assertSame(7, PromptTemplate::where('status', 'active')->count());

        // mengaktifkan di salinan tidak menyentuh sumber
        $cs = $copies[0]->sections->first();
        $this->as()->postJson($this->url("/{$copies[0]->id}/sections/{$cs->id}/prompt/{$this->draftOf($cs)->id}/activate"))->assertOk();
        $this->assertSame(8, PromptTemplate::where('status', 'active')->count());
    }

    /* ---------------------------------------------------------------------------- jaga modul lain, audit */

    public function test_document_prompts_stay_out_of_instruksi_and_dashboard_counts(): void
    {
        $this->fajar();
        $tool = AiTool::create(['code' => 'research-gap', 'name' => 'Research Gap', 'status' => 'active']);
        $p = PromptTemplate::create(['ai_tool_id' => $tool->id, 'name' => 'Analisis Celah', 'version' => 1, 'task_prompt' => 'x', 'status' => 'active']);
        $tool->update(['active_prompt_template_id' => $p->id]);

        $page = $this->as()->get('/'.config('research.admin_path').'/prompts')->assertOk();
        $page->assertSee('Analisis Celah')->assertDontSee('document-section:', false)->assertDontSee('Pendahuluan');

        $docSection = PromptTemplate::query()->where('name', 'like', 'document-section:%')->first();
        $this->as()->get('/'.config('research.admin_path').'/prompts?instruksi='.$docSection->id)->assertRedirect($this->url());

        $inventory = app(\App\Services\Research\DashboardAnalytics::class)->promptInventory();
        $this->assertSame(1, $inventory['total']);
        $this->assertSame(1, $inventory['active']);
        $this->assertSame(0, $inventory['draft']);

        $this->as()->get('/'.config('research.admin_path'))->assertOk();
        $this->assertSame($p->id, $tool->refresh()->active_prompt_template_id, 'penunjuk alat tidak tersentuh');
    }

    public function test_mutations_write_audit_entries_without_prompt_text(): void
    {
        $t = $this->fajar();
        $s = $this->section($t, 'pendahuluan');
        $p = $this->draftOf($s);

        $this->as()->postJson($this->url("/{$t->id}/sections/{$s->id}/prompt/{$p->id}/activate"))->assertOk();
        $this->as()->postJson($this->url("/{$t->id}/sections/{$s->id}/move"), ['direction' => 'down'])->assertOk();

        $actions = AuditLog::query()->where('auditable_type', DocumentPromptTemplate::class)->pluck('action')->all();
        $this->assertContains('document_prompt.prompt.activated', $actions);
        $this->assertContains('document_prompt.section.moved', $actions);

        $meta = json_encode(AuditLog::all()->pluck('metadata_json'));
        $this->assertStringNotContainsString('Anda adalah penulis', $meta);
    }

    /* ---------------------------------------------------------------- penyimpanan belum tersedia (tanpa tabel) */

    public function test_page_is_honest_when_storage_tables_do_not_exist(): void
    {
        Schema::drop('document_prompt_sections');
        Schema::drop('document_prompt_templates');

        $this->as()->get($this->url())->assertOk()->assertSee('Penyimpanan Prompt Dokumen belum disiapkan')->assertDontSee('Book Chapter Akademik')->assertDontSee('Buat template pertama');

        $this->as()->postJson($this->url(), $this->blank())->assertStatus(503);
        $this->as()->postJson($this->url('/import'), ['preset' => 'book-chapter-fajar'])->assertStatus(503);
        $this->assertSame(0, PromptTemplate::count(), 'tidak ada baris yang ditulis');

        // Instruksi & Versi dan Dashboard tetap hidup tanpa tabel baru.
        $this->as()->get('/'.config('research.admin_path').'/prompts')->assertOk();
        $this->as()->get('/'.config('research.admin_path'))->assertOk();
    }
}
