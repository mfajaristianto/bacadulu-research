<?php

namespace Tests\Feature;

use App\Models\Collection;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminCollectionsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
    }

    private function as(): static
    {
        return $this->withSession(['research_admin_authenticated' => true, 'research_admin_user_id' => $this->admin->id]);
    }

    private function collection(string $name, string $status = 'active', ?string $description = null): Collection
    {
        return Collection::create(['name' => $name, 'slug' => Str::slug($name), 'description' => $description, 'status' => $status]);
    }

    private function document(string $title, bool $withFile = true, ?string $doi = null): Document
    {
        $document = Document::create(['uuid' => (string) Str::uuid(), 'title' => $title, 'document_type' => 'pdf', 'status' => 'published', 'doi' => $doi, 'publication_year' => 2024]);

        if ($withFile) {
            DocumentFile::create(['document_id' => $document->id, 'disk' => 'research_sources', 'path' => Str::random(20).'.pdf', 'original_name' => Str::slug($title).'.pdf', 'checksum' => hash('sha256', $title), 'status' => 'active']);
        }

        return $document;
    }

    public function test_index_shows_real_numbers_and_the_requested_copy(): void
    {
        $esg = $this->collection('ESG dan Nilai Perusahaan', description: 'Literatur ESG.');
        $audit = $this->collection('Kualitas Audit');
        $shared = $this->document('Sumber bersama');
        $other = $this->document('Sumber lain');
        $esg->documents()->attach([$shared->id, $other->id]);
        $audit->documents()->attach($shared->id); // satu dokumen di dua koleksi dihitung sekali pada "Sumber dalam koleksi"

        $this->as()->get(route('admin.collections.index'))
            ->assertOk()
            ->assertSee('Kelompokkan sumber akademik berdasarkan topik dan kebutuhan riset.')
            ->assertSeeInOrder(['Total koleksi', '2', 'Sumber dalam koleksi', '2', 'Koleksi terbaru'])
            ->assertSee('2 sumber')->assertSee('1 sumber');
    }

    public function test_empty_state_is_shown_when_nothing_exists(): void
    {
        $this->as()->get(route('admin.collections.index'))
            ->assertOk()->assertSee('Belum ada koleksi')->assertDontSee('SIMULASI');
    }

    public function test_search_status_and_sort(): void
    {
        $this->collection('Alpha', description: 'topik audit');
        $this->collection('Beta', 'archived');
        $this->collection('Gamma');

        $this->as()->get(route('admin.collections.index', ['search' => 'audit']))->assertSee('Alpha')->assertDontSee('data-name="Beta"', false);
        $this->as()->get(route('admin.collections.index', ['status' => 'archived']))->assertSee('data-name="Beta"', false)->assertDontSee('data-name="Alpha"', false);
        $this->as()->get(route('admin.collections.index', ['sort' => 'name']))->assertSeeInOrder(['data-name="Alpha"', 'data-name="Beta"', 'data-name="Gamma"'], false);
        $this->as()->get(route('admin.collections.index', ['search' => 'zzz']))->assertSee('Tidak ada koleksi yang cocok');
        $this->as()->get(route('admin.collections.index', ['sort' => 'acak']))->assertSessionHasErrors('sort');
    }

    public function test_create_normalizes_name_and_refuses_duplicates_ignoring_case_and_spaces(): void
    {
        $this->as()->postJson(route('admin.collections.store'), ['name' => '  Inklusi   Keuangan ', 'status' => 'active'])->assertOk()->assertJsonPath('ok', true);
        $existing = Collection::query()->firstOrFail();
        $this->assertSame('Inklusi Keuangan', $existing->name);

        $this->as()->postJson(route('admin.collections.store'), ['name' => 'inklusi keuangan', 'status' => 'active'])
            ->assertStatus(422)->assertJsonValidationErrors('name')
            ->assertJsonPath('existing.url', route('admin.collections.edit', $existing));

        $this->as()->postJson(route('admin.collections.store'), ['name' => '  ', 'status' => 'active'])->assertStatus(422)->assertJsonValidationErrors('name');
        $this->assertSame(1, Collection::query()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'collection.created']);
    }

    public function test_renaming_keeps_the_slug_and_the_dialog_save_never_touches_members(): void
    {
        $collection = $this->collection('Lama');
        $document = $this->document('Anggota');
        $collection->documents()->attach($document->id);

        // Simpan dari dialog: tanpa sync_documents/document_ids, anggota harus utuh.
        $this->as()->putJson(route('admin.collections.update', $collection), ['name' => 'Baru', 'description' => 'Deskripsi', 'status' => 'archived'])->assertOk();

        $collection->refresh();
        $this->assertSame(['Baru', 'lama', 'archived'], [$collection->name, $collection->slug, $collection->status]);
        $this->assertSame(1, $collection->documents()->count());

        // Nama koleksi lain tidak boleh dipakai, tetapi nama sendiri boleh disimpan ulang.
        $this->collection('Terpakai');
        $this->as()->putJson(route('admin.collections.update', $collection), ['name' => 'terpakai', 'status' => 'active'])->assertStatus(422);
        $this->as()->putJson(route('admin.collections.update', $collection), ['name' => 'BARU', 'status' => 'active'])->assertOk();
    }

    public function test_the_plain_form_checklist_still_syncs_members_when_asked(): void
    {
        $collection = $this->collection('Daftar');
        [$a, $b] = [$this->document('A'), $this->document('B')];
        $collection->documents()->attach($a->id);

        $this->as()->put(route('admin.collections.update', $collection), ['name' => 'Daftar', 'status' => 'active', 'sync_documents' => 1, 'document_ids' => [$b->id]])
            ->assertRedirect(route('admin.collections.edit', $collection));

        $this->assertSame([$b->id], $collection->documents()->pluck('documents.id')->all());
        $this->assertSame(2, Document::query()->count()); // dokumen yang terlepas tetap ada
    }

    public function test_members_panel_lists_sources_with_pustaka_viewer_links_and_no_pdf_badge_without_a_file(): void
    {
        $collection = $this->collection('ESG');
        $pdf = $this->document('Dokumen PDF', true, '10.0000/sim.esg.001');
        $text = $this->document('Sumber teks', false);
        $collection->documents()->attach([$pdf->id, $text->id]);
        $file = DocumentFile::query()->where('document_id', $pdf->id)->firstOrFail();

        $html = $this->as()->get(route('admin.collections.members', $collection))->assertOk()
            ->assertSee('2')->assertSee('Dokumen PDF')->assertSee('DOI 10.0000/sim.esg.001')
            ->assertSee(route('admin.library.files.view', $file), false)
            ->assertSee(route('admin.collections.detach', [$collection, $pdf]), false)
            ->getContent();

        $this->assertSame(1, substr_count($html, 'bd-col__pdf"'), 'badge PDF hanya untuk dokumen yang punya file');
    }

    public function test_candidates_exclude_members_and_search_by_title_or_doi(): void
    {
        $collection = $this->collection('ESG');
        $member = $this->document('Sudah anggota');
        $free = $this->document('Belum anggota', true, '10.0000/sim.free');
        $collection->documents()->attach($member->id);

        $this->as()->get(route('admin.collections.candidates', $collection))->assertOk()
            ->assertSee('Belum anggota')->assertDontSee('Sudah anggota');
        $this->as()->get(route('admin.collections.candidates', ['collection' => $collection, 'q' => 'sim.free']))->assertSee('Belum anggota');
        $this->as()->get(route('admin.collections.candidates', ['collection' => $collection, 'q' => 'tidak-ada']))->assertSee('Tidak ada sumber yang cocok');
    }

    public function test_attach_never_creates_a_duplicate_relation_and_a_document_can_join_several_collections(): void
    {
        $one = $this->collection('Satu');
        $two = $this->collection('Dua');
        $document = $this->document('Bersama');
        $other = $this->document('Lain');

        $this->as()->postJson(route('admin.collections.attach', $one), ['document_ids' => [$document->id]])
            ->assertOk()->assertJsonPath('added', 1)->assertJsonPath('count', 1);

        // Kirim ulang + satu baru: yang sudah ada dilewati, tidak ada baris ganda.
        $this->as()->postJson(route('admin.collections.attach', $one), ['document_ids' => [$document->id, $other->id]])
            ->assertOk()->assertJsonPath('added', 1)->assertJsonPath('skipped', 1)->assertJsonPath('count', 2);

        $this->as()->postJson(route('admin.collections.attach', $two), ['document_ids' => [$document->id]])->assertOk();

        $this->assertSame(3, DB::table('collection_document')->count());
        $this->assertSame(2, Document::query()->count()); // tidak ada dokumen/PDF yang digandakan
        $this->assertSame(2, DocumentFile::query()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'collection.sources_added']);

        $this->as()->postJson(route('admin.collections.attach', $one), ['document_ids' => []])->assertStatus(422)->assertJsonValidationErrors('document_ids');
        $this->as()->postJson(route('admin.collections.attach', $one), ['document_ids' => [999]])->assertStatus(422);
        $this->as()->postJson(route('admin.collections.attach', $one), ['document_ids' => [$document->id, $document->id]])->assertStatus(422);
    }

    public function test_removing_a_source_only_cuts_the_relation(): void
    {
        $one = $this->collection('Satu');
        $two = $this->collection('Dua');
        $document = $this->document('Bersama');
        $one->documents()->attach($document->id);
        $two->documents()->attach($document->id);
        $file = DocumentFile::query()->firstOrFail();

        $this->as()->deleteJson(route('admin.collections.detach', [$one, $document]))->assertOk()->assertJsonPath('count', 0);

        $this->assertDatabaseHas('documents', ['id' => $document->id, 'status' => 'published']);
        $this->assertDatabaseHas('document_files', ['id' => $file->id, 'path' => $file->path]);
        $this->assertSame(0, $one->documents()->count());
        $this->assertSame(1, $two->documents()->count()); // koleksi lain tidak terpengaruh
        $this->assertDatabaseHas('audit_logs', ['action' => 'collection.source_removed']);

        $this->as()->deleteJson(route('admin.collections.detach', [$one, $document]))->assertStatus(409);
    }

    public function test_guests_are_sent_to_the_admin_login(): void
    {
        $collection = $this->collection('X');

        $this->get(route('admin.collections.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.collections.members', $collection))->assertRedirect(route('admin.login'));
        $this->postJson(route('admin.collections.attach', $collection), ['document_ids' => [1]])->assertRedirect(route('admin.login'));
        $this->deleteJson(route('admin.collections.detach', [$collection, $this->document('D')]))->assertRedirect(route('admin.login'));
    }
}
