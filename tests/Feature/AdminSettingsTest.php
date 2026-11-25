<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ResearchSetting;
use App\Models\User;
use App\Services\Research\ResearchSettingsService;
use App\Services\Settings\SettingsWorkspace;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

/*
 * Pengaturan V2: katalog, nilai efektif/bawaan, simpan, validasi, konflik, riwayat, dan keamanan.
 * Memakai SQLite in-memory (phpunit.xml); tidak menyentuh database asli.
 */
class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('research.admin_email', 'admin@uji.test');
        AuditLog::query()->delete();
        $this->admin = User::factory()->create(['name' => 'Admin Uji', 'email' => 'admin@uji.test']);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    private function as(): static
    {
        return $this->withSession(['research_admin_authenticated' => true, 'research_admin_user_id' => $this->admin->id]);
    }

    /** Nilai efektif saat ini sebagai payload formulir (field form lama dipertahankan). */
    private function payload(array $over = []): array
    {
        $f = app(ResearchSettingsService::class)->effectiveFlat();

        return array_replace([
            'context_messages' => $f['ai.context_messages'], 'upload_limit_kb' => $f['ingestion.max_upload_kb'], 'chunk_words' => $f['ingestion.chunk_words'],
            'chunk_overlap_words' => $f['ingestion.chunk_overlap_words'], 'minimum_chunk_words' => $f['ingestion.minimum_chunk_words'],
            'candidate_limit' => $f['retrieval.candidate_limit'], 'evidence_limit' => $f['retrieval.evidence_limit'], 'max_per_document' => $f['retrieval.max_per_document'],
            'minimum_evidence' => $f['retrieval.minimum_evidence'], 'minimum_score' => $f['retrieval.minimum_score'], 'excerpt_length' => $f['retrieval.excerpt_length'],
            'profile_gate' => $f['access.profile_gate'], 'research_queue' => $f['queue.research_queue'], 'ingestion_queue' => $f['queue.ingestion_queue'],
            'default_document_type' => $f['documents.default_type'], 'default_citation_style' => $f['documents.default_citation_style'],
        ], $over);
    }

    private function rows(): int
    {
        return ResearchSetting::query()->whereIn('key', array_keys(app(ResearchSettingsService::class)->definitions()))->count();
    }

    // ------------------------------------------------------------------------------------------------ akses

    public function test_only_admins_can_open_or_save_settings(): void
    {
        $this->get(route('admin.settings.index'))->assertRedirect(route('admin.login'));
        $this->putJson(route('admin.settings.update'), $this->payload(['evidence_limit' => 9]))->assertRedirect(route('admin.login'));
        $this->assertSame(0, $this->rows());
    }

    public function test_page_shows_effective_values_sources_and_categories_without_secrets(): void
    {
        config()->set('services.openai.api_key', 'sk-RAHASIA-JANGAN-TAMPIL');

        $res = $this->as()->get(route('admin.settings.index'))->assertOk();
        $html = $res->getContent();

        $this->assertStringContainsString('name="evidence_limit"', $html);
        $this->assertStringContainsString('value="'.config('research.retrieval.evidence_limit').'"', $html);
        foreach (['Riset', 'Sumber &amp; Pustaka', 'Dokumen', 'Akses &amp; Antrean', 'Sistem'] as $category) {
            $this->assertStringContainsString($category, $html);
        }
        $this->assertStringContainsString('Bawaan server', $html);
        $this->assertStringNotContainsString('sk-RAHASIA-JANGAN-TAMPIL', $html);
        $this->assertStringNotContainsString(config('app.key'), $html);
        $this->assertStringContainsString('terdeteksi di server', $html);
    }

    public function test_settings_owned_by_other_modules_or_unused_are_read_only(): void
    {
        $html = $this->as()->get(route('admin.settings.index'))->getContent();

        foreach (['ai_provider', 'ai_model', 'ai_timeout', 'max_output_tokens', 'live_refresh_seconds'] as $field) {
            $this->assertStringNotContainsString('name="'.$field.'"', $html, $field);
        }
        $this->assertStringContainsString('Dikelola di Provider &amp; Analitik AI', $html);
        $this->assertStringContainsString(route('admin.providers.index'), $html);
        $this->assertStringContainsString('Tersimpan tetapi belum dipakai', $html);
        $this->assertStringContainsString('tidak berpengaruh', $html);
    }

    // ------------------------------------------------------------------------------------------------ simpan dan persistensi

    public function test_save_persists_applies_to_effective_config_and_audits_before_and_after(): void
    {
        $old = config('research.retrieval.evidence_limit');
        $res = $this->as()->putJson(route('admin.settings.update'), $this->payload(['evidence_limit' => 12, 'minimum_score' => 0.25]))->assertOk()->assertJson(['ok' => true]);

        $this->assertContains('Bukti maksimum per jawaban', $res->json('changed'));
        $this->assertDatabaseHas('research_settings', ['key' => 'retrieval.evidence_limit', 'value' => '12', 'updated_by' => $this->admin->id]);
        $this->assertSame(12, config('research.retrieval.evidence_limit'));

        $log = AuditLog::query()->where('action', 'settings.updated')->firstOrFail();
        $this->assertSame($this->admin->id, $log->user_id);
        $this->assertSame(['from' => $old, 'to' => 12], $log->metadata_json['changes']['retrieval.evidence_limit']);
        $this->assertEqualsCanonicalizing(['retrieval.evidence_limit', 'retrieval.minimum_score'], $log->metadata_json['changed_keys']);
    }

    public function test_saved_values_survive_a_fresh_boot_and_reach_long_running_queue_workers(): void
    {
        $this->as()->putJson(route('admin.settings.update'), $this->payload(['chunk_words' => 640, 'chunk_overlap_words' => 80]))->assertOk();

        // Boot baru: konfigurasi kembali ke bawaan, lalu override dibaca dari database.
        config()->set('research.ingestion.chunk_words', 111);
        app(ResearchSettingsService::class)->applyOverrides();
        $this->assertSame(640, config('research.ingestion.chunk_words'));

        // Worker antrean yang berjalan lama: nilai basi dipulihkan dari database sebelum setiap job.
        config()->set('research.ingestion.chunk_words', 222);
        $job = Mockery::mock(Job::class)->shouldIgnoreMissing();
        $job->shouldReceive('getQueue')->andReturn('default');
        $job->shouldReceive('resolveName')->andReturn('JobUji');
        $job->shouldReceive('getName')->andReturn('JobUji');
        $job->shouldReceive('attempts')->andReturn(1);
        $job->shouldReceive('payload')->andReturn([]);
        event(new JobProcessing('database', $job));
        $this->assertSame(640, config('research.ingestion.chunk_words'));

        // Halaman memuat nilai tersimpan setelah muat ulang.
        $this->assertStringContainsString('value="640"', $this->as()->get(route('admin.settings.index'))->getContent());
    }

    public function test_default_values_are_kept_separately_from_saved_overrides(): void
    {
        $default = config('research.retrieval.excerpt_length');
        $this->as()->putJson(route('admin.settings.update'), $this->payload(['excerpt_length' => $default + 100]))->assertOk();

        $svc = app(ResearchSettingsService::class);
        $this->assertSame($default, $svc->defaultFlat()['retrieval.excerpt_length']);
        $this->assertSame($default + 100, $svc->effectiveFlat()['retrieval.excerpt_length']);

        $html = $this->as()->get(route('admin.settings.index'))->getContent();
        $this->assertStringContainsString('Diubah admin', $html);
        $this->assertStringContainsString('Pulihkan bawaan ('.$default.')', $html);
    }

    public function test_saving_without_changes_does_not_write_audit_rows(): void
    {
        $this->as()->putJson(route('admin.settings.update'), $this->payload())->assertOk()->assertJson(['ok' => true, 'changed' => []]);

        $this->assertSame(0, AuditLog::query()->where('action', 'settings.updated')->count());
    }

    public function test_unknown_secret_and_foreign_fields_are_ignored(): void
    {
        $before = config('research.ai_provider');
        $this->as()->putJson(route('admin.settings.update'), $this->payload([
            'ai_provider' => 'openai', 'ai_model' => 'gpt-x', 'ai_timeout' => 5, 'live_refresh_seconds' => 29, 'openai_api_key' => 'NEVER-STORE-THIS', 'foo' => 'bar',
            'evidence_limit' => 11,
        ]))->assertOk();

        $this->assertSame($before, config('research.ai_provider'));
        foreach (['ai.provider', 'ai.model', 'ai.timeout', 'queue.live_refresh_seconds', 'openai_api_key', 'foo'] as $key) {
            $this->assertDatabaseMissing('research_settings', ['key' => $key]);
        }
        $this->assertDatabaseMissing('research_settings', ['value' => 'NEVER-STORE-THIS']);
        $this->assertDatabaseHas('research_settings', ['key' => 'retrieval.evidence_limit', 'value' => '11']);
    }

    // ------------------------------------------------------------------------------------------------ validasi

    public function test_invalid_values_are_rejected_per_field_in_indonesian_and_nothing_is_saved(): void
    {
        $res = $this->as()->putJson(route('admin.settings.update'), $this->payload([
            'evidence_limit' => 999, 'minimum_score' => 'abc', 'research_queue' => 'bad name!', 'default_document_type' => 'Tidak Ada', 'profile_gate' => -5,
        ]))->assertStatus(422);

        $errors = $res->json('errors');
        $this->assertStringContainsString('maksimal 30', $errors['evidence_limit'][0]);
        $this->assertStringContainsString('harus berupa angka', $errors['minimum_score'][0]);
        $this->assertStringContainsString('hanya boleh berisi huruf', $errors['research_queue'][0]);
        $this->assertStringContainsString('bukan pilihan yang didukung', $errors['default_document_type'][0]);
        $this->assertStringContainsString('minimal 0', $errors['profile_gate'][0]);
        $this->assertSame(0, $this->rows());
        $this->assertSame(0, AuditLog::query()->where('action', 'settings.updated')->count());
    }

    public function test_cross_field_rules_protect_the_research_engine_from_broken_configuration(): void
    {
        $post = fn (array $o) => $this->as()->putJson(route('admin.settings.update'), $this->payload($o));

        $post(['chunk_words' => 300, 'chunk_overlap_words' => 300])->assertStatus(422)->assertJsonValidationErrors('chunk_overlap_words');
        $post(['chunk_words' => 300, 'minimum_chunk_words' => 400])->assertStatus(422)->assertJsonValidationErrors('minimum_chunk_words');
        $post(['evidence_limit' => 3, 'minimum_evidence' => 4])->assertStatus(422)->assertJsonValidationErrors('minimum_evidence');
        $post(['evidence_limit' => 3, 'max_per_document' => 5])->assertStatus(422)->assertJsonValidationErrors('max_per_document');
        $this->assertSame(0, $this->rows());
    }

    // ------------------------------------------------------------------------------------------------ konflik, riwayat, ringkasan

    public function test_a_stale_revision_is_rejected_so_concurrent_admins_do_not_overwrite_each_other(): void
    {
        $page = $this->as()->get(route('admin.settings.index'));
        $revision = $page->viewData('revision');

        // Admin lain menyimpan lebih dulu.
        $this->as()->putJson(route('admin.settings.update'), $this->payload(['evidence_limit' => 14]))->assertOk();
        $this->travel(2)->seconds();
        $this->as()->putJson(route('admin.settings.update'), $this->payload(['evidence_limit' => 15]))->assertOk();

        $res = $this->as()->putJson(route('admin.settings.update'), $this->payload(['evidence_limit' => 20, 'revision' => $revision]))->assertStatus(409);
        $this->assertTrue($res->json('conflict'));
        $this->assertSame(15, config('research.retrieval.evidence_limit'));

        // Dengan penanda terbaru, penyimpanan berhasil.
        $fresh = $this->as()->get(route('admin.settings.index'))->viewData('revision');
        $this->as()->putJson(route('admin.settings.update'), $this->payload(['evidence_limit' => 20, 'revision' => $fresh]))->assertOk();
        $this->assertSame(20, config('research.retrieval.evidence_limit'));
    }

    public function test_history_lists_real_changes_and_is_honest_when_old_values_are_unknown(): void
    {
        // Event lama (sebelum V2): hanya nama kunci dan snapshot sesudah; tidak ada nilai sebelumnya, dan tidak ada event yang lebih tua untuk dibandingkan.
        AuditLog::create(['user_id' => $this->admin->id, 'action' => 'settings.updated', 'auditable_type' => 'research_settings', 'metadata_json' => ['changed_keys' => ['access.profile_gate'], 'settings' => ['access.profile_gate' => 60]], 'ip_address' => '127.0.0.1']);
        $this->as()->putJson(route('admin.settings.update'), $this->payload(['evidence_limit' => 10]))->assertOk();

        $html = $this->as()->get(route('admin.settings.index'))->getContent();

        $this->assertStringContainsString('Bukti maksimum per jawaban', $html);
        $this->assertStringContainsString('<ins>10</ins>', $html);
        $this->assertStringContainsString('Kelengkapan profil minimum', $html);
        $this->assertStringContainsString('nilai sebelumnya tidak tersimpan pada event ini', $html);
        $this->assertStringContainsString(route('admin.audit.index', ['aksi' => 'settings.updated']), $html);
    }

    public function test_overview_counts_come_from_real_rows_and_flags_inconsistent_effective_values(): void
    {
        $o = $this->as()->get(route('admin.settings.index'))->viewData('overview');
        $this->assertSame(16, $o['managed']);
        $this->assertSame(0, $o['changed']);
        $this->assertNull($o['updatedAt']);
        $this->assertSame([], $o['attention']);

        $this->as()->putJson(route('admin.settings.update'), $this->payload(['candidate_limit' => config('research.retrieval.candidate_limit') + 10]))->assertOk();
        $o = $this->as()->get(route('admin.settings.index'))->viewData('overview');
        $this->assertSame(1, $o['changed']);
        $this->assertSame(15, $o['defaults']);
        $this->assertNotNull($o['updatedAt']);

        // Nilai efektif dari env server yang melanggar aturan ditandai, bukan disembunyikan.
        config()->set('research.retrieval.minimum_evidence', 9);
        config()->set('research.retrieval.evidence_limit', 5);
        $o = $this->as()->get(route('admin.settings.index'))->viewData('overview');
        $this->assertStringContainsString('Bukti minimum melebihi bukti maksimum', implode(' ', $o['attention']));
    }

    // ------------------------------------------------------------------------------------------------ keamanan dan keutuhan

    public function test_stored_values_are_escaped_and_invalid_current_choices_are_flagged(): void
    {
        config()->set('research.documents.default_type', '<script>alert(1)</script>');

        $html = $this->as()->get(route('admin.settings.index'))->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('(tidak ada dalam daftar)', $html);
    }

    public function test_viewing_settings_never_writes_rows_or_audit_events(): void
    {
        $rows = DB::table('research_settings')->count();
        $audit = AuditLog::query()->count();

        $this->as()->get(route('admin.settings.index'))->assertOk();
        $this->as()->get(route('admin.settings.index', ['kelompok' => 'sistem']))->assertOk();

        $this->assertSame($rows, DB::table('research_settings')->count());
        $this->assertSame($audit, AuditLog::query()->count());
    }

    public function test_page_query_count_does_not_grow_with_history_or_changed_rows(): void
    {
        $this->as()->putJson(route('admin.settings.update'), $this->payload(['evidence_limit' => 7]))->assertOk();
        DB::enableQueryLog();
        $this->as()->get(route('admin.settings.index'))->assertOk();
        $few = count(DB::getQueryLog());

        foreach ([[8, 450, 50, 700, 333], [9, 460, 55, 710, 334], [6, 470, 60, 720, 335], [5, 480, 65, 730, 336]] as [$e, $c, $o, $x, $k]) {
            $this->as()->putJson(route('admin.settings.update'), $this->payload(['evidence_limit' => $e, 'chunk_words' => $c, 'chunk_overlap_words' => $o, 'excerpt_length' => $x, 'candidate_limit' => $k]))->assertOk();
        }
        DB::flushQueryLog();
        $this->as()->get(route('admin.settings.index'))->assertOk();

        $this->assertSame($few, count(DB::getQueryLog()));
    }

    public function test_sidebar_keeps_a_single_settings_entry(): void
    {
        $html = $this->as()->get(route('admin.settings.index'))->getContent();
        preg_match('#<nav class="bd-admin__nav".*?</nav>#s', $html, $nav);

        $this->assertSame(1, substr_count($nav[0], 'href="'.route('admin.settings.index').'"'));
        $this->assertSame(1, substr_count($html, 'bd-admin__nav-label">Pengguna &amp; Sistem'));
    }

    public function test_integration_status_uses_honest_vocabulary_and_never_calls_third_parties(): void
    {
        Http::fake();

        config()->set('research.ai_provider', 'mock');
        $html = $this->as()->get(route('admin.settings.index'))->getContent();
        $this->assertStringContainsString('Mode uji (mock)', $html);
        $this->assertStringContainsString('Tersedia', $html);

        // OpenAI dipilih tetapi kunci tidak ada: tidak ditulis "Terhubung", dan tidak ada permintaan jaringan.
        config()->set('research.ai_provider', 'openai');
        config()->set('services.openai.api_key', '');
        $html = $this->as()->get(route('admin.settings.index'))->getContent();
        $this->assertStringContainsString('Belum dapat diverifikasi', $html);
        $this->assertStringNotContainsString('Terhubung', $html);
        Http::assertNothingSent();
    }

    public function test_upload_utilization_uses_real_file_sizes_or_stays_hidden(): void
    {
        $svc = app(SettingsWorkspace::class);
        $this->assertNull($svc->uploadUtilization(102400)); // belum ada berkas: tidak ada angka karangan
    }
}
