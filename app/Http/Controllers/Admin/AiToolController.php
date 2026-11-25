<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiTool;
use App\Models\AuditLog;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/*
 * Alat Riset (admin). Sebuah alat HANYA mendefinisikan jenis pekerjaan riset: kode (slug), nama, deskripsi, ikon, status.
 * Instruksi AI (prompt_templates) memegang prompt-nya, Versi Instruksi memegang riwayatnya, dan Template Dokumen mengatur
 * format keluaran; keempatnya terpisah. Alat tidak menyimpan prompt dan halaman ini tidak mengubah instruksi aktif.
 *
 * Pengguna hanya melihat instruksi yang statusnya active DAN alatnya active (ResearchController), jadi arsip sebuah alat
 * menyembunyikan semua instruksinya dari pengguna; baris instruksi dan versinya tetap utuh. Tidak ada hapus.
 *
 * `code` dipakai logika riset (langkah berikutnya, penyedia mock, presentasi hasil), jadi mengubahnya pada alat yang sudah
 * ada butuh konfirmasi eksplisit (confirm_code_change) selain validasi biasa.
 */
class AiToolController extends Controller
{
    private const SORTS = ['status', 'name', 'updated'];

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:160'],
            'status' => ['nullable', 'in:active,archived'],
            'instruction' => ['nullable', 'in:ready,missing'],
            'sort' => ['nullable', Rule::in(self::SORTS)],
        ]);

        $tools = AiTool::query()->with('activePromptTemplate')->withCount('promptTemplates');

        if (filled($filters['search'] ?? null)) {
            $like = '%'.addcslashes(trim((string) $filters['search']), '%_\\').'%';
            $tools->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('code', 'like', $like)->orWhere('description', 'like', $like));
        }

        if (filled($filters['status'] ?? null)) {
            $tools->where('status', $filters['status']);
        }

        // "Siap" = alat punya instruksi aktif yang benar-benar berstatus active; selain itu dianggap belum punya.
        $hasActivePrompt = fn ($q) => $q->where('status', 'active');

        if (($filters['instruction'] ?? null) === 'ready') {
            $tools->whereHas('activePromptTemplate', $hasActivePrompt);
        } elseif (($filters['instruction'] ?? null) === 'missing') {
            $tools->whereDoesntHave('activePromptTemplate', $hasActivePrompt);
        }

        match ($filters['sort'] ?? 'status') {
            'name' => $tools->orderBy('name')->orderBy('id'),
            'updated' => $tools->orderByDesc('updated_at')->orderByDesc('id'),
            default => $tools->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")->orderBy('name')->orderBy('id'),
        };

        $ready = fn () => AiTool::query()->where('status', 'active')->whereHas('activePromptTemplate', $hasActivePrompt);

        return view('admin.tools.index', [
            'tools' => $tools->paginate(20)->withQueryString(),
            'filtering' => filled($filters['search'] ?? null) || filled($filters['status'] ?? null) || filled($filters['instruction'] ?? null) || in_array($filters['sort'] ?? null, ['name', 'updated'], true),
            'stats' => [
                'total' => AiTool::query()->count(),
                'active' => AiTool::query()->where('status', 'active')->count(),
                'ready' => $ready()->count(),
                'missing' => AiTool::query()->where('status', 'active')->count() - $ready()->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.tools.form', ['tool' => new AiTool(['status' => 'active'])]);
    }

    public function edit(AiTool $tool): View
    {
        return view('admin.tools.form', ['tool' => $tool->load('activePromptTemplate')]);
    }

    /** Pemeriksaan kode (slug) selagi mengetik. Tidak mengubah apa pun. */
    public function check(Request $request): JsonResponse
    {
        $input = $request->validate(['code' => ['nullable', 'string', 'max:120'], 'ignore' => ['nullable', 'integer']]);
        $taken = filled($input['code'] ?? null)
            ? AiTool::query()->where('code', $this->slug((string) $input['code']))->when($input['ignore'] ?? null, fn ($q, $id) => $q->whereKeyNot($id))->first()
            : null;

        return response()->json(['code' => $taken ? $this->pointer($taken) : null]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $this->validated($request);
        $this->refuseTakenCode($request, $data['code'], null);

        $tool = AiTool::create($data);

        $this->audit($request, 'tool.created', $tool, ['code' => $tool->code, 'status' => $tool->status]);

        return $this->done(
            $request,
            'Alat "'.$tool->name.'" dibuat. Hubungkan instruksi AI agar alat ini bisa dipakai.',
            $tool,
            redirect()->route('admin.tools.edit', $tool),
        );
    }

    public function update(Request $request, AiTool $tool): RedirectResponse|JsonResponse
    {
        $data = $this->validated($request, $tool);
        $this->refuseTakenCode($request, $data['code'], $tool);

        // Slug dikunci pada alat yang sudah ada kecuali admin sengaja menggantinya dan mengonfirmasi dampaknya.
        if ($data['code'] !== $tool->code && ! $request->boolean('confirm_code_change')) {
            $this->raise($request, ['code' => 'Slug "'.$tool->code.'" dipakai logika riset. Konfirmasi dulu bila memang ingin mengubahnya.']);
        }

        // Hanya field yang dikirim yang ditimpa: instruksi aktif, skema input/output, dan relasi lain tidak tersentuh.
        $tool->fill($data);
        $changed = array_keys($tool->getDirty());
        $tool->save();

        $this->audit($request, 'tool.updated', $tool, ['changed' => $changed, 'code' => $tool->code, 'status' => $tool->status]);

        return $this->done($request, 'Alat "'.$tool->name.'" diperbarui.', $tool, redirect()->route('admin.tools.edit', $tool));
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function validated(Request $request, ?AiTool $tool = null): array
    {
        $request->merge(['name' => trim((string) preg_replace('/\s+/u', ' ', (string) $request->input('name')))]);

        return $request->validate([
            'code' => [
                'required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', // keunikan diperiksa refuseTakenCode() agar jawabannya menunjuk alat yang memakai slug itu
            ],
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'],
            'icon' => ['nullable', 'string', 'max:40'],
            'status' => ['required', 'in:active,archived'],
        ], [
            'code.required' => 'Slug wajib diisi.',
            'code.regex' => 'Slug hanya boleh huruf kecil, angka, dan tanda hubung (mis. research-gap).',
            'name.required' => 'Nama alat wajib diisi.',
            'name.max' => 'Nama alat maksimal 180 karakter.',
            'description.max' => 'Deskripsi maksimal 2.000 karakter.',
        ]);
    }

    private function slug(string $value): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($value)), '-');
    }

    private function refuseTakenCode(Request $request, string $code, ?AiTool $current): void
    {
        $taken = AiTool::query()->where('code', $code)->when($current, fn ($q) => $q->whereKeyNot($current->id))->first();

        if ($taken) {
            $this->raise($request, ['code' => 'Slug "'.$code.'" sudah dipakai oleh alat "'.$taken->name.'".'], $taken);
        }
    }

    private function raise(Request $request, array $errors, ?AiTool $existing = null): void
    {
        if ($request->expectsJson()) {
            throw new HttpResponseException(response()->json([
                'message' => reset($errors),
                'errors' => array_map(fn ($message) => [$message], $errors),
                'existing' => $existing ? $this->pointer($existing) : null,
            ], 422));
        }

        throw ValidationException::withMessages($errors);
    }

    private function pointer(AiTool $tool): array
    {
        return ['id' => $tool->id, 'name' => $tool->name, 'code' => $tool->code, 'url' => route('admin.tools.edit', $tool)];
    }

    private function done(Request $request, string $message, AiTool $tool, RedirectResponse $redirect): RedirectResponse|JsonResponse
    {
        return $request->expectsJson()
            ? response()->json(['ok' => true, 'message' => $message, 'id' => $tool->id, 'manage_url' => route('admin.prompt-versions.index', ['tool' => $tool->id])])
            : $redirect->with('success', $message);
    }

    private function audit(Request $request, string $action, AiTool $tool, array $metadata): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        AuditLog::create([
            'user_id' => $request->session()->get('research_admin_user_id'),
            'action' => $action,
            'auditable_type' => AiTool::class,
            'auditable_id' => $tool->id,
            'metadata_json' => $metadata,
            'ip_address' => $request->ip(),
        ]);
    }
}
