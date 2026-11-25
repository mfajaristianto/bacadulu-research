<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Document;
use App\Models\SourceSyncItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminSourceSyncWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'source-sync.provider' => 'mock',
            'research.ingestion.chunk_words' => 120,
            'research.ingestion.chunk_overlap_words' => 20,
            'research.ingestion.minimum_chunk_words' => 20,
        ]);

        $this->admin = User::factory()->create();
        $this->category = Category::create(['name' => 'Ekonomi', 'slug' => 'ekonomi', 'status' => 'active']);
    }

    private function as(): static
    {
        return $this->withSession(['research_admin_authenticated' => true, 'research_admin_user_id' => $this->admin->id]);
    }

    private function scan(): int
    {
        return (int) $this->as()->postJson(route('admin.source-sync.scan'))->assertOk()->json('run');
    }

    private function item(string $externalId = 'MOCK-ESG-001'): SourceSyncItem
    {
        return SourceSyncItem::query()->where('external_id', $externalId)->firstOrFail();
    }

    private function import(SourceSyncItem $item, ?int $category = null)
    {
        return $this->as()->postJson(route('admin.source-sync.import', $item), ['category_id' => $category ?? $this->category->id]);
    }

    public function test_page_is_honest_about_the_mock_connector_and_works_before_any_scan(): void
    {
        $this->as()->get(route('admin.source-sync.index'))
            ->assertOk()
            ->assertSee('Impor Sumber')
            ->assertSee('Data uji lokal, bukan koneksi produksi')
            ->assertSee('Belum pernah scan')
            ->assertSee('Auto-publish nonaktif')
            ->assertSee('Belum ada hasil scan');
    }

    public function test_scan_answers_json_and_the_queue_lists_real_items_with_filters(): void
    {
        $run = $this->scan();

        $this->as()->get(route('admin.source-sync.index', ['run' => $run]))
            ->assertOk()
            ->assertSee('Mock ESG Disclosure and Firm Value Evidence')
            ->assertSee('Mock AI Governance in Accounting Education Evidence')
            ->assertDontSee('PDF'); // item sync tidak punya file PDF, jadi tidak ada lencana PDF palsu

        $this->as()->get(route('admin.source-sync.index', ['run' => $run, 'q' => 'ESG']))
            ->assertSee('Mock ESG Disclosure')->assertDontSee('Mock AI Governance');
        $this->as()->get(route('admin.source-sync.index', ['run' => $run, 'status' => 'imported']))
            ->assertDontSee('Mock ESG Disclosure')->assertSee('Tidak ada item di tab ini');
        $this->as()->get(route('admin.source-sync.index', ['run' => $run, 'status' => 'bukan-status']))->assertSessionHasErrors('status');
    }

    public function test_a_running_scan_blocks_a_second_one(): void
    {
        $held = Cache::lock('source-sync-scan', 30);
        $this->assertTrue($held->get());

        $this->as()->postJson(route('admin.source-sync.scan'))->assertStatus(409);
        $this->assertSame(0, SourceSyncItem::query()->count());

        $held->release();
        $this->scan();
    }

    public function test_import_creates_one_ready_source_and_repeating_it_creates_nothing_more(): void
    {
        $this->scan();
        $item = $this->item();

        $this->import($item)->assertOk()->assertJsonPath('ok', true);

        $document = Document::query()->firstOrFail();

        $this->assertSame('ready', $document->status); // tidak pernah auto-publish
        $this->assertSame('imported', $item->fresh()->status);

        // Klik ganda / permintaan ulang: ditolak, bukan membuat sumber kedua.
        $this->import($item)->assertStatus(409)->assertJsonPath('ok', false);
        $this->assertSame(1, Document::query()->count());
    }

    public function test_import_needs_a_category(): void
    {
        $this->scan();

        $this->as()->postJson(route('admin.source-sync.import', $this->item()), [])->assertStatus(422);
        $this->assertSame(0, Document::query()->count());
    }

    public function test_ignore_changes_nothing_in_the_library(): void
    {
        $this->scan();
        $item = $this->item();

        $this->as()->patchJson(route('admin.source-sync.ignore', $item))->assertOk();
        $this->assertSame('ignored', $item->fresh()->status);
        $this->assertSame(0, Document::query()->count());
        $this->as()->patchJson(route('admin.source-sync.ignore', $item))->assertStatus(409);
    }

    public function test_identical_content_in_the_library_is_not_imported_twice(): void
    {
        $this->scan();
        $item = $this->item();

        // Sumber lain di Pustaka sudah membawa checksum yang sama persis.
        $existing = Document::create([
            'uuid' => (string) Str::uuid(), 'title' => 'Sudah ada', 'category_id' => $this->category->id,
            'document_type' => 'external_text', 'status' => 'published', 'external_checksum' => $item->external_checksum,
        ]);

        $this->import($item)
            ->assertStatus(409)
            ->assertJsonPath('duplicate.kind', 'exact')
            ->assertJsonPath('duplicate.title', 'Sudah ada');

        $this->assertSame(1, Document::query()->count());
        $this->assertSame('new', $item->fresh()->status); // tetap bisa diabaikan, tidak ditandai gagal

        $this->as()->get(route('admin.source-sync.index', ['run' => $item->source_sync_run_id]))
            ->assertSee('Isi identik dengan sumber di Pustaka')
            ->assertSee(route('admin.library.edit', $existing), false);
    }

    public function test_same_doi_as_another_library_source_is_blocked_with_a_pointer_to_it(): void
    {
        $this->scan();
        $item = $this->item();
        $item->update(['payload_json' => ['metadata' => ['doi' => 'https://doi.org/10.5555/ESG.1']]]);

        $existing = Document::create([
            'uuid' => (string) Str::uuid(), 'title' => 'ESG dari unggahan', 'category_id' => $this->category->id,
            'document_type' => 'pdf', 'status' => 'published', 'doi' => '10.5555/esg.1',
        ]);

        $this->import($item)
            ->assertStatus(409)
            ->assertJsonPath('duplicate.kind', 'doi')
            ->assertJsonPath('duplicate.url', route('admin.library.edit', $existing));

        $this->assertSame(1, Document::query()->count());
    }

    public function test_changed_source_updates_its_own_document_instead_of_duplicating_it(): void
    {
        $this->scan();
        $this->import($this->item())->assertOk();

        // Connector melaporkan isi berbeda untuk sumber yang sama.
        config(['source-sync.connectors.mock.items.0.extracted_text' => str_repeat('Kalimat baru yang cukup panjang untuk diproses menjadi potongan teks. ', 12)]);
        $run = $this->scan();

        $changed = $this->item()->fresh();
        $this->assertSame('imported', SourceSyncItem::query()->where('source_sync_run_id', '<', $run)->where('external_id', 'MOCK-ESG-001')->value('status'));

        $latest = SourceSyncItem::query()->where('source_sync_run_id', $run)->where('external_id', 'MOCK-ESG-001')->firstOrFail();
        $this->assertSame('changed', $latest->status);

        $this->as()->get(route('admin.source-sync.index', ['run' => $run]))->assertSee('Isi teks berubah');
        $this->import($latest)->assertOk();
        $this->assertSame(1, Document::query()->count());
        $this->assertNotNull($changed);
    }

    public function test_plain_form_posts_still_redirect_without_javascript(): void
    {
        $this->as()->post(route('admin.source-sync.scan'))->assertRedirect();
        $item = $this->item();

        $this->as()->post(route('admin.source-sync.import', $item), ['category_id' => $this->category->id])
            ->assertRedirect(route('admin.source-sync.index', ['run' => $item->source_sync_run_id]))
            ->assertSessionHas('success');
    }

    public function test_guests_cannot_scan_import_or_ignore(): void
    {
        $this->postJson(route('admin.source-sync.scan'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.source-sync.index'))->assertRedirect(route('admin.login'));
    }
}
