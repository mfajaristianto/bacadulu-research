<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiTool;
use App\Models\AuditLog;
use App\Models\PromptTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class PromptVersionController extends Controller
{
    public function index(
        Request $request
    ): View {
        $filters =
            $request->validate([
                'search' => [
                    'nullable',
                    'string',
                    'max:160',
                ],

                'tool' => [
                    'nullable',
                    'integer',
                    'exists:ai_tools,id',
                ],

                'status' => [
                    'nullable',
                    'in:draft,active,archived',
                ],
            ]);

        $query =
            PromptTemplate::query()
                ->with(
                    'aiTool'
                );

        if (
            filled(
                $filters[
                    'search'
                ]
                ??
                null
            )
        ) {
            $search =
                trim(
                    (string)
                        $filters[
                            'search'
                        ]
                );

            $query->where(
                function (
                    $builder
                ) use (
                    $search
                ): void {
                    $builder
                        ->where(
                            'name',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'indonesian_name',
                            'like',
                            "%{$search}%"
                        );
                }
            );
        }

        if (
            filled(
                $filters[
                    'tool'
                ]
                ??
                null
            )
        ) {
            $query->where(
                'ai_tool_id',
                $filters[
                    'tool'
                ]
            );
        }

        if (
            filled(
                $filters[
                    'status'
                ]
                ??
                null
            )
        ) {
            $query->where(
                'status',
                $filters[
                    'status'
                ]
            );
        }

        return view(
            'admin.prompt-versions.index',
            [
                'versions' =>
                    $query
                        ->orderByDesc(
                            'id'
                        )
                        ->paginate(
                            25
                        )
                        ->withQueryString(),

                'tools' =>
                    AiTool::query()
                        ->orderBy(
                            'name'
                        )
                        ->get(),
            ]
        );
    }

    public function duplicate(
        Request $request,
        PromptTemplate $prompt
    ): RedirectResponse {
        $nextVersion =
            (
                (int)
                    PromptTemplate::query()
                        ->where(
                            'ai_tool_id',
                            $prompt
                                ->ai_tool_id
                        )
                        ->where(
                            'name',
                            $prompt
                                ->name
                        )
                        ->max(
                            'version'
                        )
            )
            +
            1;

        $copy =
            $prompt
                ->replicate([
                    'status',
                    'version',
                    'created_by',
                ]);

        $copy->version =
            $nextVersion;

        $copy->status =
            'draft';

        $copy->created_by =
            $request
                ->session()
                ->get(
                    'research_admin_user_id'
                );

        $copy->save();

        $this->audit(
            $request,
            'prompt.duplicated',
            $copy,
            [
                'source_prompt_id' =>
                    $prompt
                        ->id,

                'source_version' =>
                    $prompt
                        ->version,
            ]
        );

        return redirect()
            ->route(
                'admin.prompts.edit',
                $copy
            )
            ->with(
                'success',
                'Versi baru v'
                .$nextVersion
                .' berhasil dibuat sebagai draft.'
            );
    }

    public function activate(
        Request $request,
        PromptTemplate $prompt
    ): RedirectResponse {
        if (
            ! $prompt
                ->ai_tool_id
        ) {
            return back()
                ->withErrors([
                    'prompt' =>
                        'Prompt harus terhubung ke Research Tool sebelum diaktifkan.',
                ]);
        }

        DB::transaction(
            function () use (
                $prompt
            ): void {
                PromptTemplate::query()
                    ->where(
                        'ai_tool_id',
                        $prompt
                            ->ai_tool_id
                    )
                    ->where(
                        'id',
                        '<>',
                        $prompt
                            ->id
                    )
                    ->where(
                        'status',
                        'active'
                    )
                    ->update([
                        'status' =>
                            'archived',
                    ]);

                $prompt->update([
                    'status' =>
                        'active',
                ]);

                AiTool::query()
                    ->whereKey(
                        $prompt
                            ->ai_tool_id
                    )
                    ->update([
                        'active_prompt_template_id' =>
                            $prompt
                                ->id,
                    ]);
            }
        );

        $this->audit(
            $request,
            'prompt.activated',
            $prompt
        );

        return back()
            ->with(
                'success',
                'Prompt v'
                .$prompt
                    ->version
                .' sekarang menjadi versi aktif.'
            );
    }

    private function audit(
        Request $request,
        string $action,
        PromptTemplate $prompt,
        array $extra = []
    ): void {
        try {
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
                    PromptTemplate::class,

                'auditable_id' =>
                    $prompt
                        ->id,

                'metadata_json' =>
                    [
                        'ai_tool_id' =>
                            $prompt
                                ->ai_tool_id,

                        'name' =>
                            $prompt
                                ->name,

                        'version' =>
                            $prompt
                                ->version,

                        'status' =>
                            $prompt
                                ->status,
                    ]
                    +
                    $extra,

                'ip_address' =>
                    $request
                        ->ip(),
            ]);
        } catch (
            Throwable
        ) {
            //
        }
    }
}