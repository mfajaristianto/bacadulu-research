<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Document;
use App\Models\ExternalPublicationCategory;
use App\Services\Discovery\AnalysisContract;
use App\Services\Discovery\SourceDiscovery;
use App\Services\FullText\FullTextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Referensi paling relevan (Pustaka Internal + Sumber Eksternal) TANPA AI. Hanya membaca; tidak membuat AiRun,
 * tidak memanggil provider, tidak menulis ke database riset.
 */
class ReferenceDiscoveryController extends Controller
{
    public function __construct(private SourceDiscovery $discovery, private AnalysisContract $contract) {}

    public function index(Request $request): View
    {
        $f = $this->input($request);
        $categories = Category::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']);
        $result = null;
        $top = [];
        $page = null;
        $stats = null;

        if ($f['category_id'] && $f['q'] !== '') {
            $result = $this->discovery->discover($f['category_id'], $f['q'], $f['filters']);
            $topN = (int) config('research.discovery.top_n', 5);
            $top = array_slice($result['results'], 0, $topN);

            if ($f['view'] === 'all') {
                $size = (int) config('research.discovery.page_size', 10);
                $current = max(1, (int) $request->query('page', 1));
                $page = (new LengthAwarePaginator(array_slice($result['results'], ($current - 1) * $size, $size), count($result['results']), $size, $current, ['path' => $request->url(), 'query' => $request->except('page')]))->withQueryString();
            }
            if ($result['results'] === []) {
                $stats = $this->scopeStats($f['category_id']);
            }
        }

        // Status full-text (tampilan saja; TIDAK memengaruhi skor atau urutan relevansi).
        $shown = array_merge($top, $page ? $page->items() : []);
        $ft = app(FullTextService::class)->statusFor(collect($shown)->where('kind', 'external')->pluck('id')->unique()->values()->all());

        // Evidence Bundle: bukti nyata untuk lima teratas (tanpa AI, tanpa unduhan). Relevansi tidak diubah.
        $bundle = $result && $result['results'] !== [] && (($page?->currentPage() ?? 1) === 1)
            ? app(\App\Services\Evidence\EvidenceBundleBuilder::class)->build((int) $f['category_id'], $f['q'], $f['filters'], $result)->toArray()
            : null;

        return view('research.references.index', [
            'bundle' => $bundle, 'synth' => $bundle ? app(\App\Services\Synthesis\GroundedSynthesisService::class)->availability() : null, 'f' => $f, 'categories' => $categories, 'result' => $result, 'top' => $top, 'page' => $page, 'stats' => $stats, 'ft' => $ft,
            'ai' => $this->contract->aiStatus(), 'actions' => $this->contract->actions(),
            'terms' => $result ? array_values(array_unique([...$result['analysis']['core'], ...$result['analysis']['generic']])) : [],
            'rankOffset' => $page ? ($page->currentPage() - 1) * $page->perPage() : 0,
        ]);
    }

    public function show(Request $request, string $kind, int $id): View
    {
        $f = $this->input($request);
        $item = $this->discovery->describe($kind, $id, $f['category_id'], $f['q']);
        abort_if($item === null, 404);
        $a = $f['q'] !== '' ? app(\App\Services\Discovery\QueryAnalyzer::class)->analyze($f['q']) : null;

        $ft = $item['kind'] === 'external' ? app(FullTextService::class)->statusFor([(int) $item['id']]) : [];

        return view('research.references.show', ['r' => $item, 'f' => $f, 'ft' => $ft, 'terms' => $a ? array_values(array_unique([...$a['core'], ...$a['generic']])) : []]);
    }

    /**
     * Pra-pemeriksaan analisis AI. Urutan: validasi -> muat ulang sumber dari server (klien tidak dipercaya) -> kualitas bukti ->
     * provider AI -> konfirmasi eksplisit. Tidak ada pemanggilan AI di sini; AI tidak pernah jalan hanya karena referensi tampil.
     */
    public function analysis(Request $request): View|RedirectResponse
    {
        $data = $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'q' => ['required', 'string', 'max:500'],
            'prompt_template_id' => ['required', 'integer'],
            'keys' => ['required', 'array', 'min:1', 'max:10'],
            'keys.*' => ['string', 'regex:/^(ext|doc):\d+$/'],
            'confirm' => ['nullable', 'boolean'],
        ], ['keys.required' => 'Pilih minimal satu referensi untuk dianalisis.', 'keys.min' => 'Pilih minimal satu referensi untuk dianalisis.']);

        $action = collect($this->contract->actions())->firstWhere('prompt_template_id', (int) $data['prompt_template_id']);
        if (! $action) {
            return back()->withErrors(['prompt_template_id' => 'Jenis analisis tidak tersedia di Katalog Alat AI.'])->withInput();
        }

        $result = $this->discovery->discover((int) $data['category_id'], $data['q'], $this->input($request)['filters']);
        $byKey = [];
        foreach ($result['results'] as $r) {
            $byKey[$r['key']] = $r;
            isset($r['alt_key']) && $byKey[$r['alt_key']] = $r;
        }
        $selected = collect($data['keys'])->map(fn ($k) => $byKey[$k] ?? null)->filter()->unique('key')->values()->all();
        if ($selected === []) {
            return back()->withErrors(['keys' => 'Referensi yang dipilih tidak ditemukan pada hasil pencarian saat ini. Muat ulang pencarian.'])->withInput();
        }

        $contract = $this->contract->build($selected);
        $ai = $this->contract->aiStatus();
        $state = ! $ai['available'] ? 'unavailable' : (($data['confirm'] ?? false) ? 'confirmed' : 'confirm');

        // Satu dokumen Pustaka: serahkan ke alur riset yang sudah ada (pengguna tetap menekan kirim sendiri di Workspace).
        if ($state === 'confirmed' && count($selected) === 1 && $selected[0]['kind'] === 'internal') {
            return redirect()->route('workspace', ['source' => $selected[0]['id'], 'prompt' => $action['prompt_template_id']]);
        }

        return view('research.references.analysis', [
            'state' => $state, 'ai' => $ai, 'action' => $action, 'contract' => $contract, 'selected' => $selected, 'data' => $data,
            'missing' => count($data['keys']) - count($selected),
        ]);
    }

    /** @return array{category_id: ?int, q: string, view: string, filters: array{year: ?int, language: ?string, type: ?string}} */
    private function input(Request $request): array
    {
        $v = $request->validate([
            'category_id' => ['nullable', 'integer'], 'category' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:500'], 'message' => ['nullable', 'string', 'max:500'],
            'year' => ['nullable', 'integer', 'between:1000,2200'], 'language' => ['nullable', 'string', 'regex:/^[a-zA-Z]{2,3}$/'],
            'type' => ['nullable', 'in:article,book,chapter,proceedings,dataset,thesis'], 'view' => ['nullable', 'in:top,all'],
        ]);

        return [
            'category_id' => ($v['category_id'] ?? $v['category'] ?? null) ? (int) ($v['category_id'] ?? $v['category']) : null,
            'q' => trim((string) ($v['q'] ?? $v['message'] ?? '')),
            'view' => $v['view'] ?? 'top',
            'filters' => ['year' => ($v['year'] ?? null) ? (int) $v['year'] : null, 'language' => ($v['language'] ?? null) ? strtolower($v['language']) : null, 'type' => $v['type'] ?? null],
        ];
    }

    /** Ukuran korpus kategori untuk empty state yang jujur (di-cache; tabel besar tidak dihitung ulang tiap permintaan). */
    private function scopeStats(int $categoryId): array
    {
        return Cache::remember("discovery:stats:{$categoryId}", 600, fn () => [
            'internal' => Document::query()->eligibleForResearch()->where('category_id', $categoryId)->count(),
            'external' => ExternalPublicationCategory::query()->where('category_id', $categoryId)->count(),
        ]);
    }
}
