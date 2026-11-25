<?php

namespace App\Services\Documents;

use RuntimeException;
use ZipArchive;

/*
 * Penulis DOCX untuk layout cetak: paket OOXML nyata (bukan HTML berekstensi .docx) dengan ukuran/orientasi halaman, margin (termasuk cermin dan gutter),
 * style bernama (heading 1–3 bisa dikenali panel navigasi Word, Body Text, Caption, Reference, TOC 1–3), header/footer berisi field PAGE dan STYLEREF,
 * header ganjil-genap, bagian (section) untuk halaman judul, halaman awal berangka romawi, dan bab di halaman ganjil, tabel, serta daftar isi sebagai
 * field Word (TOC). Field daftar isi diisi Word saat dokumen dibuka/diperbarui; ini dijelaskan di Pemeriksaan Cetak, bukan disembunyikan.
 * Word menata halaman sendiri: pagination tidak dijamin identik dengan PDF/pratinjau.
 */
final class PrintDocxWriter
{
    private const NS = 'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"';

    private array $L;

    private array $doc;

    public static function render(array $doc, array $layout, string $absolutePath): void
    {
        (new self)->write($doc, $layout, $absolutePath);
    }

    private function write(array $doc, array $layout, string $path): void
    {
        $this->L = $layout;
        $this->doc = $doc;
        @unlink($path);
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('DOCX gagal dibuat (arsip tidak dapat dibuka).');
        }

        $parts = $this->headerFooterParts();
        $rels = '<Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'<Relationship Id="rIdSettings" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings" Target="settings.xml"/>';
        $types = '';

        foreach ($parts as $id => $p) {
            $zip->addFromString('word/'.$p['file'], $p['xml']);
            $rels .= '<Relationship Id="'.$id.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/'.$p['type'].'" Target="'.$p['file'].'"/>';
            $types .= '<Override PartName="/word/'.$p['file'].'" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.'.$p['type'].'+xml"/>';
        }

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            .'<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
            .'<Override PartName="/word/settings.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml"/>'
            .'<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            .$types.'</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/></Relationships>');
        $zip->addFromString('docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            .'<dc:title>'.$this->x($doc['title']).'</dc:title><dc:creator>BacaDulu Research</dc:creator><dcterms:created xsi:type="dcterms:W3CDTF">'.gmdate('Y-m-d\TH:i:s\Z').'</dcterms:created></cp:coreProperties>');
        $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$rels.'</Relationships>');
        $zip->addFromString('word/styles.xml', $this->styles());
        $zip->addFromString('word/settings.xml', $this->settings());
        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document '.self::NS.'><w:body>'.$this->body().'</w:body></w:document>');

        if (! $zip->close() || ! is_file($path) || filesize($path) <= 0) {
            @unlink($path);

            throw new RuntimeException('DOCX gagal dibuat.');
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Bagian dokumen
    |--------------------------------------------------------------------------
    */

    private function body(): string
    {
        $L = $this->L;
        $pn = $L['page_numbers'];
        $hasTitle = $L['front']['title_page'];
        $hasToc = $L['toc']['enabled'];
        $hasFront = $hasTitle || $hasToc;
        $out = '';
        $chapterStart = $L['headings']['chapter_start'];
        $sectioned = $this->skipHeaderApplies() || $chapterStart === 'odd_page';
        $sections = [];

        if ($hasTitle) {
            $xml = $this->p('', 'Normal', '<w:spacing w:before="'.(int) (PrintLayout::twips(PrintLayout::pageMm($L)[1]) * 0.28).'"/>');
            $xml .= $this->p($this->doc['title'], 'Title');

            foreach (array_filter([$this->doc['subtitle'], $this->doc['author']]) as $line) {
                $xml .= $this->p($line, 'Subtitle');
            }

            $sections[] = ['xml' => $xml, 'type' => 'nextPage', 'head' => 'none', 'foot' => 'none', 'fmt' => $pn['format'], 'start' => $pn['start'], 'titlePg' => false];
        }

        if ($hasToc) {
            $levels = $L['toc']['levels'];
            $xml = $this->p($L['toc']['title'], 'TOCHeading');
            $instr = ' TOC \\o "1-'.$levels.'" \\h \\z \\u'.($L['toc']['page_numbers'] ? '' : ' \\n').' ';
            $xml .= '<w:p><w:pPr><w:pStyle w:val="Normal"/></w:pPr><w:r><w:fldChar w:fldCharType="begin"/></w:r><w:r><w:instrText xml:space="preserve">'.$instr.'</w:instrText></w:r><w:r><w:fldChar w:fldCharType="separate"/></w:r>'
                .'<w:r><w:t xml:space="preserve">Daftar isi akan terisi setelah dokumen dibuka di Microsoft Word. Bila diminta, pilih Ya untuk memperbarui field, atau klik kanan di sini lalu pilih Update Field.</w:t></w:r><w:r><w:fldChar w:fldCharType="end"/></w:r></w:p>';
            $sections[] = ['xml' => $xml, 'type' => 'nextPage', 'head' => 'none', 'foot' => 'normal', 'fmt' => $pn['front_roman'] ? 'lowerRoman' : $this->fmt($pn['format']), 'start' => $pn['front_roman'] ? 1 : null, 'titlePg' => false];
        }

        $body = [];
        $current = ['xml' => '', 'chapter' => false];
        $firstChapter = true;

        foreach ($this->doc['blocks'] as $b) {
            $isChapter = $b['t'] === 'h' && $b['level'] === 1 && ! empty($b['chapter']);

            if ($sectioned && $isChapter && $current['xml'] !== '') {
                $body[] = $current;
                $current = ['xml' => '', 'chapter' => true];
            }

            if ($isChapter) {
                $current['chapter'] = true;
            }

            $break = ($isChapter && ! $sectioned && $chapterStart === 'new_page' && ! ($firstChapter && ! $hasFront)) || (! empty($b['refs']) && $L['refs']['new_page']);
            $firstChapter = $isChapter ? false : $firstChapter;
            $current['xml'] .= $this->block($b, $break);
        }

        $body[] = $current;

        foreach ($body as $i => $seg) {
            $first = $i === 0;
            $odd = $chapterStart === 'odd_page' && ($sectioned);
            $sections[] = [
                'xml' => $seg['xml'], 'type' => $odd ? 'oddPage' : 'nextPage', 'head' => 'normal', 'foot' => 'normal', 'fmt' => $this->fmt($pn['format']),
                'start' => $first && (! $hasFront || $pn['front_roman']) ? $pn['start'] : null,
                'titlePg' => $sectioned && $seg['chapter'] && $this->skipHeaderApplies(),
            ];
        }

        foreach ($sections as $i => $s) {
            $last = $i === count($sections) - 1;
            $sect = $this->sectPr($s, $i === 0);
            $out .= $s['xml'].($last ? $sect : '<w:p><w:pPr>'.$sect.'</w:pPr></w:p>');
        }

        return $out;
    }

    /** Header bab pertama hanya bisa disembunyikan di DOCX bila paritas halaman bab diketahui (bab di halaman ganjil) atau header tidak ganjil-genap. */
    private function skipHeaderApplies(): bool
    {
        $L = $this->L;
        $cs = $L['headings']['chapter_start'];

        if (! $L['header_footer']['skip_chapter_header'] || $cs === 'any') {
            return false;
        }

        return $cs === 'odd_page' || ! ($L['header_footer']['odd_even'] || str_ends_with($L['page_numbers']['position'], 'outside'));
    }

    private function fmt(string $format): string
    {
        return ['arabic' => 'decimal', 'lower_roman' => 'lowerRoman', 'upper_roman' => 'upperRoman'][$format] ?? 'decimal';
    }

    private function sectPr(array $s, bool $firstSection): string
    {
        $L = $this->L;
        [$wMm, $hMm] = PrintLayout::pageMm($L);
        $m = $L['margins'];
        $landscape = $L['page']['orientation'] === 'landscape';
        $refs = '';

        foreach (['h' => 'head', 'f' => 'foot'] as $k => $key) {
            $tag = $k === 'h' ? 'header' : 'footer';
            $variant = $s[$key];

            foreach (['default' => 'Odd', 'even' => 'Even'] as $type => $suffix) {
                $id = $variant === 'none' ? ($k === 'h' ? 'rIdHNone' : 'rIdFNone') : 'rId'.strtoupper($k).$suffix;
                $refs .= '<w:'.$tag.'Reference w:type="'.$type.'" r:id="'.$id.'"/>';
            }

            if ($s['titlePg']) {
                $id = $k === 'h' ? 'rIdHNone' : 'rIdFOdd';
                $refs .= '<w:'.$tag.'Reference w:type="first" r:id="'.$id.'"/>';
            }
        }

        $left = $m['mirror'] ? $m['inner'] : $m['left'];
        $right = $m['mirror'] ? $m['outer'] : $m['right'];

        return '<w:sectPr>'.$refs
            .'<w:type w:val="'.($firstSection ? 'nextPage' : $s['type']).'"/>'
            .'<w:pgSz w:w="'.PrintLayout::twips($wMm).'" w:h="'.PrintLayout::twips($hMm).'"'.($landscape ? ' w:orient="landscape"' : '').'/>'
            .'<w:pgMar w:top="'.PrintLayout::twips($m['top']).'" w:right="'.PrintLayout::twips($right).'" w:bottom="'.PrintLayout::twips($m['bottom']).'" w:left="'.PrintLayout::twips($left).'" w:header="'.PrintLayout::twips(max(5.0, $m['top'] * 0.45)).'" w:footer="'.PrintLayout::twips(max(5.0, $m['bottom'] * 0.45)).'" w:gutter="'.PrintLayout::twips($m['gutter']).'"/>'
            .'<w:pgNumType w:fmt="'.$s['fmt'].'"'.($s['start'] !== null ? ' w:start="'.$s['start'].'"' : '').'/>'
            .($s['titlePg'] ? '<w:titlePg/>' : '')
            .'</w:sectPr>';
    }

    /*
    |--------------------------------------------------------------------------
    | Blok konten
    |--------------------------------------------------------------------------
    */

    private function block(array $b, bool $pageBreak): string
    {
        return match ($b['t']) {
            'h' => $this->p($b['text'], 'Heading'.$b['level'], $pageBreak ? '<w:pageBreakBefore/>' : ''),
            'p' => $this->p($b['text'], 'BodyText'),
            'caption' => $this->p($b['text'], 'Caption'),
            'ref' => $this->p($b['text'], 'Reference'),
            'meta' => $this->p($b['text'], 'Meta'),
            'table' => $this->table($b['rows']),
            default => '',
        };
    }

    private function p(string $text, string $style, string $ppr = ''): string
    {
        $run = $text === '' ? '' : '<w:r><w:t xml:space="preserve">'.$this->x($text).'</w:t></w:r>';

        return '<w:p><w:pPr><w:pStyle w:val="'.$style.'"/>'.$ppr.'</w:pPr>'.$run.'</w:p>';
    }

    private function table(array $rows): string
    {
        $t = $this->L['tables'];
        $cols = max(array_map('count', $rows));
        $textW = PrintLayout::twips(PrintLayout::box($this->L, true)['width']);
        $cw = (int) floor($textW / $cols);
        $pad = (int) round($t['padding'] * 20);
        $line = '<w:%s w:val="single" w:sz="4" w:space="0" w:color="000000"/>';
        $none = '<w:%s w:val="nil"/>';
        $edge = fn (string $n, bool $on) => sprintf($on ? $line : $none, $n);
        $grid = $t['border'] === 'grid';
        $horizontal = $t['border'] === 'horizontal';
        $borders = '<w:tblBorders>'.$edge('top', $grid || $horizontal).$edge('left', $grid).$edge('bottom', $grid || $horizontal).$edge('right', $grid).$edge('insideH', $grid || $horizontal).$edge('insideV', $grid).'</w:tblBorders>';
        $xml = '<w:tbl><w:tblPr><w:tblW w:w="'.$textW.'" w:type="dxa"/><w:jc w:val="'.($t['align'] === 'center' ? 'center' : 'left').'"/>'.$borders.'<w:tblLayout w:type="fixed"/>'
            .'<w:tblCellMar><w:top w:w="'.$pad.'" w:type="dxa"/><w:left w:w="'.$pad.'" w:type="dxa"/><w:bottom w:w="'.$pad.'" w:type="dxa"/><w:right w:w="'.$pad.'" w:type="dxa"/></w:tblCellMar></w:tblPr><w:tblGrid>'
            .str_repeat('<w:gridCol w:w="'.$cw.'"/>', $cols).'</w:tblGrid>';

        foreach ($rows as $ri => $row) {
            $head = $ri === 0;
            $xml .= '<w:tr><w:trPr><w:cantSplit/>'.($head ? '<w:tblHeader/>' : '').'</w:trPr>';

            for ($c = 0; $c < $cols; $c++) {
                $text = (string) ($row[$c] ?? '');
                $shade = $head && $t['header_shade'] ? '<w:shd w:val="clear" w:color="auto" w:fill="E6E6E6"/>' : '';
                $bold = $head && $t['header_bold'] ? '<w:rPr><w:b/></w:rPr>' : '';
                $xml .= '<w:tc><w:tcPr><w:tcW w:w="'.$cw.'" w:type="dxa"/>'.$shade.'</w:tcPr><w:p><w:pPr><w:pStyle w:val="TableText"/></w:pPr>'
                    .($text === '' ? '' : '<w:r>'.$bold.'<w:t xml:space="preserve">'.$this->x($text).'</w:t></w:r>').'</w:p></w:tc>';
            }

            $xml .= '</w:tr>';
        }

        return $xml.'</w:tbl><w:p><w:pPr><w:pStyle w:val="Normal"/><w:spacing w:before="0" w:after="120"/></w:pPr></w:p>';
    }

    /*
    |--------------------------------------------------------------------------
    | Header dan footer
    |--------------------------------------------------------------------------
    */

    /** @return array<string, array{file: string, type: string, xml: string}> */
    private function headerFooterParts(): array
    {
        $hf = $this->L['header_footer'];
        $pn = $this->L['page_numbers'];
        $out = [];

        foreach (['header' => 'h', 'footer' => 'f'] as $zone => $k) {
            foreach (['Odd' => true, 'Even' => false] as $name => $odd) {
                $slots = ['left' => '', 'center' => '', 'right' => ''];

                foreach (['left', 'center', 'right'] as $slot) {
                    $target = $hf['odd_even'] && ! $odd ? ['left' => 'right', 'right' => 'left', 'center' => 'center'][$slot] : $slot;
                    $slots[$target] = $hf[$zone][$slot];
                }

                if ($pn['position'] !== 'none') {
                    [$pz, $where] = explode('_', $pn['position']);

                    if ($pz === $zone) {
                        $where = $where === 'outside' ? ($odd ? 'right' : 'left') : $where;
                        $slots[$where] = $slots[$where] === '' ? '{page}' : $slots[$where].'   {page}';
                    }
                }

                $out['rId'.strtoupper($k).$name] = ['file' => $zone.'-'.strtolower($name).'.xml', 'type' => $zone, 'xml' => $this->hfXml($zone, $slots)];
            }

            $out['rId'.strtoupper($k).'None'] = ['file' => $zone.'-none.xml', 'type' => $zone, 'xml' => $this->hfXml($zone, ['left' => '', 'center' => '', 'right' => ''])];
        }

        return $out;
    }

    private function hfXml(string $zone, array $slots): string
    {
        $w = PrintLayout::twips(PrintLayout::box($this->L, true)['width']);
        $tag = $zone === 'header' ? 'hdr' : 'ftr';
        $style = $zone === 'header' ? 'Header' : 'Footer';
        $runs = $this->runs($slots['left']).'<w:r><w:tab/></w:r>'.$this->runs($slots['center']).'<w:r><w:tab/></w:r>'.$this->runs($slots['right']);
        $empty = trim($slots['left'].$slots['center'].$slots['right']) === '';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:'.$tag.' '.self::NS.'><w:p><w:pPr><w:pStyle w:val="'.$style.'"/><w:tabs><w:tab w:val="clear" w:pos="4680"/><w:tab w:val="clear" w:pos="9360"/>'
            .'<w:tab w:val="center" w:pos="'.(int) ($w / 2).'"/><w:tab w:val="right" w:pos="'.$w.'"/></w:tabs></w:pPr>'.($empty ? '' : $runs).'</w:p></w:'.$tag.'>';
    }

    /** Teks dengan token -> run: {title} literal, {page} field PAGE, {chapter} field STYLEREF "Heading 1". */
    private function runs(string $text): string
    {
        if ($text === '') {
            return '';
        }

        $xml = '';

        foreach (preg_split('/(\{[a-z]+\})/', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
            $xml .= match ($part) {
                '{title}' => $this->run($this->doc['title']),
                '{page}' => $this->field(' PAGE ', '1'),
                '{chapter}' => $this->field(' STYLEREF "Heading 1" ', ' '),
                default => $this->run($part),
            };
        }

        return $xml;
    }

    private function run(string $t): string
    {
        return '<w:r><w:t xml:space="preserve">'.$this->x($t).'</w:t></w:r>';
    }

    private function field(string $instr, string $cached): string
    {
        return '<w:r><w:fldChar w:fldCharType="begin"/></w:r><w:r><w:instrText xml:space="preserve">'.$instr.'</w:instrText></w:r><w:r><w:fldChar w:fldCharType="separate"/></w:r><w:r><w:t xml:space="preserve">'.$cached.'</w:t></w:r><w:r><w:fldChar w:fldCharType="end"/></w:r>';
    }

    /*
    |--------------------------------------------------------------------------
    | Style dan pengaturan
    |--------------------------------------------------------------------------
    */

    private function settings(): string
    {
        $pn = $this->L['page_numbers'];
        $evenOdd = $this->L['header_footer']['odd_even'] || str_ends_with($pn['position'], 'outside');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:settings '.self::NS.'>'
            .($this->L['margins']['mirror'] ? '<w:mirrorMargins/>' : '')
            .'<w:defaultTabStop w:val="720"/>'
            .($evenOdd ? '<w:evenAndOddHeaders/>' : '')
            .($this->L['toc']['enabled'] ? '<w:updateFields w:val="true"/>' : '')
            .'<w:compat><w:compatSetting w:name="compatibilityMode" w:uri="http://schemas.microsoft.com/office/word" w:val="15"/></w:compat></w:settings>';
    }

    private function font(string $key): string
    {
        $name = $this->x(PrintLayout::FONTS[$key]['docx'] ?? 'Times New Roman');

        return '<w:rFonts w:ascii="'.$name.'" w:hAnsi="'.$name.'" w:cs="'.$name.'" w:eastAsia="'.$name.'"/>';
    }

    private function hp(float $pt): int
    {
        return max(14, (int) round($pt * 2));
    }

    private function styles(): string
    {
        $L = $this->L;
        $t = $L['type'];
        $p = $L['para'];
        $body = $this->hp($t['body_size']);
        $line = (int) round(240 * $p['line_spacing']);
        $jc = ['left' => 'left', 'justify' => 'both', 'center' => 'center', 'right' => 'right'][$p['align']];
        $widow = $p['widow'] ? '<w:widowControl/>' : '<w:widowControl w:val="0"/>';
        $textW = PrintLayout::twips(PrintLayout::box($L, true)['width']);
        $leader = $L['toc']['dot_leaders'] ? 'dot' : 'none';

        $heading = function (int $n) use ($L, $t): string {
            $h = $L['headings']['h'.$n];

            return '<w:style w:type="paragraph" w:styleId="Heading'.$n.'"><w:name w:val="heading '.$n.'"/><w:basedOn w:val="Normal"/><w:next w:val="BodyText"/><w:qFormat/>'
                .'<w:pPr>'.($h['keep_next'] ? '<w:keepNext/>' : '').'<w:keepLines/><w:spacing w:before="'.(int) round($h['before'] * 20).'" w:after="'.(int) round($h['after'] * 20).'" w:line="'.(int) round(240 * 1.1).'" w:lineRule="auto"/><w:jc w:val="'.$h['align'].'"/><w:outlineLvl w:val="'.($n - 1).'"/></w:pPr>'
                .'<w:rPr>'.$this->font($t['heading_font']).($h['bold'] ? '<w:b/>' : '').'<w:sz w:val="'.$this->hp($h['size']).'"/><w:szCs w:val="'.$this->hp($h['size']).'"/></w:rPr></w:style>';
        };

        $toc = function (int $n) use ($textW, $leader, $body): string {
            return '<w:style w:type="paragraph" w:styleId="TOC'.$n.'"><w:name w:val="toc '.$n.'"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:uiPriority w:val="39"/>'
                .'<w:pPr><w:tabs><w:tab w:val="right" w:leader="'.$leader.'" w:pos="'.$textW.'"/></w:tabs><w:spacing w:before="0" w:after="40"/><w:ind w:left="'.(($n - 1) * 340).'"/></w:pPr></w:style>';
        };

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:styles '.self::NS.'>'
            .'<w:docDefaults><w:rPrDefault><w:rPr>'.$this->font($t['body_font']).'<w:sz w:val="'.$body.'"/><w:szCs w:val="'.$body.'"/><w:lang w:val="id-ID"/></w:rPr></w:rPrDefault><w:pPrDefault><w:pPr><w:spacing w:after="0" w:line="240" w:lineRule="auto"/></w:pPr></w:pPrDefault></w:docDefaults>'
            .'<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/><w:qFormat/></w:style>'
            .'<w:style w:type="paragraph" w:styleId="BodyText"><w:name w:val="Body Text"/><w:basedOn w:val="Normal"/><w:qFormat/><w:pPr>'.$widow
            .'<w:spacing w:before="'.(int) round($p['before'] * 20).'" w:after="'.(int) round($p['after'] * 20).'" w:line="'.$line.'" w:lineRule="auto"/><w:ind w:firstLine="'.PrintLayout::twips($p['indent']).'"/><w:jc w:val="'.$jc.'"/></w:pPr></w:style>'
            .$heading(1).$heading(2).$heading(3)
            .'<w:style w:type="paragraph" w:styleId="Title"><w:name w:val="Title"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:qFormat/><w:pPr><w:spacing w:before="0" w:after="360"/><w:jc w:val="center"/></w:pPr>'
            .'<w:rPr>'.$this->font($t['heading_font']).'<w:b/><w:sz w:val="'.$this->hp(max(20.0, min(28.0, $L['headings']['h1']['size'] * 1.8))).'"/></w:rPr></w:style>'
            .'<w:style w:type="paragraph" w:styleId="Subtitle"><w:name w:val="Subtitle"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:qFormat/><w:pPr><w:spacing w:before="0" w:after="160"/><w:jc w:val="center"/></w:pPr></w:style>'
            .'<w:style w:type="paragraph" w:styleId="Caption"><w:name w:val="caption"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:qFormat/><w:pPr><w:keepNext/><w:spacing w:before="80" w:after="80"/><w:jc w:val="center"/></w:pPr><w:rPr><w:sz w:val="'.$this->hp($t['caption_size']).'"/></w:rPr></w:style>'
            .'<w:style w:type="paragraph" w:styleId="Reference"><w:name w:val="Reference"/><w:basedOn w:val="Normal"/><w:qFormat/><w:pPr><w:spacing w:before="0" w:after="'.(int) round($L['refs']['after'] * 20).'" w:line="'.(int) round(240 * 1.1).'" w:lineRule="auto"/><w:ind w:left="'.PrintLayout::twips($L['refs']['hanging']).'" w:hanging="'.PrintLayout::twips($L['refs']['hanging']).'"/><w:jc w:val="left"/></w:pPr>'
            .'<w:rPr>'.$this->font($t['reference_font']).'<w:sz w:val="'.$this->hp($t['reference_size']).'"/></w:rPr></w:style>'
            .'<w:style w:type="paragraph" w:styleId="Meta"><w:name w:val="Meta"/><w:basedOn w:val="Normal"/><w:pPr><w:spacing w:after="40"/></w:pPr><w:rPr>'.$this->font('sans').'<w:sz w:val="16"/></w:rPr></w:style>'
            .'<w:style w:type="paragraph" w:styleId="TableText"><w:name w:val="Table Text"/><w:basedOn w:val="Normal"/><w:pPr><w:spacing w:before="0" w:after="0"/></w:pPr><w:rPr><w:sz w:val="'.$this->hp(max(8.0, $t['body_size'] - 1)).'"/></w:rPr></w:style>'
            .'<w:style w:type="paragraph" w:styleId="TOCHeading"><w:name w:val="TOC Heading"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:uiPriority w:val="39"/><w:pPr><w:keepNext/><w:spacing w:before="0" w:after="'.(int) round(($L['headings']['h1']['after'] + 6) * 20).'"/><w:jc w:val="center"/></w:pPr>'
            .'<w:rPr>'.$this->font($t['heading_font']).'<w:b/><w:sz w:val="'.$this->hp($L['headings']['h1']['size']).'"/></w:rPr></w:style>'
            .$toc(1).$toc(2).$toc(3)
            .'<w:style w:type="paragraph" w:styleId="Header"><w:name w:val="header"/><w:basedOn w:val="Normal"/><w:rPr><w:sz w:val="'.$this->hp(max(8.0, min(10.0, $t['body_size'] - 2))).'"/></w:rPr></w:style>'
            .'<w:style w:type="paragraph" w:styleId="Footer"><w:name w:val="footer"/><w:basedOn w:val="Normal"/><w:rPr><w:sz w:val="'.$this->hp(max(8.0, min(10.0, $t['body_size'] - 2))).'"/></w:rPr></w:style>'
            .'</w:styles>';
    }

    private function x(string $s): string
    {
        $s = (string) preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $s);

        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
