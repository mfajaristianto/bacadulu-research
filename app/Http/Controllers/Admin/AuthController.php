<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use RuntimeException;

class AuthController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if ($request->session()->get('research_admin_authenticated', false)) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $expectedEmail = trim((string) config('research.admin_email'));
        $expectedHash = trim((string) config('research.admin_password_hash'));
        $expectedPassword = (string) config('research.admin_password');

        $configured = $expectedEmail !== '' && ($expectedHash !== '' || $expectedPassword !== '');
        $valid = $configured
            && hash_equals(strtolower($expectedEmail), strtolower($data['email']))
            && $this->passwordMatches($data['password'], $expectedHash, $expectedPassword);

        if (! $valid) {
            $this->audit($request, 'admin.login.failed', null, [
                'email' => strtolower(trim($data['email'])),
            ]);

            return back()
                ->withErrors(['email' => 'Email atau password admin tidak sesuai.'])
                ->onlyInput('email');
        }

        $adminUser = User::query()->whereRaw('LOWER(email) = ?', [strtolower($expectedEmail)])->first();

        if ($adminUser && $adminUser->status !== 'active') {
            return back()
                ->withErrors(['email' => 'Akun administrator tidak aktif.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();
        $request->session()->put([
            'research_admin_authenticated' => true,
            'research_admin_email' => $expectedEmail,
            'research_admin_user_id' => $adminUser?->id,
        ]);

        $this->audit($request, 'admin.login', $adminUser?->id, [
            'email' => $expectedEmail,
        ]);

        return redirect()->route('admin.dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $adminUserId = $request->session()->get('research_admin_user_id');
        $adminEmail = $request->session()->get('research_admin_email');

        $this->audit($request, 'admin.logout', $adminUserId, [
            'email' => $adminEmail,
        ]);

        $request->session()->forget([
            'research_admin_authenticated',
            'research_admin_email',
            'research_admin_user_id',
        ]);

        $request->session()->regenerate(true);
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    private function passwordMatches(string $given, string $hash, string $plaintext): bool
    {
        if ($hash !== '') {
            try {
                return Hash::check($given, $hash);
            } catch (RuntimeException $exception) {
                // A malformed ADMIN_PASSWORD_HASH must fail closed, not with a 500.
                report($exception);

                return false;
            }
        }

        return $plaintext !== '' && hash_equals($plaintext, $given);
    }

    private function audit(Request $request, string $action, ?int $userId, array $metadata = []): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        AuditLog::create([
            'user_id' => $userId,
            'action' => $action,
            'auditable_type' => 'admin_session',
            'auditable_id' => $userId,
            'metadata_json' => $metadata,
            'ip_address' => $request->ip(),
        ]);
    }
}
