<?php

namespace Tests\Feature;

use App\Models\AiRun;
use App\Models\Category;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\ExternalPublication;
use App\Models\ExternalPublicationCategory;
use App\Models\ExternalPublicationSource;
use App\Models\User;
use App\Services\Discovery\AnalysisContract;
use App\Services\Discovery\Highlighter;
use App\Services\Discovery\QueryAnalyzer;
use App\Services\Discovery\SourceDiscovery;
use App\Services\ExternalSources\ExternalSourceSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Source Discovery V1 pada SQLite sekali pakai. Semua publikasi di sini fixture uji; tidak ada jaringan, AI, atau data produksi.
 */
class SourceDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Category $cat;

    private Category $other;

    private int $n = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->user = User::factory()->create(['phone' => '081234567890', 'institution' => 'Lab', 'address' => 'Jakarta', 'date_of_birth' => '1995-01-01', 'status' => 'active', 'email_verified_at' => now()]);
        $this->cat = Category::create(['name' => 'Accounting & Finance', 'slug' => 'acc', 'status' => 'active']);
        $this->other = Category::create(['name' => 'Management', 'slug' => 'mgmt', 'status' => 'active']);
        foreach (['crossref', 'doaj'] as $p) {
            app(ExternalSourceSettings::class)->save($p, ['enabled' => 1], null);
        }
    }

    /** Publikasi eksternal fixture. $o menimpa kolom; 'cat' kategori, 'provider' penyedia, 'status' status tautan kategori. */
    private function pub(array $o = []): ExternalPublication
    {
        $i = ++$this->n;
        $cat = array_key_exists('cat', $o) ? $o['cat'] : $this->cat;
        $provider = $o['provider'] ?? 'doaj';
        $status = $o['status'] ?? 'suggested';
        unset($o['cat'], $o['provider'], $o['status']);

        $p = ExternalPublication::create($o + [
            'uuid' => (string) Str::uuid(), 'title' => "Artikel uji {$i}", 'title_hash' => sha1("t{$i}"), 'checksum' => sha1("c{$i}"), 'doi' => "10.9999/uji.{$i}",
            'authors_json' => ['Penulis Satu'], 'publication_year' => 2022, 'publication_type' => 'article', 'language' => 'en', 'container_title' => 'Jurnal Uji',
            'abstract' => null, 'keywords_json' => [], 'oa_status' => 'open', 'availability' => 'OPEN_ACCESS_LINK', 'landing_url' => "https://doaj.org/article/{$i}",
        ]);
        ExternalPublicationSource::create(['publication_id' => $p->id, 'provider' => $provider, 'external_id' => "ext{$i}", 'source_url' => "https://doaj.org/article/ext{$i}", 'checksum' => sha1("s{$i}"), 'last_seen_at' => now()]);
        $cat && ExternalPublicationCategory::create(['publication_id' => $p->id, 'category_id' => $cat->id, 'status' => $status, 'query_term' => 'uji']);

        return $p;
    }

    private function doc(string $title, array $chunks = ['Teks uji.'], array $o = []): Document
    {
        $d = Document::create($o + [
            'uuid' => (string) Str::uuid(), 'title' => $title, 'abstract' => null, 'category_id' => $this->cat->id, 'document_type' => 'pdf', 'publication_year' => 2021,
            'language' => 'en', 'publisher' => 'Penerbit Pustaka', 'source_label' => 'Curated Source', 'status' => 'published', 'visibility' => 'public',
            'uploaded_by' => $this->user->id, 'published_at' => now(),
        ]);
        foreach ($chunks as $i => $c) {
            DocumentChunk::create(['document_id' => $d->id, 'chunk_index' => $i, 'content' => $c, 'metadata_json' => ['page' => $i + 1]]);
        }

        return $d;
    }

    private function find(string $q, ?Category $cat = null, array $filters = []): array
    {
        return app(SourceDiscovery::class)->discover(($cat ?? $this->cat)->id, $q, $filters);
    }

    private function titles(array $res): array
    {
        return array_column($res['results'], 'title');
    }

    private function page(array $params = [])
    {
        return $this->actingAs($this->user)->get(route('research.references', $params));
    }

    /* =============================================================================== analisis pertanyaan */

    public function test_query_is_analysed_deterministically_keeping_research_meaning(): void
    {
        $a = (new QueryAnalyzer)->analyze('Pengaruh Green Accounting terhadap Kinerja Keuangan');

        $this->assertSame(['green', 'accounting', 'kinerja', 'keuangan'], $a['core']);
        $this->assertSame(['pengaruh'], $a['generic'], 'kata riset umum tidak dibuang, hanya diberi bobot rendah');
        $this->assertSame(['green accounting', 'kinerja keuangan'], $a['phrases'], 'kata fungsi memisahkan frasa; kata umum di tepi frasa dilepas');
        $this->assertContains('pengaruh', $a['terms']);

        $h = (new QueryAnalyzer)->analyze('ESG disclosure year 2021 in english');
        $this->assertSame([2021, 'en'], [$h['year'], $h['language']], 'tahun/bahasa hanya dari petunjuk eksplisit');
        $this->assertNotContains('2021', $h['terms']);

        $only = (new QueryAnalyzer)->analyze('pengaruh analisis');
        $this->assertSame(['pengaruh', 'analisis'], $only['core'], 'bila hanya kata umum, tetap dicari memakai kata itu');
        $this->assertTrue((new QueryAnalyzer)->analyze('  ?! ')['empty']);
        $this->assertSame('analisis', QueryAnalyzer::normalize('Análisis!'), 'diakritik dan tanda baca dinormalisasi');
    }

    /* =============================================================================== pencarian internal & eksternal */

    public function test_search_internal_only_external_only_and_combined(): void
    {
        $this->doc('Green accounting and firm performance in Indonesia', ['Green accounting practices improve firm performance in listed companies.']);
        $this->assertSame(['internal'], array_unique(array_column($this->find('green accounting')['results'], 'kind')));

        Document::query()->delete();
        $this->pub(['title' => 'Green accounting disclosure in emerging markets', 'abstract' => str_repeat('Green accounting abstract text. ', 3)]);
        $ext = $this->find('green accounting');
        $this->assertSame(['external'], array_unique(array_column($ext['results'], 'kind')));

        $this->doc('Green accounting handbook', ['Handbook on green accounting.']);
        Cache::flush();
        $both = $this->find('green accounting');
        $this->assertEqualsCanonicalizing(['internal', 'external'], array_column($both['results'], 'kind'));
        $this->assertSame([1, 1], [$both['counts']['internal'], $both['counts']['external']]);
    }

    public function test_category_scope_only_returns_sources_of_the_selected_category(): void
    {
        $this->pub(['title' => 'Green accounting in firms']);
        $this->pub(['title' => 'Green accounting in management', 'cat' => $this->other]);
        $this->pub(['title' => 'Green accounting unclassified', 'cat' => null]);
        $this->doc('Green accounting internal other category', ['Green accounting.'], ['category_id' => $this->other->id]);

        $this->assertSame(['Green accounting in firms'], $this->titles($this->find('green accounting')));
        $this->assertEqualsCanonicalizing(['Green accounting in management', 'Green accounting internal other category'], $this->titles($this->find('green accounting', $this->other)), 'masing-masing kategori hanya melihat miliknya');
    }

    public function test_disabled_provider_and_unpublished_documents_are_not_searched(): void
    {
        $this->pub(['title' => 'Green accounting via openalex', 'provider' => 'openalex']);
        $this->doc('Green accounting draft', ['Green accounting.'], ['status' => 'draft']);
        $this->doc('Green accounting private', ['Green accounting.'], ['visibility' => 'private']);
        $this->doc('Green accounting without chunks', [], []);

        $this->assertSame([], $this->titles($this->find('green accounting')), 'OpenAlex nonaktif; draft/privat/tanpa chunk tidak pernah muncul');
    }

    /* =============================================================================== ranking */

    public function test_ranking_prefers_title_phrase_over_abstract_only_and_explains_why(): void
    {
        $abstractOnly = $this->pub(['title' => 'Corporate reporting practices', 'abstract' => 'This paper studies green accounting and its link to financial results in many firms.']);
        $keywordOnly = $this->pub(['title' => 'Environmental disclosure', 'abstract' => 'A study of environmental disclosure by listed firms in a long abstract text.', 'keywords_json' => ['green', 'accounting']]);
        $titlePhrase = $this->pub(['title' => 'Green Accounting and Financial Performance', 'abstract' => 'Short abstract about green accounting that is long enough to count as an abstract.']);

        $res = $this->find('green accounting');

        $this->assertSame([$titlePhrase->id, $abstractOnly->id, $keywordOnly->id], array_column($res['results'], 'id'));
        $top = $res['results'][0];
        $this->assertContains('Frasa «green accounting» ada di judul', $top['reasons']);
        $this->assertGreaterThan($res['results'][1]['score'], $top['score']);
        $this->assertSame(['green', 'accounting'], $top['matched']['title']);
        $this->assertLessThanOrEqual(100, $top['score']);
    }

    public function test_one_generic_word_or_one_of_many_terms_is_not_enough(): void
    {
        $this->pub(['title' => 'Accounting education in schools', 'abstract' => null]);                 // 1 dari 2 istilah inti
        $this->pub(['title' => 'Impact of weather on crops', 'abstract' => null]);                        // hanya kata umum

        $this->assertSame([], $this->titles($this->find('green accounting')));
        $this->assertSame([], $this->titles($this->find('impact green accounting')), 'kata umum tidak menentukan kelayakan');
    }

    public function test_future_dated_metadata_is_flagged_penalised_and_never_gets_year_credit(): void
    {
        $normal = $this->pub(['title' => 'Green accounting in practice', 'publication_year' => (int) now()->year]);
        $future = $this->pub(['title' => 'Green accounting in practice II', 'publication_year' => (int) now()->year + 1]);

        $res = $this->find('green accounting year '.((int) now()->year + 1));
        $byId = array_column($res['results'], null, 'id');

        $this->assertGreaterThan($byId[$future->id]['score'], $byId[$normal->id]['score']);
        $this->assertSame('future_year', $byId[$future->id]['flags'][0]['key']);
        $this->assertArrayNotHasKey('year_hint', $byId[$future->id]['score_parts']);

        $this->page(['category_id' => $this->cat->id, 'q' => 'green accounting'])->assertSee('masa depan', false);
    }

    public function test_year_and_type_filters_are_hard_filters_but_query_year_is_only_a_hint(): void
    {
        $a = $this->pub(['title' => 'Green accounting 2019', 'publication_year' => 2019]);
        $b = $this->pub(['title' => 'Green accounting 2023', 'publication_year' => 2023, 'publication_type' => 'book']);

        $this->pub(['title' => 'Green accounting undated', 'publication_year' => null]);
        $this->assertSame([2023, 2019, 'tanpa tahun'], array_keys($this->find('green accounting')['years']), 'sebaran tahun: terbaru dulu, "tanpa tahun" di akhir');
        Cache::flush();
        $this->assertSame([$a->id], array_column($this->find('green accounting', null, ['year' => 2019])['results'], 'id'));
        $this->assertSame([$b->id], array_column($this->find('green accounting', null, ['type' => 'book'])['results'], 'id'));
        $this->assertCount(3, $this->find('green accounting tahun 2023')['results'], 'tahun di kalimat tidak menyingkirkan hasil lain');
    }

    public function test_withdrawn_records_are_hidden_but_counted(): void
    {
        $this->pub(['title' => 'Retracted: Green accounting and profit']);
        $this->pub(['title' => 'Withdrawn green accounting paper']);
        $this->pub(['title' => 'Green accounting in listed firms']);

        $res = $this->find('green accounting');

        $this->assertSame(['Green accounting in listed firms'], $this->titles($res));
        $this->assertSame(2, $res['counts']['withdrawn_hidden']);
        $this->actingAs($this->user)->get(route('research.references.show', ['kind' => 'ext', 'id' => ExternalPublication::where('title', 'like', 'Retracted%')->value('id')]))->assertNotFound();
    }

    /* =============================================================================== top 5 & halaman */

    public function test_top_five_is_selected_after_ranking_all_candidates_and_all_view_paginates(): void
    {
        foreach (range(1, 8) as $i) {
            $this->pub(['title' => 'Green accounting study '.$i]);
        }
        $best = $this->pub(['title' => 'Green accounting', 'abstract' => 'Green accounting is the central topic of this abstract and it is long enough.', 'keywords_json' => ['green accounting']]);

        $res = $this->find('green accounting');
        $this->assertCount(9, $res['results'], 'semua kandidat diberi peringkat, bukan hanya 5');
        $this->assertSame($best->id, $res['results'][0]['id']);

        $html = $this->page(['category_id' => $this->cat->id, 'q' => 'green accounting'])->assertOk();
        $this->assertSame(5, substr_count($html->getContent(), 'class="refs-item"'));
        $html->assertSee('Lihat semua referensi (9)', false);

        $all = $this->page(['category_id' => $this->cat->id, 'q' => 'green accounting', 'view' => 'all']);
        $this->assertSame(9, substr_count($all->getContent(), 'class="refs-item"'));
        $page2 = $this->page(['category_id' => $this->cat->id, 'q' => 'green accounting', 'view' => 'all', 'page' => 1]);
        $this->assertSame(substr_count($all->getContent(), 'refs-item"'), substr_count($page2->getContent(), 'refs-item"'));
    }

    public function test_fewer_than_five_shows_only_those_and_none_shows_honest_empty_state(): void
    {
        foreach (range(1, 3) as $i) {
            $this->pub(['title' => 'Blockchain auditing '.$i]);
        }
        $this->pub(['title' => 'Unrelated botany paper']);

        $html = $this->page(['category_id' => $this->cat->id, 'q' => 'blockchain auditing'])->assertOk();
        $this->assertSame(3, substr_count($html->getContent(), 'class="refs-item"'), 'tidak ada pengisi dummy');
        $html->assertSee('hanya 3 yang cukup cocok', false)->assertDontSee('Lihat semua referensi');

        $empty = $this->page(['category_id' => $this->cat->id, 'q' => 'quantum chromodynamics'])->assertOk();
        $empty->assertSee('Bukti belum cukup')->assertDontSee('refs-item"', false)->assertDontSee('Analisis dengan AI');
    }

    /* =============================================================================== dedup */

    public function test_duplicate_doi_is_one_entry_and_same_title_with_different_doi_is_kept(): void
    {
        $this->doc('Green accounting and value', ['Green accounting raises firm value.'], ['doi' => '10.1234/GREEN.1']);
        $this->pub(['title' => 'Green accounting and value', 'doi' => '10.1234/green.1', 'provider' => 'crossref']);
        $this->pub(['title' => 'Green accounting twin', 'doi' => '10.5555/twin.a']);
        $this->pub(['title' => 'Green accounting twin', 'doi' => '10.5555/twin.b']);

        $res = $this->find('green accounting');
        $titles = $this->titles($res);

        $this->assertSame(1, count(array_keys($titles, 'Green accounting and value')), 'DOI sama (beda huruf) = satu entri');
        $this->assertSame(2, count(array_keys($titles, 'Green accounting twin')), 'judul sama dengan DOI berbeda tidak digabung');
        $merged = collect($res['results'])->firstWhere('title', 'Green accounting and value');
        $this->assertSame('internal', $merged['kind'], 'Pustaka dipertahankan karena memiliki bukti teks');
        $this->assertTrue($merged['also_external']);
        $this->assertContains('crossref', $merged['providers']);
    }

    /* =============================================================================== bukti & label */

    public function test_evidence_labels_reflect_what_is_actually_stored(): void
    {
        $meta = $this->pub(['title' => 'Green accounting metadata only', 'abstract' => null, 'availability' => 'METADATA_ONLY', 'oa_status' => 'unknown']);
        $abs = $this->pub(['title' => 'Green accounting with abstract', 'abstract' => str_repeat('Penjelasan green accounting dari penulis. ', 4), 'availability' => 'OPEN_ACCESS_LINK']);
        $doc = $this->doc('Green accounting full text', ['Green accounting disclosures were measured across 120 listed firms.']);

        $by = array_column($this->find('green accounting')['results'], null, 'title');

        $this->assertSame('METADATA ONLY', $by['Green accounting metadata only']['evidence_label']);
        $this->assertSame('ABSTRACT AVAILABLE', $by['Green accounting with abstract']['evidence_label'], 'dihitung dari abstrak tersimpan, bukan dari kolom availability');
        $this->assertSame('FULL TEXT INDEXED', $by['Green accounting full text']['evidence_label']);
        $this->assertStringContainsString('120 listed firms', $by['Green accounting full text']['snippet']);

        $html = $this->page(['category_id' => $this->cat->id, 'q' => 'green accounting'])->getContent();
        $this->assertStringNotContainsString('FULL TEXT AVAILABLE', $html, 'status yang tidak didukung database tidak ditampilkan');
        $this->assertStringContainsString('Abstrak tidak tersimpan untuk sumber ini', $html);
        $this->assertStringNotContainsString('Unduh PDF', $html);
        $this->assertStringNotContainsString('.pdf', $html);
        $this->assertSame($doc->title, $by['Green accounting full text']['title']);
    }

    public function test_snippet_is_a_real_excerpt_cut_at_word_boundaries(): void
    {
        $abstract = implode(' ', array_map(fn ($i) => "kata{$i}longword", range(1, 12))).' green accounting menjelaskan '.implode(' ', array_map(fn ($i) => "sisa{$i}panjang", range(1, 30)));
        $this->pub(['title' => 'Green accounting snippet', 'abstract' => $abstract]);

        $snippet = $this->find('green accounting')['results'][0]['snippet'];
        $words = array_filter(explode(' ', trim(str_replace('…', '', $snippet))));

        $this->assertStringContainsString('green accounting', $snippet);
        $this->assertStringStartsWith('… ', $snippet);
        $this->assertStringEndsWith(' …', $snippet);
        foreach ($words as $w) {
            $this->assertStringContainsString($w, $abstract);
            $this->assertMatchesRegularExpression('/(?:^|\s)'.preg_quote($w, '/').'(?:\s|$)/', $abstract, "'{$w}' harus kata utuh dari abstrak asli");
        }
    }

    public function test_missing_year_and_doi_are_flagged_not_hidden_and_link_goes_to_the_original(): void
    {
        $p = $this->pub(['title' => 'Green accounting no year', 'publication_year' => null, 'doi' => null, 'landing_url' => 'https://doaj.org/article/xyz']);
        $q = $this->pub(['title' => 'Green accounting with doi', 'doi' => '10.4242/abc/def']);

        $by = array_column($this->find('green accounting')['results'], null, 'id');
        $this->assertSame(['missing_year', 'missing_doi'], array_column($by[$p->id]['flags'], 'key'));
        $this->assertSame('https://doaj.org/article/xyz', $by[$p->id]['landing_url']);
        $this->assertSame('https://doi.org/10.4242/abc/def', $by[$q->id]['doi_url']);

        $html = $this->page(['category_id' => $this->cat->id, 'q' => 'green accounting'])->assertOk()->getContent();
        $this->assertStringContainsString('href="https://doi.org/10.4242/abc/def" target="_blank" rel="noopener noreferrer"', $html);
        $this->assertStringContainsString('Tanpa DOI', $html);
    }

    public function test_publisher_link_is_offered_as_a_fallback_when_stored_and_safe(): void
    {
        $this->pub(['title' => 'Green accounting with publisher link', 'fulltext_url' => 'https://journal.example.org/article/1.pdf']);
        $this->pub(['title' => 'Green accounting with unsafe publisher link', 'fulltext_url' => 'javascript:alert(1)']);
        $this->pub(['title' => 'Green accounting without publisher link']);

        $html = $this->page(['category_id' => $this->cat->id, 'q' => 'green accounting'])->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'Tautan alternatif'), 'hanya sumber dengan URL penerbit yang aman');
        $this->assertStringContainsString('href="https://journal.example.org/article/1.pdf" target="_blank" rel="noopener noreferrer"', $html);
        $this->assertStringNotContainsString('javascript:alert', $html);
        $this->assertStringNotContainsString('Unduh PDF', $html, 'bukan tombol PDF dan tidak mengklaim teks lengkap tersimpan');
    }

    public function test_openalex_records_appear_only_while_enabled_with_provenance_and_honest_labels(): void
    {
        $p = $this->pub(['title' => 'Green accounting OpenAlex record', 'provider' => 'openalex', 'doi' => null, 'landing_url' => 'https://uni.example.edu/paper/7']);
        $params = ['category_id' => $this->cat->id, 'q' => 'green accounting'];

        $this->assertSame([], $this->titles($this->find('green accounting')), 'OpenAlex nonaktif: tidak dibaca');

        app(ExternalSourceSettings::class)->save('openalex', ['enabled' => 1], null);
        $row = collect($this->find('green accounting')['results'])->firstWhere('id', $p->id);
        $this->assertSame(['openalex'], $row['providers']);
        $this->assertSame('METADATA ONLY', $row['evidence_label']);
        $this->assertSame('https://uni.example.edu/paper/7', $row['landing_url']);

        $html = $this->page($params)->assertOk()->getContent();
        $this->assertStringContainsString('OpenAlex', $html);
        $this->assertStringNotContainsString('FULL TEXT', $html);
        $this->assertStringNotContainsString('Unduh PDF', $html);
    }

    /* =============================================================================== V1.1: urutan berbasis relevansi */

    private const ABS = 'Green accounting practices are examined across many firms and reporting regimes.';

    private function row(array $res, ExternalPublication $p): array
    {
        return collect($res['results'])->firstWhere('id', $p->id);
    }

    public function test_equal_scores_are_ordered_by_title_not_by_id_or_provider_and_flagged_as_tied(): void
    {
        Http::fake();
        app(ExternalSourceSettings::class)->save('openalex', ['enabled' => 1], null);
        // Id naik: Zeta < Mike < Alpha. Penyedia berbeda. Isi identik -> skor identik.
        $z = $this->pub(['title' => 'Zeta green accounting practices', 'abstract' => self::ABS, 'provider' => 'doaj']);
        $m = $this->pub(['title' => 'Mike green accounting practices', 'abstract' => self::ABS, 'provider' => 'openalex']);
        $a = $this->pub(['title' => 'Alpha green accounting practices', 'abstract' => self::ABS, 'provider' => 'crossref']);

        $res = $this->find('green accounting');
        $this->assertSame(['Alpha green accounting practices', 'Mike green accounting practices', 'Zeta green accounting practices'], $this->titles($res));
        $this->assertCount(1, array_unique(array_column($res['results'], 'score')), 'skor memang sama');
        $this->assertSame([2, 2, 2], array_column($res['results'], 'tied_with'));

        // Menukar penyedia tidak mengubah urutan: tidak ada bonus penyedia.
        foreach ([[$z, 'crossref'], [$m, 'doaj'], [$a, 'openalex']] as [$p, $provider]) {
            ExternalPublicationSource::where('publication_id', $p->id)->update(['provider' => $provider]);
        }
        Cache::flush();
        $this->assertSame(['Alpha green accounting practices', 'Mike green accounting practices', 'Zeta green accounting practices'], $this->titles($this->find('green accounting')));

        $html = $this->page(['category_id' => $this->cat->id, 'q' => 'green accounting'])->assertOk()->getContent();
        $this->assertStringContainsString('bukan peringkat kualitas ilmiah', $html);
        $this->assertStringContainsString('Setara dengan 2 lainnya', $html);
        Http::assertNothingSent();
    }

    public function test_relevance_decides_before_id_and_a_clear_winner_is_not_flagged_tied(): void
    {
        $weak = $this->pub(['title' => 'Corporate governance review', 'abstract' => 'Green accounting is mentioned once.']);
        $strong = $this->pub(['title' => 'Green accounting', 'abstract' => self::ABS]);

        $res = $this->find('green accounting');
        $this->assertSame([$strong->id, $weak->id], array_column($res['results'], 'id'));
        $this->assertGreaterThan($this->row($res, $weak)['score'], $this->row($res, $strong)['score']);
        $this->assertSame(0, $this->row($res, $strong)['tied_with']);

        $html = $this->page(['category_id' => $this->cat->id, 'q' => 'green accounting'])->assertOk()->getContent();
        $this->assertStringNotContainsString('bukan peringkat kualitas ilmiah', $html);
        $this->assertStringNotContainsString('Setara dengan', $html);
    }

    public function test_exact_title_phrase_outranks_scattered_terms(): void
    {
        $scattered = $this->pub(['title' => 'Accounting for green growth', 'abstract' => self::ABS]);
        $exact = $this->pub(['title' => 'Green accounting for growth', 'abstract' => self::ABS]);

        $res = $this->find('green accounting');
        $this->assertSame([$exact->id, $scattered->id], array_column($res['results'], 'id'));
        $this->assertGreaterThan($this->row($res, $scattered)['score'], $this->row($res, $exact)['score']);
    }

    public function test_title_focus_and_abstract_repetition_separate_otherwise_similar_matches(): void
    {
        $long = $this->pub(['title' => 'Green accounting in emerging manufacturing firms across southeast Asia', 'abstract' => self::ABS]);
        $focused = $this->pub(['title' => 'Green accounting', 'abstract' => self::ABS]);
        $res = $this->find('green accounting');
        $this->assertSame([$focused->id, $long->id], array_column($res['results'], 'id'), 'judul yang berfokus pada topik lebih relevan');

        $once = $this->pub(['title' => 'Notes on green accounting', 'abstract' => 'Green accounting is one topic among many other unrelated matters here.']);
        $thrice = $this->pub(['title' => 'Notes on green accounting too', 'abstract' => 'Green accounting is studied. Green accounting is compared. Green accounting is applied.']);
        Cache::flush();
        $res = $this->find('green accounting');
        $this->assertGreaterThan($this->row($res, $once)['score'], $this->row($res, $thrice)['score']);
        $this->assertContains('Frasa «green accounting» muncul 3 kali di abstrak', $this->row($res, $thrice)['reasons']);
    }

    public function test_evidence_availability_does_not_raise_the_score_but_separates_equal_scores(): void
    {
        // Sama-sama tidak punya kecocokan di abstrak; satu tanpa abstrak (id lebih kecil, judul lebih awal), satu dengan abstrak lain.
        $meta = $this->pub(['title' => 'Alpha green accounting', 'abstract' => null]);
        $abs = $this->pub(['title' => 'Zulu green accounting', 'abstract' => 'This note concerns audit committees and nothing else of interest to the query here.']);

        $res = $this->find('green accounting');
        $this->assertSame($this->row($res, $meta)['score'], $this->row($res, $abs)['score'], 'bukti tidak menambah skor');
        $this->assertSame([$abs->id, $meta->id], array_column($res['results'], 'id'), 'bukti hanya memisahkan skor yang sama');
        $this->assertSame([0, 0], array_column($res['results'], 'tied_with'), 'bukti berbeda = tidak setara');
        $this->assertSame('METADATA ONLY', $this->row($res, $meta)['evidence_label']);
        $this->assertSame('ABSTRACT AVAILABLE', $this->row($res, $abs)['evidence_label']);
    }

    public function test_missing_doi_and_missing_abstract_are_ranked_by_real_signals_without_errors(): void
    {
        $noDoi = $this->pub(['title' => 'Green accounting standards', 'abstract' => null, 'doi' => null]);
        $full = $this->pub(['title' => 'Green accounting standards', 'abstract' => self::ABS]);

        $res = $this->find('green accounting');
        $this->assertSame([$full->id, $noDoi->id], array_column($res['results'], 'id'));
        $row = $this->row($res, $noDoi);
        $this->assertContains('Tanpa DOI', array_column($row['flags'], 'label'));
        $this->assertSame('Abstrak tidak tersimpan: hanya metadata', collect($row['reasons'])->last());
    }

    public function test_ordering_is_stable_and_follows_the_documented_rule(): void
    {
        foreach (['Delta', 'Bravo', 'Echo', 'Alpha', 'Charlie'] as $i => $name) {
            $this->pub(['title' => "{$name} green accounting".($i % 2 ? ' practices' : ''), 'abstract' => $i === 3 ? null : self::ABS]);
        }
        $first = $this->find('green accounting')['results'];
        Cache::flush();
        $second = $this->find('green accounting')['results'];
        $this->assertSame(array_column($first, 'key'), array_column($second, 'key'));

        $expected = $first;
        usort($expected, fn ($x, $y) => [-$x['score'], -$x['evidence_rank'], QueryAnalyzer::normalize($x['title'])] <=> [-$y['score'], -$y['evidence_rank'], QueryAnalyzer::normalize($y['title'])]);
        $this->assertSame(array_column($expected, 'title'), array_column($first, 'title'));
    }

    public function test_relevance_reasons_are_factual_and_never_claim_findings(): void
    {
        $p = $this->pub(['title' => 'Green accounting practices', 'abstract' => self::ABS]);
        $r = app(SourceDiscovery::class)->describe('ext', $p->id, $this->cat->id, 'green accounting');

        $text = implode(' | ', $r['reasons']);
        $this->assertStringContainsString('Frasa «green accounting» ada di judul', $text);
        $this->assertStringContainsString('Frasa «green accounting» ada di abstrak', $text);
        $this->assertStringContainsString('Sesuai kategori Accounting & Finance (disarankan, belum diverifikasi admin)', $text);
        $this->assertStringContainsString('Abstrak tersedia (ketersediaan bukti, bukan penilaian mutu)', $text);
        $this->assertDoesNotMatchRegularExpression('/membuktikan|terbukti|menunjukkan bahwa|proves|demonstrates/i', $text);

        $html = $this->actingAs($this->user)->get(route('research.references.show', ['kind' => 'ext', 'id' => $p->id, 'category_id' => $this->cat->id, 'q' => 'green accounting']))->assertOk()->getContent();
        $this->assertStringContainsString('Ketersediaan bukti tidak menambah skor', $html);
        $this->assertStringContainsString('bukan penilaian mutu atau temuan penelitian', $html);
    }

    public function test_openalex_work_id_is_shown_and_linked_only_when_it_is_a_stored_valid_id(): void
    {
        app(ExternalSourceSettings::class)->save('openalex', ['enabled' => 1], null);
        $good = $this->pub(['title' => 'Green accounting with valid id', 'provider' => 'openalex']);
        $odd = $this->pub(['title' => 'Green accounting with odd id', 'provider' => 'openalex']);
        $other = $this->pub(['title' => 'Green accounting from doaj', 'provider' => 'doaj']);
        ExternalPublicationSource::where('publication_id', $good->id)->update(['external_id' => 'W1590592679']);
        ExternalPublicationSource::where('publication_id', $odd->id)->update(['external_id' => 'bukan-id "><script>']);
        ExternalPublicationSource::where('publication_id', $other->id)->update(['external_id' => 'W999']);

        $show = fn ($p) => $this->actingAs($this->user)->get(route('research.references.show', ['kind' => 'ext', 'id' => $p->id, 'category_id' => $this->cat->id, 'q' => 'green accounting']))->assertOk()->getContent();

        $html = $show($good);
        $this->assertStringContainsString('OpenAlex Work ID', $html);
        $this->assertStringContainsString('href="https://openalex.org/W1590592679"', $html);
        $this->assertStringContainsString('>W1590592679<', $html);

        foreach ([$odd, $other] as $p) {
            $html = $show($p);
            $this->assertStringNotContainsString('OpenAlex Work ID', $html);
            $this->assertStringNotContainsString('openalex.org/', $html);
            $this->assertStringNotContainsString('<script>', $html);
        }
    }

    public function test_untrusted_metadata_is_escaped_and_unsafe_urls_are_not_linked(): void
    {
        $this->pub(['title' => 'Green accounting <script>alert(1)</script>', 'doi' => null, 'landing_url' => 'javascript:alert(1)', 'abstract' => 'Green accounting <img src=x onerror=alert(2)> '.str_repeat('teks ', 12)]);

        $html = $this->page(['category_id' => $this->cat->id, 'q' => 'green accounting'])->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert(1)', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringNotContainsString('javascript:alert', $html);
        $this->assertStringContainsString('Tidak ada tautan publik', $html);
        $this->assertSame('a &lt;b&gt; <mark>green</mark>', (string) Highlighter::mark('a <b> green', ['green']));
    }

    /* =============================================================================== halaman, detail, privasi */

    public function test_detail_page_shows_provenance_and_honest_limits_for_metadata_only(): void
    {
        $p = $this->pub(['title' => 'Green accounting metadata only', 'abstract' => null]);

        $r = $this->actingAs($this->user)->get(route('research.references.show', ['kind' => 'ext', 'id' => $p->id, 'category_id' => $this->cat->id, 'q' => 'green accounting']))->assertOk();

        $r->assertSee('METADATA ONLY')->assertSee('Hanya metadata: sistem hanya memiliki identitas publikasi', false)->assertSee('halaman di penyedia')->assertSee('Mengapa relevan');
        $r->assertDontSee('Abstract available');
    }

    public function test_pages_require_login_and_detail_hides_non_eligible_sources(): void
    {
        $draft = $this->doc('Green accounting draft', ['Green accounting.'], ['status' => 'draft']);
        $okDoc = $this->doc('Green accounting ok', ['Green accounting.']);
        $orphan = $this->pub(['title' => 'Green accounting openalex only', 'provider' => 'openalex']);

        $this->get(route('research.references'))->assertRedirect(route('login'));
        $this->actingAs($this->user)->get(route('research.references.show', ['kind' => 'doc', 'id' => $draft->id]))->assertNotFound();
        $this->actingAs($this->user)->get(route('research.references.show', ['kind' => 'ext', 'id' => $orphan->id]))->assertNotFound();
        $this->actingAs($this->user)->get(route('research.references.show', ['kind' => 'doc', 'id' => $okDoc->id]))->assertOk();
        $this->actingAs($this->user)->get('/research/references/xyz/1')->assertNotFound();
    }

    public function test_results_never_include_other_users_data(): void
    {
        $this->pub(['title' => 'Green accounting visible']);
        $stranger = User::factory()->create(['email' => 'rahasia-orang-lain@example.test', 'status' => 'active']);
        \App\Models\Conversation::create(['user_id' => $stranger->id, 'category_id' => $this->cat->id, 'title' => 'Green accounting rahasia pengguna lain', 'status' => 'active']);

        $html = $this->page(['category_id' => $this->cat->id, 'q' => 'green accounting'])->assertOk()->getContent();

        $this->assertStringNotContainsString('rahasia pengguna lain', $html);
        $this->assertStringNotContainsString('rahasia-orang-lain', $html);
    }

    public function test_workspace_links_to_references_and_search_is_read_only(): void
    {
        $this->actingAs($this->user)->get(route('workspace'))->assertOk()->assertDontSee('Cari referensi dulu', false);

        $before = [DB::table('ai_runs')->count(), DB::table('research_conversations')->count(), DB::table('external_publications')->count(), DB::table('documents')->count()];
        $this->pub(['title' => 'Green accounting']);
        $this->page(['category_id' => $this->cat->id, 'q' => 'green accounting'])->assertOk();
        $this->assertSame([$before[0], $before[1], $before[2] + 1, $before[3]], [DB::table('ai_runs')->count(), DB::table('research_conversations')->count(), DB::table('external_publications')->count(), DB::table('documents')->count()]);
    }

    /* =============================================================================== AI sesuai permintaan */

    public function test_search_never_calls_ai_even_when_a_provider_is_configured(): void
    {
        config(['research.ai_provider' => 'openai', 'services.openai.api_key' => 'sk-test-xyz']);
        Http::fake();
        $this->app->bind(\App\Services\Research\AiProviderManager::class, fn () => throw new \RuntimeException('AI tidak boleh dipanggil dari pencarian referensi'));
        $this->pub(['title' => 'Green accounting']);

        $this->page(['category_id' => $this->cat->id, 'q' => 'green accounting'])->assertOk();
        $this->actingAs($this->user)->get(route('research.references.show', ['kind' => 'ext', 'id' => ExternalPublication::value('id')]))->assertOk();

        Http::assertNothingSent();
        $this->assertSame(0, AiRun::count());
    }

    public function test_ai_button_is_disabled_with_explanation_when_provider_is_unavailable(): void
    {
        config(['research.ai_provider' => 'mock']);
        $this->pub(['title' => 'Green accounting']);

        $html = $this->page(['category_id' => $this->cat->id, 'q' => 'green accounting'])->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<button[^>]*data-refs-ai-submit[^>]*\sdisabled/s', $html);
        $this->assertStringContainsString('Analisis AI belum tersedia', $html);
        $this->assertStringContainsString('Susun Research Gap', $html, 'aksi mengikuti Katalog Alat AI aktif');
        $this->assertStringContainsString('Petakan Teori', $html);
    }

    public function test_ai_button_is_enabled_only_when_a_real_provider_is_configured(): void
    {
        $this->pub(['title' => 'Green accounting']);
        config(['research.ai_provider' => 'openai', 'services.openai.api_key' => '']);
        $this->assertDoesNotMatchRegularExpression('/<button[^>]*data-refs-ai-submit[^>]*\sdisabled\b(?!:)/s', '') ;

        $no = $this->page(['category_id' => $this->cat->id, 'q' => 'green accounting'])->getContent();
        $this->assertStringContainsString('kunci API belum dikonfigurasi', $no);

        config(['services.openai.api_key' => 'sk-test-xyz']);
        $yes = $this->page(['category_id' => $this->cat->id, 'q' => 'green accounting'])->getContent();
        $this->assertDoesNotMatchRegularExpression('/<button[^>]*data-refs-ai-submit[^>]*\sdisabled/s', $yes);
    }

    public function test_analysis_preflight_requires_explicit_confirmation_and_never_runs_ai_here(): void
    {
        Http::fake();
        $this->pub(['title' => 'Green accounting external', 'abstract' => null]);
        $this->doc('Green accounting internal', ['Green accounting.']);
        $res = $this->find('green accounting')['results'];
        $keys = array_column($res, 'key');
        $tool = app(AnalysisContract::class)->actions()[0]['prompt_template_id'];
        $payload = ['category_id' => $this->cat->id, 'q' => 'green accounting', 'prompt_template_id' => $tool, 'keys' => $keys];

        // 1. provider mock: tidak tersedia
        config(['research.ai_provider' => 'mock']);
        $this->actingAs($this->user)->post(route('research.references.analysis'), $payload)->assertOk()->assertSee('Analisis AI belum tersedia');

        // 2. tersedia tetapi belum dikonfirmasi: ringkasan + batasan
        config(['research.ai_provider' => 'openai', 'services.openai.api_key' => 'sk-test-xyz']);
        $r = $this->actingAs($this->user)->post(route('research.references.analysis'), $payload)->assertOk();
        $r->assertSee('Konfirmasi analisis dengan AI')->assertSee('Saya mengerti batasannya')->assertSee('hanya berisi metadata', false);

        // 3. dikonfirmasi dengan sumber eksternal: belum ada jalur ke mesin sintesis -> jujur, tidak ada AI
        $this->actingAs($this->user)->post(route('research.references.analysis'), $payload + ['confirm' => 1])->assertOk()->assertSee('belum dapat dijalankan');

        // 4. tepat satu dokumen Pustaka dikonfirmasi: diserahkan ke Workspace (pengguna menekan kirim sendiri)
        $docKey = collect($res)->firstWhere('kind', 'internal')['key'];
        $this->actingAs($this->user)->post(route('research.references.analysis'), ['keys' => [$docKey], 'confirm' => 1] + $payload)
            ->assertRedirect(route('workspace', ['source' => (int) Str::after($docKey, ':'), 'prompt' => $tool]));

        Http::assertNothingSent();
        $this->assertSame(0, AiRun::count());
    }

    public function test_analysis_preflight_validates_input_server_side(): void
    {
        $this->pub(['title' => 'Green accounting']);
        $tool = app(AnalysisContract::class)->actions()[0]['prompt_template_id'];
        $base = ['category_id' => $this->cat->id, 'q' => 'green accounting', 'prompt_template_id' => $tool];

        $this->actingAs($this->user)->from(route('research.references'))->post(route('research.references.analysis'), $base)->assertSessionHasErrors('keys');
        $this->actingAs($this->user)->post(route('research.references.analysis'), $base + ['keys' => ['ext:999999']])->assertSessionHasErrors('keys');
        $this->actingAs($this->user)->post(route('research.references.analysis'), $base + ['keys' => ['ext:1; drop']])->assertSessionHasErrors('keys.0');
        $this->actingAs($this->user)->post(route('research.references.analysis'), ['prompt_template_id' => 999999, 'keys' => ['ext:1']] + $base)->assertSessionHasErrors('prompt_template_id');
        \Illuminate\Support\Facades\Auth::logout();
        $this->flushSession();
        $this->post(route('research.references.analysis'), $base + ['keys' => ['ext:1']])->assertRedirect(route('login'));
    }

    public function test_contract_separates_evidence_types_and_rejects_invented_citations(): void
    {
        $this->pub(['title' => 'Green accounting metadata', 'abstract' => null]);
        $this->pub(['title' => 'Green accounting abstract', 'abstract' => 'Abstrak penulis tentang green accounting yang cukup panjang untuk dihitung.']);
        $this->doc('Green accounting full', ['Green accounting evidence text in the document body.']);
        $svc = app(AnalysisContract::class);

        $contract = $svc->build($this->find('green accounting')['results']);
        $by = array_column($contract['sources'], null, 'title');

        $this->assertNull($by['Green accounting metadata']['evidence']['content'], 'metadata tidak pernah diubah menjadi isi');
        $this->assertSame(['identity'], $by['Green accounting metadata']['claims_allowed']);
        $this->assertSame(['identity', 'abstract_statement'], $by['Green accounting abstract']['claims_allowed']);
        $this->assertContains('text_evidence', $by['Green accounting full']['claims_allowed']);
        $this->assertSame(['S1', 'S2', 'S3'], array_column($contract['sources'], 'citation_id'));
        $this->assertSame(['S9'], $svc->unknownCitations(['S1', 'S9'], $contract), 'sitasi karangan ditolak');
        $this->assertFalse($svc->claimAllowed($contract, $by['Green accounting metadata']['citation_id'], 'abstract_statement'));
        $this->assertTrue($svc->claimAllowed($contract, $by['Green accounting full']['citation_id'], 'text_evidence'));
        $this->assertNotEmpty(array_filter($contract['limitations'], fn ($l) => str_contains($l, 'hanya berisi metadata')));
    }

    /* =============================================================================== performa */

    public function test_search_examination_is_bounded_and_fast_even_when_nothing_can_be_ruled_out(): void
    {
        // 3.000 baris yang nyaris identik: batas atas tak bisa memisahkan satu pun, jadi satu-satunya penghenti adalah batas pemeriksaan.
        config(['research.discovery.examine_limit' => 300]);
        $rows = [];
        $now = now()->toDateTimeString();
        foreach (range(1, 3000) as $i) {
            $rows[] = ['uuid' => (string) Str::uuid(), 'title' => "Green accounting paper {$i}", 'title_hash' => sha1("p{$i}"), 'checksum' => sha1("k{$i}"), 'doi' => "10.7777/p.{$i}", 'publication_year' => 2000 + $i % 25, 'publication_type' => 'article', 'abstract' => 'Green accounting abstract '.$i, 'created_at' => $now, 'updated_at' => $now];
        }
        foreach (array_chunk($rows, 300) as $chunk) {
            DB::table('external_publications')->insert($chunk);
        }
        $ids = DB::table('external_publications')->pluck('id');
        foreach ($ids->chunk(300) as $chunk) {
            DB::table('external_publication_sources')->insert($chunk->map(fn ($id) => ['publication_id' => $id, 'provider' => 'doaj', 'external_id' => "e{$id}", 'checksum' => sha1("s{$id}"), 'last_seen_at' => $now, 'created_at' => $now, 'updated_at' => $now])->all());
            DB::table('external_publication_categories')->insert($chunk->map(fn ($id) => ['publication_id' => $id, 'category_id' => $this->cat->id, 'status' => 'suggested', 'query_term' => 'uji', 'created_at' => $now, 'updated_at' => $now])->all());
        }

        DB::enableQueryLog();
        $t = microtime(true);
        $res = $this->find('green accounting');
        $ms = (microtime(true) - $t) * 1000;
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(3000, $res['counts']['external'], 'jumlah kandidat cocok di SQL dicatat apa adanya');
        $this->assertGreaterThanOrEqual(300, $res['counts']['examined']);
        $this->assertLessThanOrEqual(300 + 250, $res['counts']['examined'], 'pemeriksaan dibatasi (satu kelompok melewati batas paling banyak)');
        $this->assertTrue($res['counts']['truncated'], 'tidak mengaku pasti bila batas tercapai sebelum terbukti');
        $this->assertFalse($res['counts']['exact']);
        $this->assertLessThan(40, $queries, 'jumlah query tumbuh menurut kelompok pemeriksaan, bukan menurut jumlah baris');
        // Pagar pengaman terhadap kerja tak terbatas, bukan tolok ukur: batas kerja yang sesungguhnya diperiksa lewat jumlah baris dinilai dan query di atas.
        $this->assertLessThan(20000, $ms, "pencarian 3.000 baris tidak boleh berjalan tanpa batas (terukur {$ms} ms)");

        $t = microtime(true);
        $this->find('green accounting');
        $this->assertLessThan(150, (microtime(true) - $t) * 1000, 'permintaan kedua dilayani dari cache');
    }
}
