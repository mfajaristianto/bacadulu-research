<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\Research\AiProviderAnalytics;
use App\Services\Research\AiProviders;
use App\Services\Research\ResearchSettingsService;
use App\Services\Synthesis\GroundedProviderManager;
use App\Services\Synthesis\SynthesisBudget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/*
 * Provider & Analitik AI (admin). Membaca provider yang benar-benar terintegrasi (AiProviders), usage nyata dari run riset
 * (AiProviderAnalytics, sumber sama dengan Beranda), dan mengubah provider/model aktif lewat penyimpanan pengaturan yang
 * sudah ada (ResearchSettingsService::persist, daftar putih kunci). Kredensial tidak pernah dibaca ke view: hanya
 * boolean "terkonfigurasi". Tidak ada tes koneksi otomatis; hanya atas permintaan admin (POST) dan tanpa biaya token.
 */
class ProviderController extends Controller
{
    public function __construct(
        private readonly AiProviders $providers,
        private readonly AiProviderAnalytics $analytics,
        private readonly ResearchSettingsService $settings,
        private readonly GroundedProviderManager $synthesis,
    ) {}

    public function index(Request $request): View
    {
        $f = $request->validate([
            'periode' => ['nullable', Rule::in(array_keys(AiProviderAnalytics::PERIODS))],
            'dari' => ['nullable', 'date_format:Y-m-d'],
            'sampai' => ['nullable', 'date_format:Y-m-d'],
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['enabled', 'disabled', 'configured', 'missing'])],
            'sort' => ['nullable', Rule::in(['name', 'usage', 'cost', 'recent'])],
            'provider' => ['nullable', 'string', 'max:40'],
            'metrik' => ['nullable', Rule::in(['input', 'output', 'total', 'cost', 'runs'])],
        ]);

        $catalog = $this->providers->catalog();
        $range = $this->analytics->range($f['periode'] ?? '30d', $f['dari'] ?? null, $f['sampai'] ?? null);
        $data = $this->analytics->build($range, $catalog);
        $activeModel = trim((string) config('research.ai_model'));

        $rows = collect($data['providers'])->map(function (array $usage, string $id) use ($catalog, $activeModel) {
            $info = $catalog[$id] ?? ['id' => $id, 'name' => $id, 'kind' => 'legacy', 'kind_label' => 'Tidak lagi terintegrasi', 'logo' => null, 'needs_key' => false, 'billed' => false, 'env' => null];
            $status = isset($catalog[$id]) ? $this->providers->status($id) : ['configured' => false, 'enabled' => false, 'connection' => ['key' => 'na', 'label' => 'Tidak berlaku'], 'check' => null];
            // Model yang tampil: model aktif untuk provider yang sedang dipakai; selain itu model yang tercatat di run.
            $models = collect($usage['models'])->pluck('model')->all();
            $current = $status['enabled'] && $info['needs_key'] && $activeModel !== '' ? $activeModel : null;

            return ['info' => $info, 'status' => $status, 'usage' => $usage, 'current_model' => $current, 'models' => $models];
        });

        if (filled($f['search'] ?? null)) {
            $needle = mb_strtolower(trim($f['search']));
            $rows = $rows->filter(fn ($r) => str_contains(mb_strtolower($r['info']['name'].' '.$r['info']['id'].' '.$r['current_model'].' '.implode(' ', $r['models'])), $needle));
        }

        $rows = match ($f['status'] ?? null) {
            'enabled' => $rows->filter(fn ($r) => $r['status']['enabled']),
            'disabled' => $rows->filter(fn ($r) => ! $r['status']['enabled']),
            'configured' => $rows->filter(fn ($r) => $r['status']['configured']),
            'missing' => $rows->filter(fn ($r) => ! $r['status']['configured']),
            default => $rows,
        };

        $rows = match ($f['sort'] ?? 'name') {
            'usage' => $rows->sortByDesc(fn ($r) => $r['usage']['total'] ?? -1),
            'cost' => $rows->sortByDesc(fn ($r) => $r['usage']['cost']['usd'] ?? -1),
            'recent' => $rows->sortByDesc(fn ($r) => (string) $r['usage']['last']),
            // Provider nyata dulu, penyedia mock (uji) di bawahnya.
            default => $rows->sortBy(fn ($r) => ($r['info']['kind'] === 'mock' ? '1' : '0').mb_strtolower($r['info']['name'])),
        };

        $detailId = $f['provider'] ?? null;
        $all = $this->allRows($catalog, $data, $activeModel);
        $detail = $detailId ? ($all[$detailId] ?? null) : null;

        return view('admin.providers.index', [
            'rows' => $rows->values(),
            'data' => $data,
            'range' => $range,
            'periods' => AiProviderAnalytics::PERIODS,
            'filtering' => filled($f['search'] ?? null) || filled($f['status'] ?? null) || in_array($f['sort'] ?? null, ['usage', 'cost', 'recent'], true),
            'detail' => $detail,
            'metric' => $f['metrik'] ?? 'total',
            'tasks' => AiProviders::TASKS,
            'config' => [
                'provider' => strtolower((string) config('research.ai_provider', 'mock')),
                'model' => $activeModel,
                'timeout' => (int) config('research.ai.timeout'),
                'max_output_tokens' => (int) config('research.ai.max_output_tokens'),
            ],
            'configurable' => collect($catalog)->map(fn ($info, $id) => ['id' => $id, 'name' => $info['name'], 'configured' => $this->providers->isConfigured($id), 'needs_key' => $info['needs_key'], 'env' => $info['env']])->values(),
            'synth' => $this->synthData(),
            'now' => Carbon::now(),
        ]);
    }

    /** Sintesis berbasis bukti: status provider nyata, anggaran, dan laju. Kunci API hanya "terkonfigurasi" atau "belum": nilainya tidak pernah dibaca ke view. */
    private function synthData(): array
    {
        $s = (array) config('research.synthesis');
        $p = SynthesisBudget::prices();

        return [
            'status' => $this->synthesis->status(),
            'key_configured' => $this->synthesis->keyConfigured('openai'),
            'values' => [
                'provider' => $this->synthesis->name(), 'live_enabled' => (bool) $s['live_enabled'], 'model' => (string) $s['model'],
                'timeout' => (int) $s['timeout'], 'max_output_tokens' => (int) $s['max_output_tokens'], 'max_input_tokens' => (int) $s['max_input_tokens'],
                'price_input' => $p[0] ?? '', 'price_output' => $p[1] ?? '', 'max_cost' => (float) $s['max_cost_per_request_usd'], 'max_retries' => (int) $s['max_retries'],
                'user_daily' => (int) $s['user_requests_per_day'], 'global_daily' => (int) $s['global_requests_per_day'], 'per_minute' => (int) $s['requests_per_minute'],
            ],
            'providers' => ['mock' => 'Mock (pengujian, tanpa biaya)', 'openai' => 'OpenAI (berbayar)'],
        ];
    }

    /**
     * Konfigurasi sintesis nyata. Mengaktifkan mode live butuh SEMUA: provider nyata, kunci di server, model, dan kedua tarif dari admin
     * (harga tidak dikarang). Menyimpan konfigurasi TIDAK memanggil provider.
     */
    public function synthesis(Request $request): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'provider' => ['required', Rule::in(['mock', 'openai'])],
            'live_enabled' => ['nullable', 'boolean'],
            'model' => ['nullable', 'string', 'max:160', 'regex:'.AiProviders::MODEL_PATTERN],
            'timeout' => ['required', 'integer', 'min:10', 'max:120'],
            'max_output_tokens' => ['required', 'integer', 'min:256', 'max:8000'],
            'max_input_tokens' => ['required', 'integer', 'min:1000', 'max:100000'],
            'price_input' => ['nullable', 'numeric', 'gt:0', 'max:1000'],
            'price_output' => ['nullable', 'numeric', 'gt:0', 'max:1000'],
            'max_cost' => ['required', 'numeric', 'gt:0', 'max:5'],
            'max_retries' => ['required', 'integer', 'min:0', 'max:3'],
            'user_daily' => ['required', 'integer', 'min:0', 'max:1000'],
            'global_daily' => ['required', 'integer', 'min:0', 'max:10000'],
            'per_minute' => ['required', 'integer', 'min:1', 'max:120'],
        ], ['model.regex' => 'Nama model hanya boleh huruf, angka, titik, titik dua, garis miring, dan tanda hubung.']);

        $live = (bool) ($data['live_enabled'] ?? false);
        $errors = [];
        if ($live) {
            $data['provider'] === 'openai' || $errors['live_enabled'] = 'Mode live hanya dapat diaktifkan untuk provider nyata.';
            $this->synthesis->keyConfigured('openai') || $errors['live_enabled'] = 'Mode live belum bisa diaktifkan: OPENAI_API_KEY belum dikonfigurasi di server.';
            filled($data['model'] ?? null) || $errors['model'] = 'Model wajib diisi untuk mode live.';
            (filled($data['price_input'] ?? null) && filled($data['price_output'] ?? null)) || $errors['price_input'] = 'Tarif input dan output (USD per 1 juta token) wajib diisi admin untuk mode live; sistem tidak mengarang harga.';
        }
        if ($errors !== []) {
            throw \Illuminate\Validation\ValidationException::withMessages($errors);
        }

        $before = $this->settings->effectiveFlat();
        $this->settings->persist([
            'synthesis.provider' => $data['provider'], 'synthesis.live_enabled' => $live, 'synthesis.model' => $data['model'] ?? '',
            'synthesis.timeout' => $data['timeout'], 'synthesis.max_output_tokens' => $data['max_output_tokens'], 'synthesis.max_input_tokens' => $data['max_input_tokens'],
            'synthesis.price_input_per_mtok_usd' => (string) ($data['price_input'] ?? ''), 'synthesis.price_output_per_mtok_usd' => (string) ($data['price_output'] ?? ''),
            'synthesis.max_cost_per_request_usd' => $data['max_cost'], 'synthesis.max_retries' => $data['max_retries'], 'synthesis.user_requests_per_day' => $data['user_daily'],
            'synthesis.global_requests_per_day' => $data['global_daily'], 'synthesis.requests_per_minute' => $data['per_minute'],
        ], $request->session()->get('research_admin_user_id'));

        $after = $this->settings->effectiveFlat();
        $changed = array_keys(array_filter($after, fn ($v, $k) => str_starts_with($k, 'synthesis.') && ($before[$k] ?? null) !== $v, ARRAY_FILTER_USE_BOTH));
        if ($changed !== []) {
            $this->audit($request, 'ai.synthesis.updated', ['changed' => $changed, 'provider' => $after['synthesis.provider'], 'model' => $after['synthesis.model'], 'live_enabled' => (bool) $after['synthesis.live_enabled']]);
        }

        return redirect()->to(route('admin.providers.index').'#sintesis')->with('success', $changed === [] ? 'Tidak ada perubahan untuk disimpan.' : 'Konfigurasi sintesis disimpan. Tidak ada permintaan yang dikirim ke provider.');
    }


    /** Ubah provider/model aktif lewat penyimpanan pengaturan yang sudah ada. Riset yang sedang berjalan tidak berubah. */
    public function config(Request $request): JsonResponse|\Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'provider' => ['required', Rule::in(array_keys($this->providers->catalog()))],
            'model' => ['nullable', 'string', 'max:160', 'regex:'.AiProviders::MODEL_PATTERN],
            'timeout' => ['required', 'integer', 'min:10', 'max:300'],
            'max_output_tokens' => ['required', 'integer', 'min:256', 'max:32000'],
        ], [
            'model.regex' => 'Nama model hanya boleh huruf, angka, titik, titik dua, garis miring, dan tanda hubung.',
            'timeout.min' => 'Timeout minimal 10 detik.',
            'timeout.max' => 'Timeout maksimal 300 detik.',
            'max_output_tokens.min' => 'Batas token keluaran minimal 256.',
            'max_output_tokens.max' => 'Batas token keluaran maksimal 32.000.',
        ]);

        $errors = [];

        if ($data['provider'] === 'openai') {
            if (! $this->providers->isConfigured('openai')) {
                $errors['provider'] = 'OpenAI belum bisa dipakai: OPENAI_API_KEY belum dikonfigurasi di server.';
            }

            if (blank($data['model'] ?? null)) {
                $errors['model'] = 'Nama model wajib diisi untuk OpenAI.';
            }
        }

        if ($errors !== []) {
            return $this->fail($request, $errors);
        }

        $before = $this->settings->effectiveFlat();

        // Model dikosongkan hanya bila provider mock; untuk mock nilai lama dipertahankan agar tidak hilang diam-diam.
        $this->settings->persist([
            'ai.provider' => $data['provider'],
            'ai.model' => $data['provider'] === 'mock' ? ($data['model'] ?? $before['ai.model'] ?? '') : $data['model'],
            'ai.timeout' => $data['timeout'],
            'ai.max_output_tokens' => $data['max_output_tokens'],
        ], $request->session()->get('research_admin_user_id'));

        $after = $this->settings->effectiveFlat();
        $changed = array_keys(array_filter($after, fn ($v, $k) => ($before[$k] ?? null) !== $v && str_starts_with($k, 'ai.'), ARRAY_FILTER_USE_BOTH));

        if ($changed !== []) {
            $this->audit($request, 'ai.config.updated', ['changed' => $changed, 'provider' => $after['ai.provider'], 'model' => $after['ai.model'], 'before_provider' => $before['ai.provider'], 'before_model' => $before['ai.model']]);
        }

        $message = $changed === [] ? 'Tidak ada perubahan untuk disimpan.' : 'Konfigurasi disimpan. Riset baru memakai pengaturan ini; riset yang sudah berjalan tidak berubah.';

        return $request->expectsJson()
            ? response()->json(['ok' => true, 'message' => $message])
            : redirect()->route('admin.providers.index')->with('success', $message);
    }

    /** Tes koneksi atas permintaan admin. Metadata saja (tanpa token, tanpa biaya); hasil terakhir disimpan. */
    public function test(Request $request, string $provider): JsonResponse
    {
        abort_unless(isset($this->providers->catalog()[$provider]), 404);

        $result = $this->providers->test($provider);

        if (! ($result['throttled'] ?? false)) {
            $this->audit($request, 'ai.provider.tested', ['provider' => $provider, 'ok' => $result['ok'], 'model_found' => $result['model_found']]);
        }

        $status = $this->providers->status($provider);

        return response()->json([
            'ok' => $result['ok'],
            'message' => $result['message'],
            'connection' => $status['connection'],
            'checked_at' => $status['check'] ? $status['check']['checked_at']->copy()->timezone('Asia/Jakarta')->locale('id')->translatedFormat('j M Y, H.i').' WIB' : null,
        ], ($result['throttled'] ?? false) ? 429 : 200);
    }

    /** Baris lengkap semua provider (tanpa filter daftar) untuk panel detail. */
    private function allRows(array $catalog, array $data, string $activeModel): array
    {
        $out = [];

        foreach ($data['providers'] as $id => $usage) {
            $info = $catalog[$id] ?? ['id' => $id, 'name' => $id, 'kind' => 'legacy', 'kind_label' => 'Tidak lagi terintegrasi', 'logo' => null, 'needs_key' => false, 'billed' => false, 'env' => null];
            $status = isset($catalog[$id]) ? $this->providers->status($id) : ['configured' => false, 'enabled' => false, 'connection' => ['key' => 'na', 'label' => 'Tidak berlaku'], 'check' => null];
            $out[$id] = ['info' => $info, 'status' => $status, 'usage' => $usage, 'current_model' => $status['enabled'] && $info['needs_key'] && $activeModel !== '' ? $activeModel : null, 'models' => collect($usage['models'])->pluck('model')->all()];
        }

        return $out;
    }

    private function fail(Request $request, array $errors): JsonResponse|\Illuminate\Http\RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => reset($errors), 'errors' => array_map(fn ($m) => [$m], $errors)], 422);
        }

        throw \Illuminate\Validation\ValidationException::withMessages($errors);
    }

    private function audit(Request $request, string $action, array $metadata): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        AuditLog::create([
            'user_id' => $request->session()->get('research_admin_user_id'),
            'action' => $action,
            'auditable_type' => 'ai_provider',
            'auditable_id' => null,
            'metadata_json' => $metadata,
            'ip_address' => $request->ip(),
        ]);
    }
}
