<?php

namespace Tests\Feature;

use App\Models\AiRun;
use App\Models\Category;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\ExternalPublication;
use App\Models\User;
use App\Services\Discovery\ExternalCandidateFinder;
use App\Services\Discovery\QueryAnalyzer;
use App\Services\Discovery\SourceDiscovery;
use App\Services\ExternalSources\ExternalSourceSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\SyntheticCatalog;
use Tests\TestCase;

/**
 * Source Discovery V1.2: retrieval tanpa pemotongan menurut ID. Setiap uji membandingkan hasil sistem dengan ORACLE: menilai
 * SELURUH katalog (tanpa prasaring SQL) lalu mengurutkan dengan aturan resmi yang sama. Data sintetis pada SQLite sekali pakai.
 */
class SourceDiscoveryScaleTest extends TestCase
{
    use RefreshDatabase;

    private Category $cat;

    private Category $other;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->cat = Category::create(['name' => 'Accounting & Finance', 'slug' => 'acc', 'status' => 'active']);
        $this->other = Category::create(['name' => 'Management', 'slug' => 'mgmt', 'status' => 'active']);
        foreach (['crossref', 'doaj', 'openalex'] as $p) {
            app(ExternalSourceSettings::class)->save($p, ['enabled' => 1], null);
        }
    }

    private function discover(string $q, array $filters = []): array
    {
        Cache::flush();

        return app(SourceDiscovery::class)->discover($this->cat->id, $q, $filters);
    }

    /** Oracle: nilai SEMUA baris kategori (tanpa SQL pra-saring), urutkan dengan aturan resmi. @return array<int,array> */
    private function oracle(string $q, ?int $need = null, array $filters = []): array
    {
        $need ??= (int) config('research.discovery.rank_limit');
        $d = app(SourceDiscovery::class);
        $a = app(QueryAnalyzer::class)->analyze($q);
        $out = [];
        $query = ExternalPublication::query()
            ->with(['sources', 'categoryLinks' => fn ($c) => $c->where('category_id', $this->cat->id)])
            ->whereHas('categoryLinks', fn ($c) => $c->where('category_id', $this->cat->id))
            ->whereHas('sources', fn ($s) => $s->whereIn('provider', $d->enabledProviders()));
        foreach (['year' => 'publication_year', 'type' => 'publication_type', 'language' => 'language'] as $k => $col) {
            filled($filters[$k] ?? null) && $query->where($col, $filters[$k]);
        }
        $query->chunkById(500, function ($rows) use (&$out, $d, $a): void {
            foreach ($rows as $p) {
                if (preg_match('/^\s*(?:retracted|retraction|withdrawn|withdrawal|notice of retraction|expression of concern)\b/iu', (string) $p->title)) {
                    continue;
                }
                $s = $d->score($d->externalItem($p, $this->cat->name, $p->categoryLinks->first()?->status), $a);
                $s && $out[] = $s;
            }
        });
        usort($out, [SourceDiscovery::class, 'compare']);

        return array_slice($out, 0, $need);
    }

    /** Shortlist LAMA (V1.1): ORDER BY hits DESC, id ASC LIMIT 400. Dipakai hanya untuk membuktikan bahwa uji ini membedakan. @return array<int,int> */
    private function legacyShortlist(string $q, int $limit = 400): array
    {
        $a = app(QueryAnalyzer::class)->analyze($q);
        $parts = [];
        $bind = [];
        foreach (array_slice($a['core'], 0, 8) as $t) {
            $like = QueryAnalyzer::like($t);
            $parts[] = "(case when lower(title) like ? escape '!' then 3 else 0 end + case when lower(coalesce(abstract,'')) like ? escape '!' then 1 else 0 end + case when lower(coalesce(keywords_json,'')) like ? escape '!' then 1 else 0 end)";
            array_push($bind, $like, $like, $like);
        }
        $hits = implode(' + ', $parts);

        return ExternalPublication::query()->select('external_publications.id')->selectRaw("({$hits}) as hits", $bind)
            ->whereHas('categoryLinks', fn ($c) => $c->where('category_id', $this->cat->id))
            ->whereRaw("({$hits}) > 0", $bind)->orderByDesc('hits')->orderBy('id')->limit($limit)->pluck('id')->all();
    }

    /** @return array<string, array{0:int}> */
    public static function sizes(): array
    {
        return ['500 publikasi' => [500], '2.000 publikasi' => [2000]];
    }

    #[DataProvider('sizes')]
    public function test_top_results_equal_the_full_catalog_oracle_and_never_lose_high_id_articles(int $size): void
    {
        Http::fake();
        $aiBefore = AiRun::count();
        $info = (new SyntheticCatalog)->build($size, $this->cat->id, $this->other->id);

        $res = $this->discover('financial reporting');
        $oracle = $this->oracle('financial reporting');
        $sysIds = array_column($res['results'], 'id');
        $oracleIds = array_column($oracle, 'id');

        // Rancangan data: artikel paling relevan punya ID terbesar di kategori target, melewati shortlist lama.
        $this->assertEqualsCanonicalizing($info['gold_ids'], array_slice($oracleIds, 0, count($info['gold_ids'])), 'oracle menaruh semua artikel gold (ID terbesar) di peringkat teratas');
        $legacy = $this->legacyShortlist('financial reporting');
        $this->assertNotEmpty(array_diff($info['gold_ids'], $legacy), 'uji ini valid: shortlist LAMA membuang artikel relevan ber-ID besar');

        // Sistem baru: Top 5 dan seluruh daftar identik dengan oracle, recall kandidat penuh.
        $this->assertSame(array_slice($oracleIds, 0, 5), array_slice($sysIds, 0, 5), 'Top 5 = oracle');
        $this->assertSame($oracleIds, $sysIds, 'seluruh daftar (rank_limit) = oracle');
        $this->assertSame([], array_diff(array_slice($oracleIds, 0, 5), $sysIds), 'recall Top 5 = 100%');
        $this->assertTrue($res['counts']['exact'], 'terbukti tidak ada baris tak-diperiksa yang bisa masuk N teratas');
        $this->assertFalse($res['counts']['truncated']);
        $this->assertSame([], array_diff(array_slice($sysIds, 0, 5), $info['gold_ids']), 'Top 5 adalah artikel paling relevan, bukan yang ber-ID kecil');

        // Jumlah "relevan" hanya dilaporkan sebagai total bila semua kandidat sudah dinilai; selain itu ia tidak mengaku total.
        $all = count($this->oracle('financial reporting', 1_000_000));
        $res['counts']['relevant_complete'] ? $this->assertSame($all, $res['counts']['relevant']) : $this->assertLessThanOrEqual($all, $res['counts']['relevant']);
        $this->assertSame($res['counts']['examined'] >= $res['counts']['external'], $res['counts']['relevant_complete']);

        // Isolasi kategori dan penyembunyian entri ditarik.
        $this->assertSame([], array_intersect($sysIds, $info['other_gold_ids']), 'artikel kategori lain tak pernah bocor');
        $this->assertSame([$this->cat->name], array_values(array_unique(array_column($res['results'], 'category'))));
        $this->assertGreaterThan(0, $res['counts']['withdrawn_hidden'] + 1);
        $this->assertSame($aiBefore, AiRun::count());
        Http::assertNothingSent();

        // Stabil: pengulangan menghasilkan urutan yang sama; hanya sebagian kecil katalog yang perlu dinilai.
        $this->assertSame($sysIds, array_column($this->discover('financial reporting')['results'], 'id'));
        $this->assertLessThan($info['target'], $res['counts']['examined'] + 1);
    }

    #[DataProvider('queries')]
    public function test_sql_upper_bound_never_underestimates_and_candidate_recall_is_complete(string $q, array $filters): void
    {
        (new SyntheticCatalog(777))->build(600, $this->cat->id, $this->other->id, 6, 0.4);
        $this->seedTrickyRows();

        $d = app(SourceDiscovery::class);
        $a = app(QueryAnalyzer::class)->analyze($q);
        $found = app(ExternalCandidateFinder::class)->find($this->cat->id, $a, $filters, $d->enabledProviders(), 1_000_000);
        $ub = array_column($found['rows'], 'ub', 'id');

        $checked = 0;
        $eligible = 0;
        ExternalPublication::query()->with(['sources', 'categoryLinks' => fn ($c) => $c->where('category_id', $this->cat->id)])
            ->whereHas('categoryLinks', fn ($c) => $c->where('category_id', $this->cat->id))
            ->when(filled($filters['year'] ?? null), fn ($x) => $x->where('publication_year', $filters['year']))
            ->chunkById(300, function ($rows) use ($d, $a, $ub, &$checked, &$eligible): void {
                foreach ($rows as $p) {
                    $s = $d->score($d->externalItem($p, $this->cat->name, $p->categoryLinks->first()?->status), $a);
                    $checked++;
                    if ($s === null) {
                        continue;
                    }
                    $eligible++;
                    $this->assertArrayHasKey($p->id, $ub, "baris layak (#{$p->id} {$p->title}) tidak ada di kandidat SQL: false negative");
                    $this->assertGreaterThanOrEqual($s['score'] - 1e-6, min(100, $ub[$p->id]), "batas atas < skor sebenarnya untuk #{$p->id} {$p->title}");
                }
            });

        $this->assertGreaterThan(0, $checked);
        $this->assertGreaterThanOrEqual(0, $eligible);
    }

    /** @return array<string, array{0:string,1:array}> */
    public static function queries(): array
    {
        return [
            'dua istilah frasa' => ['financial reporting', []],
            'bentuk jamak' => ['audit reports standards', []],
            'satu istilah' => ['esg', []],
            'banyak istilah' => ['financial reporting quality standards disclosure', []],
            'petunjuk tahun' => ['financial reporting tahun 2021', []],
            'petunjuk bahasa' => ['financial reporting in english', []],
            'filter tahun keras' => ['financial reporting', ['year' => 2020]],
            'kata riset generik' => ['analysis of financial reporting', []],
        ];
    }

    private function seedTrickyRows(): void
    {
        $now = now()->toDateTimeString();
        $mk = function (string $title, ?string $abstract, ?string $keywordsJson, array $o = []) use ($now): void {
            static $n = 900000;
            $n++;
            $id = DB::table('external_publications')->insertGetId($o + [
                'uuid' => sprintf('00000000-0000-4000-9000-%012d', $n), 'title' => $title, 'title_hash' => sha1("tr{$n}"), 'checksum' => sha1("tc{$n}"), 'doi' => "10.9999/trick.{$n}",
                'authors_json' => json_encode(['A']), 'publication_year' => 2021, 'publication_type' => 'article', 'container_title' => 'J', 'abstract' => $abstract,
                'keywords_json' => $keywordsJson, 'language' => 'en', 'oa_status' => 'open', 'availability' => 'ABSTRACT_AVAILABLE', 'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('external_publication_sources')->insert(['publication_id' => $id, 'provider' => 'doaj', 'external_id' => "tr{$n}", 'checksum' => sha1("ts{$n}"), 'last_seen_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
            DB::table('external_publication_categories')->insert(['publication_id' => $id, 'category_id' => $this->cat->id, 'status' => 'verified', 'query_term' => 'uji', 'created_at' => $now, 'updated_at' => $now]);
        };

        $mk('Annual report on audit', 'The audit report is one standard.', null);                  // "reports" <-> "report" (akar jamak)
        $mk('Financial-reporting: cash-flow, disclosure!', 'Earnings; quality/standards.', null); // tanda baca memisahkan token
        $mk('Untitled note', 'Short.', json_encode(['financial reporting', 'esg']));              // cocok hanya di kata kunci
        $mk('Catatan tanpa istilah', 'Tidak relevan.', json_encode(['laporan keuangan çok', 'esg'], 0));   // kata kunci ber-escape unicode (permisif)
        $mk('FINANCIAL REPORTING QUALITY', null, null);                                              // huruf besar, tanpa abstrak
        $mk('Financial reporting 2021 study', 'Financial reporting. Financial reporting. Financial reporting. Financial reporting.', null, ['publication_year' => 2021, 'language' => 'en']);
    }

    public function test_search_declares_incomplete_results_instead_of_silently_truncating(): void
    {
        (new SyntheticCatalog(5))->build(600, $this->cat->id, $this->other->id, 4, 0.9);

        config(['research.discovery.examine_limit' => 20, 'research.discovery.examine_chunk' => 10]);
        $res = $this->discover('financial reporting');
        $this->assertFalse($res['counts']['exact']);
        $this->assertTrue($res['counts']['truncated']);
        $this->assertLessThanOrEqual(20, $res['counts']['examined']);
        $this->assertGreaterThan(20, $res['counts']['external'], 'kandidat SQL jauh lebih banyak daripada yang diperiksa');

        $user = User::factory()->create(['phone' => '081234567890', 'institution' => 'Lab', 'address' => 'Jakarta', 'date_of_birth' => '1995-01-01', 'status' => 'active', 'email_verified_at' => now()]);
        $html = $this->actingAs($user)->get(route('research.references', ['category_id' => $this->cat->id, 'q' => 'financial reporting']))->assertOk()->getContent();
        $this->assertStringContainsString('mungkin belum lengkap', $html);

        config(['research.discovery.examine_limit' => 3000, 'research.discovery.scan_limit' => 100]);
        $res = $this->discover('financial reporting');
        $this->assertSame(100, $res['counts']['external'], 'batas pemindaian dihormati');
        // Dengan 100 baris teratas menurut batas atas, N=200 tak terpenuhi: tidak boleh mengaku pasti.
        $this->assertFalse($res['counts']['exact']);
    }

    public function test_early_stop_is_exact_when_the_bound_separates_the_top_results(): void
    {
        (new SyntheticCatalog(9))->build(1500, $this->cat->id, $this->other->id, 8, 0.2);
        config(['research.discovery.rank_limit' => 10]);

        $res = $this->discover('financial reporting');
        $oracle = $this->oracle('financial reporting', 10);

        $this->assertSame(array_column($oracle, 'id'), array_column($res['results'], 'id'));
        $this->assertTrue($res['counts']['exact']);
        $this->assertLessThan($res['counts']['external'], $res['counts']['examined'], 'berhenti lebih awal tanpa menilai seluruh kandidat');
    }

    public function test_library_documents_are_prefiltered_not_cut_by_id_and_private_ones_stay_hidden(): void
    {
        $owner = User::factory()->create();
        $mk = function (string $title, array $o = []) use ($owner): Document {
            $d = Document::create($o + ['uuid' => (string) Str::uuid(), 'title' => $title, 'abstract' => null, 'category_id' => $this->cat->id, 'document_type' => 'pdf', 'publication_year' => 2021, 'language' => 'en',
                'publisher' => 'P', 'source_label' => 'Curated Source', 'status' => 'published', 'visibility' => 'public', 'uploaded_by' => $owner->id, 'published_at' => now()]);
            DocumentChunk::create(['document_id' => $d->id, 'chunk_index' => 0, 'content' => 'Isi umum dokumen.', 'metadata_json' => ['page' => 1]]);

            return $d;
        };
        foreach (range(1, 30) as $i) {
            $mk("Dokumen tak terkait {$i}");
        }
        $private = $mk('Financial reporting rahasia', ['visibility' => 'private']);
        $draft = $mk('Financial reporting draf', ['status' => 'draft']);
        $best = $mk('Financial reporting'); // ID terbesar

        config(['research.discovery.internal_limit' => 5]);
        $res = $this->discover('financial reporting');
        $ids = array_column($res['results'], 'id');

        $this->assertContains($best->id, $ids, 'dokumen relevan ber-ID besar tidak terbuang oleh batas dokumen (prasaring, bukan potong id)');
        $this->assertNotContains($private->id, $ids);
        $this->assertNotContains($draft->id, $ids);
        $this->assertSame(1, $res['counts']['internal'], 'dokumen tak terkait tidak dimuat');
        $this->assertTrue($res['counts']['exact']);

        config(['research.discovery.internal_limit' => 1]);
        $mk('Financial reporting kedua');
        $this->assertFalse($this->discover('financial reporting')['counts']['exact'], 'batas dokumen tercapai: tidak mengaku pasti');
    }
}
