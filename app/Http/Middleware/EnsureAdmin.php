<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Security\AdminAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $session = $request->session();

        if (! $session->get('research_admin_authenticated', false)) {
            return redirect()->route('admin.login');
        }

        $adminUserId = $session->get('research_admin_user_id');

        if ($adminUserId) {
            $adminUser = User::query()->find($adminUserId);
            // Administrator tambahan (login lewat users.admin_role): peran dibaca ulang tiap permintaan, jadi pencabutan atau
            // perubahan peran berlaku seketika. Sesi admin dari konfigurasi (ADMIN_EMAIL) tidak bergantung pada kolom itu.
            $role = $session->get('research_admin_db') && $adminUser ? AdminAccess::roleFor($adminUser) : 'super_admin';

            if (! $adminUser || $adminUser->status !== 'active' || $role === null) {
                $session->forget([
                    'research_admin_authenticated',
                    'research_admin_email',
                    'research_admin_user_id',
                    'research_admin_role',
                    'research_admin_db',
                ]);

                return redirect()
                    ->route('admin.login')
                    ->withErrors(['email' => 'Akun administrator tidak aktif.']);
            }

            $session->get('research_admin_db') && $session->put('research_admin_role', $role);
        }

        // Sesi tanpa peran = sesi admin konfigurasi yang dibuat sebelum peran ada: Super Admin (satu-satunya jalur masuk saat itu).
        $session->has('research_admin_role') || $session->put('research_admin_role', 'super_admin');

        return $next($request);
    }
}
