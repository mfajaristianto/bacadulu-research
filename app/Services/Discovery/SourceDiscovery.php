<?php

namespace App\Services\Discovery;

use App\Models\Category;
use App\Models\Document;
use App\Models\ExternalPublication;
use App\Models\ExternalSyncRun;
use App\Services\ExternalSources\ExternalSourceSettings;
use App\Services\Research\KeywordRetriever;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Penemuan referensi: Pustaka Internal + metadata Sumber Eksternal, TANPA AI.
 *
 * Alur: analisis pertanyaan deterministik -> kandidat terbatas (kategori + kecocokan istilah di SQL) -> skor RELEVANSI yang
 * dapat dijelaskan -> dedup (DOI/identitas penyedia) -> urut. Pemilih (top 5, halaman) hanya mengiris hasil urut ini.
 *
 * Urutan (compare): 1) skor relevansi (turun) 2) ketersediaan bukti (turun) 3) abjad judul ternormalisasi 4) DOI 5) id numerik.
 * Ketersediaan bukti TIDAK menambah skor (abstrak ada != lebih relevan/bermutu); ia hanya memisahkan skor yang setara.
 * Penyedia TIDAK pernah memengaruhi skor maupun urutan. Referensi dengan skor+bukti setara ditandai (`tied_with`): urutan di antara
 * mereka bukan peringkat kualitas ilmiah.
 *
 * Yang SENGAJA tidak ada: kemiripan semantik, penilaian "kualitas jurnal", atau klaim membaca isi paper dari metadata.
 */
final class SourceDiscovery
{
    /** Versi algoritma: masuk kunci cache agar hasil lama tidak tersaji setelah aturan berubah. */
    private const ALGO = 3;

    /** Bobot skor relevansi (maks 100, tidak dijenuhkan oleh satu sinyal). Terbuka agar bisa diuji dan dijelaskan. */
    public const WEIGHTS = [
        'title_coverage' => 25, 'title_phrase' => 25, 'title_focus' => 5, 'abstract_coverage' => 12, 'abstract_phrase' => 8, 'abstract_repeat' => 4,
        'keywords' => 6, 'body' => 10, 'category_verified' => 4, 'category_suggested' => 2,
        'completeness_each' => 1, 'year_hint' => 3, 'language_hint' => 2,
        'penalty_other_type' => 8, 'penalty_future_year' => 10,
    ];

    public const EVIDENCE_LABELS = [
        'metadata' => 'METADATA ONLY', 'abstract' => 'ABSTRACT AVAILABLE', 'full_text_indexed' => 'FULL TEXT INDEXED',
    ];

    private const TYPE_LABELS = ['article' => 'Artikel jurnal', 'book' => 'Buku', 'chapter' => 'Bab buku', 'proceedings' => 'Prosiding', 'dataset' => 'Dataset', 'thesis' => 'Tesis/disertasi', 'other' => 'Jenis lain'];

    private const WITHDRAWN = '/^\s*(?:retracted|retraction|withdrawn|withdrawal|notice of retraction|expression of concern)\b/iu';

    public function __construct(
        private QueryAnalyzer $analyzer,
        private ExternalSourceSettings $settings,
        private KeywordRetriever $retriever,
        private ExternalCandidateFinder $finder,
    ) {}

    /**
     * @param  array{year?: ?int, language?: ?string, type?: ?string}  $filters  filter KERAS dari pengguna (bukan petunjuk di kalimat)
     * @return array{analysis: array, results: array<int, array>, counts: array<string,int|bool>, years: array<string,int>}
     */
    public function discover(int $categoryId, string $query, array $filters = []): array
    {
        $analysis = $this->analyzer->analyze($query);
        $empty = ['analysis' => $analysis, 'results' => [], 'counts' => ['internal' => 0, 'external' => 0, 'candidates' => 0, 'withdrawn_hidden' => 0, 'truncated' => false], 'years' => []];

        if ($analysis['empty'] || ! Category::query()->whereKey($categoryId)->where('status', 'active')->exists()) {
            return $empty;
        }

        $key = 'discovery:'.md5(json_encode([self::ALGO, $categoryId, $analysis['normalized'], $filters, $this->enabledProviders(), $this->version()]));

        return Cache::remember($key, (int) config('research.discovery.cache_seconds', 120), fn () => $this->run($categoryId, $analysis, $filters));
    }

    /** @return array<int,string> */
    public function enabledProviders(): array
    {
        return array_values(array_filter(['openalex', 'crossref', 'doaj'], fn ($p) => (bool) ($this->settings->get($p)['enabled'] ?? false)));
    }

    private function version(): string
    {
        // Tabel kecil (run sinkronisasi & dokumen); jangan memindai tabel publikasi yang bisa berjuta baris.
        return (string) (ExternalSyncRun::query()->max('updated_at').'|'.Document::query()->max('updated_at'));
    }

    private function run(int $categoryId, array $a, array $f): array
    {
        $category = Category::query()->find($categoryId);
        $hidden = 0;
        $meta = ['sql_candidates' => 0, 'examined' => 0, 'exact' => true];
        $need = max(1, (int) config('research.discovery.rank_limit', 200));

        $internal = $this->internal($categoryId, $a, $f, $category?->name, $meta);
        $external = $this->external($categoryId, $a, $f, $category?->name, $internal, $need, $meta, $hidden);

        $merged = $this->dedupe([...$internal, ...$external]);
        usort($merged, [self::class, 'compare']);
        $relevant = count($merged);

        // Skor + bukti yang persis sama = setara: urutan di antara mereka hanya tie-break (abjad judul), bukan mutu ilmiah.
        $groups = array_count_values(array_map(fn ($r) => self::tieKey($r), $merged));
        foreach ($merged as &$r) {
            $r['tied_with'] = $groups[self::tieKey($r)] - 1;
        }
        unset($r);
        $ranked = array_slice($merged, 0, (int) config('research.discovery.rank_limit', 200));

        $years = [];
        foreach ($ranked as $r) {
            $y = $r['year'] ? (string) $r['year'] : 'tanpa tahun';
            $years[$y] = ($years[$y] ?? 0) + 1;
        }
        krsort($years);
        if (isset($years['tanpa tahun'])) { // string mengurut di atas angka; tempatkan di akhir
            $none = $years['tanpa tahun'];
            unset($years['tanpa tahun']);
            $years['tanpa tahun'] = $none;
        }

        return [
            'analysis' => $a,
            'results' => $ranked,
            // external = baris katalog yang cocok di SQL (superset kelayakan); examined = yang benar-benar dinilai skornya.
            // exact=false hanya bila batas pemindaian/pemeriksaan tercapai sebelum N teratas terbukti; saat itu daftar bisa belum lengkap.
            'counts' => ['internal' => count($internal), 'external' => $meta['sql_candidates'], 'candidates' => count($internal) + $meta['sql_candidates'], 'withdrawn_hidden' => $hidden,
                'truncated' => ! $meta['exact'], 'exact' => $meta['exact'], 'examined' => $meta['examined'],
                // Jumlah layak yang ditemukan; menjadi TOTAL sebenarnya hanya bila semua kandidat SQL sudah dinilai (relevant_complete).
                'relevant' => $relevant, 'relevant_complete' => $meta['exact'] && $meta['examined'] >= $meta['sql_candidates']],
            'years' => $years,
        ];
    }

    /** Urutan eksplisit; tidak memakai penyedia atau kunci teks. Id numerik hanya penentu terakhir untuk entri identik. */
    public static function compare(array $x, array $y): int
    {
        return ($y['score'] <=> $x['score'])
            ?: ($y['evidence_rank'] <=> $x['evidence_rank'])
            ?: strcmp(QueryAnalyzer::normalize($x['title']), QueryAnalyzer::normalize($y['title']))
            ?: strcmp(mb_strtolower((string) $x['doi']), mb_strtolower((string) $y['doi']))
            ?: ($x['id'] <=> $y['id'])
            ?: strcmp($x['kind'], $y['kind']);
    }

    private static function tieKey(array $r): string
    {
        return number_format($r['score'], 1, '.', '').'|'.$r['evidence_rank'];
    }

    /* ----------------------------------------------------------------------------------- eksternal */

    /**
     * Sumber Eksternal tanpa pemotongan menurut ID: SQL menghitung batas atas skor tiap baris (ExternalCandidateFinder) dan baris
     * diperiksa menurut batas itu. Berhenti hanya bila batas atas baris berikutnya < skor ke-N yang sudah pasti (atau baris habis),
     * jadi N teratas identik dengan hasil menilai SELURUH katalog. Bila batas pemindaian/pemeriksaan tercapai lebih dulu,
     * `exact` = false dan UI menyatakannya.
     *
     * @param  array<int,array>  $internal  item Pustaka yang sudah dinilai (ikut menentukan skor ke-N)
     * @param  array{sql_candidates:int, examined:int, exact:bool}  $meta
     */
    private function external(int $categoryId, array $a, array $f, ?string $categoryName, array $internal, int $need, array &$meta, int &$hidden): array
    {
        $providers = $this->enabledProviders();
        if ($providers === [] || $a['core'] === []) {
            return [];
        }

        $scanLimit = max(100, (int) config('research.discovery.scan_limit', 50000));
        $examineLimit = max(1, (int) config('research.discovery.examine_limit', 3000));
        $chunkSize = max(1, (int) config('research.discovery.examine_chunk', 250));

        $found = $this->finder->find($categoryId, $a, $f, $providers, $scanLimit);
        $bounds = $found['rows'];
        $meta['sql_candidates'] = count($bounds);

        $out = [];
        $broke = false;
        // Kelompok tumbuh (mulai kecil agar berhenti dini bila batas atas sudah memisahkan N teratas, lalu membesar agar query tetap sedikit).
        $offset = 0;
        for ($i = 0; $offset < count($bounds); $i++) {
            $chunk = array_slice($bounds, $offset, min($chunkSize, 25 * (2 ** $i)));
            $offset += count($chunk);
            if ($meta['examined'] > 0) {
                $kth = $this->nthScore($internal, $out, $need);
                // round() sama dengan pembulatan skor: skor sebenarnya <= round(ub) < kth, jadi baris ini dan sisanya tak bisa masuk N teratas.
                if ($kth !== null && round(min(100, $chunk[0]['ub']), 1) < $kth) {
                    $broke = true;

                    break;
                }
                if ($meta['examined'] >= $examineLimit) {
                    $meta['exact'] = false;
                    $broke = true;

                    break;
                }
            }

            $models = ExternalPublication::query()
                ->with(['sources:id,publication_id,provider,external_id,source_url', 'categoryLinks' => fn ($c) => $c->where('category_id', $categoryId)])
                ->whereIn('id', array_column($chunk, 'id'))->get()->keyBy('id');

            foreach ($chunk as $row) {
                $p = $models[$row['id']] ?? null;
                $meta['examined']++;
                if (! $p) {
                    continue;
                }
                if (preg_match(self::WITHDRAWN, (string) $p->title)) {
                    $hidden++;

                    continue;
                }
                $scored = $this->score($this->externalItem($p, $categoryName, $p->categoryLinks->first()?->status), $a);
                $scored && $out[] = $scored;
            }
        }

        // Daftar baris dipotong oleh batas pemindaian: baris di luar daftar punya ub <= ub terakhir. Terbukti hanya bila itu < skor ke-N.
        if ($found['scan_limited'] && ! $broke) {
            $kth = $this->nthScore($internal, $out, $need);
            $last = $bounds === [] ? 0.0 : end($bounds)['ub'];
            $meta['exact'] = $kth !== null && round(min(100, $last), 1) < $kth;
        }

        return $out;
    }

    /** Skor ke-N (urutan resmi) dari gabungan Pustaka + eksternal yang sudah dinilai, setelah dedup; null bila belum ada N item. */
    private function nthScore(array $internal, array $external, int $need): ?float
    {
        $merged = $this->dedupe([...$internal, ...$external]);
        if (count($merged) < $need) {
            return null;
        }
        usort($merged, [self::class, 'compare']);

        return (float) $merged[$need - 1]['score'];
    }

    /** Bentuk seragam satu publikasi eksternal (tanpa skor). */
    public function externalItem(ExternalPublication $p, ?string $categoryName, ?string $linkStatus): array
    {
        $authors = array_values(array_filter((array) $this->arr($p->authors_json)));
        $abstract = trim((string) $p->abstract);
        $evidence = mb_strlen($abstract) >= 40 ? 'abstract' : 'metadata';

        return [
            'key' => $p->doi ? 'doi:'.$p->doi : 'ext:'.$p->id, 'kind' => 'external', 'id' => $p->id, 'title' => (string) $p->title, 'authors' => $authors,
            'year' => $p->publication_year, 'container' => $p->container_title ?: $p->publisher, 'publisher' => $p->publisher,
            'type' => $p->publication_type, 'type_label' => self::TYPE_LABELS[$p->publication_type] ?? 'Jenis lain', 'doi' => $p->doi, 'language' => $p->language,
            'abstract' => $abstract, 'keywords' => array_values(array_filter((array) $this->arr($p->keywords_json))), 'body' => null, 'snippet_source' => null,
            'providers' => $p->sources->pluck('provider')->unique()->values()->all(),
            'provenance' => $p->sources->map(fn ($s) => ['provider' => $s->provider, 'url' => $this->safeUrl($s->source_url)] + $this->sourceId($s->provider, $s->external_id))->values()->all(),
            'landing_url' => $this->safeUrl($p->landing_url), 'fulltext_url' => $this->safeUrl($p->fulltext_url), 'oa_status' => $p->oa_status,
            'category' => $categoryName, 'category_status' => $linkStatus, 'evidence' => $evidence, 'also_external' => false,
        ];
    }

    /** Bentuk seragam satu dokumen Pustaka (tanpa skor). @param array<int,string> $body potongan teks terindeks yang cocok */
    public function internalItem(Document $d, ?string $categoryName, array $body = []): array
    {
        return [
            'key' => $d->doi ? 'doi:'.$d->doi : 'doc:'.$d->id, 'kind' => 'internal', 'id' => $d->id, 'title' => (string) $d->title, 'authors' => [],
            'year' => $d->publication_year, 'container' => $d->publisher ?: $d->source_label, 'publisher' => $d->publisher,
            'type' => 'other', 'type_label' => $d->document_type ? ucfirst((string) $d->document_type) : 'Dokumen Pustaka', 'doi' => $d->doi, 'language' => $d->language,
            'abstract' => trim((string) $d->abstract), 'keywords' => [], 'body' => $body ? implode(' ', $body) : null, 'snippet_source' => $body[0] ?? null,
            'providers' => ['pustaka'], 'provenance' => [['provider' => 'pustaka', 'url' => null]], 'landing_url' => null, 'fulltext_url' => null, 'oa_status' => null,
            'category' => $categoryName, 'category_status' => 'verified', 'evidence' => 'full_text_indexed', 'also_external' => false,
        ];
    }

    /**
     * Satu sumber untuk halaman detail. Bila pertanyaan diberikan dan sumber memenuhi syarat kecocokan, penjelasan relevansi
     * ikut disertakan; bila tidak, skor kosong (tidak ada nilai relevansi yang dikarang). Null = tidak boleh ditampilkan.
     */
    public function describe(string $kind, int $id, ?int $categoryId, string $query): ?array
    {
        $category = $categoryId ? Category::query()->where('status', 'active')->find($categoryId) : null;
        if ($kind === 'doc') {
            $d = Document::query()->eligibleForResearch()->with('category:id,name')->find($id);
            $c = $d ? $this->internalItem($d, $category?->name ?? $d->category?->name) : null;
        } else {
            $p = ExternalPublication::query()->with(['sources:id,publication_id,provider,external_id,source_url', 'categoryLinks.category:id,name'])
                ->whereHas('sources', fn ($s) => $s->whereIn('provider', $this->enabledProviders() ?: ['-']))->find($id);
            if ($p && preg_match(self::WITHDRAWN, (string) $p->title)) {
                $p = null;
            }
            $link = $p?->categoryLinks->first(fn ($l) => ! $categoryId || (int) $l->category_id === $categoryId) ?? $p?->categoryLinks->first();
            $c = $p ? $this->externalItem($p, $link?->category?->name, $link?->status) : null;
        }
        if (! $c) {
            return null;
        }

        $a = $this->analyzer->analyze($query);

        return ($a['core'] !== [] ? $this->score($c, $a) : null) ?? $this->unscored($c);
    }

    /** Tampilan tanpa skor: fakta apa adanya, tanpa penjelasan relevansi. */
    private function unscored(array $c): array
    {
        $future = ! empty($c['year']) && $c['year'] > (int) now()->year;
        unset($c['body'], $c['snippet_source']);

        return $c + [
            'score' => null, 'score_parts' => [], 'reasons' => [], 'matched' => ['title' => [], 'abstract' => [], 'keywords' => [], 'body' => []],
            'flags' => $future ? [['key' => 'future_year', 'label' => 'Tahun '.$c['year'].' (masa depan): perlu verifikasi', 'level' => 'warn']] : [],
            'completeness' => 0, 'tied_with' => 0, 'evidence_rank' => ['metadata' => 0, 'abstract' => 1, 'full_text_indexed' => 2][$c['evidence']],
            'evidence_label' => self::EVIDENCE_LABELS[$c['evidence']], 'snippet' => null,
            'doi_url' => $c['doi'] ? 'https://doi.org/'.implode('/', array_map('rawurlencode', explode('/', $c['doi']))) : null,
        ];
    }

    /* ----------------------------------------------------------------------------------- internal */

    /**
     * Dokumen Pustaka kategori ini yang memuat setidaknya satu istilah (judul, abstrak, atau isi chunk). Tidak ada pemotongan diam-diam:
     * bila `internal_limit` tercapai, `exact` = false.
     *
     * @param  array{sql_candidates:int, examined:int, exact:bool}  $meta
     */
    private function internal(int $categoryId, array $a, array $f, ?string $categoryName, array &$meta): array
    {
        if ($a['core'] === []) {
            return [];
        }

        $limit = max(1, (int) config('research.discovery.internal_limit', 2000));
        $any = fn ($q) => collect($a['core'])->each(function (string $t) use ($q): void {
            $like = QueryAnalyzer::like(QueryAnalyzer::stem($t));
            $q->orWhereRaw("lower(documents.title) like ? escape '!'", [$like])
                ->orWhereRaw("lower(coalesce(documents.abstract,'')) like ? escape '!'", [$like])
                ->orWhereRaw("exists (select 1 from document_chunks dc where dc.document_id = documents.id and lower(dc.content) like ? escape '!')", [$like]);
        });

        $docs = Document::query()->eligibleForResearch()->where('category_id', $categoryId)->where($any)
            ->when(filled($f['year'] ?? null), fn ($q) => $q->where('publication_year', $f['year']))
            ->when(filled($f['language'] ?? null), fn ($q) => $q->where('language', $f['language']))
            ->orderBy('id')->limit($limit)
            ->get(['id', 'title', 'abstract', 'document_type', 'publication_year', 'language', 'doi', 'publisher', 'source_label']);

        $docs->count() >= $limit && $meta['exact'] = false;

        if ($docs->isEmpty()) {
            return [];
        }

        // Cuplikan dan bukti teks memakai pengambil chunk yang SAMA dengan Research Workspace (tanpa mesin baru).
        $chunks = [];
        try {
            foreach ($this->retriever->retrieve($docs->pluck('id')->all(), implode(' ', $a['terms'])) as $row) {
                $chunks[$row['chunk']->document_id][] = $row['chunk']->content;
            }
        } catch (Throwable) {
            $chunks = [];
        }

        $out = [];
        foreach ($docs as $d) {
            $c = $this->internalItem($d, $categoryName, array_slice($chunks[$d->id] ?? [], 0, 3));
            $scored = $this->score($c, $a);
            $scored && $out[] = $scored;
        }

        return $out;
    }

    /* ----------------------------------------------------------------------------------- skor */

    /** @return ?array kandidat + skor/penjelasan, atau null bila tidak layak (istilah inti tidak cukup cocok) */
    public function score(array $c, array $a): ?array
    {
        $W = self::WEIGHTS;
        $core = $a['core'];
        $n = count($core);
        if ($n === 0) {
            return null;
        }

        $titleN = QueryAnalyzer::normalize($c['title']);
        $absN = QueryAnalyzer::normalize($c['abstract'] ?? '');
        $kwN = QueryAnalyzer::normalize(implode(' ', $c['keywords'] ?? []));
        $bodyN = QueryAnalyzer::normalize((string) ($c['body'] ?? ''));
        $tok = [
            'title' => array_flip(QueryAnalyzer::tokens($titleN)), 'abstract' => array_flip(QueryAnalyzer::tokens($absN)),
            'keywords' => array_flip(QueryAnalyzer::tokens($kwN)), 'body' => array_flip(QueryAnalyzer::tokens($bodyN)),
        ];
        $has = fn (string $field, string $t) => isset($tok[$field][$t]) || isset($tok[$field][$t.'s']) || (str_ends_with($t, 's') && isset($tok[$field][substr($t, 0, -1)]));

        $matched = ['title' => [], 'abstract' => [], 'keywords' => [], 'body' => []];
        $any = [];
        foreach ($core as $t) {
            foreach ($matched as $field => $_) {
                if ($has($field, $t)) {
                    $matched[$field][] = $t;
                    $any[$t] = true;
                }
            }
        }

        $required = $n <= 2 ? $n : max(2, (int) ceil($n / 2));
        if (count($any) < $required) {
            return null;
        }

        $phrases = $a['phrases'];
        $np = max(1, count($phrases));
        $inTitle = array_values(array_filter($phrases, fn ($p) => QueryAnalyzer::hasPhrase($titleN, $p)));
        $inAbs = array_values(array_filter($phrases, fn ($p) => QueryAnalyzer::hasPhrase($absN, $p)));

        // Fokus judul: porsi kata bermakna di judul yang merupakan istilah pencarian (judul yang hampir seluruhnya topik > judul panjang).
        $titleWords = QueryAnalyzer::contentTokens($titleN);
        $queryTerms = array_flip([...$core, ...$a['generic']]);
        $focusHit = count(array_filter($titleWords, fn ($t) => isset($queryTerms[$t]) || isset($queryTerms[$t.'s']) || (str_ends_with($t, 's') && isset($queryTerms[substr($t, 0, -1)]))));
        $focus = $titleWords && $matched['title'] ? min(1, $focusHit / count($titleWords)) : 0;

        // Pengulangan di abstrak: berapa kali frasa (atau, tanpa frasa, istilah inti) muncul; jenuh pada 4 kali.
        $absPadded = ' '.$absN.' ';
        $phraseCounts = [];
        foreach ($inAbs as $p) {
            $phraseCounts[$p] = substr_count($absPadded, ' '.$p.' ');
        }
        $repeat = $phrases ? array_sum($phraseCounts) : array_sum(array_map(fn ($t) => substr_count($absPadded, ' '.$t.' '), $core));

        $parts = [
            'title_coverage' => $W['title_coverage'] * count($matched['title']) / $n,
            'title_phrase' => $phrases ? $W['title_phrase'] * count($inTitle) / $np : 0,
            'title_focus' => $W['title_focus'] * $focus,
            'abstract_coverage' => $W['abstract_coverage'] * count($matched['abstract']) / $n,
            'abstract_phrase' => $phrases ? $W['abstract_phrase'] * count($inAbs) / $np : 0,
            'abstract_repeat' => $W['abstract_repeat'] * min(4, $repeat) / 4,
            'keywords' => $W['keywords'] * count($matched['keywords']) / $n,
            'body' => $W['body'] * count($matched['body']) / $n,
        ];

        // Ketersediaan bukti (abstrak/teks terindeks) SENGAJA tidak ikut skor: ia dipakai terpisah sebagai kunci urut kedua.
        $status = $c['category_status'] ?? null;
        $parts['category'] = $status === 'verified' ? $W['category_verified'] : ($status === 'suggested' ? $W['category_suggested'] : 0);

        $completeness = (int) ! empty($c['authors']) + (int) ! empty($c['year']) + (int) ! empty($c['container']) + (int) ! empty($c['doi']);
        $parts['completeness'] = $completeness * $W['completeness_each'];

        $flags = [];
        $currentYear = (int) now()->year;
        if (! empty($c['year']) && $c['year'] > $currentYear) {
            // Metadata masa depan: tidak mendapat poin kecocokan tahun dan diberi penalti; tampil dengan peringatan.
            $parts['penalty_future'] = -$W['penalty_future_year'];
            $flags[] = ['key' => 'future_year', 'label' => 'Tahun '.$c['year'].' (masa depan): perlu verifikasi', 'level' => 'warn'];
        } elseif (! empty($a['year']) && ($c['year'] ?? null) === $a['year']) {
            $parts['year_hint'] = $W['year_hint'];
        }
        if (! empty($a['language']) && ($c['language'] ?? null) === $a['language']) {
            $parts['language_hint'] = $W['language_hint'];
        }
        if (($c['type'] ?? null) === 'other' && $c['kind'] === 'external') {
            $parts['penalty_type'] = -$W['penalty_other_type'];
            $flags[] = ['key' => 'other_type', 'label' => 'Bukan artikel/buku/prosiding (mis. entri rujukan)', 'level' => 'info'];
        }
        empty($c['year']) && $flags[] = ['key' => 'missing_year', 'label' => 'Tanpa tahun', 'level' => 'warn'];
        empty($c['doi']) && $flags[] = ['key' => 'missing_doi', 'label' => 'Tanpa DOI', 'level' => 'info'];
        empty($c['authors']) && $c['kind'] === 'external' && $flags[] = ['key' => 'missing_authors', 'label' => 'Tanpa penulis', 'level' => 'warn'];

        // Alasan relevansi: fakta tentang kecocokan metadata/teks, bukan klaim tentang temuan penelitian.
        $reasons = [];
        foreach ($inTitle as $p) {
            $reasons[] = "Frasa «{$p}» ada di judul";
        }
        if ($matched['title']) {
            $reasons[] = 'Istilah di judul: '.implode(', ', $matched['title']).' ('.count($matched['title'])."/{$n}); mencakup {$focusHit} dari ".count($titleWords).' kata bermakna di judul';
        }
        foreach ($inAbs as $p) {
            $reasons[] = $phraseCounts[$p] > 1 ? "Frasa «{$p}» muncul {$phraseCounts[$p]} kali di abstrak" : "Frasa «{$p}» ada di abstrak";
        }
        if (array_diff($matched['abstract'], $matched['title'])) {
            $reasons[] = 'Istilah di abstrak: '.implode(', ', array_diff($matched['abstract'], $matched['title']));
        }
        $matched['keywords'] && $reasons[] = 'Kata kunci cocok: '.implode(', ', $matched['keywords']);
        $matched['body'] && $reasons[] = 'Istilah di teks terindeks: '.implode(', ', $matched['body']);
        $reasons[] = $status === 'verified' ? 'Sesuai kategori '.$c['category'].' (terverifikasi admin)' : 'Sesuai kategori '.$c['category'].' (disarankan, belum diverifikasi admin)';
        $reasons[] = ['metadata' => 'Abstrak tidak tersimpan: hanya metadata', 'abstract' => 'Abstrak tersedia (ketersediaan bukti, bukan penilaian mutu)', 'full_text_indexed' => 'Teks dokumen terindeks di Pustaka Internal'][$c['evidence']];

        $c += [
            'score' => round(max(0, min(100, array_sum($parts))), 1), 'score_parts' => array_map(fn ($v) => round($v, 1), $parts),
            'reasons' => $reasons, 'matched' => $matched, 'flags' => $flags, 'completeness' => $completeness, 'tied_with' => 0,
            'evidence_rank' => ['metadata' => 0, 'abstract' => 1, 'full_text_indexed' => 2][$c['evidence']],
            'evidence_label' => self::EVIDENCE_LABELS[$c['evidence']],
            'snippet' => $this->snippet($c['snippet_source'] ?: $c['abstract'], array_values(array_unique([...$a['core'], ...$a['generic']]))),
            'doi_url' => $c['doi'] ? 'https://doi.org/'.implode('/', array_map('rawurlencode', explode('/', $c['doi']))) : null,
        ];
        unset($c['body'], $c['snippet_source']);

        return $c;
    }

    /** Cuplikan ASLI di sekitar kecocokan pertama (bukan ringkasan buatan). */
    private function snippet(?string $text, array $terms, int $len = 240): ?string
    {
        $text = trim(preg_replace('/\s+/u', ' ', (string) $text) ?? '');
        if ($text === '') {
            return null;
        }
        $pos = null;
        foreach ($terms as $t) {
            $p = mb_stripos($text, $t);
            if ($p !== false && ($pos === null || $p < $pos)) {
                $pos = $p;
            }
        }
        $start = max(0, ($pos ?? 0) - 70);
        $cut = mb_substr($text, $start, $len);
        $more = $start + $len < mb_strlen($text);

        // Potong di batas kata (jangan memulai/mengakhiri di tengah kata).
        if ($start > 0 && ($sp = mb_strpos($cut, ' ')) !== false && $sp < 40) {
            $cut = mb_substr($cut, $sp + 1);
        }
        if ($more && ($sp = mb_strrpos($cut, ' ')) !== false && $sp > mb_strlen($cut) - 40) {
            $cut = mb_substr($cut, 0, $sp);
        }

        return ($start > 0 ? '… ' : '').$cut.($more ? ' …' : '');
    }

    /* ----------------------------------------------------------------------------------- dedup */

    /**
     * Kunci kanonik = DOI (huruf kecil). Judul yang sama dengan DOI berbeda TIDAK digabung. Bila Pustaka dan Sumber Eksternal
     * memuat DOI yang sama, entri Pustaka dipertahankan (punya bukti teks) dan penyedia eksternal dicatat sebagai asal tambahan.
     */
    private function dedupe(array $items): array
    {
        $byKey = [];
        foreach ($items as $it) {
            $key = $it['doi'] ? 'doi:'.mb_strtolower($it['doi']) : $it['kind'].':'.$it['id'];
            $it['key'] = $it['kind'] === 'internal' ? 'doc:'.$it['id'] : 'ext:'.$it['id'];
            if (! isset($byKey[$key])) {
                $byKey[$key] = $it;

                continue;
            }
            $cur = $byKey[$key];
            [$keep, $other] = $it['kind'] === 'internal' ? [$it, $cur] : [$cur, $it];
            $keep['providers'] = array_values(array_unique([...$keep['providers'], ...$other['providers']]));
            $keep['provenance'] = [...$keep['provenance'], ...$other['provenance']];
            $keep['landing_url'] = $keep['landing_url'] ?: $other['landing_url'];
            $keep['fulltext_url'] = $keep['fulltext_url'] ?: $other['fulltext_url'];
            $keep['score'] = max($keep['score'], $other['score']);
            $keep['also_external'] = $keep['kind'] === 'internal' || $other['kind'] === 'internal';
            $keep['alt_key'] = $other['key'];
            $byKey[$key] = $keep;
        }

        return array_values($byKey);
    }

    /* ----------------------------------------------------------------------------------- util */

    private function arr(mixed $v): array
    {
        if (is_string($v)) {
            $v = json_decode($v, true);
        }

        return is_array($v) ? $v : [];
    }

    /** Identitas di penyedia yang BENAR-BENAR tersimpan. Hanya OpenAlex Work ID berformat resmi (W + angka) yang ditautkan ke OpenAlex. */
    private function sourceId(string $provider, ?string $externalId): array
    {
        $id = trim((string) $externalId);
        if ($provider !== 'openalex' || ! preg_match('/^W\d{1,15}$/', $id)) {
            return [];
        }

        return ['id_label' => 'OpenAlex Work ID', 'id_value' => $id, 'id_url' => 'https://openalex.org/'.$id];
    }

    private function safeUrl(?string $url): ?string
    {
        $url = trim((string) $url);

        return $url !== '' && mb_strlen($url) <= 1000 && preg_match('~^https?://[^\s<>"\']+$~i', $url) ? $url : null;
    }
}
