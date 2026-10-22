<?php

namespace App\Http\Middleware;

use App\Models\AiRun;
use App\Models\Conversation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResearchLiveRefresh
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $response =
            $next(
                $request
            );

        if (
            $request->method()
            !==
            'GET'
            ||
            ! $request->user()
            ||
            $response
                ->getStatusCode()
            !==
            200
        ) {
            return $response;
        }

        $conversation =
            $request
                ->route(
                    'conversation'
                );

        if (
            ! $conversation
                instanceof
                Conversation
        ) {
            return $response;
        }

        if (
            (int)
                $conversation
                    ->user_id
            !==
            (int)
                $request
                    ->user()
                    ->id
        ) {
            return $response;
        }

        /*
        |--------------------------------------------------------------------------
        | Any Active Job?
        |--------------------------------------------------------------------------
        */

        $hasPendingRun =
            AiRun::query()
                ->where(
                    'conversation_id',
                    $conversation->id
                )
                ->where(
                    'user_id',
                    $request
                        ->user()
                        ->id
                )
                ->whereIn(
                    'status',
                    [
                        'queued',
                        'retrieving',
                        'evidence_ready',
                        'generating',
                    ]
                )
                ->exists();

        if ($hasPendingRun) {
            // The browser polls /research/runs/{id}/status through JS.
            // Sending an HTTP Refresh header caused involuntary full-page reloads.
            $response->headers->remove('Refresh');
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}