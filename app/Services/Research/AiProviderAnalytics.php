<?php

namespace App\Services\Research;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Analitik penggunaan per provider dan model untuk "Provider & Analitik AI".
 *
 * Sumber data SAMA dengan Beranda: ai_runs (provider, model, status) + usage yang dilaporkan provider di
 * ai_run_results (provider_meta.usage). Parsing token dan tarif memakai AiUsageSummary (tokensFrom/runUsd) agar kedua
 * halaman tidak pernah berselisih. Tidak ada tabel usage kedua dan tidak ada angka perkiraan.
 *
 * Aturan data (sama dengan Beranda): null = tidak diketahui, 0 = terverifikasi nol. Run mock tidak dihitung sebagai
 * pemakaian API. Satu run = satu panggilan provider = satu hitungan. Token cache adalah bagian dari input; total = input + output.
 * Hari sebelum pencatatan usage pertama bernilai null ("tidak ada data"), bukan nol, supaya grafik tidak mengarang riwayat.
 */
class AiProviderAnalytics
{
    public const PERIODS = ['today' => 'Hari ini', '7d' => '7 hari', '30d' => '30 hari', 'month' => 'Bulan ini', 'custom' => 'Rentang tanggal'];

    /** Batas rentang kustom (hari) agar permintaan tidak tak terbatas. */
    public const MAX_CUSTOM_DAYS = 366;

    public function __construct(private readonly AiUsageSummary $usage) {}

    /**
     * Rentang dalam WIB; end null = terbuka sampai sekarang. Query memakai `>= start` dan `< end`.
     *
     * @return array{start: Carbon, end: ?Carbon, label: string, text: string, hourly: bool, period: string, from: ?string, to: ?string}
     */
    public function range(string $period, ?string $from = null, ?string $to = null): array
    {
        $tz = AiUsageSummary::TIMEZONE;

        if ($period === 'custom') {
            $start = $this->date($from) ?? now($tz)->startOfDay()->subDays(29);
            $last = $this->date($to) ?? now($tz)->startOfDay();
            $today = now($tz)->startOfDay();

            $last = $last->gt($today) ? $today : $last;
            $start = $start->gt($last) ? $last->copy() : $start;

            if ($start->diffInDays($last) >= self::MAX_CUSTOM_DAYS) {
                $start = $last->copy()->subDays(self::MAX_CUSTOM_DAYS - 1);
            }

            $fmt = fn (Carbon $c) => $c->copy()->locale('id')->translatedFormat('j M Y');
            // end sampai awal hari setelah `last`, kecuali hari ini (terbuka sampai sekarang).
            $end = $last->eq($today) ? null : $last->copy()->addDay();

            return ['start' => $start, 'end' => $end, 'label' => 'Rentang tanggal', 'text' => $fmt($start).' – '.$fmt($last).' (WIB)', 'hourly' => false, 'period' => 'custom', 'from' => $start->format('Y-m-d'), 'to' => $last->format('Y-m-d')];
        }

        $period = array_key_exists($period, self::PERIODS) && $period !== 'custom' ? $period : '30d';
        $base = $this->usage->range($period);

        return ['start' => $base['start'], 'end' => $base['end'], 'label' => $base['label'], 'text' => $base['text'], 'hourly' => $period === 'today', 'period' => $period, 'from' => null, 'to' => null];
    }

    private function date(?string $value): ?Carbon
    {
        return $value && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4))
            ? Carbon::createFromFormat('!Y-m-d', $value, AiUsageSummary::TIMEZONE)
            : null;
    }

    /**
     * @param  array<string, array<string, mixed>>  $catalog  provider terintegrasi (id => info)
     * @return array<string, mixed>
     */
    public function build(array $range, array $catalog): array
    {
        $hourly = $range['hourly'];
        $buckets = $this->buckets($range);
        $empty = [
            'range' => ['label' => $range['label'], 'text' => $range['text'], 'hourly' => $hourly, 'period' => $range['period'], 'from' => $range['from'], 'to' => $range['to']],
            'summary' => $this->summaryOf([], 0, $this->cost(null, 0, [])),
            'providers' => [],
            'chart' => $this->chart([], $buckets, null),
        ];

        foreach ($catalog as $id => $info) {
            $empty['providers'][$id] = $this->providerOf($id, []);
        }

        if (! Schema::hasTable('ai_runs') || ! Schema::hasColumn('ai_runs', 'model_provider')) {
            return $empty;
        }

        $withResults = Schema::hasTable('ai_run_results');
        $start = $range['start']->copy()->setTimezone(config('app.timezone'));
        $end = $range['end']?->copy()->setTimezone(config('app.timezone'));

        $query = DB::table('ai_runs as a')
            ->where('a.created_at', '>=', $start)
            ->when($end, fn ($q) => $q->where('a.created_at', '<', $end))
            ->whereNotNull('a.model_provider')
            ->select('a.id', 'a.created_at', 'a.status', 'a.model_provider', 'a.model_name');

        if ($withResults) {
            $query
                ->leftJoin('ai_run_results as r', fn ($join) => $join->on('r.ai_run_id', '=', 'a.id')->where('r.result_type', 'primary'))
                ->addSelect('r.structured_output_json->provider_meta->usage as usage');
        }

        $groups = [];
        $mock = 0;
        $seen = [];

        foreach ($query->cursor() as $row) {
            if (isset($seen[$row->id])) {
                continue; // satu run = satu hitungan walau punya lebih dari satu hasil primary
            }

            $seen[$row->id] = true;

            if ($row->model_provider === 'mock') {
                $mock++;

                continue;
            }

            $model = trim((string) $row->model_name) ?: 'tanpa nama model';
            $gid = $row->model_provider.'|'.$model;
            $g = &$groups[$gid];
            $g ??= ['provider' => $row->model_provider, 'model' => $model, 'runs' => 0, 'usage_runs' => 0, 'input' => 0, 'output' => 0, 'cached' => 0, 'has_cached' => false, 'usd' => 0.0, 'unpriced' => false, 'failed' => 0, 'finished' => 0, 'ok' => 0, 'last' => null, 'buckets' => []];

            $g['runs']++;
            $g['last'] = max((string) $g['last'], (string) $row->created_at);

            if (in_array($row->status, ['failed', 'citation_failed'], true)) {
                $g['failed']++;
                $g['finished']++;
            } elseif (in_array($row->status, ['completed', 'insufficient_evidence'], true)) {
                $g['finished']++;
                $g['ok'] += $row->status === 'completed' ? 1 : 0;
            }

            $key = $this->usage->bucketKey((string) $row->created_at, $hourly);
            $b = &$g['buckets'][$key];
            $b ??= ['runs' => 0, 'input' => 0, 'output' => 0, 'usd' => 0.0, 'unpriced' => false, 'usage_runs' => 0];
            $b['runs']++;

            $tokens = $withResults ? $this->usage->tokensFrom(json_decode((string) ($row->usage ?? ''), true)) : null;

            if ($tokens !== null) {
                $g['usage_runs']++;
                $b['usage_runs']++;
                $g['input'] += (int) $tokens['input'];
                $g['output'] += (int) $tokens['output'];
                $b['input'] += (int) $tokens['input'];
                $b['output'] += (int) $tokens['output'];

                if ($tokens['cached'] !== null) {
                    $g['cached'] += $tokens['cached'];
                    $g['has_cached'] = true;
                }

                $usd = $this->usage->runUsd($row->model_name, $tokens);

                if ($usd === null) {
                    $g['unpriced'] = true;
                    $b['unpriced'] = true;
                } else {
                    $g['usd'] += $usd;
                    $b['usd'] += $usd;
                }
            }

            unset($g, $b);
        }

        return [
            'range' => $empty['range'],
            'summary' => $this->summaryOf($groups, $mock, null),
            'providers' => $this->providers($groups, $catalog),
            'chart' => $this->chart($groups, $buckets, $this->trackedFrom($hourly)),
        ];
    }

    /** Waktu usage pertama yang pernah tercatat (seluruh riwayat, bukan hanya periode ini). */
    private function trackedFrom(bool $hourly): ?string
    {
        if (! Schema::hasTable('ai_run_results')) {
            return null;
        }

        $first = DB::table('ai_runs as a')
            ->join('ai_run_results as r', fn ($join) => $join->on('r.ai_run_id', '=', 'a.id')->where('r.result_type', 'primary'))
            ->whereNotNull('a.model_provider')->where('a.model_provider', '<>', 'mock')
            ->whereNotNull('r.structured_output_json->provider_meta->usage')
            ->min('a.created_at');

        return $first ? $this->usage->bucketKey((string) $first, $hourly) : null;
    }

    /** @return list<array{key: string, l: string, t: string}> */
    private function buckets(array $range): array
    {
        $hourly = $range['hourly'];
        $stop = $range['end'] ? $range['end']->copy()->subSecond() : now(AiUsageSummary::TIMEZONE);
        $cursor = $range['start']->copy();
        $out = [];

        while ($cursor->lte($stop)) {
            $label = $cursor->copy()->locale('id');
            $out[] = [
                'key' => $cursor->format($hourly ? 'Y-m-d H' : 'Y-m-d'),
                'l' => $hourly ? $cursor->format('H.i') : $label->translatedFormat('j M'),
                't' => $hourly ? $label->translatedFormat('j M Y, H.00').' WIB' : $label->translatedFormat('l, j F Y'),
            ];
            $hourly ? $cursor->addHour() : $cursor->addDay();
        }

        return $out;
    }

    private function providers(array $groups, array $catalog): array
    {
        $by = [];

        foreach ($groups as $g) {
            $by[$g['provider']][] = $g;
        }

        $out = [];

        foreach ($catalog as $id => $info) {
            $out[$id] = $this->providerOf($id, $by[$id] ?? []);
            unset($by[$id]);
        }

        // Provider yang pernah tercatat di run tetapi tidak lagi ada di katalog tetap ditampilkan apa adanya.
        foreach ($by as $id => $list) {
            $out[$id] = $this->providerOf($id, $list);
        }

        return $out;
    }

    private function providerOf(string $id, array $groups): array
    {
        $usageRuns = array_sum(array_column($groups, 'usage_runs'));
        $runs = array_sum(array_column($groups, 'runs'));
        $input = array_sum(array_column($groups, 'input'));
        $output = array_sum(array_column($groups, 'output'));
        $finished = array_sum(array_column($groups, 'finished'));
        $models = [];

        foreach ($groups as $g) {
            $models[] = [
                'model' => $g['model'],
                'runs' => $g['runs'],
                'usage_runs' => $g['usage_runs'],
                'input' => $g['usage_runs'] ? $g['input'] : null,
                'output' => $g['usage_runs'] ? $g['output'] : null,
                'cached' => $g['has_cached'] ? $g['cached'] : null,
                'cost' => $this->cost($g['usd'], $g['usage_runs'], $g['unpriced'] ? [$g['model']] : []),
                'last' => $g['last'],
            ];
        }

        usort($models, fn ($a, $b) => $b['runs'] <=> $a['runs']);

        $unpriced = array_column(array_filter($groups, fn ($g) => $g['unpriced']), 'model');

        return [
            'id' => $id,
            'runs' => $runs,
            'usage_runs' => $usageRuns,
            'input' => $usageRuns ? $input : null,
            'output' => $usageRuns ? $output : null,
            'cached' => array_sum(array_column($groups, 'has_cached')) ? array_sum(array_column($groups, 'cached')) : null,
            'total' => $usageRuns ? $input + $output : null,
            'partial' => $usageRuns > 0 && $usageRuns < $runs,
            'failed' => array_sum(array_column($groups, 'failed')),
            'success_rate' => $finished ? round(array_sum(array_column($groups, 'ok')) / $finished * 100, 1) : null,
            'cost' => $this->cost(array_sum(array_column($groups, 'usd')), $usageRuns, $unpriced),
            'models' => $models,
            'last' => $groups ? max(array_column($groups, 'last')) : null,
        ];
    }

    private function summaryOf(array $groups, int $mock, ?array $cost): array
    {
        $usageRuns = array_sum(array_column($groups, 'usage_runs'));
        $runs = array_sum(array_column($groups, 'runs'));
        $input = array_sum(array_column($groups, 'input'));
        $output = array_sum(array_column($groups, 'output'));
        $unpriced = array_values(array_unique(array_column(array_filter($groups, fn ($g) => $g['unpriced']), 'model')));

        return [
            'provider_runs' => $runs,
            'mock_runs' => $mock,
            'usage_runs' => $usageRuns,
            'input' => $usageRuns ? $input : null,
            'output' => $usageRuns ? $output : null,
            'total' => $usageRuns ? $input + $output : null,
            'cached' => array_sum(array_column($groups, 'has_cached')) ? array_sum(array_column($groups, 'cached')) : null,
            'partial' => $usageRuns > 0 && $usageRuns < $runs,
            'cost' => $cost ?? $this->cost(array_sum(array_column($groups, 'usd')), $usageRuns, $unpriced),
        ];
    }

    /**
     * Biaya USD (basis perhitungan) + estimasi IDR bila kurs lengkap. Null dengan alasan bila bahannya belum lengkap.
     *
     * @return array{usd: ?float, idr: ?float, reason: ?string, pricing_source: mixed, pricing_verified_at: mixed, fx_source: mixed, fx_as_of: mixed}
     */
    private function cost(?float $usd, int $usageRuns, array $unpriced): array
    {
        $fx = (array) config('research.fx');
        $rate = is_numeric($fx['usd_idr'] ?? null) && (float) $fx['usd_idr'] > 0 ? (float) $fx['usd_idr'] : null;

        $reason = match (true) {
            $usd === null || $usageRuns === 0 => 'Belum ada penggunaan token tercatat pada periode ini.',
            (array) config('research.pricing.models', []) === [] => 'Tarif model belum dikonfigurasi di sistem.',
            $unpriced !== [] => 'Model tanpa tarif: '.implode(', ', $unpriced).'.',
            ! $this->usage->pricingVerified() => 'Sumber dan tanggal verifikasi tarif belum diisi.',
            default => null,
        };

        $fxOk = $rate !== null && filled($fx['source'] ?? null) && filled($fx['as_of'] ?? null);

        return [
            'usd' => $reason === null ? round($usd, 4) : null,
            'idr' => $reason === null && $fxOk ? round($usd * $rate) : null,
            'reason' => $reason,
            'pricing_source' => config('research.pricing.source'),
            'pricing_verified_at' => config('research.pricing.verified_at'),
            'fx_source' => $fxOk ? $fx['source'] : null,
            'fx_as_of' => $fxOk ? $fx['as_of'] : null,
        ];
    }

    /**
     * Deret grafik per provider+model dan per metrik. Nilai null = tidak ada data (sebelum pencatatan dimulai, atau biaya
     * tidak bisa dihitung); angka 0 = tercatat dan benar-benar nol.
     */
    private function chart(array $groups, array $buckets, ?string $trackedFrom): array
    {
        $series = [];
        $reasons = [];
        $order = array_keys($groups);

        sort($order);

        foreach ($order as $gid) {
            $g = $groups[$gid];
            $reason = $this->cost($g['usd'], $g['usage_runs'], $g['unpriced'] ? [$g['model']] : [])['reason'];
            $reasons[] = $g['usage_runs'] > 0 ? $reason : null;
            $priced = $reason === null;
            $m = ['input' => [], 'output' => [], 'total' => [], 'cost' => [], 'runs' => []];

            foreach ($buckets as $bucket) {
                $known = $trackedFrom !== null && $bucket['key'] >= $trackedFrom;
                $b = $g['buckets'][$bucket['key']] ?? null;

                $m['runs'][] = $known || $b ? ($b['runs'] ?? 0) : null;
                $m['input'][] = $known || $b ? ($b['input'] ?? 0) : null;
                $m['output'][] = $known || $b ? ($b['output'] ?? 0) : null;
                $m['total'][] = $known || $b ? (($b['input'] ?? 0) + ($b['output'] ?? 0)) : null;
                $m['cost'][] = $priced && ($known || $b) && ! ($b['unpriced'] ?? false) ? round($b['usd'] ?? 0, 6) : null;
            }

            $series[] = ['id' => $gid, 'provider' => $g['provider'], 'model' => $g['model'], 'priced' => $priced, 'metrics' => $m];
        }

        $hasTokens = array_sum(array_column($groups, 'usage_runs')) > 0;

        return [
            'buckets' => array_map(fn ($b) => ['l' => $b['l'], 't' => $b['t']], $buckets),
            'series' => $series,
            'tracked_from' => $trackedFrom ? ($buckets[array_search($trackedFrom, array_column($buckets, 'key'), true)]['t'] ?? null) : null,
            'tracked_before_range' => $trackedFrom !== null && $buckets !== [] && $trackedFrom < $buckets[0]['key'],
            'empty' => $groups === [],
            'has_tokens' => $hasTokens,
            // Alasan pertama mengapa biaya sebagian model belum bisa dihitung (null bila semua bertarif).
            'cost_note' => collect($reasons)->filter()->first(),
        ];
    }
}
