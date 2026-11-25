<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\DocumentPromptTemplate;
use App\Models\DocumentTemplate;
use App\Models\PromptTemplate;
use App\Services\Documents\PrintDocumentModel;
use App\Services\Documents\PrintDocxWriter;
use App\Services\Documents\PrintLayout;
use App\Services\Documents\PrintLayoutEngine;
use App\Services\Documents\PrintPdfWriter;
use App\Services\Documents\PrintPreview;
use App\Services\Documents\PrintTemplates;
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
use Throwable;

/*
 * Template & Format Cetak (admin). Halaman dan rute "Template Dokumen" yang ada dipertahankan dan dijadikan dasar: template, kode, jenis dokumen, default per jenis,
 * dan sections pemetaan hasil riset tetap milik generator. Modul ini menambah layout cetak berversi di atas template itu (lihat PrintTemplates).
 *
 * Aturan dijaga di service dan di sini, bukan hanya di UI: hanya draf yang boleh diedit, aktivasi per template dengan pemeriksaan "tidak dapat diekspor"
 * yang memblokir, tidak ada hapus, template baru selalu draf, preview hanya membaca dan tidak menulis apa pun. Preview PDF/DOCX dibuat on the fly dari
 * contoh berlabel "CONTOH TAMPILAN"; tidak ada berkas yang disimpan dan nama berkas tetap, sehingga tidak ada jalur untuk path traversal.
 */
class DocumentTemplateController extends Controller
{
    private const PER_PAGE = 12;

    private const STATUS_FILTERS = ['draft' => 'Draf', 'active' => 'Aktif', 'legacy' => 'Format lama', 'archived' => 'Diarsipkan'];

    private const SAMPLES = ['all' => 'Semua halaman', 'front' => 'Halaman judul dan daftar isi', 'body' => 'Halaman isi', 'last' => 'Halaman terakhir (daftar pustaka)'];

    public function __construct(private readonly PrintTemplates $print, private readonly PromptVersions $diff) {}

    /*
    |--------------------------------------------------------------------------
    | Daftar dan buat
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $f = $request->validate([
            'search' => ['nullable', 'string', 'max:160'],
            'jenis' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(array_keys(self::STATUS_FILTERS))],
            'sort' => ['nullable', Rule::in(['updated', 'name'])],
            'baru' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $shared = ['types' => $this->types(), 'statusFilters' => self::STATUS_FILTERS, 'categories' => PrintLayout::CATEGORIES];

        $all = DocumentTemplate::query()->withCount('generations')->get()->map(fn (DocumentTemplate $t) => $this->row($t));

        if ($request->boolean('baru')) {
            return view('admin.document-templates.index', $shared + [
                'mode' => 'new', 'presets' => PrintLayout::presets(), 'existing' => $all->map(fn ($r) => $r['template'])->sortBy('name')->values(),
            ]);
        }

        $stats = ['total' => $all->count(), 'active' => $all->where('state.key', 'active')->count(), 'draft' => $all->where('state.key', 'draft')->count(), 'legacy' => $all->where('state.key', 'legacy')->count(), 'archived' => $all->where('state.key', 'archived')->count()];

        $rows = $all
            ->when(filled($f['search'] ?? null), function (Collection $c) use ($f) {
                $needle = mb_strtolower(trim($f['search']));

                return $c->filter(fn ($r) => str_contains(mb_strtolower($r['template']->name.' '.$r['template']->code.' '.$r['template']->description), $needle));
            })
            ->when(filled($f['jenis'] ?? null), fn (Collection $c) => $c->where('template.document_type', $f['jenis']))
            ->when(filled($f['status'] ?? null), fn (Collection $c) => $c->where('state.key', $f['status']));

        $rows = (($f['sort'] ?? 'updated') === 'name' ? $rows->sortBy(fn ($r) => mb_strtolower($r['template']->name)) : $rows->sortByDesc(fn ($r) => $r['template']->updated_at))->values();

        $page = LengthAwarePaginator::resolveCurrentPage();
        $paged = new LengthAwarePaginator($rows->forPage($page, self::PER_PAGE)->values(), $rows->count(), self::PER_PAGE, $page, ['path' => route('admin.document-templates.index'), 'query' => $request->except('page')]);

        return view('admin.document-templates.index', $shared + [
            'mode' => 'list', 'stats' => $stats, 'rows' => $paged,
            'filtering' => filled($f['search'] ?? null) || filled($f['jenis'] ?? null) || filled($f['status'] ?? null) || ($f['sort'] ?? 'updated') !== 'updated',
        ]);
    }

    /** Rute lama: halaman buat sekarang menyatu di daftar. */
    public function create(): RedirectResponse
    {
        return redirect()->route('admin.document-templates.index', ['baru' => 1]);
    }

    /** Template baru (selalu draf v1): dari preset, salinan template yang ada, atau dari awal. */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'source' => ['required', Rule::in(['preset', 'copy', 'blank'])],
            'name' => ['required', 'string', 'max:180'],
            'document_type' => ['nullable', 'string', Rule::in($this->types())],
            'description' => ['nullable', 'string', 'max:2000'],
            'preset' => ['nullable', 'string', 'max:60'],
            'copy_from' => ['nullable', 'integer', 'exists:document_templates,id'],
        ], ['name.required' => 'Nama format wajib diisi.']);

        $name = trim((string) preg_replace('/\s+/u', ' ', $data['name']));
        $user = $this->userId($request);

        if ($data['source'] === 'copy') {
            $from = DocumentTemplate::query()->find($data['copy_from'] ?? 0);

            if (! $from) {
                $this->raise($request, ['copy_from' => 'Pilih template yang akan disalin.']);
            }

            $template = $this->print->duplicate($from, $name, $user);
            $this->audit($request, 'document_template.duplicated', $template, ['from' => $from->id]);
            $message = 'Template "'.$name.'" disalin sebagai draf v1. Belum aktif.';
        } else {
            $preset = $data['source'] === 'preset' ? (PrintLayout::presets()[$data['preset'] ?? ''] ?? null) : null;

            if ($data['source'] === 'preset' && ! $preset) {
                $this->raise($request, ['preset' => 'Preset tidak ditemukan.']);
            }

            $type = $data['document_type'] ?? ($preset['document_type'] ?? null);

            if (! in_array($type, $this->types(), true)) {
                $this->raise($request, ['document_type' => 'Pilih jenis dokumen.']);
            }

            $layout = PrintLayout::normalize($preset['layout'] ?? PrintLayout::defaults())['layout'];
            $template = $this->print->create($name, $type, $data['description'] ?? $preset['description'] ?? null, $layout, $user);
            $this->audit($request, 'document_template.created', $template, ['source' => $data['source'], 'preset' => $data['preset'] ?? null]);
            $message = 'Template "'.$name.'" dibuat sebagai draf v1 dan belum aktif. Periksa semua pengaturan dan pratinjau, lalu aktifkan.';
        }

        return $this->done($request, $message, $this->url($template));
    }

    /*
    |--------------------------------------------------------------------------
    | Editor
    |--------------------------------------------------------------------------
    */

    public function edit(Request $request, DocumentTemplate $documentTemplate)
    {
        $f = $request->validate([
            'tab' => ['nullable', Rule::in(['format', 'versi'])],
            'v' => ['nullable', 'integer'],
            'a' => ['nullable', 'integer'],
            'b' => ['nullable', 'integer'],
            'contoh' => ['nullable', Rule::in(array_keys(self::SAMPLES))],
            'struktur' => ['nullable', 'integer'],
        ]);

        $t = $documentTemplate;
        $versions = $this->print->versions($t);
        $print = $this->print->print($t);
        $selected = $print ? (collect($versions)->firstWhere('v', $f['v'] ?? null) ?? collect($versions)->firstWhere('v', $print['active']) ?? ($versions[0] ?? null)) : null;
        $layout = $selected ? PrintLayout::hydrate($selected['layout']) : null;
        $sample = $f['contoh'] ?? 'all';
        $structureId = $f['struktur'] ?? null;
        $compare = null;

        if (filled($f['a'] ?? null) && filled($f['b'] ?? null)) {
            $old = collect($versions)->firstWhere('v', (int) $f['a']);
            $new = collect($versions)->firstWhere('v', (int) $f['b']);

            if ($old && $new && $old['v'] !== $new['v']) {
                $text = fn (array $v) => tap(new PromptTemplate, fn ($m) => $m->task_prompt = $this->describe(PrintLayout::hydrate($v['layout'])));
                $compare = ['old' => $old, 'new' => $new, 'fields' => $this->diff->compare($text($old), $text($new), ['task_prompt' => 'Pengaturan layout'])];
            }
        }

        return view('admin.document-templates.index', [
            'mode' => 'editor',
            'template' => $t,
            'state' => $this->print->state($t),
            'versions' => $versions,
            'selected' => $selected,
            'layout' => $layout,
            'lock' => $selected ? $this->print->lockReason($selected) : null,
            'tab' => $f['tab'] ?? 'format',
            'compare' => $compare,
            'types' => $this->types(),
            'categories' => PrintLayout::CATEGORIES,
            'sizes' => PrintLayout::SIZES,
            'units' => array_keys(PrintLayout::UNITS),
            'fonts' => PrintLayout::FONTS,
            'align' => PrintLayout::ALIGN,
            'numbering' => PrintLayout::NUMBERING,
            'chapterStart' => PrintLayout::CHAPTER_START,
            'pnPositions' => PrintLayout::PAGE_NUMBER_POSITIONS,
            'pnFormats' => PrintLayout::PAGE_NUMBER_FORMATS,
            'tableBorders' => PrintLayout::TABLE_BORDERS,
            'support' => PrintLayout::SUPPORT,
            'tokens' => PrintLayout::TOKENS,
            'samples' => self::SAMPLES,
            'sample' => $sample,
            'structureId' => $structureId,
            'structures' => $this->structures(),
            'preview' => $layout ? $this->renderPreview($layout, $sample, $structureId) : null,
            'legacyFormat' => is_array($t->schema_json['format'] ?? null) ? $t->schema_json['format'] : [],
            'sections' => is_array($t->schema_json['sections'] ?? null) ? $t->schema_json['sections'] : [],
            'generations' => $t->generations()->count(),
            'history' => Schema::hasTable('audit_logs')
                ? AuditLog::query()->with('user:id,name')->where('auditable_type', DocumentTemplate::class)->where('auditable_id', $t->id)->latest('id')->limit(60)->get()
                : collect(),
            'previewUrl' => route('admin.document-templates.preview', $t),
        ]);
    }

    /** Simpan draf. Versi aktif/arsip ditolak di service, apa pun yang dikirim form. */
    public function update(Request $request, DocumentTemplate $documentTemplate): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'v' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:180'],
            'document_type' => ['required', Rule::in($this->types())],
            'description' => ['nullable', 'string', 'max:2000'],
            'layout' => ['required', 'array'],
        ], ['name.required' => 'Nama format wajib diisi.', 'layout.required' => 'Pengaturan layout tidak terkirim.']);

        $normalized = PrintLayout::normalize($data['layout']);

        if ($normalized['errors'] !== []) {
            $this->raise($request, collect($normalized['errors'])->mapWithKeys(fn ($m, $k) => ['layout.'.$k => $m])->all());
        }

        try {
            $changed = $this->print->saveDraft($documentTemplate, (int) $data['v'], [
                'name' => trim((string) preg_replace('/\s+/u', ' ', $data['name'])), 'document_type' => $data['document_type'],
                'description' => filled($data['description'] ?? null) ? trim((string) $data['description']) : null,
            ], $normalized['layout'], $this->userId($request));
        } catch (InvalidArgumentException $e) {
            $this->raise($request, ['name' => $e->getMessage()], 409);
        }

        if ($changed !== []) {
            $this->audit($request, 'document_template.layout_saved', $documentTemplate, ['layout_version' => (int) $data['v'], 'changed' => $changed]);
        }

        return $this->done($request, $changed === [] ? 'Tidak ada perubahan untuk disimpan.' : 'Draf v'.(int) $data['v'].' disimpan. Belum aktif.', $this->url($documentTemplate, (int) $data['v']));
    }

    /** Arsipkan template (rute lama destroy): tidak dihapus; dokumen lama tidak berubah. */
    public function destroy(Request $request, DocumentTemplate $documentTemplate): RedirectResponse|JsonResponse
    {
        try {
            $this->print->archive($documentTemplate, $this->userId($request));
        } catch (InvalidArgumentException $e) {
            $this->raise($request, ['archive' => $e->getMessage()], 409);
        }

        $this->audit($request, 'document_template.deactivated', $documentTemplate);

        return $this->done($request, 'Template diarsipkan. Semua versi tetap tersimpan dan dokumen lama tidak berubah.', $this->url($documentTemplate));
    }

    public function restore(Request $request, DocumentTemplate $documentTemplate): RedirectResponse|JsonResponse
    {
        $this->print->restore($documentTemplate, $this->userId($request));
        $this->audit($request, 'document_template.restored', $documentTemplate);

        return $this->done($request, 'Template dipulihkan.', $this->url($documentTemplate));
    }

    public function setDefault(Request $request, DocumentTemplate $documentTemplate): RedirectResponse|JsonResponse
    {
        try {
            $this->print->setDefault($documentTemplate, $this->userId($request));
        } catch (InvalidArgumentException $e) {
            $this->raise($request, ['default' => $e->getMessage()], 409);
        }

        $this->audit($request, 'document_template.default_set', $documentTemplate);

        return $this->done($request, 'Template ini menjadi default untuk jenis "'.$documentTemplate->document_type.'".', $this->url($documentTemplate));
    }

    public function adopt(Request $request, DocumentTemplate $documentTemplate): RedirectResponse|JsonResponse
    {
        try {
            $this->print->adopt($documentTemplate, $this->userId($request));
        } catch (InvalidArgumentException $e) {
            $this->raise($request, ['adopt' => $e->getMessage()], 409);
        }

        $this->audit($request, 'document_template.layout_adopted', $documentTemplate);

        return $this->done($request, 'Draf v1 dibuat dari format lama. Template tetap memakai format lama sampai Anda mengaktifkan v1.', $this->url($documentTemplate, 1));
    }

    public function revise(Request $request, DocumentTemplate $documentTemplate, int $version): RedirectResponse|JsonResponse
    {
        try {
            $new = $this->print->revise($documentTemplate, $version, $this->userId($request));
        } catch (InvalidArgumentException $e) {
            $this->raise($request, ['revise' => $e->getMessage()], 404);
        }

        $this->audit($request, 'document_template.layout_revised', $documentTemplate, ['layout_version' => $new, 'from_version' => $version]);

        return $this->done($request, 'Draf v'.$new.' dibuat dari v'.$version.'. Versi aktif tidak berubah.', $this->url($documentTemplate, $new));
    }

    public function activate(Request $request, DocumentTemplate $documentTemplate, int $version): RedirectResponse|JsonResponse
    {
        try {
            $result = $this->print->activate($documentTemplate, $version, $this->userId($request));
        } catch (InvalidArgumentException $e) {
            $this->raise($request, ['activate' => $e->getMessage()], 409);
        }

        if ($result['changed']) {
            $this->audit($request, 'document_template.layout_activated', $documentTemplate, ['layout_version' => $version, 'previous_version' => $result['previous']]);
        }

        return $this->done($request, $result['changed'] ? 'v'.$version.' sekarang aktif'.($result['previous'] ? ' (menggantikan v'.$result['previous'].', yang tetap tersimpan)' : '').'. Berlaku untuk dokumen baru; dokumen yang sudah dibuat tidak berubah.' : 'v'.$version.' memang sudah aktif.', $this->url($documentTemplate, $version));
    }

    public function archiveVersion(Request $request, DocumentTemplate $documentTemplate, int $version): RedirectResponse|JsonResponse
    {
        try {
            $this->print->archiveVersion($documentTemplate, $version);
        } catch (InvalidArgumentException $e) {
            $this->raise($request, ['archive' => $e->getMessage()], 409);
        }

        $this->audit($request, 'document_template.layout_archived', $documentTemplate, ['layout_version' => $version]);

        return $this->done($request, 'Draf v'.$version.' diarsipkan. Isinya tetap tersimpan.', $this->url($documentTemplate, $version, 'versi'));
    }

    /*
    |--------------------------------------------------------------------------
    | Pratinjau (hanya membaca)
    |--------------------------------------------------------------------------
    */

    /** Pratinjau tata letak dari nilai form yang sedang diedit (belum disimpan), atau dari versi tersimpan. */
    public function preview(Request $request, DocumentTemplate $documentTemplate): JsonResponse
    {
        $in = $request->validate([
            'layout' => ['nullable', 'array'],
            'v' => ['nullable', 'integer'],
            'contoh' => ['nullable', Rule::in(array_keys(self::SAMPLES))],
            'struktur' => ['nullable', 'integer'],
        ]);

        if (isset($in['layout'])) {
            $n = PrintLayout::normalize($in['layout']);

            if ($n['errors'] !== []) {
                return response()->json(['ok' => false, 'message' => reset($n['errors']), 'errors' => $n['errors'], 'check' => null], 422);
            }

            $layout = $n['layout'];
        } else {
            $v = $this->print->version($documentTemplate, (int) ($in['v'] ?? 0));
            abort_unless($v, 404);
            $layout = PrintLayout::hydrate($v['layout']);
        }

        return response()->json(['ok' => true] + $this->renderPreview($layout, $in['contoh'] ?? 'all', $in['struktur'] ?? null));
    }

    /** PDF nyata dari contoh (versi tersimpan): hasil renderer yang sama dengan ekspor. Tidak disimpan. */
    public function previewPdf(Request $request, DocumentTemplate $documentTemplate, int $version)
    {
        [$layout, $model] = $this->sampleModel($documentTemplate, $version, $request->integer('struktur') ?: null);
        $pdf = PrintPdfWriter::render(PrintLayoutEngine::run($model, $layout), $model['title']);

        return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="contoh-tata-letak.pdf"', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'no-store']);
    }

    /** DOCX nyata dari contoh (versi tersimpan) untuk dibuka di Word. Dibuat di berkas sementara lalu dihapus. */
    public function previewDocx(Request $request, DocumentTemplate $documentTemplate, int $version)
    {
        [$layout, $model] = $this->sampleModel($documentTemplate, $version, $request->integer('struktur') ?: null);
        $tmp = tempnam(sys_get_temp_dir(), 'bdp');

        try {
            PrintDocxWriter::render($model, $layout, $tmp);
            $bytes = (string) file_get_contents($tmp);
        } finally {
            @unlink($tmp);
        }

        return response($bytes, 200, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'Content-Disposition' => 'attachment; filename="contoh-tata-letak.docx"', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'no-store']);
    }

    /*
    |--------------------------------------------------------------------------
    | Helper
    |--------------------------------------------------------------------------
    */

    private function sampleModel(DocumentTemplate $t, int $version, ?int $structureId): array
    {
        $v = $this->print->version($t, $version);
        abort_unless($v, 404);
        $layout = PrintLayout::hydrate($v['layout']);

        return [$layout, PrintDocumentModel::build($this->sampleContent($structureId), $layout)];
    }

    private function sampleContent(?int $structureId): array
    {
        $structure = $structureId ? $this->structureOf($structureId) : null;

        return PrintDocumentModel::sample('chapter', $structure);
    }

    private function renderPreview(array $layout, string $sample, ?int $structureId): array
    {
        $model = PrintDocumentModel::build($this->sampleContent($structureId), $layout);
        $laid = PrintLayoutEngine::run($model, $layout);
        $only = match ($sample) {
            'front' => ['title', 'front'], 'body' => ['body'], default => [],
        };

        if ($sample === 'last') {
            $laid['pages'] = [end($laid['pages'])];
            $only = [];
        }

        $out = PrintPreview::render($laid, $layout, $only);

        return ['html' => $out['html'], 'pages' => count($laid['pages']), 'shown' => $out['pages'], 'width_mm' => $out['width_mm'], 'height_mm' => $out['height_mm'], 'check' => PrintLayout::check($layout)];
    }

    /** @return list<array{id: int, name: string, type: string}> struktur dari Prompt Dokumen (Modul 4) untuk pratinjau; kosong bila tabelnya belum ada. */
    private function structures(): array
    {
        if (! Schema::hasTable('document_prompt_templates')) {
            return [];
        }

        return DocumentPromptTemplate::query()->orderBy('name')->get(['id', 'name', 'document_type'])->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'type' => $t->document_type])->all();
    }

    /** @return list<array{0: string, 1: string}>|null [judul, peran] bagian yang aktif, urut sesuai Prompt Dokumen. */
    private function structureOf(int $id): ?array
    {
        if (! Schema::hasTable('document_prompt_templates')) {
            return null;
        }

        $t = DocumentPromptTemplate::query()->with('sections')->find($id);

        return $t ? $t->sections->where('is_enabled', true)->sortBy('position')->map(fn ($s) => [(string) $s->title, (string) $s->role])->values()->all() : null;
    }

    private function row(DocumentTemplate $t): array
    {
        $state = $this->print->state($t);
        $v = $this->print->activeVersion($t) ?? ($this->print->versions($t)[0] ?? null);
        $layout = $v ? PrintLayout::hydrate($v['layout']) : null;
        $legacy = $layout === null;

        return [
            'template' => $t, 'state' => $state, 'version' => $v, 'active' => $this->print->activeVersion($t),
            'page' => $layout ? PrintLayout::SIZES[$layout['page']['size']]['label'].($layout['page']['size'] === 'custom' ? ' '.round(PrintLayout::pageMm($layout)[0]).'×'.round(PrintLayout::pageMm($layout)[1]).' mm' : '') : 'A4 (format lama)',
            'category' => $layout ? PrintLayout::CATEGORIES[$layout['category']] : null,
            'check' => $layout ? PrintLayout::check($layout) : null,
            'legacy' => $legacy,
        ];
    }

    /** Ringkasan layout sebagai baris teks tetap-urut untuk perbandingan versi. */
    private function describe(array $L): string
    {
        $flat = [];
        $walk = function (array $a, string $prefix) use (&$walk, &$flat): void {
            foreach ($a as $k => $v) {
                is_array($v) ? $walk($v, $prefix.$k.'.') : $flat[] = $prefix.$k.': '.(is_bool($v) ? ($v ? 'ya' : 'tidak') : (string) $v);
            }
        };
        $walk($L, '');

        return implode("\n", $flat);
    }

    private function types(): array
    {
        return array_values((array) config('research.documents.types', []));
    }

    private function url(DocumentTemplate $t, ?int $version = null, ?string $tab = null): string
    {
        return route('admin.document-templates.edit', array_filter(['documentTemplate' => $t->id, 'v' => $version, 'tab' => $tab && $tab !== 'format' ? $tab : null]));
    }

    private function userId(Request $request): ?int
    {
        return $request->session()->get('research_admin_user_id');
    }

    private function raise(Request $request, array $errors, int $status = 422): never
    {
        if ($request->expectsJson()) {
            throw new HttpResponseException(response()->json(['message' => reset($errors), 'errors' => array_map(fn ($m) => [$m], $errors)], $status));
        }

        throw ValidationException::withMessages($errors);
    }

    private function done(Request $request, string $message, string $url): RedirectResponse|JsonResponse
    {
        return $request->expectsJson() ? response()->json(['ok' => true, 'message' => $message, 'url' => $url]) : redirect($url)->with('success', $message);
    }

    private function audit(Request $request, string $action, DocumentTemplate $t, array $extra = []): void
    {
        try {
            AuditLog::create([
                'user_id' => $this->userId($request), 'action' => $action, 'auditable_type' => DocumentTemplate::class, 'auditable_id' => $t->id,
                'metadata_json' => ['code' => $t->code, 'document_type' => $t->document_type, 'version' => $t->version, 'is_default' => $t->is_default, 'status' => $t->status] + $extra,
                'ip_address' => $request->ip(),
            ]);
        } catch (Throwable) {
            //
        }
    }
}
