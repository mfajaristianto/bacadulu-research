<?php

namespace Tests\Feature;

use App\Models\AiRun;
use App\Models\Category;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\ExternalFulltext;
use App\Models\ExternalFulltextChunk;
use App\Models\ExternalPublication;
use App\Models\ExternalPublicationCategory;
use App\Models\ExternalPublicationSource;
use App\Models\User;
use App\Services\Discovery\SourceDiscovery;
use App\Services\Evidence\CitationId;
use App\Services\Evidence\Evidence;
use App\Services\Evidence\EvidenceBundleBuilder;
use App\Services\Evidence\EvidenceCitationValidator;
use App\Services\Evidence\ResearchEvidenceBundle;
use App\Services\ExternalSources\ExternalSourceSettings;
use App\Services\FullText\FullTextState as S;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\FullTextFixtures;
use Tests\TestCase;

/** Research Evidence Pipeline V1: bukti dari Pustaka, abstrak eksternal, dan full-text eksternal. Tanpa AI, tanpa jaringan, tanpa unduhan. */
class ResearchEvidenceBundleTest extends TestCase
{
    use FullTextFixtures;
    use RefreshDatabase;

    private Category $cat;

    private int $n = 0;

    private const Q = 'pelaporan keuangan perusahaan';

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFullText();
        $this->cat = Category::create(['name' => 'Accounting & Finance', 'slug' => 'acc', 'status' => 'active']);
        foreach (['crossref', 'doaj'] as $p) {
            app(ExternalSourceSettings::class)->save($p, ['enabled' => 1], null);
        }
        Http::fake();
    }

    /** Publikasi eksternal yang ditemukan Source Discovery (kategori + sumber). $o['abstract'] null = metadata saja. */
    private function ext(string $title, ?string $abstract, array $o = [], ?Category $cat = null): ExternalPublication
    {
        $i = ++$this->n;
        $p = $this->pub($o + ['title' => $title, 'abstract' => $abstract, 'doi' => "10.7777/ev.{$i}", 'fulltext_url' => null, 'landing_url' => "https://doaj.org/article/{$i}"]);
        ExternalPublicationSource::create(['publication_id' => $p->id, 'provider' => 'doaj', 'external_id' => "ev{$i}"]);
        ExternalPublicationCategory::create(['publication_id' => $p->id, 'category_id' => ($cat ?? $this->cat)->id, 'status' => 'suggested']);

        return $p;
    }

    /** Menandai publikasi FULL_TEXT_INDEXED dengan chunk sungguhan. $chunks: [text, page|null, section|null, refs?] */
    private function index(ExternalPublication $p, array $chunks): ExternalFulltext
    {
        $ft = ExternalFulltext::create(['publication_id' => $p->id, 'provider' => 'openalex', 'url' => "https://repo.example.org/{$p->id}.pdf", 'url_hash' => sha1("ev{$p->id}"), 'kind' => 'pdf',
            'license_code' => 'cc-by', 'version' => 'publishedVersion', 'permission' => 'allow', 'state' => S::INDEXED, 'sha256' => hash('sha256', "f{$p->id}"), 'chunk_count' => count($chunks), 'extraction_method' => 'pdftotext']);
        foreach ($chunks as $k => [$text, $page, $section]) {
            ExternalFulltextChunk::create(['fulltext_id' => $ft->id, 'publication_id' => $p->id, 'chunk_index' => $k, 'section' => $section, 'page_start' => $page, 'page_end' => $page,
                'is_references' => false, 'content' => $text, 'content_sha' => sha1($text), 'source_checksum' => $ft->sha256, 'word_count' => str_word_count($text)]);
        }

        return $ft;
    }

    private function doc(string $title, array $chunks, array $o = [], ?Category $cat = null): Document
    {
        $d = Document::create($o + ['uuid' => (string) Str::uuid(), 'title' => $title, 'abstract' => null, 'category_id' => ($cat ?? $this->cat)->id, 'document_type' => 'pdf', 'publication_year' => 2021, 'language' => 'id',
            'status' => 'published', 'visibility' => 'public', 'uploaded_by' => User::factory()->create()->id, 'published_at' => now()]);
        foreach ($chunks as $i => $c) {
            DocumentChunk::create(['document_id' => $d->id, 'chunk_index' => $i, 'content' => is_array($c) ? $c[0] : $c, 'metadata_json' => is_array($c) && $c[1] !== null ? ['page' => $c[1]] : []]);
        }

        return $d;
    }

    private function bundle(?Category $cat = null, string $q = self::Q): ResearchEvidenceBundle
    {
        Cache::flush();

        return app(EvidenceBundleBuilder::class)->build(($cat ?? $this->cat)->id, $q);
    }

    // ---- level bukti per jenis sumber -------------------------------------------------------------------------------------

    public function test_mixed_sources_get_honest_levels_and_metadata_only_never_gets_evidence_text(): void
    {
        $full = $this->ext('Pelaporan keuangan perusahaan dan nilai pasar', 'Studi pelaporan keuangan perusahaan terbuka di bursa efek Indonesia.');
        $this->index($full, [['Hasil regresi menunjukkan pelaporan keuangan perusahaan berpengaruh positif terhadap nilai pasar saham.', 7, 'results']]);
        $abs = $this->ext('Kualitas pelaporan keuangan perusahaan manufaktur', 'Penelitian ini menguji kualitas pelaporan keuangan perusahaan manufaktur dengan sampel tiga tahun.');
        $meta = $this->ext('Pelaporan keuangan perusahaan: tinjauan', null);
        $doc = $this->doc('Panduan pelaporan keuangan perusahaan', [['Pelaporan keuangan perusahaan wajib mengikuti standar akuntansi yang berlaku.', 12]]);

        $b = $this->bundle();
        $by = collect($b->sources())->keyBy('source_id');

        $this->assertSame(Evidence::FULL_TEXT, $by["EXT:{$full->id}"]['evidence_level']);
        $this->assertSame(Evidence::ABSTRACT, $by["EXT:{$abs->id}"]['evidence_level']);
        $this->assertSame(Evidence::METADATA_ONLY, $by["EXT:{$meta->id}"]['evidence_level']);
        $this->assertSame(Evidence::FULL_TEXT, $by["INT:DOC-{$doc->id}"]['evidence_level']);
        $this->assertSame(Evidence::FULLTEXT_SUPPORTED, $by["EXT:{$full->id}"]['claim_support']);
        $this->assertSame(Evidence::ABSTRACT_SUPPORTED, $by["EXT:{$abs->id}"]['claim_support']);
        $this->assertSame(Evidence::BIBLIOGRAPHIC_ONLY, $by["EXT:{$meta->id}"]['claim_support']);

        // Metadata saja: tidak ada satu pun item evidence bertext untuk sumber itu.
        $this->assertSame([], $by["EXT:{$meta->id}"]['evidence_ids']);
        $this->assertSame([], array_values(array_filter($b->items(), fn ($i) => $i['source_id'] === "EXT:{$meta->id}")));
        $this->assertSame(['sources' => 4, 'full_text' => 2, 'abstract' => 1, 'metadata_only' => 1], array_intersect_key($b->summary(), array_flip(['sources', 'full_text', 'abstract', 'metadata_only'])));
    }

    public function test_item_carries_the_full_evidence_contract_for_external_full_text(): void
    {
        $p = $this->ext('Pelaporan keuangan perusahaan dan nilai pasar', 'Studi pelaporan keuangan perusahaan.', ['authors_json' => ['A. Penulis', 'B. Peneliti'], 'publication_year' => 2022]);
        $ft = $this->index($p, [['Hasil regresi menunjukkan pelaporan keuangan perusahaan berpengaruh positif.', 7, 'results']]);

        $i = $this->bundle()->items()[0];

        $this->assertSame('EV01', $i['evidence_id']);
        $this->assertSame(Evidence::KIND_EXTERNAL_FULLTEXT, $i['source_kind']);
        $this->assertSame("EXT:{$p->id}:C0:P7", $i['citation_id']);
        $this->assertSame([$p->title, ['A. Penulis', 'B. Peneliti'], 2022, $p->doi, 'openalex'], [$i['title'], $i['authors'], $i['year'], $i['doi'], $i['provider']]);
        $this->assertSame([Evidence::FULL_TEXT, 'results', 7, 7, 'cc-by'], [$i['evidence_level'], $i['section'], $i['page_start'], $i['page_end'], $i['license']]);
        $this->assertSame($ft->sha256, $i['source_checksum']);
        $this->assertSame(sha1($i['text']), $i['chunk_checksum']);
        $this->assertSame('Hasil regresi menunjukkan pelaporan keuangan perusahaan berpengaruh positif.', $i['text'], 'teks berasal persis dari chunk database');
        $this->assertSame(['pdftotext', $ft->id], [$i['provenance']['extraction_method'], $i['provenance']['fulltext_id']]);
        $this->assertSame([Evidence::CLAIM_FULLTEXT], $i['allowed_claims']);
        $this->assertStringStartsWith('https://doi.org/', $i['source_url'], 'URL sumber = DOI publik, bukan berkas lokal');
        $this->assertStringNotContainsString('repo.example.org', json_encode($i), 'URL berkas/penyimpanan tidak masuk bundle');
    }

    public function test_abstract_evidence_internal_documents_and_missing_fields(): void
    {
        $p = $this->ext('Pelaporan keuangan perusahaan tanpa DOI', 'Abstrak penelitian pelaporan keuangan perusahaan yang cukup panjang untuk dipakai.', ['doi' => null]);
        $doc = $this->doc('Dokumen tanpa nomor halaman pelaporan keuangan perusahaan', ['Pelaporan keuangan perusahaan dijelaskan di bagian ini tanpa nomor halaman.']);

        $items = collect($this->bundle()->items())->keyBy('source_id');

        $a = $items["EXT:{$p->id}"];
        $this->assertSame([Evidence::ABSTRACT, "EXT:{$p->id}:ABS", null, null, 'abstract'], [$a['evidence_level'], $a['citation_id'], $a['doi'], $a['page_start'], $a['section']]);
        $this->assertSame(ExternalPublication::find($p->id)->checksum, $a['source_checksum']);
        $this->assertSame(Evidence::KIND_EXTERNAL_ABSTRACT, $a['source_kind']);
        $this->assertSame(sha1($a['text']), $a['chunk_checksum']);

        $d = $items["INT:DOC-{$doc->id}"];
        $this->assertSame([Evidence::KIND_INTERNAL, "INT:DOC-{$doc->id}:C0", null, null], [$d['source_kind'], $d['citation_id'], $d['page_start'], $d['section']], 'halaman tidak diketahui: tidak dikarang');
        $this->assertStringContainsString('tidak memiliki nomor halaman', implode(' ', $this->bundle()->limitations()));
    }

    public function test_internal_chunk_page_comes_from_chunk_metadata(): void
    {
        $doc = $this->doc('Panduan pelaporan keuangan perusahaan', [['Pelaporan keuangan perusahaan wajib mengikuti standar akuntansi.', 12]]);

        $i = $this->bundle()->items()[0];

        $this->assertSame(["INT:DOC-{$doc->id}:C0:P12", 12, 12], [$i['citation_id'], $i['page_start'], $i['page_end']]);
        $this->assertSame('pustaka', $i['provider']);
    }

    public function test_full_text_section_null_is_kept_null_and_pages_carry_the_citation(): void
    {
        $p = $this->ext('Pelaporan keuangan perusahaan dan nilai pasar', 'Studi pelaporan keuangan perusahaan.');
        $this->index($p, [['Pelaporan keuangan perusahaan dibahas di halaman sembilan dengan label bagian tidak diketahui.', 9, null]]);

        $i = $this->bundle()->items()[0];
        $this->assertNull($i['section']);
        $this->assertStringEndsWith(':P9', $i['citation_id']);
    }

    // ---- relevansi vs kedalaman bukti, pemilihan, anggaran -----------------------------------------------------------------

    public function test_full_text_never_changes_source_order_and_depth_is_separate_from_relevance(): void
    {
        $top = $this->ext('Pelaporan keuangan perusahaan', 'Pelaporan keuangan perusahaan: studi tentang pelaporan keuangan perusahaan di Indonesia.');
        $low = $this->ext('Studi bank dan kredit', 'Analisis kredit bank yang menyinggung pelaporan keuangan perusahaan sekilas saja.');

        $order = fn () => array_column($this->bundle()->sources(), 'source_id');
        $before = $order();
        $this->assertSame("EXT:{$top->id}", $before[0]);

        // Sumber paling TIDAK relevan mendapat full text terindeks yang sangat cocok: urutan sumber tetap.
        $this->index($low, [['Pelaporan keuangan perusahaan pelaporan keuangan perusahaan pelaporan keuangan perusahaan.', 3, 'results']]);

        $this->assertSame($before, $order(), 'ketersediaan PDF tidak mengganti sumber yang lebih relevan');
        $s = collect($this->bundle()->sources())->keyBy('source_id');
        $this->assertSame(Evidence::FULL_TEXT, $s["EXT:{$low->id}"]['evidence_level']);
        $this->assertSame(Evidence::ABSTRACT, $s["EXT:{$top->id}"]['evidence_level']);
        $this->assertGreaterThan($s["EXT:{$low->id}"]['relevance_score'], $s["EXT:{$top->id}"]['relevance_score']);
    }

    public function test_indexed_source_without_a_relevant_chunk_falls_back_to_abstract_and_says_so(): void
    {
        $p = $this->ext('Pelaporan keuangan perusahaan', 'Abstrak: studi pelaporan keuangan perusahaan yang cukup panjang untuk dipakai sebagai bukti.');
        $this->index($p, [['Catatan kaki tentang metodologi sampling dan kuesioner tanpa topik utama.', 4, null]]);

        $b = $this->bundle();
        $src = $b->sources()[0];
        $this->assertSame(Evidence::ABSTRACT, $src['evidence_level']);
        $this->assertTrue($src['availability']['full_text_indexed']);
        $this->assertStringContainsString('tidak ada bagian yang relevan', $src['note']);
        $this->assertStringContainsString('tidak ada bagian relevan', implode(' ', $b->limitations()));
    }

    public function test_exact_query_phrase_and_title_context_rank_chunks_above_generic_matches_and_page_breaks_ties(): void
    {
        $p = $this->ext('Pelaporan keuangan perusahaan dan nilai pasar', 'Studi pelaporan keuangan perusahaan.');
        $this->index($p, [
            ['Perusahaan menyusun laporan. Keuangan dibahas terpisah. Hal pelaporan lain muncul di sini saja.', 2, null],
            ['Bab ini membahas pelaporan keuangan perusahaan secara langsung dengan frasa yang persis sama.', 8, null],
            ['Bab lain membahas pelaporan keuangan perusahaan secara langsung dengan frasa yang persis sama juga.', 5, null],
        ]);
        config(['research.evidence.max_per_source' => 2]);

        $cites = array_column($this->bundle()->items(), 'citation_id');

        // Dua chunk berfrasa persis menang; di antara keduanya skor setara -> halaman lebih awal lebih dulu (bukan ID).
        $this->assertSame(["EXT:{$p->id}:C2:P5", "EXT:{$p->id}:C1:P8"], $cites);
    }

    public function test_per_source_cap_and_diversity_across_sources(): void
    {
        $a = $this->ext('Pelaporan keuangan perusahaan A', 'Pelaporan keuangan perusahaan.');
        $b = $this->ext('Pelaporan keuangan perusahaan B', 'Pelaporan keuangan perusahaan.');
        $many = fn ($s) => array_map(fn ($i) => ["Pelaporan keuangan perusahaan {$s} bagian nomor {$i} membahas topik yang sama.", $i + 1, null], range(0, 9));
        $this->index($a, $many('A'));
        $this->index($b, $many('B'));

        $items = $this->bundle()->items();
        $per = array_count_values(array_column($items, 'source_id'));

        $this->assertSame(3, $per["EXT:{$a->id}"], 'maks 3 chunk per sumber');
        $this->assertSame(3, $per["EXT:{$b->id}"], 'sumber kedua tidak tersingkir oleh sumber pertama');
    }

    public function test_global_chunk_budget_is_round_robin_so_one_source_cannot_flood(): void
    {
        $pubs = [];
        foreach (['A', 'B', 'C'] as $s) {
            $p = $this->ext("Pelaporan keuangan perusahaan {$s}", 'Pelaporan keuangan perusahaan.');
            $this->index($p, array_map(fn ($i) => ["Pelaporan keuangan perusahaan {$s} bagian {$i} membahas topik yang sama.", $i + 1, null], range(0, 4)));
            $pubs[] = $p;
        }
        config(['research.evidence.max_chunks' => 4]);

        $b = $this->bundle();
        $per = array_count_values(array_column($b->items(), 'source_id'));

        $this->assertCount(4, $b->items());
        $this->assertSame([2, 1, 1], [$per["EXT:{$pubs[0]->id}"], $per["EXT:{$pubs[1]->id}"], $per["EXT:{$pubs[2]->id}"]], 'putaran pertama memberi satu chunk tiap sumber');
        $this->assertSame(5, $b->toArray()['budget']['dropped_relevant_chunks'], '3 sumber x 3 kandidat = 9, 4 terpilih');
        $this->assertStringContainsString('Bukti dibatasi anggaran', implode(' ', $b->limitations()));
    }

    public function test_character_budget_is_enforced(): void
    {
        $p = $this->ext('Pelaporan keuangan perusahaan', 'Pelaporan keuangan perusahaan.');
        $this->index($p, array_map(fn ($i) => [str_repeat("pelaporan keuangan perusahaan bagian {$i} ", 40), $i + 1, null], range(0, 2)));
        config(['research.evidence.max_chars' => 2500]);

        $b = $this->bundle();

        $this->assertLessThanOrEqual(2500, $b->toArray()['budget']['used_chars']);
        $this->assertLessThan(3, count($b->items()));
        $this->assertGreaterThan(0, count($b->items()));
    }

    public function test_publication_cap_and_duplicate_sources_by_doi(): void
    {
        foreach (range(1, 7) as $i) {
            $this->ext("Pelaporan keuangan perusahaan nomor {$i}", "Abstrak {$i} tentang pelaporan keuangan perusahaan yang cukup panjang.");
        }
        $this->assertCount(5, $this->bundle()->sources());

        // Sumber ganda (DOI sama) disaring; yang peringkatnya lebih baik dipertahankan dan duplikat dicatat.
        $disc = app(SourceDiscovery::class)->discover($this->cat->id, self::Q, []);
        $first = $disc['results'][0];
        $dup = array_merge($first, ['id' => 999, 'key' => 'ext:999']);
        $disc['results'] = array_merge([$first], [$dup], array_slice($disc['results'], 1));

        $b = app(EvidenceBundleBuilder::class)->build($this->cat->id, self::Q, [], $disc);
        $ids = array_column($b->sources(), 'source_id');

        $this->assertCount(5, $ids);
        $this->assertNotContains('EXT:999', $ids);
        $this->assertContains('EXT:999', $b->sources()[0]['also_available_as']);
    }

    // ---- isolasi ------------------------------------------------------------------------------------------------------------

    public function test_private_draft_and_other_category_documents_never_enter_the_bundle(): void
    {
        $other = Category::create(['name' => 'Management', 'slug' => 'mgmt', 'status' => 'active']);
        $this->doc('Dokumen privat pelaporan keuangan perusahaan', ['Pelaporan keuangan perusahaan RAHASIA PRIVAT.'], ['visibility' => 'private']);
        $this->doc('Dokumen draf pelaporan keuangan perusahaan', ['Pelaporan keuangan perusahaan RAHASIA DRAF.'], ['status' => 'draft']);
        $this->doc('Dokumen kategori lain pelaporan keuangan perusahaan', ['Pelaporan keuangan perusahaan RAHASIA KATEGORI LAIN.'], [], $other);
        $this->ext('Publikasi kategori lain pelaporan keuangan perusahaan', 'Abstrak kategori lain tentang pelaporan keuangan perusahaan.', [], $other);
        $ok = $this->doc('Dokumen publik pelaporan keuangan perusahaan', ['Pelaporan keuangan perusahaan teks publik.']);

        $b = $this->bundle();

        $this->assertSame(["INT:DOC-{$ok->id}"], array_column($b->sources(), 'source_id'));
        $this->assertStringNotContainsString('RAHASIA', json_encode($b));
        $otherIds = array_column($this->bundle($other)->sources(), 'source_id');
        $this->assertNotContains("INT:DOC-{$ok->id}", $otherIds, 'bundle kategori lain tidak memuat dokumen kategori ini');
        $this->assertCount(2, $otherIds);
    }

    public function test_empty_and_invalid_inputs_produce_an_honest_empty_bundle(): void
    {
        $b = $this->bundle();
        $this->assertSame([], $b->sources());
        $this->assertFalse($b->hasEvidenceText());
        $this->assertSame('Tidak ada referensi relevan.', $b->summary()['text']);
        $this->assertStringContainsString('Tidak ada referensi relevan', $b->limitations()[0]);

        $bad = app(EvidenceBundleBuilder::class)->build(99999, 'pelaporan keuangan');
        $this->assertSame([], $bad->sources());
        $this->assertNull($bad->toArray()['category']);
    }

    // ---- keterbatasan -------------------------------------------------------------------------------------------------------

    public function test_limitations_are_generated_and_marked_as_must_preserve(): void
    {
        $full = $this->ext('Pelaporan keuangan perusahaan A', 'Pelaporan keuangan perusahaan.');
        $this->index($full, [['Pelaporan keuangan perusahaan dibahas lengkap di sini.', 4, null]]);
        $this->ext('Pelaporan keuangan perusahaan B', 'Abstrak pelaporan keuangan perusahaan yang cukup panjang untuk dipakai.');
        $this->ext('Pelaporan keuangan perusahaan C', 'Abstrak pelaporan keuangan perusahaan lain yang cukup panjang untuk dipakai.');
        $this->ext('Pelaporan keuangan perusahaan D', null);

        $b = $this->bundle();
        $text = implode("\n", $b->limitations());

        $this->assertStringContainsString('2 dari 4 sumber hanya tersedia dalam bentuk abstrak', $text);
        $this->assertStringContainsString('1 dari 4 sumber hanya memiliki metadata dan tidak digunakan untuk klaim substansial', $text);
        $this->assertStringContainsString('Teks lengkap tersedia untuk 1 sumber (1 potongan teks)', $text);
        $this->assertTrue($b->toArray()['limitations_must_be_preserved']);
        $this->assertSame('4 referensi ditemukan; bukti: 1 teks lengkap, 2 abstrak, 1 hanya metadata.', $b->summary()['text']);
    }

    public function test_no_full_text_means_an_explicit_limitation(): void
    {
        $this->ext('Pelaporan keuangan perusahaan A', 'Abstrak pelaporan keuangan perusahaan yang cukup panjang untuk dipakai.');

        $this->assertStringContainsString('Tidak ada teks lengkap yang dipakai sebagai bukti', implode(' ', $this->bundle()->limitations()));
    }

    // ---- sitasi -------------------------------------------------------------------------------------------------------------

    private function fixtureBundle(): array
    {
        $full = $this->ext('Pelaporan keuangan perusahaan dan nilai pasar', 'Studi pelaporan keuangan perusahaan.');
        $this->index($full, [['Hasil regresi menunjukkan pelaporan keuangan perusahaan berpengaruh positif terhadap nilai pasar saham.', 7, 'results']]);
        $abs = $this->ext('Kualitas pelaporan keuangan perusahaan', 'Penelitian ini menguji kualitas pelaporan keuangan perusahaan manufaktur dengan sampel tiga tahun.');
        $meta = $this->ext('Pelaporan keuangan perusahaan: tinjauan', null);

        return [$this->bundle(), $full, $abs, $meta];
    }

    public function test_citation_ids_are_stable_parseable_and_mapped(): void
    {
        [$b, $full, $abs, $meta] = $this->fixtureBundle();

        foreach ([$full, $abs, $meta] as $p) {
            $this->assertSame('source', $b->citation("EXT:{$p->id}")['type']);
        }
        $this->assertSame('evidence', $b->citation("EXT:{$full->id}:C0:P7")['type']);
        $this->assertSame('evidence', $b->citation("EXT:{$abs->id}:ABS")['type']);
        $this->assertNull($b->citation("EXT:{$meta->id}:ABS"), 'metadata saja tidak punya identifier evidence');

        $this->assertSame(['kind' => 'external', 'id' => 929, 'type' => 'chunk', 'chunk' => 12, 'page_start' => 8, 'page_end' => 9], CitationId::parse('EXT:929:C12:P8-9'));
        $this->assertSame('chunk', CitationId::parse('INT:DOC-3:C5')['type']);
        $this->assertSame('abstract', CitationId::parse('EXT:929:ABS')['type']);
        foreach (['', 'EXT:', 'EXT:abc', 'DOC-3', 'EXT:929:X1', 'EXT:929:C1:P', "EXT:929'; DROP", 'ext:929'] as $bad) {
            $this->assertNull(CitationId::parse($bad), $bad);
        }
        $this->assertSame(json_encode($b->toArray()), json_encode($b), 'bundle dapat diserialisasi apa adanya untuk diteruskan ke AI');
    }

    #[DataProvider('invalidCitations')]
    public function test_citation_validator_rejects_fabricated_and_unsupported_citations(string $case, array $citation, string $code): void
    {
        [$b, $full, $abs, $meta] = $this->fixtureBundle();
        $replace = fn ($v) => is_string($v) ? str_replace(['{full}', '{abs}', '{meta}'], [$full->id, $abs->id, $meta->id], $v) : $v;
        $citation = array_map($replace, $citation);

        $r = app(EvidenceCitationValidator::class)->validate($b, $citation);

        $this->assertFalse($r['valid'], $case);
        $this->assertSame($code, $r['code'], $case);
    }

    public static function invalidCitations(): array
    {
        $f = Evidence::CLAIM_FULLTEXT;

        return [
            'ID format palsu' => ['x', ['id' => 'E01', 'claim_type' => $f], 'malformed_id'],
            'publikasi tidak ada' => ['x', ['id' => 'EXT:987654:C0:P7', 'claim_type' => $f], 'unknown_source'],
            'dokumen tidak ada' => ['x', ['id' => 'INT:DOC-987654:C0', 'claim_type' => $f], 'unknown_source'],
            'evidence palsu (chunk tak dikirim)' => ['x', ['id' => 'EXT:{full}:C5', 'claim_type' => $f], 'unknown_evidence'],
            'halaman palsu di ID' => ['x', ['id' => 'EXT:{full}:C0:P99', 'claim_type' => $f], 'page_mismatch'],
            'halaman palsu terpisah' => ['x', ['id' => 'EXT:{full}:C0:P7', 'claim_type' => $f, 'page' => 12], 'page_mismatch'],
            'kutipan tidak ada di teks' => ['x', ['id' => 'EXT:{full}:C0:P7', 'claim_type' => $f, 'quote' => 'ditemukan bukti kecurangan massal'], 'quote_not_found'],
            'jenis klaim palsu' => ['x', ['id' => 'EXT:{full}:C0:P7', 'claim_type' => 'miracle'], 'unknown_claim_type'],
            'klaim isi dari abstrak' => ['x', ['id' => 'EXT:{abs}:ABS', 'claim_type' => $f], 'claim_not_supported'],
            'klaim isi dari ID sumber' => ['x', ['id' => 'EXT:{full}', 'claim_type' => $f], 'claim_not_supported'],
            'klaim abstrak dari ID sumber' => ['x', ['id' => 'EXT:{abs}', 'claim_type' => Evidence::CLAIM_ABSTRACT], 'claim_not_supported'],
            'metadata dipakai sebagai bukti hasil' => ['x', ['id' => 'EXT:{meta}', 'claim_type' => $f], 'metadata_only'],
            'metadata untuk klaim abstrak' => ['x', ['id' => 'EXT:{meta}', 'claim_type' => Evidence::CLAIM_ABSTRACT], 'metadata_only'],
            'halaman diklaim pada abstrak' => ['x', ['id' => 'EXT:{abs}:ABS', 'claim_type' => Evidence::CLAIM_ABSTRACT, 'page' => 3], 'page_mismatch'],
        ];
    }

    public function test_citation_validator_accepts_supported_claims_by_level(): void
    {
        [$b, $full, $abs, $meta] = $this->fixtureBundle();
        $v = app(EvidenceCitationValidator::class);

        $ok = [
            ['id' => "EXT:{$full->id}:C0:P7", 'claim_type' => Evidence::CLAIM_FULLTEXT, 'page' => 7, 'quote' => 'PELAPORAN keuangan  perusahaan berpengaruh positif'],
            ['id' => "EXT:{$abs->id}:ABS", 'claim_type' => Evidence::CLAIM_ABSTRACT, 'quote' => 'menguji kualitas pelaporan keuangan'],
            ['id' => "EXT:{$abs->id}:ABS", 'claim_type' => Evidence::CLAIM_BIBLIOGRAPHIC],
            ['id' => "EXT:{$meta->id}", 'claim_type' => Evidence::CLAIM_BIBLIOGRAPHIC],
            ['id' => "EXT:{$full->id}", 'claim_type' => Evidence::CLAIM_BIBLIOGRAPHIC],
        ];
        $res = $v->validateMany($b, $ok);

        $this->assertSame([true, true, true, true, true], array_column($res, 'valid'));
        $this->assertSame([Evidence::FULLTEXT_SUPPORTED, Evidence::ABSTRACT_SUPPORTED, Evidence::ABSTRACT_SUPPORTED, Evidence::BIBLIOGRAPHIC_ONLY], array_slice(array_column($res, 'support'), 0, 4));
    }

    // ---- keselamatan --------------------------------------------------------------------------------------------------------

    public function test_building_a_bundle_calls_no_ai_downloads_nothing_and_writes_nothing(): void
    {
        [$b] = $this->fixtureBundle();
        $tables = ['ai_runs', 'ai_run_retrievals', 'ai_run_citations', 'jobs', 'external_publications', 'external_fulltexts', 'external_fulltext_chunks', 'documents', 'document_chunks'];
        $before = array_map(fn ($t) => DB::table($t)->count(), $tables);
        $checksums = ExternalFulltextChunk::query()->orderBy('id')->pluck('content_sha')->all();

        $this->bundle();

        $this->assertSame($before, array_map(fn ($t) => DB::table($t)->count(), $tables));
        $this->assertSame($checksums, ExternalFulltextChunk::query()->orderBy('id')->pluck('content_sha')->all());
        $this->assertSame(0, AiRun::count());
        Http::assertNothingSent();
        $this->assertSame([], \Illuminate\Support\Facades\Storage::disk('external_fulltext')->allFiles());
        $this->assertTrue($b->hasEvidenceText());
    }

    // ---- tampilan Workspace ----------------------------------------------------------------------------------------------------

    private function page(array $params = [])
    {
        $u = User::factory()->create(['phone' => '081234567890', 'institution' => 'Lab', 'address' => 'Jakarta', 'date_of_birth' => '1995-01-01', 'status' => 'active', 'email_verified_at' => now()]);
        Cache::flush();

        return $this->actingAs($u)->get(route('research.references', ['category_id' => $this->cat->id, 'q' => self::Q] + $params));
    }

    public function test_workspace_shows_available_evidence_with_pages_citations_and_limitations(): void
    {
        $full = $this->ext('Pelaporan keuangan perusahaan dan nilai pasar', 'Studi pelaporan keuangan perusahaan.');
        $this->index($full, [['Hasil regresi menunjukkan pelaporan keuangan perusahaan berpengaruh positif terhadap nilai pasar saham.', 7, 'results']]);
        $this->ext('Kualitas pelaporan keuangan perusahaan', 'Penelitian ini menguji kualitas pelaporan keuangan perusahaan manufaktur dengan sampel tiga tahun.');
        $this->ext('Pelaporan keuangan perusahaan: tinjauan', null);

        $html = $this->page()->assertOk()->getContent();

        $this->assertStringContainsString('Bukti yang tersedia', $html);
        $this->assertStringContainsString('3 referensi ditemukan; bukti: 1 teks lengkap, 1 abstrak, 1 hanya metadata.', $html);
        $this->assertStringContainsString("EXT:{$full->id}:C0:P7", $html);
        $this->assertStringContainsString('hlm. 7', $html);
        $this->assertStringContainsString('Keterbatasan bukti', $html);
        $this->assertStringContainsString('hanya memiliki metadata dan tidak digunakan untuk klaim substansial', $html);
        $this->assertStringContainsString('Lihat bukti (1)', $html);
        $this->assertStringContainsString('Tidak ada teks bukti dari sumber ini.', $html, 'sumber metadata saja tidak punya cuplikan');
        $this->assertStringNotContainsString('repo.example.org', $html);
        $this->assertStringNotContainsString('external-fulltext', $html);
    }

    public function test_workspace_empty_evidence_state_when_every_source_is_metadata_only(): void
    {
        $this->ext('Pelaporan keuangan perusahaan satu', null);
        $this->ext('Pelaporan keuangan perusahaan dua', null);

        $html = $this->page()->assertOk()->getContent();

        $this->assertStringContainsString('Belum ada teks bukti yang dapat dikutip', $html);
        $this->assertStringContainsString('2 hanya metadata', $html);
    }

    public function test_workspace_without_results_has_no_evidence_section(): void
    {
        $this->page(['q' => 'zzzxqv'])->assertOk()->assertDontSee('Bukti yang tersedia');
    }
}
