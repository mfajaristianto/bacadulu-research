<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class SecurityController extends Controller
{
    public function index(Request $request): View
    {
        $recentAdminEvents = collect();

        if (Schema::hasTable('audit_logs')) {
            $recentAdminEvents = AuditLog::query()
                ->whereIn('action', ['admin.login', 'admin.logout', 'admin.login.failed'])
                ->latest('id')
                ->limit(15)
                ->get();
        }

        return view('admin.security.index', [
            'recentAdminEvents' => $recentAdminEvents,
            'security' => [
                'environment' => app()->environment(),
                'debug' => (bool) config('app.debug'),
                'session_driver' => (string) config('session.driver'),
                'secure_cookie' => (bool) config('session.secure'),
                'same_site' => (string) config('session.same_site', 'lax'),
                'email_verification' => (bool) config('research.require_email_verification', false),
                'admin_path' => (string) config('research.admin_path'),
                'admin_email_configured' => filled(config('research.admin_email')),
                'admin_password_configured' => filled(config('research.admin_password_hash')) || filled(config('research.admin_password')),
                'admin_password_hashed' => filled(config('research.admin_password_hash')),
                'current_ip' => $request->ip(),
            ],
        ]);
    }
}
