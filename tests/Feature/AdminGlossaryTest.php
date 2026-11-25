<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\GlossaryTerm;
use App\Models\User;
use App\Services\Research\GlossaryContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminGlossaryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->category = Category::create(['name' => 'Sustainability', 'slug' => 'sustainability', 'status' => 'active']);
    }

    private function as(): static
    {
        return $this->withSession(['research_admin_authenticated' => true, 'research_admin_user_id' => $this->admin->id]);
    }

    private function term(string $term, array $extra = []): GlossaryTerm
    {
        return GlossaryTerm::create($extra + ['term' => $term, 'slug' => Str::slug($term), 'definition' => 'Definisi '.$term, 'status' => 'active']);
    }

    private function store(array $data)
    {
        return $this->as()->postJson(route('admin.glossary.store'), $data + ['definition' => 'Definisi uji', 'status' => 'active']);
    }

    public function test_index_shows_real_numbers_and_the_requested_copy(): void
    {
        $this->term('ESG', ['category_id' => $this->category->id, 'aliases_json' => ['Keberlanjutan', 'Environmental Social Governance']]);
        $this->term('Audit Tenure', ['status' => 'inactive', 'aliases_json' => ['Masa audit']]);

        $this->as()->get(route('admin.glossary.index'))
            ->assertOk()
            ->assertSee('Padanan dan sinonim untuk pencarian dwibahasa dan perluasan kata kunci')
            ->assertSeeInOrder(['Total istilah', '2', 'Istilah aktif', '1', 'Kategori terpakai', '1', 'Alias', '3'])
            ->assertSee('Glosarium bukan sumber evidence');
    }

    public function test_empty_state_is_honest(): void
    {
        $this->as()->get(route('admin.glossary.index'))->assertOk()->assertSee('Glosarium masih kosong')->assertDontSee('ESG');
    }

    public function test_search_covers_term_alias_definition_and_filters_sort(): void
    {
        $this->term('Transfer Pricing', ['definition' => 'Harga transaksi antar pihak berelasi', 'aliases_json' => ['Harga transfer']]);
        $this->term('ESG', ['category_id' => $this->category->id]);
        $this->term('Green Accounting', ['status' => 'inactive']);

        $slug = fn (string $s) => 'data-term="'.$s.'"';
        $index = fn (array $q) => $this->as()->get(route('admin.glossary.index', $q))->assertOk();

        $index(['search' => 'transfer'])->assertSee($slug('Transfer Pricing'), false)->assertDontSee($slug('ESG'), false);
        $index(['search' => 'harga transfer'])->assertSee($slug('Transfer Pricing'), false); // alias
        $index(['search' => 'antar pihak'])->assertSee($slug('Transfer Pricing'), false);    // definisi
        $index(['category' => $this->category->id])->assertSee($slug('ESG'), false)->assertDontSee($slug('Transfer Pricing'), false);
        $index(['category' => 'general'])->assertSee($slug('Transfer Pricing'), false)->assertDontSee($slug('ESG'), false);
        $index(['status' => 'inactive'])->assertSee($slug('Green Accounting'), false)->assertDontSee($slug('ESG'), false);
        $index([])->assertSeeInOrder([$slug('ESG'), $slug('Green Accounting'), $slug('Transfer Pricing')], false);
        $index(['search' => 'tidak-ada'])->assertSee('Tidak ada istilah yang cocok');
        $this->as()->get(route('admin.glossary.index', ['sort' => 'acak']))->assertSessionHasErrors('sort');
    }

    public function test_create_normalizes_and_stores_aliases_from_array_or_plain_text(): void
    {
        $this->store(['term' => '  Corporate   Governance ', 'aliases' => [' Tata  kelola ', 'tata kelola', 'CORPORATE GOVERNANCE', '', 'GCG']])->assertOk();
        $term = GlossaryTerm::query()->firstOrFail();

        $this->assertSame('Corporate Governance', $term->term);
        $this->assertSame(['Tata kelola', 'GCG'], $term->aliases_json); // duplikat dan alias = istilah utama dibuang

        $this->as()->post(route('admin.glossary.store'), ['term' => 'Financial Inclusion', 'definition' => 'Inklusi keuangan', 'status' => 'active', 'aliases' => "Keuangan inklusif, inklusi\n keuangan inklusif"])
            ->assertRedirect()->assertSessionHas('success');
        $this->assertSame(['Keuangan inklusif', 'inklusi'], GlossaryTerm::query()->where('term', 'Financial Inclusion')->firstOrFail()->aliases_json);
        $this->assertDatabaseHas('audit_logs', ['action' => 'glossary.created']);
    }

    public function test_required_fields_and_alias_limits(): void
    {
        $this->store(['term' => ' ', 'definition' => ''])->assertStatus(422)->assertJsonValidationErrors(['term', 'definition']);
        $this->store(['term' => 'X', 'aliases' => array_map(fn ($i) => "alias {$i}", range(1, 31))])->assertStatus(422)->assertJsonValidationErrors('aliases');
        $this->store(['term' => 'Y', 'aliases' => [str_repeat('a', 121)]])->assertStatus(422)->assertJsonValidationErrors('aliases.0');
        $this->assertSame(0, GlossaryTerm::query()->count());
    }

    public function test_duplicates_are_refused_per_scope_with_a_pointer_to_the_existing_term(): void
    {
        $esg = $this->term('ESG');

        $this->store(['term' => '  esg '])->assertStatus(422)->assertJsonValidationErrors('term')->assertJsonPath('existing.url', route('admin.glossary.edit', $esg));

        // Cakupan berbeda (kategori) boleh memakai istilah yang sama: maknanya bisa berbeda.
        $this->store(['term' => 'ESG', 'category_id' => $this->category->id])->assertOk();
        $this->store(['term' => 'ESG', 'category_id' => $this->category->id])->assertStatus(422);
        $this->assertSame(2, GlossaryTerm::query()->count());
    }

    public function test_alias_conflicts_with_other_terms_or_aliases_in_the_same_scope_are_refused(): void
    {
        $this->term('Tax Avoidance', ['aliases_json' => ['Penghindaran pajak']]);

        $this->store(['term' => 'Tax Planning', 'aliases' => ['penghindaran  PAJAK']])->assertStatus(422)->assertJsonValidationErrors('aliases');
        $this->store(['term' => 'Tax Planning', 'aliases' => ['tax avoidance']])->assertStatus(422)->assertJsonValidationErrors('aliases');
        $this->store(['term' => 'penghindaran pajak'])->assertStatus(422)->assertJsonValidationErrors('term'); // istilah = alias istilah lain

        $this->store(['term' => 'Tax Planning', 'aliases' => ['perencanaan pajak']])->assertOk();
        $this->store(['term' => 'Tax Planning', 'category_id' => $this->category->id, 'aliases' => ['penghindaran pajak']])->assertOk(); // kategori lain
    }

    public function test_editing_keeps_the_slug_allows_saving_itself_and_blocks_stealing_an_alias(): void
    {
        $a = $this->term('Alpha', ['slug' => 'alpha', 'aliases_json' => ['satu']]);
        $this->term('Beta', ['aliases_json' => ['dua']]);

        $update = fn (GlossaryTerm $t, array $data) => $this->as()->putJson(route('admin.glossary.update', $t), $data + ['status' => 'active', 'definition' => 'D']);

        $update($a, ['term' => 'Alpha Baru', 'aliases' => ['satu', 'tiga']])->assertOk();
        $this->assertSame(['alpha', 'Alpha Baru', ['satu', 'tiga']], [$a->fresh()->slug, $a->fresh()->term, $a->fresh()->aliases_json]);

        $update($a, ['term' => 'Alpha Baru', 'aliases' => ['dua']])->assertStatus(422)->assertJsonValidationErrors('aliases');
        $this->assertSame(['satu', 'tiga'], $a->fresh()->aliases_json);
        $this->assertDatabaseHas('audit_logs', ['action' => 'glossary.updated']);
    }

    public function test_check_endpoint_reports_conflicts_without_saving(): void
    {
        $other = $this->term('Tax Avoidance', ['aliases_json' => ['Penghindaran pajak']]);
        $cat = $this->term('Tax Avoidance', ['category_id' => $this->category->id, 'slug' => 'tax-avoidance-2']);

        $this->as()->getJson(route('admin.glossary.check', ['term' => ' TAX avoidance ']))
            ->assertOk()->assertJsonPath('term.id', $other->id);
        $this->as()->getJson(route('admin.glossary.check', ['term' => 'Baru', 'aliases' => ['penghindaran pajak']]))
            ->assertOk()->assertJsonPath('aliases.0.id', $other->id)->assertJsonPath('term', null);
        $this->as()->getJson(route('admin.glossary.check', ['term' => 'Tax Avoidance', 'category' => $this->category->id, 'ignore' => $cat->id]))
            ->assertOk()->assertJsonPath('term', null)->assertJsonPath('elsewhere.id', $other->id);
        $this->assertSame(2, GlossaryTerm::query()->count());
    }

    public function test_deactivating_hides_the_term_from_the_research_engine_but_keeps_it(): void
    {
        $term = $this->term('Transfer Pricing', ['aliases_json' => ['Harga transfer']]);
        $engine = app(GlossaryContextService::class);

        // Kontrak data existing: istilah aktif terbaca lewat alias, dengan definisi dan alias lengkap.
        $found = $engine->resolve('Apa itu harga transfer di perusahaan multinasional?');
        $this->assertSame(['Transfer Pricing', ['Harga transfer']], [$found[0]['term'], $found[0]['aliases']]);

        $this->as()->deleteJson(route('admin.glossary.destroy', $term))->assertOk()->assertJsonPath('ok', true);

        $this->assertSame('inactive', $term->fresh()->status);
        $this->assertSame([], $engine->resolve('Apa itu harga transfer di perusahaan multinasional?'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'glossary.deactivated']);
    }

    public function test_the_form_pages_work_without_javascript(): void
    {
        $term = $this->term('ESG', ['aliases_json' => ['Keberlanjutan', 'Sustainability']]);

        $this->as()->get(route('admin.glossary.create'))->assertOk()->assertSee('Alias atau sinonim');
        $this->as()->get(route('admin.glossary.edit', $term))->assertOk()->assertSee('Keberlanjutan, Sustainability');

        $this->as()->from(route('admin.glossary.create'))->post(route('admin.glossary.store'), ['term' => 'esg', 'definition' => 'D', 'status' => 'active'])
            ->assertRedirect(route('admin.glossary.create'))->assertSessionHasErrors('term');
    }

    public function test_guests_are_sent_to_the_admin_login(): void
    {
        $term = $this->term('ESG');

        $this->get(route('admin.glossary.index'))->assertRedirect(route('admin.login'));
        $this->postJson(route('admin.glossary.store'), ['term' => 'X'])->assertRedirect(route('admin.login'));
        $this->deleteJson(route('admin.glossary.destroy', $term))->assertRedirect(route('admin.login'));
        $this->getJson(route('admin.glossary.check', ['term' => 'X']))->assertRedirect(route('admin.login'));
    }
}
