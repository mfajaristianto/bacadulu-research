<?php

namespace App\Services\Documents;

use InvalidArgumentException;

/*
 * Mesin tata letak halaman untuk PDF dan pratinjau: dari model blok + layout ke halaman berisi item bertempat (teks, kotak, garis) dalam poin.
 *
 * Pemenggalan baris memakai lebar glyph nyata (PrintFontMetrics), margin cermin per halaman fisik, perataan kiri-kanan, kontrol janda/yatim,
 * keep-with-next untuk heading, awal bab di halaman baru/ganjil (halaman kosong disisipkan), halaman judul, daftar isi dengan nomor halaman dari
 * paginasi nyata (dua lintasan), header/footer dengan token, dan nomor halaman (romawi untuk halaman awal bila diminta). PDF writer dan
 * pratinjau SVG membaca keluaran yang sama, sehingga pratinjau adalah hasil layout sebenarnya, bukan tiruan CSS.
 */
final class PrintLayoutEngine
{
    private array $L;

    private array $doc;

    private array $pages = [];

    private float $W;

    private float $H;

    private float $y = 0.0;

    private string $runningChapter = '';

    /** @var array<int, string> id heading -> label halaman */
    private array $headingPages = [];

    private array $tocLabels = [];

    private int $unlabelledBody = 0;

    /**
     * @return array{pages: list<array>, width: float, height: float}
     */
    public static function run(array $doc, array $layout): array
    {
        // Layout yang lolos validasi selalu punya area teks; layout rusak (mis. data diubah di luar editor) ditolak jelas, bukan membuat perulangan tanpa akhir.
        foreach ([true, false] as $odd) {
            $box = PrintLayout::box($layout, $odd);

            if ($box['width'] < 20 || $box['height'] < 30) {
                throw new InvalidArgumentException('Area teks layout terlalu kecil atau negatif; periksa ukuran halaman dan margin.');
            }
        }

        $engine = new self;
        $first = $engine->layout($doc, $layout, []);
        $second = $engine->layout($doc, $layout, $first['labels']); // daftar isi memakai halaman nyata dari lintasan pertama

        return ['pages' => $second['pages'], 'width' => $second['width'], 'height' => $second['height'], 'toc' => $second['toc']];
    }

    private function layout(array $doc, array $layout, array $labels): array
    {
        $this->L = $layout;
        $this->doc = $doc;
        $this->pages = [];
        $this->headingPages = [];
        $this->runningChapter = '';
        $this->tocLabels = $labels;
        [$wMm, $hMm] = PrintLayout::pageMm($layout);
        $this->W = PrintLayout::pt($wMm);
        $this->H = PrintLayout::pt($hMm);

        $titlePage = $layout['front']['title_page'];
        $toc = $layout['toc']['enabled'];

        if ($titlePage) {
            $this->newPage('title');
            $this->drawTitlePage();
        }

        if ($toc) {
            $this->newPage('front');
            $this->drawToc();
        }

        $hasFront = $titlePage || $toc;
        $startChapter = $layout['headings']['chapter_start'];
        $first = true;
        $blocks = $doc['blocks'];

        if ($blocks === []) {
            $this->newPage('body');
        }

        foreach ($blocks as $i => $b) {
            if ($b['t'] === 'h') {
                $isChapter = $b['level'] === 1 && ! empty($b['chapter']);
                $force = false;
                $odd = false;

                if ($isChapter) {
                    $force = $startChapter !== 'any' || ($first && $hasFront);
                    $odd = $startChapter === 'odd_page';
                } elseif (! empty($b['refs']) && $layout['refs']['new_page']) {
                    $force = true;
                } elseif ($first && $hasFront) {
                    $force = true;
                }

                $this->heading($b, $blocks[$i + 1] ?? null, $force, $odd);
                $first = false;

                continue;
            }

            if ($first) {
                $this->ensureBodyPage();
                $first = false;
            }

            match ($b['t']) {
                'p' => $this->para($b['text']),
                'caption' => $this->caption($b['text'], isset($blocks[$i + 1]) && $blocks[$i + 1]['t'] === 'table'),
                'table' => $this->table($b['rows']),
                'ref' => $this->ref($b['text']),
                'meta' => $this->meta($b['text']),
                default => null,
            };
        }

        $this->decorate();

        $toc = array_map(fn ($h) => $h + ['page' => $this->headingPages[$h['id']] ?? ''], array_values(array_filter($doc['headings'], fn ($h) => $h['level'] <= $layout['toc']['levels'] && ($h['text'] !== 'DOCUMENT PROVENANCE'))));

        return ['pages' => $this->pages, 'width' => $this->W, 'height' => $this->H, 'labels' => $this->headingPages, 'toc' => $toc];
    }

    /*
    |--------------------------------------------------------------------------
    | Halaman dan kotak teks
    |--------------------------------------------------------------------------
    */

    private function newPage(string $kind): void
    {
        $n = count($this->pages) + 1;
        $this->pages[] = ['kind' => $kind, 'n' => $n, 'items' => [], 'chapter' => $this->runningChapter, 'chapterStart' => false, 'headings' => []];
        $this->y = $this->box()['top'];
    }

    /** Kotak teks (pt) untuk halaman aktif; halaman ganjil = sisi kanan buku. */
    private function box(): array
    {
        $n = count($this->pages);
        $b = PrintLayout::box($this->L, $n % 2 === 1);

        return ['left' => PrintLayout::pt($b['left']), 'right' => $this->W - PrintLayout::pt($b['right']), 'top' => PrintLayout::pt($b['top']), 'bottom' => $this->H - PrintLayout::pt($b['bottom']), 'width' => PrintLayout::pt($b['width'])];
    }

    private function ensureBodyPage(): void
    {
        if ($this->pages === [] || end($this->pages)['kind'] !== 'body') {
            $this->newPage('body');
        }
    }

    private function add(array $item): void
    {
        $this->pages[count($this->pages) - 1]['items'][] = $item;
    }

    /*
    |--------------------------------------------------------------------------
    | Font dan teks
    |--------------------------------------------------------------------------
    */

    private function font(string $key, bool $bold = false): string
    {
        return match (PrintLayout::FONTS[$key]['pdf'] ?? 'Times') {
            'Helvetica' => $bold ? 'Helvetica-Bold' : 'Helvetica',
            'Courier' => $bold ? 'Courier-Bold' : 'Courier',
            default => $bold ? 'Times-Bold' : 'Times-Roman',
        };
    }

    public static function encode(string $text): string
    {
        $e = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);

        return $e === false ? (preg_replace('/[^\x20-\x7E]/', '?', $text) ?? '') : $e;
    }

    public static function width(string $text, string $font, float $size): float
    {
        $w = PrintFontMetrics::WIDTHS[$font];
        $sum = 0;

        foreach (str_split(self::encode($text)) as $ch) {
            $sum += $w[ord($ch)] ?? 500;
        }

        return $sum * $size / 1000;
    }

    /** Pemenggalan greedy berdasarkan lebar nyata. Kata yang lebih lebar dari baris dipecah per karakter. */
    private function wrap(string $text, string $font, float $size, float $width, float $firstIndent = 0.0): array
    {
        $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $lines = [];
        $cur = '';
        $limit = $width - $firstIndent;

        foreach ($words as $word) {
            while (self::width($word, $font, $size) > $width - 0.01 && mb_strlen($word) > 1) {
                $cut = mb_strlen($word) - 1;

                while ($cut > 1 && self::width(mb_substr($word, 0, $cut), $font, $size) > $limit) {
                    $cut--;
                }

                if ($cur !== '') {
                    $lines[] = $cur;
                    $cur = '';
                    $limit = $width;
                }

                $lines[] = mb_substr($word, 0, $cut);
                $limit = $width;
                $word = mb_substr($word, $cut);
            }

            $try = $cur === '' ? $word : $cur.' '.$word;

            if ($cur !== '' && self::width($try, $font, $size) > $limit) {
                $lines[] = $cur;
                $cur = $word;
                $limit = $width;
            } else {
                $cur = $try;
            }
        }

        if ($cur !== '') {
            $lines[] = $cur;
        }

        return $lines ?: [''];
    }

    private function lead(float $size, float $spacing = 1.0): float
    {
        return $size * 1.15 * $spacing;
    }

    /*
    |--------------------------------------------------------------------------
    | Blok
    |--------------------------------------------------------------------------
    */

    private function drawTitlePage(): void
    {
        $b = $this->box();
        $font = $this->font($this->L['type']['heading_font'], true);
        $size = max(20.0, min(28.0, $this->L['headings']['h1']['size'] * 1.8));
        $y = $b['top'] + ($b['bottom'] - $b['top']) * 0.28;

        foreach ($this->wrap($this->doc['title'], $font, $size, $b['width']) as $line) {
            $w = self::width($line, $font, $size);
            $this->add(['k' => 'text', 'x' => $b['left'] + ($b['width'] - $w) / 2, 'y' => $y, 'font' => $font, 'size' => $size, 'text' => $line, 'ws' => 0]);
            $y += $size * 1.3;
        }

        $y += 18;
        $body = $this->font($this->L['type']['body_font']);

        foreach (array_filter([$this->doc['subtitle'], $this->doc['author']]) as $line) {
            foreach ($this->wrap($line, $body, $this->L['type']['body_size'], $b['width']) as $l) {
                $w = self::width($l, $body, $this->L['type']['body_size']);
                $this->add(['k' => 'text', 'x' => $b['left'] + ($b['width'] - $w) / 2, 'y' => $y, 'font' => $body, 'size' => $this->L['type']['body_size'], 'text' => $l, 'ws' => 0]);
                $y += $this->L['type']['body_size'] * 1.6;
            }
        }

        $this->y = $b['bottom'];
    }

    private function drawToc(): void
    {
        $t = $this->L['toc'];
        $font = $this->font($this->L['type']['heading_font'], true);
        $size = $this->L['headings']['h1']['size'];
        $b = $this->box();
        $this->y = $b['top'];
        $this->line($t['title'], $font, $size, 'center', 0, 0, $this->L['headings']['h1']['after'] + 6);

        $body = $this->font($this->L['type']['body_font']);
        $bs = $this->L['type']['body_size'];
        $entries = array_filter($this->doc['headings'], fn ($h) => $h['level'] <= $t['levels'] && $h['text'] !== 'DOCUMENT PROVENANCE');

        foreach ($entries as $h) {
            $b = $this->box();
            $indent = ($h['level'] - 1) * PrintLayout::pt(6);
            $label = $t['page_numbers'] ? (string) ($this->tocLabels[$h['id']] ?? '0') : '';
            $lw = $label === '' ? 0 : self::width($label, $body, $bs) + 4;
            $lines = $this->wrap($h['text'], $body, $bs, $b['width'] - $indent - $lw - 14);
            $lead = $this->lead($bs, 1.15);

            foreach ($lines as $k => $line) {
                $this->pageBreakIfNeeded($lead);
                $b = $this->box();
                $x = $b['left'] + $indent;
                $base = $this->baseline($this->y, $lead, $bs);
                $this->add(['k' => 'text', 'x' => $x, 'y' => $base, 'font' => $body, 'size' => $bs, 'text' => $line, 'ws' => 0]);

                if ($k === count($lines) - 1 && $label !== '') {
                    $endX = $b['right'];
                    $this->add(['k' => 'text', 'x' => $endX - self::width($label, $body, $bs), 'y' => $base, 'font' => $body, 'size' => $bs, 'text' => $label, 'ws' => 0]);

                    if ($t['dot_leaders']) {
                        $from = $x + self::width($line, $body, $bs) + 4;
                        $to = $endX - self::width($label, $body, $bs) - 4;
                        $dot = self::width('. ', $body, $bs);
                        $count = (int) floor(($to - $from) / $dot);

                        if ($count > 1) {
                            $this->add(['k' => 'text', 'x' => $to - $count * $dot + 1, 'y' => $base, 'font' => $body, 'size' => $bs, 'text' => rtrim(str_repeat('. ', $count)), 'ws' => 0]);
                        }
                    }
                }

                $this->y += $lead;
            }

            $this->y += 2;
        }
    }

    private function baseline(float $top, float $lead, float $size): float
    {
        return $top + ($lead - $size) / 2 + $size * 0.85;
    }

    private function pageBreakIfNeeded(float $height): void
    {
        $kind = end($this->pages)['kind'] ?? 'body';

        if ($this->y + $height > $this->box()['bottom'] + 0.01) {
            $this->newPage($kind === 'front' ? 'front' : 'body');
        }
    }

    /** Satu baris bebas (judul daftar isi dll). */
    private function line(string $text, string $font, float $size, string $align, float $indent, float $before, float $after): void
    {
        $lead = $this->lead($size);
        $this->pageBreakIfNeeded($lead + $before);
        $b = $this->box();
        $w = self::width($text, $font, $size);
        $x = $align === 'center' ? $b['left'] + ($b['width'] - $w) / 2 : ($align === 'right' ? $b['right'] - $w : $b['left'] + $indent);
        $this->add(['k' => 'text', 'x' => $x, 'y' => $this->baseline($this->y + $before, $lead, $size), 'font' => $font, 'size' => $size, 'text' => $text, 'ws' => 0]);
        $this->y += $before + $lead + $after;
    }

    private function heading(array $b, ?array $next, bool $force, bool $odd): void
    {
        $cfg = $this->L['headings']['h'.$b['level']];
        $font = $this->font($this->L['type']['heading_font'], $cfg['bold']);
        $size = $cfg['size'];
        $lead = $this->lead($size);
        $width = $this->boxWidth();
        $lines = $this->wrap($b['text'], $font, $size, $width);
        $bodyLead = $this->lead($this->L['type']['body_size'], $this->L['para']['line_spacing']);
        $need = count($lines) * $lead + $cfg['after'] + ($cfg['keep_next'] ? $bodyLead * 2 : 0);
        $page = $this->pages === [] ? null : end($this->pages);

        if ($page === null) {
            $this->newPage('body');
        } elseif ($force) {
            if (! ($page['kind'] === 'body' && $page['items'] === [])) {
                $this->newPage('body');
            }
        } elseif ($page['kind'] !== 'body') {
            $this->newPage('body');
        }

        // Bab di halaman ganjil: bila halaman ini genap, jadikan kosong (disengaja untuk cetak buku) dan mulai di halaman berikutnya.
        if ($odd && $force && count($this->pages) % 2 === 0) {
            $this->pages[count($this->pages) - 1]['kind'] = 'blank';
            $this->newPage('body');
        }

        $onFreshPage = end($this->pages)['items'] === [];

        $before = $onFreshPage ? 0.0 : $cfg['before'];

        if (! $onFreshPage && $this->y + $before + $need > $this->box()['bottom']) {
            $this->newPage('body');
            $before = 0.0;
        }

        $this->y += $before;
        $b0 = $this->box();
        $this->pages[count($this->pages) - 1]['headings'][] = $b['id'];
        $this->headingPages[$b['id']] = $this->pageLabelFor(count($this->pages));

        if ($b['level'] === 1 && ! empty($b['chapter'])) {
            $pg = &$this->pages[count($this->pages) - 1];
            $pg['chapterStart'] = true;
            $pg['chapter'] = $b['text'];
            $this->runningChapter = $b['text'];
            unset($pg);
        }

        foreach ($lines as $line) {
            $w = self::width($line, $font, $size);
            $x = $cfg['align'] === 'center' ? $b0['left'] + ($b0['width'] - $w) / 2 : $b0['left'];
            $this->add(['k' => 'text', 'x' => $x, 'y' => $this->baseline($this->y, $lead, $size), 'font' => $font, 'size' => $size, 'text' => $line, 'ws' => 0]);
            $this->y += $lead;
        }

        $this->y += $cfg['after'];
    }

    private function boxWidth(): float
    {
        return PrintLayout::pt(PrintLayout::box($this->L, true)['width']);
    }

    private function para(string $text): void
    {
        $p = $this->L['para'];
        $size = $this->L['type']['body_size'];
        $this->paragraphLines($text, $this->font($this->L['type']['body_font']), $size, $this->lead($size, $p['line_spacing']), $p['align'], PrintLayout::pt($p['indent']), 0.0, $p['before'], $p['after'], $p['widow']);
    }

    private function ref(string $text): void
    {
        $r = $this->L['refs'];
        $size = $this->L['type']['reference_size'];
        $this->paragraphLines($text, $this->font($this->L['type']['reference_font']), $size, $this->lead($size, 1.1), 'left', 0.0, PrintLayout::pt($r['hanging']), 0.0, $r['after'], false, true);
    }

    private function meta(string $text): void
    {
        $this->paragraphLines($text, $this->font('sans'), 8.0, $this->lead(8.0), 'left', 0.0, 0.0, 0.0, 2.0, false);
    }

    private function caption(string $text, bool $keepNext): void
    {
        $size = $this->L['type']['caption_size'];
        $this->paragraphLines($text, $this->font($this->L['type']['body_font']), $size, $this->lead($size, 1.1), 'center', 0.0, 0.0, 4.0, 4.0, false, false, $keepNext ? $this->lead($this->L['type']['body_size']) * 2 : 0.0);
    }

    /**
     * @param  float  $hang  indentasi gantung: baris kedua dst. menjorok $hang, baris pertama tidak (daftar pustaka)
     */
    private function paragraphLines(string $text, string $font, float $size, float $lead, string $align, float $firstIndent, float $hang, float $before, float $after, bool $widow, bool $isRef = false, float $keepNext = 0.0): void
    {
        $this->ensureBodyPage();
        $width = $this->boxWidth();
        $lines = $this->wrapParagraph($text, $font, $size, $width, $firstIndent, $hang);
        $n = count($lines);
        $topNow = abs($this->y - $this->box()['top']) < 0.01;
        $this->y += $topNow ? 0 : $before;
        $i = 0;

        if ($keepNext > 0 && $this->y + $lead * $n + $keepNext > $this->box()['bottom']) {
            $this->newPage('body');
        }

        while ($i < $n) {
            $b = $this->box();
            $fit = (int) floor(($b['bottom'] - $this->y + 0.01) / $lead);
            $left = $n - $i;

            if ($fit < $left && $widow && $left >= 4) {
                if ($fit < 2) {
                    $fit = 0;
                } elseif ($left - $fit < 2) {
                    $fit = $left - 2;
                }
            }

            if ($fit <= 0) {
                // Di puncak halaman baru satu baris tetap harus ditempatkan; tanpa ini baris yang lebih tinggi dari halaman memutar tanpa akhir.
                if (abs($this->y - $b['top']) >= 0.01) {
                    $this->newPage('body');

                    continue;
                }

                $fit = 1;
            }

            $take = min($fit, $left);

            for ($k = 0; $k < $take; $k++, $i++) {
                $b = $this->box();
                $line = $lines[$i];
                $indent = $i === 0 ? $firstIndent : $hang;
                $avail = $b['width'] - $indent;
                $w = self::width($line, $font, $size);
                $x = $b['left'] + $indent;
                $ws = 0.0;
                $last = $i === $n - 1;

                if ($align === 'justify' && ! $last && substr_count($line, ' ') > 0) {
                    $ws = max(0.0, ($avail - $w) / substr_count($line, ' '));
                } elseif ($align === 'center') {
                    $x = $b['left'] + ($b['width'] - $w) / 2;
                } elseif ($align === 'right') {
                    $x = $b['right'] - $w;
                }

                $this->add(['k' => 'text', 'x' => $x, 'y' => $this->baseline($this->y, $lead, $size), 'font' => $font, 'size' => $size, 'text' => $line, 'ws' => round($ws, 3)]);
                $this->y += $lead;
            }

            if ($i < $n) {
                $this->newPage('body');
            }
        }

        $this->y += $after;
    }

    /** Pemenggalan dengan indentasi baris pertama dan gantung (baris pertama lebih sempit oleh firstIndent, sisanya oleh hang). */
    private function wrapParagraph(string $text, string $font, float $size, float $width, float $firstIndent, float $hang): array
    {
        if ($hang > 0) {
            $lines = [];
            $rest = $text;
            $first = $this->wrap($rest, $font, $size, $width, 0.0);
            // baris pertama penuh lebar; sisanya dipenggal ulang dengan lebar berkurang $hang
            $firstLine = $first[0];
            $remaining = trim(mb_substr($text, mb_strlen($firstLine)));
            $lines[] = $firstLine;

            return $remaining === '' ? $lines : array_merge($lines, $this->wrap($remaining, $font, $size, $width - $hang));
        }

        return $this->wrap($text, $font, $size, $width, $firstIndent);
    }

    private function table(array $rows): void
    {
        $this->ensureBodyPage();
        $t = $this->L['tables'];
        $cols = max(array_map('count', $rows));
        $bs = $this->L['type']['body_size'] - 1;
        $pad = $t['padding'];
        $width = $this->boxWidth();
        $cw = $width / $cols;
        $lead = $this->lead($bs, 1.0);
        $this->y += 4;

        foreach ($rows as $ri => $row) {
            $head = $ri === 0 && $t['header_bold'];
            $font = $this->font($this->L['type']['body_font'], $head);
            $cells = [];
            $maxLines = 1;

            for ($c = 0; $c < $cols; $c++) {
                $lines = $this->wrap((string) ($row[$c] ?? ''), $font, $bs, $cw - 2 * $pad);
                $cells[$c] = $lines;
                $maxLines = max($maxLines, count($lines));
            }

            $h = $maxLines * $lead + 2 * $pad;
            $this->pageBreakIfNeeded($h);
            $b = $this->box();
            $x0 = $t['align'] === 'center' ? $b['left'] : $b['left'];

            if ($ri === 0 && $t['header_shade']) {
                $this->add(['k' => 'rect', 'x' => $x0, 'y' => $this->y, 'w' => $width, 'h' => $h, 'fill' => 0.9, 'stroke' => false]);
            }

            foreach ($cells as $c => $lines) {
                foreach ($lines as $li => $line) {
                    $this->add(['k' => 'text', 'x' => $x0 + $c * $cw + $pad, 'y' => $this->baseline($this->y + $pad + $li * $lead, $lead, $bs), 'font' => $font, 'size' => $bs, 'text' => $line, 'ws' => 0]);
                }
            }

            if ($t['border'] === 'grid') {
                $this->add(['k' => 'rect', 'x' => $x0, 'y' => $this->y, 'w' => $width, 'h' => $h, 'fill' => null, 'stroke' => true]);

                for ($c = 1; $c < $cols; $c++) {
                    $this->add(['k' => 'line', 'x1' => $x0 + $c * $cw, 'y1' => $this->y, 'x2' => $x0 + $c * $cw, 'y2' => $this->y + $h, 'w' => 0.5]);
                }
            } elseif ($t['border'] === 'horizontal') {
                if ($ri === 0) {
                    $this->add(['k' => 'line', 'x1' => $x0, 'y1' => $this->y, 'x2' => $x0 + $width, 'y2' => $this->y, 'w' => 0.8]);
                }

                $this->add(['k' => 'line', 'x1' => $x0, 'y1' => $this->y + $h, 'x2' => $x0 + $width, 'y2' => $this->y + $h, 'w' => $ri === count($rows) - 1 || $ri === 0 ? 0.8 : 0.3]);
            }

            $this->y += $h;
        }

        $this->y += 6;
    }

    /*
    |--------------------------------------------------------------------------
    | Nomor halaman, header, footer
    |--------------------------------------------------------------------------
    */

    /** Label halaman untuk halaman fisik ke-$n: dihitung dari urutan halaman bernomor, bukan dikarang. */
    private function pageLabelFor(int $n): string
    {
        return $this->labels($n)[$n] ?? '';
    }

    /** @return array<int, string> nomor fisik -> label ('' = tidak bernomor) */
    private function labels(int $upTo): array
    {
        $pn = $this->L['page_numbers'];
        $out = [];
        $front = 0;
        $body = $pn['start'] - 1;
        $seenBody = false;

        foreach ($this->pages as $idx => $pg) {
            $n = $idx + 1;

            if ($n > $upTo) {
                break;
            }

            $kind = $pg['kind'];

            if ($kind === 'title') {
                $out[$n] = '';

                if (! $pn['front_roman']) {
                    $body++;
                }

                continue;
            }

            if ($kind === 'front' && $pn['front_roman']) {
                $front++;
                $out[$n] = PrintDocumentModel::pageLabel($front, 'lower_roman');

                continue;
            }

            $body++;
            $out[$n] = $kind === 'blank' ? '' : PrintDocumentModel::pageLabel($body, $pn["format"]);
            $seenBody = true;
        }

        unset($seenBody);

        return $out;
    }

    private function decorate(): void
    {
        $labels = $this->labels(count($this->pages));
        $hf = $this->L['header_footer'];
        $pn = $this->L['page_numbers'];
        $font = $this->font($this->L['type']['body_font']);
        $size = max(8.0, min(10.0, $this->L['type']['body_size'] - 2));

        foreach ($this->pages as $idx => &$pg) {
            $n = $idx + 1;
            $odd = $n % 2 === 1;
            $kind = $pg['kind'];

            if ($kind === 'title' || $kind === 'blank') {
                continue;
            }

            $b = PrintLayout::box($this->L, $odd);
            $left = PrintLayout::pt($b['left']);
            $right = $this->W - PrintLayout::pt($b['right']);
            $slots = ['header' => ['left' => '', 'center' => '', 'right' => ''], 'footer' => ['left' => '', 'center' => '', 'right' => '']];
            $label = $labels[$n] ?? '';
            $showHeader = $kind === "body" && ! ($hf["skip_chapter_header"] && $pg["chapterStart"] && $this->L["headings"]["chapter_start"] !== "any");

            foreach (['header', 'footer'] as $zone) {
                if ($zone === 'header' && ! $showHeader) {
                    continue;
                }

                foreach (['left', 'center', 'right'] as $slot) {
                    $text = $hf[$zone][$slot];
                    $target = $hf['odd_even'] && ! $odd ? ['left' => 'right', 'right' => 'left', 'center' => 'center'][$slot] : $slot;
                    $slots[$zone][$target] = $this->tokens($text, $pg, $label);
                }
            }

            if ($pn['position'] !== 'none' && $label !== '') {
                [$zone, $where] = explode('_', $pn['position']);
                $where = $where === 'outside' ? ($odd ? 'right' : 'left') : $where;
                $cur = $slots[$zone][$where];
                $slots[$zone][$where] = $cur === '' ? $label : $cur.'   '.$label;
            }

            foreach (['header', 'footer'] as $zone) {
                foreach (['left', 'center', 'right'] as $slot) {
                    $text = trim($slots[$zone][$slot]);

                    if ($text === '') {
                        continue;
                    }

                    $w = self::width($text, $font, $size);
                    $x = $slot === 'left' ? $left : ($slot === 'right' ? $right - $w : $left + ($right - $left - $w) / 2);
                    $y = $zone === 'header' ? PrintLayout::pt(max(6.0, $b['top'] * 0.5)) + $size * 0.3 : $this->H - PrintLayout::pt(max(6.0, $b['bottom'] * 0.5)) + $size * 0.3;
                    $pg['items'][] = ['k' => 'text', 'x' => $x, 'y' => $y, 'font' => $font, 'size' => $size, 'text' => $text, 'ws' => 0, 'deco' => true];
                }
            }

            $pg['label'] = $label;
        }

        unset($pg);
    }

    private function tokens(string $text, array $page, string $label): string
    {
        return str_replace(['{title}', '{chapter}', '{page}'], [$this->doc['title'], $page['chapter'], $label], $text);
    }
}
