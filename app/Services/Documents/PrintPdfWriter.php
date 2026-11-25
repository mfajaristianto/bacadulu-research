<?php

namespace App\Services\Documents;

/*
 * Penulis PDF untuk keluaran PrintLayoutEngine: PDF 1.4 nyata (bukan DOCX berganti nama), halaman berukuran sesuai layout, font dasar Type1
 * (Helvetica, Times, Courier; tidak disematkan), teks WinAnsi, kotak dan garis tabel, metadata judul. Tidak ada PDF/X, bleed, crop marks, atau CMYK.
 */
final class PrintPdfWriter
{
    private const FONT_ORDER = ['Times-Roman', 'Times-Bold', 'Helvetica', 'Helvetica-Bold', 'Courier', 'Courier-Bold'];

    public static function render(array $laid, string $title): string
    {
        $fonts = [];

        foreach (self::FONT_ORDER as $i => $name) {
            $fonts[$name] = 'F'.($i + 1);
        }

        $objects = [];
        $kids = [];
        $next = 4 + count(self::FONT_ORDER);

        $fontRes = implode(' ', array_map(fn ($name) => '/'.$fonts[$name].' '.(4 + array_search($name, self::FONT_ORDER, true)).' 0 R', array_keys($fonts)));

        foreach ($laid['pages'] as $page) {
            $pageId = $next++;
            $contentId = $next++;
            $stream = self::stream($page, $laid['height'], $fonts);
            $objects[$contentId] = '<< /Length '.strlen($stream)." >>\nstream\n".$stream."\nendstream";
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '.self::n($laid['width']).' '.self::n($laid['height']).'] /Resources << /Font << '.$fontRes.' >> >> /Contents '.$contentId.' 0 R >>';
            $kids[] = $pageId.' 0 R';
        }

        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = '<< /Type /Pages /Count '.count($kids).' /Kids ['.implode(' ', $kids).'] >>';
        $objects[3] = '<< /Title <'.self::utf16hex($title).'> /Producer (BacaDulu Research) /Creator (BacaDulu Research) /CreationDate (D:'.gmdate('YmdHis').'Z) >>';

        foreach (self::FONT_ORDER as $i => $name) {
            $objects[4 + $i] = '<< /Type /Font /Subtype /Type1 /BaseFont /'.$name.' /Encoding /WinAnsiEncoding >>';
        }

        ksort($objects);
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];

        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id." 0 obj\n".$body."\nendobj\n";
        }

        $xref = strlen($pdf);
        $max = max(array_keys($objects));
        $pdf .= "xref\n0 ".($max + 1)."\n0000000000 65535 f \n";

        for ($id = 1; $id <= $max; $id++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id] ?? 0);
        }

        return $pdf.'trailer << /Size '.($max + 1)." /Root 1 0 R /Info 3 0 R >>\nstartxref\n".$xref."\n%%EOF";
    }

    private static function stream(array $page, float $H, array $fonts): string
    {
        $cmd = [];

        foreach ($page['items'] as $it) {
            if ($it['k'] === 'text') {
                $cmd[] = 'BT /'.$fonts[$it['font']].' '.self::n($it['size']).' Tf 1 0 0 1 '.self::n($it['x']).' '.self::n($H - $it['y']).' Tm '.self::n($it['ws'] ?? 0).' Tw ('.self::escape(PrintLayoutEngine::encode($it['text'])).') Tj ET';
            } elseif ($it['k'] === 'rect') {
                $y = $H - $it['y'] - $it['h'];

                if ($it['fill'] !== null) {
                    $cmd[] = self::n($it['fill']).' g '.self::n($it['x']).' '.self::n($y).' '.self::n($it['w']).' '.self::n($it['h']).' re f 0 g';
                }

                if ($it['stroke']) {
                    $cmd[] = '0.5 w 0 G '.self::n($it['x']).' '.self::n($y).' '.self::n($it['w']).' '.self::n($it['h']).' re S';
                }
            } elseif ($it['k'] === 'line') {
                $cmd[] = self::n($it['w']).' w '.self::n($it['x1']).' '.self::n($H - $it['y1']).' m '.self::n($it['x2']).' '.self::n($H - $it['y2']).' l S';
            }
        }

        return implode("\n", $cmd);
    }

    private static function n(float|int $v): string
    {
        return rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.') ?: '0';
    }

    private static function escape(string $s): string
    {
        return str_replace(['\\', '(', ')', "\r"], ['\\\\', '\\(', '\\)', '\\r'], $s);
    }

    private static function utf16hex(string $s): string
    {
        return 'FEFF'.strtoupper(bin2hex((string) mb_convert_encoding($s, 'UTF-16BE', 'UTF-8')));
    }
}
