<?php

namespace App\Services\ExternalSources;

use App\Models\ResearchSetting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Throwable;

/*
 * Pengaturan per penyedia disimpan di tabel research_settings yang sudah ada (kunci `external.{kode}.*`), tanpa
 * migration. API key dienkripsi (Crypt) dan tidak pernah dikembalikan ke browser: UI hanya tahu "ada/tidak".
 * Urutan sumber key: nilai admin (terenkripsi) → env (config). Penyedia nonaktif sampai admin mengaktifkannya.
 */
class ExternalSourceSettings
{
    public function get(string $code): array
    {
        $rows = $this->rows($code);
        $stored = $this->decrypt($rows['api_key'] ?? null);
        // config/services.php (mis. services.openalex.api_key) adalah sumber resmi; external-sources.* tetap dihormati bila terisi.
        $envKey = (string) (config("external-sources.providers.{$code}.api_key") ?: config("services.{$code}.api_key", ''));

        return [
            'enabled' => ($rows['enabled'] ?? '0') === '1',
            'contact' => ($rows['contact'] ?? '') !== '' ? $rows['contact'] : (string) config('external-sources.contact', ''),
            'contact_own' => ($rows['contact'] ?? '') !== '',
            'api_key' => $stored ?: ($envKey ?: null),
            'has_key' => (bool) ($stored ?: $envKey),
            'key_source' => $stored ? 'admin' : ($envKey ? 'env' : null),
        ];
    }

    /** Hanya yang dikirim adapter: tanpa bidang tampilan. */
    public function forAdapter(string $code): array
    {
        $s = $this->get($code);

        return ['api_key' => $s['api_key'], 'contact' => $s['contact'] ?: null];
    }

    public function save(string $code, array $data, ?int $userId): void
    {
        if (! $this->available()) {
            return;
        }

        $this->put($code, 'enabled', ! empty($data['enabled']) ? '1' : '0', 'bool', $userId);
        $this->put($code, 'contact', trim((string) ($data['contact'] ?? '')), 'string', $userId);

        if (! empty($data['clear_api_key'])) {
            ResearchSetting::query()->where('key', $this->key($code, 'api_key'))->delete();
        } elseif (filled($data['api_key'] ?? null)) {
            $this->put($code, 'api_key', Crypt::encryptString(trim((string) $data['api_key'])), 'secret', $userId);
        }
    }

    /** Henti darurat global: bila aktif, tidak ada sinkronisasi yang boleh dimulai/dilanjutkan dan semua batch berhenti. */
    public function emergencyStopped(): bool
    {
        if (! $this->available()) {
            return false;
        }

        return ResearchSetting::query()->where('key', 'external.emergency_stop')->value('value') === '1';
    }

    public function setEmergency(bool $on, ?int $userId): void
    {
        if ($this->available()) {
            ResearchSetting::query()->updateOrCreate(['key' => 'external.emergency_stop'], ['group' => 'external-sources', 'value' => $on ? '1' : '0', 'value_type' => 'bool', 'updated_by' => $userId]);
        }
    }

    public function recordConnection(string $code, bool $ok, string $message, int $latencyMs): void
    {
        Cache::put("external-sources:conn:{$code}", ['ok' => $ok, 'message' => $message, 'at' => now()->toIso8601String(), 'latency_ms' => $latencyMs], now()->addDays(7));
    }

    public function lastConnection(string $code): ?array
    {
        return Cache::get("external-sources:conn:{$code}");
    }

    private function available(): bool
    {
        try {
            return Schema::hasTable('research_settings');
        } catch (Throwable) {
            return false;
        }
    }

    private function key(string $code, string $name): string
    {
        return "external.{$code}.{$name}";
    }

    /** @return array<string, string> */
    private function rows(string $code): array
    {
        if (! $this->available()) {
            return [];
        }

        return ResearchSetting::query()->where('key', 'like', "external.{$code}.%")->pluck('value', 'key')
            ->mapWithKeys(fn ($v, $k) => [substr($k, strlen("external.{$code}.")) => (string) $v])->all();
    }

    private function put(string $code, string $name, string $value, string $type, ?int $userId): void
    {
        ResearchSetting::query()->updateOrCreate(
            ['key' => $this->key($code, $name)],
            ['group' => 'external-sources', 'value' => $value, 'value_type' => $type, 'updated_by' => $userId]
        );
    }

    private function decrypt(?string $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return null;
        }
    }
}
