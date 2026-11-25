<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Security\AdminAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/*
 * Admin & Keamanan: daftar administrator panel. Melihat daftar boleh untuk semua admin; menambah, mengubah peran, dan mencabut
 * HANYA Super Admin (dicek di server, bukan hanya disembunyikan di UI). Admin biasa tidak dapat menaikkan peran dirinya sendiri.
 * Akun dari ADMIN_EMAIL adalah Super Admin implisit dan tidak dapat diubah/dicabut dari sini. Mencabut tidak menghapus akun pengguna.
 */
class AdministratorController extends Controller
{
    public function index(Request $request): View
    {
        $supported = AdminAccess::supported();
        $admins = User::query()
            ->when($supported, fn ($q) => $q->where(fn ($w) => $w->whereNotNull('admin_role')->orWhereRaw('lower(email) = ?', [AdminAccess::configEmail()])),
                fn ($q) => $q->whereRaw('lower(email) = ?', [AdminAccess::configEmail()]))
            ->orderByRaw('lower(email)')->get();

        // Login panel terakhir per akun dari audit (admin.login mencatat user_id bila akun ada).
        $lastPanelLogin = Schema::hasTable('audit_logs')
            ? AuditLog::query()->where('action', 'admin.login')->whereIn('user_id', $admins->pluck('id'))->toBase()
                ->selectRaw('user_id, max(created_at) as at')->groupBy('user_id')->pluck('at', 'user_id')->all()
            : [];

        return view('admin.administrators.index', [
            'admins' => $admins, 'supported' => $supported, 'isSuper' => AdminAccess::isSuper($request), 'roles' => AdminAccess::ROLES,
            'configEmail' => AdminAccess::configEmail(), 'lastPanelLogin' => $lastPanelLogin, 'meId' => $request->session()->get('research_admin_user_id'),
            'configHasAccount' => $admins->contains(fn ($u) => AdminAccess::isConfigAdmin($u)),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($denied = $this->requireSuper($request)) {
            return $denied;
        }
        if (! AdminAccess::supported()) {
            return back()->withErrors(['email' => 'Fitur akun administrator belum aktif: migration database belum dijalankan.']);
        }

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::in(array_keys(AdminAccess::ROLES))],
            'name' => ['nullable', 'string', 'max:120'],
            'password' => ['nullable', 'string', 'min:12', 'max:200', 'confirmed'],
        ], ['password.min' => 'Kata sandi minimal 12 karakter.', 'password.confirmed' => 'Konfirmasi kata sandi tidak sama.']);

        $email = strtolower(trim($data['email']));
        if ($email === AdminAccess::configEmail()) {
            return back()->withErrors(['email' => 'Akun ini sudah menjadi Super Admin utama (dari konfigurasi server).'])->withInput();
        }

        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        if ($user?->admin_role) {
            return back()->withErrors(['email' => 'Akun ini sudah menjadi administrator.'])->withInput();
        }
        if (! $user && (blank($data['name'] ?? null) || blank($data['password'] ?? null))) {
            return back()->withErrors(['email' => 'Email belum terdaftar sebagai pengguna: isi nama dan kata sandi untuk membuat akun admin baru.'])->withInput();
        }

        $user ??= User::query()->create([
            'name' => trim($data['name']), 'email' => $email, 'password' => $data['password'], 'status' => 'active',
        ]);
        $user->forceFill(['admin_role' => $data['role'], 'status' => 'active'])->save();
        $this->audit($request, 'admin.account_created', $user, ['role' => $data['role'], 'new_account' => $user->wasRecentlyCreated]);

        return redirect()->route('admin.administrators.index')->with('success', 'Akun administrator ditambahkan.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        if ($denied = $this->requireSuper($request)) {
            return $denied;
        }
        $data = $request->validate(['role' => ['required', Rule::in(array_keys(AdminAccess::ROLES))]]);

        if (AdminAccess::isConfigAdmin($user)) {
            return back()->withErrors(['role' => 'Super Admin utama (konfigurasi server) tidak dapat diubah dari sini.']);
        }
        if (! $user->admin_role) {
            abort(404);
        }
        if ($user->admin_role === 'super_admin' && $data['role'] !== 'super_admin' && ! $this->otherSuperExists($user)) {
            return back()->withErrors(['role' => 'Harus tersisa minimal satu Super Admin.']);
        }

        $from = $user->admin_role;
        $user->forceFill(['admin_role' => $data['role']])->save();
        $this->audit($request, 'admin.account_role_changed', $user, ['from' => $from, 'to' => $data['role']]);

        return back()->with('success', 'Peran administrator diperbarui.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($denied = $this->requireSuper($request)) {
            return $denied;
        }
        if (AdminAccess::isConfigAdmin($user)) {
            return back()->withErrors(['role' => 'Super Admin utama (konfigurasi server) tidak dapat dicabut dari sini.']);
        }
        if ((int) $request->session()->get('research_admin_user_id') === (int) $user->id) {
            return back()->withErrors(['role' => 'Anda tidak dapat mencabut akses Anda sendiri.']);
        }
        if (! $user->admin_role) {
            abort(404);
        }
        if ($user->admin_role === 'super_admin' && ! $this->otherSuperExists($user)) {
            return back()->withErrors(['role' => 'Harus tersisa minimal satu Super Admin.']);
        }

        $from = $user->admin_role;
        $user->forceFill(['admin_role' => null])->save();
        $this->audit($request, 'admin.account_removed', $user, ['from' => $from]);

        return back()->with('success', 'Akses administrator dicabut. Akun pengguna tetap ada.');
    }

    /** Tanpa UI untuk non-Super Admin, jadi permintaan ini hanya datang dari permintaan yang dipalsukan: 403. */
    private function requireSuper(Request $request): ?RedirectResponse
    {
        abort_unless(AdminAccess::isSuper($request), 403, 'Hanya Super Admin yang dapat mengelola akun administrator.');

        return null;
    }

    private function otherSuperExists(User $except): bool
    {
        return AdminAccess::configEmail() !== '' || User::query()->where('admin_role', 'super_admin')->whereKeyNot($except->id)->exists();
    }

    private function audit(Request $request, string $action, User $user, array $meta): void
    {
        AuditLog::create([
            'user_id' => $request->session()->get('research_admin_user_id'), 'action' => $action, 'auditable_type' => User::class,
            'auditable_id' => $user->id, 'metadata_json' => $meta, 'ip_address' => $request->ip(),
        ]);
    }
}
