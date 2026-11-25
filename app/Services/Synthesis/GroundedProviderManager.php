<?php

namespace App\Services\Synthesis;

/**
 * Memilih provider sintesis dan membedakan tiga mode:
 *   mock           : provider uji, tanpa jaringan dan tanpa biaya;
 *   real-disabled  : provider nyata dipilih tetapi belum boleh dipanggil (live dimatikan, kunci/model/tarif belum lengkap);
 *   real-enabled   : provider nyata siap DAN diaktifkan eksplisit oleh admin.
 * Tidak ada perpindahan otomatis dari mock ke OpenAI hanya karena OPENAI_API_KEY ada, dan memilih openai di alur riset lama
 * (research.ai_provider) tidak berpengaruh di sini. Provider sintesis dikonfigurasi terpisah (research.synthesis.*).
 *
 * Menambah provider lain (Gemini, OpenRouter, ...) = menambah satu kelas GroundedAiProvider dan satu baris di REAL; Evidence Pipeline,
 * validator, dan snapshot tidak berubah.
 */
final class GroundedProviderManager
{
    /** id provider nyata => [kelas adaptor, nama, nama env kunci (hanya untuk teks bantuan), kunci config kredensial] */
    public const REAL = [
        'openai' => ['class' => OpenAiGroundedProvider::class, 'name' => 'OpenAI', 'env' => 'OPENAI_API_KEY', 'key_config' => 'services.openai.api_key'],
    ];

    public function name(): string
    {
        return strtolower(trim((string) config('research.synthesis.provider', 'mock'))) ?: 'mock';
    }

    public function keyConfigured(string $id): bool
    {
        return isset(self::REAL[$id]) && trim((string) config(self::REAL[$id]['key_config'])) !== '';
    }

    /**
     * Hal yang masih menghalangi provider nyata. Tidak pernah memuat nilai kunci.
     *
     * @return list<string>
     */
    public function blockers(): array
    {
        $id = $this->name();
        if (! isset(self::REAL[$id])) {
            return ['provider tidak dikenal'];
        }

        return array_values(array_filter([
            config('research.synthesis.live_enabled') ? null : 'mode live belum diaktifkan admin',
            $this->keyConfigured($id) ? null : 'kunci API belum dikonfigurasi di server',
            trim((string) config('research.synthesis.model')) !== '' ? null : 'model belum dipilih',
            SynthesisBudget::prices() !== null ? null : 'tarif model belum diisi admin',
        ]));
    }

    public function mode(): string
    {
        $id = $this->name();
        if ($id === 'mock') {
            return 'mock';
        }

        return isset(self::REAL[$id]) && $this->blockers() === [] ? 'real-enabled' : 'real-disabled';
    }

    /** @return array{available: bool, test_mode: bool, billed: bool, mode: string, provider: string, provider_name: string, label: string, reason: ?string, blockers: list<string>} */
    public function status(): array
    {
        $id = $this->name();
        $mode = $this->mode();
        $name = $id === 'mock' ? 'Mock (pengujian)' : (self::REAL[$id]['name'] ?? $id);

        return match ($mode) {
            'mock' => ['available' => true, 'test_mode' => true, 'billed' => false, 'mode' => $mode, 'provider' => 'mock', 'provider_name' => $name, 'label' => 'Mode pengujian (Mock)', 'reason' => null, 'blockers' => []],
            'real-enabled' => ['available' => true, 'test_mode' => false, 'billed' => true, 'mode' => $mode, 'provider' => $id, 'provider_name' => $name, 'label' => "Provider AI nyata ({$name}), berbayar", 'reason' => null, 'blockers' => []],
            default => ['available' => false, 'test_mode' => false, 'billed' => false, 'mode' => $mode, 'provider' => $id, 'provider_name' => $name, 'label' => 'Provider AI nyata belum diaktifkan',
                'reason' => 'Provider AI nyata belum disambungkan: '.implode('; ', $this->blockers()).'. Tidak ada permintaan yang dikirim.', 'blockers' => $this->blockers()],
        };
    }

    public function driver(): GroundedAiProvider
    {
        $mode = $this->mode();

        return match (true) {
            $mode === 'mock' => app(GroundedMockProvider::class),
            $mode === 'real-enabled' => app(self::REAL[$this->name()]['class']),
            default => throw new ProviderNotEnabled($this->status()['reason']),
        };
    }
}
