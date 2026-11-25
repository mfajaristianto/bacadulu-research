<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Services\Research\AiUsageSummary;
use App\Services\Research\DashboardAnalytics;
use App\Models\DocumentPromptSection;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private AiUsageSummary $usage, private DashboardAnalytics $analytics) {}

    public function index(Request $request): View
    {
        [$usagePeriod, $usageMonth] = $this->usage->normalize(
            $request->query('periode'),
            $request->query('bulan')
        );

        $stats = [
            'sources' => $this->countTable('documents'),
            'published' => $this->countWhere('documents', 'status', 'published'),
            'draft' => $this->countWhere('documents', 'status', 'draft'),
            'processing' => $this->countWhere('documents', 'status', 'processing'),
            'failed' => $this->countWhere('documents', 'status', 'failed'),
            'ready' => $this->countWhere('documents', 'status', 'ready'),
            'sync_pending' => $this->countWhereIn('source_sync_items', 'status', ['new', 'changed']),
            'runs_failed_today' => $this->countWhereIn('ai_runs', 'status', ['failed', 'citation_failed'], today: true),
            'tools_active' => $this->countWhere('ai_tools', 'status', 'active'),
            'tools_with_prompt' => $this->countActiveToolsWithPrompt(),
            'prompts' => $this->promptCount(),
            'prompts_active' => $this->promptCount('active'),
            'prompts_draft' => $this->promptCount('draft'),
            'prompts_archived' => $this->promptCount('archived'),
            'runs_today' => $this->countToday('ai_runs'),
            'users' => $this->countTable('users'),
            'users_active' => $this->countWhere('users', 'status', 'active'),
            'users_suspended' => $this->countWhere('users', 'status', 'suspended'),
        ];

        // Status lain (atau kosong) = sisa total; tidak dihitung terpisah agar irisan donut tidak pernah melebihi total.
        $stats['users_other'] = max(0, $stats['users'] - $stats['users_active'] - $stats['users_suspended']);

        $recentDocuments = $this->recentDocuments();
        $recentActivity = $this->recentActivity();
        $aiStatus = $this->aiStatus();
        $panels = $this->analytics->panels($usagePeriod, $usageMonth);
        $aiUsage = $panels['usage'];
        $kpis = $this->analytics->kpis($stats);
        $usagePeriods = AiUsageSummary::PERIODS;
        $usageMonths = $this->usage->months();

        return view('admin.dashboard', compact(
            'stats',
            'recentDocuments',
            'recentActivity',
            'aiStatus',
            'aiUsage',
            'panels',
            'kpis',
            'usagePeriod',
            'usageMonth',
            'usagePeriods',
            'usageMonths'
        ));
    }

    /** Data Penggunaan AI untuk filter tanpa reload; membaca saja, tidak memanggil provider. */
    public function usage(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'periode' => ['required', Rule::in(array_keys(AiUsageSummary::PERIODS))],
            'bulan' => [
                'nullable',
                'required_if:periode,history',
                fn ($attribute, $value, $fail) => is_string($value) && $this->usage->isValidMonth($value) ? null : $fail('Bulan tidak valid.'),
            ],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Periode tidak valid.', 'errors' => $validator->errors()], 422);
        }

        $period = (string) $request->query('periode');
        $month = $period === 'history' ? (string) $request->query('bulan') : null;
        $panels = $this->analytics->panels($period, $month);
        $summary = $panels['usage'];

        return response()
            ->json([
                'period' => $summary['period'],
                'month' => $summary['month'],
                'label' => $summary['label'],
                'data' => $summary,
                'html' => view('admin.partials.analytics-body', ['aiUsage' => $summary, 'panels' => $panels])->render(),
            ])
            ->header('Cache-Control', 'private, no-store');
    }

    /**
     * Status dari konfigurasi saja; tidak ada request ke provider saat Beranda dibuka.
     * `providers` adalah daftar ringkas (utama lebih dulu); kini hanya provider aktif, daftar bisa bertambah tanpa ubah view.
     *
     * @return array{state: string, provider: string, model: string, api_key: bool, providers: array<int, array<string, mixed>>}
     */
    private function aiStatus(): array
    {
        $provider = strtolower((string) config('research.ai_provider', 'mock'));
        $model = (string) config('research.ai_model', '');
        $apiKey = filled(config('services.openai.api_key'));

        $state = match (true) {
            $provider === 'mock' => 'mock',
            $provider !== 'openai' => 'unknown',
            ! $apiKey => 'unconfigured',
            default => 'configured',
        };

        return [
            'state' => $state,
            'provider' => $provider,
            'model' => $model,
            'api_key' => $apiKey,
            'providers' => [[
                'label' => match ($provider) {
                    'mock' => 'Mock (mode uji)',
                    'openai' => 'OpenAI',
                    default => 'Tidak dikenali: '.$provider,
                },
                'meta' => match ($provider) {
                    'mock' => 'Data contoh, tanpa panggilan API',
                    'openai' => ($model !== '' ? $model.' · ' : '').'API key '.($apiKey ? 'terisi' : 'belum diisi'),
                    default => 'Gunakan mock atau openai',
                },
                'primary' => true,
            ]],
        ];
    }

    /** "Hari ini" mengikuti hari operasional WIB (rumus rentang ada di AiUsageSummary). */
    private function whereToday(Builder $query): Builder
    {
        $start = $this->usage->range('today')['start']->copy()->setTimezone(config('app.timezone'));

        return $query
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $start->copy()->addDay());
    }

    /** Instruksi alat riset saja: versi bagian Prompt Dokumen punya inventaris sendiri dan tidak ikut angka ini. */
    private function promptCount(?string $status = null): int
    {
        if (! Schema::hasTable('prompt_templates')) {
            return 0;
        }

        return DB::table('prompt_templates')
            ->where('name', 'not like', DocumentPromptSection::PROMPT_PREFIX.'%')
            ->where('name', 'not like', \App\Services\Research\AnswerBehavior::PREFIX.'%')
            ->when($status, fn ($q) => $q->where('status', $status))
            ->count();
    }

    private function countTable(string $table): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        return DB::table($table)->count();
    }

    private function countWhere(
        string $table,
        string $column,
        string $value
    ): int {
        if (
            ! Schema::hasTable($table)
            || ! Schema::hasColumn($table, $column)
        ) {
            return 0;
        }

        return DB::table($table)
            ->where($column, $value)
            ->count();
    }

    private function countWhereIn(
        string $table,
        string $column,
        array $values,
        bool $today = false
    ): int {
        if (
            ! Schema::hasTable($table)
            || ! Schema::hasColumn($table, $column)
        ) {
            return 0;
        }

        return DB::table($table)
            ->whereIn($column, $values)
            ->when($today, fn ($query) => $this->whereToday($query))
            ->count();
    }

    private function countActiveToolsWithPrompt(): int
    {
        if (
            ! Schema::hasTable('ai_tools')
            || ! Schema::hasTable('prompt_templates')
            || ! Schema::hasColumn('ai_tools', 'active_prompt_template_id')
        ) {
            return 0;
        }

        // Valid = prompt yang ditunjuk masih ada, berstatus aktif, dan milik alat itu sendiri (aturan PromptController::activate).
        return DB::table('ai_tools as t')
            ->join('prompt_templates as p', 'p.id', '=', 't.active_prompt_template_id')
            ->where('t.status', 'active')
            ->where('p.status', 'active')
            ->whereColumn('p.ai_tool_id', 't.id')
            ->count();
    }

    private function countToday(string $table): int
    {
        if (
            ! Schema::hasTable($table)
            || ! Schema::hasColumn($table, 'created_at')
        ) {
            return 0;
        }

        return $this->whereToday(DB::table($table))->count();
    }

    private function recentDocuments(): Collection
    {
        if (! Schema::hasTable('documents')) {
            return collect();
        }

        $query = DB::table('documents as d')
            ->select([
                'd.id',
                'd.title',
                'd.status',
                'd.document_type',
                'd.created_at',
            ]);

        if (
            Schema::hasColumn('documents', 'category_id')
            && Schema::hasTable('categories')
        ) {
            $query
                ->leftJoin(
                    'categories as c',
                    'c.id',
                    '=',
                    'd.category_id'
                )
                ->addSelect(
                    'c.name as category_name'
                );
        }

        return $query
            ->orderByDesc('d.created_at')
            ->orderByDesc('d.id')
            ->limit(5)
            ->get();
    }

    private function recentActivity(): Collection
    {
        if (! Schema::hasTable('audit_logs')) {
            return collect();
        }

        $query = DB::table('audit_logs as a')
            ->select(['a.id', 'a.action', 'a.auditable_type', 'a.auditable_id', 'a.created_at']);

        if (Schema::hasTable('users')) {
            $query
                ->leftJoin('users as u', 'u.id', '=', 'a.user_id')
                ->addSelect('u.name as actor_name');
        }

        $rows = $query
            ->orderByDesc('a.id')
            ->limit(5)
            ->get();

        // Tautan detail hanya untuk dokumen yang masih ada; aksi lain tidak punya halaman detail sendiri.
        $documentIds = $rows->where('auditable_type', Document::class)->pluck('auditable_id')->filter()->unique();
        $existing = $documentIds->isNotEmpty() && Schema::hasTable('documents')
            ? DB::table('documents')->whereIn('id', $documentIds)->pluck('id')->all()
            : [];

        return $rows->map(function ($row) use ($existing) {
            $row->href = $row->auditable_type === Document::class && in_array((int) $row->auditable_id, array_map('intval', $existing), true)
                ? route('admin.library.edit', $row->auditable_id)
                : null;

            return $row;
        });
    }
}
