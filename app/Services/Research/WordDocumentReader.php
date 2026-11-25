<?php

namespace App\Services\Research;

use App\Exceptions\WordImportException;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use ZipArchive;

/*
 * Pembaca DOCX yang aman untuk impor Prompt Dokumen. Hanya TEKS: tidak ada gambar, makro, objek tertanam, tautan, atau
 * relasi eksternal yang pernah diikuti. Isi berkas diperlakukan sebagai data.
 *
 * Perlindungan (semuanya diuji dengan fixture di tests/Feature/AdminDocumentWordImportTest):
 *   - ZIP: batas jumlah entri, nama entri berbahaya (path traversal, absolut, backslash), total dan rasio ukuran
 *     (zip bomb), ukuran bagian dibaca dengan batas keras (header tidak dipercaya), CHECKCONS.
 *   - Makro: berkas dengan vbaProject.bin atau tipe macroEnabled ditolak, tidak pernah dijalankan.
 *   - XML: dokumen yang memuat DOCTYPE/ENTITY ditolak (OOXML sah tidak memakainya) sehingga XXE dan entity expansion mustahil;
 *     libxml dipanggil dengan LIBXML_NONET dan tanpa LIBXML_NOENT/DTDLOAD.
 *   - Waktu dan jumlah blok/teks dibatasi.
 *
 * Hasil: daftar blok berurutan (judul, heading, paragraf, butir daftar, tabel) beserta peringatan tentang apa yang tidak dibaca.
 */
class WordDocumentReader
{
    public const MAX_FILE_BYTES = 5 * 1024 * 1024;

    private const NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    private const MAX_ENTRIES = 1000;
    private const MAX_DOC_BYTES = 8 * 1024 * 1024;
    private const MAX_SMALL_PART = 2 * 1024 * 1024;
    private const MAX_TOTAL_UNCOMPRESSED = 80 * 1024 * 1024;
    private const MAX_RATIO = 200;
    private const MAX_BLOCKS = 20000;
    private const MAX_TEXT_CHARS = 500000;
    private const TIME_LIMIT = 20.0;

    private float $started = 0.0;

    /** @var array<string, array{name: string, outline: ?int, num: ?array}> */
    private array $styles = [];

    /** @var array<int, array<int, array{fmt: string}>> abstractNumId => ilvl => fmt */
    private array $abstract = [];

    /** @var array<int, int> numId => abstractNumId */
    private array $nums = [];

    /** @var array<string, int> penghitung butir bernomor per "numId:ilvl" */
    private array $counters = [];

    private int $chars = 0;

    private array $notes = [];

    /**
     * @return array{blocks: list<array<string, mixed>>, warnings: list<string>, stats: array<string, int>}
     *
     * @throws WordImportException
     */
    public function read(string $path): array
    {
        $this->started = microtime(true);
        $this->styles = $this->abstract = $this->nums = $this->counters = [];
        $this->chars = 0;
        $this->notes = ['drawing' => 0, 'textbox' => 0, 'external' => 0, 'footnotes' => false, 'tracked' => 0, 'truncated' => false];

        if (! class_exists(ZipArchive::class)) {
            throw new WordImportException('Server belum mendukung pembacaan berkas Word (ekstensi zip tidak aktif).', 500);
        }

        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::RDONLY | ZipArchive::CHECKCONS) !== true) {
            throw new WordImportException('Berkas bukan dokumen Word (.docx) yang utuh. Pastikan file tidak rusak lalu coba lagi.');
        }

        try {
            $names = $this->inspect($zip);
            $documentXml = $this->part($zip, 'word/document.xml', self::MAX_DOC_BYTES);
            $this->guardXml($documentXml, 'isi dokumen');

            if (isset($names['word/styles.xml'])) {
                $this->loadStyles($this->safeXml($this->part($zip, 'word/styles.xml', self::MAX_SMALL_PART), 'gaya dokumen'));
            }

            if (isset($names['word/numbering.xml'])) {
                $this->loadNumbering($this->safeXml($this->part($zip, 'word/numbering.xml', self::MAX_SMALL_PART), 'penomoran'));
            }

            if (isset($names['word/_rels/document.xml.rels'])) {
                $rels = $this->part($zip, 'word/_rels/document.xml.rels', 1024 * 1024);
                $this->notes['external'] = (int) preg_match_all('/TargetMode\s*=\s*"External"/i', $rels);
            }

            $this->notes['footnotes'] = isset($names['word/footnotes.xml']) || isset($names['word/endnotes.xml']);
            $dom = $this->safeXml($documentXml, 'isi dokumen');
        } finally {
            $zip->close();
        }

        $xp = $this->xpath($dom);
        $body = $xp->query('/w:document/w:body')->item(0);

        if (! $body instanceof DOMElement) {
            throw new WordImportException('Isi dokumen Word tidak ditemukan (elemen body kosong).');
        }

        $blocks = [];
        $this->walk($body, $xp, $blocks);

        $stats = ['blocks' => count($blocks), 'headings' => 0, 'list_items' => 0, 'tables' => 0, 'paragraphs' => 0];

        foreach ($blocks as $b) {
            match ($b['t']) {
                'h', 'title' => $stats['headings']++,
                'li' => $stats['list_items']++,
                'table' => $stats['tables']++,
                default => $stats['paragraphs']++,
            };
        }

        return ['blocks' => $blocks, 'warnings' => $this->warnings(), 'stats' => $stats];
    }

    /*
    |--------------------------------------------------------------------------
    | ZIP
    |--------------------------------------------------------------------------
    */

    /** @return array<string, true> nama entri yang ada */
    private function inspect(ZipArchive $zip): array
    {
        if ($zip->numFiles < 1 || $zip->numFiles > self::MAX_ENTRIES) {
            throw new WordImportException('Struktur berkas tidak wajar (jumlah isi arsip di luar batas). Berkas ditolak.');
        }

        $names = [];
        $total = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $st = $zip->statIndex($i);
            $name = (string) ($st['name'] ?? '');

            if ($name === '' || str_contains($name, "\0") || str_contains($name, '\\') || str_starts_with($name, '/') || preg_match('#(^|/)\.\.(/|$)#', $name) || preg_match('#^[A-Za-z]:#', $name)) {
                throw new WordImportException('Berkas memuat nama isi arsip yang berbahaya (path traversal). Berkas ditolak.');
            }

            if (preg_match('#(^|/)vbaProject\.bin$#i', $name) || preg_match('#\.(docm|exe|dll|bat|cmd|js|vbs|ps1)$#i', $name)) {
                throw new WordImportException('Dokumen memuat makro atau berkas yang dapat dieksekusi. Berkas ditolak; simpan ulang sebagai .docx tanpa makro.');
            }

            $size = (int) ($st['size'] ?? 0);
            $comp = (int) ($st['comp_size'] ?? 0);
            $total += $size;

            if ($size > 1024 * 1024 && ($comp === 0 || $size / max(1, $comp) > self::MAX_RATIO)) {
                throw new WordImportException('Rasio kompresi berkas tidak wajar (indikasi zip bomb). Berkas ditolak.');
            }

            $names[$name] = true;
        }

        if ($total > self::MAX_TOTAL_UNCOMPRESSED) {
            throw new WordImportException('Ukuran isi dokumen setelah diekstrak terlalu besar. Berkas ditolak.');
        }

        if (! isset($names['[Content_Types].xml']) || ! isset($names['word/document.xml'])) {
            throw new WordImportException('Berkas bukan dokumen Word (.docx): bagian wajib tidak ditemukan.');
        }

        $types = $this->part($zip, '[Content_Types].xml', 1024 * 1024);

        if (stripos($types, 'macroEnabled') !== false) {
            throw new WordImportException('Dokumen bertipe berkas berisi makro. Berkas ditolak.');
        }

        return $names;
    }

    /** Membaca bagian dengan batas keras: ukuran di header tidak dipercaya. */
    private function part(ZipArchive $zip, string $name, int $cap): string
    {
        $stat = $zip->statName($name);

        if ($stat !== false && (int) $stat['size'] > $cap) {
            throw new WordImportException('Bagian "'.$name.'" terlalu besar untuk diimpor (maks '.round($cap / 1048576, 1).' MB). Pecah dokumen menjadi beberapa berkas.');
        }

        $stream = $zip->getStream($name);

        if (! is_resource($stream)) {
            throw new WordImportException('Bagian "'.$name.'" tidak dapat dibaca. Berkas mungkin rusak.');
        }

        $data = '';

        while (! feof($stream)) {
            $data .= (string) fread($stream, 65536);

            if (strlen($data) > $cap) {
                fclose($stream);

                throw new WordImportException('Bagian "'.$name.'" melebihi batas ukuran setelah diekstrak. Berkas ditolak.');
            }
        }

        fclose($stream);

        return $data;
    }

    /*
    |--------------------------------------------------------------------------
    | XML
    |--------------------------------------------------------------------------
    */

    private function guardXml(string $xml, string $label): void
    {
        if (preg_match('/<!DOCTYPE|<!ENTITY|<!\[CDATA\[.{0,16}<!ENTITY/is', $xml)) {
            throw new WordImportException('XML pada '.$label.' memuat deklarasi DOCTYPE/ENTITY yang tidak dipakai dokumen Word sah. Berkas ditolak.');
        }
    }

    private function safeXml(string $xml, string $label): DOMDocument
    {
        $this->guardXml($xml, $label);

        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument;
        $ok = $dom->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $ok) {
            throw new WordImportException('XML pada '.$label.' tidak valid. Berkas mungkin rusak.');
        }

        return $dom;
    }

    private function xpath(DOMDocument $dom): DOMXPath
    {
        $xp = new DOMXPath($dom);
        $xp->registerNamespace('w', self::NS);

        return $xp;
    }

    private function loadStyles(DOMDocument $dom): void
    {
        $xp = $this->xpath($dom);

        foreach ($xp->query('//w:style[@w:type="paragraph"]') as $style) {
            $id = $style->getAttributeNS(self::NS, 'styleId');
            $outline = $xp->query('w:pPr/w:outlineLvl', $style)->item(0);
            $numId = $xp->query('w:pPr/w:numPr/w:numId', $style)->item(0);
            $ilvl = $xp->query('w:pPr/w:numPr/w:ilvl', $style)->item(0);

            $this->styles[$id] = [
                'name' => strtolower((string) $xp->query('w:name', $style)->item(0)?->getAttributeNS(self::NS, 'val')),
                'outline' => $outline ? (int) $outline->getAttributeNS(self::NS, 'val') : null,
                'num' => $numId ? ['id' => (int) $numId->getAttributeNS(self::NS, 'val'), 'lvl' => $ilvl ? (int) $ilvl->getAttributeNS(self::NS, 'val') : 0] : null,
            ];
        }
    }

    private function loadNumbering(DOMDocument $dom): void
    {
        $xp = $this->xpath($dom);

        foreach ($xp->query('//w:abstractNum') as $abs) {
            $id = (int) $abs->getAttributeNS(self::NS, 'abstractNumId');

            foreach ($xp->query('w:lvl', $abs) as $lvl) {
                $this->abstract[$id][(int) $lvl->getAttributeNS(self::NS, 'ilvl')] = [
                    'fmt' => (string) $xp->query('w:numFmt', $lvl)->item(0)?->getAttributeNS(self::NS, 'val') ?: 'decimal',
                ];
            }
        }

        foreach ($xp->query('//w:num') as $num) {
            $this->nums[(int) $num->getAttributeNS(self::NS, 'numId')] = (int) $xp->query('w:abstractNumId', $num)->item(0)?->getAttributeNS(self::NS, 'val');
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Badan dokumen
    |--------------------------------------------------------------------------
    */

    private function walk(DOMNode $parent, DOMXPath $xp, array &$blocks): void
    {
        foreach ($parent->childNodes as $node) {
            if (! $node instanceof DOMElement || $node->namespaceURI !== self::NS) {
                continue;
            }

            if (microtime(true) - $this->started > self::TIME_LIMIT) {
                throw new WordImportException('Pembacaan dokumen melewati batas waktu. Coba dokumen yang lebih pendek.', 422);
            }

            if (count($blocks) >= self::MAX_BLOCKS || $this->chars >= self::MAX_TEXT_CHARS) {
                $this->notes['truncated'] = true;

                return;
            }

            match ($node->localName) {
                'p' => $this->paragraph($node, $xp, $blocks),
                'tbl' => $this->table($node, $xp, $blocks),
                'sdt' => ($content = $xp->query('w:sdtContent', $node)->item(0)) ? $this->walk($content, $xp, $blocks) : null,
                'ins' => $this->walk($node, $xp, $blocks),
                default => null,
            };
        }
    }

    private function paragraph(DOMElement $p, DOMXPath $xp, array &$blocks): void
    {
        [$text, $allBold, $phrases] = $this->runs($p, $xp);
        $text = $this->clean($text);

        if ($text === '') {
            return;
        }

        $styleId = (string) $xp->query('w:pPr/w:pStyle', $p)->item(0)?->getAttributeNS(self::NS, 'val');
        $style = $this->styles[$styleId] ?? ['name' => strtolower($styleId), 'outline' => null, 'num' => null];
        $outline = $xp->query('w:pPr/w:outlineLvl', $p)->item(0);
        $outlineLvl = $outline ? (int) $outline->getAttributeNS(self::NS, 'val') : $style['outline'];
        $name = $style['name'] !== '' ? $style['name'] : strtolower($styleId);
        $this->chars += mb_strlen($text);

        $numNode = $xp->query('w:pPr/w:numPr', $p)->item(0);
        $num = $style['num'];

        if ($numNode) {
            $id = (int) $xp->query('w:numId', $numNode)->item(0)?->getAttributeNS(self::NS, 'val');
            $num = $id > 0 ? ['id' => $id, 'lvl' => (int) $xp->query('w:ilvl', $numNode)->item(0)?->getAttributeNS(self::NS, 'val')] : null;
        }

        $base = ['text' => $text, 'bold' => $allBold, 'phrases' => $phrases];

        if ($name === 'title') {
            $blocks[] = ['t' => 'title', 'level' => 0] + $base;

            return;
        }

        if (preg_match('/^heading\s*(\d)$/', $name, $m) || ($outlineLvl !== null && $outlineLvl <= 8)) {
            $level = isset($m[1]) ? (int) $m[1] : $outlineLvl + 1;
            $blocks[] = ['t' => 'h', 'level' => $level] + $base;

            return;
        }

        if ($num) {
            $fmt = $this->abstract[$this->nums[$num['id']] ?? -1][$num['lvl']]['fmt'] ?? 'decimal';

            if ($fmt !== 'none') {
                $blocks[] = ['t' => 'li', 'lvl' => $num['lvl'], 'num_id' => $num['id'], 'fmt' => $fmt, 'label' => $this->label($num['id'], $num['lvl'], $fmt)] + $base;

                return;
            }
        }

        $blocks[] = ['t' => 'p'] + $base;
    }

    /** @return array{0: string, 1: bool, 2: list<string>} teks, semua tebal?, frasa tebal (>=3 kata) */
    private function runs(DOMElement $p, DOMXPath $xp): array
    {
        $text = '';
        $bold = 0;
        $plain = 0;
        $phrases = [];
        $current = '';

        foreach ($xp->query('.//w:r', $p) as $run) {
            if ($xp->query('ancestor::w:txbxContent', $run)->length) {
                $this->notes['textbox']++;

                continue;
            }

            if ($xp->query('ancestor::w:del', $run)->length) {
                $this->notes['tracked']++;

                continue;
            }

            if ($xp->query('w:drawing|w:pict|w:object', $run)->length) {
                $this->notes['drawing']++;
            }

            $piece = '';

            foreach ($run->childNodes as $c) {
                if (! $c instanceof DOMElement) {
                    continue;
                }

                $piece .= match ($c->localName) {
                    't' => $c->textContent,
                    'tab' => "\t",
                    'br', 'cr' => "\n",
                    'noBreakHyphen' => '-',
                    default => '',
                };
            }

            if (trim($piece) === '') {
                $text .= $piece;

                continue;
            }

            $b = $xp->query('w:rPr/w:b', $run)->item(0);
            $isBold = $b !== null && ! in_array(strtolower($b->getAttributeNS(self::NS, 'val')), ['0', 'false', 'off'], true);
            $isBold ? $bold++ : $plain++;
            $text .= $piece;

            if ($isBold) {
                $current .= $piece;
            } elseif ($current !== '') {
                $phrases[] = trim($current);
                $current = '';
            }
        }

        if ($current !== '') {
            $phrases[] = trim($current);
        }

        $phrases = array_values(array_filter(array_map(fn ($s) => trim($s, " 	,.;:"), $phrases), fn ($s) => preg_match_all('/\p{L}+/u', $s) >= 3 && mb_strlen($s) <= 160));

        return [$text, $bold > 0 && $plain === 0, $plain === 0 ? [] : $phrases];
    }

    private function table(DOMElement $tbl, DOMXPath $xp, array &$blocks): void
    {
        $rows = [];

        foreach ($xp->query('w:tr', $tbl) as $tr) {
            if (count($rows) >= 200) {
                $this->notes['truncated'] = true;

                break;
            }

            $cells = [];

            foreach ($xp->query('w:tc', $tr) as $tc) {
                $parts = [];

                foreach ($xp->query('.//w:p', $tc) as $p) {
                    $t = $this->clean($this->runs($p, $xp)[0]);

                    if ($t !== '') {
                        $parts[] = $t;
                    }
                }

                $cells[] = implode(' ', $parts);
            }

            if (array_filter($cells, fn ($c) => $c !== '')) {
                $rows[] = array_slice($cells, 0, 20);
            }
        }

        if ($rows) {
            $text = implode("\n", array_map(fn ($r) => implode(' | ', $r), $rows));
            $this->chars += mb_strlen($text);
            $blocks[] = ['t' => 'table', 'text' => $text, 'rows' => count($rows), 'bold' => false, 'phrases' => []];
        }
    }

    private function label(int $numId, int $lvl, string $fmt): string
    {
        $key = $numId.':'.$lvl;
        $this->counters[$key] = ($this->counters[$key] ?? 0) + 1;

        foreach (array_keys($this->counters) as $other) {
            [$n, $l] = array_map('intval', explode(':', $other));

            if ($n === $numId && $l > $lvl) {
                unset($this->counters[$other]);
            }
        }

        $n = $this->counters[$key];

        return match ($fmt) {
            'bullet' => '-',
            'lowerLetter' => chr(96 + (($n - 1) % 26) + 1).'.',
            'upperLetter' => chr(64 + (($n - 1) % 26) + 1).'.',
            'lowerRoman' => strtolower($this->roman($n)).'.',
            'upperRoman' => $this->roman($n).'.',
            default => $n.'.',
        };
    }

    private function roman(int $n): string
    {
        $out = '';

        foreach (['M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400, 'C' => 100, 'XC' => 90, 'L' => 50, 'XL' => 40, 'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1] as $s => $v) {
            while ($n >= $v) {
                $out .= $s;
                $n -= $v;
            }
        }

        return $out;
    }

    /** Teks mentah dibersihkan dari karakter kontrol dan spasi aneh; tidak ada HTML yang pernah dibangun dari sini. */
    private function clean(string $text): string
    {
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? '';
        $text = preg_replace('/[\x{00A0}\x{2007}\x{202F}]/u', ' ', $text) ?? '';
        $text = preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}]/u', '', $text) ?? '';

        return trim($text);
    }

    /** @return list<string> */
    private function warnings(): array
    {
        $n = $this->notes;
        $w = [];

        if ($n['drawing']) {
            $w[] = 'Dokumen memuat '.$n['drawing'].' gambar atau objek tertanam. Hanya teks yang diimpor; gambar tidak dibaca.';
        }

        if ($n['textbox']) {
            $w[] = 'Dokumen memuat kotak teks (text box). Isinya tidak diimpor; salin manual bila penting.';
        }

        if ($n['footnotes']) {
            $w[] = 'Catatan kaki dan catatan akhir tidak diimpor.';
        }

        if ($n['external']) {
            $w[] = 'Dokumen memuat '.$n['external'].' tautan atau relasi eksternal. Tidak ada yang diikuti atau diunduh; hanya teksnya yang dibaca.';
        }

        if ($n['tracked']) {
            $w[] = 'Dokumen memuat perubahan terlacak (track changes). Teks yang dihapus diabaikan; teks yang ditambahkan dibaca.';
        }

        if ($n['truncated']) {
            $w[] = 'Dokumen sangat panjang: pembacaan dihentikan pada batas aman, sehingga bagian akhir tidak terbaca. Pecah dokumen menjadi beberapa berkas.';
        }

        $w[] = 'Header, footer, dan komentar tidak dibaca.';

        return $w;
    }
}
