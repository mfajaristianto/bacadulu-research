<?php

namespace App\Http\Middleware;

use App\Models\Document;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SourceProcessingUi
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $document =
            null;

        $shouldRefresh =
            false;

        /*
        |--------------------------------------------------------------------------
        | Source Detail
        |--------------------------------------------------------------------------
        */

        if (
            $request
                ->isMethod(
                    'GET'
                )
            &&
            $request
                ->routeIs(
                    'admin.library.edit'
                )
        ) {
            $routeDocument =
                $request
                    ->route(
                        'document'
                    );

            if (
                $routeDocument
                instanceof
                Document
            ) {
                $document =
                    $routeDocument
                        ->fresh();
            }

            if (
                $document?->status
                ===
                'processing'
            ) {
                $shouldRefresh =
                    true;

                if (
                    ! $request
                        ->session()
                        ->has(
                            'warning'
                        )
                ) {
                    $request
                        ->session()
                        ->flash(
                            'warning',
                            $this
                                ->processingMessage(
                                    $document
                                )
                        );
                }
            }

            /*
             * Error tersimpan di DB.
             * Jadi tetap terlihat setelah refresh/browser reload.
             */

            if (
                $document?->status
                ===
                'failed'
                &&
                filled(
                    $document
                        ->processing_error
                )
                &&
                ! $request
                    ->session()
                    ->has(
                        'error'
                    )
            ) {
                $request
                    ->session()
                    ->flash(
                        'error',
                        'Ingestion PDF gagal: '
                        .$document
                            ->processing_error
                    );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Library Index
        |--------------------------------------------------------------------------
        */

        if (
            $request
                ->isMethod(
                    'GET'
                )
            &&
            $request
                ->routeIs(
                    'admin.library.index'
                )
            &&
            Document::query()
                ->where(
                    'status',
                    'processing'
                )
                ->exists()
        ) {
            $shouldRefresh =
                true;

            if (
                ! $request
                    ->session()
                    ->has(
                        'warning'
                    )
            ) {
                $request
                    ->session()
                    ->flash(
                        'warning',
                        'Ada source yang sedang diproses di background. Daftar akan diperbarui otomatis.'
                    );
            }
        }

        $response =
            $next(
                $request
            );

        if (
            $shouldRefresh
            &&
            $response
                ->getStatusCode()
            ===
            200
        ) {
            $seconds =
                max(
                    1,
                    (int)
                        config(
                            'research.queue.live_refresh_seconds',
                            2
                        )
                );

            $response
                ->headers
                ->set(
                    'Refresh',
                    $seconds
                    .'; url='
                    .$request
                        ->fullUrl()
                );

            $response
                ->headers
                ->set(
                    'Cache-Control',
                    'no-store, private'
                );
        }

        return $response;
    }

    private function processingMessage(
        Document $document
    ): string {
        $stage =
            match (
                $document
                    ->processing_stage
            ) {
                'queued' =>
                    'menunggu queue worker',

                'extracting' =>
                    'membaca teks dari PDF',

                'chunking' =>
                    'membentuk evidence chunks',

                default =>
                    'memproses source',
            };

        return 'PDF sedang '
            .$stage
            .' · '
            .(
                (int)
                    $document
                        ->processing_progress
            )
            .'%.';
    }
}