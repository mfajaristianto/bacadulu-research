<?php

namespace App\Services\Documents;

/*
 * Pratinjau tata letak: halaman hasil PrintLayoutEngine (engine yang sama dengan PDF) digambar sebagai SVG. Bukan tiruan CSS: posisi baris, pemenggalan,
 * margin cermin, header/footer, dan nomor halaman identik dengan PDF. Yang tidak dijamin identik adalah DOCX (dirender Word) dan font di komputer pembaca.
 * Semua teks di-escape; tidak ada HTML dari konten yang dirender mentah.
 */
final class PrintPreview
{
    /** @return array{html: string, pages: int, width_mm: float, height_mm: float} */
    public static function render(array $laid, array $layout, array $only = []): array
    {
        $W = $laid['width'];
        $H = $laid['height'];
        $out = '';
        $shown = 0;

        foreach ($laid['pages'] as $i => $page) {
            $n = $i + 1;

            if ($only !== [] && ! in_array($page['kind'], $only, true)) {
                continue;
            }

            $odd = $n % 2 === 1;
            $b = PrintLayout::box($layout, $odd);
            $shown++;
            $svg = '<svg viewBox="0 0 '.self::f($W).' '.self::f($H).'" role="img" aria-label="Halaman '.$n.'" xmlns="http://www.w3.org/2000/svg">'
                .'<rect width="'.self::f($W).'" height="'.self::f($H).'" fill="#fff"/>'
                .'<rect class="pf-guide" x="'.self::f(PrintLayout::pt($b['left'])).'" y="'.self::f(PrintLayout::pt($b['top'])).'" width="'.self::f(PrintLayout::pt($b['width'])).'" height="'.self::f(PrintLayout::pt($b['height'])).'" fill="none" stroke="#C9B9A0" stroke-width=".6" stroke-dasharray="3 3"/>';

            foreach ($page['items'] as $it) {
                if ($it['k'] === 'text') {
                    $svg .= '<text x="'.self::f($it['x']).'" y="'.self::f($it['y']).'" font-size="'.self::f($it['size']).'" style="'.self::style($it['font']).'"'
                        .(($it['ws'] ?? 0) > 0 ? ' word-spacing="'.self::f($it['ws']).'"' : '').' xml:space="preserve">'.htmlspecialchars($it['text'], ENT_XML1 | ENT_QUOTES, 'UTF-8').'</text>';
                } elseif ($it['k'] === 'rect') {
                    $svg .= '<rect x="'.self::f($it['x']).'" y="'.self::f($it['y']).'" width="'.self::f($it['w']).'" height="'.self::f($it['h']).'" fill="'.($it['fill'] !== null ? 'rgb('.round($it['fill'] * 255).','.round($it['fill'] * 255).','.round($it['fill'] * 255).')' : 'none').'" stroke="'.($it['stroke'] ? '#000' : 'none').'" stroke-width=".5"/>';
                } elseif ($it['k'] === 'line') {
                    $svg .= '<line x1="'.self::f($it['x1']).'" y1="'.self::f($it['y1']).'" x2="'.self::f($it['x2']).'" y2="'.self::f($it['y2']).'" stroke="#000" stroke-width="'.self::f($it['w']).'"/>';
                }
            }

            $svg .= '</svg>';
            $caption = match ($page['kind']) {
                'title' => 'Halaman judul', 'front' => 'Halaman awal', 'blank' => 'Halaman kosong (bab di halaman ganjil)', default => 'Halaman isi',
            };
            $out .= '<figure class="pf-page" data-kind="'.$page['kind'].'">'.$svg.'<figcaption>'.$caption.' · halaman fisik '.$n.(($page['label'] ?? '') !== '' ? ' · nomor '.htmlspecialchars((string) $page['label'], ENT_QUOTES, 'UTF-8') : '').'</figcaption></figure>';
        }

        [$wMm, $hMm] = PrintLayout::pageMm($layout);

        return ['html' => $out, 'pages' => $shown, 'width_mm' => $wMm, 'height_mm' => $hMm];
    }

    private static function style(string $font): string
    {
        $bold = str_ends_with($font, 'Bold') ? 'font-weight:700;' : '';
        $family = str_starts_with($font, 'Helvetica') ? "Helvetica,Arial,'Liberation Sans',sans-serif" : (str_starts_with($font, 'Courier') ? "'Courier New',Courier,'Liberation Mono',monospace" : "'Times New Roman',Times,'Liberation Serif',serif");

        return 'font-family:'.$family.';'.$bold;
    }

    private static function f(float|int $v): string
    {
        return rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.') ?: '0';
    }
}
