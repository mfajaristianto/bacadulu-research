<?php

namespace App\Services\Synthesis;

use App\Models\AiRun;
use App\Models\AiRunEvidence;
use App\Models\AiRunResult;
use App\Models\AuditLog;
use App\Models\PromptTemplate;
use App\Models\User;
use App\Services\Evidence\Evidence;
use App\Services\Evidence\EvidenceBundleBuilder;
use App\Services\Evidence\ResearchEvidenceBundle;
use App\Services\Research\AnswerBehaviorResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Sintesis AI berbasis Evidence Bundle. Alur: bundle -> ResearchSynthesisRequest -> guard anggaran/kuota/laju -> provider
 * (mock, atau nyata hanya bila real-enabled) -> parse -> validasi sitasi -> pemeriksaan struktural klaim.
 * `execute()` murni (tanpa tulis database). `run()` menyimpan AI Run + snapshot evidence yang diberikan ke model + hasil + usage + audit.
 * Alur riset Pustaka yang lama (ProcessResearchRun/ResearchRunService/CitationValidator) tidak disentuh.
 */
final class GroundedSynthesisService
{
    public function __construct(
        private readonly EvidenceBundleBuilder $builder,
        private readonly GroundedProviderManager $providers,
        private readonly StructuredResponseParser $parser,
        private readonly SynthesisValidator $validator,
        private readonly SynthesisTools $tools,
        private readonly AnswerBehaviorResolver $behavior,
        private readonly SynthesisBudget $budget,
        private readonly ClaimEvidenceVerifier $verifier,
    ) {}

    /** Snapshot evidence hanya dapat disimpan bila migration ai_run_evidence sudah dijalankan. */
    public static function snapshotAvailable(): bool
    {
        return Schema::hasTable('ai_run_evidence');
    }

    /** @return array<string, mixed> */
    public function availability(): array
    {
        $p = $this->providers->status();
        $reason = $p['reason'] ?? (self::snapshotAvailable() ? null : 'Penyimpanan snapshot bukti belum tersedia (migration ai_run_evidence belum dijalankan). Tidak ada yang dijalankan.');
        $tools = $this->tools->actions();
        $reason ??= $tools === [] ? 'Belum ada alat riset aktif dengan prompt aktif di Katalog Alat AI.' : null;

        return ['available' => $reason === null, 'reason' => $reason, 'test_mode' => $p['test_mode'], 'billed' => $p['billed'], 'mode' => $p['mode'], 'blockers' => $p['blockers'],
            'label' => $p['label'], 'provider' => $p['provider'], 'provider_name' => $p['provider_name'], 'tools' => $tools];
    }

    public function request(PromptTemplate $prompt, ResearchEvidenceBundle $bundle, string $query, array $context = []): ResearchSynthesisRequest
    {
        $directive = null;
        try {
            $directive = $this->behavior->payload($this->behavior->safe('research', $prompt->aiTool?->code))['directive'] ?? null;
        } catch (Throwable) {
            // perilaku jawaban hanya gaya penyajian; kegagalan memuatnya tidak boleh menghentikan sintesis
        }

        return new ResearchSynthesisRequest($prompt, $query, $bundle, $this->trimContext($context), $directive);
    }

    /** Perkiraan anggaran untuk ditampilkan sebelum konfirmasi. Tidak memanggil provider dan tidak menghitung kuota. */
    public function estimate(ResearchSynthesisRequest $request): array
    {
        return $this->budget->estimate($request, $this->providers->status()['billed']);
    }

    /**
     * Murni: provider -> parse -> validasi -> pemeriksaan struktural klaim. Tidak menulis database.
     * ProviderFailure dilempar ke pemanggil.
     *
     * @return array{status: string, raw: string, meta: array<string, mixed>, parsed: array<string, mixed>, validation: array<string, mixed>, request_sha256: string}
     */
    public function execute(ResearchSynthesisRequest $request): array
    {
        $payload = $request->toProviderPayload();
        $out = $this->providers->driver()->complete($request);
        $parsed = $this->parser->parse((string) $out['raw']);
        $validation = $this->validator->validate($request->bundle, $parsed);

        $verification = $this->verifier->verify($request->bundle, $validation['claims']);
        foreach ($validation['claims'] as $i => $c) {
            $validation['claims'][$i]['verification'] = $verification[$c['index']] ?? null;
        }
        $statuses = array_column(array_filter($verification), 'status');
        $validation['verification'] = [
            'verified_structurally' => count(array_filter($statuses, fn ($s) => $s === ClaimEvidenceVerifier::VERIFIED_STRUCTURALLY)),
            'needs_semantic_review' => count(array_filter($statuses, fn ($s) => $s === ClaimEvidenceVerifier::NEEDS_SEMANTIC_REVIEW)),
            'note' => 'Pemeriksaan deterministik (kata, angka, negasi), bukan pembuktian kesesuaian makna klaim dengan kutipan.',
        ];

        return ['status' => $validation['status'], 'raw' => (string) $out['raw'], 'meta' => $out['meta'], 'parsed' => $parsed, 'validation' => $validation, 'request_sha256' => $payload['request_sha256']];
    }

    /**
     * Menjalankan sintesis penuh dan menyimpannya.
     *
     * @throws SynthesisRejected bila anggaran/kuota/laju terlampaui atau permintaan identik sedang diproses (tidak ada AiRun dibuat)
     * @throws RuntimeException bila snapshot belum dapat disimpan atau provider tidak aktif
     */
    public function run(User $user, PromptTemplate $prompt, int $categoryId, string $query, array $filters = [], ?string $ip = null): AiRun
    {
        if (! self::snapshotAvailable()) {
            throw new RuntimeException('Penyimpanan snapshot bukti belum tersedia (migration ai_run_evidence belum dijalankan).');
        }
        $this->providers->driver(); // provider nyata yang belum real-enabled ditolak SEBELUM ada yang dibuat

        $status = $this->providers->status();
        $bundle = $this->builder->build($categoryId, $query, $filters);
        $request = $this->request($prompt, $bundle, $query);
        $payload = $request->toProviderPayload();
        $estimate = $this->budget->estimate($request, $status['billed']);

        // Permintaan identik dari pengguna yang sama tidak boleh berjalan dobel (klik ganda, kirim ulang): satu kunci per sidik jari.
        $lock = Cache::lock('synthesis:run:'.$user->id.':'.$payload['request_sha256'], 150);
        if (! $lock->get()) {
            throw new SynthesisRejected(SynthesisFailure::RATE_LIMITED, 'duplicate', 'Permintaan identik masih diproses. Tunggu hasilnya; tidak ada permintaan ganda yang dikirim.');
        }

        try {
            try {
                $this->budget->guard($user, $status['provider'], $estimate);
            } catch (SynthesisRejected $e) {
                $this->auditRejected($user, $prompt, $categoryId, $status, $estimate, $e, $ip);
                throw $e;
            }

            return $this->runGuarded($user, $prompt, $categoryId, $query, $filters, $ip, $status, $bundle, $request, $payload, $estimate);
        } finally {
            $lock->release();
        }
    }

    /** @param array<string, mixed> $status @param array<string, mixed> $estimate @param array<string, mixed> $payload */
    private function runGuarded(User $user, PromptTemplate $prompt, int $categoryId, string $query, array $filters, ?string $ip, array $status, ResearchEvidenceBundle $bundle, ResearchSynthesisRequest $request, array $payload, array $estimate): AiRun
    {
        $b = $bundle->toArray();
        $model = $status['test_mode'] ? 'grounded-mock-v1' : trim((string) config('research.synthesis.model'));

        $run = DB::transaction(function () use ($user, $prompt, $categoryId, $query, $filters, $b, $payload, $status, $model) {
            $run = AiRun::create([
                'uuid' => (string) Str::uuid(), 'user_id' => $user->id, 'conversation_id' => null,
                'ai_tool_id' => $prompt->ai_tool_id, 'prompt_template_id' => $prompt->id, 'status' => 'generating',
                'user_parameters_json' => [
                    'mode' => 'grounded_synthesis', 'synthesis_mode' => $status['mode'], 'category_id' => $categoryId, 'category' => $b['category']['name'] ?? null,
                    'user_message' => $query, 'filters' => $filters, 'research_tool_code' => $prompt->aiTool?->code, 'prompt_version' => $prompt->version,
                    'request_sha256' => $payload['request_sha256'], 'bundle_sha256' => hash('sha256', json_encode($b, JSON_UNESCAPED_UNICODE)),
                    'source_ids' => array_column($b['selected_sources'], 'source_id'), 'citation_ids' => array_keys($b['citation_map']),
                    'answer_behavior' => $this->behaviorProvenance($prompt),
                ],
                'model_provider' => $status['provider'], 'model_name' => $model, 'started_at' => now(),
            ]);
            $this->snapshot($run, $b);

            return $run;
        });

        if (! $bundle->hasEvidenceText()) {
            return $this->finish($run, 'insufficient_evidence', $bundle, null, 'Bukti belum cukup: semua referensi hanya berisi metadata. Provider AI tidak dipanggil.', $ip, null, $estimate);
        }

        try {
            $outcome = $this->execute($request);
        } catch (ProviderFailure $e) {
            return $this->finish($run, 'failed', $bundle, null, $e->getMessage(), $ip, $e, $estimate);
        } catch (Throwable $e) {
            // Galat tak terduga: provider nyata tidak pernah menampilkan pesan mentah (dapat memuat data sensitif).
            $msg = $status['test_mode'] ? Str::limit($e->getMessage(), 300) : SynthesisFailure::message(SynthesisFailure::PROVIDER_UNAVAILABLE);

            return $this->finish($run, 'failed', $bundle, null, $msg, $ip, new ProviderFailure(SynthesisFailure::PROVIDER_UNAVAILABLE), $estimate);
        }

        return $this->finish($run, $outcome['status'] === SynthesisValidator::VALID ? 'completed' : 'citation_failed', $bundle, $outcome, null, $ip, null, $estimate);
    }

    /** @param array<string, mixed>|null $outcome @param array<string, mixed> $estimate */
    private function finish(AiRun $run, string $status, ResearchEvidenceBundle $bundle, ?array $outcome, ?string $failure, ?string $ip, ?ProviderFailure $pf, array $estimate): AiRun
    {
        $validation = $outcome['validation'] ?? null;
        $v = $validation['status'] ?? null;
        $answer = $outcome['parsed']['data']['answer'] ?? null;
        $meta = $outcome['meta'] ?? ($pf?->meta ?? []);
        $state = match (true) {
            $pf !== null => $pf->state,
            $status === 'completed' || $status === 'insufficient_evidence' => null,
            $outcome !== null && ! $outcome['parsed']['ok'] => SynthesisFailure::SCHEMA_FAILED,
            default => SynthesisFailure::CITATION_FAILED,
        };
        $text = match (true) {
            $status === 'completed' => $answer,
            $status === 'citation_failed' => 'Hasil sintesis tidak lolos validasi sitasi ('.($v === SynthesisValidator::PARTIAL ? 'sebagian' : 'seluruhnya').'); tidak ditampilkan sebagai jawaban.',
            default => (string) $failure,
        };

        $usage = is_array($meta['usage'] ?? null) ? $meta['usage'] : null;
        $accounting = [
            'provider' => $run->model_provider, 'model' => $meta['model'] ?? $run->model_name, 'status' => $state ?? $status,
            'input_tokens' => $usage['input_tokens'] ?? null, 'output_tokens' => $usage['output_tokens'] ?? null,
            'total_tokens' => $usage['total_tokens'] ?? (isset($usage['input_tokens'], $usage['output_tokens']) ? $usage['input_tokens'] + $usage['output_tokens'] : null),
            'duration_ms' => $meta['duration_ms'] ?? null, 'attempts' => $meta['attempts'] ?? null, 'request_id' => $meta['request_id'] ?? null,
            'estimated_input_tokens' => $estimate['input_tokens'], 'max_output_tokens' => $estimate['max_output_tokens'],
            'estimated_max_cost_usd' => $estimate['max_cost_usd'], 'actual_cost_usd' => SynthesisBudget::costFromUsage($usage),
            'cost_basis' => $usage !== null ? 'usage dilaporkan provider x tarif admin' : null,
        ];

        AiRunResult::create([
            'ai_run_id' => $run->id, 'result_type' => 'primary', 'content' => $text,
            'structured_output_json' => [
                'grounded_synthesis' => true, 'contract' => GroundingRules::CONTRACT, 'validation_status' => $v ?? $status, 'failure_state' => $state,
                'test_mode' => (bool) ($meta['test_mode'] ?? $this->providers->status()['test_mode']),
                'answer_unverified' => $v === SynthesisValidator::VALID ? null : $answer,
                'claims' => $validation['claims'] ?? [], 'validation' => $validation,
                'model_limitations' => $outcome['parsed']['data']['limitations'] ?? [],
                // Keterbatasan sistem SELALU dilampirkan, tak peduli apa yang disalin model.
                'limitations' => $bundle->limitations(), 'limitations_must_be_preserved' => true,
                'sources_used' => $validation['sources_used']['verified'] ?? [], 'summary' => $bundle->summary(),
                // provider_meta.usage = format yang dibaca analitik Admin (AiProviderAnalytics).
                'provider_meta' => $meta, 'usage_accounting' => $accounting, 'failure_reason' => $failure,
                'raw_response' => isset($outcome['raw']) ? Str::limit($outcome['raw'], 20000, '') : null,
            ],
            'citations_json' => array_values(array_unique(array_merge([], ...array_map(fn ($c) => $c['valid'] ? $c['citations'] : [], $validation['claims'] ?? [])))),
        ]);

        $run->update(['status' => $status, 'finished_at' => now(), 'model_name' => $accounting['model'], 'error_message' => $status === 'failed' ? $failure : null]);
        $this->audit($run, $status, $v, $bundle, $validation, $failure, $meta, $state, $accounting, $ip);

        return $run->fresh();
    }

    /** @param array<string, mixed> $b */
    private function snapshot(AiRun $run, array $b): void
    {
        $now = now();
        foreach ($b['selected_sources'] as $s) {
            AiRunEvidence::create([
                'ai_run_id' => $run->id, 'entry_type' => 'source', 'evidence_id' => null, 'citation_id' => $s['source_id'], 'source_id' => $s['source_id'],
                'source_kind' => $s['kind'] === 'internal' ? Evidence::KIND_INTERNAL : ($s['evidence_level'] === Evidence::FULL_TEXT ? Evidence::KIND_EXTERNAL_FULLTEXT : Evidence::KIND_EXTERNAL_ABSTRACT),
                'publication_id' => $s['id'], 'evidence_level' => $s['evidence_level'], 'claim_support_level' => $s['claim_support'],
                'title' => $s['title'], 'authors_json' => $s['authors'], 'publication_year' => $s['year'], 'doi' => $s['doi'], 'provider' => $s['providers'][0] ?? null,
                'source_url' => $s['url'], 'license' => $s['license'] ?? null, 'allowed_claims_json' => [Evidence::CLAIM_BIBLIOGRAPHIC],
                'relevance_json' => ['source_rank' => $s['rank'], 'source_score' => $s['relevance_score']],
                'provenance_json' => ['providers' => $s['providers'], 'availability' => $s['availability'], 'note' => $s['note']], 'created_at' => $now,
            ]);
        }
        foreach ($b['evidence_items'] as $i) {
            AiRunEvidence::create([
                'ai_run_id' => $run->id, 'entry_type' => 'evidence', 'evidence_id' => $i['evidence_id'], 'citation_id' => $i['citation_id'], 'source_id' => $i['source_id'],
                'source_kind' => $i['source_kind'], 'publication_id' => $i['publication_id'], 'evidence_level' => $i['evidence_level'], 'claim_support_level' => $i['claim_support'],
                'title' => $i['title'], 'authors_json' => $i['authors'], 'publication_year' => $i['year'], 'doi' => $i['doi'], 'provider' => $i['provider'], 'source_url' => $i['source_url'],
                'section' => $i['section'], 'page_start' => $i['page_start'], 'page_end' => $i['page_end'], 'evidence_text' => $i['text'], 'text_sha256' => hash('sha256', $i['text']),
                'source_checksum' => $i['source_checksum'], 'chunk_checksum' => $i['chunk_checksum'], 'license' => $i['license'], 'allowed_claims_json' => $i['allowed_claims'],
                'relevance_json' => $i['relevance'], 'provenance_json' => $i['provenance'], 'created_at' => $now,
            ]);
        }
    }

    /** @param array<string, mixed> $accounting */
    private function audit(AiRun $run, string $status, ?string $validation, ResearchEvidenceBundle $bundle, ?array $v, ?string $failure, array $meta, ?string $state, array $accounting, ?string $ip): void
    {
        $action = match (true) {
            $status === 'completed' => 'research.synthesis_completed',
            $status === 'insufficient_evidence' => 'research.synthesis_insufficient_evidence',
            $status === 'failed' => 'research.synthesis_failed',
            $validation === SynthesisValidator::PARTIAL => 'research.synthesis_partial',
            default => 'research.synthesis_citation_failed',
        };

        try {
            AuditLog::create([
                'user_id' => $run->user_id, 'action' => $action, 'auditable_type' => AiRun::class, 'auditable_id' => $run->id, 'ip_address' => $ip,
                'metadata_json' => [
                    'ai_run_id' => $run->id, 'research_tool_code' => data_get($run->user_parameters_json, 'research_tool_code'), 'prompt_template_id' => $run->prompt_template_id,
                    'prompt_version' => data_get($run->user_parameters_json, 'prompt_version'), 'provider' => $run->model_provider, 'model' => $run->model_name,
                    'synthesis_mode' => data_get($run->user_parameters_json, 'synthesis_mode'),
                    'category_id' => data_get($run->user_parameters_json, 'category_id'), 'test_mode' => (bool) ($meta['test_mode'] ?? true),
                    'validation_status' => $validation ?? $status, 'failure_state' => $state, 'citation_ids' => array_keys($bundle->citationMap()),
                    'issue_codes' => array_values(array_unique(array_column($v['issues'] ?? [], 'code'))),
                    'counts' => $v['counts'] ?? null, 'bundle_sha256' => data_get($run->user_parameters_json, 'bundle_sha256'), 'failure_reason' => $failure,
                    'usage' => array_intersect_key($accounting, array_flip(['input_tokens', 'output_tokens', 'total_tokens', 'duration_ms', 'attempts', 'request_id', 'estimated_max_cost_usd', 'actual_cost_usd'])),
                ],
            ]);
        } catch (Throwable) {
            // audit tidak boleh menggagalkan hasil
        }
    }

    /** @param array<string, mixed> $status @param array<string, mixed> $estimate */
    private function auditRejected(User $user, PromptTemplate $prompt, int $categoryId, array $status, array $estimate, SynthesisRejected $e, ?string $ip): void
    {
        $action = $e->state === SynthesisFailure::RATE_LIMITED ? 'research.synthesis_rate_limited' : 'research.synthesis_budget_exceeded';

        try {
            AuditLog::create([
                'user_id' => $user->id, 'action' => $action, 'auditable_type' => PromptTemplate::class, 'auditable_id' => $prompt->id, 'ip_address' => $ip,
                'metadata_json' => ['reason' => $e->reason, 'failure_state' => $e->state, 'provider' => $status['provider'], 'research_tool_code' => $prompt->aiTool?->code,
                    'prompt_version' => $prompt->version, 'category_id' => $categoryId, 'estimated_input_tokens' => $estimate['input_tokens'], 'estimated_max_cost_usd' => $estimate['max_cost_usd']],
            ]);
        } catch (Throwable) {
            // audit tidak boleh menggagalkan penolakan
        }
    }

    /** @return list<array{role: string, content: string}> */
    private function trimContext(array $messages): array
    {
        $n = max(0, (int) config('research.synthesis.context_messages', 4));
        if ($n === 0) {
            return [];
        }

        return array_values(array_map(
            fn ($m) => ['role' => in_array($m['role'] ?? '', ['user', 'assistant'], true) ? $m['role'] : 'user', 'content' => Str::limit((string) ($m['content'] ?? ''), 600, '…')],
            array_slice(array_filter($messages, fn ($m) => is_array($m) && filled($m['content'] ?? null)), -$n)
        ));
    }

    private function behaviorProvenance(PromptTemplate $prompt): ?array
    {
        try {
            $behavior = $this->behavior->safe('research', $prompt->aiTool?->code);

            return $this->behavior->payload($behavior) !== null ? $this->behavior->provenance($behavior) : null;
        } catch (Throwable) {
            return null;
        }
    }
}
