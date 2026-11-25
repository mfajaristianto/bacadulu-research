<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminCategoriesTest extends TestCase
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

    private function make(string $name, ?Category $parent = null, string $status = 'active', ?string $slug = null): Category
    {
        return Category::create(['name' => $name, 'slug' => $slug ?? Str::slug($name), 'parent_id' => $parent?->id, 'status' => $status]);
    }

    private function store(array $data)
    {
        return $this->as()->postJson(route('admin.categories.store'), $data + ['status' => 'active']);
    }

    public function test_index_shows_real_counts_and_the_hierarchy(): void
    {
        $parent = $this->make('Akuntansi');
        $child = $this->make('Akuntansi Keuangan', $parent);
        $this->make('Hukum', status: 'archived');
        Document::create(['uuid' => (string) Str::uuid(), 'title' => 'Sumber', 'category_id' => $child->id, 'document_type' => 'pdf', 'status' => 'draft']);

        $this->as()->get(route('admin.categories.index'))
            ->assertOk()
            ->assertSee('Kelola pengelompokan sumber akademik agar pustaka lebih terstruktur.')
            ->assertSeeInOrder(['Total kategori', '3', 'Aktif', '2', 'Subkategori', '1', 'Memiliki sumber', '1'])
            ->assertSeeInOrder(['Akuntansi', 'Akuntansi Keuangan', 'subkategori dari Akuntansi'])
            ->assertSee('1 sumber');
    }

    public function test_search_status_and_level_filters_keep_parents_for_context(): void
    {
        $parent = $this->make('Akuntansi');
        $this->make('Pajak', $parent);
        $this->make('Hukum', status: 'archived');

        $this->as()->get(route('admin.categories.index', ['search' => 'pajak']))
            ->assertSee('Pajak')->assertSee('is-context', false)->assertDontSee('data-slug="hukum"', false);
        $this->as()->get(route('admin.categories.index', ['status' => 'archived']))
            ->assertSee('data-slug="hukum"', false)->assertDontSee('data-slug="pajak"', false);
        $this->as()->get(route('admin.categories.index', ['level' => 'sub']))
            ->assertSee('Pajak')->assertDontSee('data-slug="hukum"', false);
        $this->as()->get(route('admin.categories.index', ['search' => 'zzz']))->assertSee('Tidak ada kategori yang cocok');
        $this->as()->get(route('admin.categories.index', ['status' => 'x']))->assertSessionHasErrors('status');
    }

    public function test_create_normalizes_the_name_and_builds_a_unique_slug(): void
    {
        $this->store(['name' => '  Ekonomi    Syariah  '])->assertOk()->assertJsonPath('ok', true);
        $this->assertDatabaseHas('categories', ['name' => 'Ekonomi Syariah', 'slug' => 'ekonomi-syariah']);

        // Nama beda induk boleh sama, tetapi slug otomatis tidak boleh bentrok.
        $parent = $this->make('Hukum');
        $this->store(['name' => 'Ekonomi Syariah', 'parent_id' => $parent->id])->assertOk();
        $this->assertDatabaseHas('categories', ['slug' => 'ekonomi-syariah-2', 'parent_id' => $parent->id]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'category.created']);
    }

    public function test_name_is_required(): void
    {
        $this->store(['name' => '   '])->assertStatus(422)->assertJsonValidationErrors('name');
        $this->assertSame(0, Category::query()->count());
    }

    public function test_duplicate_names_are_refused_ignoring_case_and_extra_spaces_with_a_pointer_to_the_existing_one(): void
    {
        $existing = $this->make('Akuntansi Keuangan');

        $this->store(['name' => '  akuntansi   KEUANGAN '])
            ->assertStatus(422)
            ->assertJsonPath('existing.id', $existing->id)
            ->assertJsonPath('existing.url', route('admin.categories.edit', $existing))
            ->assertJsonValidationErrors('name');

        $this->assertSame(1, Category::query()->count());
    }

    public function test_explicit_slug_conflicts_are_reported_instead_of_silently_changed(): void
    {
        $existing = $this->make('Hukum', slug: 'hukum');

        $this->store(['name' => 'Hukum Pajak', 'slug' => 'Hukum'])
            ->assertStatus(422)->assertJsonValidationErrors('slug')->assertJsonPath('existing.id', $existing->id);

        $this->store(['name' => 'Hukum Pajak', 'slug' => 'hukum-pajak'])->assertOk();
        $this->assertDatabaseHas('categories', ['name' => 'Hukum Pajak', 'slug' => 'hukum-pajak']);
    }

    public function test_renaming_never_changes_the_slug_by_itself(): void
    {
        $category = $this->make('Akuntansi', slug: 'akuntansi');

        $this->as()->putJson(route('admin.categories.update', $category), ['name' => 'Akuntansi dan Audit', 'slug' => 'akuntansi', 'status' => 'active'])->assertOk();
        $this->assertSame('akuntansi', $category->fresh()->slug);

        // Slug kosong saat edit juga berarti "pertahankan", bukan "buat ulang dari nama".
        $this->as()->putJson(route('admin.categories.update', $category), ['name' => 'Audit', 'slug' => '', 'status' => 'active'])->assertOk();
        $this->assertSame('akuntansi', $category->fresh()->slug);

        $this->as()->putJson(route('admin.categories.update', $category), ['name' => 'Audit', 'slug' => 'audit-internal', 'status' => 'active'])->assertOk();
        $this->assertSame('audit-internal', $category->fresh()->slug);
    }

    public function test_a_category_cannot_be_its_own_parent_or_move_under_its_descendant(): void
    {
        $root = $this->make('Akuntansi');
        $child = $this->make('Pajak', $root);
        $grandchild = $this->make('PPh', $child);

        $update = fn (Category $c, ?int $parent) => $this->as()->putJson(route('admin.categories.update', $c), ['name' => $c->name, 'slug' => $c->slug, 'parent_id' => $parent, 'status' => 'active']);

        $update($root, $root->id)->assertStatus(422)->assertJsonValidationErrors('parent_id');
        $update($root, $child->id)->assertStatus(422)->assertJsonValidationErrors('parent_id');
        $update($root, $grandchild->id)->assertStatus(422)->assertJsonValidationErrors('parent_id');
        $this->assertNull($root->fresh()->parent_id);

        $update($grandchild, $root->id)->assertOk(); // memindahkan ke atas tetap boleh
        $this->assertSame($root->id, $grandchild->fresh()->parent_id);
    }

    public function test_archived_categories_cannot_become_a_new_parent_but_an_existing_link_survives(): void
    {
        $archived = $this->make('Lama', status: 'archived');

        $this->store(['name' => 'Baru', 'parent_id' => $archived->id])->assertStatus(422)->assertJsonValidationErrors('parent_id');

        $child = $this->make('Anak', $archived);
        $this->as()->putJson(route('admin.categories.update', $child), ['name' => 'Anak Baru', 'slug' => 'anak', 'parent_id' => $archived->id, 'status' => 'active'])->assertOk();
        $this->assertSame($archived->id, $child->fresh()->parent_id);
    }

    public function test_archiving_keeps_the_sources_attached_and_counted(): void
    {
        $category = $this->make('Ekonomi');
        $document = Document::create(['uuid' => (string) Str::uuid(), 'title' => 'Sumber', 'category_id' => $category->id, 'document_type' => 'pdf', 'status' => 'published']);

        $this->as()->putJson(route('admin.categories.update', $category), ['name' => 'Ekonomi', 'slug' => 'ekonomi', 'status' => 'archived'])->assertOk();

        $this->assertSame($category->id, $document->fresh()->category_id);
        $this->assertSame('published', $document->fresh()->status);
        $this->as()->get(route('admin.categories.index'))->assertSee('1 sumber')->assertSee('Diarsipkan');
    }

    public function test_check_endpoint_reports_conflicts_without_saving(): void
    {
        $parent = $this->make('Hukum');
        $existing = $this->make('Pajak', $parent, slug: 'pajak');

        $this->as()->getJson(route('admin.categories.check', ['name' => ' PAJAK ', 'parent_id' => $parent->id, 'slug' => 'pajak']))
            ->assertOk()->assertJsonPath('name.id', $existing->id)->assertJsonPath('slug.id', $existing->id);

        $this->as()->getJson(route('admin.categories.check', ['name' => 'Pajak']))
            ->assertOk()->assertJsonPath('name', null)->assertJsonPath('elsewhere.id', $existing->id);

        $this->as()->getJson(route('admin.categories.check', ['name' => 'Pajak', 'parent_id' => $parent->id, 'ignore' => $existing->id]))
            ->assertOk()->assertJsonPath('name', null);

        $this->assertSame(2, Category::query()->count());
    }

    public function test_plain_form_posts_still_redirect_without_javascript(): void
    {
        $this->as()->post(route('admin.categories.store'), ['name' => 'Etika', 'status' => 'active'])
            ->assertRedirect(route('admin.categories.index'))->assertSessionHas('success');

        $this->as()->from(route('admin.categories.create'))->post(route('admin.categories.store'), ['name' => 'etika', 'status' => 'active'])
            ->assertRedirect(route('admin.categories.create'))->assertSessionHasErrors('name');

        $this->as()->get(route('admin.categories.create'))->assertOk()->assertSee('Nama kategori');
        $this->as()->get(route('admin.categories.edit', Category::query()->firstOrFail()))->assertOk()->assertSee('Etika');
    }

    public function test_guests_are_sent_to_the_admin_login(): void
    {
        $this->get(route('admin.categories.index'))->assertRedirect(route('admin.login'));
        $this->postJson(route('admin.categories.store'), ['name' => 'X'])->assertRedirect(route('admin.login'));
        $this->getJson(route('admin.categories.check', ['name' => 'X']))->assertRedirect(route('admin.login'));
    }
}
