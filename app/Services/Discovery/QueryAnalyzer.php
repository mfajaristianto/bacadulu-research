<?php

namespace App\Services\Discovery;

use Illuminate\Support\Str;

/**
 * Pemahaman pertanyaan secara DETERMINISTIK (tanpa LLM, tanpa jaringan).
 *
 * Kata fungsi (stopword) memisahkan pertanyaan menjadi "frasa" (rentetan kata isi). Kata riset generik
 * (pengaruh, analisis, effect, impact, ...) TIDAK dibuang: tetap menjadi istilah, tetapi tidak menentukan kelayakan
 * dan bobotnya lebih kecil. Tahun/bahasa hanya ditandai sebagai petunjuk bila diminta eksplisit di kalimat.
 */
final class QueryAnalyzer
{
    private const STOPWORDS = [
        // Indonesia
        'yang', 'dan', 'di', 'ke', 'dari', 'untuk', 'pada', 'dalam', 'dengan', 'terhadap', 'atau', 'oleh', 'sebagai', 'adalah', 'ini', 'itu',
        'tentang', 'antara', 'serta', 'para', 'suatu', 'kepada', 'bagi', 'apakah', 'bagaimana', 'mengapa', 'apa', 'saya', 'cari', 'carikan',
        'referensi', 'jurnal', 'tentang', 'mengenai', 'terkait', 'berkaitan',
        // Kata kerja/penanda meta pada pertanyaan percakapan ("...dibahas dalam literatur yang tersedia"): bukan topik, sehingga tidak boleh mewajibkan kecocokan.
        'dibahas', 'membahas', 'dibicarakan', 'tersedia', 'literatur', 'pustaka', 'ditemukan',
        // Inggris
        'the', 'of', 'and', 'in', 'on', 'for', 'to', 'a', 'an', 'with', 'by', 'from', 'as', 'at', 'is', 'are', 'its', 'their', 'between',
        'towards', 'toward', 'among', 'using', 'use', 'does', 'do', 'how', 'what', 'why', 'about', 'find', 'references', 'reference', 'papers', 'paper',
        'discussed', 'available', 'literature', 'covered',
    ];

    /** Kata riset yang sangat umum: dipertahankan, tetapi berbobot rendah dan tidak menentukan kelayakan. */
    private const GENERIC = [
        'isu', 'pengaruh', 'analisis', 'hubungan', 'studi', 'kajian', 'efek', 'peran', 'dampak', 'penelitian', 'faktor',
        'issue', 'issues', 'effect', 'effects', 'impact', 'impacts', 'analysis', 'study', 'studies', 'role', 'relationship', 'influence', 'evidence', 'factors', 'research',
    ];

    /** @return array{normalized: string, terms: array<int,string>, core: array<int,string>, generic: array<int,string>, phrases: array<int,string>, year: ?int, language: ?string, empty: bool} */
    public function analyze(string $raw): array
    {
        $text = self::normalize($raw);

        // Petunjuk eksplisit saja: "tahun 2021", "year 2021"; "bahasa indonesia", "in english".
        $year = null;
        if (preg_match('/\b(?:tahun|year|th)\s+((?:19|20)\d{2})\b/u', $text, $m)) {
            $year = (int) $m[1];
            $text = trim(str_replace($m[0], ' ', $text));
        }
        $language = null;
        if (preg_match('/\b(?:bahasa\s+indonesia|dalam\s+bahasa\s+indonesia|in\s+indonesian)\b/u', $text, $m)) {
            $language = 'id';
            $text = trim(str_replace($m[0], ' ', $text));
        } elseif (preg_match('/\b(?:bahasa\s+inggris|dalam\s+bahasa\s+inggris|in\s+english)\b/u', $text, $m)) {
            $language = 'en';
            $text = trim(str_replace($m[0], ' ', $text));
        }

        $tokens = array_values(array_filter(explode(' ', $text), fn ($t) => $t !== ''));

        // Frasa = rentetan kata isi di antara kata fungsi; kata generik di tepi frasa dilepas dari frasa (bukan dari istilah).
        $runs = [];
        $cur = [];
        foreach ($tokens as $t) {
            if (in_array($t, self::STOPWORDS, true) || mb_strlen($t) < 2) {
                $cur && $runs[] = $cur;
                $cur = [];

                continue;
            }
            $cur[] = $t;
        }
        $cur && $runs[] = $cur;

        $terms = [];
        $phrases = [];
        foreach ($runs as $run) {
            foreach ($run as $t) {
                $terms[$t] = true;
            }
            while ($run && in_array($run[0], self::GENERIC, true)) {
                array_shift($run);
            }
            while ($run && in_array(end($run), self::GENERIC, true)) {
                array_pop($run);
            }
            if (count($run) >= 2) {
                $phrases[implode(' ', $run)] = true;
            }
        }

        $terms = array_slice(array_keys($terms), 0, 12);
        $generic = array_values(array_filter($terms, fn ($t) => in_array($t, self::GENERIC, true)));
        $core = array_values(array_diff($terms, $generic));
        // Pertanyaan yang hanya berisi kata generik tetap dicari memakai kata itu (tidak dikosongkan).
        if ($core === [] && $terms !== []) {
            $core = $terms;
            $generic = [];
        }

        return [
            'normalized' => implode(' ', $tokens),
            'terms' => $terms,
            'core' => $core,
            'generic' => $generic,
            'phrases' => array_slice(array_keys($phrases), 0, 4),
            'year' => $year,
            'language' => $language,
            'empty' => $terms === [],
        ];
    }

    /** Huruf kecil, tanpa diakritik, tanda baca menjadi spasi; huruf non-Latin dipertahankan. */
    public static function normalize(string $text): string
    {
        $text = mb_strtolower($text);
        if (class_exists(\Normalizer::class)) {
            $text = preg_replace('/\p{Mn}+/u', '', \Normalizer::normalize($text, \Normalizer::FORM_D) ?: $text) ?? $text;
        }

        return trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text) ?? '');
    }

    /** @return array<int,string> token unik (untuk pencocokan cakupan istilah) */
    public static function tokens(?string $text): array
    {
        return array_values(array_unique(array_filter(explode(' ', self::normalize((string) $text)), fn ($t) => $t !== '')));
    }

    /** Kata bermakna (bukan kata fungsi) pada teks yang sudah dinormalisasi; duplikat dipertahankan. @return array<int,string> */
    public static function contentTokens(string $normalizedText): array
    {
        return array_values(array_filter(explode(' ', $normalizedText), fn ($t) => $t !== '' && mb_strlen($t) >= 2 && ! in_array($t, self::STOPWORDS, true)));
    }

    /** Pencocokan frasa pada teks yang sudah dinormalisasi, dengan batas kata. */
    public static function hasPhrase(string $normalizedText, string $phrase): bool
    {
        return $phrase !== '' && str_contains(' '.$normalizedText.' ', ' '.$phrase.' ');
    }

    /**
     * Akar istilah untuk pencocokan SQL: istilah berakhiran "s" dicocokkan juga dengan bentuk tunggalnya (aturan skor menerima
     * "reports" <-> "report"). Pola LIKE dari akar ini adalah SUPERSET dari pencocokan token di skor, sehingga SQL tidak pernah
     * membuang baris yang skornya akan menganggap cocok.
     */
    public static function stem(string $term): string
    {
        return mb_strlen($term) > 1 && str_ends_with($term, 's') ? mb_substr($term, 0, -1) : $term;
    }

    /** Pola LIKE aman (ESCAPE '!') untuk satu istilah. */
    public static function like(string $term): string
    {
        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], Str::lower($term)).'%';
    }
}
