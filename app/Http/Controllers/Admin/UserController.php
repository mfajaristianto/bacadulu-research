<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Security\AdminAccess;
use Illuminate\Support\Facades\Storage;
use App\Services\Audit\AuditTrail;
use App\Services\Users\UserAnalytics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/*
 * Pengguna: dasbor analitik + direktori + panel detail. Aksi akun: status (aktif/ditangguhkan, aksi utama yang aman), Reset Tutorial,
 * hapus riset, dan hapus akun permanen. Dua aksi terakhir WAJIB melalui halaman konfirmasi yang menampilkan jumlah data terkait,
 * dan tidak berlaku untuk akun administrator.
 */
class UserController extends Controller
{
    public function __construct(private readonly UserAnalytics $analytics) {}

    public function index(Request $request): View
    {
        ['f' => $f, 'notice' => $notice] = $this->analytics->filters($request);

        $kpis = $this->analytics->kpis($f);
        $research = $this->analytics->research($f, $kpis);
        $users = $this->analytics->directory($f);
        $detail = $f['pengguna'] && ($target = User::query()->find($f['pengguna'])) ? $this->analytics->detail($target) : null;

        return view('admin.users.index', [
            'f' => $f, 'notice' => $notice, 'periods' => AuditTrail::PERIODS, 'sorts' => UserAnalytics::SORTS,
            'range' => $this->analytics->range($f), 'kpis' => $kpis, 'growth' => $this->analytics->growth($f),
            'composition' => $this->analytics->composition(), 'research' => $research, 'insights' => $this->analytics->insights($kpis, $research),
            'users' => $users, 'filtering' => $this->analytics->filtering($f), 'detail' => $detail,
            'adminEmail' => UserAnalytics::adminEmail(),
        ]);
    }

    /** Isi panel detail (JSON berisi HTML yang dirender server). Tanpa JavaScript, tautan lama tetap bekerja: dialihkan ke daftar dengan panel terbuka. */
    public function show(Request $request, User $user): JsonResponse|RedirectResponse
    {
        if (! $request->expectsJson()) {
            return redirect()->route('admin.users.index', ['pengguna' => $user->id]);
        }

        return response()->json(['ok' => true, 'id' => $user->id, 'html' => $this->detailHtml($user)]);
    }

    public function status(Request $request, User $user): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:active,suspended']]);
        $to = $data['status'];
        $sessionId = $request->session()->get('research_admin_user_id');
        $isAdminAccount = UserAnalytics::isAdminAccount($user) || ($sessionId && (int) $sessionId === (int) $user->id);

        if ($isAdminAccount && $to !== 'active') {
            return $this->respond($request, $user, false, 'Akun administrator panel tidak dapat ditangguhkan: ia satu-satunya akun yang bisa masuk ke panel ini.', 422);
        }

        $from = $user->status;

        if ($from === $to) {
            return $this->respond($request, $user, true, $to === 'active' ? 'Akun sudah aktif.' : 'Akun sudah ditangguhkan.');
        }

        DB::transaction(function () use ($request, $user, $from, $to): void {
            $user->update(['status' => $to]);
            $this->audit($request, $to === 'active' ? 'user.reactivated' : 'user.suspended', $user, ['from' => $from, 'to' => $to]);
        });

        return $this->respond($request, $user->refresh(), true, $to === 'active' ? 'Akun diaktifkan kembali.' : 'Akun ditangguhkan. Data historis tetap tersimpan.');
    }

    public function resetTutorial(Request $request, User $user): RedirectResponse
    {
        // Hanya status tutorial; data riset dan dokumen pengguna tidak disentuh. Login berikutnya menampilkan tutorial lagi.
        $user->forceFill(['onboarding_completed_at' => null])->save();
        $this->audit($request, 'user.tutorial_reset', $user, []);

        return redirect()->route('admin.users.index', ['pengguna' => $user->id])->with('success', 'Tutorial pengguna direset. Tutorial tampil lagi saat login berikutnya.');
    }

    public function confirmResearchDeletion(Request $request, User $user): View
    {
        return view('admin.users.delete', ['target' => $user, 'counts' => $this->counts($user), 'mode' => 'research', 'locked' => $this->isProtected($request, $user)]);
    }

    public function destroyResearch(Request $request, User $user): RedirectResponse
    {
        $request->validate(['confirm_email' => ['required', 'string']]);
        if (strtolower(trim((string) $request->input('confirm_email'))) !== strtolower($user->email)) {
            return back()->withErrors(['confirm_email' => 'Email konfirmasi tidak sama dengan email pengguna.']);
        }

        $counts = $this->counts($user);
        // Dokumen (document_generations) TIDAK dihapus: ai_run_id-nya menjadi null. Percakapan menghapus pesannya lewat cascade.
        DB::transaction(function () use ($user): void {
            $user->savedResults()->delete();
            $user->conversations()->delete();
            $user->aiRuns()->delete();
        });
        $this->audit($request, 'user.research_deleted', $user, ['runs' => $counts['runs'], 'conversations' => $counts['conversations'], 'saved' => $counts['saved']]);

        return redirect()->route('admin.users.index', ['pengguna' => $user->id])->with('success', 'Riset pengguna dihapus. Dokumen dan akun tidak diubah.');
    }

    public function confirmDeletion(Request $request, User $user): View
    {
        return view('admin.users.delete', ['target' => $user, 'counts' => $this->counts($user), 'mode' => 'account', 'locked' => $this->isProtected($request, $user)]);
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $request->validate(['confirm_email' => ['required', 'string']]);
        if ($this->isProtected($request, $user)) {
            return back()->withErrors(['confirm_email' => 'Akun administrator tidak dapat dihapus dari sini.']);
        }
        if (strtolower(trim((string) $request->input('confirm_email'))) !== strtolower($user->email)) {
            return back()->withErrors(['confirm_email' => 'Email konfirmasi tidak sama dengan email pengguna.']);
        }

        $counts = $this->counts($user);
        $meta = ['user_id' => $user->id, 'email_hash' => hash('sha256', strtolower($user->email))] + $counts; // email asli tidak disimpan di audit
        $disk = (string) config('research.documents.disk', 'research_outputs');
        $id = $user->id;

        DB::transaction(function () use ($user): void {
            $user->delete(); // FK cascade: riset, dokumen, tersimpan, riwayat login
        });
        Storage::disk($disk)->deleteDirectory('documents/'.$id); // berkas dokumen yatim
        $this->audit($request, 'user.deleted', $user, $meta);

        return redirect()->route('admin.users.index')->with('success', 'Akun pengguna dihapus permanen beserta data terkait.');
    }

    /** @return array{runs: int, conversations: int, saved: int, documents: int, logins: int} */
    private function counts(User $user): array
    {
        return [
            'runs' => $user->aiRuns()->count(), 'conversations' => $user->conversations()->count(), 'saved' => $user->savedResults()->count(),
            'documents' => $user->documentGenerations()->count(),
            'logins' => Schema::hasTable('user_login_events') ? \App\Models\UserLoginEvent::query()->where('user_id', $user->id)->count() : 0,
        ];
    }

    /** Akun administrator (config, admin_role) dan akun yang sedang dipakai masuk ke panel tidak boleh dihapus dari halaman Pengguna. */
    private function isProtected(Request $request, User $user): bool
    {
        return UserAnalytics::isAdminAccount($user) || AdminAccess::roleFor($user) !== null
            || (int) $request->session()->get('research_admin_user_id') === (int) $user->id;
    }

    private function respond(Request $request, User $user, bool $ok, string $message, int $code = 200): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['ok' => $ok, 'message' => $message, 'html' => $this->detailHtml($user)], $code);
        }

        $back = redirect()->route('admin.users.index', ['pengguna' => $user->id]);

        return $ok ? $back->with('success', $message) : $back->withErrors(['status' => $message]);
    }

    private function detailHtml(User $user): string
    {
        return view('admin.users.partials.detail', ['d' => $this->analytics->detail($user), 'adminEmail' => UserAnalytics::adminEmail()])->render();
    }

    private function audit(Request $request, string $action, User $user, array $metadata): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        AuditLog::create([
            'user_id' => $request->session()->get('research_admin_user_id'),
            'action' => $action,
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'metadata_json' => $metadata,
            'ip_address' => $request->ip(),
        ]);
    }
}
