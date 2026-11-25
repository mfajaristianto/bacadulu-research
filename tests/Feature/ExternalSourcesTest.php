<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\ExternalCategoryMapping;
use App\Models\ExternalPublication;
use App\Models\ExternalPublicationCategory;
use App\Models\ExternalPublicationSource;
use App\Models\ExternalSyncRun;
use App\Models\ResearchSetting;
use App\Models\User;
use App\Services\ExternalSources\ExternalSourceException;
use App\Services\ExternalSources\ExternalSourceRegistry;
use App\Services\ExternalSources\PublicationSearch;
use App\Services\ExternalSources\SafeHttp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Semua panggilan penyedia di-FAKE (tanpa jaringan, tanpa biaya, tanpa AI). Ini membuktikan kontrak dan logika kita,
 * bukan bahwa penyedia live berfungsi: bentuk respons difiksasi dari dokumentasi resmi dan satu pengecekan GET publik.
 */
class ExternalSourcesTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'oa-test-SECRET-KEY-123';

    private User $admin;

    private Category $cat;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->admin = User::factory()->create();
        $this->cat = Category::create(['name' => 'Akuntansi', 'slug' => 'akuntansi', 'status' => 'active']);
    }

    private function as(): static
    {
        return $this->withSession(['research_admin_authenticated' => true, 'research_admin_user_id' => $this->admin->id]);
    }

    private function enable(string $provider, array $extra = []): void
    {
        $this->as()->post(route('admin.external-sources.settings', $provider), ['enabled' => 1] + $extra)->assertRedirect();
    }

    private function sync(string $provider, array $over = [])
    {
        return $this->as()->postJson(route('admin.external-sources.sync', $provider), $over + ['category_id' => $this->cat->id, 'terms' => 'financial reporting', 'max_records' => 50]);
    }

    private function work(string $id, string $title, ?string $doi = null, array $over = []): array
    {
        return array_replace([
            'id' => "https://openalex.org/{$id}", 'doi' => $doi ? "https://doi.org/{$doi}" : null, 'title' => $title, 'publication_year' => 2023, 'publication_date' => '2023-05-01',
            'language' => 'en', 'type' => 'article', 'open_access' => ['is_oa' => true, 'oa_status' => 'gold', 'oa_url' => 'https://example.org/paper.pdf'],
            'primary_location' => ['landing_page_url' => 'https://example.org/paper', 'license' => 'cc-by', 'source' => ['display_name' => 'Journal of Tests', 'host_organization_name' => 'Test Press', 'issn' => ['1234-5678']]],
            'authorships' => [['author' => ['display_name' => 'Ada Lovelace']], ['author' => ['display_name' => 'Alan Turing']]],
            'keywords' => [['display_name' => 'accounting']], 'abstract_inverted_index' => ['Metadata' => [0], 'only' => [1], 'abstract' => [2]],
        ], $over);
    }

    private function oa(array $works, ?string $next = null, int $count = 100): \Illuminate\Http\Client\Response|\GuzzleHttp\Promise\PromiseInterface
    {
        return Http::response(['meta' => ['count' => $count, 'next_cursor' => $next], 'results' => $works]);
    }

    private function crossrefItem(string $doi, string $title, array $over = []): array
    {
        return array_replace(['DOI' => strtoupper($doi), 'title' => [$title], 'author' => [['given' => 'Grace', 'family' => 'Hopper']], 'issued' => ['date-parts' => [[2022, 3, 9]]],
            'container-title' => ['Crossref Journal'], 'publisher' => 'CR Press', 'type' => 'journal-article', 'ISSN' => ['1111-2222'], 'URL' => 'https://doi.org/'.$doi,
            'abstract' => '<jats:p>Abstrak <jats:italic>JATS</jats:italic> &amp; bersih.</jats:p>', 'subject' => ['Accounting']], $over);
    }

    /* --------------------------------------------------------------------------- registry & akses */

    /** MySQL/MariaDB menolak pengenal > 64 karakter, SQLite tidak: tanpa tes ini galat baru muncul saat migrate di server sungguhan. */
    public function test_every_generated_identifier_fits_the_mysql_64_character_limit(): void
    {
        $tables = ['external_publications', 'external_publication_sources', 'external_publication_categories', 'external_category_mappings', 'external_sync_runs'];
        $names = [];

        foreach ($tables as $table) {
            $names[] = $table;
            foreach (Schema::getIndexes($table) as $index) {
                $names[] = $index['name'];
            }
            foreach (Schema::getColumnListing($table) as $column) {
                $names[] = $column;
            }
            // Nama FK otomatis Laravel: {tabel}_{kolom}_foreign.
            foreach (Schema::getForeignKeys($table) as $fk) {
                $names[] = $table.'_'.implode('_', $fk['columns']).'_foreign';
            }
        }

        $tooLong = array_values(array_filter(array_unique($names), fn ($n) => strlen($n) > 64));
        $this->assertSame([], $tooLong, 'pengenal melebihi 64 karakter');
    }

    public function test_registry_has_three_working_adapters_and_honest_catalog(): void
    {
        $dir = collect(app(ExternalSourceRegistry::class)->directory());

        $this->assertEqualsCanonicalizing(['openalex', 'crossref', 'doaj'], $dir->where('implemented', true)->pluck('code')->all());
        $pending = $dir->where('implemented', false);
        $this->assertGreaterThanOrEqual(8, $pending->count());
        $this->assertTrue($pending->every(fn ($c) => in_array($c['status'], ['PENDING_VERIFICATION', 'REQUIRES_CONFIGURATION', 'NOT_SUPPORTED', 'METADATA_ONLY'], true)));
        $this->assertSame(['SUPPORTED'], $dir->where('implemented', true)->pluck('status')->unique()->all());
    }

    public function test_pages_require_admin_and_page_loads_for_admin(): void
    {
        $this->get(route('admin.external-sources.index'))->assertRedirect(route('admin.login'));
        $this->post(route('admin.external-sources.sync', 'openalex'))->assertRedirect(route('admin.login'));
        $this->post(route('admin.external-sources.settings', 'openalex'))->assertRedirect(route('admin.login'));
        $this->getJson(route('admin.external-sources.status'))->assertRedirect(route('admin.login'));

        $this->as()->get(route('admin.external-sources.index'))->assertOk()
            ->assertSee('Sumber Eksternal')->assertSee('OpenAlex')->assertSee('Crossref')->assertSee('DOAJ')
            ->assertSee('PENDING_VERIFICATION')->assertSee('Belum ada data untuk divisualisasikan');
    }

    public function test_sidebar_links_the_new_page_next_to_library_without_replacing_old_routes(): void
    {
        $this->as()->get(route('admin.source-sync.index'))->assertOk()
            ->assertSee(route('admin.external-sources.index'), false)->assertSee('Impor Sumber')->assertSee('Pustaka');
    }

    public function test_unknown_provider_is_404_and_page_survives_missing_tables(): void
    {
        $this->as()->postJson(route('admin.external-sources.test', 'nope'))->assertNotFound();
        $this->as()->postJson(route('admin.external-sources.sync', 'doaj-fake'))->assertNotFound();

        foreach (['external_sync_runs', 'external_category_mappings', 'external_publication_categories', 'external_publication_sources', 'external_publications'] as $t) {
            Schema::dropIfExists($t);
        }

        $this->as()->get(route('admin.external-sources.index'))->assertOk()->assertSee('Penyimpanan belum disiapkan')->assertSee('menunggu persetujuan');
        $this->as()->postJson(route('admin.external-sources.sync', 'openalex'), ['terms' => 'x'])->assertStatus(503);
    }

    /* ---------------------------------------------------------------------- pengaturan & rahasia */

    public function test_provider_is_disabled_by_default_and_blocks_preview_and_sync(): void
    {
        Http::fake();

        $this->as()->postJson(route('admin.external-sources.preview', 'openalex'), ['terms' => 'x', 'category_id' => $this->cat->id])->assertStatus(422)->assertJsonPath('ok', false);
        $this->sync('openalex')->assertStatus(422)->assertJsonPath('ok', false);
        $this->assertSame(0, ExternalSyncRun::count());
        Http::assertNothingSent();
    }

    public function test_api_key_is_stored_encrypted_never_echoed_and_never_audited(): void
    {
        $this->enable('openalex', ['api_key' => self::KEY, 'contact' => 'kontak@lembaga.test']);

        $stored = ResearchSetting::query()->where('key', 'external.openalex.api_key')->value('value');
        $this->assertNotSame(self::KEY, $stored);
        $this->assertStringNotContainsString('SECRET', $stored);

        $page = $this->as()->get(route('admin.external-sources.index'))->assertOk();
        $this->assertStringNotContainsString(self::KEY, $page->getContent());
        $this->assertStringNotContainsString('SECRET', $page->getContent());
        $this->assertStringNotContainsString(self::KEY, json_encode(AuditLog::all()->toArray()));
        $this->assertTrue(AuditLog::where('action', 'external_source.settings_updated')->exists());

        $this->as()->post(route('admin.external-sources.settings', 'openalex'), ['enabled' => 1, 'clear_api_key' => 1])->assertRedirect();
        $this->assertNull(ResearchSetting::query()->where('key', 'external.openalex.api_key')->first());
    }

    public function test_openalex_key_from_services_config_is_sent_as_bearer_and_admin_key_overrides_it(): void
    {
        config(['services.openalex.api_key' => self::KEY, 'external-sources.providers.openalex.api_key' => '']);
        Http::fake(['api.openalex.org/*' => $this->oa([], null, 5)]);

        $this->as()->postJson(route('admin.external-sources.test', 'openalex'))->assertOk()->assertJsonPath('ok', true);
        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer '.self::KEY) && ! str_contains($r->url(), self::KEY));

        $page = $this->as()->get(route('admin.external-sources.index'))->assertOk();
        $this->assertStringNotContainsString(self::KEY, $page->getContent());

        $this->enable('openalex', ['api_key' => 'admin-key-OVERRIDE']);
        Http::fake(['api.openalex.org/*' => $this->oa([], null, 5)]);
        $this->as()->postJson(route('admin.external-sources.test', 'openalex'))->assertOk();
        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer admin-key-OVERRIDE'));
    }

    public function test_settings_validation_rejects_bad_contact_and_key(): void
    {
        $this->as()->post(route('admin.external-sources.settings', 'openalex'), ['contact' => 'bukan-email'])->assertSessionHasErrors('contact');
        $this->as()->post(route('admin.external-sources.settings', 'openalex'), ['api_key' => 'ada spasi & simbol'])->assertSessionHasErrors('api_key');
    }

    public function test_unconfigured_connectors_do_not_break_existing_pages(): void
    {
        $this->as()->get(route('admin.dashboard'))->assertOk();
        $this->as()->get(route('admin.library.index'))->assertOk();
        $this->as()->get(route('admin.source-sync.index'))->assertOk();
        $this->assertSame(0, ResearchSetting::query()->where('key', 'like', 'external.%')->count());
    }

    /* -------------------------------------------------------------------------- tes koneksi */

    public function test_connection_test_reports_success_and_failure_and_records_it(): void
    {
        Http::fake(['api.openalex.org/*' => $this->oa([], null, 5), 'doaj.org/*' => Http::response([], 401)]);

        $this->as()->postJson(route('admin.external-sources.test', 'openalex'))->assertOk()->assertJsonPath('ok', true);
        $this->as()->postJson(route('admin.external-sources.test', 'doaj'))->assertOk()->assertJsonPath('ok', false);
        $this->assertTrue(Cache::get('external-sources:conn:openalex')['ok']);
        $this->assertFalse(Cache::get('external-sources:conn:doaj')['ok']);
        $this->as()->get(route('admin.external-sources.index'))->assertSee('Koneksi baik')->assertSee('Koneksi gagal');
    }

    /* ----------------------------------------------------------------------------------- preview */

    public function test_preview_returns_samples_without_writing_and_escapes_hostile_metadata(): void
    {
        Http::fake(['api.openalex.org/*' => $this->oa([$this->work('W1', '<script>alert(1)</script> Laporan Keuangan Tom & Jerry', '10.1000/xss')], 'c2', 321)]);
        $this->enable('openalex');

        $response = $this->as()->postJson(route('admin.external-sources.preview', 'openalex'), ['terms' => 'financial reporting', 'category_id' => $this->cat->id])->assertOk()->assertJsonPath('ok', true);

        $html = $response->json('html');
        $this->assertStringContainsString('Pratinjau, belum disimpan', $html);
        $this->assertStringContainsString('alert(1) Laporan Keuangan', $html, 'tag HTML dari penyedia dibuang saat normalisasi');
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringContainsString('Tom &amp; Jerry', $html, 'sisa teks di-escape oleh Blade');
        $this->assertSame(0, ExternalPublication::count());
        $this->assertSame(0, ExternalSyncRun::count());
        $this->assertSame(0, ExternalPublicationSource::count());
    }

    public function test_invalid_category_unsupported_filter_and_bad_year_range_are_rejected(): void
    {
        Http::fake();
        $this->enable('openalex');
        $this->enable('crossref');

        $this->sync('openalex', ['category_id' => 99999])->assertStatus(422);
        $this->sync('crossref', ['language' => 'id'])->assertStatus(422)->assertJsonPath('ok', false);
        $this->sync('openalex', ['year_from' => 2024, 'year_to' => 2020])->assertStatus(422);
        $this->sync('openalex', ['type' => 'film'])->assertStatus(422);
        $this->sync('openalex', ['terms' => '!!! ###'])->assertStatus(422);
        Http::assertNothingSent();
    }

    /* ------------------------------------------------------------------- sync OpenAlex (kontrak) */

    public function test_openalex_sync_with_key_uses_cursor_bearer_and_free_filters_only(): void
    {
        Http::fake(['api.openalex.org/*' => Http::sequence()
            ->push(['meta' => ['count' => 3, 'next_cursor' => 'CUR2'], 'results' => [$this->work('W1', 'Laporan Keuangan A', '10.1000/a'), $this->work('W2', 'Laporan Keuangan B', '10.1000/b')]])
            ->push(['meta' => ['count' => 3, 'next_cursor' => null], 'results' => [$this->work('W3', 'Laporan Keuangan C', '10.1000/c')]])]);
        $this->enable('openalex', ['api_key' => self::KEY, 'contact' => 'kontak@lembaga.test']);

        $this->sync('openalex', ['year_from' => 2020, 'year_to' => 2024, 'language' => 'en', 'open_access_only' => true, 'type' => 'article'])->assertOk()->assertJsonPath('ok', true);

        $run = ExternalSyncRun::firstOrFail();
        $this->assertSame('completed', $run->status);
        $this->assertSame([3, 3, 0], [$run->new_count, $run->processed_count, $run->failed_count]);
        $this->assertSame(3, ExternalPublication::count());

        $requests = collect(Http::recorded())->map(fn ($p) => $p[0]);
        $this->assertCount(2, $requests);
        $first = $requests->first();
        $this->assertSame('Bearer '.self::KEY, $first->header('Authorization')[0]);
        $this->assertStringNotContainsString(self::KEY, $first->url());
        $this->assertStringContainsString('title_and_abstract.search', urldecode($first->url()));
        $this->assertStringContainsString('publication_year:2020-2024', urldecode($first->url()));
        $this->assertStringContainsString('language:en', urldecode($first->url()));
        $this->assertStringContainsString('is_oa:true', urldecode($first->url()));
        $this->assertStringContainsString('cursor=%2A', $first->url());
        $this->assertStringContainsString('cursor=CUR2', $requests->last()->url());
        $this->assertStringNotContainsString('updated_date', urldecode($first->url()));
        $this->assertStringNotContainsString('created_date', urldecode($first->url()));
        $this->assertStringContainsString('mailto:kontak@lembaga.test', $first->header('User-Agent')[0]);
    }

    public function test_openalex_without_key_pages_by_number_not_cursor(): void
    {
        Http::fake(['api.openalex.org/*' => Http::sequence()
            ->push(['meta' => ['count' => 2], 'results' => [$this->work('W1', 'A satu', '10.1000/1'), $this->work('W2', 'B dua', '10.1000/2')]])
            ->push(['meta' => ['count' => 2], 'results' => []])]);
        $this->enable('openalex');

        $this->sync('openalex', ['max_records' => 10])->assertOk();

        $urls = collect(Http::recorded())->map(fn ($p) => $p[0]->url());
        $this->assertStringContainsString('page=1', $urls->first());
        $this->assertStringNotContainsString('cursor=', $urls->first());
        $this->assertStringNotContainsString('Authorization', json_encode(collect(Http::recorded())->first()[0]->headers()));
        $this->assertSame('completed', ExternalSyncRun::firstOrFail()->status);
    }

    public function test_max_records_is_never_exceeded(): void
    {
        Http::fake(['api.openalex.org/*' => $this->oa(array_map(fn ($i) => $this->work("W{$i}", "Judul unik nomor {$i}", "10.1000/m{$i}"), range(1, 10)), 'NEXT', 500)]);
        $this->enable('openalex', ['api_key' => self::KEY]);

        $this->sync('openalex', ['max_records' => 4])->assertOk();

        $this->assertSame(4, ExternalPublication::count());
        $this->assertSame('completed', ExternalSyncRun::firstOrFail()->status);
        $this->assertSame(4, ExternalSyncRun::firstOrFail()->processed_count);
    }

    /* --------------------------------------------------------------------- Crossref & DOAJ shapes */

    public function test_crossref_maps_fields_strips_jats_and_sends_mailto_only_when_configured(): void
    {
        Http::fake(['api.crossref.org/*' => Http::sequence()
            ->push(['message' => ['total-results' => 1, 'next-cursor' => 'CR2', 'items' => [$this->crossrefItem('10.5555/CR1', 'Judul Crossref', ['ISBN' => ['978-3-16-148410-0']])]]])]);
        $this->enable('crossref');

        $this->sync('crossref', ['year_from' => 2020, 'max_records' => 5])->assertOk();

        $p = ExternalPublication::firstOrFail();
        $this->assertSame('10.5555/cr1', $p->doi);
        $this->assertSame('Abstrak JATS & bersih.', $p->abstract);
        $this->assertSame(2022, $p->publication_year);
        $this->assertSame('2022-03-09', $p->published_date->toDateString());
        $this->assertSame(['9783161484100'], $p->isbn_json);
        $this->assertSame('ABSTRACT_AVAILABLE', $p->availability);
        $first = collect(Http::recorded())->first()[0];
        $this->assertStringNotContainsString('mailto=', $first->url());
        $this->assertStringContainsString('from-pub-date%3A2020', $first->url());
        $this->assertArrayNotHasKey('Authorization', $first->headers());
    }

    public function test_crossref_pages_by_offset_in_relevance_order_and_never_uses_cursor(): void
    {
        // Regresi nyata: cursor=* membuat Crossref mengembalikan hasil tak relevan (riset alergi untuk "financial reporting").
        $items = fn (int $n, string $p) => array_map(fn ($i) => $this->crossrefItem("10.7777/{$p}{$i}", "Judul {$p} {$i}"), range(1, $n));
        Http::fake(['api.crossref.org/*' => Http::sequence()
            ->push(['message' => ['total-results' => 300, 'items' => $items(100, 'a')]])
            ->push(['message' => ['total-results' => 300, 'items' => $items(20, 'b')]])]);
        $this->enable('crossref');

        $this->sync('crossref', ['max_records' => 120])->assertOk();

        $this->assertSame(120, ExternalPublication::count());
        $this->assertSame('completed', ExternalSyncRun::firstOrFail()->status);
        $urls = collect(Http::recorded())->map(fn ($p) => urldecode($p[0]->url()));
        $this->assertCount(2, $urls);
        foreach ($urls as $url) {
            $this->assertStringNotContainsString('cursor=', $url);
            $this->assertStringContainsString('sort=relevance', $url);
            $this->assertStringContainsString('order=desc', $url);
        }
        $this->assertStringContainsString('offset=0', $urls[0]);
        $this->assertStringContainsString('offset=100', $urls[1]);
    }

    public function test_crossref_stops_when_provider_total_is_reached(): void
    {
        Http::fake(['api.crossref.org/*' => Http::response(['message' => ['total-results' => 3, 'items' => array_map(fn ($i) => $this->crossrefItem("10.8888/z{$i}", "Judul z {$i}"), [1, 2, 3])]])]);
        $this->enable('crossref');

        $this->sync('crossref', ['max_records' => 50])->assertOk();

        $this->assertSame(3, ExternalPublication::count());
        $this->assertCount(1, Http::recorded(), 'tidak meminta halaman kosong tambahan');
    }

    public function test_doaj_maps_article_as_open_access_with_provenance_and_language_filter(): void
    {
        Http::fake(['doaj.org/*' => Http::response(['total' => 1, 'page' => 1, 'pageSize' => 50, 'results' => [[
            'id' => 'abc123', 'bibjson' => ['title' => 'Artikel DOAJ', 'year' => '2021', 'month' => '12', 'abstract' => 'Abstrak DOAJ.', 'keywords' => ['akuntansi'],
                'identifier' => [['id' => '2045-7758', 'type' => 'eissn'], ['id' => '10.1002/ece3.8411', 'type' => 'doi']],
                'journal' => ['title' => 'Jurnal DOAJ', 'publisher' => 'Penerbit', 'language' => ['ID'], 'license' => [['type' => 'CC BY']]],
                'author' => [['name' => 'Siti']], 'link' => [['type' => 'fulltext', 'url' => 'https://doi.org/10.1002/ece3.8411', 'content_type' => 'HTML']]],
        ]]])]);
        $this->enable('doaj');

        $this->sync('doaj', ['language' => 'id', 'year_from' => 2020, 'year_to' => 2022])->assertOk();

        $p = ExternalPublication::firstOrFail();
        $this->assertSame(['open', 'OPEN_ACCESS_LINK', 'id'], [$p->oa_status, $p->availability, $p->language]);
        $this->assertSame('https://doaj.org/article/abc123', $p->landing_url);
        $this->assertSame('doaj', $p->sources->first()->provider);
        $url = urldecode(collect(Http::recorded())->first()[0]->url());
        $this->assertStringContainsString('bibjson.year:[2020 TO 2022]', $url);
        $this->assertStringContainsString('bibjson.journal.language:"ID"', $url);
    }

    /* ----------------------------------------------------------------- dedup, provenance, idempoten */

    public function test_same_doi_from_two_providers_is_one_publication_with_two_sources_and_rerun_is_idempotent(): void
    {
        $oa = fn () => $this->oa([$this->work('W9', 'Artikel Bersama', '10.9999/shared')]);
        Http::fake([
            'api.openalex.org/*' => $oa(),
            'api.crossref.org/*' => Http::response(['message' => ['total-results' => 1, 'items' => [$this->crossrefItem('10.9999/SHARED', 'Artikel Bersama')]]]),
        ]);
        $this->enable('openalex');
        $this->enable('crossref');

        $this->sync('openalex')->assertOk();
        $this->sync('crossref')->assertOk();

        $this->assertSame(1, ExternalPublication::count());
        $this->assertSame(['crossref', 'openalex'], ExternalPublicationSource::orderBy('provider')->pluck('provider')->all());
        $this->assertSame(1, ExternalSyncRun::where('provider', 'crossref')->firstOrFail()->duplicate_count);
        $this->assertSame(1, ExternalPublicationCategory::count());

        $this->sync('openalex')->assertOk();
        $this->assertSame(1, ExternalPublication::count());
        $this->assertSame(2, ExternalPublicationSource::count());
        $this->assertSame(0, ExternalSyncRun::where('provider', 'openalex')->latest('id')->firstOrFail()->new_count);
    }

    public function test_changed_record_from_same_provider_is_updated_not_duplicated(): void
    {
        Http::fake(['api.openalex.org/*' => Http::sequence()
            ->push(['meta' => ['count' => 1], 'results' => [$this->work('W1', 'Judul Lama', '10.1001/u')]])
            ->push(['meta' => ['count' => 1], 'results' => [$this->work('W1', 'Judul Baru', '10.1001/u')]])]);
        $this->enable('openalex');

        $this->sync('openalex')->assertOk();
        $this->sync('openalex')->assertOk();

        $this->assertSame(1, ExternalPublication::count());
        $this->assertSame('Judul Baru', ExternalPublication::firstOrFail()->title);
        $this->assertSame(1, ExternalSyncRun::latest('id')->firstOrFail()->updated_count);
    }

    public function test_isbn_merge_is_conservative(): void
    {
        $item = fn (string $doi, string $title, array $isbn) => $this->crossrefItem($doi, $title, ['type' => 'book', 'ISBN' => $isbn]);
        $upsert = app(\App\Services\ExternalSources\PublicationUpserter::class);
        $rec = fn (string $id, string $title, array $isbn) => \App\Services\ExternalSources\Normalize::finish('openlibrary-test', ['external_id' => $id, 'title' => $title, 'isbn' => $isbn, 'type' => 'book']);

        $this->assertSame('new', $upsert->upsert($rec('1', 'Dasar Akuntansi', ['978-3-16-148410-0'])));
        $this->assertSame('duplicate', $upsert->upsert($rec('2', 'Dasar  Akuntansi!', ['9783161484100'])), 'ISBN sama + judul sama = buku yang sama');
        $this->assertSame('new', $upsert->upsert($rec('3', 'Dasar Akuntansi', ['9780306406157'])), 'judul sama, ISBN lain (edisi lain) tidak digabung');
        $this->assertSame('new', $upsert->upsert($rec('4', 'Dasar Akuntansi', [])), 'judul sama tanpa identifier tidak digabung');
        $this->assertSame('new', $upsert->upsert($rec('5', 'Buku Lain Sekali', ['978-3-16-148410-0'])), 'ISBN sama tetapi judul beda tidak digabung');
        $this->assertSame(4, ExternalPublication::count());
        $this->assertNotNull($item);
    }

    public function test_double_click_cannot_start_two_runs_for_same_provider_and_category(): void
    {
        Http::fake();
        $this->enable('openalex');
        $service = app(\App\Services\ExternalSources\ExternalSyncService::class);
        $scope = \App\Services\ExternalSources\SyncScope::fromArray(['provider' => 'openalex', 'category_id' => $this->cat->id, 'terms' => ['x']]);

        $service->start($scope, $this->admin->id);
        $this->expectException(ExternalSourceException::class);
        $service->start($scope, $this->admin->id);
    }

    /* ------------------------------------------------------------------------------- ketahanan */

    public function test_rate_limit_pauses_with_checkpoint_and_resume_completes(): void
    {
        Http::fake(['api.openalex.org/*' => Http::sequence()
            ->push(['meta' => ['count' => 3, 'next_cursor' => 'CUR2'], 'results' => [$this->work('W1', 'Satu', '10.2/1')]])
            ->push([], 429, ['Retry-After' => '30'])
            ->push(['meta' => ['count' => 3, 'next_cursor' => null], 'results' => [$this->work('W2', 'Dua', '10.2/2')]])]);
        $this->enable('openalex', ['api_key' => self::KEY]);

        $this->sync('openalex')->assertOk();
        $run = ExternalSyncRun::firstOrFail();

        $this->assertSame('rate_limited', $run->status);
        $this->assertSame('CUR2', $run->checkpoint_json['c']);
        $this->assertSame(1, ExternalPublication::count());
        $this->assertStringContainsString('429', $run->errors_json[0]['message']);

        // Cooldown (Retry-After 30 dtk) dihormati bersama oleh semua kampanye penyedia: Lanjutkan sebelum waktunya TIDAK menembak API lagi.
        $this->assertGreaterThan(0, \App\Services\ExternalSources\RateGate::cooldownRemaining('openalex'));
        $this->as()->postJson(route('admin.external-sources.runs.resume', $run))->assertOk();
        $this->assertSame('rate_limited', $run->fresh()->status);
        $this->assertCount(2, Http::recorded(), 'tidak ada permintaan baru selama cooldown');

        // Setelah cooldown berlalu, Lanjutkan meneruskan dari checkpoint.
        Cache::forget('external-cooldown:openalex');
        $this->as()->postJson(route('admin.external-sources.runs.resume', $run))->assertOk();
        $run->refresh();
        $this->assertSame('completed', $run->status);
        $this->assertSame(2, ExternalPublication::count());
    }

    public function test_auth_error_fails_final_without_leaking_the_key_and_other_providers_still_work(): void
    {
        Http::fake([
            'api.openalex.org/*' => Http::response(['error' => 'bad '.self::KEY], 401),
            'doaj.org/*' => Http::response(['total' => 1, 'results' => [['id' => 'z1', 'bibjson' => ['title' => 'Tetap jalan', 'year' => '2020', 'journal' => ['language' => ['EN']], 'identifier' => []]]]]),
        ]);
        $this->enable('openalex', ['api_key' => self::KEY]);
        $this->enable('doaj');

        $this->sync('openalex')->assertOk();
        $this->sync('doaj')->assertOk();

        $failed = ExternalSyncRun::where('provider', 'openalex')->firstOrFail();
        $this->assertSame('failed', $failed->status);
        $this->assertStringNotContainsString(self::KEY, json_encode($failed->toArray()));
        $this->assertSame('completed', ExternalSyncRun::where('provider', 'doaj')->firstOrFail()->status);
        $this->assertSame(1, ExternalPublication::count());
    }

    public function test_persistent_server_error_after_progress_ends_partial_and_timeout_is_handled(): void
    {
        Http::fake(['api.openalex.org/*' => Http::sequence()
            ->push(['meta' => ['count' => 9, 'next_cursor' => 'CUR2'], 'results' => [$this->work('W1', 'Pertama', '10.3/1')]])
            ->push([], 500)->push([], 500)->push([], 500)]);
        $this->enable('openalex', ['api_key' => self::KEY]);

        $this->sync('openalex')->assertOk();
        $run = ExternalSyncRun::firstOrFail();

        $this->assertSame('partial', $run->status);
        $this->assertSame(1, $run->processed_count);
        $this->assertSame(3, $run->checkpoint_json['attempts']);

        Http::fake(['doaj.org/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('timeout')]);
        $this->enable('doaj');
        $this->sync('doaj')->assertOk();
        $this->assertSame('failed', ExternalSyncRun::where('provider', 'doaj')->firstOrFail()->status);
    }

    public function test_cancel_stops_a_queued_run_and_a_cancelled_run_is_not_processed(): void
    {
        Http::fake();
        $this->enable('openalex');
        $service = app(\App\Services\ExternalSources\ExternalSyncService::class);
        $run = $service->start(\App\Services\ExternalSources\SyncScope::fromArray(['provider' => 'openalex', 'category_id' => $this->cat->id, 'terms' => ['x']]), null);

        $this->as()->postJson(route('admin.external-sources.runs.cancel', $run))->assertOk();
        $this->assertSame('cancelled', $run->fresh()->status);

        $this->assertNull($service->process($run->fresh(), 3));
        Http::assertNothingSent();
    }

    public function test_external_metadata_never_becomes_research_evidence_and_no_ai_is_called(): void
    {
        config()->set('research.ai_provider', 'mock');
        config()->set('research.require_email_verification', false);
        Http::fake(['api.openalex.org/*' => $this->oa([$this->work('W1', 'ESG disclosure and firm value in emerging markets', '10.7001/esg', ['abstract_inverted_index' => ['ESG' => [0], 'disclosure' => [1], 'raises' => [2], 'firm' => [3], 'value' => [4]]])])]);
        $this->enable('openalex');
        $this->sync('openalex', ['terms' => 'ESG disclosure'])->assertOk();
        $this->assertSame(1, ExternalPublication::count());

        $user = User::factory()->create(['phone' => '081234567890', 'institution' => 'Lab', 'address' => 'Jakarta', 'date_of_birth' => '1995-01-01', 'status' => 'active', 'email_verified_at' => now()]);
        $prompt = \App\Models\PromptTemplate::query()->where('status', 'active')->whereHas('aiTool', fn ($q) => $q->where('code', 'research-gap')->where('status', 'active'))->firstOrFail();

        $this->actingAs($user)->post(route('research.run'), ['category_id' => $this->cat->id, 'prompt_template_id' => $prompt->id, 'message' => 'ESG disclosure and firm value in emerging markets'])
            ->assertSessionHasErrors('message');

        $this->assertStringContainsString('Curated Source', session('errors')->first('message'), 'kategori tanpa sumber terbit ber-chunk ditolak: metadata eksternal tidak dihitung');
        $this->assertSame(0, \App\Models\AiRun::count(), 'tidak ada run riset, jadi tidak ada panggilan provider AI');
        $this->assertSame(0, \App\Models\AiRunCitation::count());
        $this->assertSame(0, \App\Models\DocumentChunk::count());
        Http::assertNotSent(fn (Request $r) => ! str_contains($r->url(), 'api.openalex.org'));
    }

    /* ----------------------------------------------------------------- DOAJ: urutan judul-dulu, tipe, DOI */

    private function doajArticle(string $id, string $title, array $over = []): array
    {
        return ['id' => $id, 'bibjson' => array_replace([
            'title' => $title, 'year' => '2022', 'month' => '3', 'abstract' => 'Abstrak DOAJ.', 'keywords' => ['akuntansi'],
            'identifier' => [['id' => '1234-5678', 'type' => 'eissn']],
            'journal' => ['title' => 'Jurnal Uji', 'publisher' => 'Penerbit Uji', 'language' => ['EN'], 'license' => [['type' => 'CC BY']]],
            'author' => [['name' => 'Siti Uji']], 'link' => [['type' => 'fulltext', 'url' => 'https://example.org/'.$id, 'content_type' => 'HTML']],
        ], $over)];
    }

    public function test_doaj_returns_title_matches_first_then_abstract_only_without_dropping_anything(): void
    {
        Http::fake(['doaj.org/*' => Http::sequence()
            ->push(['total' => 2, 'results' => [$this->doajArticle('t1', 'Financial reporting quality'), $this->doajArticle('t2', 'On financial reporting')]])
            ->push(['total' => 3, 'results' => [$this->doajArticle('a1', 'Tax morale'), $this->doajArticle('a2', 'Audit committees'), $this->doajArticle('a3', 'Costing')]])]);
        $this->enable('doaj');

        $this->sync('doaj', ['terms' => 'financial reporting', 'max_records' => 4])->assertOk();

        $urls = collect(Http::recorded())->map(fn ($p) => urldecode($p[0]->url()));
        $this->assertCount(2, $urls);
        $this->assertStringContainsString('(bibjson.title:"financial reporting")', $urls[0]);
        $this->assertStringNotContainsString('bibjson.abstract', $urls[0], 'fase pertama hanya judul');
        $this->assertStringContainsString('bibjson.abstract:"financial reporting" AND NOT bibjson.title:"financial reporting"', $urls[1]);
        $this->assertSame(['t1', 't2', 'a1', 'a2'], ExternalPublicationSource::orderBy('id')->pluck('external_id')->all(), 'judul dulu, lalu abstrak-saja, batas 4');
        $this->assertSame('completed', ExternalSyncRun::firstOrFail()->status);
    }

    public function test_doaj_falls_through_to_abstract_phase_when_no_title_matches_in_the_same_page_call(): void
    {
        Http::fake(['doaj.org/*' => Http::sequence()
            ->push(['total' => 0, 'results' => []])
            ->push(['total' => 1, 'results' => [$this->doajArticle('only', 'Judul tanpa frasa')]])]);
        $this->enable('doaj');

        $this->sync('doaj', ['max_records' => 5])->assertOk();

        $this->assertSame(1, ExternalPublication::count(), 'tidak ada artikel yang dibuang walau tak ada kecocokan judul');
        $this->assertCount(2, Http::recorded());
    }

    public function test_doaj_type_is_article_only_with_a_journal_block_and_doi_urls_are_canonicalized(): void
    {
        Http::fake(['doaj.org/*' => Http::response(['total' => 2, 'results' => [
            $this->doajArticle('j1', 'Artikel jurnal', ['identifier' => [['id' => '1234-5678', 'type' => 'eissn'], ['id' => 'https://doi.org/10.5555/AbC.Def', 'type' => 'doi']]]),
            ['id' => 'nj', 'bibjson' => ['title' => 'Tanpa blok jurnal', 'year' => '2020', 'identifier' => []]],
        ]])]);
        $this->enable('doaj');

        $this->sync('doaj')->assertOk();

        $by = ExternalPublication::pluck('publication_type', 'title');
        $this->assertSame('article', $by['Artikel jurnal']);
        $this->assertSame('other', $by['Tanpa blok jurnal'], 'tidak dibuang, tetapi tidak diaku sebagai artikel jurnal');
        $this->assertSame('10.5555/abc.def', ExternalPublication::where('title', 'Artikel jurnal')->value('doi'), 'DOI berbentuk URL & huruf besar dinormalisasi kanonis');
        $this->assertSame(['1234-5678'], ExternalPublication::where('title', 'Artikel jurnal')->firstOrFail()->issn_json);
    }

    /* ----------------------------------------------------------------------- dedup lintas provider A–E */

    public function test_cross_provider_dedup_scenarios_a_to_e_never_destroy_crossref_data(): void
    {
        Http::fake([
            'api.crossref.org/*' => Http::response(['message' => ['total-results' => 2, 'items' => [
                $this->crossrefItem('10.6001/shared.x', 'Judul Crossref Asli', ['abstract' => 'Abstrak Crossref asli.']),
                $this->crossrefItem('10.6001/kembar', 'Judul Yang Sama Persis'),
            ]]]),
            'doaj.org/*' => fn (Request $r) => Http::response(str_contains(urldecode($r->url()), 'AND NOT') ? ['total' => 0, 'results' => []] : ['total' => 4, 'results' => [
                // B + E: DOI sama dengan Crossref, tetapi berbentuk URL dan berbeda kapitalisasi.
                $this->doajArticle('d-b', 'Judul DOAJ Berbeda', ['abstract' => 'Abstrak DOAJ berbeda.', 'identifier' => [['id' => 'https://doi.org/10.6001/SHARED.X', 'type' => 'doi']]]),
                // A: DOI baru.
                $this->doajArticle('d-a', 'Artikel Baru DOAJ', ['identifier' => [['id' => '10.6002/baru', 'type' => 'doi']]]),
                // C: tanpa DOI -> identitas provider.
                $this->doajArticle('d-c', 'Artikel Tanpa DOI'),
                // D: judul sama dengan milik Crossref tetapi DOI berbeda -> tidak digabung.
                $this->doajArticle('d-d', 'Judul Yang Sama Persis', ['identifier' => [['id' => '10.6003/lain', 'type' => 'doi']]]),
            ]]),
        ]);
        $this->enable('crossref');
        $this->enable('doaj');
        $this->sync('crossref')->assertOk();
        $before = ExternalPublication::where('doi', '10.6001/shared.x')->firstOrFail()->only(['title', 'abstract', 'publisher', 'publication_year', 'container_title', 'checksum']);
        $crossrefCount = ExternalPublication::count();

        $this->sync('doaj')->assertOk();

        $run = ExternalSyncRun::where('provider', 'doaj')->firstOrFail();
        $this->assertSame([3, 1], [$run->new_count, $run->duplicate_count], 'A, C, D baru; B duplikat');
        $this->assertSame($crossrefCount + 3, ExternalPublication::count());

        $shared = ExternalPublication::where('doi', '10.6001/shared.x')->firstOrFail();
        $this->assertSame(['crossref', 'doaj'], $shared->sources()->orderBy('provider')->pluck('provider')->all(), 'B/E: satu publikasi, provenance ditambah');
        $this->assertSame($before, $shared->only(array_keys($before)), 'data Crossref tidak ditimpa (judul, abstrak, penerbit, tahun, jurnal, checksum)');
        $this->assertSame('open', $shared->oa_status, 'status OA "unknown" boleh dilengkapi DOAJ');
        $this->assertNotNull($shared->fulltext_url, 'tautan OA dari DOAJ melengkapi yang kosong');
        $this->assertSame('crossref', ExternalPublicationSource::where('publication_id', $shared->id)->orderBy('id')->value('provider'));

        $noDoi = ExternalPublication::findOrFail(ExternalPublicationSource::where('provider', 'doaj')->where('external_id', 'd-c')->value('publication_id'));
        $this->assertNull($noDoi->doi, 'C: identitas provider dipakai');

        $this->assertSame(2, ExternalPublication::where('title', 'Judul Yang Sama Persis')->count(), 'D: judul sama + DOI beda tidak digabung');

        $this->sync('doaj')->assertOk();
        $this->assertSame($crossrefCount + 3, ExternalPublication::count(), 'sync ulang idempoten');
        $this->assertSame(0, ExternalPublication::selectRaw('doi, count(*) n')->whereNotNull('doi')->groupBy('doi')->havingRaw('count(*) > 1')->get()->count(), 'tidak ada DOI ganda');
    }

    /* ---------------------------------------------------------------------------------- sinyal kualitas */

    public function test_quality_signals_inform_without_filtering_anything_out(): void
    {
        $q = fn (array $r, ?string $t = 'financial reporting') => \App\Services\ExternalSources\QualitySignals::evaluate($r, $t);
        $keys = fn (array $e) => collect($e['flags'])->pluck('key')->all();

        $complete = $q(['title' => 'Financial reporting quality', 'abstract' => 'x', 'authors' => ['A'], 'year' => 2020, 'doi' => '10.1/x', 'type' => 'article']);
        $this->assertSame([], $keys($complete));
        $this->assertSame('title_exact', $complete['match']['key']);

        $bare = $q(['title' => 'Reporting of financial data', 'abstract' => null, 'authors' => [], 'year' => null, 'doi' => null, 'type' => 'chapter']);
        $this->assertEqualsCanonicalizing(['missing_authors', 'missing_year', 'missing_doi', 'missing_abstract', 'non_article'], $keys($bare));
        $this->assertSame('title_related', $bare['match']['key'], 'semua kata kunci ada di judul walau tidak berurutan');

        $this->assertSame('abstract_exact', $q(['title' => 'Lain', 'abstract' => 'Studi financial reporting.'])['match']['key']);
        $this->assertSame('abstract_related', $q(['title' => 'Lain', 'abstract' => 'Reporting untuk financial.'])['match']['key']);
        $this->assertSame('none', $q(['title' => 'Biografi tokoh', 'abstract' => 'Tidak terkait.'])['match']['key']);
        $this->assertNull($q(['title' => 'x'], null)['match'], 'tanpa istilah tidak ada penilaian kata kunci');
    }

    public function test_admin_page_shows_quality_signals_and_filters_by_completeness(): void
    {
        Http::fake(['doaj.org/*' => Http::response(['total' => 2, 'results' => [
            $this->doajArticle('q1', 'Financial reporting lengkap', ['identifier' => [['id' => '10.7007/q1', 'type' => 'doi']]]),
            $this->doajArticle('q2', 'Financial reporting tanpa penulis', ['author' => [], 'identifier' => [], 'abstract' => '']),
        ]])]);
        $this->enable('doaj');
        $this->sync('doaj', ['terms' => 'financial reporting'])->assertOk();
        $this->assertSame(2, ExternalPublication::count(), 'rekaman tidak lengkap tetap disimpan');

        $page = $this->as()->get(route('admin.external-sources.index'))->assertOk()->assertSee('Frasa persis di judul')->assertSee('Tanpa penulis')->assertSee('Tanpa DOI');
        $this->assertSame(1, substr_count($page->getContent(), 'data-quality="missing_authors"'), 'sinyal hanya pada rekaman yang memang kurang');

        $this->as()->get(route('admin.external-sources.index', ['quality' => 'missing_authors']))->assertOk()->assertSee('tanpa penulis')->assertDontSee('lengkap</p>', false);
        $search = app(PublicationSearch::class);
        $this->assertSame(1, $search->query(['quality' => 'missing_authors'])->count());
        $this->assertSame(1, $search->query(['quality' => 'missing_doi'])->count());
        $this->assertSame(1, $search->query(['quality' => 'missing_abstract'])->count());
        $this->assertSame(0, $search->query(['quality' => 'missing_year'])->count());

        $this->as()->get(route('admin.external-sources.publications.show', ExternalPublication::where('title', 'like', '%tanpa penulis')->firstOrFail()))
            ->assertOk()->assertSee('Kualitas data')->assertSee('bukan filter');
    }

    public function test_growth_chart_series_is_cumulative_and_ends_at_the_real_total(): void
    {
        foreach ([0, 0, 3, 10, 40] as $i => $daysAgo) {
            ExternalPublication::create(['uuid' => (string) \Illuminate\Support\Str::uuid(), 'title' => "Terbit {$i}", 'title_hash' => sha1("t{$i}"), 'checksum' => sha1("c{$i}"), 'created_at' => now()->subDays($daysAgo)]);
        }

        $series = collect($this->as()->get(route('admin.external-sources.index'))->assertOk()->viewData('charts')['growth']);

        $this->assertCount(30, $series);
        $this->assertSame($series->pluck('value')->sort()->values()->all(), $series->pluck('value')->all(), 'tidak pernah turun');
        $this->assertSame(5, $series->last()['value'], 'akhir deret = total nyata');
        $this->assertSame(1, $series->first()['value'], 'publikasi 40 hari lalu masuk sebagai dasar kumulatif');
    }

    public function test_double_encoded_entities_and_tags_in_provider_text_are_cleaned(): void
    {
        $clean = fn (string $v) => \App\Services\ExternalSources\Normalize::text($v);

        $this->assertSame('Few prediction schemes & more.', $clean('Few prediction&amp;#x0D; schemes &amp;amp; more.'));
        $this->assertSame('Abstrak JATS & bersih.', $clean('<jats:p>Abstrak <jats:italic>JATS</jats:italic> &amp; bersih.</jats:p>'));
        $this->assertStringNotContainsString('<script', (string) $clean('&lt;script&gt;alert(1)&lt;/script&gt; aman'));
        $this->assertNull($clean('   '));
    }

    public function test_a_crashed_worker_job_never_leaves_the_run_stuck_in_queue(): void
    {
        Http::fake();
        $this->enable('openalex');
        $run = app(\App\Services\ExternalSources\ExternalSyncService::class)->start(\App\Services\ExternalSources\SyncScope::fromArray(['provider' => 'openalex', 'category_id' => $this->cat->id, 'terms' => ['x']]), null);

        (new \App\Jobs\ProcessExternalSyncRun($run->id))->failed(new \RuntimeException('database is locked'));

        $run->refresh();
        $this->assertSame('failed', $run->status);
        $this->assertNotNull($run->checkpoint_json);
        $this->assertStringContainsString('Lanjutkan', $run->errors_json[0]['message']);
        $this->assertStringNotContainsString('locked', json_encode($run->errors_json));
        $this->as()->postJson(route('admin.external-sources.runs.resume', $run))->assertOk();
    }

    /* -------------------------------------------------------------------- kategori & ketersediaan */

    public function test_classification_is_transparent_and_admin_verification_survives_resync(): void
    {
        Http::fake(['api.openalex.org/*' => $this->oa([$this->work('W1', 'Pajak dan Audit', '10.4/1')])]);
        $this->enable('openalex');
        $other = Category::create(['name' => 'Manajemen', 'slug' => 'manajemen', 'status' => 'active']);

        $this->sync('openalex', ['terms' => 'taxation'])->assertOk();
        $link = ExternalPublicationCategory::firstOrFail();
        $this->assertSame('suggested', $link->status);
        $this->assertStringContainsString('taxation', $link->reason);
        $this->assertSame('openalex', $link->provider);

        $p = ExternalPublication::firstOrFail();
        $this->as()->patchJson(route('admin.external-sources.publications.classify', $p), ['category_id' => $other->id, 'from_category_id' => $this->cat->id])->assertOk();
        $this->assertSame([[$other->id, 'verified']], ExternalPublicationCategory::all()->map(fn ($l) => [$l->category_id, $l->status])->all());

        $this->sync('openalex', ['terms' => 'taxation'])->assertOk();
        $this->assertSame('verified', ExternalPublicationCategory::where('category_id', $other->id)->firstOrFail()->status);
        $this->assertSame(2, ExternalPublicationCategory::count(), 'kategori lama disarankan lagi, yang diverifikasi tidak diturunkan');
    }

    public function test_availability_is_derived_from_real_data_and_never_claims_full_text_indexing(): void
    {
        Http::fake(['api.openalex.org/*' => $this->oa([
            $this->work('W1', 'Hanya metadata', '10.5001/1', ['abstract_inverted_index' => null, 'open_access' => ['is_oa' => null]]),
            $this->work('W2', 'Tertutup', '10.5002/2', ['abstract_inverted_index' => null, 'open_access' => ['is_oa' => false]]),
            $this->work('W3', 'Terbuka', '10.5003/3'),
        ])]);
        $this->enable('openalex');

        $this->sync('openalex')->assertOk();

        $by = ExternalPublication::pluck('availability', 'doi');
        $this->assertSame('METADATA_ONLY', $by['10.5001/1']);
        $this->assertSame('RESTRICTED', $by['10.5002/2']);
        $this->assertSame('OPEN_ACCESS_LINK', $by['10.5003/3']);
        $this->assertNotContains('FULL_TEXT_INDEXED', $by->values()->all());
        $this->assertSame(0, \App\Models\DocumentChunk::count());
        $this->assertSame(0, \App\Models\Document::count());
    }

    /* --------------------------------------------------------------------------------- pencarian */

    public function test_search_filters_and_treats_like_wildcards_literally(): void
    {
        Http::fake(['api.openalex.org/*' => $this->oa([$this->work('W1', 'Audit internal 100% efektif', '10.6001/1'), $this->work('W2', 'Pajak daerah', '10.6002/2', ['publication_year' => 2019, 'language' => 'id'])])]);
        $this->enable('openalex');
        $this->sync('openalex')->assertOk();
        $search = app(PublicationSearch::class);

        $this->assertSame(1, $search->query(['q' => '100%'])->count());
        $this->assertSame(0, $search->query(['q' => '%%%%'])->count(), 'wildcard dari pengguna tidak boleh cocok semuanya');
        $this->assertSame(1, $search->query(['year' => 2019])->count());
        $this->assertSame(1, $search->query(['language' => 'id'])->count());
        $this->assertSame(2, $search->query(['provider' => 'openalex', 'category' => $this->cat->id])->count());
        $this->assertSame(0, $search->query(['provider' => 'crossref'])->count());
        $this->assertSame(2, $search->query([])->count());

        // Regresi: filter peninjauan harus bekerja TANPA memilih kategori.
        $this->assertSame(2, $search->query(['review' => 'suggested'])->count());
        $this->assertSame(0, $search->query(['review' => 'verified'])->count());
        ExternalPublicationCategory::query()->where('publication_id', ExternalPublication::query()->orderBy('id')->value('id'))->update(['status' => 'verified']);
        $this->assertSame(1, $search->query(['review' => 'verified'])->count());
        $this->assertSame(0, $search->query(['review' => 'verified', 'category' => 99999])->count());
    }

    public function test_page_after_sync_shows_records_charts_history_and_detail_without_ai(): void
    {
        Http::fake(['api.openalex.org/*' => $this->oa([$this->work('W1', 'Laporan Keberlanjutan', '10.8001/1')])]);
        $this->enable('openalex');
        $this->sync('openalex')->assertOk();

        $page = $this->as()->get(route('admin.external-sources.index'))->assertOk()
            ->assertSee('Laporan Keberlanjutan')->assertSee('Rekaman per penyedia')->assertSee('Riwayat sinkronisasi')->assertSee('Selesai');
        $this->assertStringContainsString('10.8001/1', $page->getContent());

        $this->as()->get(route('admin.external-sources.index', ['q' => 'tidak-ada-judul-ini']))->assertOk()->assertSee('Tidak ada rekaman yang cocok');
        $this->as()->get(route('admin.external-sources.publications.show', ExternalPublication::firstOrFail()))->assertOk()->assertSee('Asal data')->assertSee('Disarankan');
        $this->as()->getJson(route('admin.external-sources.status'))->assertOk()->assertJsonStructure(['runs']);

        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'openai') || str_contains($r->url(), 'anthropic'));
    }

    /* ------------------------------------------------------------------------- sync semua & peta */

    public function test_sync_all_runs_only_enabled_mapped_providers_and_reports_skips(): void
    {
        Http::fake([
            'api.openalex.org/*' => $this->oa([$this->work('W1', 'Satu untuk semua', '10.9/1')]),
            'api.crossref.org/*' => Http::response(['message' => ['items' => []]]),
        ]);
        $this->enable('openalex');
        $this->enable('crossref');
        $this->as()->putJson(route('admin.external-sources.mappings.save'), ['category_id' => $this->cat->id, 'provider' => 'openalex', 'terms' => "accounting\ntaxation", 'max_records' => 20])->assertOk();

        $this->as()->postJson(route('admin.external-sources.sync-all'), ['category_id' => $this->cat->id, 'max_total' => 20])->assertOk()->assertJsonPath('ok', true)
            ->assertJsonPath('skipped.crossref', 'Belum ada pemetaan istilah untuk kategori ini.')->assertJsonPath('skipped.doaj', 'Konektor nonaktif. Aktifkan lebih dulu di Pengaturan.');

        $this->assertSame(['openalex'], ExternalSyncRun::pluck('provider')->unique()->values()->all());
        $this->assertSame(['accounting', 'taxation'], ExternalSyncRun::firstOrFail()->scope_json['terms']);
        $this->assertSame(1, ExternalCategoryMapping::count());

        $this->as()->deleteJson(route('admin.external-sources.mappings.delete', ExternalCategoryMapping::firstOrFail()))->assertOk();
        $this->as()->postJson(route('admin.external-sources.sync-all'), ['category_id' => $this->cat->id, 'max_total' => 20])->assertStatus(422);
    }

    /* ------------------------------------------------------------------------------- keamanan */

    public function test_safe_http_rejects_foreign_hosts_plain_http_ips_and_redirects(): void
    {
        Http::fake(['*' => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest'])]);
        $http = new SafeHttp(['api.openalex.org']);

        foreach (['https://evil.example/x', 'http://api.openalex.org/works', 'https://127.0.0.1/works', 'https://api.openalex.org.evil.example/works', 'ftp://api.openalex.org/x'] as $url) {
            try {
                $http->get($url);
                $this->fail("harus ditolak: {$url}");
            } catch (ExternalSourceException $e) {
                $this->assertSame('bad_request', $e->kind);
            }
        }
        Http::assertNothingSent();

        try {
            $http->get('https://api.openalex.org/works');
            $this->fail('redirect harus ditolak');
        } catch (ExternalSourceException $e) {
            $this->assertStringContainsString('mengarahkan ulang', $e->getMessage());
        }
    }

    public function test_hostile_search_terms_cannot_inject_query_syntax(): void
    {
        $scope = \App\Services\ExternalSources\SyncScope::fromArray(['provider' => 'doaj', 'terms' => ['" OR bibjson.title:* ) (', 'a,b|c:d&e=f'], 'category_id' => 1]);

        foreach ($scope->terms as $term) {
            $this->assertDoesNotMatchRegularExpression('/["()*:,|&=]/', $term);
        }
    }

    public function test_every_mutating_action_is_audited_without_secrets(): void
    {
        Http::fake(['api.openalex.org/*' => $this->oa([])]);
        $this->enable('openalex', ['api_key' => self::KEY]);
        $this->as()->postJson(route('admin.external-sources.test', 'openalex'))->assertOk();
        $this->sync('openalex')->assertOk();

        $actions = AuditLog::pluck('action')->all();
        foreach (['external_source.settings_updated', 'external_source.connection_tested', 'external_source.sync_started'] as $a) {
            $this->assertContains($a, $actions);
        }
        $this->assertStringNotContainsString(self::KEY, json_encode(AuditLog::all()->toArray()));
    }
}
