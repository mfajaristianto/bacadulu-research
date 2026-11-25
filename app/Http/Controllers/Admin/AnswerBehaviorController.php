<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PromptTemplate;
use App\Services\Research\AnswerBehavior;
use App\Services\Research\AnswerBehaviorPresets;
use App\Services\Research\AnswerBehaviorResolver;
use App\Services\Research\AnswerBehaviorRules;
use App\Services\Research\PromptVersions;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/*
 * Perilaku Jawaban AI (admin). Aturan dijaga di AnswerBehaviorRules dan AnswerBehavior, bukan hanya di UI: hanya draf yang boleh
 * diedit, aktivasi atomik per aturan, satu aturan aktif per slot (kelompok + cakupan + alat), tidak ada hapus, tidak ada aktivasi
 * otomatis, preset hanya dipasang sebagai draf. Pratinjau hanya membaca dan tidak memanggil model.
 */
class AnswerBehaviorController extends Controller
{
    private const PER_PAGE = 12;

    private const STATUS_FILTERS = ['active' => 'Aktif', 'draft' => 'Draf', 'archived' => 'Diarsipkan', 'review' => 'Perlu ditinjau'];

    private const TABS = ['' => 'Semua Aturan', 'global' => 'Aturan Umum', 'research' => 'Riset Akademik', 'document' => 'Penulisan Dokumen', 'tool' => 'Khusus Alat'];

    private const PREVIEW_CONDITIONS = ['ok' => 'Sumber cukup', 'no_documents' => 'Tidak ada dokumen', 'irrelevant' => 'Dokumen tidak relevan', 'insufficient' => 'Bukti tidak cukup', 'conflict' => 'Sumber bertentangan', 'error' => 'Error teknis'];

    public function __construct(
        private readonly AnswerBehavior $def,
        private readonly AnswerBehaviorRules $rules,
        private readonly AnswerBehaviorResolver $resolver,
        private readonly PromptVersions $versions
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Daftar dan buat
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $f = $request->validate([
            'search' => ['nullable', 'string', 'max:160'],
            'cakupan' => ['nullable', Rule::in(array_keys(AnswerBehavior::SCOPES))],
            'kelompok' => ['nullable', Rule::in(array_keys(AnswerBehavior::GROUPS))],
            'status' => ['nullable', Rule::in(array_keys(self::STATUS_FILTERS))],
            'alat' => ['nullable', 'string', 'max:80'],
            'sort' => ['nullable', Rule::in(['updated', 'name'])],
            'baru' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $shared = ['groups' => AnswerBehavior::GROUPS, 'scopes' => AnswerBehavior::SCOPES, 'tools' => $this->rules->tools(), 'statusFilters' => self::STATUS_FILTERS, 'tabs' => self::TABS];

        if ($request->boolean('baru')) {
            $existing = $this->rules->all();

            return view('admin.answer-behavior.index', $shared + ['mode' => 'new', 'presets' => AnswerBehaviorPresets::all(), 'installed' => $existing->pluck('title')->map(fn ($t) => mb_strtolower($t))->all()]);
        }

        $all = $this->rules->all();
        $stats = $this->rules->stats($all);

        $tabCounts = ['' => $all->count()] + collect(array_keys(AnswerBehavior::SCOPES))->mapWithKeys(fn ($s) => [$s => $all->where('scope', $s)->count()])->all();

        $rows = $all
            ->when(filled($f['search'] ?? null), function (Collection $c) use ($f) {
                $needle = mb_strtolower(trim($f['search']));

                return $c->filter(fn ($r) => str_contains(mb_strtolower($r['title'].' '.$r['description']), $needle));
            })
            ->when(filled($f['cakupan'] ?? null), fn (Collection $c) => $c->where('scope', $f['cakupan']))
            ->when(filled($f['kelompok'] ?? null), fn (Collection $c) => $c->where('group', $f['kelompok']))
            ->when(filled($f['alat'] ?? null), fn (Collection $c) => $c->where('tool_code', $f['alat']))
            ->when(filled($f['status'] ?? null), fn (Collection $c) => $f['status'] === 'review' ? $c->where('review', true) : $c->where('state', $f['status']));

        $rows = (($f['sort'] ?? 'updated') === 'name' ? $rows->sortBy(fn ($r) => mb_strtolower($r['title'])) : $rows->sortByDesc('updated_at'))->values();

        $page = LengthAwarePaginator::resolveCurrentPage();
        $paged = new LengthAwarePaginator($rows->forPage($page, self::PER_PAGE)->values(), $rows->count(), self::PER_PAGE, $page, ['path' => route('admin.answer-behavior.index'), 'query' => $request->except('page')]);

        return view('admin.answer-behavior.index', $shared + [
            'mode' => 'list',
            'stats' => $stats,
            'rows' => $paged,
            'tabCounts' => $tabCounts,
            'activeTab' => $f['cakupan'] ?? '',
            'filtering' => filled($f['search'] ?? null) || filled($f['kelompok'] ?? null) || filled($f['status'] ?? null) || filled($f['alat'] ?? null) || filled($f['cakupan'] ?? null) || ($f['sort'] ?? 'updated') !== 'updated',
        ]);
    }

    /** Buat aturan baru: v1 draf dengan nilai awal untuk kelompoknya. Belum aktif. */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:400'],
            'group' => ['required', Rule::in(array_keys(AnswerBehavior::GROUPS))],
            'scope' => ['required', Rule::in(array_keys(AnswerBehavior::SCOPES))],
            'tool_code' => ['nullable', 'string', 'max:80'],
        ], ['title.required' => 'Nama aturan wajib diisi.']);

        $title = trim((string) preg_replace('/\s+/u', ' ', $data['title']));
        $tool = $data['scope'] === 'tool' ? ($data['tool_code'] ?? null) : null;

        if ($data['scope'] === 'tool' && ! array_key_exists((string) $tool, $this->rules->tools())) {
            $this->raise($request, ['tool_code' => 'Pilih alat riset untuk cakupan alat tertentu.']);
        }

        $config = ['v' => 1, 'group' => $data['group'], 'scope' => $data['scope'], 'tool_code' => $tool] + $this->def->defaults($data['group']);

        try {
            $version = $this->rules->create(['title' => $title, 'description' => $this->clean($data['description'] ?? null)], $config, $this->starterText($data['group']), $this->userId($request));
        } catch (InvalidArgumentException $e) {
            $this->raise($request, ['title' => $e->getMessage()]);
        }

        $this->audit($request, 'answer_behavior.created', $version, ['group' => $data['group'], 'scope' => $data['scope']]);

        return $this->done($request, 'Aturan "'.$title.'" dibuat sebagai draf v1. Belum aktif: tinjau isinya lalu aktifkan.', $this->url($version));
    }

    /** Pasang satu preset sebagai draf (aksi eksplisit). Tidak pernah mengaktifkan. */
    public function preset(Request $request, string $preset): RedirectResponse|JsonResponse
    {
        $found = collect(AnswerBehaviorPresets::all())->firstWhere('key', $preset);
        abort_unless($found, 404);

        if ($found['scope'] === 'tool' && ! array_key_exists((string) $found['tool_code'], $this->rules->tools())) {
            $this->raise($request, ['preset' => 'Alat "'.$found['tool_code'].'" tidak tersedia di katalog, jadi preset ini tidak dapat dipasang.'], null, 422);
        }

        $result = $this->def->normalize($found['group'], $found['scope'], $found['tool_code'], $found['input'], array_keys($this->rules->tools()));

        try {
            $version = $this->rules->create(['title' => $found['title'], 'description' => $found['description']], $result['config'], $found['text'], $this->userId($request));
        } catch (InvalidArgumentException $e) {
            $this->raise($request, ['preset' => $e->getMessage()]);
        }

        $this->audit($request, 'answer_behavior.preset_installed', $version, ['preset' => $preset]);

        return $this->done($request, 'Aturan "'.$found['title'].'" dipasang sebagai draf v1. Belum aktif.', $this->url($version));
    }

    /*
    |--------------------------------------------------------------------------
    | Editor
    |--------------------------------------------------------------------------
    */

    public function show(Request $request, string $rule)
    {
        $item = $this->rules->find($rule);
        abort_unless($item, 404);

        $f = $request->validate([
            'tab' => ['nullable', Rule::in(['aturan', 'versi', 'pratinjau'])],
            'v' => ['nullable', 'integer'],
            'a' => ['nullable', 'integer'],
            'b' => ['nullable', 'integer'],
            'mode' => ['nullable', Rule::in(['research', 'document'])],
            'alat' => ['nullable', 'string', 'max:80'],
            'kondisi' => ['nullable', Rule::in(array_keys(self::PREVIEW_CONDITIONS))],
            'uji' => ['nullable', Rule::in(['this', 'active'])],
            'contoh' => ['nullable', 'string', 'max:500'],
        ]);

        $versions = $item['versions'];
        $version = $versions->firstWhere('id', $f['v'] ?? null) ?? $item['active'] ?? $item['draft'] ?? $versions->first();
        $tab = $f['tab'] ?? 'aturan';
        $compare = null;

        if (filled($f['a'] ?? null) && filled($f['b'] ?? null)) {
            $old = $versions->firstWhere('id', $f['a']);
            $new = $versions->firstWhere('id', $f['b']);

            if ($old && $new && $old->id !== $new->id) {
                $compare = ['old' => $old, 'new' => $new, 'fields' => $this->compareFields($old, $new)];
            }
        }

        $ids = $versions->modelKeys();
        $cfg = $this->def->decode($version->expected_output);

        return view('admin.answer-behavior.index', [
            'mode' => 'editor',
            'rule' => $item,
            'versions' => $versions,
            'version' => $version,
            'config' => $cfg,
            'lock' => $this->versions->lockReason($version),
            'tab' => $tab,
            'compare' => $compare,
            'groups' => AnswerBehavior::GROUPS,
            'scopes' => AnswerBehavior::SCOPES,
            'tools' => $this->rules->tools(),
            'depths' => AnswerBehavior::DEPTHS,
            'registers' => AnswerBehavior::REGISTERS,
            'languages' => AnswerBehavior::LANGUAGES,
            'sectionOptions' => AnswerBehavior::SECTIONS,
            'followTypes' => AnswerBehavior::FOLLOW_TYPES,
            'conditions' => AnswerBehavior::CONDITIONS,
            'systemCopy' => AnswerBehavior::SYSTEM_COPY,
            'fixedRules' => AnswerBehavior::FIXED_RULES,
            'conflict' => $version->status !== 'active' && $cfg !== [] ? $this->rules->conflictFor($version->name, $cfg) : null,
            'lintNotes' => $this->def->lint((string) $version->task_prompt),
            'preview' => $tab === 'pratinjau' && $cfg !== [] ? $this->preview($version, $f) : null,
            'previewInput' => ['mode' => $f['mode'] ?? (($cfg['scope'] ?? '') === 'document' ? 'document' : 'research'), 'alat' => $f['alat'] ?? ($cfg['tool_code'] ?? ''), 'kondisi' => $f['kondisi'] ?? 'ok', 'uji' => $f['uji'] ?? 'this', 'contoh' => $f['contoh'] ?? ''],
            'previewConditions' => self::PREVIEW_CONDITIONS,
            'history' => Schema::hasTable('audit_logs')
                ? AuditLog::query()->with('user:id,name')->where('auditable_type', PromptTemplate::class)->whereIn('auditable_id', $ids)->where('action', 'like', 'answer_behavior.%')->latest('id')->limit(60)->get()
                : collect(),
        ]);
    }

    /** Simpan draf. Versi aktif/arsip ditolak di service, apa pun yang dikirim form. */
    public function update(Request $request, string $rule, string $version): RedirectResponse|JsonResponse
    {
        $target = $this->rules->version($rule, $version);
        $current = $this->def->decode($target->expected_output);
        abort_if($current === [], 422, 'Konfigurasi versi tidak dapat dibaca. Buat revisi dari versi lain.');

        $texts = [];

        foreach (['task_prompt', 'description'] as $key) {
            if (is_string($request->input($key))) {
                $texts[$key] = $this->def->clean($request->input($key));
            }
        }

        $request->merge($texts);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:400'],
            'scope' => ['required', Rule::in(array_keys(AnswerBehavior::SCOPES))],
            'tool_code' => ['nullable', 'string', 'max:80'],
            'task_prompt' => ['required', 'string', 'min:10', 'max:'.AnswerBehavior::MAX_TEXT],
        ], [
            'title.required' => 'Nama aturan wajib diisi.',
            'task_prompt.required' => 'Isi prompt perilaku wajib diisi.',
            'task_prompt.min' => 'Isi prompt perilaku terlalu pendek.',
            'task_prompt.max' => 'Isi prompt perilaku maksimal '.number_format(AnswerBehavior::MAX_TEXT, 0, ',', '.').' karakter.',
        ]);

        $errors = [];

        foreach ($this->def->lint($data['task_prompt']) as $note) {
            $errors['task_prompt'] = $note;
        }

        $result = $this->def->normalize($current['group'], $data['scope'], $data['tool_code'] ?? null, $request->all(), array_keys($this->rules->tools()));
        $errors = $errors + $result['errors'];

        if ($errors !== []) {
            $this->raise($request, $errors);
        }

        try {
            $saved = $this->rules->saveDraft($target, ['title' => trim((string) preg_replace('/\s+/u', ' ', $data['title'])), 'description' => $this->clean($data['description'] ?? null)], $result['config'], $data['task_prompt']);
        } catch (InvalidArgumentException $e) {
            $this->raise($request, ['title' => $e->getMessage()], null, str_contains($e->getMessage(), 'dikunci') ? 409 : 422);
        }

        if ($saved['changed'] !== []) {
            $this->audit($request, 'answer_behavior.updated', $saved['prompt'], ['changed' => $saved['changed']]);
        }

        return $this->done($request, $saved['changed'] === [] ? 'Tidak ada perubahan untuk disimpan.' : 'Draf v'.$saved['prompt']->version.' disimpan. Belum aktif.', $this->url($saved['prompt']));
    }

    public function revise(Request $request, string $rule, string $version): RedirectResponse|JsonResponse
    {
        $from = $this->rules->version($rule, $version);
        $copy = $this->rules->revise($from, $this->userId($request));

        $this->audit($request, 'answer_behavior.revised', $copy, ['from_version' => $from->version]);

        return $this->done($request, 'Draf v'.$copy->version.' dibuat dari v'.$from->version.'. Versi aktif tidak berubah.', $this->url($copy));
    }

    public function activate(Request $request, string $rule, string $version): RedirectResponse|JsonResponse
    {
        $target = $this->rules->version($rule, $version);

        try {
            $result = $this->rules->activate($target);
        } catch (InvalidArgumentException $e) {
            $this->raise($request, ['activate' => $e->getMessage()], null, 409);
        }

        if ($result['changed']) {
            $this->audit($request, 'answer_behavior.activated', $target, ['previous_version' => $result['previous']?->version]);
        }

        $title = $target->indonesian_name ?: 'Aturan';

        return $this->done($request, $result['changed'] ? '"'.$title.'": v'.$target->version.' sekarang aktif'.($result['previous'] ? ' (menggantikan v'.$result['previous']->version.', yang tetap tersimpan)' : '').'. Berlaku untuk riset berikutnya; hasil lama tidak berubah.' : 'v'.$target->version.' memang sudah aktif.', $this->url($target));
    }

    public function archive(Request $request, string $rule, string $version): RedirectResponse|JsonResponse
    {
        $target = $this->rules->version($rule, $version);

        try {
            $this->rules->archiveDraft($target);
        } catch (InvalidArgumentException $e) {
            $this->raise($request, ['archive' => $e->getMessage()], null, 409);
        }

        $this->audit($request, 'answer_behavior.archived', $target);

        return $this->done($request, 'Draf v'.$target->version.' diarsipkan. Isinya tetap tersimpan.', $this->url($target, 'versi'));
    }

    /** Berhentikan aturan: versi aktif diarsipkan (tetap tersimpan). Riset berikutnya kembali ke aturan yang lebih umum atau perilaku bawaan. */
    public function deactivate(Request $request, string $rule): RedirectResponse|JsonResponse
    {
        $archived = $this->rules->deactivate($rule);

        if (! $archived) {
            $this->raise($request, ['deactivate' => 'Aturan ini memang tidak sedang aktif.'], null, 409);
        }

        $this->audit($request, 'answer_behavior.deactivated', $archived);

        return $this->done($request, 'Aturan dinonaktifkan. v'.$archived->version.' tetap tersimpan dan bisa diaktifkan lagi.', $this->url($archived, 'versi'));
    }

    /*
    |--------------------------------------------------------------------------
    | Pratinjau dan perbandingan
    |--------------------------------------------------------------------------
    */

    /** Pratinjau konfigurasi: hanya membaca, tidak memanggil model, tidak menulis apa pun. */
    private function preview(PromptTemplate $version, array $f): array
    {
        $config = $this->def->decode($version->expected_output);
        $mode = $f['mode'] ?? ($config['scope'] === 'document' ? 'document' : 'research');
        $tool = filled($f['alat'] ?? null) ? $f['alat'] : ($config['tool_code'] ?? null);
        $whatIf = ($f['uji'] ?? 'this') === 'this' && $version->status !== 'active' ? $version : null;
        $resolved = $this->resolver->resolve($mode, $tool, $whatIf);
        $condition = $f['kondisi'] ?? 'ok';
        $thisApplied = collect($resolved['applied'])->contains(fn ($a) => $a['version_id'] === $version->id);

        return [
            'resolved' => $resolved,
            'mode' => $mode,
            'tool' => $tool,
            'condition' => $condition,
            'whatIf' => $whatIf !== null,
            'thisApplied' => $thisApplied,
            'payload' => $this->resolver->payload($resolved),
            'copy' => isset(AnswerBehavior::CONDITIONS[$condition]) ? $this->resolver->unavailable($condition, $resolved) + ['custom' => $resolved['unavailable'][$condition] ?? []] : null,
            'sample' => trim((string) ($f['contoh'] ?? '')),
        ];
    }

    private function compareFields(PromptTemplate $old, PromptTemplate $new): array
    {
        // Konfigurasi dibandingkan sebagai teks terbaca (bukan JSON mentah) agar admin melihat perubahan yang bermakna.
        $read = fn (PromptTemplate $v): PromptTemplate => tap(new PromptTemplate, function ($c) use ($v): void {
            $cfg = $this->def->decode($v->expected_output);
            $c->task_prompt = (string) $v->task_prompt;
            $c->indonesian_name = (string) $v->indonesian_name;
            $c->short_description = (string) $v->short_description;
            $c->expected_output = $this->describe($cfg);
        });

        return $this->versions->compare($read($old), $read($new), ['task_prompt' => 'Isi prompt perilaku', 'expected_output' => 'Pengaturan', 'indonesian_name' => 'Nama aturan', 'short_description' => 'Deskripsi']);
    }

    /** Ringkasan konfigurasi sebagai baris teks, urut tetap. */
    private function describe(array $c): string
    {
        $lines = ['Cakupan: '.(AnswerBehavior::SCOPES[$c['scope'] ?? ''] ?? '—').(($c['tool_code'] ?? null) ? ' ('.$c['tool_code'].')' : '')];

        if ($s = $c['style'] ?? null) {
            $lines[] = 'Kedalaman: '.(AnswerBehavior::DEPTHS[$s['depth']] ?? $s['depth']);
            $lines[] = 'Register: '.(AnswerBehavior::REGISTERS[$s['register']] ?? $s['register']);
            $lines[] = 'Bahasa: '.(AnswerBehavior::LANGUAGES[$s['language']] ?? $s['language']);
            $lines[] = 'Jelaskan istilah teknis: '.($s['explain_terms'] ? 'ya' : 'tidak');
        }

        if ($s = $c['structure'] ?? null) {
            $lines[] = 'Bagian: '.implode(', ', array_map(fn ($k) => AnswerBehavior::SECTIONS[$k] ?? $k, $s['sections']));
        }

        if ($g = $c['grounding'] ?? null) {
            $lines[] = 'Dukungan sebagian: '.($g['partial'] ? 'ya' : 'tidak');
            $lines[] = 'Sumber bertentangan: '.($g['conflict'] ? 'ya' : 'tidak');
            $lines[] = 'Nyatakan ketidakpastian: '.($g['uncertainty'] ? 'ya' : 'tidak');

            foreach (AnswerBehavior::CONDITIONS as $key => $label) {
                $copy = $g['unavailable'][$key] ?? null;
                $lines[] = $label.' — pesan: '.($copy['message'] ?? '(bawaan)');
                $lines[] = $label.' — langkah: '.($copy['next_action'] ?? '(bawaan)');
            }
        }

        if ($f = $c['follow_up'] ?? null) {
            $lines[] = 'Pertanyaan lanjutan: '.($f['enabled'] ? 'aktif' : 'nonaktif');
            $lines[] = 'Jumlah: '.$f['min'].'–'.$f['max'];
            $lines[] = 'Jenis: '.implode(', ', array_map(fn ($k) => AnswerBehavior::FOLLOW_TYPES[$k] ?? $k, $f['types']));
            $lines[] = 'Rincian: '.($f['detail'] === 'detailed' ? 'rinci' : 'singkat');
            $lines[] = 'Sembunyikan bila bukti kurang dari: '.$f['hide_below_evidence'];
        }

        return implode("\n", $lines);
    }

    /*
    |--------------------------------------------------------------------------
    | Helper
    |--------------------------------------------------------------------------
    */

    private function starterText(string $group): string
    {
        return match ($group) {
            'style' => 'Jawab pertanyaan utama pengguna terlebih dahulu dengan bahasa yang jelas dan tidak bertele-tele.',
            'structure' => 'Susun jawaban mengikuti urutan bagian yang dipilih dan lewati bagian yang tidak dapat didukung bukti.',
            'grounding' => 'Hanya nyatakan klaim yang didukung bukti dari sumber, dan jelaskan bagian yang belum didukung.',
            default => 'Tawarkan pertanyaan lanjutan hanya bila relevan dengan topik dan analisis yang baru diberikan.',
        };
    }

    private function clean(?string $text): ?string
    {
        $text = $this->def->clean((string) $text);

        return $text === '' ? null : $text;
    }

    private function url(PromptTemplate $version, ?string $tab = null): string
    {
        return route('admin.answer-behavior.show', array_filter(['rule' => $this->rules->uuidOf($version->name), 'tab' => $tab && $tab !== 'aturan' ? $tab : null, 'v' => $version->id]));
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

    private function audit(Request $request, string $action, PromptTemplate $version, array $extra = []): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        AuditLog::create([
            'user_id' => $this->userId($request),
            'action' => $action,
            'auditable_type' => PromptTemplate::class,
            'auditable_id' => $version->id,
            'metadata_json' => ['rule' => $this->rules->uuidOf($version->name), 'name' => $version->indonesian_name, 'version' => $version->version, 'status' => $version->status] + $extra,
            'ip_address' => $request->ip(),
        ]);
    }
}
