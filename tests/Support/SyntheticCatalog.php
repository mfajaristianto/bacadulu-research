<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;

/**
 * Katalog publikasi SINTETIS deterministik untuk uji skala (SQLite uji atau MariaDB scratch). Tidak pernah dipakai pada database asli.
 *
 * Susunan (target = kategori yang dicari):
 *  - noise  : kata acak, kadang satu istilah topik.
 *  - decoy  : memuat "financial" DAN "reporting" terpisah di judul/abstrak/kata kunci -> `hits` lama maksimal, skor relevansi sedang.
 *             Dibuat lebih dulu (ID kecil) sehingga LIMIT 400 menurut hits+id lama memenuhi shortlist dengan decoy.
 *  - gold   : judul pendek berfrasa, abstrak mengulang frasa, kata kunci, metadata lengkap, kategori terverifikasi. Dibuat TERAKHIR (ID terbesar).
 *  - other  : kategori lain, teks identik dengan gold (harus tak pernah muncul).
 *  - withdrawn: judul "Retracted: ..." (harus disembunyikan).
 */
final class SyntheticCatalog
{
    private const TOPIC = ['accounting', 'audit', 'disclosure', 'earnings', 'quality', 'standards', 'ifrs', 'tax', 'governance', 'sustainability', 'esg', 'cash', 'flow', 'risk', 'bank', 'regulation', 'valuation', 'compliance', 'control', 'fraud'];

    /** @var array<int,string> */
    private array $filler = [];

    public function __construct(private int $seed = 20261118)
    {
        mt_srand($this->seed);
        for ($i = 0; $i < 400; $i++) {
            $this->filler[] = 'w'.base_convert((string) mt_rand(1000, 999999), 10, 36).'x';
        }
    }

    /**
     * @return array{target:int, other:int, gold_ids:array<int,int>, other_gold_ids:array<int,int>, decoys:int, total:int}
     */
    public function build(int $total, int $targetCat, int $otherCat, int $gold = 8, float $decoyShare = 0.9, float $targetShare = 0.95): array
    {
        mt_srand($this->seed);
        $now = now()->toDateTimeString();
        $nTarget = (int) round($total * $targetShare);
        $nDecoy = (int) round(($nTarget - $gold) * $decoyShare);
        $nWithdrawn = min(5, max(1, (int) ($total / 500)));
        $nOther = $total - $nTarget;
        $plan = [];

        // 1) decoy dulu (ID kecil), 2) noise + withdrawn di target, 3) other (kategori lain), 4) gold TERAKHIR.
        for ($i = 0; $i < $nDecoy; $i++) {
            $plan[] = ['decoy', $targetCat];
        }
        for ($i = 0; $i < $nTarget - $gold - $nDecoy - $nWithdrawn; $i++) {
            $plan[] = ['noise', $targetCat];
        }
        for ($i = 0; $i < $nWithdrawn; $i++) {
            $plan[] = ['withdrawn', $targetCat];
        }
        for ($i = 0; $i < $nOther - $gold; $i++) {
            $plan[] = [$i % 3 === 0 ? 'decoy' : 'noise', $otherCat];
        }
        for ($i = 0; $i < $gold; $i++) {
            $plan[] = ['gold', $otherCat]; // gold di kategori LAIN: tak boleh bocor
        }
        for ($i = 0; $i < $gold; $i++) {
            $plan[] = ['gold', $targetCat];
        }

        $providers = ['doaj', 'crossref', 'openalex'];
        $meta = [];
        $buffer = [];
        $total = 0;
        // Baris dibuat dan disisipkan per kelompok (tidak menahan seluruh katalog di memori, agar 100.000+ baris tetap bisa dibuat).
        foreach ($plan as $i => [$kind, $cat]) {
            $n = $i + 1;
            $r = $this->row($kind, $n, $now);
            $buffer[] = $r['pub'];
            $meta[] = ['kind' => $kind, 'cat' => $cat, 'provider' => $providers[$n % 3], 'status' => $r['status'], 'n' => $n];
            if (count($buffer) >= 250) {
                DB::table('external_publications')->insert($buffer);
                $total += count($buffer);
                $buffer = [];
            }
        }
        if ($buffer !== []) {
            DB::table('external_publications')->insert($buffer);
            $total += count($buffer);
        }

        $ids = DB::table('external_publications')->orderBy('id')->pluck('id')->all();
        $goldIds = [];
        $otherGold = [];
        $srcRows = [];
        $catRows = [];
        $flush = function () use (&$srcRows, &$catRows): void {
            $srcRows !== [] && DB::table('external_publication_sources')->insert($srcRows);
            $catRows !== [] && DB::table('external_publication_categories')->insert($catRows);
            $srcRows = [];
            $catRows = [];
        };
        foreach ($meta as $i => $m) {
            $id = $ids[$i];
            $srcRows[] = ['publication_id' => $id, 'provider' => $m['provider'], 'external_id' => "syn{$m['n']}", 'checksum' => sha1("s{$m['n']}"), 'last_seen_at' => $now, 'created_at' => $now, 'updated_at' => $now];
            $catRows[] = ['publication_id' => $id, 'category_id' => $m['cat'], 'status' => $m['status'], 'query_term' => 'uji', 'created_at' => $now, 'updated_at' => $now];
            if ($m['kind'] === 'gold') {
                $m['cat'] === $targetCat ? $goldIds[] = $id : $otherGold[] = $id;
            }
            count($srcRows) >= 500 && $flush();
        }
        $flush();

        return ['target' => $nTarget, 'other' => $nOther, 'gold_ids' => $goldIds, 'other_gold_ids' => $otherGold, 'decoys' => $nDecoy, 'total' => $total];
    }

    private function words(int $n, array $inject = []): string
    {
        $w = [];
        for ($i = 0; $i < $n; $i++) {
            $w[] = mt_rand(0, 9) < 2 ? self::TOPIC[mt_rand(0, count(self::TOPIC) - 1)] : $this->filler[mt_rand(0, count($this->filler) - 1)];
        }
        foreach ($inject as $word) {
            array_splice($w, mt_rand(0, count($w)), 0, [$word]);
        }

        return implode(' ', $w);
    }

    /** @return array{pub: array<string,mixed>, status: string} */
    private function row(string $kind, int $n, string $now): array
    {
        $year = mt_rand(0, 9) === 0 ? null : 1998 + mt_rand(0, 27);
        $status = 'suggested';
        $keywords = null;
        $authors = mt_rand(0, 6) === 0 ? null : json_encode(['Penulis '.$n]);

        switch ($kind) {
            case 'decoy':
                // Kedua istilah ada di judul, abstrak, dan kata kunci (hits maksimal), tetapi terpisah dan di judul panjang.
                $title = ucfirst($this->words(mt_rand(7, 13), ['financial', 'reporting']));
                $abstract = $this->words(mt_rand(30, 60), array_merge(['financial', 'reporting'], mt_rand(0, 1) ? ['financial', 'reporting'] : []));
                $keywords = json_encode(['financial', 'reporting', $this->filler[$n % 400]]);
                break;
            case 'gold':
                $title = 'Financial reporting'.(mt_rand(0, 1) ? ' '.self::TOPIC[$n % count(self::TOPIC)] : '');
                $abstract = 'Financial reporting is examined. '.$this->words(8).' financial reporting quality. '.$this->words(8).' financial reporting standards. '.$this->words(6).' financial reporting practice.';
                $keywords = json_encode(['financial reporting', 'accounting']);
                $status = 'verified';
                $year ??= 2020;
                $authors ??= json_encode(['Penulis '.$n]);
                break;
            case 'withdrawn':
                $title = 'Retracted: financial reporting '.$this->words(3);
                $abstract = $this->words(30, ['financial', 'reporting']);
                break;
            default: // noise
                $title = ucfirst($this->words(mt_rand(5, 10)));
                $abstract = $this->words(mt_rand(30, 60));
        }

        return ['status' => $status, 'pub' => [
            'uuid' => sprintf('00000000-0000-4000-8000-%012d', $n), 'title' => $title, 'title_hash' => sha1("t{$n}"), 'checksum' => sha1("c{$n}"),
            'doi' => $kind === 'gold' || $n % 11 !== 0 ? "10.8888/syn.{$n}" : null, 'authors_json' => $authors, 'publication_year' => $year, 'publication_type' => 'article',
            'container_title' => $n % 5 === 0 ? null : 'Jurnal Sintetis', 'abstract' => $abstract, 'keywords_json' => $keywords, 'language' => 'en',
            'oa_status' => 'open', 'availability' => 'ABSTRACT_AVAILABLE', 'landing_url' => "https://example.org/syn/{$n}", 'created_at' => $now, 'updated_at' => $now,
        ]];
    }
}
