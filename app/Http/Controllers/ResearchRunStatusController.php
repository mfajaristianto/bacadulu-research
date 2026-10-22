<?php

namespace App\Http\Controllers;

use App\Models\AiRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResearchRunStatusController extends Controller
{
    public function show(
        Request $request,
        AiRun $aiRun
    ): JsonResponse {
        /*
         * IDOR protection.
         */

        abort_unless(
            (int)
                $aiRun->user_id
            ===
            (int)
                $request
                    ->user()
                    ->id,
            404
        );

        $status =
            (string)
                $aiRun->status;

        $progress =
            match (
                $status
            ) {
                'queued' =>
                    10,

                'retrieving' =>
                    35,

                'evidence_ready' =>
                    60,

                'generating' =>
                    80,

                'completed',
                'insufficient_evidence',
                'citation_failed',
                'failed' =>
                    100,

                default =>
                    5,
            };

        $terminal =
            in_array(
                $status,
                [
                    'completed',
                    'insufficient_evidence',
                    'citation_failed',
                    'failed',
                ],
                true
            );

        return response()
            ->json([
                'id' =>
                    $aiRun->id,

                'uuid' =>
                    $aiRun->uuid,

                'status' =>
                    $status,

                'progress' =>
                    $progress,

                'terminal' =>
                    $terminal,

                /*
                 * Jangan expose internal error kecuali run memang failed.
                 */
                'error_message' =>
                    $status
                    ===
                    'failed'
                        ? $aiRun
                            ->error_message
                        : null,

                'updated_at' =>
                    $aiRun
                        ->updated_at
                        ?->toIso8601String(),
            ]);
    }
}