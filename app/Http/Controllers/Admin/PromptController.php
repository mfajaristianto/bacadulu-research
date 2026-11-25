<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiTool;
use App\Models\AuditLog;
use App\Models\PromptTemplate;
use App\Services\Research\PromptVersions;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/*
 * Instruksi & Versi (admin). Satu halaman untuk daftar instruksi, riwayat versi, edit draf, bandingkan, dan "Jadikan Aktif".
 *
 * Penyimpanan tetap prompt_templates: satu baris = satu versi, satu "instruksi" = baris dengan alat dan nama yang sama.
 * Aturan yang dijaga di sini (bukan hanya di UI):
 *   - hanya draf yang belum pernah dipakai run yang bisa diedit; versi aktif/arsip dikunci, perubahan lewat revisi;
 *   - aktivasi hanya memindahkan status dan penunjuk alat, tidak pernah menyentuh isi, dan tidak mengaktifkan alat arsip;
 *   - tidak ada hapus, tidak ada pembuatan versi otomatis, tidak ada aktivasi otomatis.
 */
class PromptController extends Controller
{
    /** Kolom isi per kelompok tampilan: [kolom => [label, bantuan]]. */
    public const FIELD_GROUPS = [
        'Instruksi internal' => [
            'system_prompt' => ['System prompt', 'Peran dan batasan umum AI. Opsional.'],
            'task_prompt' => ['Instruksi tugas', 'Isi utama instruksi yang dikirim ke AI. Wajib.'],
            'retrieval_instructions' => ['Instruksi pengambilan bukti', 'Cara AI memilih dan memakai bukti dari pustaka.'],
            'output_instructions' => ['Instruksi keluaran', 'Format dan struktur jawaban yang diharapkan.'],
        ],
        'Teks untuk peneliti' => [
            'question_template' => ['Template pertanyaan', 'Pertanyaan contoh untuk peneliti. Gunakan {topik}, {objek}, atau {fokus}.'],
            'meaning' => ['Arti', 'Penjelasan singkat apa yang dilakukan alat ini.'],
            'when_to_use' => ['Kapan dipakai', ''],
            'when_not_to_use' => ['Kapan tidak dipakai', ''],
            'example_text' => ['Contoh', ''],
            'expected_output' => ['Hasil yang diharapkan', ''],
        ],
    ];

    private const STATUS_LABELS = ['draft' => 'Draf', 'active' => 'Aktif', 'archived' => 'Diarsipkan'];

    public function __construct(private readonly PromptVersions $versions) {}

    public function index(Request $request): View|RedirectResponse
    {
        $f = $request->validate([
            'search' => ['nullable', 'string', 'max:160'],
            'status' => ['nullable', 'in:active,draft,archived'],
            'tool' => ['nullable', 'integer', 'exists:ai_tools,id'],
            'sort' => ['nullable', Rule::in(['updated', 'name'])],
            'instruksi' => ['nullable', 'integer', 'exists:prompt_templates,id'],
            'v' => ['nullable', 'integer'],
            'a' => ['nullable', 'integer'],
            'b' => ['nullable', 'integer'],
            'baru' => ['nullable', 'boolean'],
        ]);

        $tools = AiTool::query()->orderBy('name')->get();
        $shared = ['tools' => $tools, 'stats' => $this->stats(), 'statusLabels' => self::STATUS_LABELS, 'fieldGroups' => self::FIELD_GROUPS];

        if (filled($f['instruksi'] ?? null)) {
            // Versi bagian Prompt Dokumen dikelola di modulnya sendiri.
            $owner = (string) PromptTemplate::query()->whereKey($f['instruksi'])->value('name');

            if (str_starts_with($owner, \App\Models\DocumentPromptSection::PROMPT_PREFIX)) {
                return redirect()->route('admin.prompt-documents.index');
            }

            // Aturan Perilaku Jawaban AI (answer-policy:*) juga punya modul sendiri.
            if (str_starts_with($owner, \App\Services\Research\AnswerBehavior::PREFIX)) {
                return redirect()->route('admin.answer-behavior.index');
            }

            return view('admin.prompts.index', $shared + ['mode' => 'detail'] + $this->detail(PromptTemplate::query()->findOrFail($f['instruksi']), $f));
        }

        if ($request->boolean('baru')) {
            return view('admin.prompts.index', $shared + ['mode' => 'new', 'presetTool' => $f['tool'] ?? null]);
        }

        return view('admin.prompts.index', $shared + ['mode' => 'list', 'filtering' => filled($f['search'] ?? null) || filled($f['status'] ?? null) || filled($f['tool'] ?? null) || ($f['sort'] ?? null) === 'name'] + $this->groups($f));
    }

    /** Rute lama: tetap hidup dan mengarah ke halaman terpadu. */
    public function create(Request $request): RedirectResponse
    {
        return redirect()->route('admin.prompts.index', array_filter(['baru' => 1, 'tool' => $request->query('tool')]));
    }

    public function edit(PromptTemplate $prompt): RedirectResponse
    {
        return redirect()->route('admin.prompts.index', ['instruksi' => $prompt->id, 'v' => $prompt->id]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $this->content($request) + $request->validate([
            'ai_tool_id' => ['required', 'integer', 'exists:ai_tools,id'],
            'name' => ['required', 'string', 'max:180'],
        ], ['ai_tool_id.required' => 'Pilih alat riset untuk instruksi ini.', 'name.required' => 'Nama instruksi wajib diisi.']);

        $name = trim((string) preg_replace('/\s+/u', ' ', $data['name']));
        $taken = PromptTemplate::query()->where('ai_tool_id', $data['ai_tool_id'])->whereRaw('lower(name) = ?', [mb_strtolower($name)])->orderByRaw("case when status = 'active' then 0 else 1 end")->orderByDesc('version')->first();

        if ($taken) {
            $this->raise($request, ['name' => 'Alat ini sudah punya instruksi bernama "'.$taken->name.'". Buat versi baru di sana.'], $this->url($taken));
        }

        $prompt = PromptTemplate::create(['name' => $name, 'version' => 1, 'status' => 'draft', 'created_by' => $this->userId($request)] + $data);
        $this->audit($request, 'prompt.created', $prompt);

        return $this->done($request, 'Instruksi "'.$prompt->name.'" dibuat sebagai draf v1. Jadikan aktif bila sudah siap.', $this->url($prompt));
    }

    /** Edit draf. Versi aktif/arsip/yang pernah dipakai run ditolak di sini, apa pun yang dikirim form. */
    public function update(Request $request, PromptTemplate $prompt): RedirectResponse|JsonResponse
    {
        $data = $this->content($request);

        $changed = DB::transaction(function () use ($request, $prompt, $data): array {
            $row = $this->versions->fresh($prompt);

            if ($reason = $this->versions->lockReason($row)) {
                $this->raise($request, ['prompt' => $reason], null, 409);
            }

            $row->fill($data);
            $dirty = array_keys($row->getDirty());
            $row->save();

            return $dirty;
        });

        if ($changed !== []) {
            $this->audit($request, 'prompt.updated', $prompt->refresh(), ['changed' => $changed]);
        }

        return $this->done($request, $changed === [] ? 'Tidak ada perubahan untuk disimpan.' : 'Draf v'.$prompt->version.' disimpan. Belum aktif.', $this->url($prompt, $prompt));
    }

    /** Draf baru dari versi mana pun; versi sumber tidak berubah. */
    public function revise(Request $request, PromptTemplate $prompt): RedirectResponse|JsonResponse
    {
        $copy = $this->versions->revise($prompt, $this->userId($request));

        $this->audit($request, 'prompt.duplicated', $copy, ['source_prompt_id' => $prompt->id, 'source_version' => $prompt->version]);

        return $this->done($request, 'Draf v'.$copy->version.' dibuat dari v'.$prompt->version.'. Versi aktif tidak berubah.', $this->url($copy, $copy));
    }

    public function activate(Request $request, PromptTemplate $prompt): RedirectResponse|JsonResponse
    {
        if (! $prompt->ai_tool_id) {
            $this->raise($request, ['prompt' => 'Instruksi harus terhubung ke alat riset sebelum diaktifkan.']);
        }

        $result = $this->versions->activate($prompt);

        if ($result['changed']) {
            $this->audit($request, 'prompt.activated', $prompt->refresh(), $result['previous'] ? [
                'previous_prompt_id' => $result['previous']->id,
                'previous_name' => $result['previous']->name,
                'previous_version' => $result['previous']->version,
            ] : []);
        }

        $tool = $prompt->aiTool()->first();
        $note = $tool?->status === 'archived' ? ' Alat "'.$tool->name.'" masih diarsipkan, jadi belum tersedia untuk pengguna.' : '';

        return $this->done(
            $request,
            ($result['changed'] ? 'v'.$prompt->version.' sekarang aktif.' : 'v'.$prompt->version.' memang sudah aktif.').$note,
            $this->url($prompt, $prompt),
        );
    }

    /** Hanya draf yang bisa diarsipkan; versi aktif diganti lewat aktivasi versi lain. */
    public function archive(Request $request, PromptTemplate $prompt): RedirectResponse|JsonResponse
    {
        DB::transaction(function () use ($request, $prompt): void {
            $row = PromptTemplate::query()->lockForUpdate()->findOrFail($prompt->id);

            if ($row->status !== 'draft') {
                $this->raise($request, ['prompt' => $row->status === 'active' ? 'Versi aktif tidak bisa diarsipkan. Aktifkan versi lain terlebih dahulu.' : 'Hanya draf yang bisa diarsipkan.'], null, 409);
            }

            $row->update(['status' => 'archived']);
        });

        $this->audit($request, 'prompt.archived', $prompt->refresh());

        return $this->done($request, 'Draf v'.$prompt->version.' diarsipkan. Isinya tetap tersimpan.', $this->url($prompt, $prompt));
    }

    /*
    |--------------------------------------------------------------------------
    | Data halaman
    |--------------------------------------------------------------------------
    */

    private function stats(): array
    {
        $groups = PromptTemplate::query()->toolInstructions()
            ->selectRaw("ai_tool_id, name, max(case when status = 'active' then 1 else 0 end) as has_active, sum(case when status = 'draft' then 1 else 0 end) as drafts")
            ->groupBy('ai_tool_id', 'name')->get();

        // "Perlu ditinjau" = alat aktif yang belum punya instruksi berstatus active (kriteria yang sama dengan Katalog Alat AI).
        $missing = AiTool::query()->where('status', 'active')->whereDoesntHave('activePromptTemplate', fn ($q) => $q->where('status', 'active'))->count();

        return [
            'total' => $groups->count(),
            'active' => $groups->where('has_active', 1)->count(),
            'draft' => $groups->where('drafts', '>', 0)->count(),
            'review' => $missing,
        ];
    }

    private function groups(array $f): array
    {
        $query = PromptTemplate::query()->toolInstructions()
            ->selectRaw("ai_tool_id, name, count(*) as versions_count, max(updated_at) as last_update, min(id) as first_id")
            ->groupBy('ai_tool_id', 'name');

        if (filled($f['search'] ?? null)) {
            $like = '%'.addcslashes(trim((string) $f['search']), '%_\\').'%';
            $query->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('indonesian_name', 'like', $like)->orWhere('short_description', 'like', $like)
                ->orWhereHas('aiTool', fn ($t) => $t->where('name', 'like', $like)->orWhere('code', 'like', $like)));
        }

        if (filled($f['tool'] ?? null)) {
            $query->where('ai_tool_id', $f['tool']);
        }

        match ($f['status'] ?? null) {
            'active' => $query->havingRaw("max(case when status = 'active' then 1 else 0 end) = 1"),
            'draft' => $query->havingRaw("sum(case when status = 'draft' then 1 else 0 end) > 0"),
            'archived' => $query->havingRaw("sum(case when status in ('active','draft') then 1 else 0 end) = 0"),
            default => null,
        };

        ($f['sort'] ?? null) === 'name' ? $query->orderBy('name') : $query->orderByRaw('max(updated_at) desc');

        $page = $query->orderBy('first_id')->paginate(15)->withQueryString();

        $rows = PromptTemplate::query()->with('aiTool')->withCount('aiRuns')
            ->where(function ($q) use ($page): void {
                foreach ($page as $g) {
                    $q->orWhere(fn ($w) => ($g->ai_tool_id ? $w->where('ai_tool_id', $g->ai_tool_id) : $w->whereNull('ai_tool_id'))->where('name', $g->name));
                }
            })->when($page->isEmpty(), fn ($q) => $q->whereRaw('1 = 0'))->orderByDesc('version')->get()
            ->groupBy(fn ($r) => ($r->ai_tool_id ?? 0).'|'.mb_strtolower($r->name));

        $page->setCollection($page->getCollection()->map(function ($g) use ($rows) {
            $versions = $rows->get(($g->ai_tool_id ?? 0).'|'.mb_strtolower($g->name), collect());
            $active = $versions->firstWhere('status', 'active');
            $tool = $versions->first()?->aiTool;

            return (object) [
                'name' => $g->name,
                // Dari daftar, buka versi aktif (atau yang terbaru bila belum ada yang aktif).
                'url' => route('admin.prompts.index', ['instruksi' => $active?->id ?? $versions->first()?->id ?? $g->first_id]),
                'tool' => $tool,
                'versions' => $versions,
                'active' => $active,
                'drafts' => $versions->where('status', 'draft')->count(),
                'latest' => $versions->first(),
                'updated' => $g->last_update ? \Illuminate\Support\Carbon::parse($g->last_update) : null,
                // "Siap" = ada versi aktif DAN alatnya aktif; instruksi tanpa alat tidak pernah siap.
                'ready' => $active && $tool?->status === 'active' && (int) $tool->active_prompt_template_id === $active->id,
            ];
        }));

        return ['groups' => $page];
    }

    private function detail(PromptTemplate $anchor, array $f): array
    {
        $versions = $this->versions->group($anchor->ai_tool_id, $anchor->name)->with(['creator', 'aiTool'])->withCount('aiRuns')->orderByDesc('version')->get();
        $active = $versions->firstWhere('status', 'active');
        $selected = $versions->firstWhere('id', $f['v'] ?? null) ?? $versions->firstWhere('id', $anchor->id) ?? $active ?? $versions->first();
        $tool = $anchor->aiTool;
        $compare = null;

        if (filled($f['a'] ?? null) && filled($f['b'] ?? null)) {
            $old = $versions->firstWhere('id', $f['a']);
            $new = $versions->firstWhere('id', $f['b']);

            if ($old && $new && $old->id !== $new->id) {
                $labels = [];

                foreach (self::FIELD_GROUPS as $fields) {
                    foreach ($fields as $key => [$label]) {
                        $labels[$key] = $label;
                    }
                }

                $compare = ['old' => $old, 'new' => $new, 'fields' => $this->versions->compare($old, $new, $labels)];
            }
        }

        return [
            'name' => $anchor->name,
            'tool' => $tool,
            'versions' => $versions,
            'active' => $active,
            'selected' => $selected,
            'lock' => $this->versions->lockReason($selected),
            'compare' => $compare,
            'history' => Schema::hasTable('audit_logs')
                ? AuditLog::query()->with('user:id,name')->where('auditable_type', PromptTemplate::class)->whereIn('auditable_id', $versions->modelKeys())->latest('id')->limit(12)->get()
                : collect(),
            'versionById' => $versions->keyBy('id'),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Helper
    |--------------------------------------------------------------------------
    */

    /** Hanya kolom isi (bukan nama, alat, status, atau nomor versi) yang boleh datang dari form edit. */
    private function content(Request $request): array
    {
        $texts = [];

        foreach (PromptVersions::CONTENT as $key) {
            if ($request->has($key) && is_string($request->input($key))) {
                $texts[$key] = str_replace(["\r\n", "\r"], "\n", $request->input($key));
            }
        }

        $request->merge($texts);

        // Jenis input Alat Riset ('standard' | 'book'); kolom baru milik migration DRAFT, jadi diabaikan sampai ada.
        $rules = \Illuminate\Support\Facades\Schema::hasColumn('prompt_templates', 'input_mode') ? ['input_mode' => ['nullable', 'in:standard,book']] : [];

        return $request->validate($rules + [
            'indonesian_name' => ['nullable', 'string', 'max:180'],
            'short_description' => ['nullable', 'string', 'max:1000'],
            'question_template' => ['nullable', 'string', 'max:5000'],
            'meaning' => ['nullable', 'string', 'max:5000'],
            'when_to_use' => ['nullable', 'string', 'max:5000'],
            'when_not_to_use' => ['nullable', 'string', 'max:5000'],
            'example_text' => ['nullable', 'string', 'max:5000'],
            'expected_output' => ['nullable', 'string', 'max:5000'],
            'system_prompt' => ['nullable', 'string', 'max:20000'],
            'task_prompt' => ['required', 'string', 'max:30000'],
            'retrieval_instructions' => ['nullable', 'string', 'max:20000'],
            'output_instructions' => ['nullable', 'string', 'max:20000'],
        ], [
            'task_prompt.required' => 'Instruksi tugas wajib diisi.',
            'task_prompt.max' => 'Instruksi tugas maksimal 30.000 karakter.',
        ]);
    }

    private function url(PromptTemplate $prompt, ?PromptTemplate $select = null): string
    {
        return route('admin.prompts.index', array_filter(['instruksi' => $prompt->id, 'v' => $select?->id]));
    }

    private function userId(Request $request): ?int
    {
        return $request->session()->get('research_admin_user_id');
    }

    private function raise(Request $request, array $errors, ?string $existingUrl = null, int $status = 422): never
    {
        if ($request->expectsJson()) {
            throw new HttpResponseException(response()->json([
                'message' => reset($errors),
                'errors' => array_map(fn ($message) => [$message], $errors),
                'existing' => $existingUrl ? ['url' => $existingUrl] : null,
            ], $status));
        }

        throw ValidationException::withMessages($errors);
    }

    private function done(Request $request, string $message, string $url): RedirectResponse|JsonResponse
    {
        return $request->expectsJson()
            ? response()->json(['ok' => true, 'message' => $message, 'url' => $url])
            : redirect($url)->with('success', $message);
    }

    private function audit(Request $request, string $action, PromptTemplate $prompt, array $extra = []): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        AuditLog::create([
            'user_id' => $this->userId($request),
            'action' => $action,
            'auditable_type' => PromptTemplate::class,
            'auditable_id' => $prompt->id,
            'metadata_json' => ['ai_tool_id' => $prompt->ai_tool_id, 'name' => $prompt->name, 'version' => $prompt->version, 'status' => $prompt->status] + $extra,
            'ip_address' => $request->ip(),
        ]);
    }
}
