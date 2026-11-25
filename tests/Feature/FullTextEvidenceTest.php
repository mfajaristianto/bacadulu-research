<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\ExternalFulltext;
use App\Models\ExternalFulltextChunk;
use App\Models\ExternalPublicationCategory;
use App\Models\ExternalPublicationSource;
use App\Models\User;
use App\Services\Discovery\SourceDiscovery;
use App\Services\ExternalSources\ExternalSourceSettings;
use App\Services\FullText\ExternalEvidence;
use App\Services\FullText\FullTextChunker;
use App\Services\FullText\FullTextException;
use App\Services\FullText\FullTextExtractor;
use App\Services\FullText\FullTextState as S;
use App\Services\FullText\SectionSegmenter;
use App\Services\Research\KeywordRetriever;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\FullTextFixtures;
use Tests\Support\TinyPdf;
use Tests\TestCase;

/** Chunking, bagian, XML aman, retrieval tanpa bias ID, paket bukti, dan bukti bahwa peringkat Discovery tidak berubah. */
class FullTextEvidenceTest extends TestCase
{
    use FullTextFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFullText();
    }

    // ---- bagian -----------------------------------------------------------------------------------------------------------

    #[DataProvider('headings')]
    public function test_only_standalone_standard_headings_are_recognised(string $line, ?string $section): void
    {
        $this->assertSame($section, SectionSegmenter::canonical($line));
    }

    public static function headings(): array
    {
        return [
            ['Abstract', 'abstract'], ['ABSTRAK', 'abstract'], ['1. Introduction', 'introduction'], ['I. INTRODUCTION', 'introduction'], ['2.1 Literature Review', 'literature_review'],
            ['Tinjauan Pustaka', 'literature_review'], ['3. Research Methods', 'methodology'], ['Metode Penelitian', 'methodology'], ['4. Results', 'results'],
            ['Hasil dan Pembahasan', 'results_discussion'], ['5. Discussion', 'discussion'], ['6. Conclusion:', 'conclusion'], ['Kesimpulan dan Saran', 'conclusion'],
            ['References', 'references'], ['DAFTAR PUSTAKA', 'references'],
            // Bukan heading: tidak ditebak.
            ['Introduction to Financial Reporting Standards', null], ['Abstract: This paper studies', null], ['The results show that firms', null],
            ['Table of Contents ........ 12', null], ['Appendix A', null], ['Methods of payment in 2020 were varied and numerous across all regions of the country', null], ['', null],
        ];
    }

    public function test_references_after_the_heading_are_flagged_and_pages_are_kept_even_when_a_page_is_blank(): void
    {
        $pages = ["Introduction\nTeks pengantar artikel.", '', "Conclusion\nKesimpulan akhir artikel ini.\nReferences\nSmith 2020 Journal."];
        $blocks = SectionSegmenter::fromPages($pages);

        $this->assertSame([[1, 'introduction', false], [3, 'conclusion', false], [3, 'references', true]], array_map(fn ($b) => [$b['page'], $b['section'], $b['refs']], $blocks));
    }

    public function test_unrecognised_numbered_heading_ends_the_previous_section_instead_of_inheriting_its_label(): void
    {
        $blocks = SectionSegmenter::fromPages([
            "1 Introduction\nTeks pengantar.\n2 Institutional background\nLatar belakang kelembagaan yang berbeda.\n2.1. Data and sample selection\nSampel diambil dari laporan.\nConclusion\nHal-hal yang disimpulkan penulis.",
        ]);
        $this->assertSame(['introduction', null, null, 'conclusion'], array_column($blocks, 'section'));

        // Baris tabel, kalimat bertitik, angka, dan entri referensi BUKAN heading.
        $keep = SectionSegmenter::fromPages(["Results\n13   Table 1 (continued)      Panel C\n5 percent of firms were audited.\n2020 Annual report totals\nHasil lanjutan.\nReferences\n3 Smith J Journal of Accounting"]);
        $this->assertSame(['results', 'references'], array_column($keep, 'section'));
        $this->assertTrue($keep[1]['refs']);
    }

    // ---- chunker ----------------------------------------------------------------------------------------------------------

    private function words(int $n, string $w = 'kata'): string
    {
        return implode(' ', array_fill(0, $n, $w));
    }

    public function test_chunks_do_not_cross_section_or_reference_boundaries_and_keep_page_range(): void
    {
        config(['external-sources.fulltext.extract.chunk_words' => 100, 'external-sources.fulltext.extract.chunk_overlap_words' => 20]);
        $chunks = app(FullTextChunker::class)->chunk([
            ['page' => 1, 'section' => 'methodology', 'refs' => false, 'text' => $this->words(80, 'metode')],
            ['page' => 2, 'section' => 'methodology', 'refs' => false, 'text' => $this->words(80, 'sampel')],
            ['page' => 3, 'section' => 'references', 'refs' => true, 'text' => $this->words(60, 'smith')],
        ]);

        $this->assertSame(['methodology', 'methodology', 'references'], array_column($chunks, 'section'));
        $this->assertSame([false, false, true], array_column($chunks, 'is_references'));
        $this->assertSame([1, 2], array_slice(array_column($chunks, 'page_start'), 0, 2));
        $this->assertSame([2, 2], [$chunks[0]['page_end'], $chunks[1]['page_end']], 'jendela pertama melintasi halaman 1-2');
        $this->assertStringNotContainsString('smith', $chunks[0]['content'].$chunks[1]['content']);
        $this->assertSame(range(0, 2), array_column($chunks, 'index'));
        $this->assertSame(sha1($chunks[2]['content']), $chunks[2]['content_sha']);
    }

    public function test_tiny_fragments_are_dropped_and_chunk_cap_fails_loudly(): void
    {
        $chunks = app(FullTextChunker::class)->chunk([['page' => 1, 'section' => null, 'refs' => false, 'text' => '12'], ['page' => 1, 'section' => 'results', 'refs' => false, 'text' => $this->words(30)]]);
        $this->assertCount(1, $chunks);
        $this->assertSame('results', $chunks[0]['section']);

        config(['external-sources.fulltext.extract.max_chunks' => 2, 'external-sources.fulltext.extract.chunk_words' => 50, 'external-sources.fulltext.extract.chunk_overlap_words' => 5]);
        $this->expectException(FullTextException::class);
        app(FullTextChunker::class)->chunk([['page' => 1, 'section' => null, 'refs' => false, 'text' => $this->words(500)]]);
    }

    // ---- XML --------------------------------------------------------------------------------------------------------------

    private function xmlFile(string $xml): string
    {
        $dir = storage_path('framework/testing/fulltext-xml');
        is_dir($dir) || mkdir($dir, 0775, true);
        $path = $dir.DIRECTORY_SEPARATOR.'x'.Str::random(5).'.xml';
        file_put_contents($path, $xml);

        return $path;
    }

    public function test_tei_extraction_keeps_sections_and_flags_bibliography(): void
    {
        $xml = '<?xml version="1.0"?><TEI xmlns="http://www.tei-c.org/ns/1.0"><teiHeader><profileDesc><abstract><p>Abstrak artikel singkat.</p></profileDesc></teiHeader>'
            .'<text><body><div><head>1. Introduction</head><p>Paragraf pengantar.</p></div><div><head>Temuan Aneh</head><p>Isi tanpa bagian baku.</p></div></body>'
            .'<back><listBibl><biblStruct>Smith 2020.</biblStruct></listBibl></back></text></TEI>';
        $xml = str_replace('</profileDesc>', '</abstract></profileDesc>', $xml);
        $r = app(FullTextExtractor::class)->extract($this->xmlFile($xml), 'xml');

        $this->assertSame('xml-tei', $r['method']);
        $by = collect($r['blocks'])->keyBy(fn ($b) => $b['section'] ?? 'null');
        $this->assertSame(['abstract', 'introduction', 'null', 'references'], $by->keys()->all());
        $this->assertTrue($by['references']['refs']);
        $this->assertNull($by['null']['page'], 'XML tidak punya nomor halaman: tidak dikarang');
    }

    public function test_xml_with_doctype_or_entities_is_refused_for_xxe_safety(): void
    {
        foreach ([
            '<?xml version="1.0"?><!DOCTYPE TEI SYSTEM "file:///etc/passwd"><TEI><text><body><div><p>x</p></div></body></text></TEI>',
            '<?xml version="1.0"?><!DOCTYPE a [<!ENTITY x SYSTEM "file:///etc/passwd">]><TEI><text><body><div><p>&x;</p></div></body></text></TEI>',
        ] as $xml) {
            try {
                app(FullTextExtractor::class)->extract($this->xmlFile($xml), 'xml');
                $this->fail('XML berbahaya harus ditolak');
            } catch (FullTextException $e) {
                $this->assertSame('xml_unsafe', $e->errorCode);
            }
        }

        $this->expectExceptionObject(new FullTextException('xml_unknown', 'Format XML bukan TEI atau JATS.'));
        app(FullTextExtractor::class)->extract($this->xmlFile('<?xml version="1.0"?><html><body>hai</body></html>'), 'xml');
    }

    // ---- KeywordRetriever (audit batas 400) ---------------------------------------------------------------------------------

    private function library(int $chunks, int $bestIndex): Document
    {
        $cat = Category::firstOrCreate(['slug' => 'akt'], ['name' => 'Akuntansi', 'status' => 'active']);
        $d = Document::create(['uuid' => (string) Str::uuid(), 'title' => 'Dok', 'category_id' => $cat->id, 'document_type' => 'pdf', 'status' => 'published', 'visibility' => 'public', 'uploaded_by' => User::factory()->create()->id]);
        $rows = [];
        for ($i = 0; $i < $chunks; $i++) {
            $rows[] = ['document_id' => $d->id, 'chunk_index' => $i, 'metadata_json' => '[]', 'created_at' => now(), 'updated_at' => now(),
                'content' => $i === $bestIndex
                    ? 'Pelaporan keuangan berkualitas meningkatkan nilai perusahaan; pelaporan keuangan yang transparan menurunkan biaya modal perusahaan.'
                    : 'Pelaporan rutin dibahas sekilas tanpa membahas topik utama nomor '.$i.'.'];
        }
        foreach (array_chunk($rows, 200) as $b) {
            DB::table('document_chunks')->insert($b);
        }

        return $d;
    }

    public function test_keyword_retriever_finds_the_best_chunk_even_when_its_id_is_beyond_the_old_400_limit(): void
    {
        $d = $this->library(1500, 1450);
        $best = DocumentChunk::where('document_id', $d->id)->where('chunk_index', 1450)->value('id');
        $this->assertGreaterThan(400, DocumentChunk::where('document_id', $d->id)->where('id', '<', $best)->count());

        $r = app(KeywordRetriever::class);
        $hits = $r->retrieve([$d->id], 'pelaporan keuangan nilai perusahaan');

        $this->assertSame($best, $hits->first()['chunk']->id, 'chunk paling relevan (id besar) tetap terpilih');
        $this->assertFalse($r->wasScanLimited());
    }

    public function test_keyword_retriever_reports_when_the_safety_scan_cap_is_reached(): void
    {
        $d = $this->library(1500, 1450);
        config(['research.retrieval.scan_limit' => 1000]);
        $r = app(KeywordRetriever::class);
        $r->retrieve([$d->id], 'pelaporan keuangan');

        $this->assertTrue($r->wasScanLimited(), 'pagar pengaman yang tercapai dilaporkan, bukan diam-diam');
    }

    public function test_keyword_retriever_result_is_independent_of_chunk_order(): void
    {
        $a = $this->library(600, 5);
        $b = $this->library(600, 590);
        $r = app(KeywordRetriever::class);

        $ra = $r->retrieve([$a->id], 'pelaporan keuangan nilai perusahaan')->first();
        $rb = $r->retrieve([$b->id], 'pelaporan keuangan nilai perusahaan')->first();
        $this->assertSame([5, 590], [$ra['chunk']->chunk_index, $rb['chunk']->chunk_index]);
        $this->assertEquals($ra['score'], $rb['score']);
    }

    // ---- bukti eksternal --------------------------------------------------------------------------------------------------

    private function indexed(array $pub = [], array $chunks = []): ExternalFulltext
    {
        $ft = $this->loc($this->pub($pub), ['state' => S::INDEXED, 'sha256' => hash('sha256', Str::random(5)), 'chunk_count' => count($chunks), 'attribution' => 'A. Penulis (2022). "Judul". Lisensi: CC-BY.']);
        foreach ($chunks as $i => $c) {
            ExternalFulltextChunk::create(['fulltext_id' => $ft->id, 'publication_id' => $ft->publication_id, 'chunk_index' => $i, 'content_sha' => sha1($c['text']), 'source_checksum' => $ft->sha256, 'word_count' => 10,
                'section' => $c['section'] ?? null, 'page_start' => $c['page'] ?? null, 'page_end' => $c['page'] ?? null, 'is_references' => $c['refs'] ?? false, 'content' => $c['text']]);
        }

        return $ft;
    }

    public function test_external_evidence_retrieval_is_unbiased_excludes_references_and_unindexed_files(): void
    {
        $filler = array_map(fn ($i) => ['text' => "Catatan umum tentang laporan tahun {$i} tanpa topik inti.", 'section' => 'results', 'page' => 2], range(0, 700));
        $best = ['text' => 'Pelaporan keuangan berkualitas meningkatkan nilai perusahaan secara signifikan menurut hasil regresi.', 'section' => 'results', 'page' => 7];
        $ref = ['text' => 'Smith J. Pelaporan keuangan dan nilai perusahaan. Journal of Accounting. Pelaporan keuangan nilai perusahaan.', 'section' => 'references', 'refs' => true, 'page' => 9];
        $ft = $this->indexed([], [...$filler, $best, $ref]);
        $notIndexed = $this->loc($this->pub(['title' => 'Belum diindeks']), ['state' => S::STORED]);
        ExternalFulltextChunk::create(['fulltext_id' => $notIndexed->id, 'publication_id' => $notIndexed->publication_id, 'chunk_index' => 0, 'content_sha' => 'x', 'source_checksum' => 'y', 'content' => 'Pelaporan keuangan nilai perusahaan sangat penting sekali.']);

        $hits = app(ExternalEvidence::class)->retrieve([$ft->publication_id, $notIndexed->publication_id], 'pelaporan keuangan nilai perusahaan');

        $this->assertSame($best['text'], $hits->first()['chunk']->content, 'chunk ber-ID besar tetap yang terbaik');
        $this->assertTrue($hits->every(fn ($h) => ! $h['chunk']->is_references), 'daftar pustaka tidak jadi bukti');
        $this->assertTrue($hits->every(fn ($h) => $h['chunk']->publication_id === $ft->publication_id), 'berkas yang belum terindeks tidak dipakai');
    }

    public function test_evidence_bundle_limits_claims_by_availability_level(): void
    {
        $svc = app(ExternalEvidence::class);

        $full = $this->indexed(['doi' => '10.5555/e.1'], [['text' => 'Hasil regresi menunjukkan pelaporan keuangan berpengaruh positif terhadap nilai perusahaan.', 'section' => 'results', 'page' => 4]]);
        $b = $svc->bundle($full->publication, 'pelaporan keuangan nilai perusahaan');
        $this->assertSame('full_text', $b['level']);
        $this->assertSame('doi:10.5555/e.1', $b['citation']['identifier']);
        $this->assertSame('hlm. 4, results', $b['chunks'][0]['locator']);
        $this->assertSame([], $b['supported_claims'], 'klaim diisi sintesis AI nanti; belum ada klaim karangan');
        $this->assertTrue(ExternalEvidence::supportsCitation($b, [$b['chunks'][0]['chunk_id']]));
        $this->assertFalse(ExternalEvidence::supportsCitation($b, [999999]), 'kutipan ke chunk yang tidak ada di paket ditolak');
        $this->assertFalse(ExternalEvidence::supportsCitation($b, []));

        $abs = $svc->bundle($this->pub(['title' => 'Hanya abstrak']), 'pelaporan');
        $this->assertSame('abstract', $abs['level']);
        $this->assertSame([], $abs['chunks']);
        $this->assertStringContainsString('dibatasi pada isi abstrak', $abs['claim_scope']);
        $this->assertFalse(ExternalEvidence::supportsCitation($abs, [1]));

        $meta = $svc->bundle($this->pub(['title' => 'Tanpa abstrak', 'abstract' => null]), 'pelaporan');
        $this->assertSame('metadata', $meta['level']);
        $this->assertNull($meta['abstract']);
        $this->assertStringContainsString('jangan membuat klaim tentang metode, hasil, atau kesimpulan', $meta['claim_scope']);
    }

    // ---- Source Discovery: peringkat tidak berubah ----------------------------------------------------------------------------

    private function discovery(): array
    {
        $cat = Category::firstOrCreate(['slug' => 'acc'], ['name' => 'Accounting & Finance', 'status' => 'active']);
        foreach (['crossref', 'doaj'] as $p) {
            app(ExternalSourceSettings::class)->save($p, ['enabled' => 1], null);
        }
        $ids = [];
        foreach ([['Pelaporan keuangan dan nilai perusahaan', 'Studi pelaporan keuangan.'], ['Audit pelaporan keuangan perusahaan', 'Audit dan pelaporan.'], ['Kinerja keuangan bank', 'Pelaporan bank.']] as $i => [$t, $a]) {
            $p = $this->pub(['title' => $t, 'abstract' => $a, 'doi' => "10.7777/rank.{$i}", 'fulltext_url' => null]);
            ExternalPublicationSource::create(['publication_id' => $p->id, 'provider' => 'doaj', 'external_id' => "r{$i}"]);
            ExternalPublicationCategory::create(['publication_id' => $p->id, 'category_id' => $cat->id, 'status' => 'suggested']);
            $ids[] = $p->id;
        }

        return [$cat, $ids];
    }

    public function test_full_text_availability_never_changes_relevance_score_or_order(): void
    {
        [$cat, $ids] = $this->discovery();
        $d = app(SourceDiscovery::class);
        $snapshot = fn () => array_map(fn ($r) => [$r['id'], $r['score'], $r['evidence'], $r['evidence_rank']], $d->discover($cat->id, 'pelaporan keuangan perusahaan', [])['results']);

        Cache::flush();
        $before = $snapshot();
        $this->assertNotEmpty($before);

        // Beri publikasi di urutan TERBAWAH teks lengkap terindeks.
        $last = end($before)[0];
        $this->indexedFor($last);
        Cache::flush();

        $this->assertSame($before, $snapshot(), 'skor, bukti, dan urutan tidak berubah karena PDF tersedia');
    }

    private function indexedFor(int $pubId): void
    {
        $ft = ExternalFulltext::create(['publication_id' => $pubId, 'provider' => 'openalex', 'url' => 'https://repo.example.org/'.$pubId.'.pdf', 'url_hash' => sha1((string) $pubId), 'kind' => 'pdf',
            'license_code' => 'cc-by', 'permission' => 'allow', 'state' => S::INDEXED, 'chunk_count' => 2, 'sha256' => hash('sha256', (string) $pubId)]);
        ExternalFulltextChunk::create(['fulltext_id' => $ft->id, 'publication_id' => $pubId, 'chunk_index' => 0, 'content_sha' => 'a', 'source_checksum' => 'b', 'content' => 'isi']);
    }

    public function test_discovery_page_separates_found_open_stored_and_indexed(): void
    {
        [$cat, $ids] = $this->discovery();
        $user = User::factory()->create(['phone' => '081234567890', 'institution' => 'Lab', 'address' => 'Jakarta', 'date_of_birth' => '1995-01-01', 'status' => 'active', 'email_verified_at' => now()]);
        $mk = fn (int $pid, string $state, string $kind = 'pdf', string $perm = 'allow') => ExternalFulltext::create(['publication_id' => $pid, 'provider' => 'openalex', 'url' => "https://repo.example.org/{$pid}.pdf", 'url_hash' => sha1("u{$pid}"), 'kind' => $kind, 'license_code' => 'cc-by', 'permission' => $perm, 'state' => $state, 'chunk_count' => 4]);
        $mk($ids[0], S::INDEXED);
        $mk($ids[1], S::REVIEW_REQUIRED, 'pdf', 'review');
        Cache::flush();

        $html = $this->actingAs($user)->get(route('research.references', ['category_id' => $cat->id, 'q' => 'pelaporan keuangan perusahaan', 'view' => 'all']))->assertOk()->getContent();

        $this->assertStringContainsString('Terindeks, siap jadi evidence', $html);
        $this->assertStringContainsString('Isi artikel sudah diindeks (4 potongan teks) dengan lisensi CC-BY', $html);
        $this->assertStringContainsString('Lisensi belum jelas; admin perlu meninjau', $html);
        $this->assertStringContainsString('Belum disimpan', $html);
        $this->assertStringNotContainsString('repo.example.org', $html, 'URL berkas tidak dibocorkan ke pengguna');

        $show = $this->actingAs($user)->get(route('research.references.show', ['kind' => 'ext', 'id' => $ids[2], 'category_id' => $cat->id, 'q' => 'pelaporan']))->assertOk()->getContent();
        $this->assertStringContainsString('Teks lengkap belum diperiksa', $show);
        $this->assertStringNotContainsString('Terindeks, siap jadi evidence', $show, 'tidak mengklaim indeks yang tidak ada');
    }
}
