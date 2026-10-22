<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\SourceSyncItem;
use App\Models\SourceSyncRun;
use App\Services\Research\Sync\SourceSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Throwable;

class SourceSyncController extends Controller
{
    public function index(
        Request $request
    ): View {
        $runs =
            SourceSyncRun::query()
                ->with(
                    'initiatedBy:id,name,email'
                )
                ->latest(
                    'id'
                )
                ->paginate(
                    12
                )
                ->withQueryString();

        $selectedRun =
            null;

        if (
            $request->filled(
                'run'
            )
        ) {
            $selectedRun =
                SourceSyncRun::query()
                    ->find(
                        $request
                            ->integer(
                                'run'
                            )
                    );
        }

        $selectedRun ??=
            SourceSyncRun::query()
                ->latest(
                    'id'
                )
                ->first();

        $items =
            collect();

        if ($selectedRun) {
            $items =
                $selectedRun
                    ->items()
                    ->with([
                        'matchedDocument:id,title,status',
                        'importedDocument:id,title,status',
                        'category:id,name',
                    ])
                    ->orderByRaw(
                        "
                        CASE status
                            WHEN 'new' THEN 1
                            WHEN 'changed' THEN 2
                            WHEN 'failed' THEN 3
                            WHEN 'imported' THEN 4
                            WHEN 'ignored' THEN 5
                            ELSE 6
                        END
                        "
                    )
                    ->orderBy(
                        'title'
                    )
                    ->get();
        }

        return view(
            'admin.source-sync.index',
            [
                'provider' =>
                    (string)
                        config(
                            'source-sync.provider',
                            'mock'
                        ),

                'autoPublish' =>
                    (bool)
                        config(
                            'source-sync.auto_publish',
                            false
                        ),

                'runs' =>
                    $runs,

                'selectedRun' =>
                    $selectedRun,

                'items' =>
                    $items,

                'categories' =>
                    Category::query()
                        ->where(
                            'status',
                            'active'
                        )
                        ->orderBy(
                            'name'
                        )
                        ->get([
                            'id',
                            'name',
                        ]),
            ]
        );
    }

    public function scan(
        Request $request,
        SourceSyncService $sync
    ): RedirectResponse {
        $adminId =
            $request
                ->session()
                ->get(
                    'research_admin_user_id'
                );

        try {
            $run =
                $sync->scan(
                    $adminId
                        ? (int)
                            $adminId
                        : null
                );

            $this->audit(
                $request,
                'source_sync.scanned',
                SourceSyncRun::class,
                $run->id,
                [
                    'connector' =>
                        $run
                            ->connector,

                    'status' =>
                        $run
                            ->status,

                    'discovered_count' =>
                        $run
                            ->discovered_count,

                    'new_count' =>
                        $run
                            ->new_count,

                    'changed_count' =>
                        $run
                            ->changed_count,

                    'unchanged_count' =>
                        $run
                            ->unchanged_count,
                ]
            );

            return redirect()
                ->route(
                    'admin.source-sync.index',
                    [
                        'run' =>
                            $run->id,
                    ]
                )
                ->with(
                    'success',
                    'Scan Source Sync selesai. '
                    .$run->discovered_count
                    .' source ditemukan; '
                    .$run->new_count
                    .' baru dan '
                    .$run->changed_count
                    .' berubah.'
                );
        } catch (
            Throwable $exception
        ) {
            report(
                $exception
            );

            return redirect()
                ->route(
                    'admin.source-sync.index'
                )
                ->withErrors([
                    'source_sync' =>
                        $exception
                            ->getMessage(),
                ]);
        }
    }

    public function import(
        Request $request,
        SourceSyncItem $syncItem,
        SourceSyncService $sync
    ): RedirectResponse {
        $validated =
            $request->validate([
                'category_id' => [
                    'required',
                    'integer',
                    'exists:categories,id',
                ],
            ]);

        $adminId =
            $request
                ->session()
                ->get(
                    'research_admin_user_id'
                );

        try {
            $document =
                $sync->import(
                    $syncItem,
                    (int)
                        $validated[
                            'category_id'
                        ],
                    $adminId
                        ? (int)
                            $adminId
                        : null
                );

            $this->audit(
                $request,
                'source_sync.imported',
                SourceSyncItem::class,
                $syncItem->id,
                [
                    'connector' =>
                        $syncItem
                            ->connector,

                    'external_id' =>
                        $syncItem
                            ->external_id,

                    'document_id' =>
                        $document
                            ->id,

                    'document_status' =>
                        $document
                            ->status,

                    'chunks' =>
                        $document
                            ->chunks()
                            ->count(),
                ]
            );

            return redirect()
                ->route(
                    'admin.source-sync.index',
                    [
                        'run' =>
                            $syncItem
                                ->source_sync_run_id,
                    ]
                )
                ->with(
                    'success',
                    'Source berhasil diimport ke Library sebagai READY. Review source lalu Publish secara manual jika sudah layak.'
                );
        } catch (
            Throwable $exception
        ) {
            report(
                $exception
            );

            return redirect()
                ->route(
                    'admin.source-sync.index',
                    [
                        'run' =>
                            $syncItem
                                ->source_sync_run_id,
                    ]
                )
                ->withErrors([
                    'source_sync' =>
                        $exception
                            ->getMessage(),
                ]);
        }
    }

    public function ignore(
        Request $request,
        SourceSyncItem $syncItem,
        SourceSyncService $sync
    ): RedirectResponse {
        $adminId =
            $request
                ->session()
                ->get(
                    'research_admin_user_id'
                );

        try {
            $item =
                $sync->ignore(
                    $syncItem,
                    $adminId
                        ? (int)
                            $adminId
                        : null
                );

            $this->audit(
                $request,
                'source_sync.ignored',
                SourceSyncItem::class,
                $item->id,
                [
                    'connector' =>
                        $item
                            ->connector,

                    'external_id' =>
                        $item
                            ->external_id,
                ]
            );

            return redirect()
                ->route(
                    'admin.source-sync.index',
                    [
                        'run' =>
                            $item
                                ->source_sync_run_id,
                    ]
                )
                ->with(
                    'success',
                    'Source Sync item diabaikan. Tidak ada perubahan ke Library.'
                );
        } catch (
            Throwable $exception
        ) {
            report(
                $exception
            );

            return redirect()
                ->route(
                    'admin.source-sync.index',
                    [
                        'run' =>
                            $syncItem
                                ->source_sync_run_id,
                    ]
                )
                ->withErrors([
                    'source_sync' =>
                        $exception
                            ->getMessage(),
                ]);
        }
    }

    private function audit(
        Request $request,
        string $action,
        string $auditableType,
        ?int $auditableId,
        array $metadata = []
    ): void {
        if (
            ! Schema::hasTable(
                'audit_logs'
            )
        ) {
            return;
        }

        AuditLog::create([
            'user_id' =>
                $request
                    ->session()
                    ->get(
                        'research_admin_user_id'
                    ),

            'action' =>
                $action,

            'auditable_type' =>
                $auditableType,

            'auditable_id' =>
                $auditableId,

            'metadata_json' =>
                $metadata,

            'ip_address' =>
                $request
                    ->ip(),
        ]);
    }
}