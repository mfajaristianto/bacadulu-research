<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\Audit\AuditEvents;
use App\Services\Audit\AuditTrail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/*
 * Log Aktivitas (audit trail). Halaman ini hanya membaca: tidak ada rute atau aksi untuk mengubah, menghapus, atau mengatur ulang log.
 */
class AuditLogController extends Controller
{
    public function __construct(private readonly AuditTrail $trail) {}

    public function index(Request $request): View
    {
        ['f' => $f, 'notice' => $notice] = $this->trail->filters($request);
        $ready = Schema::hasTable('audit_logs');
        $detail = null;

        if (! $ready) {
            return view('admin.audit.index', ['ready' => false, 'f' => $f, 'notice' => $notice, 'detail' => null, 'periods' => AuditTrail::PERIODS]);
        }

        $logs = $this->trail->paginate($f);
        $counts = $this->trail->categoryCounts($f);

        if ($f['log'] && ($log = AuditLog::query()->find($f['log']))) {
            $detail = $this->trail->detail($log);
        }

        return view('admin.audit.index', [
            'ready' => true, 'f' => $f, 'notice' => $notice,
            'periods' => AuditTrail::PERIODS, 'sorts' => AuditTrail::SORTS,
            'overview' => $this->trail->overview($f),
            'chips' => $this->chips($f, $counts),
            'options' => $this->trail->options($f),
            'logs' => $logs, 'rows' => $this->trail->present($logs->items()),
            'detail' => $detail,
            'filtering' => $this->filtering($f),
            'range' => $this->trail->range($f),
            'categories' => AuditEvents::CATEGORIES,
        ]);
    }

    /** Isi panel detail untuk satu event (dimuat saat dibuka; payload tidak ikut dikirim bersama daftar). */
    public function show(AuditLog $auditLog): JsonResponse
    {
        $d = $this->trail->detail($auditLog);

        return response()->json(['ok' => true, 'id' => $auditLog->id, 'html' => view('admin.audit.partials.detail', ['d' => $d])->render()]);
    }

    /** Filter cepat hanya untuk kategori yang punya data pada periode terpilih. */
    private function chips(array $f, array $counts): array
    {
        $chips = [['key' => 'semua', 'label' => 'Semua', 'count' => null, 'query' => ['kategori' => null, 'tipe' => null], 'active' => ! $f['kategori'] && $f['tipe'] !== 'sistem']];

        foreach (AuditEvents::CATEGORIES as $key => $label) {
            if (($counts[$key] ?? 0) > 0) {
                $chips[] = ['key' => $key, 'label' => $label, 'count' => $counts[$key], 'query' => ['kategori' => $key, 'tipe' => null], 'active' => $f['kategori'] === $key];
            }
        }

        if (($counts['sistem'] ?? 0) > 0) {
            $chips[] = ['key' => 'sistem', 'label' => 'Proses sistem', 'count' => $counts['sistem'], 'query' => ['kategori' => null, 'tipe' => 'sistem'], 'active' => $f['tipe'] === 'sistem' && ! $f['kategori']];
        }

        if (($counts['gagal'] ?? 0) > 0) {
            $chips[] = ['key' => 'gagal', 'label' => 'Gagal', 'count' => $counts['gagal'], 'query' => ['kategori' => 'gagal', 'tipe' => null], 'active' => $f['kategori'] === 'gagal'];
        }

        return $chips;
    }

    private function filtering(array $f): bool
    {
        return $f['q'] !== '' || $f['aktor'] || $f['tipe'] || $f['aksi'] || $f['entitas'] || $f['kategori'];
    }
}
