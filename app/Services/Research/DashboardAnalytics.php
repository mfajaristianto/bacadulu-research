<?php

namespace App\Services\Research;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Angka Beranda Admin selain run riset (yang ada di AiUsageSummary): perubahan KPI dan panel Prompt / Sumber Terbit.
 *
 * Pertumbuhan hanya dihitung dari data historis yang valid (created_at / published_at / audit log, dalam WIB).
 * Bila pembandingnya nol atau tidak tercatat, persentase null dan UI menyatakannya, bukan menampilkan 0%.
 */
class DashboardAnalytics
{
    /** Jendela pembanding KPI: 30 hari terakhir dibanding total sebelum jendela itu. */
    public const KPI_DAYS = 30;

    public function __construct(private AiUsageSummary $usage) {}

    /**
     * Panel analitik untuk satu periode; dipakai render awal dan endpoint JSON.
     *
     * @return array{usage: array<string, mixed>, sources: array<string, mixed>, prompts: array<string, int>}
     */
    public function panels(string $period, ?string $month): array
    {
        return [
            'usage' => $this->usage->summary($period, $month),
            'sources' => $this->sources($period, $month),
            'prompts' => $this->promptInventory(),
        ];
    }

    /**
     * Empat KPI utama. delta = perubahan absolut pada jendela pembanding, pct = pertumbuhan terhadap nilai sebelum jendela.
     *
     * @param  array<string, int>  $stats  hitungan yang sudah dihitung controller
     * @return array<int, array<string, mixed>>
     */
    public function kpis(array $stats): array
    {
        $cut = $this->wibNow()->subDays(self::KPI_DAYS)->setTimezone(config('app.timezone'));
        $window = 'dalam '.self::KPI_DAYS.' hari terakhir';
        $versus = 'dibanding '.self::KPI_DAYS.' hari lalu';

        $newUsers = $this->count('users', fn ($q) => $q->where('created_at', '>=', $cut));
        $newPublished = $this->count('documents', fn ($q) => $q->where('status', 'published')->where('published_at', '>=', $cut));
        $activations = $this->count('audit_logs', fn ($q) => $q->where('action', 'prompt.activated')->where('created_at', '>=', $cut));

        $todayStart = $this->usage->range('today')['start'];
        $yesterdayToNow = $this->count('ai_runs', fn ($q) => $q
            ->where('created_at', '>=', $todayStart->copy()->subDay()->setTimezone(config('app.timezone')))
            ->where('created_at', '<', $this->wibNow()->subDay()->setTimezone(config('app.timezone'))));

        return [
            $this->kpi('users', 'Total Pengguna', $stats['users'], $newUsers, $stats['users'] - $newUsers, 'pengguna baru '.$window, $versus, route('admin.users.index')),
            $this->kpi('sources', 'Sumber Terbit', $stats['published'], $newPublished, $stats['published'] - $newPublished, 'sumber diterbitkan '.$window, $versus, route('admin.library.index', ['status' => 'published'])),
            // Jumlah prompt aktif tidak punya riwayat; yang tercatat hanya aksi aktivasi (bukan selisih jumlah), jadi tanpa persentase.
            $this->kpi('prompts', 'Prompt Aktif', $stats['prompts_active'], Schema::hasTable('audit_logs') ? $activations : null, null, 'aktivasi versi '.$window, null, route('admin.prompts.index'), 'Riwayat jumlah prompt aktif tidak disimpan', true),
            $this->kpi('runs', 'Riset Hari Ini', $stats['runs_today'], $stats['runs_today'] - $yesterdayToNow, $yesterdayToNow, 'dibanding kemarin pada jam yang sama', 'kemarin: '.$yesterdayToNow.' riset', route('admin.ai-runs.index')),
        ];
    }

    /**
     * @param  ?int  $delta  perubahan absolut pada jendela pembanding (null = tidak tersedia)
     * @param  ?int  $base  nilai sebelum jendela; dasar persentase
     * @return array<string, mixed>
     */
    private function kpi(string $key, string $label, int $value, ?int $delta, ?int $base, string $caption, ?string $versus, string $href, string $noPct = 'Belum ada data pembanding', bool $activity = false): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'value' => $value,
            'delta' => $delta,
            // Persentase hanya bila ada pembanding positif.
            'pct' => $delta !== null && $base !== null && $base > 0 ? round($delta / $base * 100, 1) : null,
            'caption' => $caption,
            'versus' => $versus,
            'no_pct' => $noPct,
            'activity' => $activity,
            'href' => $href,
        ];
    }

    /**
     * Sumber Terbit: hanya dokumen berstatus published. Antrean impor dipisah (bukan sumber terbit).
     *
     * @return array<string, mixed>
     */
    public function sources(string $period, ?string $month): array
    {
        $out = ['total' => 0, 'new' => 0, 'series' => [], 'types' => [], 'statuses' => [], 'sync_pending' => 0];

        if (! Schema::hasTable('documents')) {
            return $out;
        }

        $range = $this->usage->range($period, $month);
        [$hourly, $series] = $this->usage->emptySeries($period, $month);
        $start = $range['start']->copy()->setTimezone(config('app.timezone'));
        $end = $range['end']?->copy()->setTimezone(config('app.timezone'));

        $out['total'] = DB::table('documents')->where('status', 'published')->count();

        DB::table('documents')
            ->where('status', 'published')
            ->where('published_at', '>=', $start)
            ->when($end, fn ($q) => $q->where('published_at', '<', $end))
            ->pluck('published_at')
            ->each(function ($at) use (&$series, &$out, $hourly) {
                $out['new']++;
                $key = $this->usage->bucketKey((string) $at, $hourly);

                if (isset($series[$key])) {
                    $series[$key]['count']++;
                }
            });

        $out['series'] = array_values($series);
        $out['types'] = DB::table('documents')->where('status', 'published')->whereNotNull('document_type')
            ->selectRaw('document_type as label, count(*) as total')->groupBy('document_type')->orderByDesc('total')->limit(6)->get()
            ->map(fn ($r) => ['name' => ucfirst(str_replace(['_', '-'], ' ', (string) $r->label)), 'count' => (int) $r->total])->all();
        $out['statuses'] = DB::table('documents')->selectRaw('status as label, count(*) as total')->groupBy('status')->orderByDesc('total')->get()
            ->map(fn ($r) => ['key' => (string) $r->label, 'count' => (int) $r->total])->all();
        $out['sync_pending'] = $this->count('source_sync_items', fn ($q) => $q->whereIn('status', ['new', 'changed']));

        return $out;
    }

    /**
     * Inventaris prompt (bukan penggunaan; penggunaan dihitung dari run di AiUsageSummary).
     *
     * @return array<string, int>
     */
    public function promptInventory(): array
    {
        // Hanya instruksi alat riset: versi bagian Prompt Dokumen (nama berawalan document-section:) punya inventaris sendiri.
        $tools = fn ($q) => $q->where('name', 'not like', \App\Models\DocumentPromptSection::PROMPT_PREFIX.'%')->where('name', 'not like', \App\Services\Research\AnswerBehavior::PREFIX.'%');
        $by = fn (string $status) => $this->count('prompt_templates', fn ($q) => $tools($q)->where('status', $status));

        return [
            'total' => $this->count('prompt_templates', $tools),
            'active' => $by('active'),
            'draft' => $by('draft'),
            'archived' => $by('archived'),
        ];
    }

    private function count(string $table, ?\Closure $scope = null): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        $query = DB::table($table);

        return (int) ($scope ? $scope($query) : $query)->count();
    }

    private function wibNow(): Carbon
    {
        return now(AiUsageSummary::TIMEZONE);
    }
}
