<?php

namespace App\Http\Controllers;

use App\Models\AiRun;
use App\Models\Category;
use App\Models\Conversation;
use App\Models\Document;
use App\Models\PromptTemplate;
use App\Models\ResearchMessage;
use App\Services\Research\ResearchRunService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class ResearchController extends Controller
{
    public function dashboard(): RedirectResponse
    {
        return redirect()
            ->route(
                'workspace'
            );
    }

    public function workspace(
        Request $request,
        ?Conversation $conversation = null
    ): View {
        $user =
            $request->user();

        $selectedSource =
            null;

        if (
            $request->filled(
                'source'
            )
        ) {
            $selectedSource =
                Document::query()
                    ->eligibleForResearch()
                    ->with(
                        'category'
                    )
                    ->find(
                        $request
                            ->integer(
                                'source'
                            )
                    );
        }

        $pinnedConversations =
            Conversation::query()
                ->where(
                    'user_id',
                    $user->id
                )
                ->whereNotNull(
                    'pinned_at'
                )
                ->with([
                    'category',
                    'latestMessage',
                ])
                ->orderByDesc(
                    'pinned_at'
                )
                ->limit(12)
                ->get();

        $recentConversations =
            Conversation::query()
                ->where(
                    'user_id',
                    $user->id
                )
                ->whereNull(
                    'pinned_at'
                )
                ->with([
                    'category',
                    'latestMessage',
                ])
                ->latest(
                    'updated_at'
                )
                ->limit(30)
                ->get();

        if ($conversation) {
            /*
             * IDOR protection.
             */
            abort_unless(
                (int)
                    $conversation
                        ->user_id
                ===
                (int)
                    $user->id,
                404
            );

            $conversation->load([
                'category',

                'messages' => fn (
                    $query
                ) => $query
                    ->oldest(),
            ]);
        }

        return view(
            'research.workspace.index',
            [
                'conversation' => $conversation,

                'pinnedConversations' => $pinnedConversations,

                'recentConversations' => $recentConversations,

                'selectedSource' => $selectedSource,

                'categories' => Category::query()
                    ->where(
                        'status',
                        'active'
                    )
                    ->orderBy(
                        'name'
                    )
                    ->get(),

                'prompts' => PromptTemplate::query()
                    ->where(
                        'status',
                        'active'
                    )
                    ->whereHas(
                        'aiTool',
                        fn (
                            $query
                        ) => $query
                            ->where(
                                'status',
                                'active'
                            )
                    )
                    ->with(
                        'aiTool'
                    )
                    ->orderBy(
                        'name'
                    )
                    ->get(),

                'libraryCount' => Document::query()
                    ->eligibleForResearch()
                    ->count(),
            ]
        );
    }

    public function run(
        Request $request,
        ResearchRunService $runService
    ): RedirectResponse {
        $data =
            $request->validate([
                'conversation_id' => [
                    'nullable',
                    'integer',
                    'exists:research_conversations,id',
                ],

                'category_id' => [
                    'required',
                    'integer',
                    'exists:categories,id',
                ],

                'prompt_template_id' => [
                    'required',
                    'integer',
                    'exists:prompt_templates,id',
                ],

                'source_id' => [
                    'nullable',
                    'integer',
                    'exists:documents,id',
                ],

                'message' => [
                    'required',
                    'string',
                    'max:5000',
                ],
            ]);

        $user =
            $request->user();

        /*
        |--------------------------------------------------------------------------
        | Category
        |--------------------------------------------------------------------------
        */

        $category =
            Category::query()
                ->where(
                    'status',
                    'active'
                )
                ->findOrFail(
                    $data[
                        'category_id'
                    ]
                );

        /*
        |--------------------------------------------------------------------------
        | Prompt + Research Tool
        |--------------------------------------------------------------------------
        */

        $prompt =
            PromptTemplate::query()
                ->where(
                    'status',
                    'active'
                )
                ->whereHas(
                    'aiTool',
                    fn (
                        $query
                    ) => $query
                        ->where(
                            'status',
                            'active'
                        )
                )
                ->with(
                    'aiTool'
                )
                ->findOrFail(
                    $data[
                        'prompt_template_id'
                    ]
                );

        /*
        |--------------------------------------------------------------------------
        | Eligible Sources
        |--------------------------------------------------------------------------
        */

        $sourceQuery =
            Document::query()
                ->eligibleForResearch()
                ->where(
                    'category_id',
                    $category->id
                );

        if (
            ! empty(
                $data[
                    'source_id'
                ]
            )
        ) {
            $sourceQuery->whereKey(
                $data[
                    'source_id'
                ]
            );
        }

        $sourceIds =
            $sourceQuery
                ->pluck('id')
                ->map(
                    fn ($id) => (int) $id
                )
                ->all();

        if ($sourceIds === []) {
            return back()
                ->withErrors([
                    'message' => 'Belum ada Curated Source yang sudah Published dan memiliki chunks untuk kategori ini.',
                ])
                ->withInput();
        }

        /*
        |--------------------------------------------------------------------------
        | Existing Conversation
        |--------------------------------------------------------------------------
        */

        $conversation =
            null;

        if (
            ! empty(
                $data[
                    'conversation_id'
                ]
            )
        ) {
            $conversation =
                Conversation::query()
                    ->where(
                        'user_id',
                        $user->id
                    )
                    ->findOrFail(
                        $data[
                            'conversation_id'
                        ]
                    );
        }

        /*
        |--------------------------------------------------------------------------
        | Conversation + AI Run
        |--------------------------------------------------------------------------
        */

        [
            $conversation,
            $run,
        ] = DB::transaction(
            function () use (
                $conversation,
                $user,
                $category,
                $prompt,
                $sourceIds,
                $data
            ): array {
                if (! $conversation) {
                    $conversation =
                        Conversation::create([
                            'user_id' => $user->id,

                            'category_id' => $category
                                ->id,

                            'title' => Str::limit(
                                trim(
                                    $data[
                                        'message'
                                    ]
                                ),
                                70,
                                '…'
                            ),

                            'status' => 'active',
                        ]);
                } else {
                    $conversation->update([
                        'category_id' => $category
                            ->id,
                    ]);
                }

                $run =
                    AiRun::create([
                        'uuid' => (string)
                                Str::uuid(),

                        'user_id' => $user->id,

                        'ai_tool_id' => $prompt
                            ->ai_tool_id,

                        'prompt_template_id' => $prompt
                            ->id,

                        'status' => 'queued',

                        'user_parameters_json' => [
                            'category_id' => $category
                                ->id,

                            'category' => $category
                                ->name,

                            'document_ids' => $sourceIds,

                            'user_message' => trim(
                                $data[
                                    'message'
                                ]
                            ),

                            'research_tool_code' => $prompt
                                ->aiTool
                                ?->code,

                            'prompt_version' => $prompt
                                ->version,
                        ],

                        'model_provider' => config(
                            'research.ai_provider'
                        ),

                        'model_name' => config(
                            'research.ai_model'
                        ),

                        'started_at' => now(),
                    ]);

                /*
                 * Source provenance.
                 */
                foreach (
                    $sourceIds as $documentId
                ) {
                    DB::table(
                        'ai_run_sources'
                    )
                        ->insertOrIgnore([
                            'ai_run_id' => $run->id,

                            'document_id' => $documentId,

                            'created_at' => now(),

                            'updated_at' => now(),
                        ]);
                }

                /*
                 * User question.
                 */
                ResearchMessage::create([
                    'conversation_id' => $conversation
                        ->id,

                    'user_id' => $user
                        ->id,

                    'role' => 'user',

                    'content' => trim(
                        $data[
                            'message'
                        ]
                    ),

                    'metadata' => [
                    'prompt_template_id' => $prompt
                        ->id,

                    'prompt_version' => $prompt
                        ->version,

                    'research_tool_code' => $prompt
                        ->aiTool
                        ?->code,
                    ],
                ]);

                return [
                    $conversation,
                    $run,
                ];
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Retrieval → Synthesis → Citation Validation
        |--------------------------------------------------------------------------
        */

        try {
            $result =
                $runService
                    ->execute(
                        $run,
                        $conversation,
                        $prompt,
                        $sourceIds,
                        trim(
                            $data[
                                'message'
                            ]
                        )
                    );

            ResearchMessage::create([
                'conversation_id' => $conversation
                    ->id,

                'user_id' => $user
                    ->id,

                'role' => 'assistant',

                'content' => $result[
                        'message'
                    ],

                'ai_run_id' => $run
                    ->id,

                'metadata' => [
                    'status' => $result[
                            'status'
                        ],

                    'evidence' => $result[
                            'evidence'
                        ],

                    'sources' => $result[
                            'sources'
                        ],

                    'citations' => $result[
                            'citations'
                        ] ?? [],

                    'next_action' => $result[
                            'next_action'
                        ],
                ],
            ]);

            $flashMessage =
                $result['status']
                    === 'completed'
                ? 'Research selesai dengan citation tervalidasi.'
                : 'Retrieval selesai, tetapi evidence belum cukup.';
        } catch (
            Throwable $exception
        ) {
            report(
                $exception
            );

            $currentStatus =
                $run
                    ->fresh()
                    ->status;

            /*
             * Citation failed tidak ditimpa
             * menjadi generic failed.
             */
            if (
                ! in_array(
                    $currentStatus,
                    [
                        'citation_failed',
                    ],
                    true
                )
            ) {
                $run->update([
                    'status' => 'failed',

                    'finished_at' => now(),

                    'error_message' => Str::limit(
                        $exception
                            ->getMessage(),
                        2000,
                        '…'
                    ),
                ]);
            }

            $currentStatus =
                $run
                    ->fresh()
                    ->status;

            ResearchMessage::create([
                'conversation_id' => $conversation
                    ->id,

                'user_id' => $user
                    ->id,

                'role' => 'assistant',

                'content' => $currentStatus
                        === 'citation_failed'
                    ? 'Jawaban AI tidak ditampilkan karena citation validation gagal.'
                    : 'Research tidak dapat diselesaikan. Silakan coba kembali.',

                'ai_run_id' => $run
                    ->id,

                'metadata' => [
                    'status' => $currentStatus,

                    'evidence' => [],

                    'sources' => [],

                    'citations' => [],

                    'next_action' => 'Coba ulangi research request atau periksa konfigurasi AI/provider.',
                ],
            ]);

            $flashMessage =
                'Research request tersimpan, tetapi proses AI belum berhasil.';
        }

        $conversation->touch();

        return redirect()
            ->route(
                'workspace.conversation',
                $conversation
            )
            ->with(
                'success',
                $flashMessage
            );
    }
}
