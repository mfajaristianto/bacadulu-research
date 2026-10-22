<?php

namespace App\Http\Middleware;

use App\Services\Monitoring\SentryContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SentryRequestContext
{
    public function __construct(
        private readonly SentryContext $sentryContext
    ) {}

    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $this
            ->sentryContext
            ->forRequest(
                $request
            );

        return $next(
            $request
        );
    }
}