<?php

namespace Tests\Feature;

use App\Services\Documents\PrintDocumentModel;
use App\Services\Documents\PrintDocxWriter;
use App\Services\Documents\PrintLayout;
use App\Services\Documents\PrintLayoutEngine;
use App\Services\Documents\PrintPdfWriter;
use App\Services\Documents\PrintPreview;
use Tests\TestCase;
use ZipArchive;

/*
 * Mesin Template & Format Cetak: satuan, validasi, ukuran halaman, serta berkas DOCX dan PDF yang benar-benar dihasilkan
 * (dibaca ulang dari byte, bukan hanya dari model). Tidak memakai database.
 */
class PrintFormatEngineTest extends TestCase
{
    private function layout(array $over = []): array
    {
        $n = PrintLayout::normalize(array_replace_recursive(PrintLayout::defaults(), $over));

        $this->assertSame([], $n['errors'], 'layout uji harus valid: '.json_encode($n['errors']));

        return $n['layout'];
    }

    private function model(array $layout, string $kind = 'chapter'): array
    {
        return PrintDocumentModel::build(PrintDocumentModel::sample($kind), $layout);
    }

    private function pdf(array $layout): string
    {
        $model = $this->model($layout);

        return PrintPdfWriter::render(PrintLayoutEngine::run($model, $layout), $model['title']);
    }

    /** @return array{0: string, 1: string, 2: ZipArchive} document.xml, styles.xml, zip */
    private function docx(array $layout): array
    {
        $path = tempnam(sys_get_temp_dir(), 'bdt');
        PrintDocxWriter::render($this->model($layout), $layout, $path);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true, 'DOCX harus berupa ZIP yang valid');
        $doc = (string) $zip->getFromName('word/document.xml');
        $styles = (string) $zip->getFromName('word/styles.xml');
        $zip->close();
        @unlink($path);

        return [$doc, $styles, $zip];
    }

    /** @return array{0: float, 1: float} lebar dan tinggi MediaBox halaman pertama (pt) */
    private function mediaBox(string $pdf): array
    {
        $this->assertMatchesRegularExpression('/\/MediaBox \[0 0 ([\d.]+) ([\d.]+)\]/', $pdf);
        preg_match('/\/MediaBox \[0 0 ([\d.]+) ([\d.]+)\]/', $pdf, $m);

        return [(float) $m[1], (float) $m[2]];
    }

    public function test_unit_conversion_is_accurate_and_round_trips(): void
    {
        $this->assertSame(25.4, PrintLayout::toMm(1, 'in'));
        $this->assertSame(30.0, PrintLayout::toMm(3, 'cm'));
        $this->assertSame(210.0, PrintLayout::toMm(210, 'mm'));
        $this->assertSame(1.0, PrintLayout::fromMm(25.4, 'in'));
        $this->assertSame(2.54, PrintLayout::fromMm(25.4, 'cm'));
        $this->assertEqualsWithDelta(72.0, PrintLayout::pt(25.4), 0.001);
        $this->assertSame(1440, PrintLayout::twips(25.4));
    }

    public function test_standard_page_sizes_are_distinct_and_exact(): void
    {
        $dims = fn (string $size) => PrintLayout::pageMm($this->layout(['page' => ['size' => $size]]));

        $this->assertSame([210.0, 297.0], $dims('a4'));
        $this->assertSame([148.0, 210.0], $dims('a5'));
        $this->assertSame([176.0, 250.0], $dims('b5_iso'));
        $this->assertSame([182.0, 257.0], $dims('b5_jis'));
        $this->assertNotSame($dims('b5_iso'), $dims('b5_jis'), 'B5 ISO dan B5 JIS tidak boleh dicampur');
        $this->assertSame([297.0, 210.0], PrintLayout::pageMm($this->layout(['page' => ['orientation' => 'landscape']])));
        $this->assertSame([155.0, 230.0], PrintLayout::pageMm($this->layout(['page' => ['size' => 'custom', 'width' => 155, 'height' => 230]])));
    }

    public function test_invalid_settings_are_rejected_with_field_errors(): void
    {
        $bad = fn (array $over) => PrintLayout::normalize(array_replace_recursive(PrintLayout::defaults(), $over))['errors'];

        $this->assertArrayHasKey('margins.top', $bad(['margins' => ['top' => 400]]), 'margin melebihi batas');
        $this->assertNotSame([], $bad(['margins' => ['left' => 80, 'right' => 80, 'gutter' => 40]]), 'area teks lebih sempit dari batas minimum');
        $this->assertNotSame([], $bad(['page' => ['size' => 'custom', 'width' => 10, 'height' => 10]]), 'ukuran kustom di luar batas');
        $this->assertNotSame([], $bad(['page' => ['size' => 'bukan-ukuran']]));
        $this->assertNotSame([], $bad(['type' => ['body_font' => 'Comic Papyrus']]));
        $this->assertNotSame([], $bad(['header_footer' => ['header' => ['left' => '{tidak_dikenal}']]]), 'token tidak dikenal');
        $this->assertNotSame([], $bad(['type' => ['body_size' => 2]]));
    }

    public function test_check_distinguishes_ready_review_and_blocked_without_claiming_press_ready(): void
    {
        $ok = PrintLayout::check($this->layout());
        $this->assertContains($ok['status'], [PrintLayout::READY, PrintLayout::REVIEW]);
        $this->assertStringNotContainsStringIgnoringCase('siap cetak profesional', $ok['label']);

        $cramped = PrintLayout::check($this->layout(['margins' => ['top' => 2, 'bottom' => 2, 'left' => 4, 'right' => 4]]));
        $this->assertNotSame(PrintLayout::READY, $cramped['status'], 'margin sangat tipis tidak boleh dinilai siap');
    }

    public function test_pdf_page_size_matches_the_chosen_format(): void
    {
        foreach (['a4' => [595.28, 841.89], 'a5' => [419.53, 595.28], 'b5_iso' => [498.90, 708.66], 'b5_jis' => [515.91, 728.50]] as $size => [$w, $h]) {
            $bytes = $this->pdf($this->layout(['page' => ['size' => $size]]));

            $this->assertStringStartsWith('%PDF-', $bytes);
            $this->assertStringContainsString('%%EOF', $bytes);
            [$mw, $mh] = $this->mediaBox($bytes);
            $this->assertEqualsWithDelta($w, $mw, 0.6, "lebar $size");
            $this->assertEqualsWithDelta($h, $mh, 0.6, "tinggi $size");
        }
    }

    public function test_pdf_pagination_is_real_and_pages_have_content(): void
    {
        $layout = $this->layout();
        $laid = PrintLayoutEngine::run($this->model($layout), $layout);

        $this->assertGreaterThanOrEqual(1, count($laid['pages']));

        foreach ($laid['pages'] as $page) {
            if ($page['kind'] !== 'blank') {
                $this->assertNotEmpty(array_filter($page['items'], fn ($i) => $i['k'] === 'text'), 'halaman isi tidak boleh kosong');
            }
        }

        $bytes = PrintPdfWriter::render($laid, 'Judul Uji');
        $this->assertSame(count($laid['pages']), preg_match_all('/\/Type \/Page /', $bytes), 'jumlah halaman PDF sama dengan hasil paginasi');
    }

    public function test_text_never_leaves_the_text_area_of_the_page(): void
    {
        foreach (['a4', 'a5', 'b5_iso'] as $size) {
            $layout = $this->layout(['page' => ['size' => $size], 'margins' => ['mirror' => true, 'inner' => 20, 'outer' => 18, 'gutter' => 6]]);
            $laid = PrintLayoutEngine::run($this->model($layout), $layout);

            foreach ($laid['pages'] as $i => $page) {
                $box = PrintLayout::box($layout, ($i + 1) % 2 === 1);
                $left = PrintLayout::pt($box['left']) - 0.5;
                $right = PrintLayout::pt($box['left'] + $box['width']) + 0.5;

                foreach ($page['items'] as $item) {
                    if ($item['k'] !== 'text') {
                        continue;
                    }

                    $w = PrintLayoutEngine::width($item['text'], $item['font'], $item['size']);
                    $this->assertGreaterThanOrEqual($left, $item['x'], "teks keluar dari margin kiri ($size, halaman ".($i + 1).')');
                    $this->assertLessThanOrEqual($laid['width'], $item['x'] + $w, "teks keluar dari tepi halaman ($size)");
                }
            }
        }
    }

    public function test_mirrored_margins_swap_sides_between_odd_and_even_pages_with_gutter(): void
    {
        $layout = $this->layout(['margins' => ['mirror' => true, 'inner' => 18, 'outer' => 25, 'gutter' => 5]]);
        $odd = PrintLayout::box($layout, true);
        $even = PrintLayout::box($layout, false);

        $this->assertEqualsWithDelta(23.0, $odd['left'], 0.01, 'halaman ganjil: dalam (18) + gutter (5) di kiri');
        $this->assertEqualsWithDelta(25.0, $even['left'], 0.01, 'halaman genap: luar di kiri');
        $this->assertEqualsWithDelta($odd['width'], $even['width'], 0.01, 'lebar teks sama');
    }

    public function test_docx_is_a_real_package_with_page_size_margins_and_named_heading_styles(): void
    {
        [$doc, $styles] = $this->docx($this->layout(['page' => ['size' => 'a5'], 'margins' => ['top' => 20, 'bottom' => 20, 'left' => 22, 'right' => 18, 'gutter' => 0]]));

        $this->assertStringContainsString('<w:pgSz w:w="'.PrintLayout::twips(148).'" w:h="'.PrintLayout::twips(210).'"', $doc);
        $this->assertStringContainsString('w:top="'.PrintLayout::twips(20).'"', $doc);
        $this->assertStringContainsString('w:left="'.PrintLayout::twips(22).'"', $doc);
        $this->assertStringContainsString('w:right="'.PrintLayout::twips(18).'"', $doc);
        $this->assertStringContainsString('w:pStyle w:val="Heading1"', $doc, 'judul memakai style bernama agar muncul di panel navigasi Word');
        $this->assertStringContainsString('w:styleId="Heading1"', $styles);
        $this->assertStringContainsString('w:styleId="Heading2"', $styles);
    }

    public function test_docx_uses_b5_jis_and_iso_sizes_distinctly(): void
    {
        [$iso] = $this->docx($this->layout(['page' => ['size' => 'b5_iso']]));
        [$jis] = $this->docx($this->layout(['page' => ['size' => 'b5_jis']]));

        $this->assertStringContainsString('w:w="'.PrintLayout::twips(176).'" w:h="'.PrintLayout::twips(250).'"', $iso);
        $this->assertStringContainsString('w:w="'.PrintLayout::twips(182).'" w:h="'.PrintLayout::twips(257).'"', $jis);
    }

    public function test_docx_book_layout_has_mirror_margins_gutter_odd_even_headers_toc_field_and_title_page(): void
    {
        $preset = PrintLayout::presets()['buku-b5']['layout'];
        $layout = PrintLayout::normalize($preset)['layout'];
        $path = tempnam(sys_get_temp_dir(), 'bdt');
        PrintDocxWriter::render($this->model($layout), $layout, $path);

        $zip = new ZipArchive;
        $zip->open($path);
        $doc = (string) $zip->getFromName('word/document.xml');
        $settings = (string) $zip->getFromName('word/settings.xml');
        $names = array_map(fn ($i) => $zip->getNameIndex($i), range(0, $zip->numFiles - 1));
        $zip->close();
        @unlink($path);

        $this->assertStringContainsString('<w:mirrorMargins/>', $settings);
        $this->assertStringContainsString('<w:evenAndOddHeaders/>', $settings);
        $this->assertStringContainsString('<w:updateFields w:val="true"/>', $settings, 'daftar isi adalah field Word yang diperbarui saat dibuka');
        $this->assertMatchesRegularExpression('/TOC \\\\o "1-2"/', $doc);
        $this->assertStringContainsString('w:gutter="'.PrintLayout::twips(8).'"', $doc);
        $this->assertStringContainsString('w:type w:val="oddPage"', $doc, 'bab mulai di halaman ganjil lewat section break');
        $this->assertStringContainsString('w:pgNumType w:fmt="lowerRoman"', $doc, 'halaman awal berangka Romawi');
        $this->assertNotEmpty(array_filter($names, fn ($n) => str_starts_with($n, 'word/header')), 'berkas header ada');
        $this->assertNotEmpty(array_filter($names, fn ($n) => str_starts_with($n, 'word/footer')), 'berkas footer ada');
    }

    public function test_docx_has_no_toc_field_when_disabled_and_no_forced_branding(): void
    {
        [$doc] = $this->docx($this->layout(['toc' => ['enabled' => false]]));

        $this->assertStringNotContainsString('TOC \\o', $doc);
        $this->assertStringNotContainsString('BacaDulu', $doc, 'tidak ada merek yang dipaksakan pada dokumen');
    }

    public function test_page_number_fields_exist_in_docx_footer(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'bdt');
        $layout = $this->layout(['page_numbers' => ['position' => 'footer_center']]);
        PrintDocxWriter::render($this->model($layout), $layout, $path);
        $zip = new ZipArchive;
        $zip->open($path);
        $footers = '';

        for ($i = 0; $i < $zip->numFiles; $i++) {
            if (str_starts_with($zip->getNameIndex($i), 'word/footer')) {
                $footers .= $zip->getFromIndex($i);
            }
        }

        $zip->close();
        @unlink($path);

        $this->assertMatchesRegularExpression('/PAGE/', $footers, 'nomor halaman memakai field PAGE');
    }

    public function test_preview_svg_escapes_document_text(): void
    {
        $layout = $this->layout();
        $content = PrintDocumentModel::sample('chapter');
        $content['sections'][0]['heading'] = '<script>alert(1)</script> & "kutip"';
        $content['title'] = '<img src=x onerror=alert(1)>';
        $laid = PrintLayoutEngine::run(PrintDocumentModel::build($content, $layout), $layout);
        $html = PrintPreview::render($laid, $layout, [])['html'];

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsStringIgnoringCase('&lt;script&gt;', $html, 'heading dirender sebagai teks (huruf kapital karena gaya heading), bukan markup');
    }

    public function test_numbering_has_a_single_source_manual_numbers_are_stripped(): void
    {
        $layout = $this->layout(['headings' => ['numbering' => 'bab']]);
        $content = PrintDocumentModel::sample('chapter');
        $content['sections'][0]['heading'] = 'BAB I PENDAHULUAN';
        $content['sections'][1]['heading'] = '2. Hasil dan Pembahasan';
        $text = collect(PrintLayoutEngine::run(PrintDocumentModel::build($content, $layout), $layout)['pages'])
            ->flatMap(fn ($p) => $p['items'])->where('k', 'text')->pluck('text')->implode("\n");

        $this->assertStringNotContainsString('BAB I BAB I', $text);
        $this->assertStringNotContainsString('BAB II 2.', $text);
        $this->assertStringContainsString('BAB I', $text);
    }

    public function test_a_corrupt_layout_with_no_text_area_is_rejected_instead_of_looping_forever(): void
    {
        $broken = PrintLayout::defaults();
        $broken['margins']['left'] = $broken['margins']['right'] = $broken['margins']['inner'] = $broken['margins']['outer'] = 645.16; // mis. nilai inci tersimpan sebagai mm

        $this->expectException(\InvalidArgumentException::class);
        PrintLayoutEngine::run(PrintDocumentModel::build(PrintDocumentModel::sample('chapter'), $broken), $broken);
    }

    public function test_presets_are_all_valid_and_cover_requested_formats(): void
    {
        $presets = PrintLayout::presets();

        $this->assertEqualsCanonicalizing(['riset-a4', 'artikel-a4', 'book-chapter-a5', 'buku-b5', 'kustom-penerbit'], array_keys($presets));

        foreach ($presets as $key => $p) {
            $n = PrintLayout::normalize($p['layout']);
            $this->assertSame([], $n['errors'], "preset $key valid");
            $this->assertNotSame(PrintLayout::BLOCKED, PrintLayout::check($n['layout'])['status'], "preset $key dapat diekspor");
            $this->assertStringStartsWith('%PDF-', $this->pdf($n['layout']));
            $this->docx($n['layout']);
        }
    }
}
