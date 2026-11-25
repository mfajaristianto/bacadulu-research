<?php

namespace Tests\Support;

/**
 * PDF kecil yang valid (dibuat dari string, tanpa berkas pihak ketiga) untuk uji. Satu elemen = satu halaman; baris dipisah "\n".
 * Halaman kosong ('') menghasilkan halaman tanpa teks (mensimulasikan pindaian).
 */
final class TinyPdf
{
    /** @param  list<string>  $pages */
    public static function build(array $pages): string
    {
        $objects = [1 => '<< /Type /Catalog /Pages 2 0 R >>', 3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>'];
        $kids = [];

        foreach ($pages as $i => $text) {
            $page = 4 + 2 * $i;
            $content = $page + 1;
            $kids[] = "{$page} 0 R";

            $ops = '';
            if (trim($text) !== '') {
                $ops = "BT /F1 11 Tf 14 TL 56 740 Td\n";
                foreach (preg_split('/\R/', $text) as $line) {
                    $ops .= '('.str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line).") Tj T*\n";
                }
                $ops .= 'ET';
            }
            $objects[$page] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents {$content} 0 R /Resources << /Font << /F1 3 0 R >> >> >>";
            $objects[$content] = '<< /Length '.strlen($ops)." >>\nstream\n{$ops}\nendstream";
        }
        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.count($pages).' >>';
        ksort($objects);

        $out = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $n => $body) {
            $offsets[$n] = strlen($out);
            $out .= "{$n} 0 obj\n{$body}\nendobj\n";
        }
        $xref = strlen($out);
        $out .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $off) {
            $out .= sprintf("%010d 00000 n \n", $off);
        }
        $out .= 'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";

        return $out;
    }

    /** Teks panjang berisi kalimat berulang (untuk membuat banyak kata per halaman). */
    public static function prose(int $words, string $seed = 'pelaporan keuangan perusahaan'): string
    {
        $unit = explode(' ', $seed);
        $lines = [];
        $line = [];
        for ($i = 0; $i < $words; $i++) {
            $line[] = $unit[$i % count($unit)];
            if (count($line) === 12) {
                $lines[] = implode(' ', $line);
                $line = [];
            }
        }
        $line && $lines[] = implode(' ', $line);

        return implode("\n", $lines);
    }
}
