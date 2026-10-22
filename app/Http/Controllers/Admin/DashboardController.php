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
            'chunks' => $this->countTable('document_chunks'),
            'tools' => $this->countTable('ai_tools'),
            'prompts' => $this->countTable('prompt_templates'),
            'runs_today' => $this->countToday('ai_runs'),
            'users' => $this->countTable('users'),
        ];

        $recentDocuments = $this->recentDocuments();
        $recentRuns = $this->recentRuns();
        $recentActivity = $this->recentActivity();

        return view('admin.dashboard', compact(
            'stats',
            'recentDocuments',
            'recentRuns',
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
                'd.updated_at',
            ])
            ->selectSub(
                DB::table('document_chunks as dc')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('dc.document_id', 'd.id'),
                'chunks_count'
            );

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
            ->orderByDesc('d.updated_at')
            ->limit(6)
            ->get();
    }

    private function recentRuns(): Collection
    {
        if (! Schema::hasTable('ai_runs')) {
            return collect();
        }

        $query = DB::table('ai_runs as r')
            ->select([
                'r.id',
                'r.status',
                'r.model_name',
                'r.created_at',
            ]);

        if (
            Schema::hasColumn('ai_runs', 'ai_tool_id')
            && Schema::hasTable('ai_tools')
        ) {
            $query
                ->leftJoin(
                    'ai_tools as t',
                    't.id',
                    '=',
                    'r.ai_tool_id'
                )
                ->addSelect(
                    't.name as tool_name'
                );
        }

        return $query
            ->orderByDesc('r.id')
            ->limit(6)
            ->get();
    }

    private function recentActivity(): Collection
    {
        if (! Schema::hasTable('audit_logs')) {
            return collect();
        }

        return DB::table('audit_logs')
            ->orderByDesc('id')
            ->limit(7)
            ->get();
    }
}
