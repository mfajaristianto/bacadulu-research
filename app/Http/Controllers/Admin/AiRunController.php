<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiRun;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AiRunController extends Controller
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

                'status' => [
                    'nullable',
                    'in:queued,retrieving,evidence_ready,generating,completed,insufficient_evidence,citation_failed,failed',
                ],

                'provider' => [
                    'nullable',
                    'string',
                    'max:120',
                ],
            ]);

        $runs =
            AiRun::query()
                ->with([
                    'user:id,name,email',
                    'aiTool:id,code,name',
                    'promptTemplate:id,name,version',
                    'assistantMessage.conversation:id,title',
                ])
                ->withCount([
                    'sources',
                    'retrievals',
                    'results',

                    'citations as valid_citations_count' => fn ($query) => $query->where(
                        'is_valid',
                        true
                    ),

                    'citations as invalid_citations_count' => fn ($query) => $query->where(
                        'is_valid',
                        false
                    ),
                ]);

        if (
            filled(
                $filters['search']
                ?? null
            )
        ) {
            $search = trim(
                (string)
                    $filters[
                        'search'
                    ]
            );

            $runs->where(
                function ($query) use (
                    $search
                ): void {
                    $query
                        ->where(
                            'uuid',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhereHas(
                            'user',
                            function (
                                $user
                            ) use (
                                $search
                            ): void {
                                $user
                                    ->where(
                                        'name',
                                        'like',
                                        "%{$search}%"
                                    )
                                    ->orWhere(
                                        'email',
                                        'like',
                                        "%{$search}%"
                                    );
                            }
                        )
                        ->orWhereHas(
                            'aiTool',
                            fn ($tool) => $tool
                                ->where(
                                    'name',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'code',
                                    'like',
                                    "%{$search}%"
                                )
                        )
                        ->orWhereHas(
                            'promptTemplate',
                            fn ($prompt) => $prompt
                                ->where(
                                    'name',
                                    'like',
                                    "%{$search}%"
                                )
                        );
                }
            );
        }

        if (
            filled(
                $filters['status']
                ?? null
            )
        ) {
            $runs->where(
                'status',
                $filters[
                    'status'
                ]
            );
        }

        if (
            filled(
                $filters['provider']
                ?? null
            )
        ) {
            $runs->where(
                'model_provider',
                $filters[
                    'provider'
                ]
            );
        }

        $providers =
            AiRun::query()
                ->whereNotNull(
                    'model_provider'
                )
                ->where(
                    'model_provider',
                    '<>',
                    ''
                )
                ->distinct()
                ->orderBy(
                    'model_provider'
                )
                ->pluck(
                    'model_provider'
                );

        return view(
            'admin.ai-runs.index',
            [
                'runs' => $runs
                    ->latest('id')
                    ->paginate(20)
                    ->withQueryString(),

                'providers' => $providers,

                'filters' => $filters,
            ]
        );
    }

    public function show(
        AiRun $aiRun
    ): View {
        $aiRun->load([
            'user',
            'aiTool',
            'promptTemplate',

            'assistantMessage.conversation',

            'sources.category',

            'retrievals' => fn ($query) => $query
                ->with([
                    'document',
                    'documentChunk',
                ])
                ->orderBy(
                    'rank_no'
                ),

            'citations' => fn ($query) => $query
                ->with([
                    'document',
                    'documentChunk',
                ])
                ->orderBy(
                    'citation_key'
                ),

            'results' => fn ($query) => $query
                ->orderBy(
                    'id'
                ),

            'messages' => fn ($query) => $query
                ->orderBy(
                    'id'
                ),
        ]);

        $conversation =
            $aiRun->conversation
            ?: $aiRun
                ->assistantMessage
                ?->conversation;

        $durationMs =
            null;

        if (
            $aiRun->started_at
            && $aiRun->finished_at
        ) {
            $durationMs =
                (int)
                    $aiRun
                        ->started_at
                        ->diffInMilliseconds(
                            $aiRun
                                ->finished_at
                        );
        }

        return view(
            'admin.ai-runs.show',
            [
                'run' => $aiRun,

                'conversation' => $conversation,

                'durationMs' => $durationMs,

                'parameters' => is_array(
                    $aiRun
                        ->user_parameters_json
                )
                        ? $aiRun
                            ->user_parameters_json
                        : [],
            ]
        );
    }
}
