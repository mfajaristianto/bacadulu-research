<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->get('research_admin_authenticated', false)) {
            return redirect()->route('admin.login');
        }

        $adminUserId = $request->session()->get('research_admin_user_id');

        if ($adminUserId) {
            $adminUser = User::query()->find($adminUserId);

            if (! $adminUser || $adminUser->status !== 'active') {
                $request->session()->forget([
                    'research_admin_authenticated',
                    'research_admin_email',
                    'research_admin_user_id',
                ]);

                return redirect()
                    ->route('admin.login')
                    ->withErrors(['email' => 'Akun administrator tidak aktif.']);
            }
        }

        return $next($request);
    }
}
