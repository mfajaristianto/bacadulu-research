<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\Audit\AuditTrail;
use App\Services\Security\SecurityMonitor;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/*
 * Keamanan: observability akses dan kontrol perlindungan. Hanya GET: tidak ada aksi yang mengubah akses, sesi, atau data audit dari halaman ini.
 */
class SecurityController extends Controller
{
    public function __construct(private readonly SecurityMonitor $monitor) {}

    public function index(Request $request): View
    {
        ['f' => $f, 'notice' => $notice] = $this->monitor->filters($request);
        $ready = SecurityMonitor::available();
        $base = [
            'f' => $f, 'notice' => $notice, 'ready' => $ready, 'periods' => AuditTrail::PERIODS, 'sorts' => SecurityMonitor::SORTS, 'results' => SecurityMonitor::RESULTS,
            'events' => SecurityMonitor::EVENTS, 'range' => $this->monitor->range($f), 'filtering' => $this->monitor->filtering($f),
            'controls' => $this->monitor->controls(), 'limitations' => $this->monitor->limitations(), 'loadedAt' => CarbonImmutable::now(SecurityMonitor::TZ),
            'detail' => null,
        ];

        if (! $ready) {
            return view('admin.security.index', $base);
        }

        $kpis = $this->monitor->kpis($f);
        $logs = $this->monitor->paginate($f);
        $detail = null;

        if ($f['log'] && ($log = AuditLog::query()->find($f['log'])) && SecurityMonitor::isSecurityEvent($log)) {
            $detail = $this->monitor->detail($log);
        }

        return view('admin.security.index', $base + [
            'kpis' => $kpis, 'activity' => $this->monitor->activity($f), 'distribution' => $this->monitor->distribution($f),
            'insights' => $this->monitor->insights($f, $kpis), 'logs' => $logs, 'rows' => $this->monitor->present($logs->items()), 'detail' => $detail,
        ]);
    }

    /** Detail satu event keamanan (JSON berisi HTML yang dirender server). Event di luar subset akses dijawab 404 agar halaman ini tidak menjadi jalan masuk ke seluruh audit. */
    public function show(AuditLog $auditLog): JsonResponse
    {
        abort_unless(SecurityMonitor::isSecurityEvent($auditLog), 404);

        return response()->json(['ok' => true, 'id' => $auditLog->id, 'html' => view('admin.security.partials.detail', ['d' => $this->monitor->detail($auditLog)])->render()]);
    }
}
