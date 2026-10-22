<?php

namespace App\Http\Controllers;

use App\Models\AiRun;
use App\Models\DocumentGeneration;
use App\Models\PromptTemplate;
use App\Models\SavedResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResearchOutputPresentationController extends Controller
{
    public function run(
        Request $request,
        AiRun $aiRun
    ): JsonResponse {
        $this->ensureRunOwnership(
            $request,
            $aiRun
        );

        $aiRun->load([
            'aiTool',
            'promptTemplate',
            'primaryResult',
            'assistantMessage.conversation',
            'citations.document',
        ]);

        $savedResult =
            SavedResult::query()
                ->where(
                    'user_id',
                    $request
                        ->user()
                        ->id
                )
                ->where(
                    'ai_run_id',
                    $aiRun->id
                )
                ->first();

        $nextPrompt =
            $this->resolveNextPrompt(
                $aiRun
            );

        $conversation =
            $aiRun
                ->assistantMessage
                ?->conversation;

        $nextPromptUrl =
            null;

        if ($nextPrompt) {
            if ($conversation) {
                $nextPromptUrl =
                    route(
                        'workspace.conversation',
                        [
                            'conversation' => $conversation,

                            'prompt' => $nextPrompt
                                ->id,
                        ]
                    );
            } else {
                $nextPromptUrl =
                    route(
                        'workspace',
                        [
                            'prompt' => $nextPrompt
                                ->id,
                        ]
                    );
            }
        }

        return response()
            ->json([
                'run_id' => $aiRun->id,

                'status' => $aiRun->status,

                'completed' => $aiRun->status
                        === 'completed',

                'can_save' => $aiRun->status
                        === 'completed'
                        && $aiRun
                            ->primaryResult
                        !== null,

                'can_generate' => $aiRun->status
                        === 'completed'
                        && $aiRun
                            ->primaryResult
                        !== null,

                'save' => [
                    'saved' => $savedResult
                            !== null,

                    'saved_result_id' => $savedResult
                        ?->id,

                    'url' => $savedResult
                            ? route(
                                'saved.show',
                                $savedResult
                            )
                            : null,
                ],

                'generate_url' => $aiRun->status
                        === 'completed'
                        ? route(
                            'documents.create',
                            [
                                'ai_run' => $aiRun
                                    ->id,
                            ]
                        )
                        : null,

                'citations' => $this
                    ->citationPayload(
                        $aiRun
                    ),

                'next_action' => $this
                    ->nextAction(
                        $aiRun
                    ),

                'next_prompt' => $nextPrompt
                        ? [
                            'id' => $nextPrompt
                                ->id,

                            'name' => $nextPrompt
                                ->display_name,

                            'description' => $nextPrompt
                                ->short_description
                                    ?: $nextPrompt
                                        ->aiTool
                                        ?->description
                                    ?: '',

                            'tool' => $nextPrompt
                                ->aiTool
                                ?->name
                                    ?: 'Research Tool',

                            'url' => $nextPromptUrl,
                        ]
                        : null,
            ]);
    }

    public function saved(
        Request $request,
        SavedResult $savedResult
    ): JsonResponse {
        $this->ensureSavedOwnership(
            $request,
            $savedResult
        );

        $savedResult->load([
            'aiRun.aiTool',
            'aiRun.promptTemplate',
            'aiRun.primaryResult',
            'aiRun.assistantMessage.conversation',
        ]);

        $run =
            $savedResult
                ->aiRun;

        abort_unless(
            $run !== null,
            404
        );

        $documents =
            DocumentGeneration::query()
                ->where(
                    'user_id',
                    $request
                        ->user()
                        ->id
                )
                ->where(
                    'ai_run_id',
                    $run->id
                )
                ->latest()
                ->get()
                ->map(
                    function (
                        DocumentGeneration $document
                    ): array {
                        return [
                            'id' => $document
                                ->id,

                            'title' => $document
                                ->title,

                            'document_type' => $document
                                ->document_type,

                            'citation_style' => $document
                                ->citation_style,

                            'status' => $document
                                ->status,

                            'generated_at' => $document
                                ->generated_at
                                ?->format(
                                    'd M Y · H:i'
                                ),

                            'show_url' => route(
                                'documents.show',
                                $document
                            ),

                            'docx_url' => $document
                                ->status
                                === 'completed'
                                ? route(
                                    'documents.download',
                                    [
                                        $document,
                                        'docx',
                                    ]
                                )
                                    : null,

                            'pdf_url' => $document
                                ->status
                                === 'completed'
                                ? route(
                                    'documents.download',
                                    [
                                        $document,
                                        'pdf',
                                    ]
                                )
                                    : null,
                        ];
                    }
                )
                ->values()
                ->all();

        return response()
            ->json([
                'saved_result_id' => $savedResult
                    ->id,

                'run_id' => $run
                    ->id,

                'can_generate' => $run
                    ->status
                        === 'completed'
                        && $run
                            ->primaryResult
                        !== null,

                'generate_url' => $run
                    ->status
                    === 'completed'
                    ? route(
                        'documents.create',
                        [
                            'saved_result' => $savedResult
                                ->id,
                        ]
                    )
                        : null,

                'documents' => $documents,
            ]);
    }

    private function citationPayload(
        AiRun $run
    ): array {
        return $run
            ->citations
            ->where(
                'is_valid',
                true
            )
            ->sortBy(
                'citation_key'
            )
            ->map(
                function ($citation): array {
                    return [
                        'key' => $citation
                            ->citation_key,

                        'claim' => $citation
                            ->claim_label,

                        'quote' => $citation
                            ->quote_snippet,

                        'source' => $citation
                            ->document
                            ?->title
                            ?: 'Curated Source',

                        'year' => $citation
                            ->document
                            ?->publication_year,

                        'doi' => $citation
                            ->document
                            ?->doi,
                    ];
                }
            )
            ->values()
            ->all();
    }

    private function nextAction(
        AiRun $run
    ): ?string {
        $structured =
            is_array(
                $run
                    ->primaryResult
                    ?->structured_output_json
            )
                ? $run
                    ->primaryResult
                    ->structured_output_json
                : [];

        $action = trim(
            (string) (
                $structured[
                    'next_action'
                ]
                ?? data_get(
                    $run
                        ->assistantMessage
                        ?->metadata,
                    'next_action',
                    ''
                )
            )
        );

        return $action !== ''
            ? $action
            : null;
    }

    private function resolveNextPrompt(
        AiRun $run
    ): ?PromptTemplate {
        $currentCode =
            $run
                ->aiTool
                ?->code;

        if (! $currentCode) {
            return null;
        }

        /*
        |--------------------------------------------------------------------------
        | Canonical Research Flow
        |--------------------------------------------------------------------------
        |
        | Ini suggestion, bukan paksaan.
        |
        | Literature Synthesis
        | → Research Gap
        | → Theoretical Mapping
        | → Conceptual Framework
        | → Variable Identification
        | → Comparative Analysis
        |
        */

        $chain = [
            'literature-synthesis' => 'research-gap',

            'research-gap' => 'theoretical-mapping',

            'theoretical-mapping' => 'conceptual-framework',

            'conceptual-framework' => 'variable-identification',

            'variable-identification' => 'comparative-analysis',

            /*
             * Comparative Analysis dapat mengarah
             * kembali ke Research Gap jika user
             * ingin memperdalam perbedaan temuan.
             */
            'comparative-analysis' => 'research-gap',
        ];

        $nextCode =
            $chain[
                $currentCode
            ]
            ?? null;

        if (! $nextCode) {
            return null;
        }

        return PromptTemplate::query()
            ->where(
                'status',
                'active'
            )
            ->whereHas(
                'aiTool',
                fn ($query) => $query
                    ->where(
                        'code',
                        $nextCode
                    )
                    ->where(
                        'status',
                        'active'
                    )
            )
            ->with(
                'aiTool'
            )
            ->orderByDesc(
                'version'
            )
            ->first();
    }

    private function ensureRunOwnership(
        Request $request,
        AiRun $aiRun
    ): void {
        abort_unless(
            (int)
                $aiRun
                    ->user_id
            ===
            (int)
                $request
                    ->user()
                    ->id,
            404
        );
    }

    private function ensureSavedOwnership(
        Request $request,
        SavedResult $savedResult
    ): void {
        abort_unless(
            (int)
                $savedResult
                    ->user_id
            ===
            (int)
                $request
                    ->user()
                    ->id,
            404
        );
    }
}
