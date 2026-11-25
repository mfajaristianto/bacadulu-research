<?php

namespace App\Services\Synthesis;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * Adaptor OpenAI (Responses API) untuk sintesis berbasis bukti. Memakai config('services.openai.*') dan config('research.synthesis.*')
 * (tidak membaca env()). Tidak menyimpan/mencatat kunci atau header Authorization, tidak menyimpan galat mentah provider.
 * Structured output (json_schema strict) diminta, tetapi aplikasi tetap memvalidasi sendiri. HANYA dipanggil bila
 * GroundedProviderManager berstatus real-enabled (provider=openai + live diaktifkan admin + kunci + model + tarif).
 *
 * Retry: hanya galat transien (timeout, koneksi, 408, 5xx, 429 non-kuota), dibatasi max_retries (<= 3) dan total_timeout, dengan
 * exponential backoff atau Retry-After (bila <= 20 dtk). Tidak ada retry untuk kunci salah, request/model tidak valid, kuota habis.
 * Semua percobaan terjadi di dalam SATU AiRun (tidak membuat run ganda).
 */
final class OpenAiGroundedProvider implements GroundedAiProvider
{
    private const MAX_RETRY_AFTER = 20;

    public function complete(ResearchSynthesisRequest $request): array
    {
        $key = trim((string) config('services.openai.api_key'));
        $model = trim((string) config('research.synthesis.model'));
        if ($key === '' || $model === '') {
            throw new ProviderFailure(SynthesisFailure::PROVIDER_UNAVAILABLE);
        }

        $payload = $request->toProviderPayload();
        $url = rtrim((string) config('services.openai.base_url', 'https://api.openai.com/v1'), '/').'/responses';
        $body = $this->body($model, $payload);
        $retries = max(0, min(3, (int) config('research.synthesis.max_retries')));
        $started = microtime(true);
        $deadline = $started + max(10, (int) config('research.synthesis.total_timeout', 110));

        for ($attempt = 1; ; $attempt++) {
            $failure = null;
            $response = null;
            try {
                $remaining = max(5, (int) floor($deadline - microtime(true)));
                $response = Http::withToken($key)->acceptJson()->asJson()
                    ->connectTimeout(max(1, (int) config('research.synthesis.connect_timeout', 10)))
                    ->timeout(min($remaining, max(1, (int) config('research.synthesis.timeout', 60))))
                    ->post($url, $body);
            } catch (ConnectionException $e) {
                $timedOut = str_contains(strtolower($e->getMessage()), 'timed out') || str_contains($e->getMessage(), 'cURL error 28');
                $failure = [$timedOut ? SynthesisFailure::TIMEOUT : SynthesisFailure::PROVIDER_UNAVAILABLE, true, null, null, null];
            }

            if ($response !== null) {
                if ($response->successful()) {
                    return $this->success($response, $model, $attempt, $started);
                }
                $failure = $this->classify($response);
            }

            [$state, $retryable, $status, $code, $retryAfter] = $failure;
            $meta = ['attempts' => $attempt, 'duration_ms' => (int) round((microtime(true) - $started) * 1000), 'model' => $model];
            $wait = $retryAfter ?? min(8, 2 ** ($attempt - 1));
            if (! $retryable || $attempt > $retries || ($retryAfter !== null && $retryAfter > self::MAX_RETRY_AFTER) || microtime(true) + $wait >= $deadline) {
                throw new ProviderFailure($state, $retryable, $status, $code, $meta);
            }
            Sleep::for($wait)->seconds();
        }
    }

    /** @return array<string, mixed> */
    private function body(string $model, array $payload): array
    {
        $body = [
            'model' => $model,
            'input' => [
                ['role' => 'system', 'content' => $payload['system_prompt']],
                ['role' => 'user', 'content' => $payload['compiled_prompt']],
            ],
            'text' => ['format' => ['type' => 'json_schema', 'name' => 'bacadulu_grounded_synthesis', 'strict' => true, 'schema' => GroundingRules::jsonSchema()]],
            'max_output_tokens' => max(256, (int) config('research.synthesis.max_output_tokens')),
            'store' => false, // jangan menyimpan percakapan di sisi provider
        ];
        if (is_numeric(config('research.synthesis.temperature'))) {
            $body['temperature'] = (float) config('research.synthesis.temperature');
        }
        if (is_string(config('research.synthesis.reasoning_effort')) && config('research.synthesis.reasoning_effort') !== '') {
            $body['reasoning'] = ['effort' => config('research.synthesis.reasoning_effort')];
        }

        return $body;
    }

    /** @return array{raw: string, meta: array<string, mixed>} */
    private function success(Response $r, string $model, int $attempt, float $started): array
    {
        $json = $r->json();
        $meta = $this->meta($r, is_array($json) ? $json : [], $model, $attempt, $started);
        $fail = fn (string $state) => new ProviderFailure($state, false, $r->status(), null, $meta);

        if (! is_array($json)) {
            throw $fail(SynthesisFailure::INVALID_RESPONSE);
        }
        if (($json['status'] ?? 'completed') === 'incomplete') {
            throw $fail(SynthesisFailure::INVALID_RESPONSE); // mis. max_output_tokens tercapai: JSON terpotong tidak boleh dipakai
        }

        $text = '';
        foreach ((array) ($json['output'] ?? []) as $item) {
            foreach ((array) ($item['content'] ?? []) as $c) {
                if (($c['type'] ?? null) === 'refusal') {
                    throw $fail(SynthesisFailure::INVALID_RESPONSE);
                }
                if (($c['type'] ?? null) === 'output_text' && is_string($c['text'] ?? null)) {
                    $text .= $c['text'];
                }
            }
        }
        if (trim($text) === '') {
            throw $fail(SynthesisFailure::INVALID_RESPONSE);
        }

        return ['raw' => $text, 'meta' => $meta];
    }

    /** @return array<string, mixed> hanya nilai yang aman dicatat */
    private function meta(Response $r, array $json, string $model, int $attempt, float $started): array
    {
        $safe = fn ($v) => is_string($v) && preg_match('/^[A-Za-z0-9_.:\/-]{1,100}$/', $v) ? $v : null;
        $usage = null;
        if (is_array($json['usage'] ?? null)) {
            $usage = [];
            foreach (['input_tokens', 'output_tokens', 'total_tokens'] as $k) {
                if (isset($json['usage'][$k]) && is_numeric($json['usage'][$k])) {
                    $usage[$k] = (int) $json['usage'][$k];
                }
            }
            foreach (['input_tokens_details' => 'cached_tokens', 'output_tokens_details' => 'reasoning_tokens'] as $group => $k) {
                if (isset($json['usage'][$group][$k]) && is_numeric($json['usage'][$group][$k])) {
                    $usage[$group] = [$k => (int) $json['usage'][$group][$k]];
                }
            }
            $usage = isset($usage['input_tokens'], $usage['output_tokens']) ? $usage : null;
        }

        return [
            'provider' => 'openai', 'model' => $safe($json['model'] ?? null) ?? $model, 'test_mode' => false, 'network' => true,
            'usage' => $usage, 'attempts' => $attempt, 'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'request_id' => $safe($r->header('x-request-id')) ?? $safe($json['id'] ?? null),
        ];
    }

    /** @return array{0: string, 1: bool, 2: int, 3: ?string, 4: ?float} state, retryable, http status, kode provider aman, Retry-After detik */
    private function classify(Response $r): array
    {
        $status = $r->status();
        $code = $r->json('error.code') ?? $r->json('error.type');
        $code = is_string($code) && preg_match('/^[A-Za-z0-9_.-]{1,60}$/', $code) ? strtolower($code) : null;
        $ra = $r->header('Retry-After');
        $retryAfter = is_numeric($ra) ? max(0.0, (float) $ra) : null;

        return match (true) {
            in_array($status, [401, 403], true) => [SynthesisFailure::AUTH_FAILED, false, $status, $code, null],
            $status === 429 && $code !== null && preg_match('/quota|billing/', $code) === 1 => [SynthesisFailure::QUOTA_EXHAUSTED, false, $status, $code, null],
            $status === 429 => [SynthesisFailure::RATE_LIMITED, true, $status, $code, $retryAfter],
            $status === 408 => [SynthesisFailure::TIMEOUT, true, $status, $code, $retryAfter],
            $status >= 500 => [SynthesisFailure::PROVIDER_UNAVAILABLE, true, $status, $code, $retryAfter],
            default => [SynthesisFailure::INVALID_RESPONSE, false, $status, $code, null], // 400/404/422: request atau model tidak didukung
        };
    }
}
