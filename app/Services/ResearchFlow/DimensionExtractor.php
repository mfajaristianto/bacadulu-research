<?php

namespace App\Services\ResearchFlow;

/**
 * Mengambil kalimat evidence yang memuat petunjuk (cue) sebuah dimensi penelitian. Murni aturan kata kunci (ID + EN):
 * hasilnya KUTIPAN apa adanya dari teks evidence, bukan ringkasan atau dugaan. Tanpa kecocokan = tidak tersedia.
 */
final class DimensionExtractor
{
    public const UNAVAILABLE = 'Data tidak tersedia pada evidence.';

    /** @var array<string, array{label: string, cue: string}> */
    public const DIMENSIONS = [
        'objek' => ['label' => 'Objek', 'cue' => '/\b(objek|obyek|object of|subjek|subject of|perusahaan|firms?|companies|industr(?:i|y)|sektor|sector|sekolah|schools?|universit(?:as|y)|rumah sakit|hospitals?|bank|umkm|msmes?|listed on|terdaftar di)\b/iu'],
        'sampel' => ['label' => 'Sampel', 'cue' => '/\b(sampel|samples?|responden|respondents?|participants?|partisipan|populasi|population|purposive|observasi|observations?|firm-years?|n\s*=\s*\d+)\b/iu'],
        'metode' => ['label' => 'Metode', 'cue' => '/\b(metode|method(?:s|ology)?|metodologi|regresi|regression|panel data|pls|sem|anova|survey|survei|kuantitatif|quantitative|kualitatif|qualitative|eksperimen|experiment(?:al)?|systematic review|meta-analysis|studi kasus|case study|wawancara|interviews?)\b/iu'],
        'variabel' => ['label' => 'Variabel', 'cue' => '/\b(variabel|variables?|independent|dependent|moderat(?:ing|or|asi)|mediat(?:ing|or|asi)|bebas|terikat|proxy|diukur|measured by)\b/iu'],
        'teori' => ['label' => 'Teori', 'cue' => '/\b(teori|theor(?:y|ies|etical)|stakeholder|signaling|signalling|agency|legitimacy|legitimasi|resource-based|kerangka teoretis)\b/iu'],
        'hasil' => ['label' => 'Hasil', 'cue' => '/\b(hasil|results?|findings?|menunjukkan|menemukan|ditemukan|shows?|showed|found|reveal(?:s|ed)?|conclude[sd]?|berpengaruh|significant(?:ly)?|signifikan|no effect)\b/iu'],
    ];

    /** Dimensi yang disebut ruleset Alat Riset (nama ID/EN dalam teks aturan); tanpa sebutan = seluruh dimensi baku. */
    public static function fromRuleset(string $ruleset): array
    {
        $names = [
            'objek' => '/\b(objek|obyek|object)/iu', 'sampel' => '/\b(sampel|sample)/iu', 'metode' => '/\b(metode|method)/iu',
            'variabel' => '/\b(variabel|variable)/iu', 'teori' => '/\b(teori|theor)/iu', 'hasil' => '/\b(hasil|results?|findings?)\b/iu',
        ];
        $hit = array_values(array_filter(array_keys($names), fn ($k) => preg_match($names[$k], $ruleset) === 1));

        return $hit === [] ? array_keys(self::DIMENSIONS) : $hit;
    }

    /**
     * @param  list<string>  $dimensions
     * @param  list<array{text: string, citation_id: string}>  $items  evidence milik SATU sumber
     * @return array<string, array{available: bool, value: ?string, citation_id: ?string}>
     */
    public function extract(array $dimensions, array $items): array
    {
        $out = [];
        foreach ($dimensions as $d) {
            $cue = self::DIMENSIONS[$d]['cue'] ?? null;
            $found = null;
            foreach ($items as $it) {
                foreach ($cue ? $this->sentences((string) $it['text']) : [] as $s) {
                    if (preg_match($cue, $s) === 1) {
                        $found = ['value' => $this->clip($s), 'citation_id' => $it['citation_id']];
                        break 2;
                    }
                }
            }
            $out[$d] = ['available' => $found !== null, 'value' => $found['value'] ?? null, 'citation_id' => $found['citation_id'] ?? null];
        }

        return $out;
    }

    /** @return list<string> */
    private function sentences(string $text): array
    {
        $parts = preg_split('/(?<=[.!?])\s+(?=\p{Lu})|\n+/u', trim($text)) ?: [];

        return array_values(array_filter(array_map('trim', $parts), fn ($s) => mb_strlen($s) >= 20));
    }

    private function clip(string $s): string
    {
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;

        return mb_strlen($s) > 240 ? rtrim(mb_substr($s, 0, 237)).'…' : $s;
    }
}
