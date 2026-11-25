<?php

namespace App\Services\Research;

use App\Models\ResearchSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/*
 * Provider AI yang BENAR-BENAR terintegrasi di kode (AiProviderManager): `openai` (Responses API) dan `mock` (khusus uji).
 * Provider lain (Anthropic, Google, dst.) sengaja tidak ada di sini karena belum ada driver-nya; daftar ini mengikuti kode,
 * bukan keinginan. Yang dikelola halaman ini hanya provider inferensi. Pencarian web tidak terintegrasi, dan retrieval
 * dokumen adalah pencarian kata kunci lokal di Pustaka (KeywordRetriever), keduanya bukan "provider" dan tanpa token.
 *
 * Kredensial hanya dibaca dari environment/config di server dan tidak pernah keluar dari kelas ini: yang diteruskan ke UI
 * hanya boolean "terkonfigurasi". Hasil tes koneksi terakhir disimpan di research_settings (tanpa kunci, tanpa isi respons).
 */
class AiProviders
{
    public const MODEL_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._:\\/-]*$/';

    /** Satu-satunya tugas yang saat ini memanggil AI: lihat ResearchSynthesisService (satu panggilan per run riset). */
    public const TASKS = [
        ['key' => 'research', 'label' => 'Analisis dan jawaban riset', 'note' => 'Semua alat riset: analisis literatur, Research Gap, jawaban akademik, dan pertanyaan lanjutan dihasilkan dalam satu panggilan per run.', 'routable' => true],
        ['key' => 'documents', 'label' => 'Penulisan dokumen', 'note' => 'Dokumen dirakit tanpa AI dari hasil riset yang sudah ada, jadi tidak memakai token.', 'routable' => false],
        ['key' => 'extraction', 'label' => 'Ekstraksi dan pembacaan PDF', 'note' => 'Diproses lokal oleh parser PDF di server, tanpa provider AI.', 'routable' => false],
        ['key' => 'web', 'label' => 'Pencarian referensi internet', 'note' => 'Belum terintegrasi. Model tidak otomatis bisa mencari web tanpa alat pencarian terpisah.', 'routable' => false],
    ];

    public function catalog(): array
    {
        return [
            'openai' => [
                'id' => 'openai',
                'name' => 'OpenAI',
                'kind' => 'inference',
                'kind_label' => 'Inferensi (Responses API)',
                'logo' => 'images/providers/openai.svg',
                'needs_key' => true,
                'env' => 'OPENAI_API_KEY',
                'billed' => true,
            ],
            'mock' => [
                'id' => 'mock',
                'name' => 'Mock (pengujian)',
                'kind' => 'mock',
                'kind_label' => 'Penyedia uji tanpa panggilan API',
                'logo' => null,
                'needs_key' => false,
                'env' => null,
                'billed' => false,
            ],
        ];
    }

    public function isConfigured(string $id): bool
    {
        return match ($id) {
            'openai' => filled(trim((string) config('services.openai.api_key'))),
            'mock' => true,
            default => false,
        };
    }

    /**
     * Status satu provider. "Terhubung" hanya muncul dari tes koneksi yang benar-benar berhasil, bukan karena kunci ada.
     *
     * @return array<string, mixed>
     */
    public function status(string $id): array
    {
        $info = $this->catalog()[$id];
        $configured = $this->isConfigured($id);
        // Hasil tes lama tidak berlaku lagi bila kunci sekarang tidak ada.
        $check = $info['needs_key'] && $configured ? $this->lastCheck($id) : null;

        $connection = match (true) {
            ! $info['needs_key'] => ['key' => 'na', 'label' => 'Tidak berlaku'],
            ! $configured => ['key' => 'missing', 'label' => 'Belum dikonfigurasi'],
            $check === null => ['key' => 'unchecked', 'label' => 'Belum diperiksa'],
            $check['ok'] => ['key' => 'ok', 'label' => 'Terhubung'],
            default => ['key' => 'error', 'label' => 'Gagal terhubung'],
        };

        return [
            'configured' => $configured,
            'enabled' => strtolower((string) config('research.ai_provider')) === $id,
            'connection' => $connection,
            'check' => $check,
        ];
    }

    /** @return ?array{ok: bool, checked_at: Carbon, message: string, model_found: ?bool} */
    public function lastCheck(string $id): ?array
    {
        if (! Schema::hasTable('research_settings')) {
            return null;
        }

        $raw = ResearchSetting::query()->where('key', 'provider.'.$id.'.last_check')->value('value');
        $data = is_string($raw) ? json_decode($raw, true) : null;

        if (! is_array($data) || ! isset($data['ok'], $data['at'])) {
            return null;
        }

        return [
            'ok' => (bool) $data['ok'],
            'checked_at' => Carbon::parse($data['at']),
            'message' => (string) ($data['message'] ?? ''),
            'model_found' => $data['model_found'] ?? null,
        ];
    }

    /**
     * Tes koneksi OpenAI lewat GET /models: metadata saja, tidak memakai token dan tidak ditagih. Hanya dijalankan atas
     * permintaan admin (tidak pernah otomatis saat halaman dibuka), dibatasi satu kali per 10 detik, dan hasilnya
     * hanya memuat status serta kategori galat: tidak ada kunci dan tidak ada isi respons.
     *
     * @return array{ok: bool, message: string, model_found: ?bool, throttled?: bool}
     */
    public function test(string $id): array
    {
        if ($id !== 'openai') {
            return ['ok' => false, 'message' => 'Provider ini tidak punya koneksi untuk diperiksa.', 'model_found' => null];
        }

        if (! $this->isConfigured('openai')) {
            return ['ok' => false, 'message' => 'OPENAI_API_KEY belum dikonfigurasi di server.', 'model_found' => null];
        }

        if (! Cache::add('provider-test:'.$id, 1, 10)) {
            return ['ok' => false, 'message' => 'Tunggu beberapa detik sebelum memeriksa lagi.', 'model_found' => null, 'throttled' => true];
        }

        $base = rtrim((string) config('services.openai.base_url', 'https://api.openai.com/v1'), '/');
        $model = trim((string) config('research.ai_model'));
        $found = null;

        try {
            $response = Http::withToken(trim((string) config('services.openai.api_key')))->acceptJson()->timeout(10)->connectTimeout(5)->get($base.'/models');

            if ($response->successful()) {
                $ids = collect($response->json('data'))->pluck('id')->filter()->all();
                $found = $model !== '' ? in_array($model, $ids, true) : null;
                $result = [
                    'ok' => true,
                    'model_found' => $found,
                    'message' => match (true) {
                        $found === true => 'Koneksi berhasil dan model "'.$model.'" tersedia untuk akun ini.',
                        $found === false => 'Koneksi berhasil, tetapi model "'.$model.'" tidak ditemukan di akun ini.',
                        default => 'Koneksi berhasil. Model belum dipilih.',
                    },
                ];
            } else {
                $result = ['ok' => false, 'model_found' => null, 'message' => match ($response->status()) {
                    401, 403 => 'Kunci API ditolak oleh provider.',
                    429 => 'Provider membatasi permintaan (rate limit) atau kuota habis.',
                    default => 'Provider menjawab galat HTTP '.$response->status().'.',
                }];
            }
        } catch (ConnectionException) {
            $result = ['ok' => false, 'model_found' => null, 'message' => 'Tidak dapat menjangkau provider (jaringan atau timeout).'];
        }

        ResearchSetting::query()->updateOrCreate(['key' => 'provider.'.$id.'.last_check'], [
            'group' => 'provider_status',
            'value' => json_encode(['ok' => $result['ok'], 'at' => now()->toIso8601String(), 'message' => $result['message'], 'model_found' => $result['model_found']]),
            'value_type' => 'string',
            'description' => 'Hasil tes koneksi terakhir (tanpa kredensial).',
        ]);

        return $result;
    }
}
