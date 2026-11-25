<?php

namespace App\Services\Research;

use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ringkasan run riset untuk Beranda Admin. Satu-satunya tempat rumus periode dan agregasi,
 * dipakai render awal Blade dan endpoint JSON agar angkanya tidak pernah berbeda.
 *
 * Aturan data: null = tidak diketahui (UI "—"), 0 = terverifikasi nol. Run mock tidak dihitung sebagai pemakaian API.
 * Biaya hanya dihitung bila tarif model dan kurs diisi di config('research.pricing'|'research.fx') beserta
 * sumber dan tanggalnya; selain itu null dengan alasan. Tidak ada tarif atau kurs bawaan.
 */
class AiUsageSummary
{
    public const TIMEZONE = 'Asia/Jakarta';

    public const PERIODS = [
        'today' => 'Hari ini',
        '7d' => '7 hari',
        '30d' => '30 hari',
        'month' => 'Bulan ini',
        'history' => 'Riwayat',
    ];

    /** Batas histori yang boleh diminta (mencegah rentang tak terbatas). */
    private const HISTORY_MONTHS_MAX = 36;

    /** Minimal jumlah bulan sebelumnya yang ditawarkan walau belum ada histori. */
    private const HISTORY_MONTHS_MIN = 6;

    /**
     * Rapikan input URL untuk render halaman: nilai tidak valid jatuh ke default (Bulan ini).
     *
     * @return array{0: string, 1: ?string}
     */
    public function normalize(?string $period, ?string $month): array
    {
        if (! array_key_exists((string) $period, self::PERIODS)) {
            return ['month', null];
        }

        if ($period !== 'history') {
            return [$period, null];
        }

        // Default Riwayat = bulan lalu (months() selalu berisi minimal 6 bulan).
        return ['history', $this->isValidMonth((string) $month) ? $month : $this->months()[0]['value']];
    }

    /** Bulan (YYYY-MM) valid: bukan masa depan dan tidak lebih lama dari batas histori. */
    public function isValidMonth(string $month): bool
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            return false;
        }

        $start = $this->monthStart($month);

        return $start->lte($this->currentMonth())
            && $start->gte($this->currentMonth()->subMonths(self::HISTORY_MONTHS_MAX));
    }

    /**
     * Rentang periode dalam WIB; end null = terbuka sampai sekarang. Query memakai `>= start` dan `< end`.
     *
     * @return array{start: Carbon, end: ?Carbon, label: string, text: string}
     */
    public function range(string $period, ?string $month = null): array
    {
        $day = now(self::TIMEZONE)->startOfDay();
        $end = null;

        if ($period === 'history' && $month !== null) {
            $start = $this->monthStart($month);
            $end = $start->copy()->addMonth();
        } else {
            $start = match ($period) {
                'today' => $day,
                '7d' => $day->copy()->subDays(6),
                '30d' => $day->copy()->subDays(29),
                default => $day->copy()->startOfMonth(),
            };
        }

        $date = fn (Carbon $c, string $format) => $c->copy()->locale('id')->translatedFormat($format);

        return [
            'start' => $start,
            'end' => $end,
            'label' => $period === 'history' && $month !== null ? $date($start, 'F Y') : self::PERIODS[$period] ?? 'Bulan ini',
            'text' => $end
                ? $date($start, 'j F Y').' – '.$date($end->copy()->subDay(), 'j F Y').' (WIB)'
                : $date($start, 'j M Y, H.i').' WIB – sekarang',
        ];
    }

    /**
     * Pilihan bulan untuk Riwayat, terbaru dulu: dari bulan lalu sampai histori run tertua (minimal 6 bulan, maksimal 36).
     *
     * @return array<int, array{value: string, label: string, year: int}>
     */
    public function months(): array
    {
        $current = $this->currentMonth();
        $floor = $current->copy()->subMonths(self::HISTORY_MONTHS_MIN);
        $earliest = $this->earliestRunMonth();
        $from = $earliest && $earliest->lt($floor) ? $earliest : $floor;
        $oldest = $current->copy()->subMonths(self::HISTORY_MONTHS_MAX);

        if ($from->lt($oldest)) {
            $from = $oldest;
        }

        $months = [];

        for ($m = $current->copy()->subMonth(); $m->gte($from); $m->subMonth()) {
            $months[] = [
                'value' => $m->format('Y-m'),
                'label' => $m->copy()->locale('id')->translatedFormat('F Y'),
                'year' => (int) $m->format('Y'),
            ];
        }

        return $months;
    }

    /**
     * Deret kosong per jam (Hari ini) atau per hari, dalam WIB, dari awal rentang sampai sekarang / akhir rentang.
     * Dipakai ulang untuk tren sumber agar sumbu waktu semua grafik sama.
     *
     * @return array{0: bool, 1: array<string, array<string, mixed>>} [per jam?, titik]
     */
    public function emptySeries(string $period, ?string $month = null): array
    {
        $range = $this->range($period, $month);
        $hourly = $period === 'today';
        $stop = $range['end'] ? $range['end']->copy()->subSecond() : now(self::TIMEZONE);
        $cursor = $range['start']->copy();
        $points = [];

        while ($cursor->lte($stop)) {
            $label = $cursor->copy()->locale('id');

            $points[$cursor->format($hourly ? 'Y-m-d H' : 'Y-m-d')] = [
                'l' => $hourly ? $cursor->format('H.i') : $label->translatedFormat('j M'),
                't' => $hourly ? $label->translatedFormat('j M, H.00').' WIB' : $label->translatedFormat('l, j F Y'),
                'input' => 0, 'output' => 0, 'runs' => 0, 'ok' => 0, 'fail' => 0, 'prompt' => 0, 'count' => 0,
            ];

            $hourly ? $cursor->addHour() : $cursor->addDay();
        }

        return [$hourly, $points];
    }

    /** Kunci titik deret untuk timestamp database (zona penyimpanan) -> WIB. */
    public function bucketKey(string $storedAt, bool $hourly): string
    {
        return Carbon::parse($storedAt, config('app.timezone'))->setTimezone(self::TIMEZONE)->format($hourly ? 'Y-m-d H' : 'Y-m-d');
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(string $period, ?string $month = null): array
    {
        $range = $this->range($period, $month);
        [$hourly, $series] = $this->emptySeries($period, $month);

        $usage = [
            'period' => $period,
            'month' => $period === 'history' ? $month : null,
            'label' => $range['label'],
            'range_text' => $range['text'],
            'range_start' => $range['start']->toIso8601String(),
            'range_end' => $range['end']?->toIso8601String(),
            'provider_runs' => null,
            'mock_runs' => null,
            'usage_runs' => 0,
            'input' => null,
            'output' => null,
            'total_tokens' => null,
            'cached' => null,
            'partial' => false,
            'runs_total' => null,
            'runs_ok' => null,
            'runs_failed' => null,
            'runs_insufficient' => null,
            'success_rate' => null,
            'prompt_runs' => null,
            'series' => array_values($series),
            'tools' => [],
            'prompts' => [],
            'cost' => $this->cost(null, false, 0, 0, []),
        ];

        if (! Schema::hasTable('ai_runs') || ! Schema::hasColumn('ai_runs', 'model_provider')) {
            return $usage;
        }

        // Batas WIB dikonversi ke zona penyimpanan created_at sebelum dibandingkan.
        $start = $range['start']->copy()->setTimezone(config('app.timezone'));
        $end = $range['end']?->copy()->setTimezone(config('app.timezone'));

        $query = DB::table('ai_runs as a')
            ->where('a.created_at', '>=', $start)
            ->when($end, fn ($q) => $q->where('a.created_at', '<', $end))
            ->select('a.id', 'a.created_at', 'a.status', 'a.model_provider', 'a.model_name', 'a.ai_tool_id', 'a.prompt_template_id');

        $withResults = Schema::hasTable('ai_run_results');

        if ($withResults) {
            $query
                ->leftJoin('ai_run_results as r', fn ($join) => $join->on('r.ai_run_id', '=', 'a.id')->where('r.result_type', 'primary'))
                ->addSelect('r.structured_output_json->provider_meta->usage as usage');
        }

        $prices = array_change_key_case((array) config('research.pricing.models', []));
        $seen = [];
        $tally = ['total' => 0, 'ok' => 0, 'failed' => 0, 'insufficient' => 0, 'provider' => 0, 'mock' => 0, 'prompt' => 0];
        $input = $output = $cached = 0;
        $hasInput = $hasOutput = $hasCached = false;
        $usd = 0.0;
        $unpriced = [];
        $toolCounts = $promptCounts = [];

        foreach ($query->cursor() as $row) {
            if (isset($seen[$row->id])) {
                continue; // satu run = satu hitungan walau punya lebih dari satu hasil primary
            }

            $seen[$row->id] = true;
            $key = $this->bucketKey((string) $row->created_at, $hourly);
            $inSeries = isset($series[$key]);
            $tally['total']++;
            $inSeries && $series[$key]['runs']++;

            if ($row->status === 'completed') {
                $tally['ok']++;
                $inSeries && $series[$key]['ok']++;
            } elseif (in_array($row->status, ['failed', 'citation_failed'], true)) {
                $tally['failed']++;
                $inSeries && $series[$key]['fail']++;
            } elseif ($row->status === 'insufficient_evidence') {
                $tally['insufficient']++;
            }

            if ($row->ai_tool_id) {
                $toolCounts[$row->ai_tool_id] = ($toolCounts[$row->ai_tool_id] ?? 0) + 1;
            }

            if ($row->prompt_template_id) {
                $tally['prompt']++;
                $promptCounts[$row->prompt_template_id] = ($promptCounts[$row->prompt_template_id] ?? 0) + 1;
                $inSeries && $series[$key]['prompt']++;
            }

            if ($row->model_provider === null) {
                continue;
            }

            if ($row->model_provider === 'mock') {
                $tally['mock']++;

                continue;
            }

            $tally['provider']++;
            $meta = $withResults ? json_decode((string) ($row->usage ?? ''), true) : null;

            if (! is_array($meta)) {
                continue;
            }

            $tokens = $this->tokensFrom($meta);

            if ($tokens === null) {
                continue;
            }

            [$in, $out, $cache] = [$tokens['input'], $tokens['output'], $tokens['cached']];

            $usage['usage_runs']++;

            if (is_numeric($in)) {
                $input += (int) $in;
                $hasInput = true;
                $inSeries && $series[$key]['input'] += (int) $in;
            }

            if (is_numeric($out)) {
                $output += (int) $out;
                $hasOutput = true;
                $inSeries && $series[$key]['output'] += (int) $out;
            }

            if (is_numeric($cache)) {
                $cached += (int) $cache;
                $hasCached = true;
            }

            $runUsd = $this->runUsd($row->model_name, $tokens);

            if ($runUsd !== null) {
                $usd += $runUsd;
            } else {
                $unpriced[(string) ($row->model_name ?: 'tanpa nama model')] = true;
            }
        }

        $usage['provider_runs'] = $tally['provider'];
        $usage['mock_runs'] = $tally['mock'];
        $usage['input'] = $hasInput ? $input : null;
        $usage['output'] = $hasOutput ? $output : null;
        $usage['total_tokens'] = $hasInput || $hasOutput ? $input + $output : null;
        $usage['cached'] = $hasCached ? $cached : null;
        // Total parsial: ada run provider nyata yang tidak melaporkan token, jadi jumlah di atas bukan total penuh.
        $usage['partial'] = $usage['usage_runs'] > 0 && $usage['usage_runs'] < $usage['provider_runs'];
        $usage['runs_total'] = $tally['total'];
        $usage['runs_ok'] = $tally['ok'];
        $usage['runs_failed'] = $tally['failed'];
        $usage['runs_insufficient'] = $tally['insufficient'];
        // Hanya run yang sudah selesai (berhasil, gagal, bukti kurang); run yang masih berjalan belum menentukan hasil.
        $finished = $tally['ok'] + $tally['failed'] + $tally['insufficient'];
        $usage['success_rate'] = $finished > 0 ? round($tally['ok'] / $finished * 100, 1) : null;
        $usage['prompt_runs'] = $tally['prompt'];
        $usage['series'] = array_values($series);
        $usage['tools'] = $this->rank($toolCounts, 'ai_tools', fn ($row) => ['name' => $row->name, 'sub' => null]);
        $usage['prompts'] = $this->rank($promptCounts, 'prompt_templates', fn ($row) => [
            'name' => $row->indonesian_name ?? $row->name,
            'sub' => 'v'.$row->version,
        ]);
        $usage['cost'] = $this->cost($usd, $usage['partial'], $usage['provider_runs'], $usage['usage_runs'], array_keys($unpriced), $prices !== []);

        return $usage;
    }

    /**
     * Token dari blok usage yang dilaporkan provider; null bila run tidak melaporkan token sama sekali.
     * Format berbeda antar provider: Responses API (input_tokens/output_tokens) vs chat completions (prompt_/completion_).
     * Token cache adalah BAGIAN dari input (bukan tambahan), jadi total = input + output.
     * Satu-satunya tempat aturan ini: Beranda dan Provider & Analitik AI memakainya agar angkanya sama.
     *
     * @return ?array{input: ?int, output: ?int, cached: ?int}
     */
    public function tokensFrom(mixed $meta): ?array
    {
        if (! is_array($meta)) {
            return null;
        }

        $in = $meta['input_tokens'] ?? $meta['prompt_tokens'] ?? null;
        $out = $meta['output_tokens'] ?? $meta['completion_tokens'] ?? null;
        $cache = $meta['input_tokens_details']['cached_tokens'] ?? $meta['prompt_tokens_details']['cached_tokens'] ?? null;

        if (! is_numeric($in) && ! is_numeric($out)) {
            return null;
        }

        return [
            'input' => is_numeric($in) ? (int) $in : null,
            'output' => is_numeric($out) ? (int) $out : null,
            'cached' => is_numeric($cache) ? (int) $cache : null,
        ];
    }

    /** Biaya USD satu run dari tarif model yang dikonfigurasi; null bila model belum punya tarif lengkap. */
    public function runUsd(?string $model, array $tokens): ?float
    {
        $rate = array_change_key_case((array) config('research.pricing.models', []))[strtolower((string) $model)] ?? null;

        if (! is_array($rate) || ! is_numeric($rate['input'] ?? null) || ! is_numeric($rate['output'] ?? null)) {
            return null;
        }

        $cached = is_numeric($tokens['cached'] ?? null) && is_numeric($rate['cached_input'] ?? null) ? (int) $tokens['cached'] : 0;

        return (((int) $tokens['input'] - $cached) * $rate['input'] + $cached * ($rate['cached_input'] ?? 0) + (int) $tokens['output'] * $rate['output']) / 1_000_000;
    }

    /** Tarif dan kurs hanya sah bila sumber dan tanggal verifikasinya diisi (tidak ada angka bawaan). */
    public function pricingVerified(): bool
    {
        return filled(config('research.pricing.source')) && filled(config('research.pricing.verified_at'));
    }

    /**
     * Biaya dari tarif + kurs yang dikonfigurasi. Tiap mata uang null bila bahan hitungnya belum lengkap.
     *
     * @param  ?float  $usd  total USD dari run bertarif; null bila belum dihitung
     * @return array<string, mixed>
     */
    private function cost(?float $usd, bool $partial, int $providerRuns, int $usageRuns, array $unpriced, bool $hasPrices = false): array
    {
        $pricing = (array) config('research.pricing');
        $fx = (array) config('research.fx');
        $rate = is_numeric($fx['usd_idr'] ?? null) && (float) $fx['usd_idr'] > 0 ? (float) $fx['usd_idr'] : null;

        $usdReason = match (true) {
            $usd === null || $providerRuns === 0 => 'Belum ada run provider nyata pada periode ini.',
            $usageRuns === 0 => 'Token belum tercatat, jadi biaya belum bisa dihitung.',
            ! $hasPrices => 'Tarif model belum dikonfigurasi di sistem.',
            $unpriced !== [] => 'Model tanpa tarif: '.implode(', ', $unpriced).'.',
            blank($pricing['source'] ?? null) || blank($pricing['verified_at'] ?? null) => 'Sumber dan tanggal verifikasi tarif belum diisi.',
            default => null,
        };

        $idrReason = match (true) {
            $usdReason !== null => $usdReason,
            $rate === null => 'Kurs USD ke IDR belum dikonfigurasi.',
            blank($fx['source'] ?? null) || blank($fx['as_of'] ?? null) => 'Sumber dan waktu kurs belum diisi.',
            default => null,
        };

        return [
            'usd' => $usdReason === null ? round($usd, 4) : null,
            'idr' => $idrReason === null ? round($usd * $rate) : null,
            'usd_reason' => $usdReason,
            'idr_reason' => $idrReason,
            'partial' => $partial,
            'pricing_source' => $pricing['source'] ?? null,
            'pricing_verified_at' => $pricing['verified_at'] ?? null,
            'fx_source' => $fx['source'] ?? null,
            'fx_as_of' => $fx['as_of'] ?? null,
        ];
    }

    /**
     * Peringkat teratas (maks. 5) dari hitungan per id, dengan nama dari tabel master.
     *
     * @param  array<int|string, int>  $counts
     * @return array<int, array{name: string, sub: ?string, count: int}>
     */
    private function rank(array $counts, string $table, Closure $label): array
    {
        if ($counts === [] || ! Schema::hasTable($table)) {
            return [];
        }

        arsort($counts);
        $top = array_slice($counts, 0, 5, true);
        $rows = DB::table($table)->whereIn('id', array_keys($top))->get()->keyBy('id');
        $ranked = [];

        foreach ($top as $id => $count) {
            if ($rows->has($id)) {
                $ranked[] = $label($rows[$id]) + ['count' => $count];
            }
        }

        return $ranked;
    }

    private function currentMonth(): Carbon
    {
        return now(self::TIMEZONE)->startOfMonth();
    }

    private function monthStart(string $month): Carbon
    {
        return Carbon::createFromFormat('!Y-m', $month, self::TIMEZONE);
    }

    private function earliestRunMonth(): ?Carbon
    {
        if (! Schema::hasTable('ai_runs')) {
            return null;
        }

        $first = DB::table('ai_runs')->min('created_at');

        return $first
            ? Carbon::parse($first, config('app.timezone'))->setTimezone(self::TIMEZONE)->startOfMonth()
            : null;
    }
}
