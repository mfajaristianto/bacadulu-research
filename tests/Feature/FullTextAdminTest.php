<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\ExternalFulltext;
use App\Models\ExternalFulltextChunk;
use App\Models\User;
use App\Services\FullText\FullTextService;
use App\Services\FullText\FullTextState as S;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\FullTextFixtures;
use Tests\TestCase;

/** Admin Full-Text: otorisasi, CSRF, tombol hanya bila ada handler, audit tanpa data sensitif, tidak ada rute berkas. */
class FullTextAdminTest extends TestCase
{
    use FullTextFixtures;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFullText();
        $this->admin = User::factory()->create();
    }

    private function as(): static
    {
        return $this->withSession(['research_admin_authenticated' => true, 'research_admin_user_id' => $this->admin->id]);
    }

    public function test_every_fulltext_route_requires_the_admin_session(): void
    {
        $ft = $this->loc($this->pub());
        $r = fn (string $n, array $p = []) => route("admin.external-sources.fulltext.{$n}", $p);

        $this->get($r('index'))->assertRedirect(route('admin.login'));
        $this->get($r('show', [$ft]))->assertRedirect(route('admin.login'));
        foreach (['check' => [], 'review' => [$ft], 'queue' => [$ft], 'cancel' => [$ft], 'retry' => [$ft]] as $name => $params) {
            $this->post($r($name, $params))->assertRedirect(route('admin.login'));
        }
        $this->delete($r('copy.delete', [$ft]))->assertRedirect(route('admin.login'));

        // Pengguna biasa yang login (bukan sesi admin) juga ditolak.
        $this->actingAs(User::factory()->create())->post($r('queue', [$ft]))->assertRedirect(route('admin.login'));
        $this->assertSame(S::ELIGIBLE, $ft->refresh()->state);
    }

    public function test_mutating_routes_sit_behind_the_web_group_with_csrf_and_none_serves_files(): void
    {
        $names = collect(Route::getRoutes()->getRoutesByName())->filter(fn ($r, $n) => str_starts_with($n, 'admin.external-sources.fulltext.'));
        $this->assertGreaterThanOrEqual(8, $names->count());

        foreach ($names as $name => $route) {
            $this->assertContains('web', $route->gatherMiddleware(), $name);
            $this->assertContains('admin', $route->gatherMiddleware(), $name);
            if (array_diff($route->methods(), ['GET', 'HEAD']) !== []) {
                $this->assertNotContains('GET', $route->methods(), "{$name}: aksi mengubah data tidak boleh lewat GET");
            }
            $this->assertDoesNotMatchRegularExpression('#download|file|storage|stream|raw|pdf#i', $route->uri(), "{$name}: tidak boleh ada rute penyaji berkas");
        }
        $this->assertFalse(file_exists(public_path('storage/external-fulltext')));
        $this->assertArrayNotHasKey('url', config('filesystems.disks.external_fulltext'), 'disk privat tanpa URL publik');
        $this->assertSame('private', config('filesystems.disks.external_fulltext.visibility'));
    }

    public function test_page_shows_real_numbers_and_only_buttons_that_have_handlers(): void
    {
        $this->as()->get(route('admin.external-sources.fulltext.index'))->assertOk()->assertSee('Belum ada lokasi teks lengkap');

        $review = $this->loc($this->pub(['title' => 'Judul butuh tinjauan']), ['state' => S::REVIEW_REQUIRED, 'permission' => 'review', 'license_code' => null]);
        $eligible = $this->loc($this->pub(['title' => 'Judul siap unduh']));
        $queued = $this->loc($this->pub(['title' => 'Judul antre']), ['state' => S::QUEUED]);
        $landing = $this->loc($this->pub(['title' => 'Judul halaman arahan']), ['kind' => 'landing', 'state' => S::LINK_FOUND, 'permission' => 'review']);
        $indexed = $this->loc($this->pub(['title' => 'Judul terindeks']), ['state' => S::INDEXED, 'chunk_count' => 3, 'size_bytes' => 2097152, 'disk' => 'external_fulltext', 'path' => '1/x.pdf']);
        $failed = $this->loc($this->pub(['title' => 'Judul gagal']), ['state' => S::DOWNLOAD_FAILED, 'error_code' => 'not_pdf', 'error_message' => 'Server mengembalikan halaman web']);

        $html = $this->as()->get(route('admin.external-sources.fulltext.index'))->assertOk()->getContent();

        // Angka berasal dari database (6 lokasi, 1 perlu tinjauan, 1 boleh diunduh, 1 terindeks, 1 unduhan gagal).
        $stats = app(FullTextService::class)->stats();
        $this->assertSame([6, 1, 1, 1, 1], [$stats['locations'], $stats['review_required'], $stats['eligible'], $stats['indexed'], $stats['download_failed']]);
        $this->assertStringContainsString('2,0 MB', $html, 'penyimpanan terpakai nyata');

        // Tiap tombol hanya muncul pada status yang punya handler.
        $row = fn (string $title) => Str::before(Str::after($html, $title), '</tr>');
        $this->assertStringContainsString('Tinjau Lisensi', $row('Judul butuh tinjauan'));
        $this->assertStringContainsString('Unduh &amp; Indeks', $row('Judul siap unduh'));
        $this->assertStringNotContainsString('Unduh &amp; Indeks', $row('Judul butuh tinjauan'));
        $this->assertStringContainsString('Batalkan', $row('Judul antre'));
        $this->assertStringNotContainsString('Tinjau Lisensi', $row('Judul halaman arahan'), 'halaman arahan tidak bisa disetujui');
        $this->assertStringContainsString('Hapus Salinan Lokal', $row('Judul terindeks'));
        $this->assertStringContainsString('Retry', $row('Judul gagal'));
        $this->assertStringContainsString('Server mengembalikan halaman web', $row('Judul gagal'));
        $this->assertStringNotContainsString('Hapus Salinan Lokal', $row('Judul siap unduh'));
    }

    public function test_page_is_honest_when_migration_has_not_been_run(): void
    {
        Schema::drop('external_fulltext_chunks');
        Schema::drop('external_fulltexts');
        FullTextService::forgetReady();

        $this->as()->get(route('admin.external-sources.fulltext.index'))->assertOk()
            ->assertSee('Penyimpanan full-text belum disiapkan')->assertDontSee('Punya abstrak')->assertDontSee('Periksa Full Text');
        $this->as()->post(route('admin.external-sources.fulltext.check'), ['target' => '1'])->assertStatus(503);
        $this->assertSame([], app(FullTextService::class)->statusFor([1, 2]));
    }

    public function test_license_review_requires_evidence_note_and_is_audited_without_sensitive_data(): void
    {
        $ft = $this->loc($this->pub(), ['state' => S::REVIEW_REQUIRED, 'permission' => 'review', 'license_code' => null, 'url' => 'https://repo.example.org/rahasia/token=ABC123.pdf']);
        $url = route('admin.external-sources.fulltext.review', $ft);

        $this->as()->post($url, ['decision' => 'approve', 'note' => 'ok'])->assertSessionHas('error');
        $this->assertSame(S::REVIEW_REQUIRED, $ft->refresh()->state);

        $this->as()->post($url, ['decision' => 'approve', 'note' => 'Lisensi CC BY tertulis di halaman pertama PDF.'])->assertSessionHas('success');
        $ft->refresh();
        $this->assertSame([S::ELIGIBLE, 'allow', $this->admin->id], [$ft->state, $ft->permission, $ft->reviewed_by]);

        $log = AuditLog::where('action', 'external_fulltext.license_reviewed')->firstOrFail();
        $this->assertSame($this->admin->id, $log->user_id);
        $this->assertStringNotContainsString('ABC123', json_encode($log->metadata_json), 'URL/token tidak masuk audit');

        $this->as()->post($url, ['decision' => 'bogus'])->assertSessionHasErrors('decision');
    }

    public function test_queue_download_is_admin_triggered_only_and_respects_limits(): void
    {
        Queue::fake();
        $ft = $this->loc($this->pub());
        $review = $this->loc($this->pub(), ['state' => S::REVIEW_REQUIRED, 'permission' => 'review']);

        $this->as()->post(route('admin.external-sources.fulltext.queue', $review))->assertSessionHas('error');
        Queue::assertNothingPushed();

        $this->as()->post(route('admin.external-sources.fulltext.queue', $ft))->assertSessionHas('success');
        $this->assertSame(S::QUEUED, $ft->refresh()->state);
        Queue::assertPushedOn('fulltext', \App\Jobs\ProcessExternalFullText::class);
        $this->assertNotNull(AuditLog::where('action', 'external_fulltext.queued')->first());

        config(['external-sources.fulltext.queue_limits.max_pending' => 1]);
        $other = $this->loc($this->pub());
        $this->as()->post(route('admin.external-sources.fulltext.queue', $other))->assertSessionHas('error');
        $this->assertSame(S::ELIGIBLE, $other->refresh()->state);

        $this->as()->post(route('admin.external-sources.fulltext.cancel', $ft))->assertSessionHas('success');
        $this->assertSame(S::ELIGIBLE, $ft->refresh()->state);
    }

    public function test_job_uses_the_dedicated_queue_not_the_research_queue(): void
    {
        $job = new \App\Jobs\ProcessExternalFullText(1, 't');
        $this->assertSame('fulltext', $job->queue);
        $this->assertNotSame(config('research.queue_name', 'research'), $job->queue);
        $this->assertSame('ingestion', config('external-sources.queue'), 'sinkronisasi metadata tetap di antreannya sendiri');
    }

    public function test_delete_local_copy_needs_confirmation_and_is_audited(): void
    {
        $ft = $this->loc($this->pub());
        $this->fakePdf($ft->url, $this->articlePdf());
        $ft->forceFill(['state' => S::QUEUED, 'queue_token' => 't'])->save();
        app(\App\Services\FullText\FullTextIngestor::class)->process($ft, 't');
        $path = $ft->refresh()->path;
        $this->assertSame(S::INDEXED, $ft->state);

        $this->as()->delete(route('admin.external-sources.fulltext.copy.delete', $ft))->assertSessionHasErrors('confirm');
        Storage::disk('external_fulltext')->assertExists($path);

        $this->as()->delete(route('admin.external-sources.fulltext.copy.delete', $ft), ['confirm' => 1])->assertRedirect(route('admin.external-sources.fulltext.index'));
        Storage::disk('external_fulltext')->assertMissing($path);
        $this->assertSame(S::ELIGIBLE, $ft->refresh()->state);
        $this->assertNotNull(AuditLog::where('action', 'external_fulltext.local_copy_deleted')->first());
    }

    public function test_check_by_id_or_doi_records_locations_and_never_downloads(): void
    {
        $p = $this->pub(['doi' => '10.5555/adm.1', 'fulltext_url' => 'https://repo.example.org/p/adm.pdf', 'license' => null]);
        Http::fake();

        $this->as()->post(route('admin.external-sources.fulltext.check'), ['target' => (string) $p->id])->assertRedirect()->assertSessionHas('success');
        $this->as()->post(route('admin.external-sources.fulltext.check'), ['target' => 'https://doi.org/10.5555/ADM.1'])->assertRedirect();
        $this->as()->post(route('admin.external-sources.fulltext.check'), ['target' => '999999'])->assertSessionHasErrors('fulltext');

        $this->assertSame(1, ExternalFulltext::count(), 'dua pemeriksaan = satu lokasi');
        $this->assertSame(S::REVIEW_REQUIRED, ExternalFulltext::first()->state);
        Http::assertNothingSent();
        $this->assertSame([], Storage::disk('external_fulltext')->allFiles());
    }

    public function test_detail_page_lists_chunks_with_provenance_and_escapes_content(): void
    {
        $ft = $this->loc($this->pub(), ['state' => S::INDEXED, 'chunk_count' => 1, 'sha256' => str_repeat('a', 64), 'disk' => 'external_fulltext', 'path' => '1/a.pdf', 'size_bytes' => 1000, 'pages_total' => 4, 'pages_with_text' => 3, 'text_chars' => 900, 'extraction_method' => 'smalot/pdfparser']);
        ExternalFulltextChunk::create([
            'fulltext_id' => $ft->id, 'publication_id' => $ft->publication_id, 'chunk_index' => 0, 'section' => 'methodology', 'page_start' => 3, 'page_end' => 4, 'is_references' => false,
            'content' => 'Metode: <script>alert(1)</script> sampel perusahaan.', 'content_sha' => sha1('x'), 'source_checksum' => str_repeat('a', 64), 'word_count' => 5,
        ]);

        $this->as()->get(route('admin.external-sources.fulltext.show', $ft))->assertOk()
            ->assertSee('Metodologi')->assertSee('3–4', false)->assertSee('(parsial)', false)->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('Tidak ada tautan unduh publik');
    }

    public function test_chunk_viewer_warns_when_one_section_label_covers_most_chunks(): void
    {
        $ft = $this->loc($this->pub(), ['state' => S::INDEXED, 'chunk_count' => 10, 'sha256' => str_repeat('b', 64), 'disk' => 'external_fulltext', 'path' => '1/b.pdf', 'size_bytes' => 1000]);
        foreach (range(0, 9) as $i) {
            ExternalFulltextChunk::create(['fulltext_id' => $ft->id, 'publication_id' => $ft->publication_id, 'chunk_index' => $i, 'section' => 'introduction', 'page_start' => $i + 1, 'page_end' => $i + 1,
                'content' => "Isi {$i}", 'content_sha' => sha1("c{$i}"), 'source_checksum' => str_repeat('b', 64), 'word_count' => 2]);
        }

        $this->as()->get(route('admin.external-sources.fulltext.show', $ft))->assertOk()
            ->assertSee('Label bagian mungkin terlalu luas')->assertSee('Pendahuluan 10');
    }

    public function test_private_library_documents_are_never_exposed_through_the_fulltext_feature(): void
    {
        $cat = Category::create(['name' => 'Akuntansi', 'slug' => 'akt', 'status' => 'active']);
        $doc = Document::create(['uuid' => (string) Str::uuid(), 'title' => 'Dokumen Privat Rahasia', 'category_id' => $cat->id, 'document_type' => 'pdf', 'status' => 'published', 'visibility' => 'private', 'uploaded_by' => $this->admin->id, 'doi' => '10.5555/priv.9']);
        DocumentChunk::create(['document_id' => $doc->id, 'chunk_index' => 0, 'content' => 'ISI RAHASIA PRIVAT', 'metadata_json' => []]);
        $p = $this->pub(['doi' => '10.5555/priv.9', 'fulltext_url' => 'https://repo.example.org/z.pdf']);

        $html = $this->as()->get(route('admin.external-sources.fulltext.index'))->getContent();
        app(FullTextService::class)->check($p);

        $this->assertStringNotContainsString('RAHASIA', $html);
        $this->assertStringNotContainsString('ISI RAHASIA PRIVAT', json_encode(ExternalFulltextChunk::all()->toArray()));
        $this->assertSame(1, ExternalFulltext::count(), 'DOI sama dengan dokumen privat tidak dianggap tersedia di Pustaka');
    }
}
