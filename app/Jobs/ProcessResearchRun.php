<?php

namespace App\Jobs;

use App\Models\AiRun;
use App\Models\AuditLog;
use App\Models\ResearchMessage;
use App\Services\Research\ResearchRunService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ProcessResearchRun implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 180;

    public bool $failOnTimeout = true;

    public function __construct(
        public readonly int $aiRunId
    ) {
        $this->onQueue(
            (string) config(
                'research.queue.research_queue',
                'research'
            )
        );
    }

    public function handle(
        ResearchRunService $runService
    ): void {
        $run =
            AiRun::query()
                ->with([
                    'conversation',
                    'promptTemplate.aiTool',
                ])
                ->findOrFail(
                    $this->aiRunId
                );

        /*
        |--------------------------------------------------------------------------
        | Terminal Run Guard
        |--------------------------------------------------------------------------
        |
        | Job tidak boleh memproses ulang run yang sudah mencapai
        | terminal state.
        |
        */

        if (
            in_array(
                $run->status,
                [
                    'completed',
                    'insufficient_evidence',
                    'citation_failed',
                ],
                true
            )
        ) {
            return;
        }

        $conversation =
            $run->conversation;

        $prompt =
            $run->promptTemplate;

        if (
            ! $conversation
            ||
            ! $prompt
        ) {
            throw new RuntimeException(
                'AI Run kehilangan conversation atau prompt provenance.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Stored Submission Parameters
        |--------------------------------------------------------------------------
        */

        $parameters =
            is_array(
                $run->user_parameters_json
            )
                ? $run->user_parameters_json
                : [];

        $sourceIds =
            collect(
                $parameters[
                    'document_ids'
                ]
                ?? []
            )
                ->map(
                    fn ($id) =>
                        (int) $id
                )
                ->filter(
                    fn (int $id) =>
                        $id > 0
                )
                ->unique()
                ->values()
                ->all();

        $question =
            trim(
                (string) (
                    $parameters[
                        'user_message'
                    ]
                    ?? ''
                )
            );

        if (
            $sourceIds === []
            ||
            $question === ''
        ) {
            throw new RuntimeException(
                'AI Run tidak mempunyai source candidate atau user question.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Start Processing
        |--------------------------------------------------------------------------
        */

        $run->update([
            'status' =>
                'queued',

            'started_at' =>
                $run->started_at
                ?: now(),

            'finished_at' =>
                null,

            'error_message' =>
                null,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Assistant Placeholder
        |--------------------------------------------------------------------------
        */

        $assistant =
            ResearchMessage::query()
                ->where(
                    'ai_run_id',
                    $run->id
                )
                ->where(
                    'role',
                    'assistant'
                )
                ->latest(
                    'id'
                )
                ->first();

        try {

            /*
            |--------------------------------------------------------------------------
            | Existing Research Pipeline
            |--------------------------------------------------------------------------
            |
            | ResearchRunService:
            |
            | queued
            | → retrieving
            | → evidence_ready
            | → generating
            | → completed
            |
            | atau:
            |
            | → insufficient_evidence
            | → citation_failed
            |
            */

            $result =
                $runService->execute(
                    $run,
                    $conversation,
                    $prompt,
                    $sourceIds,
                    $question
                );

            /*
            |--------------------------------------------------------------------------
            | Ensure Placeholder Exists
            |--------------------------------------------------------------------------
            */

            $assistant ??=
                ResearchMessage::create([
                    'conversation_id' =>
                        $conversation->id,

                    'user_id' =>
                        $run->user_id,

                    'role' =>
                        'assistant',

                    'content' =>
                        'Research sedang diproses.',

                    'ai_run_id' =>
                        $run->id,

                    'metadata' => [
                        'status' =>
                            'queued',
                    ],
                ]);

            /*
            |--------------------------------------------------------------------------
            | Replace Placeholder With Final Result
            |--------------------------------------------------------------------------
            */

            $assistant->update([
                'content' =>
                    (string) (
                        $result[
                            'message'
                        ]
                        ??
                        'Research selesai.'
                    ),

                'metadata' => [
                    'status' =>
                        $result[
                            'status'
                        ]
                        ??
                        $run
                            ->fresh()
                            ->status,

                    'evidence' =>
                        $result[
                            'evidence'
                        ]
                        ?? [],

                    'sources' =>
                        $result[
                            'sources'
                        ]
                        ?? [],

                    'citations' =>
                        $result[
                            'citations'
                        ]
                        ?? [],

                    'next_action' =>
                        $result[
                            'next_action'
                        ]
                        ?? null,
                ],
            ]);

            /*
            |--------------------------------------------------------------------------
            | Update Conversation Activity
            |--------------------------------------------------------------------------
            */

            $conversation->touch();

            /*
            |--------------------------------------------------------------------------
            | Audit
            |--------------------------------------------------------------------------
            */

            $this->audit(
                $run,
                'research.'
                .(
                    $result[
                        'status'
                    ]
                    ?? 'completed'
                ),
                [
                    'conversation_id' =>
                        $conversation->id,

                    'prompt_template_id' =>
                        $prompt->id,

                    'prompt_version' =>
                        $prompt->version,

                    'retrievals' =>
                        $run
                            ->retrievals()
                            ->count(),

                    'citations' =>
                        $run
                            ->citations()
                            ->count(),

                    'results' =>
                        $run
                            ->results()
                            ->count(),
                ]
            );
        } catch (
            Throwable $exception
        ) {
            /*
            |--------------------------------------------------------------------------
            | Determine State Produced By Pipeline
            |--------------------------------------------------------------------------
            |
            | CitationValidator memang menggunakan exception untuk menghentikan
            | synthesis ketika citation invalid.
            |
            | Tetapi citation_failed adalah EXPECTED TERMINAL DOMAIN STATE,
            | bukan queue infrastructure failure.
            |
            */

            $run->refresh();

            $currentStatus =
                (string)
                    $run->status;

            /*
            |--------------------------------------------------------------------------
            | CITATION FAILED
            |--------------------------------------------------------------------------
            |
            | Sangat penting:
            |
            | JANGAN throw ulang exception.
            |
            | Kalau queue connection = sync, throw ulang akan naik ke
            | HTTP request dan menghasilkan 500.
            |
            | Padahal pipeline sebenarnya sudah bekerja benar:
            |
            | invalid citation
            | → citation_failed
            | → invalid answer disembunyikan
            | → request tetap kembali ke workspace
            |
            */

            if (
                $currentStatus
                ===
                'citation_failed'
            ) {
                $assistant ??=
                    ResearchMessage::create([
                        'conversation_id' =>
                            $conversation->id,

                        'user_id' =>
                            $run->user_id,

                        'role' =>
                            'assistant',

                        'content' =>
                            'Research sedang diproses.',

                        'ai_run_id' =>
                            $run->id,

                        'metadata' => [
                            'status' =>
                                'queued',
                        ],
                    ]);

                $assistant->update([
                    'content' =>
                        'Jawaban AI tidak ditampilkan karena citation validation gagal.',

                    'metadata' => [
                        'status' =>
                            'citation_failed',

                        'evidence' =>
                            [],

                        'sources' =>
                            [],

                        'citations' =>
                            [],

                        'next_action' =>
                            'Periksa citation validation atau jalankan ulang research.',
                    ],
                ]);

                $conversation->touch();

                $this->audit(
                    $run,
                    'research.citation_failed',
                    [
                        'conversation_id' =>
                            $conversation->id,

                        'prompt_template_id' =>
                            $prompt->id,

                        'prompt_version' =>
                            $prompt->version,

                        'error' =>
                            Str::limit(
                                $exception
                                    ->getMessage(),
                                1000,
                                '…'
                            ),

                        'retrievals' =>
                            $run
                                ->retrievals()
                                ->count(),

                        'citations' =>
                            $run
                                ->citations()
                                ->count(),

                        'results' =>
                            $run
                                ->results()
                                ->count(),
                    ]
                );

                /*
                 * Expected domain failure.
                 *
                 * Job dianggap selesai dengan benar,
                 * tetapi AI Run tetap citation_failed.
                 */
                return;
            }

            /*
            |--------------------------------------------------------------------------
            | Real Unexpected Failure
            |--------------------------------------------------------------------------
            */

            report(
                $exception
            );

            $run->update([
                'status' =>
                    'failed',

                'finished_at' =>
                    now(),

                'error_message' =>
                    Str::limit(
                        $exception
                            ->getMessage(),
                        2000,
                        '…'
                    ),
            ]);

            $assistant ??=
                ResearchMessage::create([
                    'conversation_id' =>
                        $conversation->id,

                    'user_id' =>
                        $run->user_id,

                    'role' =>
                        'assistant',

                    'content' =>
                        'Research sedang diproses.',

                    'ai_run_id' =>
                        $run->id,

                    'metadata' => [
                        'status' =>
                            'queued',
                    ],
                ]);

            $assistant->update([
                'content' =>
                    'Research tidak dapat diselesaikan. Silakan coba kembali.',

                'metadata' => [
                    'status' =>
                        'failed',

                    'evidence' =>
                        [],

                    'sources' =>
                        [],

                    'citations' =>
                        [],

                    'next_action' =>
                        'Coba ulangi research request atau periksa konfigurasi AI/provider.',
                ],
            ]);

            $conversation->touch();

            $this->audit(
                $run,
                'research.failed',
                [
                    'conversation_id' =>
                        $conversation->id,

                    'prompt_template_id' =>
                        $prompt->id,

                    'prompt_version' =>
                        $prompt->version,

                    'error' =>
                        Str::limit(
                            $exception
                                ->getMessage(),
                            1000,
                            '…'
                        ),
                ]
            );

            /*
             * Ini benar-benar unexpected infrastructure/application failure.
             *
             * Biarkan Laravel queue mencatat failed job.
             */
            throw $exception;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Queue-Level Failure
    |--------------------------------------------------------------------------
    |
    | Method ini hanya digunakan kalau job benar-benar gagal pada level queue:
    |
    | - timeout
    | - database error
    | - provider crash
    | - uncaught exception
    |
    | citation_failed tidak seharusnya masuk sini lagi.
    |
    */

    public function failed(
        ?Throwable $exception
    ): void {
        $run =
            AiRun::query()
                ->with(
                    'conversation'
                )
                ->find(
                    $this->aiRunId
                );

        if (! $run) {
            return;
        }

        /*
         * Jangan timpa terminal domain state yang sudah valid.
         */

        if (
            in_array(
                $run->status,
                [
                    'completed',
                    'insufficient_evidence',
                    'citation_failed',
                ],
                true
            )
        ) {
            return;
        }

        $run->update([
            'status' =>
                'failed',

            'finished_at' =>
                now(),

            'error_message' =>
                Str::limit(
                    $exception
                        ?->getMessage()
                    ?: 'Queue job gagal.',
                    2000,
                    '…'
                ),
        ]);

        ResearchMessage::query()
            ->where(
                'ai_run_id',
                $run->id
            )
            ->where(
                'role',
                'assistant'
            )
            ->update([
                'content' =>
                    'Research tidak dapat diselesaikan. Silakan coba kembali.',

                'metadata' => [
                    'status' =>
                        'failed',

                    'evidence' =>
                        [],

                    'sources' =>
                        [],

                    'citations' =>
                        [],

                    'next_action' =>
                        'Coba ulangi research request atau periksa queue worker.',
                ],
            ]);

        $run
            ->conversation
            ?->touch();

        $this->audit(
            $run,
            'research.queue_failed',
            [
                'error' =>
                    Str::limit(
                        $exception
                            ?->getMessage()
                        ?: 'Queue job gagal.',
                        1000,
                        '…'
                    ),
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Audit Helper
    |--------------------------------------------------------------------------
    */

    private function audit(
        AiRun $run,
        string $action,
        array $metadata = []
    ): void {
        try {
            AuditLog::create([
                'user_id' =>
                    $run->user_id,

                'action' =>
                    $action,

                'auditable_type' =>
                    AiRun::class,

                'auditable_id' =>
                    $run->id,

                'metadata_json' =>
                    $metadata,

                'ip_address' =>
                    null,
            ]);
        } catch (
            Throwable
        ) {
            /*
             * Audit tidak boleh memblokir research pipeline.
             */
        }
    }
}