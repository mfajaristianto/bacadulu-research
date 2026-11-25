<?php

namespace App\Services\Synthesis;

use App\Models\AiRun;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Anggaran, kuota, dan laju untuk sintesis NYATA. Tarif TIDAK pernah dikarang: tanpa tarif dari admin, provider nyata nonaktif
 * (lihat GroundedProviderManager). Perkiraan token memakai heuristik konservatif (1 token ≈ 3 byte teks, ditambah overhead skema):
 * ia ESTIMASI, bukan hitungan tokenizer; biaya aktual hanya dihitung bila provider melaporkan usage.
 * Semua guard dijalankan SEBELUM AiRun dibuat dan SEBELUM ada permintaan ke provider.
 */
final class SynthesisBudget
{
    public const SCHEMA_OVERHEAD_TOKENS = 450;

    public const BYTES_PER_TOKEN = 3;

    /** @return array{0: float, 1: float}|null [USD per 1M input, USD per 1M output]; null bila belum diisi admin */
    public static function prices(): ?array
    {
        $in = config('research.synthesis.price_input_per_mtok_usd');
        $out = config('research.synthesis.price_output_per_mtok_usd');

        if (! is_numeric($in) || ! is_numeric($out) || (float) $in <= 0 || (float) $out <= 0) {
            return null;
        }

        return [(float) $in, (float) $out];
    }

    public static function estimateInputTokens(ResearchSynthesisRequest $request): int
    {
        $p = $request->toProviderPayload();

        return (int) ceil((strlen($p['system_prompt']) + strlen($p['compiled_prompt'])) / self::BYTES_PER_TOKEN) + self::SCHEMA_OVERHEAD_TOKENS;
    }

    /**
     * @return array{input_tokens: int, max_output_tokens: int, max_cost_usd: ?float, priced: bool, cost_cap_usd: float, max_input_tokens: int, billed: bool}
     */
    public function estimate(ResearchSynthesisRequest $request, bool $billed): array
    {
        $in = self::estimateInputTokens($request);
        $out = max(1, (int) config('research.synthesis.max_output_tokens'));
        $p = self::prices();

        return [
            'input_tokens' => $in, 'max_output_tokens' => $out, 'billed' => $billed, 'priced' => $p !== null,
            'max_cost_usd' => $p !== null ? round($in * $p[0] / 1_000_000 + $out * $p[1] / 1_000_000, 6) : null,
            'cost_cap_usd' => (float) config('research.synthesis.max_cost_per_request_usd'),
            'max_input_tokens' => (int) config('research.synthesis.max_input_tokens'),
        ];
    }

    /** Biaya perkiraan dari usage yang DILAPORKAN provider; null bila tarif atau usage tidak ada. */
    public static function costFromUsage(?array $usage): ?float
    {
        $p = self::prices();
        if ($p === null || ! is_array($usage) || ! isset($usage['input_tokens'], $usage['output_tokens'])) {
            return null;
        }

        return round((int) $usage['input_tokens'] * $p[0] / 1_000_000 + (int) $usage['output_tokens'] * $p[1] / 1_000_000, 6);
    }

    /**
     * Tolak (SynthesisRejected) bila melewati batas. Hanya untuk provider berbayar; Mock tidak dibatasi.
     *
     * @param  array<string, mixed>  $estimate
     */
    public function guard(User $user, string $provider, array $estimate): void
    {
        if (! $estimate['billed']) {
            return;
        }
        $b = SynthesisFailure::BUDGET_EXCEEDED;

        if ($estimate['input_tokens'] > $estimate['max_input_tokens']) {
            throw new SynthesisRejected($b, 'input_tokens', "Perkiraan {$estimate['input_tokens']} token masukan melewati batas {$estimate['max_input_tokens']}. ".SynthesisFailure::message($b));
        }
        if ($estimate['max_cost_usd'] === null) {
            throw new SynthesisRejected(SynthesisFailure::PROVIDER_UNAVAILABLE, 'pricing_missing', 'Tarif model belum diisi admin; tidak ada permintaan dikirim.');
        }
        if ($estimate['max_cost_usd'] > $estimate['cost_cap_usd']) {
            throw new SynthesisRejected($b, 'cost', 'Perkiraan biaya maksimum $'.number_format($estimate['max_cost_usd'], 4).' melewati batas $'.number_format($estimate['cost_cap_usd'], 4).' per permintaan. '.SynthesisFailure::message($b));
        }

        $today = now(config('app.timezone'))->startOfDay();
        $userLimit = (int) config('research.synthesis.user_requests_per_day');
        if ($userLimit >= 0 && AiRun::query()->where('user_id', $user->id)->where('model_provider', $provider)->where('created_at', '>=', $today)->count() >= $userLimit) {
            throw new SynthesisRejected($b, 'user_daily', "Batas {$userLimit} analisis per hari untuk akun Anda sudah tercapai. ".SynthesisFailure::message($b));
        }
        $globalLimit = (int) config('research.synthesis.global_requests_per_day');
        if ($globalLimit >= 0 && AiRun::query()->where('model_provider', $provider)->where('created_at', '>=', $today)->count() >= $globalLimit) {
            throw new SynthesisRejected($b, 'global_daily', 'Batas analisis harian sistem sudah tercapai. '.SynthesisFailure::message($b));
        }

        $perMinute = max(1, (int) config('research.synthesis.requests_per_minute'));
        $key = 'synthesis:minute:'.$provider;
        if (RateLimiter::tooManyAttempts($key, $perMinute)) {
            throw new SynthesisRejected(SynthesisFailure::RATE_LIMITED, 'per_minute', 'Terlalu banyak analisis dalam satu menit. '.SynthesisFailure::message(SynthesisFailure::RATE_LIMITED));
        }
        RateLimiter::hit($key, 60);
    }
}
