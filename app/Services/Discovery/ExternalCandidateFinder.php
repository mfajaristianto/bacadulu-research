<?php

namespace App\Services\Discovery;

use Illuminate\Support\Facades\DB;

/**
 * Kandidat Sumber Eksternal dengan BATAS ATAS skor yang dihitung di SQL.
 *
 * Masalah lama: kandidat dipilih `ORDER BY hits DESC, id ASC LIMIT 400`. `hits` hanya skor kasar, jadi ratusan baris berbatas-sama
 * dipotong menurut ID dan artikel yang paling relevan bisa terbuang sebelum skor relevansi dihitung.
 *
 * Di sini SQL tidak membuang apa pun berdasarkan ID. Untuk tiap baris SQL menghitung `ub` = batas atas skor relevansi yang
 * dijamin >= skor sebenarnya (SourceDiscovery::score), lalu mengurutkan menurut `ub`. Pemanggil memeriksa baris menurut urutan itu dan
 * berhenti hanya bila `ub` baris berikutnya < skor ke-N yang sudah pasti, sehingga tidak ada baris tak-diperiksa yang bisa masuk N teratas.
 *
 * Bentuk query (3 lapis, agar tiap LIKE dihitung SEKALI per baris):
 *   f: bendera 0/1 per (istilah x bidang) dan per (frasa x bidang), hanya untuk baris yang memuat setidaknya satu istilah  [LIMIT besar = materialisasi]
 *   m: elig (jumlah istilah cocok) dan ub (batas atas) sebagai aritmetika atas bendera
 *   x: elig >= syarat kelayakan, urut ub turun
 *
 * Syarat kesahihan batas atas (diuji sebagai sifat, bukan hanya contoh):
 *  - LIKE '%akar%' adalah superset pencocokan token pada skor (substring >= token, akar tunggal untuk "...s"), sehingga jumlah istilah
 *    cocok di SQL >= jumlah di skor. Frasa: kata-kata harus berdampingan (pemisah 1..PHRASE_GAP karakter) = superset frasa berbatas-kata.
 *    Batasan jujur: judul/abstrak dengan pemisah antar-kata frasa lebih dari PHRASE_GAP karakter non-alfanumerik dapat dinilai di bawah batas.
 *  - Suku yang bisa MENURUNKAN skor (penalti tahun masa depan, jenis "other") sengaja tidak dikurangkan.
 *  - Kata kunci tersimpan sebagai JSON ber-escape (\uXXXX) dan berkolasi biner: baris ber-escape unicode diperlakukan permisif (dianggap
 *    cocok) agar tidak ada negatif-palsu.
 *  - Judul/abstrak harus berkolasi tak-peka huruf besar/aksen (utf8mb4_unicode_ci di MariaDB proyek ini), sehingga LIKE tidak butuh lower().
 *    Pada SQLite (uji) dipakai lower() dan aksen peka, jadi kesetaraan aksen hanya dijamin di MariaDB.
 */
final class ExternalCandidateFinder
{
    /** Jumlah maksimum karakter pemisah antar dua kata frasa yang masih dianggap berdampingan oleh batas atas. */
    public const PHRASE_GAP = 5;

    /**
     * @param  array<string,mixed>  $a  hasil QueryAnalyzer::analyze
     * @param  array<string,mixed>  $f  filter keras (year, type, language)
     * @param  array<int,string>  $providers  penyedia aktif
     * @return array{rows: array<int, array{id:int, ub:float}>, scan_limited: bool}
     */
    public function find(int $categoryId, array $a, array $f, array $providers, int $limit): array
    {
        $W = SourceDiscovery::WEIGHTS;
        $core = array_values($a['core']);
        $n = count($core);
        if ($n === 0 || $providers === []) {
            return ['rows' => [], 'scan_limited' => false];
        }

        $required = $n <= 2 ? $n : max(2, (int) ceil($n / 2));
        $mysql = in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
        $col = $mysql
            ? ['t' => 'p.title', 'a' => 'p.abstract', 'k' => 'p.keywords_json collate utf8mb4_unicode_ci']
            : ['t' => 'lower(p.title)', 'a' => 'lower(p.abstract)', 'k' => 'lower(p.keywords_json)'];
        $esc = fn (string $w): string => str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($w));

        // ---- lapis f: bendera ------------------------------------------------------------------------------------------------
        $flags = [];   // alias => [sql, bindings]
        $any = [];
        $anyBind = [];
        foreach ($core as $i => $t) {
            $pat = '%'.$esc(QueryAnalyzer::stem($t)).'%';
            $parts = [];
            foreach (['t', 'a', 'k'] as $kind) {
                if ($kind === 'k') {
                    // Cocok pada kata kunci, ATAU kata kunci memuat escape unicode JSON (tak bisa dibandingkan andal di SQL): permisif.
                    $flags["k{$i}"] = ["(case when {$col['k']} like ? escape '!' or p.keywords_json like ? escape '!' then 1 else 0 end)", [$pat, '%\\u%']];
                    $parts[] = ["({$col['k']} like ? escape '!' or p.keywords_json like ? escape '!')", [$pat, '%\\u%']];
                } else {
                    $flags["{$kind}{$i}"] = ["(case when {$col[$kind]} like ? escape '!' then 1 else 0 end)", [$pat]];
                    $parts[] = ["({$col[$kind]} like ? escape '!')", [$pat]];
                }
            }
            foreach ($parts as [$s, $b]) {
                $any[] = $s;
                array_push($anyBind, ...$b);
            }
        }

        // Frasa: 1 bila kata-kata frasa BERDAMPINGAN (tiap pasangan bersebelahan dipisah 1..GAP karakter apa pun; "_" = satu karakter).
        // Superset dari frasa berbatas-kata di skor, yang menganggap rentetan karakter non-huruf/angka sebagai satu spasi.
        $phrases = array_values($a['phrases']);
        $np = max(1, count($phrases));
        foreach ($phrases as $j => $p) {
            $words = explode(' ', $p);
            foreach (['t', 'a'] as $kind) {
                $pairs = [];
                $bind = [];
                for ($w = 0; $w + 1 < count($words); $w++) {
                    // Prasaring berurutan "%w1%w2%" (satu pemindaian) membuang sebagian besar baris sebelum 5 pemeriksaan jarak.
                    $pairBind = ['%'.$esc($words[$w]).'%'.$esc($words[$w + 1]).'%'];
                    $alts = [];
                    for ($g = 1; $g <= self::PHRASE_GAP; $g++) {
                        $alts[] = "{$col[$kind]} like ? escape '!'";
                        $pairBind[] = '%'.$esc($words[$w]).str_repeat('_', $g).$esc($words[$w + 1]).'%';
                    }
                    $pairs[] = "({$col[$kind]} like ? escape '!' and (".implode(' or ', $alts).'))';
                    array_push($bind, ...$pairBind);
                }
                $flags["p{$kind}{$j}"] = ['(case when '.implode(' and ', $pairs).' then 1 else 0 end)', $bind];
            }
        }

        $inner = DB::table('external_publications as p')
            ->join('external_publication_categories as c', fn ($j) => $j->on('c.publication_id', '=', 'p.id')->where('c.category_id', '=', $categoryId))
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('external_publication_sources as s')->whereColumn('s.publication_id', 'p.id')->whereIn('s.provider', $providers))
            ->whereRaw('('.implode(' or ', $any).')', $anyBind)
            ->selectRaw('p.id as id')
            ->selectRaw('c.status as status')
            ->selectRaw("(case when p.authors_json is not null and p.authors_json not in ('[]','null','') then 1 else 0 end)"
                ." + (case when coalesce(p.publication_year,0) > 0 then 1 else 0 end)"
                ." + (case when coalesce(nullif(p.container_title,''), nullif(p.publisher,'')) is not null then 1 else 0 end)"
                ." + (case when p.doi is not null and p.doi <> '' then 1 else 0 end) as comp");

        if (! empty($a['year'])) {
            $inner->selectRaw('(case when p.publication_year = ? then 1 else 0 end) as yhit', [(int) $a['year']]);
        }
        if (! empty($a['language'])) {
            $inner->selectRaw("(case when lower(coalesce(p.language,'')) = ? then 1 else 0 end) as lhit", [(string) $a['language']]);
        }
        foreach ($flags as $alias => [$sql, $bind]) {
            $inner->selectRaw("{$sql} as {$alias}", $bind);
        }
        foreach (['year' => 'publication_year', 'type' => 'publication_type', 'language' => 'language'] as $key => $c) {
            filled($f[$key] ?? null) && $inner->where("p.{$c}", $f[$key]);
        }
        // LIMIT besar mencegah optimizer menggabung (merge) lapis f ke atas, yang akan mengulang evaluasi LIKE.
        $inner->limit(PHP_INT_MAX);

        // ---- lapis m: aritmetika atas bendera ----------------------------------------------------------------------------------
        $sumOf = fn (string $kind): string => '('.implode(' + ', array_map(fn ($i) => "x.{$kind}{$i}", array_keys($core))).')';
        $tS = $sumOf('t');
        $aS = $sumOf('a');
        $kS = $sumOf('k');
        $pt = $phrases === [] ? '0' : '('.implode(' + ', array_map(fn ($j) => "x.pt{$j}", array_keys($phrases))).')';
        $pa = $phrases === [] ? '0' : '('.implode(' + ', array_map(fn ($j) => "x.pa{$j}", array_keys($phrases))).')';

        $elig = '('.implode(' + ', array_map(fn ($i) => "(case when x.t{$i} + x.a{$i} + x.k{$i} > 0 then 1 else 0 end)", array_keys($core))).')';

        $ub = [];
        $ub[] = "{$W['title_coverage']}.0 * {$tS} / {$n}.0";
        $phrases !== [] && $ub[] = "{$W['title_phrase']}.0 * {$pt} / {$np}.0";
        $ub[] = "{$W['title_focus']}.0 * (case when {$tS} > 0 then 1 else 0 end)";
        $ub[] = "{$W['abstract_coverage']}.0 * {$aS} / {$n}.0";
        $phrases !== [] && $ub[] = "{$W['abstract_phrase']}.0 * {$pa} / {$np}.0";
        $ub[] = "{$W['abstract_repeat']}.0 * (case when ".($phrases !== [] ? $pa : $aS).' > 0 then 1 else 0 end)';
        $ub[] = "{$W['keywords']}.0 * {$kS} / {$n}.0";
        $ub[] = "(case x.status when 'verified' then {$W['category_verified']} when 'suggested' then {$W['category_suggested']} else 0 end)";
        $ub[] = "{$W['completeness_each']}.0 * x.comp";
        ! empty($a['year']) && $ub[] = "{$W['year_hint']}.0 * x.yhit";
        ! empty($a['language']) && $ub[] = "{$W['language_hint']}.0 * x.lhit";

        $mid = DB::query()->fromSub($inner, 'x')->selectRaw('x.id as id')->selectRaw("{$elig} as elig")->selectRaw('('.implode(' + ', $ub).') as ub');

        // ---- lapis x: syarat kelayakan dan urutan batas atas -----------------------------------------------------------------
        $rows = DB::query()->fromSub($mid, 'm')->where('m.elig', '>=', $required)->orderByDesc('m.ub')->orderBy('m.id')->limit($limit)->get(['m.id', 'm.ub']);

        return [
            'rows' => $rows->map(fn ($r) => ['id' => (int) $r->id, 'ub' => (float) $r->ub])->all(),
            'scan_limited' => $rows->count() >= $limit,
        ];
    }
}
