<?php

namespace App\Services\Users;

use App\Models\User;
use App\Models\UserLoginEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Riwayat login untuk keamanan: IP (hasil trusted proxy Laravel, bukan header mentah), User-Agent terpotong, jenis
 * perangkat/OS/browser. Gagal menulis tidak boleh menggagalkan login. Retensi dipangkas oleh `prune()`.
 */
final class LoginRecorder
{
    public const SESSION_KEY = 'login_event_id';

    /** Pembaruan "aktivitas terakhir" paling sering sekali per interval ini, agar tidak menulis di setiap request. */
    private const TOUCH_SECONDS = 300;

    public static function available(): bool
    {
        static $has = null;

        return $has ??= Schema::hasTable('user_login_events');
    }

    public function record(Request $request, User $user): ?UserLoginEvent
    {
        if (! self::available()) {
            return null;
        }

        try {
            $ua = mb_substr((string) $request->userAgent(), 0, 255);
            $event = UserLoginEvent::query()->create([
                'user_id' => $user->id,
                'login_at' => now(),
                'last_activity_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => $ua !== '' ? $ua : null,
                ...DeviceParser::parse($ua),
            ]);
            $request->hasSession() && $request->session()->put(self::SESSION_KEY, $event->id);

            return $event;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    public function touch(Request $request): void
    {
        if (! self::available() || ! $request->hasSession() || ! ($id = $request->session()->get(self::SESSION_KEY))) {
            return;
        }

        try {
            UserLoginEvent::query()->whereKey($id)->where('user_id', $request->user()?->id)
                ->where(fn ($q) => $q->whereNull('last_activity_at')->orWhere('last_activity_at', '<', now()->subSeconds(self::TOUCH_SECONDS)))
                ->update(['last_activity_at' => now()]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function prune(): int
    {
        return self::available()
            ? UserLoginEvent::query()->where('login_at', '<', now()->subDays(max(30, (int) config('research.security.login_retention_days', 180))))->delete()
            : 0;
    }
}
