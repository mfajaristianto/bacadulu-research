<?php

namespace App\Services\FullText;

/**
 * Membagi teks artikel menjadi blok (halaman x bagian). Bagian HANYA diberi bila sebuah baris berdiri sendiri dan persis sama dengan
 * heading baku (Inggris/Indonesia). Tidak ada bagian yang ditebak: tanpa heading yang dikenali, section = null.
 * Teks setelah heading Referensi/Daftar Pustaka ditandai `refs` agar tidak diperlakukan sebagai hasil penelitian artikel.
 *
 * Batasan jujur: heading yang ditulis tidak baku, digabung dengan teks di baris yang sama, atau terpecah dua baris tidak dikenali;
 * baris tunggal bertuliskan persis "References" di tengah isi dianggap heading.
 */
final class SectionSegmenter
{
    public const SECTIONS = [
        'abstract' => ['abstract', 'abstrak'],
        'introduction' => ['introduction', 'pendahuluan'],
        'literature_review' => ['literature review', 'review of literature', 'related work', 'theoretical framework', 'tinjauan pustaka', 'kajian pustaka', 'kajian teori', 'landasan teori', 'tinjauan literatur'],
        'methodology' => ['methodology', 'methods', 'method', 'research method', 'research methods', 'research methodology', 'materials and methods', 'metode', 'metodologi', 'metode penelitian', 'metodologi penelitian'],
        'results' => ['results', 'findings', 'hasil', 'hasil penelitian', 'temuan'],
        'results_discussion' => ['results and discussion', 'findings and discussion', 'hasil dan pembahasan', 'hasil penelitian dan pembahasan'],
        'discussion' => ['discussion', 'pembahasan'],
        'conclusion' => ['conclusion', 'conclusions', 'conclusion and recommendations', 'concluding remarks', 'kesimpulan', 'simpulan', 'kesimpulan dan saran', 'penutup'],
        'references' => ['references', 'reference list', 'bibliography', 'daftar pustaka', 'daftar referensi', 'referensi'],
    ];

    public static function canonical(string $heading): ?string
    {
        $l = trim((string) preg_replace('/\s+/u', ' ', $heading));
        if ($l === '' || mb_strlen($l) > 70) {
            return null;
        }
        // Nomor bagian di depan ("1.", "2.1", "III.") dibuang.
        $l = (string) preg_replace('/^(?:\d{1,2}(?:\.\d{1,2})*|[IVX]{1,5})[.)]?\s+/u', '', $l);
        $key = mb_strtolower(rtrim($l, " .:\t"));

        foreach (self::SECTIONS as $section => $names) {
            if (in_array($key, $names, true)) {
                return $section;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $pages  indeks 0 = halaman 1 (halaman kosong tetap ada agar nomor halaman benar)
     * @return list<array{page: int, section: ?string, refs: bool, text: string}>
     */
    public static function fromPages(array $pages): array
    {
        $blocks = [];
        $section = null;
        $refs = false;

        foreach ($pages as $i => $text) {
            $lines = [];
            $flush = function () use (&$lines, &$blocks, &$section, &$refs, $i): void {
                $text = trim(implode(' ', $lines));
                if ($text !== '') {
                    $blocks[] = ['page' => $i + 1, 'section' => $section, 'refs' => $refs, 'text' => $text];
                }
                $lines = [];
            };

            foreach (preg_split('/\R/u', (string) $text) ?: [] as $raw) {
                $line = trim($raw);
                if ($line === '') {
                    continue;
                }
                if (($found = self::canonical($line)) !== null) {
                    $flush();
                    $section = $found;
                    $refs = $found === 'references';

                    continue;
                }
                // Heading bernomor yang TIDAK dikenali ("2 Data and sample") mengakhiri bagian sebelumnya: label lama tidak boleh
                // menempel ke bagian yang jelas berbeda. Baris tabel (spasi lebar), kalimat bertitik, dan teks dalam referensi diabaikan.
                if (! $refs && ! preg_match('/\s{3,}/', $raw) && self::looksLikeNumberedHeading($line)) {
                    $flush();
                    $section = null;

                    continue;
                }
                $lines[] = $line;
            }
            $flush();
        }

        return $blocks;
    }

    /** "2 Data and sample", "3.1. Hypothesis development": nomor + judul pendek berhuruf kapital, tanpa titik/koma/angka lain. */
    private static function looksLikeNumberedHeading(string $line): bool
    {
        return mb_strlen($line) <= 80
            && count(preg_split('/\s+/u', $line, -1, PREG_SPLIT_NO_EMPTY) ?: []) <= 10
            && preg_match('/^\d{1,2}(?:\.\d{1,2})*\.?[\s\x{2002}\x{00A0}]+\p{Lu}[^.,;:\d]{2,70}$/u', $line) === 1;
    }
}
