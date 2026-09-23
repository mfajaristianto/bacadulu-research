<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

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
        $expectedPassword = (string) config('research.admin_password');

        $configured = $expectedEmail !== '' && $expectedPassword !== '';
        $valid = $configured
            && hash_equals(strtolower($expectedEmail), strtolower($data['email']))
            && hash_equals($expectedPassword, $data['password']);

        if (! $valid) {
            return back()
                ->withErrors(['email' => 'Email atau password admin tidak sesuai.'])
                ->onlyInput('email');
        }

        $adminUser = User::query()->where('email', $expectedEmail)->first();

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

        return redirect()->route('admin.dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->forget([
            'research_admin_authenticated',
            'research_admin_email',
            'research_admin_user_id',
        ]);
        $request->session()->regenerate(true);
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
