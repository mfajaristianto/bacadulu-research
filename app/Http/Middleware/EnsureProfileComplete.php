<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProfileComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }

        if (config('research.profile_fields_optional', true)
            && filled($user->name)
            && filled($user->email)
            && filled($user->institution)) {
            return $next($request);
        }

        if ($user->profileCompletion() < config('research.profile_gate', 80)) {
            return redirect()->route('profile.setup');
        }

        return $next($request);
    }
}
