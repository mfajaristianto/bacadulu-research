<?php

namespace Tests\Feature;

use App\Models\DocumentGeneration;
use App\Services\DocumentFlow\DocumentTextCleaner;
use App\Services\DocumentFlow\MockSectionWriter;
use App\Services\DocumentFlow\SectionRequest;
use App\Services\DocumentFlow\SectionWriter;
use App\Services\DocumentFlow\SectionWriterFailure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ProductFlowFixtures;
use Tests\TestCase;

/** Generate Document: bagian per bagian, checkpoint, failover penyedia, pembersihan keluaran, Dokumen Saya, unduhan, temukan dokumen. */
class ProductFlowDocumentTest extends TestCase
{
    use ProductFlowFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFlow();
        Storage::fake('research_outputs');
        config(['research.documents.disk' => 'research_outputs', 'research.document_generation.providers' => [MockSectionWriter::class]]);
        FlakyWriter::$log = [];
        $this->seedGreenAccounting();
    }

    private function generate(?\App\Models\User $user = null, array $over = [])
    {
        $user ??= $this->member();

        return $this->actingAs($user)->post(route('research.document.store'), $over + [
            'category_id' => $this->cat->id, 'prompt_template_id' => $this->tool->id, 'q' => 'green accounting terhadap kinerja keuangan perusahaan',
            'document_type' => 'Literature Review', 'citation_style' => 'APA 7', 'output_format' => 'docx', 'language' => 'id', 'length' => 'sedang',
        ]);
    }

    public function test_generate_creates_checkpointed_sections_saves_to_my_documents_and_renders_chosen_format_only(): void
    {
        $res = $this->generate();
        $g = DocumentGeneration::firstOrFail();
        $res->assertRedirect(route('documents.ready', $g));

        $this->assertSame('completed', $g->status);
        $gen = $g->settings_snapshot_json['generation'];
        $this->assertSame('done', $gen['state']);
        $this->assertSame(['Pendahuluan', 'Kajian Literatur', 'Metode', 'Pembahasan', 'Kesimpulan'], $gen['outline']['items']);
        $this->assertSame(5, count($gen['sections']));
        foreach ($gen['sections'] as $s) {
            $this->assertSame('done', $s['status']);
            $this->assertSame('mock', $s['provider']);
            $this->assertSame('mock-sections-1', $s['model']);
        }
        $this->assertCount(5, $g->content_json['sections']);
        $this->assertNotEmpty($g->content_json['references']);
        $this->assertTrue($g->content_json['provenance']['ai_used']);
        $this->assertNotNull($g->docx_path);
        $this->assertNull($g->pdf_path);                                   // hanya format pilihan
        Storage::disk('research_outputs')->assertExists($g->docx_path);
        $this->assertSame($g->user_id, auth()->id());
    }

    public function test_user_can_deselect_optional_sections_but_not_required_ones(): void
    {
        $this->generate(null, ['sections' => ['kajian_literatur']]);   // pendahuluan/kesimpulan wajib tetap ikut
        $g = DocumentGeneration::firstOrFail();
        $this->assertSame(['Pendahuluan', 'Kajian Literatur', 'Pembahasan', 'Kesimpulan'], array_column($g->settings_snapshot_json['generation']['sections'], 'heading'));
    }

    public function test_book_mode_writes_one_discussion_section_per_question_from_its_own_evidence(): void
    {
        $this->actingAs($this->member())->post(route('research.document.store'), [
            'category_id' => $this->cat->id, 'prompt_template_id' => $this->bookTool->id, 'q' => 'akuntansi lingkungan',
            'questions' => ['green accounting kinerja keuangan perusahaan', 'xyzzy tidak ada'],
            'document_type' => 'Book Chapter', 'citation_style' => 'APA 7', 'output_format' => 'docx', 'language' => 'id', 'length' => 'ringkas',
        ]);
        $g = DocumentGeneration::firstOrFail();
        $headings = array_column($g->settings_snapshot_json['generation']['sections'], 'heading');
        $this->assertContains('Pembahasan Rumusan Masalah 1', $headings);
        $this->assertContains('Pembahasan Rumusan Masalah 2', $headings);
        $this->assertSame('completed', $g->status);
    }

    public function test_ready_page_autodownloads_once_then_reports_previous_download_and_records_event(): void
    {
        $user = $this->member();
        $this->generate($user);
        $g = DocumentGeneration::firstOrFail();

        $first = $this->actingAs($user)->get(route('documents.ready', $g))->assertOk();
        $first->assertSee('Dokumen berhasil dibuat')->assertSee('Tersimpan di Dokumen Saya')->assertSee('Unduhan dimulai otomatis.')
            ->assertSee('data-rf-auto="'.$g->id.'"', false)->assertSee('Unduh lagi')->assertSee('Temukan dokumen');
        $first->assertDontSee('tersimpan di komputer');

        $this->get(route('documents.download', [$g, 'docx']))->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'document.downloaded', 'auditable_id' => $g->id, 'user_id' => $user->id]);

        $second = $this->get(route('documents.ready', $g))->assertOk();
        $second->assertSee('Dokumen ini pernah diunduh.')->assertDontSee('data-rf-auto', false)->assertDontSee('Unduhan dimulai otomatis.');
    }

    public function test_find_document_focuses_the_card_and_ignores_other_users_documents(): void
    {
        $user = $this->member();
        $this->generate($user);
        $g = DocumentGeneration::firstOrFail();

        $this->actingAs($user)->get(route('documents.index', ['focus' => $g->id]))->assertOk()
            ->assertSee('id="doc-'.$g->id.'"', false)->assertSee('data-doc-focus', false);

        $this->actingAs($this->member())->get(route('documents.index', ['focus' => $g->id]))->assertOk()->assertDontSee('data-doc-focus', false)->assertDontSee('id="doc-'.$g->id.'"', false);
    }

    public function test_private_user_isolation_for_ready_and_download(): void
    {
        $this->generate();
        $g = DocumentGeneration::firstOrFail();
        $other = $this->member();
        $this->actingAs($other)->get(route('documents.ready', $g))->assertNotFound();
        $this->get(route('documents.download', [$g, 'docx']))->assertNotFound();
    }

    public function test_generation_requires_valid_options_and_login(): void
    {
        $this->post(route('research.document.store'))->assertRedirect(route('login'));
        $this->generate(null, ['output_format' => 'exe'])->assertSessionHasErrors('output_format');
        $this->generate(null, ['document_type' => 'Tidak Ada'])->assertSessionHasErrors('document_type');
        $this->assertSame(0, DocumentGeneration::count());
    }

    // ---- failover ---------------------------------------------------------------------------------------------------

    public function test_failover_discards_failed_output_and_next_provider_rewrites_the_same_section_from_scratch(): void
    {
        FlakyWriter::$failOn = ['Kajian Literatur' => 'rate_limit'];
        config(['research.document_generation.providers' => [FlakyWriter::class, MockSectionWriter::class]]);

        $this->generate();
        $g = DocumentGeneration::firstOrFail();
        $this->assertSame('completed', $g->status);

        $byKey = collect($g->settings_snapshot_json['generation']['sections'])->keyBy('key');
        $this->assertSame('flaky', $byKey['pendahuluan']['provider']);                    // penyedia pertama menulis bagian yang berhasil
        $this->assertSame('mock', $byKey['kajian_literatur']['provider']);                // penyedia berikutnya mengambil alih BAGIAN itu
        $this->assertSame([['provider' => 'flaky', 'state' => 'rate_limit'], ['provider' => 'mock', 'state' => 'ok']], $byKey['kajian_literatur']['attempts']);
        $this->assertStringNotContainsString('POTONGAN-TIDAK-LENGKAP', json_encode($g->content_json));   // keluaran gagal dibuang

        // Permintaan identik untuk penyedia kedua: outline sama, evidence sama, bagian sebelumnya sama.
        $failed = FlakyWriter::$log['Kajian Literatur'][0];
        $this->assertSame(['Pendahuluan'], array_column($failed['previous'], 'heading'));
    }

    public function test_output_with_unknown_citation_is_rejected_then_failover(): void
    {
        FlakyWriter::$failOn = ['Pendahuluan' => 'fake_cite'];
        config(['research.document_generation.providers' => [FlakyWriter::class, MockSectionWriter::class]]);
        $this->generate();
        $g = DocumentGeneration::firstOrFail();
        $this->assertSame('mock', $g->settings_snapshot_json['generation']['sections'][0]['provider']);
        $this->assertSame('invalid', $g->settings_snapshot_json['generation']['sections'][0]['attempts'][0]['state']);
        $this->assertStringNotContainsString('EXT:999999', json_encode($g->content_json));
    }

    public function test_when_all_providers_fail_document_is_failed_and_previous_sections_are_kept(): void
    {
        FlakyWriter::$failOn = ['Kajian Literatur' => 'timeout'];
        config(['research.document_generation.providers' => [FlakyWriter::class]]);
        $this->generate();
        $g = DocumentGeneration::firstOrFail();

        $this->assertSame('failed', $g->status);
        $this->assertStringContainsString('Kajian Literatur', $g->error_message);
        $this->assertSame(['Pendahuluan'], array_column($g->content_json['sections'], 'heading'));   // checkpoint bagian sebelumnya
        $this->assertSame('failed', $g->settings_snapshot_json['generation']['sections'][1]['status']);
        $this->assertNull($g->docx_path);
        $this->actingAs($g->user)->get(route('documents.ready', $g))->assertSee('Dokumen belum selesai');
    }

    public function test_paid_provider_is_never_used_unless_admin_allows_it(): void
    {
        config(['research.document_generation.providers' => [PaidWriter::class, MockSectionWriter::class]]);
        $this->generate();
        $this->assertSame([], PaidWriter::$calls);
        $this->assertSame('mock', DocumentGeneration::firstOrFail()->settings_snapshot_json['generation']['sections'][0]['provider']);

        PaidWriter::$calls = [];
        DocumentGeneration::query()->delete();
        config(['research.document_generation.allow_paid' => true]);   // setara free_first: gratis lebih dulu, berbayar hanya cadangan
        $this->generate();
        $this->assertSame([], PaidWriter::$calls);
    }

    public function test_provider_policy_free_first_and_paid_only(): void
    {
        $ids = fn () => array_map(fn ($w) => $w->id(), app(\App\Services\DocumentFlow\SectionWriterRouter::class)->writers());
        config(['research.document_generation.providers' => [PaidWriter::class, MockSectionWriter::class]]);

        config(['research.document_generation.policy' => null, 'research.document_generation.allow_paid' => false]);
        $this->assertSame(['mock'], $ids());                       // bawaan = FREE ONLY
        config(['research.document_generation.policy' => 'free_first']);
        $this->assertSame(['mock', 'paid'], $ids());
        config(['research.document_generation.policy' => 'paid_only']);
        $this->assertSame(['paid'], $ids());
    }

    public function test_section_checkpoint_records_validation_attempts_evidence_and_timestamp(): void
    {
        $this->generate();
        foreach (DocumentGeneration::firstOrFail()->settings_snapshot_json['generation']['sections'] as $s) {
            $this->assertSame('done', $s['status']);
            $this->assertSame('passed', $s['validation']);
            $this->assertNotEmpty($s['completed_at']);
            $this->assertNotEmpty($s['provider']);
            $this->assertNotEmpty($s['attempts']);
            $this->assertIsArray($s['citations']);
        }
    }

    public function test_non_failover_failure_stops_without_trying_other_providers(): void
    {
        FlakyWriter::$failOn = ['Pendahuluan' => 'refused'];
        config(['research.document_generation.providers' => [FlakyWriter::class, MockSectionWriter::class]]);
        $this->generate();
        $g = DocumentGeneration::firstOrFail();
        $this->assertSame('failed', $g->status);
        $this->assertCount(1, $g->settings_snapshot_json['generation']['sections'][0]['attempts']);
    }

    public function test_generation_makes_no_http_call_to_real_providers(): void
    {
        $this->generate();
        Http::assertNothingSent();
    }

    // ---- pembersihan ------------------------------------------------------------------------------------------------

    public function test_cleaner_removes_markdown_artifacts_but_keeps_academic_content(): void
    {
        $dirty = "### Pendahuluan\n\n***\n\n**Green accounting** berpengaruh positif (p < 0,05) [EXT:12:ABS].\n\n---\n\n- butir satu\n* butir dua\n\n`kode`  \n\n\n\nPenutup *miring* dan 2*3 tetap.";
        $clean = DocumentTextCleaner::clean($dirty);

        foreach (['###', '***', '---', '**', '`'] as $artifact) {
            $this->assertStringNotContainsString($artifact, $clean);
        }
        $this->assertStringContainsString('Green accounting berpengaruh positif (p < 0,05) [EXT:12:ABS].', $clean);
        $this->assertStringContainsString("• butir satu\n• butir dua", $clean);
        $this->assertStringContainsString('Penutup miring dan 2*3 tetap.', $clean);
        $this->assertStringNotContainsString("\n\n\n", $clean);
    }
}

/** Penulis uji yang bisa dibuat gagal per judul bagian dan mencatat setiap permintaan. */
class FlakyWriter implements SectionWriter
{
    /** @var array<string, string> judul bagian => keadaan gagal */
    public static array $failOn = [];

    public static array $log = [];

    public function id(): string
    {
        return 'flaky';
    }

    public function tier(): string
    {
        return 'free';
    }

    public function write(SectionRequest $r): array
    {
        self::$log[$r->heading][] = ['previous' => $r->previous, 'outline' => $r->outline, 'evidence' => $r->evidence];
        $mode = self::$failOn[$r->heading] ?? null;
        if ($mode === 'fake_cite') {
            return ['text' => 'Klaim ini memakai sitasi karangan yang panjang sekali [EXT:999999:C1:P1].', 'model' => 'flaky-1'];
        }
        if ($mode === 'refused') {
            throw new SectionWriterFailure('refused');
        }
        if ($mode) {
            // Keluaran sebagian yang TIDAK boleh masuk dokumen akhir.
            throw new SectionWriterFailure($mode, 'POTONGAN-TIDAK-LENGKAP');
        }

        return ['text' => 'Teks bagian '.$r->heading.' dari penyedia gratis, cukup panjang untuk lolos validasi.', 'model' => 'flaky-1'];
    }
}

class PaidWriter implements SectionWriter
{
    public static array $calls = [];

    public function id(): string
    {
        return 'paid';
    }

    public function tier(): string
    {
        return 'paid';
    }

    public function write(SectionRequest $r): array
    {
        self::$calls[] = $r->heading;

        return ['text' => 'Teks berbayar untuk '.$r->heading.', cukup panjang untuk lolos validasi.', 'model' => 'paid-1'];
    }
}
