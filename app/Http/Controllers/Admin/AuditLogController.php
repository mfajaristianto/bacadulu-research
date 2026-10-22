<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:160'],
            'action' => ['nullable', 'string', 'max:180'],
        ]);

        $query = AuditLog::query()->with('user:id,name,email');

        if (filled($filters['search'] ?? null)) {
            $search = trim((string) $filters['search']);
            $query->where(function ($q) use ($search): void {
                $q->where('action', 'like', "%{$search}%")
                    ->orWhere('auditable_type', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($user) => $user->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
            });
        }

        if (filled($filters['action'] ?? null)) {
            $query->where('action', $filters['action']);
        }

        return view('admin.audit.index', [
            'logs' => $query->latest('id')->paginate(30)->withQueryString(),
            'actions' => AuditLog::query()->select('action')->distinct()->orderBy('action')->pluck('action'),
        ]);
    }
}
