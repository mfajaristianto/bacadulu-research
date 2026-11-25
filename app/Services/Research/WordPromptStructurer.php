<?php

namespace App\Services\Research;

use Illuminate\Support\Str;

/*
 * Mengubah blok hasil WordDocumentReader menjadi USULAN template prompt: bagian, instruksi, batas kata, jumlah butir, sitasi.
 * Deterministik dan lokal (tanpa AI, tanpa jaringan). Hasilnya selalu berupa usulan untuk ditinjau admin: setiap bagian diberi
 * tingkat keyakinan, dan teks yang tidak masuk bagian mana pun dilaporkan, bukan dibuang diam-diam.
 *
 * Redaksi asli dipertahankan: isi prompt = teks blok apa adanya (butir daftar diberi penanda "-", "1.", dst.). Tidak ada ringkasan,
 * parafrase, atau penambahan instruksi. Bagian yang tidak ada di dokumen tidak diciptakan.
 *
 * Urutan strategi (yang pertama menghasilkan >= 2 bagian dipakai):
 *   heading  : heading Word pada tingkat terdalam-terkecil yang muncul >= 2 kali
 *   numbered : butir daftar bernomor/berhuruf tingkat 0 (bukan bullet), judul diambil dari awal kalimat
 *   pattern  : paragraf bergaya judul ("1. Pendahuluan", "BAB II", baris tebal pendek)
 *   single   : seluruh dokumen = satu prompt
 */
class WordPromptStructurer
{
    public const MAX_SECTIONS = 60;

    private const NUMBER_WORDS = ['satu' => 1, 'dua' => 2, 'tiga' => 3, 'empat' => 4, 'lima' => 5, 'enam' => 6, 'tujuh' => 7, 'delapan' => 8, 'sembilan' => 9, 'sepuluh' => 10];

    public function __construct(private readonly DocumentPrompts $docs) {}

    /**
     * @param  array{blocks: list<array<string, mixed>>, warnings: list<string>, stats: array<string, int>}  $read
     * @return array<string, mixed>
     */
    public function structure(array $read, string $filename): array
    {
        $blocks = $read['blocks'];
        $warnings = $read['warnings'];
        $full = implode("\n", array_column($blocks, 'text'));

        [$strategy, $starts] = $this->detectStarts($blocks);
        $unassigned = [];
        $sections = $strategy === 'single' ? [$this->single($blocks, $filename)] : $this->sections($blocks, $starts, $strategy, $unassigned);

        if (count($sections) > self::MAX_SECTIONS) {
            $extra = array_slice($sections, self::MAX_SECTIONS - 1);
            $sections = array_slice($sections, 0, self::MAX_SECTIONS - 1);
            $sections[] = $this->mergeTail($extra);
            $warnings[] = 'Terdeteksi lebih dari '.self::MAX_SECTIONS.' bagian. Bagian ke-'.self::MAX_SECTIONS.' dan seterusnya digabung menjadi satu agar tidak ada teks yang hilang; pisahkan manual bila perlu.';
        }

        $sections = $this->finish($sections);

        $titleBlock = collect($blocks)->first(fn ($b) => $b['t'] === 'title') ?? ($strategy === 'heading' ? collect($blocks)->first(fn ($b) => $b['t'] === 'h' && $b['level'] === 1 && collect($blocks)->where('t', 'h')->where('level', 1)->count() === 1) : null);
        $name = $titleBlock ? Str::limit($titleBlock['text'], 170, '') : $this->humanize($filename);
        [$type, $label, $typeNote] = $this->documentType($full);
        [$style, $citationNotes, $styleNote] = $this->citation($full);
        $hasQa = collect($sections)->contains(fn ($s) => $s['role'] === 'qa');

        $warnings[] = match ($strategy) {
            'heading' => 'Bagian ditentukan dari heading Word.',
            'numbered' => 'Tidak ada heading yang jelas. Bagian ditentukan dari butir daftar bernomor/berhuruf tingkat pertama; periksa apakah setiap butir memang bagian terpisah.',
            'pattern' => 'Tidak ada heading Word maupun daftar bernomor. Bagian ditebak dari baris bergaya judul; periksa dengan teliti.',
            default => 'Struktur bagian tidak terdeteksi. Seluruh dokumen dijadikan satu prompt; pisahkan manual dengan tombol "Pisahkan di kursor".',
        };

        if ($unassigned) {
            $warnings[] = count($unassigned).' paragraf sebelum bagian pertama tidak dimasukkan ke bagian mana pun (lihat daftar di bawah). Salin ke prompt yang sesuai bila diperlukan.';
        }

        if ($typeNote) {
            $warnings[] = $typeNote;
        }

        if ($styleNote) {
            $warnings[] = $styleNote;
        }

        if ($hasQa) {
            $warnings[] = 'Ada bagian pertanyaan dan jawaban: penempatannya diatur "Masuk naskah". Ubah di pengaturan template bila perlu.';
        }

        $flagged = collect($sections)->filter(fn ($s) => $s['flags'])->count();

        if ($flagged) {
            $warnings[] = $flagged.' bagian berlabel "Perlu diperiksa". Tinjau judul, batas kata, dan isi sebelum menyimpan.';
        }

        $withLimits = collect($sections)->filter(fn ($s) => $s['min_words'] || $s['max_words'] || $s['target_words'])->count();

        if ($withLimits === 0) {
            $warnings[] = 'Tidak ada batas jumlah kata yang ditemukan. Isi manual bila dokumen mensyaratkannya.';
        }

        return [
            'strategy' => $strategy,
            'template' => [
                'name' => $name,
                'document_type' => $type,
                'type_label' => $type === 'other' ? $label : null,
                'description' => 'Diimpor dari Word ('.$filename.'): '.count($sections).' bagian terdeteksi. Tinjau dan ubah sesuai kebutuhan.',
                'language' => $this->language($full),
                'citation_style' => $style,
                'qa_mode' => $hasQa ? 'inline' : 'none',
            ],
            'sections' => $sections,
            'warnings' => array_values(array_unique($warnings)),
            'unassigned' => $unassigned,
            'citation_notes' => $citationNotes,
            'specifics' => $this->specifics($blocks, $sections),
            'stats' => $read['stats'] + ['sections' => count($sections), 'words' => $this->docs->wordCount($full), 'chars' => mb_strlen($full)],
            'source_text' => $this->sourceText($blocks),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Deteksi awal bagian
    |--------------------------------------------------------------------------
    */

    /** @return array{0: string, 1: list<int>} */
    private function detectStarts(array $blocks): array
    {
        $heads = [];

        foreach ($blocks as $i => $b) {
            if ($b['t'] === 'h') {
                $heads[$b['level']][] = $i;
            }
        }

        ksort($heads);

        foreach ($heads as $level => $idx) {
            if (count($idx) >= 2) {
                return ['heading', $idx];
            }
        }

        $ordered = array_keys(array_filter($blocks, fn ($b) => $b['t'] === 'li' && $b['lvl'] === 0 && $b['fmt'] !== 'bullet'));

        if (count($ordered) >= 2) {
            return ['numbered', $ordered];
        }

        $pattern = [];

        foreach ($blocks as $i => $b) {
            if ($b['t'] === 'p' && $this->looksLikeHeading($b)) {
                $pattern[] = $i;
            }
        }

        return count($pattern) >= 2 ? ['pattern', $pattern] : ['single', []];
    }

    private function looksLikeHeading(array $b): bool
    {
        $t = $b['text'];

        if (mb_strlen($t) > 80 || str_contains($t, "\n") || preg_match_all('/\p{L}+/u', $t) > 10) {
            return false;
        }

        if (preg_match('/^(bab|chapter|bagian|part)\s+[ivxlc\d]+\b/iu', $t)) {
            return true;
        }

        $stripped = preg_replace('/^(\d+(\.\d+)*[\.\)]|[A-Za-z][\.\)]|[IVXLC]+[\.\)])\s+/u', '', $t, -1, $had);

        if ($had) {
            return ! preg_match('/[.!?,;]$/u', trim((string) $stripped)) || preg_match('/^\d+(\.\d+)+/', $t) === 1;
        }

        return $b['bold'] && ! preg_match('/[.!?,;:]$/u', $t) && mb_strlen($t) <= 70;
    }

    /*
    |--------------------------------------------------------------------------
    | Penyusunan bagian
    |--------------------------------------------------------------------------
    */

    /** @return list<array<string, mixed>> */
    private function sections(array $blocks, array $starts, string $strategy, array &$unassigned): array
    {
        $out = [];
        $count = count($blocks);

        // Pengantar berakhiran ":" tepat sebelum awal bagian (mis. "...maka buatlah:") ikut bagian itu, bukan bagian sebelumnya.
        $lead = [];

        if ($strategy !== 'heading') {
            foreach ($starts as $k => $start) {
                $floor = $k === 0 ? -1 : $starts[$k - 1];

                if ($start - 1 > $floor && $blocks[$start - 1]['t'] === 'p' && str_ends_with($blocks[$start - 1]['text'], ':')) {
                    $lead[$k] = $start - 1;
                }
            }
        }

        foreach (array_slice($blocks, 0, $starts[0], true) as $j => $b) {
            if ($b['t'] !== 'title' && ($lead[0] ?? -1) !== $j) {
                $unassigned[] = Str::limit($b['text'], 240);
            }
        }

        foreach ($starts as $k => $start) {
            $end = $lead[$k + 1] ?? ($starts[$k + 1] ?? $count);
            $first = $blocks[$start];
            $slice = array_slice($blocks, $start, $end - $start);

            if ($first['t'] === 'li') {
                $slice[0]['drop'] = true;
            } else {
                array_shift($slice);
            }

            if (isset($lead[$k])) {
                array_unshift($slice, $blocks[$lead[$k]]);
            }

            [$title, $confidence, $flags] = $this->titleFrom($first, $strategy);
            $out[] = $this->makeSection($title, $slice, $confidence, $flags);
        }

        return $out;
    }

    private function single(array $blocks, string $filename): array
    {
        $content = array_values(array_filter($blocks, fn ($b) => $b['t'] !== 'title'));

        return $this->makeSection($this->humanize($filename), $content, 'rendah', ['Struktur bagian tidak terdeteksi: seluruh dokumen dijadikan satu prompt.']);
    }

    private function mergeTail(array $extra): array
    {
        $first = $extra[0];

        foreach (array_slice($extra, 1) as $s) {
            $first['prompt'] .= "\n\n".$s['title'].":\n".$s['prompt'];
        }

        $first['flags'][] = 'Gabungan '.count($extra).' bagian terakhir karena melebihi batas '.self::MAX_SECTIONS.' bagian.';
        $first['confidence'] = 'rendah';

        return $this->analyse($first['title'], $first['prompt'], $first['confidence'], $first['flags']);
    }

    /** @return array{0: string, 1: string, 2: list<string>} */
    private function titleFrom(array $first, string $strategy): array
    {
        $text = $first['text'];

        if ($first['t'] === 'h') {
            return [Str::limit($text, 170, ''), 'tinggi', []];
        }

        if ($first['t'] === 'p') {
            $t = trim((string) preg_replace('/^(\d+(\.\d+)*[\.\)]|[A-Za-z][\.\)]|[IVXLC]+[\.\)])\s+/u', '', $text), " .:");

            return [Str::limit($t !== '' ? $t : $text, 170, ''), 'sedang', ['Judul diambil dari baris bergaya judul, bukan heading Word.']];
        }

        // Butir daftar: judul = awal kalimat sampai tanda kurung/titik dua (yakin sedang) atau kata sambung (kurang yakin).
        if (preg_match('/^(.{3,}?)\s*(?:\(|:)/u', $text, $m) && preg_match_all('/\p{L}+/u', $m[1]) <= 8) {
            return [$this->tidy($m[1]), 'sedang', []];
        }

        $cut = preg_split('/\s+(?:yang|secara|dengan|untuk|agar|sehingga|dan\s+lanjutkan)\s+/iu', $text, 2)[0] ?? $text;
        $words = preg_split('/\s+/u', trim($cut));
        $title = count($words) > 8 ? implode(' ', array_slice($words, 0, 8)) : $cut;

        return [$this->tidy($title), 'rendah', ['Judul diambil dari awal kalimat pertama karena tidak ada heading; ganti dengan nama bagian yang tepat.']];
    }

    private function tidy(string $t): string
    {
        $t = trim($t, " \t\n\r.,;:-–—");

        return Str::limit(mb_strtoupper(mb_substr($t, 0, 1)).mb_substr($t, 1), 170, '');
    }

    private function makeSection(string $title, array $blocks, string $confidence, array $flags): array
    {
        $lines = [];
        $prev = null;

        foreach ($blocks as $b) {
            $line = $this->line($b);
            $needsGap = $prev !== null && ($b['t'] !== 'li' || $prev !== 'li');

            if ($needsGap) {
                $lines[] = '';
            }

            $lines[] = $line;
            $prev = $b['t'];
        }

        return $this->analyse($title, trim(implode("\n", $lines)), $confidence, $flags);
    }

    /** Satu baris prompt dari satu blok. Butir awal bagian kehilangan nomor otomatisnya (itu penanda bagian, bukan redaksi). */
    private function line(array $b): string
    {
        return match ($b['t']) {
            'li' => str_repeat('  ', max(0, $b['lvl'])).(($b['drop'] ?? false) ? '' : $b['label'].' ').$b['text'],
            default => $b['text'],
        };
    }

    private function analyse(string $title, string $prompt, string $confidence, array $flags): array
    {
        [$limits, $evidence] = $this->limits($prompt);
        $itemCount = $this->itemCount($prompt, $evidence);
        $role = match (true) {
            (bool) preg_match('/(daftar\s+)?pustaka|referensi|references?|bibliograph/iu', $title) => 'references',
            (bool) preg_match('/pertanyaan|tanya\s*jawab|q\s*&\s*a|\bfaq\b|\bsoal\b/iu', $title) => 'qa',
            default => 'body',
        };

        if ($limits['min_words'] && $limits['max_words'] && $limits['min_words'] > $limits['max_words']) {
            $flags[] = 'Minimum kata lebih besar dari maksimum; periksa batas kata.';
        }

        if ($this->docs->wordCount($prompt) < 5) {
            $flags[] = 'Isi bagian sangat pendek atau kosong.';
            $confidence = 'rendah';
        }

        if (mb_strlen($prompt) > 30000) {
            $flags[] = 'Isi melebihi 30.000 karakter (batas prompt). Pisahkan bagian ini sebelum menyimpan.';
        }

        $unknown = $this->docs->unknownPlaceholders([$prompt]);

        if ($unknown) {
            $flags[] = 'Berisi {'.implode('}, {', $unknown).'} yang bukan variabel dikenal; ubah atau hapus sebelum menyimpan.';
        }

        return [
            'title' => $title,
            'prompt' => $prompt,
            'role' => $role,
            'is_required' => $role !== 'qa' && ! preg_match('/\b(opsional|optional|jika perlu|bila perlu|boleh dilewati)\b/iu', $prompt),
            'min_words' => $limits['min_words'],
            'max_words' => $limits['max_words'],
            'target_words' => $limits['target_words'],
            'item_count' => $itemCount,
            'confidence' => $flags && $confidence === 'tinggi' ? 'sedang' : $confidence,
            'flags' => array_values(array_unique($flags)),
            'evidence' => array_filter($evidence),
        ];
    }

    /** Judul ganda diberi akhiran agar unik; setiap perubahan ditandai. */
    private function finish(array $sections): array
    {
        $seen = [];

        foreach ($sections as $i => $s) {
            $key = mb_strtolower($s['title']);

            if (isset($seen[$key])) {
                $sections[$i]['title'] = $s['title'].' ('.(++$seen[$key]).')';
                $sections[$i]['flags'][] = 'Judul sama dengan bagian lain, diberi nomor; ganti dengan nama yang membedakan.';
                $sections[$i]['confidence'] = 'rendah';
            } else {
                $seen[$key] = 1;
            }
        }

        return $sections;
    }

    /*
    |--------------------------------------------------------------------------
    | Batas kata, jumlah butir
    |--------------------------------------------------------------------------
    */

    /** @return array{0: array{min_words: ?int, max_words: ?int, target_words: ?int}, 1: array<string, ?string>} */
    private function limits(string $text): array
    {
        $out = ['min_words' => null, 'max_words' => null, 'target_words' => null];
        $ev = ['min_words' => null, 'max_words' => null, 'target_words' => null];
        $n = '(\d[\d\.,]*)';
        $unit = '\s*(?:kata|words?)\b';
        $find = function (string $pattern, string $key) use ($text, &$out, &$ev): void {
            if ($out[$key] === null && preg_match($pattern, $text, $m) && ($v = $this->int($m[1])) !== null) {
                $out[$key] = $v;
                $ev[$key] = Str::limit(trim($m[0]), 90, '');
            }
        };

        if (preg_match('/'.$n.'\s*[-–—]\s*'.$n.$unit.'/iu', $text, $m) && ($a = $this->int($m[1])) !== null && ($b = $this->int($m[2])) !== null && $a <= $b) {
            $out['min_words'] = $a;
            $out['max_words'] = $b;
            $ev['min_words'] = $ev['max_words'] = Str::limit(trim($m[0]), 90, '');
        }

        $find('/(?:minimal|minimum|min\.?|paling sedikit|sekurang-kurangnya|setidaknya|at least|no fewer than)\s*:?\s*'.$n.$unit.'/iu', 'min_words');
        $find('/(?:maksimal|maksimum|maks\.?|paling banyak|tidak lebih dari|at most|no more than|up to)\s*:?\s*'.$n.$unit.'/iu', 'max_words');
        $find('/(?:sekitar|kurang lebih|±|target|approximately|around)\s*:?\s*'.$n.$unit.'/iu', 'target_words');

        return [$out, $ev];
    }

    private function int(string $s): ?int
    {
        $clean = preg_replace('/(?<=\d)[\.,](?=\d{3}(?!\d))/', '', trim($s, " .,"));
        $v = (int) $clean;

        return $v >= 1 && $v <= 200000 && ctype_digit((string) $clean) ? $v : null;
    }

    private function itemCount(string $text, array &$evidence): ?int
    {
        if (preg_match('/\b(satu|dua|tiga|empat|lima|enam|tujuh|delapan|sembilan|sepuluh|\d{1,3})\s+(?:buah\s+)?(pertanyaan|soal|butir|poin|questions?)\b/iu', $text, $m)) {
            $v = ctype_digit($m[1]) ? (int) $m[1] : self::NUMBER_WORDS[mb_strtolower($m[1])];

            if ($v >= 1 && $v <= 200) {
                $evidence['item_count'] = Str::limit($m[0], 90, '');

                return $v;
            }
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | Tingkat dokumen
    |--------------------------------------------------------------------------
    */

    /** @return array{0: string, 1: ?string, 2: ?string} */
    private function documentType(string $text): array
    {
        $t = mb_strtolower($text);

        foreach ([
            ['/\bskripsi\b/u', 'other', 'Skripsi'], ['/\btesis\b|\bthesis\b/u', 'other', 'Tesis'], ['/\bdisertasi\b|\bdissertation\b/u', 'other', 'Disertasi'],
            ['/proposal penelitian|\bproposal\b/u', 'other', 'Proposal penelitian'], ['/book chapter|bab buku|\bchapter\b/u', 'book_chapter', null],
            ['/artikel|jurnal|journal|manuscript/u', 'article', null], ['/buku akademik|\bbuku\b/u', 'book', null], ['/riset|penelitian|research/u', 'research', null],
        ] as [$re, $type, $label]) {
            if (preg_match($re, $t)) {
                return [$type, $label, null];
            }
        }

        return ['other', 'Lainnya', 'Jenis dokumen tidak terdeteksi; dipilih "Lainnya". Ubah di pengaturan template.'];
    }

    private function language(string $text): string
    {
        $id = preg_match_all('/\b(yang|dan|dengan|untuk|dari|pada|dalam|adalah|atau|ini)\b/iu', $text);
        $en = preg_match_all('/\b(the|and|with|for|of|that|this|is|are|should)\b/iu', $text);

        return $en > $id * 1.3 ? 'en' : 'id';
    }

    /** @return array{0: string, 1: list<string>, 2: ?string} gaya, kalimat sitasi yang ditemukan, catatan */
    private function citation(string $text): array
    {
        $notes = [];

        foreach (preg_split('/\R+|(?<=[.!?])\s+/u', $text) ?: [] as $sentence) {
            if (preg_match('/\b(sitasi|kutipan|mengutip|daftar pustaka|referensi|APA|Harvard|IEEE|Chicago|MLA)\b/iu', $sentence) && count($notes) < 8) {
                $notes[] = Str::limit(trim($sentence), 200);
            }
        }

        $style = null;
        $note = null;

        if (preg_match('/\bAPA\b[^\n]{0,25}?(?:\b7\b|ke-?7|seventh)/iu', $text)) {
            $style = 'APA 7';
        } elseif (preg_match('/\bAPA\b[^\n]{0,25}?\b6\b/iu', $text)) {
            $style = 'APA 6';
        } elseif (preg_match('/\bAPA\b/u', $text)) {
            $style = 'APA 7';
            $note = 'Dokumen menyebut APA tanpa versi; diasumsikan APA 7. Periksa gaya sitasi di pengaturan template.';
        }

        foreach (['Harvard', 'IEEE', 'Chicago', 'MLA'] as $s) {
            if ($style === null && preg_match('/\b'.$s.'\b/iu', $text)) {
                $style = $s;
            }
        }

        if ($style === null) {
            $style = 'APA 7';
            $note = 'Gaya sitasi tidak ditemukan di dokumen; dipakai bawaan APA 7. Ubah bila perlu.';
        }

        return [$style, $notes, $note];
    }

    /**
     * Frasa tebal (>= 3 kata) yang tampak spesifik topik, mis. judul bab, supaya admin bisa menggantinya dengan variabel
     * agar template bisa dipakai ulang. Hanya saran: tidak ada yang diganti otomatis.
     *
     * @return list<string>
     */
    private function specifics(array $blocks, array $sections): array
    {
        $titles = array_map(fn ($s) => mb_strtolower($s['title']), $sections);
        $all = [];

        foreach ($blocks as $b) {
            foreach ($b['phrases'] ?? [] as $p) {
                $p = trim($p, " \t,.;:");

                if ($p !== '' && ! in_array(mb_strtolower($p), $titles, true)) {
                    $all[$p] = true;
                }
            }
        }

        return array_slice(array_keys($all), 0, 8);
    }

    /** Teks asli hasil ekstraksi, berurutan, untuk dibandingkan admin dengan pembagian bagian. */
    private function sourceText(array $blocks): string
    {
        $out = [];
        $prev = null;

        foreach ($blocks as $b) {
            $line = match ($b['t']) {
                'title' => '# '.$b['text'],
                'h' => str_repeat('#', min(6, max(1, $b['level']))).' '.$b['text'],
                'li' => str_repeat('  ', $b['lvl']).$b['label'].' '.$b['text'],
                default => $b['text'],
            };

            if ($prev !== null && ($b['t'] !== 'li' || $prev !== 'li')) {
                $out[] = '';
            }

            $out[] = $line;
            $prev = $b['t'];
        }

        return Str::limit(implode("\n", $out), 200000, "\n… (dipotong)");
    }

    private function humanize(string $filename): string
    {
        $base = pathinfo($filename, PATHINFO_FILENAME);
        $base = preg_replace('/\s*\(\d+\)\s*$/', '', $base) ?? $base;
        $base = trim((string) preg_replace('/[_\-]+/', ' ', $base));

        return Str::limit($base !== '' ? $base : 'Template dari Word', 170, '');
    }
}
