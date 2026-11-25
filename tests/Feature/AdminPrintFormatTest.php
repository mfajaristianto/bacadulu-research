<?php

namespace Tests\Feature;

use App\Models\AiRun;
use App\Models\AiRunResult;
use App\Models\AuditLog;
use App\Models\DocumentGeneration;
use App\Models\DocumentTemplate;
use App\Models\User;
use App\Services\Documents\DocumentRenderService;
use App\Services\Documents\PrintLayout;
use App\Services\Documents\PrintTemplates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;
use ZipArchive;

/*
 * Admin "Template & Format Cetak": daftar, editor, versi, aktivasi, pratinjau, unduhan contoh, keamanan, dan integrasi dengan generator
 * dokumen (salinan layout per dokumen, fallback format lama). Semua data berada di SQLite memori dan direktori sementara.
 */
class AdminPrintFormatTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        DocumentTemplate::query()->delete(); // migrasi menanam template bawaan; kosongkan agar hitungan tiap tes pasti
        $this->admin = User::factory()->create();
    }

    private function as(): static
    {
        return $this->withSession(['research_admin_authenticated' => true, 'research_admin_user_id' => $this->admin->id]);
    }

    private function svc(): PrintTemplates
    {
        return app(PrintTemplates::class);
    }

    private function fromPreset(string $preset = 'riset-a4', string $name = 'Format Uji', string $type = 'Research Report'): DocumentTemplate
    {
        $p = PrintLayout::presets()[$preset];

        return $this->svc()->create($name, $type, null, PrintLayout::normalize($p['layout'])['layout'], $this->admin->id);
    }

    private function active(string $preset = 'riset-a4', string $name = 'Format Aktif'): DocumentTemplate
    {
        $t = $this->fromPreset($preset, $name);
        $this->svc()->activate($t, 1, $this->admin->id);

        return $t->fresh();
    }

    /** Isian form editor (struktur layout[...]) dari layout tersimpan, dengan perubahan opsional. */
    private function payload(DocumentTemplate $t, int $v, array $over = []): array
    {
        $layout = array_replace_recursive(PrintLayout::hydrate($this->svc()->version($t, $v)['layout']), $over);

        return ['v' => $v, 'name' => $t->name, 'document_type' => $t->document_type, 'description' => '', 'layout' => $layout];
    }

    private function legacy(): DocumentTemplate
    {
        return DocumentTemplate::create([
            'code' => 'format-lama', 'name' => 'Format Lama Uji', 'document_type' => 'Research Report', 'description' => null,
            'schema_json' => ['sections' => [['heading' => 'ANALISIS', 'role' => 'research_result']], 'format' => ['body_font_size' => 11, 'line_spacing' => 1.5, 'first_line_indent_cm' => 1.0]],
            'version' => 1, 'is_default' => false, 'status' => 'active',
        ]);
    }

    // ------------------------------------------------------------------------------------------------ akses

    public function test_guests_cannot_reach_any_print_format_route(): void
    {
        $t = $this->fromPreset();

        $this->get(route('admin.document-templates.index'))->assertRedirect();
        $this->get(route('admin.document-templates.edit', $t))->assertRedirect();
        $this->post(route('admin.document-templates.store'), ['source' => 'blank', 'name' => 'x'])->assertRedirect();
        $this->put(route('admin.document-templates.update', $t), [])->assertRedirect();
        $this->post(route('admin.document-templates.activate', [$t, 1]))->assertRedirect();
        $this->post(route('admin.document-templates.preview', $t))->assertRedirect();
        $this->get(route('admin.document-templates.preview-pdf', [$t, 1]))->assertRedirect();
        $this->get(route('admin.document-templates.preview-docx', [$t, 1]))->assertRedirect();

        $this->assertSame(1, DocumentTemplate::count());
        $this->assertNull($this->svc()->activeVersion($t->fresh()));
    }

    public function test_sidebar_uses_the_new_label_and_the_same_route(): void
    {
        $this->as()->get(route('admin.document-templates.index'))
            ->assertOk()
            ->assertSee('Template &amp; Format Cetak', false)
            ->assertSee(route('admin.document-templates.index'), false)
            ->assertDontSee('Template Dokumen');
    }

    // ------------------------------------------------------------------------------------------------ daftar

    public function test_empty_list_explains_itself_and_offers_creation(): void
    {
        $this->as()->get(route('admin.document-templates.index'))
            ->assertOk()
            ->assertSee('Belum ada template format')
            ->assertSee('Buat Template Format')
            ->assertSee('Kelola tata letak, tipografi, dan standar penyusunan dokumen akademik untuk Word, PDF, dan naskah buku.');
    }

    public function test_list_shows_rows_with_status_and_supports_search_filter_and_sort(): void
    {
        $this->active('riset-a4', 'Alfa Aktif');
        $this->fromPreset('book-chapter-a5', 'Beta Draf');
        $this->legacy();

        $this->as()->get(route('admin.document-templates.index'))
            ->assertOk()
            ->assertSeeInOrder(['Alfa Aktif', 'Beta Draf', 'Format Lama Uji'], false)
            ->assertSee('Kelola Alfa Aktif')
            ->assertSee('Pratinjau Beta Draf')
            ->assertSee('Format lama');

        $this->as()->get(route('admin.document-templates.index', ['search' => 'Beta']))->assertOk()->assertSee('Beta Draf')->assertDontSee('Alfa Aktif');
        $this->as()->get(route('admin.document-templates.index', ['status' => 'active']))->assertOk()->assertSee('Alfa Aktif')->assertDontSee('Beta Draf');
        $this->as()->get(route('admin.document-templates.index', ['sort' => 'name']))->assertOk();
        $this->as()->get(route('admin.document-templates.index', ['search' => 'tidak-ada-yang-cocok']))->assertOk()->assertSee('Tidak ada template yang cocok');
    }

    public function test_listing_never_writes_anything(): void
    {
        $this->fromPreset();
        $before = [DocumentTemplate::count(), AuditLog::count(), json_encode(DocumentTemplate::first()->schema_json)];

        $this->as()->get(route('admin.document-templates.index'))->assertOk();
        $this->as()->get(route('admin.document-templates.index', ['baru' => 1]))->assertOk();
        $this->as()->get(route('admin.document-templates.edit', DocumentTemplate::first()))->assertOk();

        $this->assertSame($before, [DocumentTemplate::count(), AuditLog::count(), json_encode(DocumentTemplate::first()->schema_json)]);
    }

    // ------------------------------------------------------------------------------------------------ membuat

    public function test_new_page_lists_all_presets_for_inspection_without_creating_them(): void
    {
        $page = $this->as()->get(route('admin.document-templates.index', ['baru' => 1]))->assertOk();

        foreach (['Riset Akademik A4', 'Artikel Ilmiah A4', 'Book Chapter A5', 'Buku Akademik B5', 'Format Kustom Penerbit'] as $name) {
            $page->assertSee($name);
        }

        $this->assertSame(0, DocumentTemplate::count(), 'preset tidak dibuat otomatis');
    }

    public function test_every_preset_creates_an_unlocked_inactive_draft_v1_with_audit(): void
    {
        foreach (array_keys(PrintLayout::presets()) as $key) {
            $this->as()->postJson(route('admin.document-templates.store'), ['source' => 'preset', 'preset' => $key, 'name' => 'Dari '.$key])
                ->assertOk()->assertJson(['ok' => true]);
        }

        $this->assertSame(5, DocumentTemplate::count());

        foreach (DocumentTemplate::all() as $t) {
            $this->assertSame('draft', $t->status);
            $this->assertFalse((bool) $t->is_default);
            $v = $this->svc()->versions($t);
            $this->assertCount(1, $v);
            $this->assertSame('draft', $v[0]['status']);
            $this->assertNull($this->svc()->print($t)['active']);
            $this->assertNull($this->svc()->lockReason($v[0]), 'preset tidak terkunci permanen');
        }

        $this->assertSame(5, AuditLog::where('action', 'document_template.created')->count());
    }

    public function test_blank_creation_validates_and_copy_is_independent_of_its_source(): void
    {
        $this->as()->postJson(route('admin.document-templates.store'), ['source' => 'blank', 'name' => ''])->assertStatus(422)->assertJsonValidationErrors('name');
        $this->as()->postJson(route('admin.document-templates.store'), ['source' => 'blank', 'name' => 'Kosong', 'document_type' => 'Jenis Palsu'])->assertStatus(422);
        $this->as()->postJson(route('admin.document-templates.store'), ['source' => 'blank', 'name' => 'Kosong', 'document_type' => 'Research Report'])->assertOk();

        $src = $this->active('buku-b5', 'Sumber');
        $this->as()->postJson(route('admin.document-templates.store'), ['source' => 'copy', 'copy_from' => $src->id, 'name' => 'Salinan'])->assertOk();

        $copy = DocumentTemplate::where('name', 'Salinan')->firstOrFail();
        $this->assertSame('draft', $copy->status);
        $this->assertNotSame($src->code, $copy->code);
        $this->assertCount(1, $this->svc()->versions($copy));
        $this->assertSame('b5_iso', PrintLayout::hydrate($this->svc()->version($copy, 1)['layout'])['page']['size']);
        $this->assertNotNull($this->svc()->activeVersion($src->fresh()), 'sumber tidak berubah');
    }

    // ------------------------------------------------------------------------------------------------ editor

    public function test_editor_exposes_every_required_settings_tab_and_the_simulation_label(): void
    {
        $t = $this->fromPreset('buku-b5', 'Buku Uji');

        $page = $this->as()->get(route('admin.document-templates.edit', $t))->assertOk();

        foreach (['Halaman', 'Tipografi', 'Paragraf', 'Heading', 'Header &amp; Footer', 'Penomoran', 'Daftar Isi', 'Tabel &amp; Pustaka', 'Ekspor', 'Pemeriksaan', 'Pratinjau'] as $tab) {
            $page->assertSee('>'.$tab.'</button>', false);
        }

        $page->assertSee('CONTOH TAMPILAN')
            ->assertSee('B5 ISO')
            ->assertSee('B5 JIS')
            ->assertSee('layout[margins][gutter]', false)
            ->assertSee('data-pf-preview-url', false)
            ->assertSee('PDF/X, bleed, crop marks, dan CMYK tidak didukung')
            ->assertSee('bukan jaminan siap cetak profesional');
    }

    public function test_editor_never_offers_branding_or_fake_publisher_fields(): void
    {
        $t = $this->fromPreset();
        $html = $this->as()->get(route('admin.document-templates.edit', $t))->assertOk()->getContent();

        $this->assertStringNotContainsStringIgnoringCase('ISBN', strip_tags(preg_replace('/Tidak membuat[^<]*/', '', $html)));
        $this->assertStringNotContainsString('Siap cetak profesional</', $html);
        $this->assertStringNotContainsString('PDF/X siap', $html);
    }

    public function test_template_name_is_escaped_in_list_and_editor(): void
    {
        $t = $this->fromPreset('riset-a4', '<script>alert(1)</script>');

        $this->as()->get(route('admin.document-templates.index'))->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
        $this->as()->get(route('admin.document-templates.edit', $t))->assertOk()->assertDontSee('<script>alert(1)</script>', false);
    }

    // ------------------------------------------------------------------------------------------------ simpan

    public function test_saving_a_draft_stores_values_in_millimetres_and_audits_the_change(): void
    {
        $t = $this->fromPreset();
        // Form mengirim panjang dalam satuan terpilih (inci); server menyimpan milimeter.
        $in = $this->payload($t, 1, ['unit' => 'in', 'page' => ['size' => 'a5'], 'margins' => ['top' => 1.0, 'bottom' => 1.0, 'left' => 1.5, 'right' => 1.0, 'inner' => 1.0, 'outer' => 1.0, 'gutter' => 0.0],
            'para' => ['indent' => 0.3], 'refs' => ['hanging' => 0.3]]);

        $this->as()->putJson(route('admin.document-templates.update', $t), $in)->assertOk()->assertJson(['ok' => true]);

        $saved = PrintLayout::hydrate($this->svc()->version($t->fresh(), 1)['layout']);
        $this->assertSame('a5', $saved['page']['size']);
        $this->assertSame(25.4, $saved['margins']['top']);
        $this->assertSame(38.1, $saved['margins']['left']);
        $this->assertSame('draft', $this->svc()->version($t->fresh(), 1)['status']);
        $this->assertSame(1, AuditLog::where('action', 'document_template.layout_saved')->count());
    }

    public function test_saving_rejects_invalid_values_with_field_errors_and_changes_nothing(): void
    {
        $t = $this->fromPreset();
        $before = json_encode($t->fresh()->schema_json);

        $this->as()->putJson(route('admin.document-templates.update', $t), $this->payload($t, 1, ['margins' => ['top' => 999]]))
            ->assertStatus(422)->assertJsonValidationErrors(['layout.margins.top']);
        $this->as()->putJson(route('admin.document-templates.update', $t), $this->payload($t, 1, ['type' => ['body_font' => 'hacker']]))
            ->assertStatus(422);
        $this->as()->putJson(route('admin.document-templates.update', $t), $this->payload($t, 1, ['header_footer' => ['header' => ['left' => '{bukan_token}']]]))
            ->assertStatus(422);
        $this->as()->putJson(route('admin.document-templates.update', $t), ['v' => 1, 'name' => '', 'document_type' => 'Research Report', 'layout' => []])
            ->assertStatus(422);

        $this->assertSame($before, json_encode($t->fresh()->schema_json));
        $this->assertSame(0, AuditLog::where('action', 'document_template.layout_saved')->count());
    }

    public function test_active_and_archived_versions_are_immutable_on_the_server(): void
    {
        $t = $this->active();
        $before = json_encode($t->fresh()->schema_json);

        $this->as()->putJson(route('admin.document-templates.update', $t), $this->payload($t, 1, ['margins' => ['top' => 30]]))
            ->assertStatus(409);
        $this->assertSame($before, json_encode($t->fresh()->schema_json), 'versi aktif tidak berubah walau form dikirim langsung');

        $this->svc()->revise($t, 1, $this->admin->id);
        $this->svc()->activate($t->fresh(), 2, $this->admin->id);
        $this->assertSame('archived', $this->svc()->version($t->fresh(), 1)['status']);

        $this->as()->putJson(route('admin.document-templates.update', $t), $this->payload($t, 1, ['margins' => ['top' => 30]]))
            ->assertStatus(409);
    }

    // ------------------------------------------------------------------------------------------------ versi

    public function test_revise_activate_and_rollback_keep_every_version_and_audit_each_step(): void
    {
        $t = $this->active('riset-a4', 'Versi Uji');

        $this->as()->postJson(route('admin.document-templates.revise', [$t, 1]))->assertOk();
        $this->assertSame(['draft', 'active'], collect($this->svc()->versions($t->fresh()))->pluck('status')->all());

        $this->as()->putJson(route('admin.document-templates.update', $t), $this->payload($t->fresh(), 2, ['type' => ['body_size' => 11]]))->assertOk();
        $this->as()->postJson(route('admin.document-templates.activate', [$t, 2]))->assertOk();

        $fresh = $t->fresh();
        $this->assertSame(['active', 'archived'], collect($this->svc()->versions($fresh))->pluck('status')->all());
        $this->assertSame(2, (int) $fresh->version, 'kolom version mengikuti versi aktif');
        $this->assertSame(11.0, (float) PrintLayout::hydrate($this->svc()->version($fresh, 2)['layout'])['type']['body_size']);
        $this->assertSame(12.0, (float) PrintLayout::hydrate($this->svc()->version($fresh, 1)['layout'])['type']['body_size'], 'v1 tetap utuh');

        // Rollback: v1 dihidupkan lewat revisi (v3), bukan dengan menyunting arsip.
        $this->as()->postJson(route('admin.document-templates.revise', [$fresh, 1]))->assertOk();
        $this->as()->postJson(route('admin.document-templates.activate', [$fresh, 3]))->assertOk();
        $this->assertSame(12.0, (float) PrintLayout::hydrate($this->svc()->activeVersion($fresh->fresh())['layout'])['type']['body_size']);
        $this->assertCount(3, $this->svc()->versions($fresh->fresh()));

        foreach (['document_template.layout_revised', 'document_template.layout_activated'] as $action) {
            $this->assertGreaterThanOrEqual(2, AuditLog::where('action', $action)->count(), $action);
        }
    }

    public function test_only_drafts_can_be_archived_from_the_version_action(): void
    {
        $t = $this->active();

        $this->as()->postJson(route('admin.document-templates.archive-version', [$t, 1]))->assertStatus(409);
        $this->assertSame('active', $this->svc()->version($t->fresh(), 1)['status']);

        $this->as()->postJson(route('admin.document-templates.revise', [$t, 1]))->assertOk();
        $this->as()->postJson(route('admin.document-templates.archive-version', [$t, 2]))->assertOk();
        $this->assertSame('archived', $this->svc()->version($t->fresh(), 2)['status']);
        $this->as()->postJson(route('admin.document-templates.activate', [$t, 99]))->assertStatus(409);
    }

    public function test_default_archive_and_restore_rules(): void
    {
        $a = $this->active('riset-a4', 'A');
        $b = $this->active('artikel-a4', 'B');

        $this->as()->postJson(route('admin.document-templates.default', $a))->assertOk();
        $this->as()->postJson(route('admin.document-templates.default', $b))->assertOk();
        $this->assertFalse((bool) $a->fresh()->is_default);
        $this->assertTrue((bool) $b->fresh()->is_default);
        $this->assertSame('active', $a->fresh()->status, 'default lama tidak diarsipkan');

        $this->as()->deleteJson(route('admin.document-templates.destroy', $b))->assertStatus(409);
        $this->as()->deleteJson(route('admin.document-templates.destroy', $a))->assertOk();
        $this->assertSame('inactive', $a->fresh()->status);
        $this->assertCount(1, $this->svc()->versions($a->fresh()), 'arsip tidak menghapus versi');

        $this->as()->postJson(route('admin.document-templates.restore', $a))->assertOk();
        $this->assertSame('active', $a->fresh()->status);

        $draft = $this->fromPreset('riset-a4', 'Draf');
        $this->as()->postJson(route('admin.document-templates.default', $draft))->assertStatus(409);
    }

    public function test_legacy_template_is_shown_as_is_and_can_adopt_its_old_values_as_draft_v1(): void
    {
        $t = $this->legacy();

        $this->as()->get(route('admin.document-templates.edit', $t))->assertOk()->assertSee('Format lama')->assertSee('Mulai atur format cetak');
        $this->as()->postJson(route('admin.document-templates.adopt', $t))->assertOk();

        $fresh = $t->fresh();
        $v = $this->svc()->version($fresh, 1);
        $this->assertSame('draft', $v['status']);
        $this->assertSame(11.0, (float) PrintLayout::hydrate($v['layout'])['type']['body_size'], 'nilai format lama dipertahankan');
        $this->assertEquals(['body_font_size' => 11, 'line_spacing' => 1.5, 'first_line_indent_cm' => 1.0], $fresh->schema_json['format'], 'format lama tidak diubah');
        $this->assertNull($this->svc()->effective($fresh), 'draf belum dipakai generator');
        $this->as()->postJson(route('admin.document-templates.adopt', $fresh))->assertStatus(409);
    }

    // ------------------------------------------------------------------------------------------------ pratinjau dan unduhan

    public function test_live_preview_renders_unsaved_values_and_writes_nothing(): void
    {
        $t = $this->fromPreset();
        $before = [json_encode($t->fresh()->schema_json), AuditLog::count()];
        $in = $this->payload($t, 1, ['page' => ['size' => 'b5_jis']]);

        $json = $this->as()->postJson(route('admin.document-templates.preview', $t), ['layout' => $in['layout'], 'contoh' => 'all'])
            ->assertOk()->assertJson(['ok' => true])->json();

        $this->assertEqualsWithDelta(182.0, $json['width_mm'], 0.01);
        $this->assertEqualsWithDelta(257.0, $json['height_mm'], 0.01);
        $this->assertStringContainsString('<svg', $json['html']);
        $this->assertContains($json['check']['status'], ['ready', 'review', 'blocked']);
        $this->assertSame($before, [json_encode($t->fresh()->schema_json), AuditLog::count()], 'pratinjau tidak menyimpan apa pun');
    }

    public function test_live_preview_rejects_invalid_values_and_unknown_samples(): void
    {
        $t = $this->fromPreset();
        $bad = $this->payload($t, 1, ['margins' => ['top' => 999]]);

        $this->as()->postJson(route('admin.document-templates.preview', $t), ['layout' => $bad['layout']])->assertStatus(422)->assertJson(['ok' => false]);
        $this->as()->postJson(route('admin.document-templates.preview', $t), ['layout' => $this->payload($t, 1)['layout'], 'contoh' => 'rm -rf'])->assertStatus(422);
        $this->as()->postJson(route('admin.document-templates.preview', $t), ['v' => 77])->assertNotFound();
    }

    public function test_sample_downloads_are_a_real_pdf_and_a_real_docx_with_safe_headers(): void
    {
        $t = $this->fromPreset('buku-b5', 'Unduh');

        $pdf = $this->as()->get(route('admin.document-templates.preview-pdf', [$t, 1]))->assertOk();
        $this->assertStringContainsString('application/pdf', $pdf->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $this->assertSame('nosniff', $pdf->headers->get('X-Content-Type-Options'));

        $docx = $this->as()->get(route('admin.document-templates.preview-docx', [$t, 1]))->assertOk();
        $this->assertStringContainsString('wordprocessingml.document', $docx->headers->get('Content-Type'));
        $this->assertStringContainsString('contoh-tata-letak.docx', $docx->headers->get('Content-Disposition'));

        $tmp = tempnam(sys_get_temp_dir(), 'bdx');
        file_put_contents($tmp, $docx->getContent());
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($tmp) === true);
        $this->assertNotFalse($zip->getFromName('word/document.xml'));
        $zip->close();
        @unlink($tmp);

        $this->as()->get(route('admin.document-templates.preview-pdf', [$t, 9]))->assertNotFound();
        $this->as()->get(route('admin.document-templates.preview-docx', [$t, 9]))->assertNotFound();
    }

    // ------------------------------------------------------------------------------------------------ integrasi generator

    private function makeRun(): AiRun
    {
        $run = AiRun::create([
            'uuid' => (string) Str::uuid(), 'user_id' => User::factory()->create(['institution' => 'Universitas Simulasi'])->id, 'status' => 'completed',
            'user_parameters_json' => ['user_message' => 'Analisis evidence untuk laporan.'], 'model_provider' => 'mock', 'model_name' => 'uji',
            'started_at' => now()->subSecond(), 'finished_at' => now(),
        ]);

        AiRunResult::create([
            'ai_run_id' => $run->id, 'result_type' => 'primary', 'content' => 'Hasil simulasi untuk uji tata letak.',
            'structured_output_json' => ['answer' => 'Hasil simulasi untuk uji tata letak.', 'evidence' => [], 'sources' => [], 'glossary_context' => []], 'citations_json' => [],
        ]);

        return $run->fresh();
    }

    private function generate(AiRun $run): DocumentGeneration
    {
        $this->actingAs($run->user)->post(route('documents.store'), [
            'ai_run_id' => $run->id, 'document_type' => 'Research Report', 'citation_style' => 'APA 7', 'title' => 'Dokumen Simulasi',
        ])->assertRedirect();

        return DocumentGeneration::query()->latest('id')->firstOrFail();
    }

    public function test_generator_copies_the_active_layout_into_each_document_and_old_documents_do_not_change(): void
    {
        $t = $this->active('book-chapter-a5', 'Format Generator');
        $this->svc()->setDefault($t, $this->admin->id);

        $first = $this->generate($this->makeRun());
        $content = $first->content_json;

        $this->assertSame('a5', $content['layout']['page']['size'], 'salinan layout ikut tersimpan di dokumen');
        $this->assertSame($t->id, $first->document_template_id);
        $this->assertSame(1, (int) $content['template']['layout_version']);
        $this->assertNotEmpty($content['template']['layout_hash']);
        $this->assertSame($content['template']['layout_hash'], $content['provenance']['print_layout_hash']);

        // Versi baru diaktifkan: dokumen lama tetap memakai salinannya, dokumen baru memakai v2.
        $this->svc()->revise($t, 1, $this->admin->id);
        $this->svc()->saveDraft($t->fresh(), 2, [], PrintLayout::normalize(array_replace_recursive(PrintLayout::hydrate($this->svc()->version($t->fresh(), 2)['layout']), ['page' => ['size' => 'b5_iso']]))['layout'], $this->admin->id);
        $this->svc()->activate($t->fresh(), 2, $this->admin->id);

        $this->assertSame('a5', $first->fresh()->content_json['layout']['page']['size'], 'dokumen lama tidak berubah');
        $second = $this->generate($this->makeRun());
        $this->assertSame('b5_iso', $second->content_json['layout']['page']['size']);
        $this->assertSame(2, (int) $second->content_json['template']['layout_version']);
    }

    public function test_draft_or_archived_layouts_are_never_used_by_the_generator(): void
    {
        $t = $this->fromPreset('buku-b5', 'Masih Draf');
        $t->update(['is_default' => true, 'status' => 'active']);

        $g = $this->generate($this->makeRun());

        $this->assertArrayNotHasKey('layout', $g->content_json, 'versi draf tidak pernah dipakai dokumen');
    }

    public function test_legacy_templates_keep_the_old_document_path_without_a_layout_copy(): void
    {
        $t = $this->legacy();
        $t->update(['is_default' => true]);

        $g = $this->generate($this->makeRun());

        $this->assertSame($t->id, $g->document_template_id);
        $this->assertArrayNotHasKey('layout', $g->content_json);
        $this->assertArrayNotHasKey('layout_version', $g->content_json['template']);
    }

    public function test_rendering_a_document_with_a_layout_makes_real_docx_and_pdf_without_rerunning_ai(): void
    {
        $root = storage_path('framework/testing/print-format-'.Str::random(8));
        config(['filesystems.disks.research_outputs.root' => $root, 'research.documents.disk' => 'research_outputs']);

        try {
            $t = $this->active('buku-b5', 'Render Nyata');
            $this->svc()->setDefault($t, $this->admin->id);
            $generation = $this->generate($this->makeRun());
            $runsBefore = AiRun::count();

            $done = app(DocumentRenderService::class)->render($generation);

            $this->assertSame($runsBefore, AiRun::count(), 'render tidak memanggil AI lagi');
            $this->assertSame('completed', $done->status);

            $docx = $root.'/'.$done->docx_path;
            $pdf = $root.'/'.$done->pdf_path;
            $this->assertFileExists($docx);
            $this->assertFileExists($pdf);
            $this->assertStringStartsWith('%PDF-', (string) file_get_contents($pdf, false, null, 0, 8));

            preg_match('/\/MediaBox \[0 0 ([\d.]+) ([\d.]+)\]/', (string) file_get_contents($pdf), $m);
            $this->assertEqualsWithDelta(498.9, (float) $m[1], 0.6, 'PDF berukuran B5 ISO sesuai layout dokumen');

            $zip = new ZipArchive;
            $this->assertTrue($zip->open($docx) === true);
            $this->assertStringContainsString('w:w="'.PrintLayout::twips(176).'"', (string) $zip->getFromName('word/document.xml'));
            $zip->close();
        } finally {
            File::deleteDirectory($root);
        }
    }

    public function test_rendering_a_legacy_document_still_uses_the_old_renderers(): void
    {
        $root = storage_path('framework/testing/print-format-'.Str::random(8));
        config(['filesystems.disks.research_outputs.root' => $root, 'research.documents.disk' => 'research_outputs']);

        try {
            $this->legacy()->update(['is_default' => true]);
            $done = app(DocumentRenderService::class)->render($this->generate($this->makeRun()));

            $this->assertSame('completed', $done->status);
            $this->assertFileExists($root.'/'.$done->docx_path);
            $this->assertFileExists($root.'/'.$done->pdf_path);
        } finally {
            File::deleteDirectory($root);
        }
    }
}
