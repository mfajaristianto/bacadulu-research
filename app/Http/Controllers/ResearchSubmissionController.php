<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessResearchRun;
use App\Models\AiRun;
use App\Models\Category;
use App\Models\Conversation;
use App\Models\Document;
use App\Models\PromptTemplate;
use App\Models\ResearchMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ResearchSubmissionController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
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

        $user = $request->user();

        /*
        |--------------------------------------------------------------------------
        | Category
        |--------------------------------------------------------------------------
        */

        $category = Category::query()
            ->where(
                'status',
                'active'
            )
            ->findOrFail(
                $data['category_id']
            );

        /*
        |--------------------------------------------------------------------------
        | Active Prompt
        |--------------------------------------------------------------------------
        */

        $prompt = PromptTemplate::query()
            ->where(
                'status',
                'active'
            )
            ->whereHas(
                'aiTool',
                fn ($query) =>
                    $query->where(
                        'status',
                        'active'
                    )
            )
            ->with(
                'aiTool'
            )
            ->findOrFail(
                $data['prompt_template_id']
            );

        /*
        |--------------------------------------------------------------------------
        | Eligible Sources
        |--------------------------------------------------------------------------
        */

        $sourceQuery = Document::query()
            ->eligibleForResearch()
            ->where(
                'category_id',
                $category->id
            );

        if (
            ! empty(
                $data['source_id']
            )
        ) {
            $sourceQuery->whereKey(
                $data['source_id']
            );
        }

        $sourceIds = $sourceQuery
            ->pluck('id')
            ->map(
                fn ($id) =>
                    (int) $id
            )
            ->all();

        if ($sourceIds === []) {
            return back()
                ->withErrors([
                    'message' =>
                        'Belum ada Curated Source yang sudah Published dan memiliki chunks untuk kategori ini.',
                ])
                ->withInput();
        }

        /*
        |--------------------------------------------------------------------------
        | Existing Conversation
        |--------------------------------------------------------------------------
        */

        $conversation = null;

        if (
            ! empty(
                $data['conversation_id']
            )
        ) {
            $conversation = Conversation::query()
                ->where(
                    'user_id',
                    $user->id
                )
                ->findOrFail(
                    $data['conversation_id']
                );

            /*
             * Satu conversation jangan menjalankan dua proses
             * sekaligus karena urutan message bisa kacau.
             */
            $pendingRuns = AiRun::query()
                ->where('user_id', $user->id)
                ->where('conversation_id', $conversation->id)
                ->whereIn('status', [
                    'queued',
                    'retrieving',
                    'evidence_ready',
                    'generating',
                ])
                ->latest('id')
                ->get();

            /*
             * Jangan biarkan run lama mengunci conversation selamanya.
             * Ini yang sebelumnya membuat pertanyaan yang sama terlihat
             * seperti reload tanpa akhir ketika queue worker tidak berjalan.
             */
            $staleBefore = now()->subMinutes(15);

            foreach ($pendingRuns as $pendingRun) {
                $lastActivity = $pendingRun->updated_at ?: $pendingRun->created_at;

                // Never cancel an active generation just because its DB record
                // has not been updated. The worker can be executing remotely.
                if ($pendingRun->status === 'queued' && $lastActivity && $lastActivity->lt($staleBefore)) {
                    $pendingRun->update([
                        'status' => 'failed',
                        'finished_at' => now(),
                        'error_message' => 'Research run dihentikan otomatis karena tidak ada progress lebih dari 15 menit.',
                    ]);

                    $staleAssistant = ResearchMessage::query()
                        ->where('ai_run_id', $pendingRun->id)
                        ->where('role', 'assistant')
                        ->latest('id')
                        ->first();

                    $staleAssistant?->update([
                        'content' => 'Proses research sebelumnya berhenti sebelum selesai. Silakan jalankan ulang pertanyaan.',
                        'metadata' => [
                            'status' => 'failed',
                            'evidence' => [],
                            'sources' => [],
                            'citations' => [],
                            'next_action' => 'Jalankan ulang research request.',
                        ],
                    ]);
                }
            }

            $hasPendingRun = AiRun::query()
                ->where('user_id', $user->id)
                ->where('conversation_id', $conversation->id)
                ->whereIn('status', [
                    'queued',
                    'retrieving',
                    'evidence_ready',
                    'generating',
                ])
                ->exists();

            if ($hasPendingRun) {
                return back()
                    ->withErrors([
                        'message' => 'Research sebelumnya masih benar-benar diproses. Tunggu sampai selesai atau coba lagi beberapa saat.',
                    ])
                    ->withInput();
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Create Conversation + Run + Placeholder
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
                            'user_id' =>
                                $user->id,

                            'category_id' =>
                                $category->id,

                            'title' =>
                                Str::limit(
                                    trim(
                                        $data['message']
                                    ),
                                    70,
                                    '…'
                                ),

                            'status' =>
                                'active',
                        ]);
                } else {
                    $conversation->update([
                        'category_id' =>
                            $category->id,
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | AI Run starts as QUEUED
                |--------------------------------------------------------------------------
                */

                $run =
                    AiRun::create([
                        'uuid' =>
                            (string)
                                Str::uuid(),

                        'user_id' =>
                            $user->id,

                        'conversation_id' =>
                            $conversation->id,

                        'ai_tool_id' =>
                            $prompt->ai_tool_id,

                        'prompt_template_id' =>
                            $prompt->id,

                        'status' =>
                            'queued',

                        'user_parameters_json' => [
                            'category_id' =>
                                $category->id,

                            'category' =>
                                $category->name,

                            'document_ids' =>
                                $sourceIds,

                            'user_message' =>
                                trim(
                                    $data['message']
                                ),

                            'research_tool_code' =>
                                $prompt
                                    ->aiTool
                                    ?->code,

                            'prompt_version' =>
                                $prompt->version,
                        ],

                        'model_provider' =>
                            config(
                                'research.ai_provider'
                            ),

                        'model_name' =>
                            config(
                                'research.ai_model'
                            ),

                        /*
                         * Worker yang akan mengisi started_at.
                         */
                        'started_at' =>
                            null,

                        'finished_at' =>
                            null,

                        'error_message' =>
                            null,
                    ]);

                /*
                |--------------------------------------------------------------------------
                | Candidate Source Provenance
                |--------------------------------------------------------------------------
                */

                foreach (
                    $sourceIds as
                    $documentId
                ) {
                    DB::table(
                        'ai_run_sources'
                    )
                        ->insertOrIgnore([
                            'ai_run_id' =>
                                $run->id,

                            'document_id' =>
                                $documentId,

                            'created_at' =>
                                now(),

                            'updated_at' =>
                                now(),
                        ]);
                }

                /*
                |--------------------------------------------------------------------------
                | User Message
                |--------------------------------------------------------------------------
                */

                ResearchMessage::create([
                    'conversation_id' =>
                        $conversation->id,

                    'user_id' =>
                        $user->id,

                    'role' =>
                        'user',

                    'content' =>
                        trim(
                            $data['message']
                        ),

                    'metadata' => [
                        'prompt_template_id' =>
                            $prompt->id,

                        'prompt_version' =>
                            $prompt->version,

                        'research_tool_code' =>
                            $prompt
                                ->aiTool
                                ?->code,
                    ],
                ]);

                /*
                |--------------------------------------------------------------------------
                | Assistant Placeholder
                |--------------------------------------------------------------------------
                |
                | Kita UPDATE message ini nanti dari Queue Job.
                | Jadi tidak membuat bubble assistant kedua.
                |
                */

                ResearchMessage::create([
                    'conversation_id' =>
                        $conversation->id,

                    'user_id' =>
                        $user->id,

                    'role' =>
                        'assistant',

                    'content' =>
                        'Research sedang diproses dari evidence yang tersedia…',

                    'ai_run_id' =>
                        $run->id,

                    'metadata' => [
                        'status' =>
                            'queued',

                        'evidence' =>
                            [],

                        'sources' =>
                            [],

                        'citations' =>
                            [],

                        'next_action' =>
                            'Menunggu retrieval dan synthesis.',
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
        | Dispatch
        |--------------------------------------------------------------------------
        */

        /*
         * Local development should work even when the developer has not
         * started `php artisan queue:work`. Production still uses the real
         * queue when configured for database/redis.
         */
        $runInline = config('queue.default') === 'sync'
            || app()->environment('local');

        if ($runInline) {
            ProcessResearchRun::dispatchSync($run->id);
        } else {
            ProcessResearchRun::dispatch($run->id);
        }

        return redirect()
            ->route('workspace.conversation', $conversation)
            ->with(
                'success',
                $runInline
                    ? 'Research selesai diproses.'
                    : 'Research masuk antrean. Progress akan diperbarui tanpa reload halaman.'
            );
    }
}