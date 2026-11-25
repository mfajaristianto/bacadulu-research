<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\DocumentPromptSection;
use App\Models\DocumentPromptTemplate;
use App\Models\PromptTemplate;
use App\Services\Research\DocumentPromptPresets;
use App\Services\Research\DocumentPrompts;
use App\Services\Research\PromptVersions;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/*
 * Prompt Dokumen (admin): template penulisan dokumen -> bagian -> instruksi bagian (versi di prompt_templates).
 *
 * Aturan dijaga di sini dan di DocumentPrompts, bukan hanya di UI: hanya draf yang belum dipakai yang boleh diedit,
 * aktivasi satu bagian tidak menyentuh bagian lain, tidak ada hapus, tidak ada aktivasi otomatis, dan template baru
 * (termasuk impor preset) selalu berstatus draf. Rute memakai id mentah (bukan implicit binding) supaya halaman tetap
 * memberi penjelasan yang jujur ketika tabelnya belum dimigrasi.
 */
class DocumentPromptController extends Controller
{
    private const PER_PAGE = 12;

    private const STATUS_FILTERS = ['draft' => 'Draf', 'incomplete' => 'Perlu dilengkapi', 'ready' => 'Siap digunakan', 'archived' => 'Diarsipkan'];

    public function __construct(private readonly DocumentPrompts $docs, private readonly PromptVersions $versions) {}

    /*
    |--------------------------------------------------------------------------
    | Daftar, impor, buat
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        if (! $this->docs->available()) {
            return view('admin.document-prompts.index', ['mode' => 'unavailable']);
        }

        $f = $request->validate([
            'search' => ['nullable', 'string', 'max:160'],
            'jenis' => ['nullable', Rule::in(array_keys(DocumentPrompts::TYPES))],
            'status' => ['nullable', Rule::in(array_keys(self::STATUS_FILTERS))],
            'sort' => ['nullable', Rule::in(['updated', 'name', 'sections'])],
            'baru' => ['nullable', 'boolean'],
            'impor' => ['nullable', 'string', 'max:60'],
            'word' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $shared = ['types' => DocumentPrompts::TYPES, 'statusFilters' => self::STATUS_FILTERS];

        if ($request->boolean('word')) {
            return view('admin.document-prompts.index', $shared + [
                'mode' => 'word', 'types' => DocumentPrompts::TYPES, 'languages' => DocumentPrompts::LANGUAGES, 'styles' => DocumentPrompts::CITATION_STYLES,
                'qaModes' => DocumentPrompts::QA_MODES, 'roles' => DocumentPrompts::ROLES, 'placeholders' => DocumentPrompts::PLACEHOLDERS,
            ]);
        }

        if (filled($f['impor'] ?? null)) {
            $preset = DocumentPromptPresets::find($f['impor']);
            abort_unless($preset, 404);
            $existing = DocumentPromptTemplate::query()->where('slug', $this->docs->slug($preset['name']))->first();

            return view('admin.document-prompts.index', $shared + ['mode' => 'import', 'preset' => $preset, 'existing' => $existing, 'placeholders' => DocumentPrompts::PLACEHOLDERS]);
        }

        if ($request->boolean('baru')) {
            return view('admin.document-prompts.index', $shared + ['mode' => 'new', 'presets' => DocumentPromptPresets::all(), 'languages' => DocumentPrompts::LANGUAGES, 'styles' => DocumentPrompts::CITATION_STYLES, 'qaModes' => DocumentPrompts::QA_MODES]);
        }

        $all = DocumentPromptTemplate::query()->with(['sections.activePrompt'])->get()->map(fn (DocumentPromptTemplate $t) => $this->row($t));

        $stats = [
            'total' => $all->count(),
            'ready' => $all->where('state.key', 'ready')->count(),
            'draft' => $all->where('state.key', 'draft')->count(),
            'incomplete' => $all->where('state.key', 'incomplete')->count(),
        ];

        $rows = $all
            ->when(filled($f['search'] ?? null), function (Collection $c) use ($f) {
                $needle = mb_strtolower(trim($f['search']));

                return $c->filter(fn ($r) => str_contains(mb_strtolower($r['template']->name.' '.$r['template']->description), $needle));
            })
            ->when(filled($f['jenis'] ?? null), fn (Collection $c) => $c->where('template.document_type', $f['jenis']))
            ->when(filled($f['status'] ?? null), fn (Collection $c) => $c->where('state.key', $f['status']));

        $rows = (match ($f['sort'] ?? 'updated') {
            'name' => $rows->sortBy(fn ($r) => mb_strtolower($r['template']->name)),
            'sections' => $rows->sortByDesc('enabled'),
            default => $rows->sortByDesc(fn ($r) => $r['template']->updated_at),
        })->values();

        $page = LengthAwarePaginator::resolveCurrentPage();
        $paged = new LengthAwarePaginator($rows->forPage($page, self::PER_PAGE)->values(), $rows->count(), self::PER_PAGE, $page, ['path' => route('admin.prompt-documents.index'), 'query' => $request->except('page')]);

        return view('admin.document-prompts.index', $shared + [
            'mode' => 'list',
            'stats' => $stats,
            'rows' => $paged,
            'filtering' => filled($f['search'] ?? null) || filled($f['jenis'] ?? null) || filled($f['status'] ?? null) || ($f['sort'] ?? 'updated') !== 'updated',
        ]);
    }

    /** Buat template kosong (draf). Bagian ditambahkan di editor. */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->requireStorage($request);
        $data = $this->templateData($request, null);

        $template = DocumentPromptTemplate::create($data + ['uuid' => (string) Str::uuid(), 'status' => 'draft', 'created_by' => $this->userId($request)]);
        $this->audit($request, 'document_prompt.template.created', $template);

        return $this->done($request, 'Template "'.$template->name.'" dibuat sebagai draf. Tambahkan bagian pertama.', $this->url($template));
    }

    /** Simpan preset sebagai template DRAF (aksi eksplisit setelah admin membaca pratinjau). */
    public function import(Request $request): RedirectResponse|JsonResponse
    {
        $this->requireStorage($request);
        $key = (string) $request->validate(['preset' => ['required', 'string', 'max:60']])['preset'];
        $preset = DocumentPromptPresets::find($key);

        if (! $preset) {
            $this->raise($request, ['preset' => 'Preset tidak ditemukan.'], null, 404);
        }

        $existing = DocumentPromptTemplate::query()->where('slug', $this->docs->slug($preset['name']))->first();

        if ($existing) {
            $this->raise($request, ['preset' => 'Template "'.$existing->name.'" sudah ada. Buka yang sudah ada atau salin untuk membuat varian.'], $this->url($existing), 422);
        }

        $template = $this->docs->importPreset($preset, $this->userId($request));
        $this->audit($request, 'document_prompt.template.imported', $template, ['preset' => $key, 'sections' => count($preset['sections'])]);

        return $this->done($request, 'Template "'.$template->name.'" disimpan sebagai draf dengan '.count($preset['sections']).' bagian. Belum ada yang aktif: tinjau lalu aktifkan.', $this->url($template));
    }

    /*
    |--------------------------------------------------------------------------
    | Editor template
    |--------------------------------------------------------------------------
    */

    public function show(Request $request, string $template)
    {
        $this->requireStorage($request);
        $template = $this->template($template);

        $f = $request->validate([
            'bagian' => ['nullable', 'string', 'max:60'],
            'tab' => ['nullable', Rule::in(['prompt', 'konfigurasi', 'versi'])],
            'v' => ['nullable', 'integer'],
            'a' => ['nullable', 'integer'],
            'b' => ['nullable', 'integer'],
        ]);

        $template->load(['sections.activePrompt', 'creator']);
        $sections = $template->sections;
        $selected = $sections->firstWhere('key', $f['bagian'] ?? null) ?? $sections->first();
        $readiness = $this->docs->readiness($template);
        $state = $this->docs->state($template, $readiness);

        $base = [
            'mode' => 'editor',
            'template' => $template,
            'sections' => $sections,
            'readiness' => $readiness,
            'state' => $state,
            'types' => DocumentPrompts::TYPES,
            'languages' => DocumentPrompts::LANGUAGES,
            'styles' => DocumentPrompts::CITATION_STYLES,
            'qaModes' => DocumentPrompts::QA_MODES,
            'roles' => DocumentPrompts::ROLES,
            'placeholders' => DocumentPrompts::PLACEHOLDERS,
            'tab' => $f['tab'] ?? 'prompt',
            'selected' => $selected,
            'contract' => $this->docs->contract($template),
            'history' => AuditLog::query()->with('user:id,name')->where('auditable_type', DocumentPromptTemplate::class)->where('auditable_id', $template->id)->latest('id')->limit(80)->get(),
            'sectionInfo' => $sections->mapWithKeys(fn ($s) => [$s->id => $this->sectionInfo($s)]),
        ];

        if (! $selected) {
            return view('admin.document-prompts.index', $base + ['versions' => collect(), 'version' => null, 'lock' => null, 'compare' => null]);
        }

        $versions = $this->docs->versionsOf($selected);
        $version = $versions->firstWhere('id', $f['v'] ?? null) ?? $versions->firstWhere('id', $selected->active_prompt_template_id) ?? $versions->firstWhere('status', 'draft') ?? $versions->first();
        $compare = null;

        if (filled($f['a'] ?? null) && filled($f['b'] ?? null)) {
            $old = $versions->firstWhere('id', $f['a']);
            $new = $versions->firstWhere('id', $f['b']);

            if ($old && $new && $old->id !== $new->id) {
                $compare = ['old' => $old, 'new' => $new, 'fields' => $this->versions->compare($old, $new, ['task_prompt' => 'Isi prompt', 'system_prompt' => 'Peran dan batasan', 'retrieval_instructions' => 'Penggunaan sumber', 'output_instructions' => 'Format keluaran'])];
            }
        }

        return view('admin.document-prompts.index', $base + [
            'versions' => $versions,
            'version' => $version,
            'lock' => $version ? $this->docs->lockReason($version) : null,
            'compare' => $compare,
        ]);
    }

    /** Pengaturan template (nama, jenis, bahasa, sitasi, penempatan Q&A). */
    public function update(Request $request, string $template): RedirectResponse|JsonResponse
    {
        $this->requireStorage($request);
        $template = $this->template($template);
        $template->fill($this->templateData($request, $template));
        $dirty = array_keys($template->getDirty());
        $template->save();

        if ($dirty !== []) {
            $this->audit($request, 'document_prompt.template.updated', $template, ['changed' => $dirty]);
        }

        return $this->done($request, $dirty === [] ? 'Tidak ada perubahan untuk disimpan.' : 'Pengaturan template disimpan.', $this->url($template, $request->input('bagian')));
    }

    public function status(Request $request, string $template): RedirectResponse|JsonResponse
    {
        $this->requireStorage($request);
        $template = $this->template($template);
        $to = $request->validate(['to' => ['required', Rule::in(['active', 'archived', 'draft'])]])['to'];

        try {
            $message = match ($to) {
                'active' => $this->docs->activateTemplate($template) ? 'Template "'.$template->name.'" diaktifkan.' : 'Template memang sudah aktif.',
                'archived' => $this->setStatus($template, 'archived', 'Template "'.$template->name.'" diarsipkan. Semua versi tetap tersimpan.'),
                'draft' => $template->status === 'archived' ? $this->setStatus($template, 'draft', 'Template dipulihkan sebagai draf. Aktifkan lagi setelah ditinjau.') : 'Template tidak sedang diarsipkan.',
            };
        } catch (InvalidArgumentException $e) {
            $this->raise($request, ['status' => $e->getMessage()], null, 422);
        }

        $this->audit($request, 'document_prompt.template.status', $template->refresh(), ['to' => $to]);

        return $this->done($request, $message, $this->url($template, $request->input('bagian')));
    }

    public function duplicate(Request $request, string $template): RedirectResponse|JsonResponse
    {
        $this->requireStorage($request);
        $source = $this->template($template);
        $copy = $this->docs->duplicate($source, $this->userId($request));
        $this->audit($request, 'document_prompt.template.duplicated', $copy, ['source_template_id' => $source->id, 'source_name' => $source->name]);

        return $this->done($request, 'Salinan "'.$copy->name.'" dibuat sebagai draf. Instruksinya berupa draf v1 yang belum aktif.', $this->url($copy));
    }

    /** Aktifkan draf terbaru bagian yang belum punya versi aktif (aksi eksplisit admin). */
    public function activateDrafts(Request $request, string $template): RedirectResponse|JsonResponse
    {
        $this->requireStorage($request);
        $template = $this->template($template);
        $done = $this->docs->activateDrafts($template);

        foreach ($done as $item) {
            $this->audit($request, 'document_prompt.prompt.activated', $template, ['section' => $item['section']->key, 'version' => $item['prompt']->version, 'bulk' => true]);
        }

        return $this->done($request, $done === [] ? 'Tidak ada draf yang bisa diaktifkan. Bagian yang sudah aktif tidak diubah.' : count($done).' bagian diaktifkan dari draf terbarunya: '.collect($done)->map(fn ($d) => $d['section']->title.' v'.$d['prompt']->version)->implode(', ').'.', $this->url($template, $request->input('bagian')));
    }

    /*
    |--------------------------------------------------------------------------
    | Bagian
    |--------------------------------------------------------------------------
    */

    public function sectionStore(Request $request, string $template): RedirectResponse|JsonResponse
    {
        $this->requireStorage($request);
        $template = $this->template($template);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'role' => ['required', Rule::in(array_keys(DocumentPrompts::ROLES))],
            'is_required' => ['nullable', 'boolean'],
        ], ['title.required' => 'Nama bagian wajib diisi.']);

        $title = trim((string) preg_replace('/\s+/u', ' ', $data['title']));

        if ($template->sections()->whereRaw('lower(title) = ?', [mb_strtolower($title)])->exists()) {
            $this->raise($request, ['title' => 'Template ini sudah punya bagian bernama "'.$title.'".']);
        }

        $section = DocumentPromptSection::create([
            'uuid' => (string) Str::uuid(),
            'document_prompt_template_id' => $template->id,
            'key' => $this->docs->uniqueKey($template, $title),
            'title' => $title,
            'role' => $data['role'],
            'is_required' => $request->boolean('is_required', true),
            'position' => 1 + (int) $template->sections()->max('position'),
        ]);
        $this->audit($request, 'document_prompt.section.created', $template, ['section' => $section->key]);

        return $this->done($request, 'Bagian "'.$section->title.'" ditambahkan di urutan terakhir. Tulis instruksinya.', $this->url($template, $section->key, 'prompt'));
    }

    public function sectionUpdate(Request $request, string $template, string $section): RedirectResponse|JsonResponse
    {
        $this->requireStorage($request);
        $template = $this->template($template);
        $section = $this->section($template, $section);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:1000'],
            'purpose' => ['nullable', 'string', 'max:1000'],
            'role' => ['required', Rule::in(array_keys(DocumentPrompts::ROLES))],
            'min_words' => ['nullable', 'integer', 'min:0', 'max:200000'],
            'max_words' => ['nullable', 'integer', 'min:0', 'max:200000'],
            'target_words' => ['nullable', 'integer', 'min:0', 'max:200000'],
            'item_count' => ['nullable', 'integer', 'min:1', 'max:200'],
            'min_sources' => ['nullable', 'integer', 'min:0', 'max:500'],
            'requirements' => ['nullable', 'string', 'max:3000'],
            'depends_on' => ['nullable', 'array', 'max:30'],
            'depends_on.*' => ['string', 'max:60'],
        ], [
            'title.required' => 'Nama bagian wajib diisi.',
            'min_words.min' => 'Jumlah kata tidak boleh negatif.', 'max_words.min' => 'Jumlah kata tidak boleh negatif.', 'target_words.min' => 'Jumlah kata tidak boleh negatif.',
            'min_words.integer' => 'Isi dengan angka bulat.', 'max_words.integer' => 'Isi dengan angka bulat.', 'target_words.integer' => 'Isi dengan angka bulat.',
        ]);

        $errors = [];
        $title = trim((string) preg_replace('/\s+/u', ' ', $data['title']));

        if ($template->sections()->whereKeyNot($section->id)->whereRaw('lower(title) = ?', [mb_strtolower($title)])->exists()) {
            $errors['title'] = 'Template ini sudah punya bagian bernama "'.$title.'".';
        }

        $min = $data['min_words'] ?? null;
        $max = $data['max_words'] ?? null;
        $target = $data['target_words'] ?? null;

        if ($min !== null && $max !== null && $min > $max) {
            $errors['min_words'] = 'Minimum kata tidak boleh lebih besar dari maksimum.';
        }

        if ($target !== null && (($min !== null && $target < $min) || ($max !== null && $target > $max))) {
            $errors['target_words'] = 'Target kata harus berada di antara minimum dan maksimum.';
        }

        $depends = array_values(array_unique($data['depends_on'] ?? []));
        $others = $template->sections()->whereKeyNot($section->id)->get()->keyBy('key');

        foreach ($depends as $key) {
            $dep = $others->get($key);

            if (! $dep) {
                $errors['depends_on'] = 'Prasyarat "'.$key.'" tidak ada pada template ini.';
            } elseif ($dep->position >= $section->position) {
                $errors['depends_on'] = '"'.$dep->title.'" berada setelah bagian ini, jadi tidak bisa menjadi prasyarat. Pindahkan urutannya dahulu.';
            }
        }

        if ($errors !== []) {
            $this->raise($request, $errors);
        }

        $section->fill([
            'title' => $title,
            'description' => $data['description'] ?? null,
            'purpose' => $data['purpose'] ?? null,
            'role' => $data['role'],
            'is_required' => $request->boolean('is_required'),
            'is_enabled' => $request->boolean('is_enabled'),
            'min_words' => $min, 'max_words' => $max, 'target_words' => $target,
            'item_count' => $data['item_count'] ?? null,
            'min_sources' => $data['min_sources'] ?? null,
            'requirements' => isset($data['requirements']) ? str_replace(["\r\n", "\r"], "\n", $data['requirements']) : null,
            'depends_on' => $depends,
        ]);
        $dirty = array_keys($section->getDirty());
        $section->save();

        if ($dirty !== []) {
            $this->audit($request, 'document_prompt.section.updated', $template, ['section' => $section->key, 'changed' => $dirty]);
        }

        return $this->done($request, $dirty === [] ? 'Tidak ada perubahan untuk disimpan.' : 'Konfigurasi "'.$section->title.'" disimpan.', $this->url($template, $section->key, 'konfigurasi'));
    }

    public function sectionMove(Request $request, string $template, string $section): RedirectResponse|JsonResponse
    {
        $this->requireStorage($request);
        $template = $this->template($template);
        $section = $this->section($template, $section);
        $direction = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]])['direction'];

        try {
            $moved = $this->docs->move($template, $section, $direction);
        } catch (InvalidArgumentException $e) {
            $this->raise($request, ['direction' => $e->getMessage()]);
        }

        if ($moved) {
            $this->audit($request, 'document_prompt.section.moved', $template, ['section' => $section->key, 'direction' => $direction]);
        }

        return $this->done($request, $moved ? '"'.$section->title.'" dipindah ke '.($direction === 'up' ? 'atas' : 'bawah').'.' : 'Bagian sudah berada di ujung urutan.', $this->url($template, $section->key, $request->input('tab')));
    }

    /*
    |--------------------------------------------------------------------------
    | Instruksi bagian dan versinya
    |--------------------------------------------------------------------------
    */

    /** Buat v1 draf (bagian belum punya versi). */
    public function promptStore(Request $request, string $template, string $section): RedirectResponse|JsonResponse
    {
        return $this->savePrompt($request, $template, $section, null);
    }

    /** Edit draf. Versi aktif/arsip/yang pernah dipakai ditolak di service, apa pun yang dikirim form. */
    public function promptUpdate(Request $request, string $template, string $section, string $prompt): RedirectResponse|JsonResponse
    {
        return $this->savePrompt($request, $template, $section, $prompt);
    }

    public function promptRevise(Request $request, string $template, string $section, string $prompt): RedirectResponse|JsonResponse
    {
        $this->requireStorage($request);
        [$template, $section, $from] = $this->resolve($template, $section, $prompt);

        try {
            $copy = $this->docs->revise($section, $from, $this->userId($request));
        } catch (InvalidArgumentException $e) {
            $this->raise($request, ['prompt' => $e->getMessage()]);
        }

        $this->audit($request, 'document_prompt.prompt.revised', $template, ['section' => $section->key, 'version' => $copy->version, 'from_version' => $from->version]);

        return $this->done($request, 'Draf v'.$copy->version.' dibuat dari v'.$from->version.'. Versi aktif tidak berubah.', $this->url($template, $section->key, 'prompt', $copy->id));
    }

    public function promptActivate(Request $request, string $template, string $section, string $prompt): RedirectResponse|JsonResponse
    {
        $this->requireStorage($request);
        [$template, $section, $target] = $this->resolve($template, $section, $prompt);

        try {
            $result = $this->docs->activate($section, $target);
        } catch (InvalidArgumentException $e) {
            $this->raise($request, ['prompt' => $e->getMessage()]);
        }

        if ($result['changed']) {
            $this->audit($request, 'document_prompt.prompt.activated', $template, ['section' => $section->key, 'version' => $target->version, 'previous_version' => $result['previous']?->version]);
        }

        return $this->done($request, ($result['changed'] ? '"'.$section->title.'": v'.$target->version.' sekarang aktif. Bagian lain tidak berubah.' : 'v'.$target->version.' memang sudah aktif.'), $this->url($template, $section->key, 'prompt', $target->id));
    }

    public function promptArchive(Request $request, string $template, string $section, string $prompt): RedirectResponse|JsonResponse
    {
        $this->requireStorage($request);
        [$template, $section, $target] = $this->resolve($template, $section, $prompt);

        try {
            $this->docs->archiveDraft($section, $target);
        } catch (InvalidArgumentException $e) {
            $this->raise($request, ['prompt' => $e->getMessage()], null, 409);
        }

        $this->audit($request, 'document_prompt.prompt.archived', $template, ['section' => $section->key, 'version' => $target->version]);

        return $this->done($request, 'Draf v'.$target->version.' diarsipkan. Isinya tetap tersimpan.', $this->url($template, $section->key, 'versi', $target->id));
    }

    /*
    |--------------------------------------------------------------------------
    | Helper
    |--------------------------------------------------------------------------
    */

    private function savePrompt(Request $request, string $template, string $section, ?string $prompt): RedirectResponse|JsonResponse
    {
        $this->requireStorage($request);
        $template = $this->template($template);
        $section = $this->section($template, $section);
        $target = $prompt === null ? null : $this->prompt($section, $prompt);

        $texts = [];

        foreach (DocumentPrompts::PROMPT_FIELDS as $key) {
            if (is_string($request->input($key))) {
                $texts[$key] = str_replace(["\r\n", "\r"], "\n", $request->input($key));
            }
        }

        $request->merge($texts);

        $data = $request->validate([
            'task_prompt' => ['required', 'string', 'max:30000'],
            'system_prompt' => ['nullable', 'string', 'max:20000'],
            'retrieval_instructions' => ['nullable', 'string', 'max:20000'],
            'output_instructions' => ['nullable', 'string', 'max:20000'],
        ], ['task_prompt.required' => 'Isi prompt wajib diisi.', 'task_prompt.max' => 'Isi prompt maksimal 30.000 karakter.']);

        try {
            $saved = $this->docs->saveDraft($section, $target, $data, $this->userId($request));
        } catch (InvalidArgumentException $e) {
            $this->raise($request, ['task_prompt' => $e->getMessage()], null, str_contains($e->getMessage(), 'dikunci') ? 409 : 422);
        }

        if ($saved['created'] || $saved['changed'] !== []) {
            $this->audit($request, $saved['created'] ? 'document_prompt.prompt.created' : 'document_prompt.prompt.updated', $template, ['section' => $section->key, 'version' => $saved['prompt']->version, 'changed' => $saved['changed']]);
        }

        $message = $saved['created'] ? 'Draf v1 untuk "'.$section->title.'" dibuat. Belum aktif.' : ($saved['changed'] === [] ? 'Tidak ada perubahan untuk disimpan.' : 'Draf v'.$saved['prompt']->version.' disimpan. Belum aktif.');

        return $this->done($request, $message, $this->url($template, $section->key, 'prompt', $saved['prompt']->id));
    }

    private function setStatus(DocumentPromptTemplate $template, string $status, string $message): string
    {
        $template->update(['status' => $status, 'activated_at' => $status === 'draft' ? null : $template->activated_at]);

        return $message;
    }

    /** Baris daftar: kesiapan, status tampilan, dan hitungan bagian. */
    private function row(DocumentPromptTemplate $template): array
    {
        $readiness = $this->docs->readiness($template);
        $enabled = $template->sections->where('is_enabled', true);

        return [
            'template' => $template,
            'readiness' => $readiness,
            'state' => $this->docs->state($template, $readiness),
            'enabled' => $enabled->count(),
            'with_prompt' => $enabled->filter(fn ($s) => $s->activePrompt?->status === 'active')->count(),
        ];
    }

    /** Ringkasan status instruksi sebuah bagian untuk daftar bagian di editor. */
    private function sectionInfo(DocumentPromptSection $section): array
    {
        $active = $section->activePrompt?->status === 'active' ? $section->activePrompt : null;
        $latest = $this->versions->group(null, $section->promptName());
        $draft = (clone $latest)->where('status', 'draft')->orderByDesc('version')->first();

        return ['active' => $active, 'draft' => $draft, 'count' => (clone $latest)->count()];
    }

    private function templateData(Request $request, ?DocumentPromptTemplate $current): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'document_type' => ['required', Rule::in(array_keys(DocumentPrompts::TYPES))],
            'type_label' => ['nullable', 'required_if:document_type,other', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:2000'],
            'language' => ['required', Rule::in(array_keys(DocumentPrompts::LANGUAGES))],
            'citation_style' => ['required', Rule::in(DocumentPrompts::CITATION_STYLES)],
            'qa_mode' => ['required', Rule::in(array_keys(DocumentPrompts::QA_MODES))],
        ], ['name.required' => 'Nama template wajib diisi.', 'type_label.required_if' => 'Isi nama jenis dokumen.']);

        $data['name'] = trim((string) preg_replace('/\s+/u', ' ', $data['name']));
        $data['slug'] = $this->docs->slug($data['name']);
        $data['type_label'] = $data['document_type'] === 'other' ? ($data['type_label'] ?? null) : null;

        if ($data['slug'] === '') {
            $this->raise($request, ['name' => 'Nama template harus memuat huruf atau angka.']);
        }

        $taken = DocumentPromptTemplate::query()->where('slug', $data['slug'])->when($current, fn ($q) => $q->whereKeyNot($current->id))->first();

        if ($taken) {
            $this->raise($request, ['name' => 'Sudah ada template bernama "'.$taken->name.'". Gunakan nama lain atau salin template itu.'], $this->url($taken));
        }

        return $data;
    }

    private function requireStorage(Request $request): void
    {
        if (! $this->docs->available()) {
            $this->raise($request, ['storage' => 'Penyimpanan Prompt Dokumen belum disiapkan di database ini. Migration perlu disetujui dan dijalankan terlebih dahulu.'], null, 503);
        }
    }

    private function template(string $id): DocumentPromptTemplate
    {
        return DocumentPromptTemplate::query()->findOrFail((int) $id);
    }

    private function section(DocumentPromptTemplate $template, string $id): DocumentPromptSection
    {
        return $template->sections()->findOrFail((int) $id);
    }

    private function prompt(DocumentPromptSection $section, string $id): PromptTemplate
    {
        return $this->versions->group(null, $section->promptName())->findOrFail((int) $id);
    }

    /** @return array{0: DocumentPromptTemplate, 1: DocumentPromptSection, 2: PromptTemplate} */
    private function resolve(string $template, string $section, string $prompt): array
    {
        $template = $this->template($template);
        $section = $this->section($template, $section);

        return [$template, $section, $this->prompt($section, $prompt)];
    }

    private function url(DocumentPromptTemplate $template, ?string $section = null, ?string $tab = null, ?int $version = null): string
    {
        return route('admin.prompt-documents.show', array_filter(['template' => $template->id, 'bagian' => $section, 'tab' => $tab && $tab !== 'prompt' ? $tab : null, 'v' => $version]));
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

    private function audit(Request $request, string $action, DocumentPromptTemplate $template, array $extra = []): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        AuditLog::create([
            'user_id' => $this->userId($request),
            'action' => $action,
            'auditable_type' => DocumentPromptTemplate::class,
            'auditable_id' => $template->id,
            'metadata_json' => ['name' => $template->name, 'status' => $template->status] + $extra,
            'ip_address' => $request->ip(),
        ]);
    }
}
