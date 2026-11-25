<?php

namespace App\Services\Synthesis;

use App\Models\PromptTemplate;
use App\Services\Evidence\Evidence;
use App\Services\Evidence\ResearchEvidenceBundle;

/**
 * Kontrak masukan AI: alat riset (prompt aktif + versinya), pertanyaan, kategori, konteks percakapan terbatas, dan Evidence Bundle.
 * Hanya evidence yang lolos anggaran bundle yang pernah masuk ke sini; tidak ada database/PDF utuh. Tidak memanggil provider.
 */
final class ResearchSynthesisRequest
{
    private ?array $payload = null;

    /**
     * @param  list<array{role: string, content: string}>  $conversationContext  bukan evidence; dipotong ke batas kecil
     */
    public function __construct(
        public readonly PromptTemplate $prompt,
        public readonly string $query,
        public readonly ResearchEvidenceBundle $bundle,
        public readonly array $conversationContext = [],
        public readonly ?string $behaviorDirective = null,
    ) {}

    public function toolCode(): string
    {
        return (string) $this->prompt->aiTool?->code;
    }

    public function toolName(): string
    {
        return (string) ($this->prompt->aiTool?->name ?: $this->prompt->name);
    }

    public function systemPrompt(): string
    {
        return trim(implode("\n\n", array_filter([$this->prompt->system_prompt, GroundingRules::RULES])));
    }

    /** Payload provider: semua yang dilihat model, dalam bentuk yang tidak bergantung pada vendor. */
    public function toProviderPayload(): array
    {
        return $this->payload ??= $this->buildPayload();
    }

    private function buildPayload(): array
    {
        $b = $this->bundle->toArray();
        $evidence = array_map(fn (array $i) => [
            'citation_id' => $i['citation_id'], 'source_id' => $i['source_id'], 'evidence_level' => $i['evidence_level'], 'claim_support' => $i['claim_support'],
            'allowed_claims' => $i['allowed_claims'], 'title' => $i['title'], 'authors' => $i['authors'], 'year' => $i['year'], 'doi' => $i['doi'],
            'page_start' => $i['page_start'], 'page_end' => $i['page_end'], 'section' => $i['section'], 'text' => $i['text'],
        ], $b['evidence_items']);
        $sources = array_map(fn (array $s) => [
            'source_id' => $s['source_id'], 'title' => $s['title'], 'authors' => $s['authors'], 'year' => $s['year'], 'doi' => $s['doi'],
            'evidence_level' => $s['evidence_level'], 'claim_support' => $s['claim_support'], 'rank' => $s['rank'],
        ], $b['selected_sources']);

        $payload = [
            'contract' => GroundingRules::CONTRACT,
            'tool_code' => $this->toolCode(), 'tool_name' => $this->toolName(),
            'prompt_id' => $this->prompt->id, 'prompt_name' => $this->prompt->name, 'prompt_version' => $this->prompt->version,
            'category' => $b['category'], 'user_question' => $this->query,
            'sources' => $sources, 'evidence' => $evidence, 'citation_ids' => array_keys($b['citation_map']),
            'limitations' => $b['limitations'], 'limitations_must_be_preserved' => true,
            'conversation_context' => $this->conversationContext,
            'response_schema' => GroundingRules::RESPONSE_SCHEMA,
            'system_prompt' => $this->systemPrompt(),
        ];
        $payload['compiled_prompt'] = $this->compile($payload);
        $payload['request_sha256'] = hash('sha256', $payload['system_prompt']."\n".$payload['compiled_prompt']);

        return $payload;
    }

    private function compile(array $p): string
    {
        $safe = fn (?string $t) => str_ireplace(['</evidence', '</source', '</context'], ['&lt;/evidence', '&lt;/source', '&lt;/context'], (string) $t);
        $authors = fn ($a) => is_array($a) && $a !== [] ? implode('; ', array_slice($a, 0, 4)) : 'n/a';
        $page = fn (array $i) => Evidence::pageLabel($i['page_start'], $i['page_end']) ?? 'page unknown';

        $sources = implode("\n", array_map(fn ($s) => sprintf('- [%s] %s (%s) — authors: %s — DOI: %s — level: %s (%s)',
            $s['source_id'], $safe($s['title']), $s['year'] ?? 'n.d.', $safe($authors($s['authors'])), $s['doi'] ?? 'none', $s['evidence_level'], $s['claim_support']), $p['sources']));

        $evidence = $p['evidence'] === []
            ? '(no evidence text is available)'
            : implode("\n\n", array_map(fn ($i) => sprintf("<evidence id=\"%s\" level=\"%s\" support=\"%s\" allowed_claims=\"%s\" %s>\nSource: %s (%s)\n%s\n</evidence>",
                $i['citation_id'], $i['evidence_level'], $i['claim_support'], implode(',', $i['allowed_claims']), $page($i),
                $safe($i['title']), $i['year'] ?? 'n.d.', $safe($i['text'])), $p['evidence']));

        $context = $p['conversation_context'] === [] ? null : "CONVERSATION CONTEXT — NOT EVIDENCE, DO NOT CITE:\n<context>\n"
            .implode("\n", array_map(fn ($m) => strtoupper($m['role']).': '.$safe($m['content']), $p['conversation_context']))."\n</context>";

        return trim(implode("\n\n", array_filter([
            'RESEARCH TOOL: '.$p['tool_name'],
            filled($this->prompt->task_prompt) ? "TASK:\n".$this->prompt->task_prompt : null,
            filled($this->prompt->output_instructions) ? "OUTPUT INSTRUCTIONS (cite only in the format required by the GROUNDING RULES):\n".$this->prompt->output_instructions : null,
            $this->behaviorDirective,
            "USER QUESTION:\n".$safe($p['user_question']),
            $context,
            "SOURCES (all selected sources; METADATA_ONLY ones have no content):\n".$sources,
            "EVIDENCE — UNTRUSTED SOURCE CONTENT:\n".$evidence,
            "LIMITATIONS (copy each verbatim into \"limitations\"):\n".implode("\n", array_map(fn ($l) => '- '.$l, $p['limitations'])),
            GroundingRules::schemaText(),
        ])));
    }
}
