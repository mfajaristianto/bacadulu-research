<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $stats = [
            'sources' => $this->countTable('documents'),
            'published' => $this->countWhere('documents', 'status', 'published'),
            'draft' => $this->countWhere('documents', 'status', 'draft'),
            'processing' => $this->countWhere('documents', 'status', 'processing'),
            'failed' => $this->countWhere('documents', 'status', 'failed'),
            'ready' => $this->countWhere('documents', 'status', 'ready'),
            'sync_pending' => $this->countWhereIn('source_sync_items', 'status', ['new', 'changed']),
            'runs_failed_today' => $this->countWhereIn('ai_runs', 'status', ['failed', 'citation_failed'], today: true),
            'chunks' => $this->countTable('document_chunks'),
            'tools' => $this->countTable('ai_tools'),
            'tools_active' => $this->countWhere('ai_tools', 'status', 'active'),
            'tools_with_prompt' => $this->countActiveToolsWithPrompt(),
            'prompts' => $this->countTable('prompt_templates'),
            'prompts_active' => $this->countWhere('prompt_templates', 'status', 'active'),
            'prompts_draft' => $this->countWhere('prompt_templates', 'status', 'draft'),
            'prompts_archived' => $this->countWhere('prompt_templates', 'status', 'archived'),
            'runs_today' => $this->countToday('ai_runs'),
            'users' => $this->countTable('users'),
            'users_active' => $this->countWhere('users', 'status', 'active'),
        ];

        $recentDocuments = $this->recentDocuments();
        $recentActivity = $this->recentActivity();

        return view('admin.dashboard', compact(
            'stats',
            'recentDocuments',
            'recentActivity'
        ));
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
            ->when($today, fn ($query) => $query->whereDate('created_at', today()))
            ->count();
    }

    private function countActiveToolsWithPrompt(): int
    {
        if (
            ! Schema::hasTable('ai_tools')
            || ! Schema::hasColumn('ai_tools', 'active_prompt_template_id')
        ) {
            return 0;
        }

        return DB::table('ai_tools')
            ->where('status', 'active')
            ->whereNotNull('active_prompt_template_id')
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

        return DB::table($table)
            ->whereDate('created_at', today())
            ->count();
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
            ->select(['a.id', 'a.action', 'a.created_at']);

        if (Schema::hasTable('users')) {
            $query
                ->leftJoin('users as u', 'u.id', '=', 'a.user_id')
                ->addSelect('u.name as actor_name');
        }

        return $query
            ->orderByDesc('a.id')
            ->limit(4)
            ->get();
    }
}
