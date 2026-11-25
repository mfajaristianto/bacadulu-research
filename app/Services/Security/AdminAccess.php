<?php

namespace App\Services\Security;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Peran administrator panel. Akun dari ADMIN_EMAIL (config) selalu Super Admin implisit dan tidak dapat diubah dari UI.
 * Administrator tambahan = pengguna dengan users.admin_role ('super_admin' | 'admin'); masuk ke panel dengan kata sandi akunnya.
 * Tanpa kolom admin_role (migration belum jalan) hanya admin dari config yang ada.
 */
final class AdminAccess
{
    public const ROLES = ['super_admin' => 'Super Admin', 'admin' => 'Admin'];

    public static function supported(): bool
    {
        return Schema::hasColumn('users', 'admin_role');
    }

    public static function configEmail(): string
    {
        return strtolower(trim((string) config('research.admin_email')));
    }

    public static function isConfigAdmin(?User $user): bool
    {
        return $user !== null && self::configEmail() !== '' && strtolower($user->email) === self::configEmail();
    }

    /** Peran efektif sebuah akun, atau null bila bukan administrator. */
    public static function roleFor(?User $user): ?string
    {
        if ($user === null) {
            return null;
        }
        if (self::isConfigAdmin($user)) {
            return 'super_admin';
        }
        $role = $user->getAttribute('admin_role');

        return in_array($role, array_keys(self::ROLES), true) ? $role : null;
    }

    public static function current(Request $request): ?string
    {
        $role = $request->session()->get('research_admin_role');

        return in_array($role, array_keys(self::ROLES), true) ? $role : null;
    }

    public static function isSuper(Request $request): bool
    {
        return self::current($request) === 'super_admin';
    }
}
