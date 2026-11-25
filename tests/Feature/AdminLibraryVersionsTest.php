<?php

namespace Tests\Feature;

use App\Jobs\ProcessSourceIngestion;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminLibraryVersionsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        config(['research.ingestion.disk' => 'research_sources', 'research.ingestion.upload_lock_wait' => 1]);
        Storage::fake('research_sources');
        Bus::fake([ProcessSourceIngestion::class]);

        $this->admin = User::factory()->create(['name' => 'Admin Uji']);
        $this->category = Category::create(['name' => 'Ekonomi', 'slug' => 'ekonomi', 'status' => 'active']);
    }

    private function as(): static
    {
        return $this->withSession(['research_admin_authenticated' => true, 'research_admin_user_id' => $this->admin->id]);
    }

    private function pdf(string $name, string $body = 'a'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n% {$body}\n");
    }

    private function create(UploadedFile $file, array $extra = [])
    {
        return $this->as()->postJson(route('admin.library.store'), $extra + [
            'title' => 'ESG Disclosure',
            'category_id' => $this->category->id,
            'source_file' => $file,
        ]);
    }

    private function makeActive(Document $document): void
    {
        $document->files()->latest('id')->first()->update(['status' => 'active']);
        Document::query()->whereKey($document->id)->update(['status' => 'ready', 'processing_file_id' => null]);
    }

    public function test_valid_upload_creates_one_source_with_hash_and_queues_processing(): void
    {
        $response = $this->create($this->pdf('esg.pdf'))->assertOk();

        $document = Document::query()->firstOrFail();
        $file = $document->files()->firstOrFail();

        $this->assertSame(route('admin.library.edit', $document), $response->json('redirect'));
        $this->assertSame(hash('sha256', "%PDF-1.4\n% a\n"), $file->checksum);
        $this->assertSame('queued', $file->status);
        Bus::assertDispatched(ProcessSourceIngestion::class);
        Storage::disk('research_sources')->assertExists($file->path);
    }

    public function test_invalid_and_oversized_files_are_rejected_before_anything_is_stored(): void
    {
        $this->create(UploadedFile::fake()->createWithContent('catatan.txt', 'bukan pdf'))->assertStatus(422)->assertJsonValidationErrors('source_file');

        config(['research.ingestion.max_upload_kb' => 1024]);
        $this->create(UploadedFile::fake()->create('besar.pdf', 2048, 'application/pdf'))->assertStatus(422)->assertJsonValidationErrors('source_file');

        $this->assertSame(0, Document::query()->count());
        $this->assertSame(0, DocumentFile::query()->count());
    }

    public function test_identical_file_never_creates_a_second_source(): void
    {
        $this->create($this->pdf('esg.pdf'))->assertOk();

        $this->create($this->pdf('salinan.pdf'), ['title' => 'Judul berbeda'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'exact_duplicate')
            ->assertJsonPath('match.title', 'ESG Disclosure')
            ->assertJsonPath('match.version', 'v1');

        $this->assertSame(1, Document::query()->count());
        $this->assertSame(1, DocumentFile::query()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'source.duplicate_blocked']);
    }

    public function test_same_doi_with_a_different_file_needs_an_explicit_decision(): void
    {
        $this->create($this->pdf('v1.pdf', 'satu'), ['doi' => 'https://doi.org/10.1234/ABC.5'])->assertOk();
        $existing = Document::query()->firstOrFail();
        $this->assertSame('10.1234/abc.5', $existing->doi);

        // Tanpa keputusan: ditahan, tidak ada yang tersimpan.
        $this->create($this->pdf('v2.pdf', 'dua'), ['title' => 'Judul lain', 'doi' => 'doi:10.1234/abc.5'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'same_source')
            ->assertJsonPath('match.id', $existing->id);
        $this->assertSame(1, DocumentFile::query()->count());

        // Keputusan harus menunjuk sumber yang benar-benar cocok.
        $this->create($this->pdf('v2.pdf', 'dua'), ['doi' => '10.1234/abc.5', 'duplicate_decision' => 'new_version', 'duplicate_target' => $existing->id + 99])->assertStatus(409);

        $this->create($this->pdf('v2.pdf', 'dua'), ['doi' => '10.1234/abc.5', 'duplicate_decision' => 'new_version', 'duplicate_target' => $existing->id])->assertOk();

        $this->assertSame(1, Document::query()->count());
        $this->assertSame(2, $existing->files()->count());
    }

    public function test_similar_title_alone_is_not_a_duplicate(): void
    {
        $this->create($this->pdf('a.pdf', 'satu'))->assertOk();
        $this->create($this->pdf('b.pdf', 'dua'))->assertOk(); // judul sama persis, isi dan DOI berbeda

        $this->assertSame(2, Document::query()->count());
    }

    public function test_new_version_keeps_the_old_file_and_history_numbers_stay_stable(): void
    {
        $this->create($this->pdf('v1.pdf', 'satu'))->assertOk();
        $document = Document::query()->firstOrFail();
        $first = $document->files()->firstOrFail();
        $this->makeActive($document);

        $this->as()->putJson(route('admin.library.update', $document), [
            'title' => 'ESG Disclosure', 'category_id' => $this->category->id, 'source_file' => $this->pdf('v2.pdf', 'dua'),
        ])->assertOk();

        $first->refresh();
        $second = $document->files()->latest('id')->firstOrFail();

        $this->assertSame('superseded', $first->status);
        $this->assertSame('queued', $second->status);
        Storage::disk('research_sources')->assertExists([$first->path, $second->path]);

        $history = app(\App\Services\Research\SourceVersions::class)->history($document->fresh(), true);

        $this->assertSame(['v2', 'v1'], array_column($history, 'label'));
        $this->assertSame('Admin Uji', $history[0]['uploader']);
        $this->assertFalse($history[0]['missing']);
    }

    public function test_replacing_with_the_active_or_an_old_version_is_refused_with_a_hint(): void
    {
        $this->create($this->pdf('v1.pdf', 'satu'))->assertOk();
        $document = Document::query()->firstOrFail();
        $this->makeActive($document);

        $put = fn (string $body) => $this->as()->putJson(route('admin.library.update', $document), [
            'title' => 'ESG Disclosure', 'category_id' => $this->category->id, 'source_file' => $this->pdf('x.pdf', $body),
        ]);

        $put('satu')->assertStatus(409)->assertJsonPath('match.is_active_version', true);

        $put('dua')->assertOk();
        $document->refresh()->update(['status' => 'ready']);

        $put('satu')->assertStatus(409)->assertJsonPath('match.is_active_version', false)->assertSee('Pulihkan');
        $this->assertSame(2, $document->files()->count());
    }

    public function test_restore_creates_a_new_version_from_old_content_and_keeps_history(): void
    {
        $this->create($this->pdf('v1.pdf', 'satu'))->assertOk();
        $document = Document::query()->firstOrFail();
        $this->makeActive($document);
        $old = $document->files()->firstOrFail();

        $this->as()->putJson(route('admin.library.update', $document), ['title' => 'ESG Disclosure', 'category_id' => $this->category->id, 'source_file' => $this->pdf('v2.pdf', 'dua')])->assertOk();
        $this->makeActive($document);

        $r = $this->as()->postJson(route('admin.library.files.restore', [$document, $old])); $this->assertSame(200, $r->status(), $r->getContent());

        $document->refresh();
        $restored = $document->files()->latest('id')->firstOrFail();

        $this->assertSame(3, $document->files()->count());
        $this->assertSame($old->checksum, $restored->checksum);
        $this->assertNotSame($old->path, $restored->path); // salinan, bukan berbagi file
        Storage::disk('research_sources')->assertExists([$old->path, $restored->path]);
        $this->assertSame('superseded', $old->fresh()->status);
        $this->assertSame('processing', $document->status);
        $this->assertSame($restored->id, (int) $document->processing_file_id);

        $log = AuditLog::query()->where('action', 'source.version_restored')->firstOrFail();
        $this->assertSame($old->id, $log->metadata_json['restored_from_file_id']);

        $latest = app(\App\Services\Research\SourceVersions::class)->history($document)[0];
        $this->assertSame(['v3', 1], [$latest['label'], $latest['restored_from']]);
    }

    public function test_only_superseded_versions_of_an_idle_source_can_be_restored(): void
    {
        $this->create($this->pdf('v1.pdf', 'satu'))->assertOk();
        $document = Document::query()->firstOrFail();
        $file = $document->files()->firstOrFail();

        $this->as()->postJson(route('admin.library.files.restore', [$document, $file]))->assertStatus(422); // belum superseded

        $file->update(['status' => 'superseded']);
        $this->as()->postJson(route('admin.library.files.restore', [$document, $file]))->assertStatus(422); // dokumen masih diproses

        $other = Document::create(['uuid' => (string) Str::uuid(), 'title' => 'Lain', 'document_type' => 'pdf', 'status' => 'ready']);
        $this->as()->postJson(route('admin.library.files.restore', [$other, $file]))->assertNotFound(); // file milik sumber lain
    }

    public function test_pdf_is_served_only_to_admins_inline_and_without_caching(): void
    {
        $this->create($this->pdf('esg.pdf'))->assertOk();
        $file = DocumentFile::query()->firstOrFail();

        $this->flushSession();
        $this->getJson(route('admin.library.files.view', $file))->assertRedirect(route('admin.login'));
        $this->get(route('admin.library.files.download', $file))->assertRedirect(route('admin.login'));

        $view = $this->as()->get(route('admin.library.files.view', $file))->assertOk();
        $this->assertStringContainsString('inline', $view->headers->get('Content-Disposition'));
        $this->assertSame('application/pdf', $view->headers->get('Content-Type'));
        $this->assertStringContainsString('no-store', $view->headers->get('Cache-Control'));

        $this->as()->get(route('admin.library.files.download', $file))->assertOk()->assertDownload('esg.pdf');
        $this->assertDatabaseHas('audit_logs', ['action' => 'source.file_downloaded']);

        Storage::disk('research_sources')->delete($file->path);
        $this->as()->get(route('admin.library.files.view', $file))->assertNotFound();
    }

    public function test_a_busy_upload_lock_returns_503_without_saving(): void
    {
        $held = Cache::lock('library-upload', 30);
        $this->assertTrue($held->get());

        $this->create($this->pdf('esg.pdf'))->assertStatus(503);
        $this->assertSame(0, Document::query()->count());

        $held->release();
        $this->create($this->pdf('esg.pdf'))->assertOk();
    }

    public function test_preflight_check_reports_identical_files_by_hash(): void
    {
        $this->create($this->pdf('esg.pdf'))->assertOk();

        $hash = hash('sha256', "%PDF-1.4\n% a\n");

        $this->as()->postJson(route('admin.library.check-duplicate'), ['sha256' => $hash])
            ->assertOk()->assertJsonPath('exact.title', 'ESG Disclosure');
        $this->as()->postJson(route('admin.library.check-duplicate'), ['sha256' => str_repeat('0', 64)])
            ->assertOk()->assertJsonPath('exact', null);
        $this->as()->postJson(route('admin.library.check-duplicate'), ['sha256' => 'bukan-hash'])->assertStatus(422);
        $this->flushSession();
        $this->postJson(route('admin.library.check-duplicate'), ['sha256' => $hash])->assertRedirect(route('admin.login'));
    }

    public function test_search_filter_sort_and_stats_work_on_the_index(): void
    {
        $other = Category::create(['name' => 'Hukum', 'slug' => 'hukum', 'status' => 'active']);
        $this->create($this->pdf('laporan-esg-2024.pdf', 'satu'), ['title' => 'Alpha', 'doi' => '10.5555/alpha'])->assertOk();
        $this->create($this->pdf('lain.pdf', 'dua'), ['title' => 'Beta', 'category_id' => $other->id])->assertOk();
        Document::query()->where('title', 'Beta')->update(['status' => 'published']);

        $index = fn (array $q = []) => $this->as()->get(route('admin.library.index', $q))->assertOk();

        $index(['search' => '10.5555'])->assertSee('Alpha')->assertDontSee('Beta');
        $index(['search' => 'laporan-esg'])->assertSee('Alpha')->assertDontSee('Beta');
        $index(['category' => $other->id])->assertSee('Beta')->assertDontSee('Alpha');
        $index(['status' => 'published'])->assertSee('Beta')->assertDontSee('Alpha');
        $index(['sort' => 'title'])->assertSeeInOrder(['Alpha', 'Beta']);
        $index(['sort' => 'oldest'])->assertSeeInOrder(['Alpha', 'Beta']);
        $index(['sort' => 'newest'])->assertSeeInOrder(['Beta', 'Alpha']);
        $this->as()->get(route('admin.library.index', ['sort' => 'acak']))->assertSessionHasErrors('sort');
        $index(['search' => 'tidak-ada-sama-sekali'])->assertSee('Tidak ada sumber yang cocok');
    }

    public function test_pages_never_ask_the_browser_to_reload_while_a_source_is_processing(): void
    {
        $this->create($this->pdf('esg.pdf'))->assertOk();
        $document = Document::query()->firstOrFail();

        $this->assertSame('processing', $document->status);

        // Dulu middleware mengirim "Refresh: 2; url=..." sehingga halaman dimuat ulang penuh tiap 2 detik.
        foreach ([route('admin.library.index'), route('admin.library.edit', $document)] as $url) {
            $response = $this->as()->get($url)->assertOk();

            $this->assertNull($response->headers->get('Refresh'), "Refresh header on {$url}");
            $response->assertDontSee('http-equiv="refresh"', false);
        }

        // Pemantauan live memakai endpoint JSON dan region yang diganti di tempat.
        $this->as()->get(route('admin.library.index'))
            ->assertSee('data-lib-watch="'.route('admin.library.ingestion-status', $document).'"', false)
            ->assertSee('data-lib-swap="results"', false);
        $this->as()->getJson(route('admin.library.ingestion-status', $document))
            ->assertOk()->assertJsonPath('terminal', false);
    }
}
