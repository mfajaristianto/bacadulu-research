<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:160'],
            'status' => ['nullable', 'in:active,suspended'],
        ]);

        $query = User::query()->withCount(['conversations', 'aiRuns']);

        if (filled($filters['search'] ?? null)) {
            $search = trim((string) $filters['search']);
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('institution', 'like', "%{$search}%");
            });
        }

        if (filled($filters['status'] ?? null)) {
            $query->where('status', $filters['status']);
        }

        return view('admin.users.index', [
            'users' => $query->latest('id')->paginate(25)->withQueryString(),
        ]);
    }

    public function show(User $user): View
    {
        $user->loadCount(['conversations', 'aiRuns', 'savedResults']);

        return view('admin.users.show', ['user' => $user]);
    }

    public function status(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:active,suspended'],
        ]);

        $adminUserId = $request->session()->get('research_admin_user_id');

        if ($adminUserId && (int) $adminUserId === (int) $user->id && $data['status'] !== 'active') {
            return back()->withErrors(['status' => 'Akun administrator yang sedang digunakan tidak dapat disuspend dari sesi ini.']);
        }

        $user->update(['status' => $data['status']]);

        return back()->with('success', $data['status'] === 'active' ? 'User diaktifkan kembali.' : 'User berhasil disuspend.');
    }
}
