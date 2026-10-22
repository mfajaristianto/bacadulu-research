<?php

namespace App\Services\Research;

use App\Models\ResearchSetting;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ResearchSettingsService
{
    public function definitions(): array
    {
        return [

            /*
            |--------------------------------------------------------------------------
            | AI
            |--------------------------------------------------------------------------
            */

            'ai.provider' => [
                'config' => 'research.ai_provider',
                'type' => 'string',
                'group' => 'ai',
                'description' => 'AI provider runtime.',
            ],

            'ai.model' => [
                'config' => 'research.ai_model',
                'type' => 'string',
                'group' => 'ai',
                'description' => 'AI model name.',
            ],

            'ai.timeout' => [
                'config' => 'research.ai.timeout',
                'type' => 'integer',
                'group' => 'ai',
                'description' => 'AI request timeout in seconds.',
            ],

            'ai.max_output_tokens' => [
                'config' => 'research.ai.max_output_tokens',
                'type' => 'integer',
                'group' => 'ai',
                'description' => 'Maximum generated output tokens.',
            ],

            'ai.context_messages' => [
                'config' => 'research.ai.context_messages',
                'type' => 'integer',
                'group' => 'ai',
                'description' => 'Conversation messages supplied as context.',
            ],

            /*
            |--------------------------------------------------------------------------
            | Ingestion
            |--------------------------------------------------------------------------
            */

            'ingestion.max_upload_kb' => [
                'config' => 'research.ingestion.max_upload_kb',
                'type' => 'integer',
                'group' => 'ingestion',
                'description' => 'Maximum PDF upload size.',
            ],

            'ingestion.chunk_words' => [
                'config' => 'research.ingestion.chunk_words',
                'type' => 'integer',
                'group' => 'ingestion',
                'description' => 'Target words per document chunk.',
            ],

            'ingestion.chunk_overlap_words' => [
                'config' => 'research.ingestion.chunk_overlap_words',
                'type' => 'integer',
                'group' => 'ingestion',
                'description' => 'Word overlap between chunks.',
            ],

            'ingestion.minimum_chunk_words' => [
                'config' => 'research.ingestion.minimum_chunk_words',
                'type' => 'integer',
                'group' => 'ingestion',
                'description' => 'Minimum words accepted for a chunk.',
            ],

            /*
            |--------------------------------------------------------------------------
            | Retrieval
            |--------------------------------------------------------------------------
            */

            'retrieval.candidate_limit' => [
                'config' => 'research.retrieval.candidate_limit',
                'type' => 'integer',
                'group' => 'retrieval',
                'description' => 'Maximum retrieval candidates.',
            ],

            'retrieval.evidence_limit' => [
                'config' => 'research.retrieval.evidence_limit',
                'type' => 'integer',
                'group' => 'retrieval',
                'description' => 'Maximum evidence supplied to synthesis.',
            ],

            'retrieval.max_per_document' => [
                'config' => 'research.retrieval.max_per_document',
                'type' => 'integer',
                'group' => 'retrieval',
                'description' => 'Maximum evidence from one document.',
            ],

            'retrieval.minimum_evidence' => [
                'config' => 'research.retrieval.minimum_evidence',
                'type' => 'integer',
                'group' => 'retrieval',
                'description' => 'Minimum evidence before synthesis is allowed.',
            ],

            'retrieval.minimum_score' => [
                'config' => 'research.retrieval.minimum_score',
                'type' => 'float',
                'group' => 'retrieval',
                'description' => 'Minimum retrieval relevance score.',
            ],

            'retrieval.excerpt_length' => [
                'config' => 'research.retrieval.excerpt_length',
                'type' => 'integer',
                'group' => 'retrieval',
                'description' => 'Maximum evidence excerpt length.',
            ],

            /*
            |--------------------------------------------------------------------------
            | Access
            |--------------------------------------------------------------------------
            */

            'access.profile_gate' => [
                'config' => 'research.profile_gate',
                'type' => 'integer',
                'group' => 'access',
                'description' => 'Minimum profile completion percentage.',
            ],

            /*
            |--------------------------------------------------------------------------
            | Queue
            |--------------------------------------------------------------------------
            */

            'queue.research_queue' => [
                'config' => 'research.queue.research_queue',
                'type' => 'string',
                'group' => 'queue',
                'description' => 'Queue used for AI research.',
            ],

            'queue.ingestion_queue' => [
                'config' => 'research.queue.ingestion_queue',
                'type' => 'string',
                'group' => 'queue',
                'description' => 'Queue used for source ingestion.',
            ],

            'queue.live_refresh_seconds' => [
                'config' => 'research.queue.live_refresh_seconds',
                'type' => 'integer',
                'group' => 'queue',
                'description' => 'Workspace/admin live refresh interval.',
            ],

            /*
            |--------------------------------------------------------------------------
            | Document Defaults
            |--------------------------------------------------------------------------
            */

            'documents.default_type' => [
                'config' => 'research.documents.default_type',
                'type' => 'string',
                'group' => 'documents',
                'description' => 'Default generated document type.',
            ],

            'documents.default_citation_style' => [
                'config' => 'research.documents.default_citation_style',
                'type' => 'string',
                'group' => 'documents',
                'description' => 'Default citation style.',
            ],

        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Database Overrides → Laravel Config
    |--------------------------------------------------------------------------
    */

    public function applyOverrides(): void
    {
        try {
            if (! Schema::hasTable('research_settings')) {
                return;
            }

            $definitions =
                $this->definitions();

            $settings =
                ResearchSetting::query()
                    ->get();

            foreach ($settings as $setting) {
                $definition =
                    $definitions[$setting->key]
                    ?? null;

                if (! $definition) {
                    continue;
                }

                config()->set(
                    $definition['config'],
                    $this->cast(
                        $setting->value,
                        $definition['type']
                    )
                );
            }
        } catch (Throwable) {
            /*
             * Laravel tetap harus bisa boot ketika:
             *
             * - database belum tersedia;
             * - migration belum dijalankan;
             * - artisan install sedang berjalan.
             */
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Persist Whitelisted Runtime Settings
    |--------------------------------------------------------------------------
    */

    public function persist(
        array $values,
        ?int $updatedBy = null
    ): void {
        $definitions =
            $this->definitions();

        foreach ($values as $key => $value) {
            $definition =
                $definitions[$key]
                ?? null;

            /*
             * Hanya setting di definitions() yang boleh disimpan.
             *
             * Artinya key liar seperti:
             *
             * openai.api_key
             * password
             * token
             *
             * otomatis diabaikan.
             */

            if (! $definition) {
                continue;
            }

            $normalized =
                $this->cast(
                    $value,
                    $definition['type']
                );

            ResearchSetting::query()
                ->updateOrCreate(
                    [
                        'key' => $key,
                    ],
                    [
                        'group' =>
                            $definition['group'],

                        'value' =>
                            $this->serialize(
                                $normalized,
                                $definition['type']
                            ),

                        'value_type' =>
                            $definition['type'],

                        'description' =>
                            $definition['description'],

                        'updated_by' =>
                            $updatedBy,
                    ]
                );

            /*
             * Request yang sedang berjalan langsung memakai
             * setting yang baru.
             */

            config()->set(
                $definition['config'],
                $normalized
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Nested Effective Settings
    |--------------------------------------------------------------------------
    |
    | Dipakai oleh Blade:
    |
    | data_get(
    |     $settings,
    |     'retrieval.minimum_evidence'
    | )
    |
    | Output:
    |
    | [
    |     'ai' => [
    |         'provider' => 'mock',
    |     ],
    |
    |     'retrieval' => [
    |         'minimum_evidence' => 2,
    |     ],
    | ]
    |
    */

    public function effective(): array
    {
        $result = [];

        foreach (
            $this->effectiveFlat()
            as
            $key => $value
        ) {
            data_set(
                $result,
                $key,
                $value
            );
        }

        return $result;
    }

    /*
    |--------------------------------------------------------------------------
    | Flat Effective Settings
    |--------------------------------------------------------------------------
    |
    | Dipakai untuk:
    |
    | - before/after diff
    | - Audit Log
    | - automated regression tests
    |
    */

    public function effectiveFlat(): array
    {
        $result = [];

        foreach (
            $this->definitions()
            as
            $key => $definition
        ) {
            $result[$key] =
                config(
                    $definition['config'],
                    $this->fallback(
                        $key
                    )
                );
        }

        return $result;
    }

    private function fallback(
        string $key
    ): mixed {
        return match ($key) {

            'documents.default_type' =>
                'Research Article',

            'documents.default_citation_style' =>
                'APA 7',

            default =>
                null,

        };
    }

    private function cast(
        mixed $value,
        string $type
    ): mixed {
        return match ($type) {

            'integer' =>
                (int) $value,

            'float' =>
                (float) $value,

            'boolean' =>
                filter_var(
                    $value,
                    FILTER_VALIDATE_BOOL
                ),

            default =>
                trim(
                    (string) $value
                ),

        };
    }

    private function serialize(
        mixed $value,
        string $type
    ): string {
        if ($type === 'boolean') {
            return $value
                ? '1'
                : '0';
        }

        return (string) $value;
    }
}