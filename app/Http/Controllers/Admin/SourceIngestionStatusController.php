<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\JsonResponse;

class SourceIngestionStatusController extends Controller
{
    public function show(
        Document $document
    ): JsonResponse {
        $document
            ->loadCount(
                'chunks'
            );

        $latestFile =
            $document
                ->files()
                ->latest(
                    'id'
                )
                ->first();

        $terminal =
            in_array(
                $document
                    ->status,
                [
                    'draft',
                    'ready',
                    'published',
                    'failed',
                    'archived',
                ],
                true
            );

        return response()
            ->json([
                'id' =>
                    $document->id,

                'status' =>
                    $document->status,

                'stage' =>
                    $document
                        ->processing_stage,

                'progress' =>
                    (int) (
                        $document
                            ->processing_progress
                        ??
                        0
                    ),

                'terminal' =>
                    $terminal,

                'chunks' =>
                    (int)
                        $document
                            ->chunks_count,

                'processing_error' =>
                    $document
                        ->status
                    ===
                    'failed'
                        ? $document
                            ->processing_error
                        : null,

                'processing_started_at' =>
                    $document
                        ->processing_started_at
                        ?->toIso8601String(),

                'processing_finished_at' =>
                    $document
                        ->processing_finished_at
                        ?->toIso8601String(),

                'file' =>
                    $latestFile
                        ? [
                            'id' =>
                                $latestFile
                                    ->id,

                            'name' =>
                                $latestFile
                                    ->original_name,

                            'status' =>
                                $latestFile
                                    ->status,
                        ]
                        : null,
            ]);
    }
}