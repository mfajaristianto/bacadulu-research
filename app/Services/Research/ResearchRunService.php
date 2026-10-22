<?php

namespace App\Services\Research;

use App\Models\AiRun;
use App\Models\Conversation;
use App\Models\PromptTemplate;

class ResearchRunService
{
    public function __construct(
        private readonly ResearchEvidencePipeline $evidencePipeline,
        private readonly ResearchSynthesisService $synthesisService
    ) {}

    public function execute(
        AiRun $run,
        Conversation $conversation,
        PromptTemplate $prompt,
        array $sourceIds,
        string $question
    ): array {
        /*
        |--------------------------------------------------------------------------
        | Direct Conversation Provenance
        |--------------------------------------------------------------------------
        |
        | Sebelumnya hubungan AI Run → Conversation hanya dapat
        | ditelusuri melalui ResearchMessage.
        |
        | Sekarang AI Run menyimpan conversation_id secara langsung.
        |
        */

        if (
            (int) $run->conversation_id
            !==
            (int) $conversation->id
        ) {
            $run->update([
                'conversation_id' => $conversation->id,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Step 1 — Retrieval
        |--------------------------------------------------------------------------
        */

        $prepared =
            $this->evidencePipeline
                ->prepare(
                    $run,
                    $prompt,
                    $sourceIds,
                    $question
                );

        /*
         * Evidence tidak cukup:
         *
         * STOP.
         *
         * Jangan memanggil LLM untuk mengarang
         * jawaban tanpa dasar evidence.
         */
        if (
            $prepared['status']
            !== 'evidence_ready'
        ) {
            return [
                ...$prepared,

                'citations' => [],
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Step 2 — AI Synthesis
        |--------------------------------------------------------------------------
        */

        return $this
            ->synthesisService
            ->synthesize(
                $run,
                $conversation,
                $prompt,
                $question,
                $prepared
            );
    }
}
