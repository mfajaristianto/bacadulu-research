<?php

namespace App\Services\Documents;

/*
 * Konfigurasi tata letak cetak (Template & Format Cetak): definisi, satuan, validasi, preset, dan pemeriksaan kesiapan.
 *
 * Aturannya satu tempat. Renderer DOCX, renderer PDF, dan pratinjau tata letak membaca struktur yang sama (hasil normalize()), jadi
 * tidak ada algoritma layout kedua di controller. Semua panjang disimpan dalam milimeter, ukuran huruf dalam poin; satuan cm/inci hanya
 * untuk tampilan dan dikonversi di sini. Kontrol hanya ada bila renderer benar-benar memakainya; SUPPORT mencatat dukungan per renderer.
 */
final class PrintLayout
{
    public const SIZES = [
        'a4' => ['label' => 'A4', 'w' => 210.0, 'h' => 297.0],
        'a5' => ['label' => 'A5', 'w' => 148.0, 'h' => 210.0],
        'b5_iso' => ['label' => 'B5 (ISO)', 'w' => 176.0, 'h' => 250.0],
        'b5_jis' => ['label' => 'B5 (JIS)', 'w' => 182.0, 'h' => 257.0],
        'custom' => ['label' => 'Ukuran kustom', 'w' => null, 'h' => null],
    ];

    public const UNITS = ['mm' => 1.0, 'cm' => 10.0, 'in' => 25.4];

    public const CATEGORIES = ['riset' => 'Dokumen riset', 'artikel' => 'Artikel ilmiah', 'book_chapter' => 'Book Chapter', 'buku' => 'Buku akademik', 'kustom' => 'Format kustom'];

    /** Font yang dapat dipilih. PDF memakai font dasar (tidak disematkan); fallback=true berarti PDF/pratinjau memakai pengganti. */
    public const FONTS = [
        'serif' => ['label' => 'Serif — Times New Roman', 'docx' => 'Times New Roman', 'pdf' => 'Times', 'fallback' => false],
        'sans' => ['label' => 'Sans — Arial', 'docx' => 'Arial', 'pdf' => 'Helvetica', 'fallback' => false],
        'mono' => ['label' => 'Mono — Courier New', 'docx' => 'Courier New', 'pdf' => 'Courier', 'fallback' => false],
        'calibri' => ['label' => 'Calibri (DOCX; PDF memakai Arial)', 'docx' => 'Calibri', 'pdf' => 'Helvetica', 'fallback' => true],
        'cambria' => ['label' => 'Cambria (DOCX; PDF memakai Times)', 'docx' => 'Cambria', 'pdf' => 'Times', 'fallback' => true],
        'georgia' => ['label' => 'Georgia (DOCX; PDF memakai Times)', 'docx' => 'Georgia', 'pdf' => 'Times', 'fallback' => true],
    ];

    public const ALIGN = ['left' => 'Rata kiri', 'justify' => 'Rata kiri-kanan', 'center' => 'Tengah', 'right' => 'Rata kanan'];

    public const NUMBERING = ['none' => 'Tanpa penomoran', 'decimal' => 'Angka (1, 2, 3)', 'multilevel' => 'Bertingkat (1.1, 1.2.1)', 'bab' => 'BAB I, BAB II (subbab 1.1)'];

    public const CHAPTER_START = ['any' => 'Lanjut di halaman yang sama', 'new_page' => 'Halaman baru', 'odd_page' => 'Halaman ganjil (kanan)'];

    public const PAGE_NUMBER_POSITIONS = [
        'none' => 'Tanpa nomor halaman', 'footer_center' => 'Footer tengah', 'footer_right' => 'Footer kanan', 'footer_left' => 'Footer kiri',
        'footer_outside' => 'Footer sisi luar (buku)', 'header_right' => 'Header kanan', 'header_center' => 'Header tengah', 'header_left' => 'Header kiri',
    ];

    public const PAGE_NUMBER_FORMATS = ['arabic' => 'Angka (1, 2, 3)', 'lower_roman' => 'Romawi kecil (i, ii, iii)', 'upper_roman' => 'Romawi besar (I, II, III)'];

    public const TABLE_BORDERS = ['grid' => 'Kisi penuh', 'horizontal' => 'Garis horizontal', 'none' => 'Tanpa garis'];

    public const TOKENS = ['title', 'chapter', 'page'];

    public const LIMITS = [
        'custom_w' => [50, 500], 'custom_h' => [80, 700], 'margin' => [0, 80], 'gutter' => [0, 40], 'content_w' => 50.0, 'content_h' => 80.0,
        'body_size' => [8, 16], 'caption_size' => [7, 14], 'ref_size' => [8, 16], 'heading_size' => [8, 36], 'line' => [1, 3], 'space' => [0, 72],
        'indent' => [0, 30], 'padding' => [0, 10], 'hanging' => [0, 30], 'start' => [1, 9999], 'text' => 120,
    ];

    /** Dukungan nyata per kontrol: [DOCX, PDF/pratinjau, catatan]. Kontrol di luar daftar ini tidak ditawarkan. */
    public const SUPPORT = [
        'Ukuran halaman, orientasi, ukuran kustom' => [true, true, ''],
        'Margin, margin cermin (dalam/luar), gutter' => [true, true, ''],
        'Font dan ukuran (isi, heading, daftar pustaka)' => [true, true, 'PDF memakai font dasar tanpa menyematkan; font non-inti diganti di PDF.'],
        'Spasi baris, spasi paragraf, indentasi, rata teks' => [true, true, ''],
        'Kontrol janda/yatim (widow/orphan)' => [true, true, 'DOCX: pengaturan Word; PDF: minimal 2 baris.'],
        'Heading H1–H3 dan penomoran (BAB I, 1.1)' => [true, true, 'Nomor ditulis sebagai teks, bukan penomoran otomatis Word.'],
        'Header, footer, token {title} {chapter} {page}' => [true, true, 'Token {chapter} memakai field STYLEREF di Word.'],
        'Header/footer ganjil-genap (cermin)' => [true, true, ''],
        'Nomor halaman: posisi, format, awal, romawi halaman awal' => [true, true, ''],
        'Daftar isi dari heading' => [true, true, 'DOCX: field Word yang diisi saat dokumen dibuka/diperbarui; PDF: nomor halaman hasil paginasi nyata.'],
        'Bab mulai halaman baru / halaman ganjil' => [true, true, 'Halaman ganjil menyisipkan halaman kosong bila perlu.'],
        'Halaman judul' => [true, true, ''],
        'Tabel: border, header, padding, perataan' => [true, true, 'Tabel dibaca dari baris bergaya | kolom |.'],
        'Daftar pustaka: heading, indentasi gantung, spasi' => [true, true, 'Isi referensi berasal dari sistem sitasi yang ada.'],
    ];

    /*
    |--------------------------------------------------------------------------
    | Satuan dan ukuran
    |--------------------------------------------------------------------------
    */

    public static function toMm(float $value, string $unit): float
    {
        return round($value * (self::UNITS[$unit] ?? 1.0), 2);
    }

    public static function fromMm(float $mm, string $unit): float
    {
        $v = $mm / (self::UNITS[$unit] ?? 1.0);

        return round($v, $unit === 'in' ? 3 : 2);
    }

    public static function pt(float $mm): float
    {
        return $mm * 72 / 25.4;
    }

    public static function twips(float $mm): int
    {
        return (int) round($mm * 1440 / 25.4);
    }

    /** @return array{0: float, 1: float} lebar dan tinggi halaman (mm) setelah orientasi */
    public static function pageMm(array $layout): array
    {
        $p = $layout['page'];
        $size = self::SIZES[$p['size']] ?? self::SIZES['a4'];
        $w = $p['size'] === 'custom' ? (float) $p['width'] : (float) $size['w'];
        $h = $p['size'] === 'custom' ? (float) $p['height'] : (float) $size['h'];

        return $p['orientation'] === 'landscape' ? [max($w, $h), min($w, $h)] : [min($w, $h), max($w, $h)];
    }

    /**
     * Kotak teks (mm) untuk halaman ganjil (1, 3, …) atau genap. Margin cermin: sisi dalam = inner + gutter di sisi jilid
     * (kiri pada halaman ganjil, kanan pada halaman genap). Tanpa cermin: gutter ditambahkan ke sisi kiri.
     *
     * @return array{left: float, right: float, top: float, bottom: float, width: float, height: float}
     */
    public static function box(array $layout, bool $oddPage): array
    {
        [$w, $h] = self::pageMm($layout);
        $m = $layout['margins'];
        $g = (float) $m['gutter'];

        if ($m['mirror']) {
            $left = $oddPage ? $m['inner'] + $g : $m['outer'];
            $right = $oddPage ? $m['outer'] : $m['inner'] + $g;
        } else {
            $left = $m['left'] + $g;
            $right = $m['right'];
        }

        return ['left' => (float) $left, 'right' => (float) $right, 'top' => (float) $m['top'], 'bottom' => (float) $m['bottom'], 'width' => $w - $left - $right, 'height' => $h - $m['top'] - $m['bottom']];
    }

    /*
    |--------------------------------------------------------------------------
    | Bawaan dan preset
    |--------------------------------------------------------------------------
    */

    public static function defaults(): array
    {
        return [
            'v' => 1,
            'category' => 'riset',
            'unit' => 'mm',
            'page' => ['size' => 'a4', 'width' => 210.0, 'height' => 297.0, 'orientation' => 'portrait'],
            'margins' => ['mirror' => false, 'top' => 25.4, 'bottom' => 25.4, 'left' => 25.4, 'right' => 25.4, 'inner' => 25.4, 'outer' => 25.4, 'gutter' => 0.0],
            'type' => ['body_font' => 'serif', 'body_size' => 12.0, 'heading_font' => 'serif', 'reference_font' => 'serif', 'reference_size' => 11.0, 'caption_size' => 10.0],
            'para' => ['line_spacing' => 1.5, 'before' => 0.0, 'after' => 6.0, 'indent' => 12.7, 'align' => 'justify', 'widow' => true],
            'headings' => [
                'numbering' => 'none', 'chapter_start' => 'any',
                'h1' => ['size' => 14.0, 'bold' => true, 'align' => 'left', 'before' => 18.0, 'after' => 9.0, 'uppercase' => true, 'keep_next' => true],
                'h2' => ['size' => 12.0, 'bold' => true, 'align' => 'left', 'before' => 12.0, 'after' => 6.0, 'uppercase' => false, 'keep_next' => true],
                'h3' => ['size' => 12.0, 'bold' => true, 'align' => 'left', 'before' => 10.0, 'after' => 4.0, 'uppercase' => false, 'keep_next' => true],
            ],
            'header_footer' => [
                'header' => ['left' => '', 'center' => '', 'right' => ''], 'footer' => ['left' => '', 'center' => '', 'right' => ''],
                'odd_even' => false, 'skip_chapter_header' => false,
            ],
            'page_numbers' => ['position' => 'footer_center', 'format' => 'arabic', 'start' => 1, 'front_roman' => false],
            'toc' => ['enabled' => false, 'title' => 'DAFTAR ISI', 'levels' => 2, 'dot_leaders' => true, 'page_numbers' => true],
            'front' => ['title_page' => false],
            'tables' => ['align' => 'center', 'border' => 'grid', 'header_bold' => true, 'header_shade' => true, 'padding' => 3.0],
            'refs' => ['heading' => 'DAFTAR PUSTAKA', 'new_page' => false, 'hanging' => 10.0, 'after' => 4.0],
        ];
    }

    /** @return array<string, array{name: string, description: string, document_type: string, layout: array}> */
    public static function presets(): array
    {
        $d = self::defaults();
        $with = fn (array $over) => array_replace_recursive($d, $over);

        return [
            'riset-a4' => ['name' => 'Riset Akademik A4', 'description' => 'Laporan dan ringkasan riset: A4, margin 25,4 mm, Times 12 pt, spasi 1,5, nomor halaman di footer tengah.', 'document_type' => 'Research Report',
                'layout' => $with(['category' => 'riset'])],
            'artikel-a4' => ['name' => 'Artikel Ilmiah A4', 'description' => 'Artikel jurnal: A4, margin 25 mm, Times 11 pt, spasi 1,15, heading bertingkat, judul berjalan di header.', 'document_type' => 'Research Article',
                'layout' => $with(['category' => 'artikel', 'margins' => ['top' => 25.0, 'bottom' => 25.0, 'left' => 25.0, 'right' => 25.0],
                    'type' => ['body_size' => 11.0, 'reference_size' => 10.0, 'caption_size' => 9.0],
                    'para' => ['line_spacing' => 1.15, 'after' => 6.0, 'indent' => 0.0, 'align' => 'justify'],
                    'headings' => ['numbering' => 'multilevel', 'h1' => ['size' => 13.0, 'uppercase' => false], 'h2' => ['size' => 11.5], 'h3' => ['size' => 11.0]],
                    'header_footer' => ['header' => ['center' => '{title}']], 'page_numbers' => ['position' => 'footer_right']])],
            'book-chapter-a5' => ['name' => 'Book Chapter A5', 'description' => 'Bab buku: A5, margin cermin 18/22 mm + gutter 5 mm, Times 10,5 pt, bab mulai halaman baru, judul bab di header.', 'document_type' => 'Book Chapter',
                'layout' => $with(['category' => 'book_chapter', 'page' => ['size' => 'a5'],
                    'margins' => ['mirror' => true, 'top' => 20.0, 'bottom' => 20.0, 'inner' => 18.0, 'outer' => 22.0, 'gutter' => 5.0],
                    'type' => ['body_size' => 10.5, 'reference_size' => 9.5, 'caption_size' => 9.0],
                    'para' => ['line_spacing' => 1.2, 'after' => 4.0, 'indent' => 8.0, 'align' => 'justify'],
                    'headings' => ['numbering' => 'bab', 'chapter_start' => 'new_page', 'h1' => ['size' => 15.0, 'align' => 'center', 'uppercase' => true, 'before' => 30.0, 'after' => 14.0], 'h2' => ['size' => 11.5], 'h3' => ['size' => 10.5]],
                    'header_footer' => ['header' => ['left' => '{title}', 'right' => '{chapter}'], 'odd_even' => true, 'skip_chapter_header' => true],
                    'page_numbers' => ['position' => 'footer_outside'], 'refs' => ['new_page' => true, 'hanging' => 7.0]])],
            'buku-b5' => ['name' => 'Buku Akademik B5', 'description' => 'Naskah buku: B5 ISO, margin cermin + gutter 8 mm, halaman judul, daftar isi, romawi di halaman awal, bab mulai halaman ganjil.', 'document_type' => 'Book Chapter',
                'layout' => $with(['category' => 'buku', 'page' => ['size' => 'b5_iso'],
                    'margins' => ['mirror' => true, 'top' => 22.0, 'bottom' => 22.0, 'inner' => 20.0, 'outer' => 22.0, 'gutter' => 8.0],
                    'type' => ['body_size' => 11.0, 'reference_size' => 10.0, 'caption_size' => 9.0],
                    'para' => ['line_spacing' => 1.25, 'after' => 4.0, 'indent' => 8.0, 'align' => 'justify'],
                    'headings' => ['numbering' => 'bab', 'chapter_start' => 'odd_page', 'h1' => ['size' => 16.0, 'align' => 'center', 'before' => 36.0, 'after' => 16.0], 'h2' => ['size' => 12.0], 'h3' => ['size' => 11.0]],
                    'header_footer' => ['header' => ['left' => '{title}', 'right' => '{chapter}'], 'odd_even' => true, 'skip_chapter_header' => true],
                    'page_numbers' => ['position' => 'footer_outside', 'front_roman' => true, 'format' => 'arabic'],
                    'toc' => ['enabled' => true, 'levels' => 2], 'front' => ['title_page' => true], 'refs' => ['new_page' => true, 'hanging' => 7.0]])],
            'kustom-penerbit' => ['name' => 'Format Kustom Penerbit', 'description' => 'Titik mulai untuk persyaratan penerbit: ukuran kustom 155 × 230 mm. Sesuaikan dengan pedoman penerbit atau percetakan.', 'document_type' => 'Research Report',
                'layout' => $with(['category' => 'kustom', 'page' => ['size' => 'custom', 'width' => 155.0, 'height' => 230.0],
                    'margins' => ['top' => 20.0, 'bottom' => 20.0, 'left' => 20.0, 'right' => 18.0, 'gutter' => 4.0],
                    'type' => ['body_size' => 11.0], 'para' => ['line_spacing' => 1.3, 'indent' => 8.0]])],
        ];
    }

    /** Konversi format lama (schema_json.format) ke layout, agar template lama dapat mulai diatur tanpa kehilangan nilai. */
    public static function fromLegacy(array $format): array
    {
        $l = self::defaults();
        $num = fn ($v, $d) => is_numeric($v) ? (float) $v : $d;
        $l['type']['body_size'] = $num($format['body_font_size'] ?? null, 12.0);
        $l['type']['reference_size'] = $num($format['reference_font_size'] ?? null, 11.0);
        $l['type']['caption_size'] = $num($format['figure_font_size'] ?? null, 10.0);
        $l['para']['line_spacing'] = $num($format['line_spacing'] ?? null, 1.5);
        $l['para']['indent'] = round($num($format['first_line_indent_cm'] ?? null, 0.5) * 10, 2);
        $l['headings']['h1']['size'] = $num($format['heading_font_size'] ?? null, 12.0);
        $l['headings']['h1']['uppercase'] = (bool) ($format['heading_uppercase'] ?? true);

        return $l;
    }

    /*
    |--------------------------------------------------------------------------
    | Normalisasi dan validasi
    |--------------------------------------------------------------------------
    */

    /**
     * Masukan form -> layout bersih. Nilai di luar daftar tetap/batas ditolak dengan galat per kolom (kunci bertitik, relatif ke layout);
     * tidak ada teks bebas selain teks header/footer/judul yang dibatasi karakter dan token.
     *
     * @return array{layout: array, errors: array<string, string>}
     */
    public static function normalize(array $in): array
    {
        $e = [];
        $d = self::defaults();
        $L = self::LIMITS;
        $unit = isset(self::UNITS[$in['unit'] ?? '']) ? $in['unit'] : 'mm';
        $f = fn ($v) => is_numeric(str_replace(',', '.', (string) $v)) ? (float) str_replace(',', '.', (string) $v) : null;
        $bool = fn ($v) => filter_var($v, FILTER_VALIDATE_BOOLEAN);

        $num = function (string $path, mixed $raw, float $default, array $range, string $label, bool $length = false) use (&$e, $f, $unit): float {
            if ($raw === null || $raw === '') {
                return $default;
            }

            $v = $f($raw);

            if ($v === null) {
                $e[$path] = $label.' harus berupa angka.';

                return $default;
            }

            $v = $length ? self::toMm($v, $unit) : $v;

            if ($v < $range[0] || $v > $range[1]) {
                $e[$path] = $label.' harus antara '.self::show($range[0], $length, $unit).' dan '.self::show($range[1], $length, $unit).'.';
            }

            return $v;
        };

        $pick = function (string $path, mixed $raw, array $allowed, string $default, string $label) use (&$e): string {
            if ($raw === null || $raw === '') {
                return $default;
            }

            if (! is_string($raw) || ! array_key_exists($raw, $allowed) && ! in_array($raw, $allowed, true)) {
                $e[$path] = $label.' tidak dikenal.';

                return $default;
            }

            return $raw;
        };

        $text = function (string $path, mixed $raw, string $label) use (&$e, $L): string {
            $t = trim((string) preg_replace('/[\x00-\x1F\x7F]/u', '', (string) $raw));

            if (mb_strlen($t) > $L['text']) {
                $e[$path] = $label.' maksimal '.$L['text'].' karakter.';
            }

            foreach (self::tokensIn($t) as $tok) {
                if (! in_array($tok, self::TOKENS, true)) {
                    $e[$path] = 'Token {'.$tok.'} tidak dikenal. Yang tersedia: {'.implode('}, {', self::TOKENS).'}.';
                }
            }

            return $t;
        };

        $p = $in['page'] ?? [];
        $size = $pick('page.size', $p['size'] ?? 'a4', self::SIZES, 'a4', 'Ukuran halaman');
        $orientation = $pick('page.orientation', $p['orientation'] ?? 'portrait', ['portrait', 'landscape'], 'portrait', 'Orientasi');
        $w = $d['page']['width'];
        $h = $d['page']['height'];

        if ($size === 'custom') {
            $w = $num('page.width', $p['width'] ?? null, 155.0, $L['custom_w'], 'Lebar halaman', true);
            $h = $num('page.height', $p['height'] ?? null, 230.0, $L['custom_h'], 'Tinggi halaman', true);
        }

        $m = $in['margins'] ?? [];
        $mirror = $bool($m['mirror'] ?? false);
        $margins = ['mirror' => $mirror];

        foreach (['top' => 'Margin atas', 'bottom' => 'Margin bawah', 'left' => 'Margin kiri', 'right' => 'Margin kanan', 'inner' => 'Margin dalam', 'outer' => 'Margin luar'] as $k => $label) {
            $margins[$k] = $num("margins.$k", $m[$k] ?? null, $d['margins'][$k], $L['margin'], $label, true);
        }

        $margins['gutter'] = $num('margins.gutter', $m['gutter'] ?? null, 0.0, $L['gutter'], 'Gutter', true);

        $layout = $d;
        $layout['v'] = 1;
        $layout['unit'] = $unit;
        $layout['category'] = $pick('category', $in['category'] ?? 'riset', self::CATEGORIES, 'riset', 'Kategori format');
        $layout['page'] = ['size' => $size, 'width' => $w, 'height' => $h, 'orientation' => $orientation];
        $layout['margins'] = $margins;

        // Kotak teks harus positif dan layak (diperiksa pada halaman ganjil dan genap).
        foreach ([true, false] as $odd) {
            $box = self::box($layout, $odd);

            if ($box['width'] < $L['content_w'] || $box['height'] < $L['content_h']) {
                $key = $box['width'] < $L['content_w'] ? ($mirror ? 'margins.inner' : 'margins.left') : 'margins.top';
                $e[$key] ??= 'Area teks terlalu kecil ('.round($box['width'], 1).' × '.round($box['height'], 1).' mm; minimal '.$L['content_w'].' × '.$L['content_h'].' mm). Kurangi margin atau gutter.';
            }
        }

        $t = $in['type'] ?? [];
        $layout['type'] = [
            'body_font' => $pick('type.body_font', $t['body_font'] ?? null, self::FONTS, 'serif', 'Font isi'),
            'body_size' => $num('type.body_size', $t['body_size'] ?? null, 12.0, $L['body_size'], 'Ukuran huruf isi'),
            'heading_font' => $pick('type.heading_font', $t['heading_font'] ?? null, self::FONTS, 'serif', 'Font heading'),
            'reference_font' => $pick('type.reference_font', $t['reference_font'] ?? null, self::FONTS, 'serif', 'Font daftar pustaka'),
            'reference_size' => $num('type.reference_size', $t['reference_size'] ?? null, 11.0, $L['ref_size'], 'Ukuran huruf daftar pustaka'),
            'caption_size' => $num('type.caption_size', $t['caption_size'] ?? null, 10.0, $L['caption_size'], 'Ukuran huruf keterangan'),
        ];

        $q = $in['para'] ?? [];
        $layout['para'] = [
            'line_spacing' => $num('para.line_spacing', $q['line_spacing'] ?? null, 1.5, $L['line'], 'Spasi baris'),
            'before' => $num('para.before', $q['before'] ?? null, 0.0, $L['space'], 'Spasi sebelum paragraf'),
            'after' => $num('para.after', $q['after'] ?? null, 6.0, $L['space'], 'Spasi sesudah paragraf'),
            'indent' => $num('para.indent', $q['indent'] ?? null, 12.7, $L['indent'], 'Indentasi baris pertama', true),
            'align' => $pick('para.align', $q['align'] ?? null, self::ALIGN, 'justify', 'Perataan'),
            'widow' => $bool($q['widow'] ?? false),
        ];

        $hd = $in['headings'] ?? [];
        $layout['headings'] = [
            'numbering' => $pick('headings.numbering', $hd['numbering'] ?? null, self::NUMBERING, 'none', 'Penomoran heading'),
            'chapter_start' => $pick('headings.chapter_start', $hd['chapter_start'] ?? null, self::CHAPTER_START, 'any', 'Awal bab'),
        ];

        foreach (['h1' => 'Heading 1', 'h2' => 'Heading 2', 'h3' => 'Heading 3'] as $k => $label) {
            $x = $hd[$k] ?? [];
            $layout['headings'][$k] = [
                'size' => $num("headings.$k.size", $x['size'] ?? null, $d['headings'][$k]['size'], $L['heading_size'], 'Ukuran '.$label),
                'bold' => $bool($x['bold'] ?? false),
                'align' => $pick("headings.$k.align", $x['align'] ?? null, ['left' => 1, 'center' => 1], 'left', 'Perataan '.$label),
                'before' => $num("headings.$k.before", $x['before'] ?? null, 12.0, $L['space'], 'Spasi sebelum '.$label),
                'after' => $num("headings.$k.after", $x['after'] ?? null, 6.0, $L['space'], 'Spasi sesudah '.$label),
                'uppercase' => $bool($x['uppercase'] ?? false),
                'keep_next' => $bool($x['keep_next'] ?? false),
            ];
        }

        $hf = $in['header_footer'] ?? [];
        $layout['header_footer'] = ['odd_even' => $bool($hf['odd_even'] ?? false), 'skip_chapter_header' => $bool($hf['skip_chapter_header'] ?? false)];

        foreach (['header' => 'Header', 'footer' => 'Footer'] as $zone => $label) {
            foreach (['left' => 'kiri', 'center' => 'tengah', 'right' => 'kanan'] as $slot => $sl) {
                $layout['header_footer'][$zone][$slot] = $text("header_footer.$zone.$slot", $hf[$zone][$slot] ?? '', $label.' '.$sl);
            }
        }

        $pn = $in['page_numbers'] ?? [];
        $layout['page_numbers'] = [
            'position' => $pick('page_numbers.position', $pn['position'] ?? null, self::PAGE_NUMBER_POSITIONS, 'footer_center', 'Posisi nomor halaman'),
            'format' => $pick('page_numbers.format', $pn['format'] ?? null, self::PAGE_NUMBER_FORMATS, 'arabic', 'Format nomor halaman'),
            'start' => (int) $num('page_numbers.start', $pn['start'] ?? null, 1.0, $L['start'], 'Nomor awal'),
            'front_roman' => $bool($pn['front_roman'] ?? false),
        ];

        $tc = $in['toc'] ?? [];
        $layout['toc'] = [
            'enabled' => $bool($tc['enabled'] ?? false),
            'title' => $text('toc.title', $tc['title'] ?? 'DAFTAR ISI', 'Judul daftar isi'),
            'levels' => (int) $num('toc.levels', $tc['levels'] ?? null, 2.0, [1, 3], 'Level heading daftar isi'),
            'dot_leaders' => $bool($tc['dot_leaders'] ?? false),
            'page_numbers' => $bool($tc['page_numbers'] ?? false),
        ];

        if ($layout['toc']['enabled'] && $layout['toc']['title'] === '') {
            $e['toc.title'] = 'Judul daftar isi wajib diisi bila daftar isi aktif.';
        }

        $layout['front'] = ['title_page' => $bool(($in['front'] ?? [])['title_page'] ?? false)];

        $tb = $in['tables'] ?? [];
        $layout['tables'] = [
            'align' => $pick('tables.align', $tb['align'] ?? null, ['left' => 1, 'center' => 1], 'center', 'Perataan tabel'),
            'border' => $pick('tables.border', $tb['border'] ?? null, self::TABLE_BORDERS, 'grid', 'Garis tabel'),
            'header_bold' => $bool($tb['header_bold'] ?? false),
            'header_shade' => $bool($tb['header_shade'] ?? false),
            'padding' => $num('tables.padding', $tb['padding'] ?? null, 3.0, $L['padding'], 'Padding sel'),
        ];

        $rf = $in['refs'] ?? [];
        $layout['refs'] = [
            'heading' => $text('refs.heading', $rf['heading'] ?? 'DAFTAR PUSTAKA', 'Judul daftar pustaka'),
            'new_page' => $bool($rf['new_page'] ?? false),
            'hanging' => $num('refs.hanging', $rf['hanging'] ?? null, 10.0, $L['hanging'], 'Indentasi gantung', true),
            'after' => $num('refs.after', $rf['after'] ?? null, 4.0, $L['space'], 'Spasi antar referensi'),
        ];

        if ($layout['refs']['heading'] === '') {
            $e['refs.heading'] = 'Judul daftar pustaka wajib diisi.';
        }

        return ['layout' => $layout, 'errors' => $e];
    }

    /** Layout tersimpan (mungkin dari versi lama skema) -> layout lengkap dengan nilai bawaan. Tidak melempar. */
    public static function hydrate(?array $stored): array
    {
        return array_replace_recursive(self::defaults(), is_array($stored) ? $stored : []);
    }

    public static function tokensIn(string $text): array
    {
        preg_match_all('/\{([A-Za-z_]+)\}/', $text, $m);

        return array_values(array_unique($m[1]));
    }

    private static function show(float|int $mm, bool $length, string $unit): string
    {
        if (! $length) {
            return (string) $mm;
        }

        $v = self::fromMm((float) $mm, $unit);

        return (abs($v - round($v)) < 1e-9 ? (string) (int) round($v) : (string) $v).' '.$unit;
    }

    /*
    |--------------------------------------------------------------------------
    | Pemeriksaan kesiapan
    |--------------------------------------------------------------------------
    */

    public const READY = 'ready';

    public const REVIEW = 'review';

    public const BLOCKED = 'blocked';

    /**
     * Pemeriksaan teknis sebelum ekspor. Hanya menilai tata letak dan kemampuan renderer, bukan mutu akademik; "siap diekspor" bukan
     * "siap cetak profesional" (itu butuh standar penerbit/percetakan yang diverifikasi di luar sistem ini).
     *
     * @return array{status: string, label: string, items: list<array{level: string, text: string}>}
     */
    public static function check(array $layout): array
    {
        $items = [];
        $add = function (string $level, string $text) use (&$items): void {
            $items[] = ['level' => $level, 'text' => $text];
        };

        [$w, $h] = self::pageMm($layout);
        $std = $layout['page']['size'] !== 'custom';
        $add('ok', 'Ukuran halaman '.($std ? self::SIZES[$layout['page']['size']]['label'] : 'kustom').' = '.round($w, 1).' × '.round($h, 1).' mm ('.($layout['page']['orientation'] === 'portrait' ? 'potret' : 'lanskap').').');

        foreach ([true, false] as $odd) {
            $box = self::box($layout, $odd);

            if ($box['width'] < self::LIMITS['content_w'] || $box['height'] < self::LIMITS['content_h']) {
                $add('error', 'Area teks halaman '.($odd ? 'ganjil' : 'genap').' terlalu kecil: '.round($box['width'], 1).' × '.round($box['height'], 1).' mm.');
            }
        }

        $box = self::box($layout, true);
        $add('ok', 'Area teks '.round($box['width'], 1).' × '.round($box['height'], 1).' mm (halaman ganjil)'.($layout['margins']['mirror'] ? '; margin cermin aktif.' : '.'));

        $hf = $layout['header_footer'];
        $usesHeader = array_filter($hf['header']) !== [] || str_starts_with($layout['page_numbers']['position'], 'header');
        $usesFooter = array_filter($hf['footer']) !== [] || str_starts_with($layout['page_numbers']['position'], 'footer');

        if ($usesHeader && $layout['margins']['top'] < 15) {
            $add('warn', 'Margin atas '.$layout['margins']['top'].' mm terlalu sempit untuk header; header akan terlalu dekat ke tepi kertas.');
        }

        if ($usesFooter && $layout['margins']['bottom'] < 15) {
            $add('warn', 'Margin bawah '.$layout['margins']['bottom'].' mm terlalu sempit untuk footer/nomor halaman.');
        }

        $fonts = array_unique([$layout['type']['body_font'], $layout['type']['heading_font'], $layout['type']['reference_font']]);

        foreach ($fonts as $key) {
            if (self::FONTS[$key]['fallback'] ?? false) {
                $add('warn', 'Font '.self::FONTS[$key]['docx'].' dipakai di DOCX, tetapi PDF dan pratinjau memakai '.self::FONTS[$key]['pdf'].'. Lebar teks dapat berbeda antar keluaran.');
            }
        }

        $add('ok', 'Font dasar ('.implode(', ', array_map(fn ($k) => self::FONTS[$k]['docx'], $fonts)).') ada di sistem umum; PDF tidak menyematkan font dan Word memakai font di komputer pembaca.');

        if ($layout['toc']['enabled']) {
            $add('warn', 'Daftar isi DOCX adalah field Word: isi dan nomor halamannya dibuat saat dokumen dibuka dan diperbarui di Word (Word dapat meminta konfirmasi). PDF memakai nomor halaman hasil paginasi nyata.');
        }

        if ($layout['headings']['numbering'] !== 'none') {
            $add('ok', 'Penomoran heading dibuat oleh sistem; nomor manual di awal judul dari Prompt Dokumen dibuang agar tidak ganda.');
        }

        if ($layout['page_numbers']['front_roman'] && ! $layout['toc']['enabled'] && ! $layout['front']['title_page']) {
            $add('warn', 'Romawi untuk halaman awal aktif, tetapi tidak ada halaman judul atau daftar isi: halaman awal tidak ada, jadi pengaturan ini tidak berpengaruh.');
        }

        if ($layout['page_numbers']['position'] === 'none') {
            $add('warn', 'Nomor halaman nonaktif.');
        }

        if ($layout['category'] === 'buku') {
            $add('warn', 'Kategori buku akademik: generator saat ini menghasilkan satu naskah per dokumen. Penggabungan beberapa bab menjadi satu buku belum tersedia. Halaman hak cipta, ISBN, dan identitas penerbit tidak dibuat otomatis.');
        }

        if ($layout['headings']['chapter_start'] === 'odd_page') {
            $add('ok', 'Bab dimulai di halaman ganjil; halaman kosong disisipkan bila perlu (disengaja untuk cetak buku).');
        }

        $add('ok', 'Gambar belum ada di konten dokumen yang dihasilkan, jadi tidak ada pengaturan gambar. Tabel dibaca dari baris bergaya | kolom |.');
        $add('ok', 'Keluaran: DOCX dan PDF akademik. PDF/X, bleed, crop marks, dan CMYK tidak didukung.');

        $levels = array_column($items, 'level');
        $status = in_array('error', $levels, true) ? self::BLOCKED : (in_array('warn', $levels, true) ? self::REVIEW : self::READY);

        return ['status' => $status, 'label' => ['ready' => 'SIAP DIEKSPOR', 'review' => 'PERLU DIPERIKSA', 'blocked' => 'TIDAK DAPAT DIEKSPOR'][$status], 'items' => $items];
    }
}
