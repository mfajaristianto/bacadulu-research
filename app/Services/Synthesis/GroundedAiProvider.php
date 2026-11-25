<?php

namespace App\Services\Synthesis;

/**
 * Kontrak provider sintesis berbasis bukti (tidak bergantung vendor: OpenAI, Gemini, OpenRouter, Mock, ...).
 * Menerima ResearchSynthesisRequest dan mengembalikan TEKS JSON mentah + meta. Aplikasi mengurai dan memvalidasi sendiri:
 * "schema valid" dari provider tidak pernah dipercaya. Kegagalan dilempar sebagai ProviderFailure (pesan aman).
 *
 * meta yang dikembalikan: provider, model, test_mode (bool), usage (format token provider, mis. input_tokens/output_tokens),
 * duration_ms, attempts, request_id (aman), dan apa pun yang tidak sensitif.
 */
interface GroundedAiProvider
{
    /** @return array{raw: string, meta: array<string, mixed>} */
    public function complete(ResearchSynthesisRequest $request): array;
}
