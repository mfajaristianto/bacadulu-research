<?php

namespace App\Services\FullText;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Smalot\PdfParser\Config as PdfConfig;
use Smalot\PdfParser\Parser;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Ekstraksi teks dari berkas yang SUDAH tersimpan (tidak pernah memakai OCR atau layanan berbayar). Menjaga nomor halaman:
 * halaman kosong tetap dihitung agar kutipan halaman benar. PDF pindaian (tanpa lapisan teks) dilaporkan gagal dengan alasan
 * `no_text_layer`; teks tidak pernah dikarang dari metadata atau abstrak.
 *
 * TEI/JATS XML: anti-XXE (dokumen ber-DOCTYPE/ENTITY ditolak, jaringan dimatikan). Batasan jujur: JATS yang membawa DOCTYPE
 * (umum pada berkas penerbit) ikut ditolak.
 */
final class FullTextExtractor
{
    /**
     * @return array{blocks: list<array{page: ?int, section: ?string, refs: bool, text: string}>, method: string, pages_total: ?int, pages_with_text: ?int, chars: int}
     */
    public function extract(string $absolutePath, string $kind = 'pdf'): array
    {
        if (! is_file($absolutePath)) {
            throw new FullTextException('file_missing', 'Berkas lokal tidak ditemukan.');
        }

        return $kind === 'xml' ? $this->fromXml($absolutePath) : $this->fromPdf($absolutePath);
    }

    private function fromPdf(string $path): array
    {
        $cfg = (array) config('external-sources.fulltext.extract');
        [$pages, $method] = $this->pdfPages($path, (int) $cfg['max_pages'], (int) $cfg['timeout']);

        $total = count($pages);
        if ($total === 0) {
            throw new FullTextException('no_text_layer', 'PDF tidak menghasilkan halaman teks.');
        }
        if ($total > (int) $cfg['max_pages']) {
            throw new FullTextException('too_many_pages', "PDF melebihi batas {$cfg['max_pages']} halaman.");
        }

        $withText = count(array_filter($pages, fn ($t) => preg_match_all('/[\p{L}\p{N}]/u', $t) >= 20));
        $chars = array_sum(array_map('mb_strlen', $pages));

        if ($chars > (int) $cfg['max_chars']) {
            throw new FullTextException('text_too_large', 'Teks hasil ekstraksi melebihi batas ukuran.');
        }
        if ($withText === 0 || $withText / $total < (float) $cfg['min_text_page_ratio']) {
            throw new FullTextException('no_text_layer', "Hanya {$withText} dari {$total} halaman memiliki teks: PDF kemungkinan berupa pindaian dan memerlukan proses tambahan (OCR) yang tidak dijalankan otomatis.");
        }

        return ['blocks' => SectionSegmenter::fromPages($pages), 'method' => $method, 'pages_total' => $total, 'pages_with_text' => $withText, 'chars' => $chars];
    }

    /** @return array{0: list<string>, 1: string} */
    private function pdfPages(string $path, int $maxPages, int $timeout): array
    {
        $binary = trim((string) config('research.ingestion.pdftotext_binary', 'pdftotext'));
        if ($binary !== '' && function_exists('proc_open')) {
            try {
                $limit = (int) config('external-sources.fulltext.extract.max_chars') * 4;
                $out = '';
                $process = new Process([$binary, '-layout', '-enc', 'UTF-8', '-l', (string) ($maxPages + 1), $path, '-']);
                $process->setTimeout(max(10, $timeout));
                $process->run(function ($type, $buffer) use (&$out, $limit, $process): void {
                    if ($type === Process::OUT) {
                        $out .= $buffer;
                        if (strlen($out) > $limit) {
                            $process->stop(0);
                        }
                    }
                });
                if ($process->isSuccessful() && trim($out) !== '') {
                    $pages = explode("\f", str_replace(["\r\n", "\r", "\0"], ["\n", "\n", ''], $out));
                    if (end($pages) === '') {
                        array_pop($pages);
                    }

                    return [$pages, 'pdftotext'];
                }
            } catch (Throwable) {
                // jatuh ke parser PHP
            }
        }

        if (! class_exists(Parser::class)) {
            throw new FullTextException('parser_missing', 'Parser PDF tidak tersedia.');
        }

        try {
            $config = new PdfConfig;
            $config->setRetainImageContent(false);
            $doc = (new Parser([], $config))->parseFile($path);
            $list = $doc->getPages();
            if (count($list) > $maxPages) {
                throw new FullTextException('too_many_pages', "PDF melebihi batas {$maxPages} halaman.");
            }
            $pages = [];
            foreach ($list as $page) {
                $pages[] = trim(str_replace(["\r\n", "\r", "\0"], ["\n", "\n", ''], (string) $page->getText()));
            }

            return [$pages, 'smalot/pdfparser'];
        } catch (FullTextException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new FullTextException('parse_error', 'PDF tidak dapat dibaca (rusak, terenkripsi, atau tidak didukung).');
        }
    }

    private function fromXml(string $path): array
    {
        $cfg = (array) config('external-sources.fulltext.extract');
        $xml = (string) file_get_contents($path);

        if (preg_match('/<!DOCTYPE|<!ENTITY/i', $xml)) {
            throw new FullTextException('xml_unsafe', 'XML dengan DOCTYPE/ENTITY ditolak (perlindungan XXE).');
        }

        $doc = new DOMDocument;
        $prev = libxml_use_internal_errors(true);
        $ok = $doc->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if (! $ok || ! $doc->documentElement) {
            throw new FullTextException('parse_error', 'XML tidak dapat di-parse.');
        }

        $xp = new DOMXPath($doc);
        $root = strtolower($doc->documentElement->localName);
        if (! in_array($root, ['tei', 'article'], true)) {
            throw new FullTextException('xml_unknown', 'Format XML bukan TEI atau JATS.');
        }

        $text = fn (?\DOMNode $n): string => trim((string) preg_replace('/\s+/u', ' ', (string) $n?->textContent));
        $blocks = [];
        $add = function (?string $section, bool $refs, string $t) use (&$blocks): void {
            if ($t !== '') {
                $blocks[] = ['page' => null, 'section' => $section, 'refs' => $refs, 'text' => $t];
            }
        };

        // Abstrak.
        foreach ($xp->query("//*[local-name()='abstract']") as $a) {
            $add('abstract', false, $text($a));
        }
        // Bagian isi: paragraf LANGSUNG milik bagian (tanpa menggandakan sub-bagian).
        foreach ($xp->query("//*[local-name()='body']//*[local-name()='div' or local-name()='sec']") as $sec) {
            /** @var DOMElement $sec */
            $head = $xp->query("*[local-name()='head' or local-name()='title']", $sec)->item(0);
            $name = $head ? SectionSegmenter::canonical($text($head)) : null;
            $paras = [];
            foreach ($xp->query("*[local-name()='p']", $sec) as $p) {
                $paras[] = $text($p);
            }
            $add($name, $name === 'references', implode(' ', $paras));
        }
        // Daftar pustaka: disimpan, tetapi ditandai referensi.
        foreach ($xp->query("//*[local-name()='listBibl' or local-name()='ref-list']") as $list) {
            $add('references', true, $text($list));
        }

        $chars = array_sum(array_map(fn ($b) => mb_strlen($b['text']), $blocks));
        if ($chars === 0) {
            throw new FullTextException('no_text_layer', 'XML tidak memuat teks isi.');
        }
        if ($chars > (int) $cfg['max_chars']) {
            throw new FullTextException('text_too_large', 'Teks hasil ekstraksi melebihi batas ukuran.');
        }

        return ['blocks' => $blocks, 'method' => 'xml-'.$root, 'pages_total' => null, 'pages_with_text' => null, 'chars' => $chars];
    }
}
