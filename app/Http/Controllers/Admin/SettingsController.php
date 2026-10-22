<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\Research\ResearchSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class SettingsController extends Controller
{
    public function index(
        ResearchSettingsService $settings
    ): View {
        return view(
            'admin.settings.index',
            [
                /*
                |--------------------------------------------------------------------------
                | Settings Untuk Blade
                |--------------------------------------------------------------------------
                |
                | effective() menghasilkan nested array:
                |
                | [
                |     'ai' => [
                |         'provider' => 'mock',
                |     ],
                |     'retrieval' => [
                |         'minimum_evidence' => 2,
                |     ],
                | ]
                |
                | Jadi data_get($settings, 'retrieval.minimum_evidence')
                | bekerja dengan benar.
                |
                */

                'settings' =>
                    $settings
                        ->effective(),

                /*
                 * Hanya status.
                 *
                 * Secret/API key tidak pernah dirender
                 * ke halaman browser.
                 */

                'apiKeyConfigured' =>
                    filled(
                        config(
                            'services.openai.api_key'
                        )
                    ),

                'queueConnection' =>
                    (string)
                        config(
                            'queue.default',
                            'sync'
                        ),

                'sourceSyncProvider' =>
                    (string)
                        config(
                            'source-sync.provider',
                            'mock'
                        ),

                'documentTypes' =>
                    config(
                        'research.documents.types',
                        []
                    ),

                'citationStyles' =>
                    config(
                        'research.documents.citation_styles',
                        []
                    ),
            ]
        );
    }

    public function update(
        Request $request,
        ResearchSettingsService $settings
    ): RedirectResponse {
        $documentTypes =
            config(
                'research.documents.types',
                []
            );

        $citationStyles =
            config(
                'research.documents.citation_styles',
                []
            );

        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        */

        $data =
            $request->validate([

                /*
                 * AI
                 */

                'ai_provider' => [
                    'required',
                    Rule::in([
                        'mock',
                        'openai',
                    ]),
                ],

                'ai_model' => [
                    'nullable',
                    'string',
                    'max:160',
                ],

                'ai_timeout' => [
                    'required',
                    'integer',
                    'min:10',
                    'max:300',
                ],

                'max_output_tokens' => [
                    'required',
                    'integer',
                    'min:256',
                    'max:16000',
                ],

                'context_messages' => [
                    'required',
                    'integer',
                    'min:0',
                    'max:30',
                ],

                /*
                 * Ingestion
                 */

                'upload_limit_kb' => [
                    'required',
                    'integer',
                    'min:1024',
                    'max:512000',
                ],

                'chunk_words' => [
                    'required',
                    'integer',
                    'min:100',
                    'max:3000',
                ],

                'chunk_overlap_words' => [
                    'required',
                    'integer',
                    'min:0',
                    'max:1000',
                ],

                'minimum_chunk_words' => [
                    'required',
                    'integer',
                    'min:10',
                    'max:1000',
                ],

                /*
                 * Retrieval
                 */

                'candidate_limit' => [
                    'required',
                    'integer',
                    'min:20',
                    'max:5000',
                ],

                'evidence_limit' => [
                    'required',
                    'integer',
                    'min:1',
                    'max:30',
                ],

                'max_per_document' => [
                    'required',
                    'integer',
                    'min:1',
                    'max:10',
                ],

                'minimum_evidence' => [
                    'required',
                    'integer',
                    'min:1',
                    'max:10',
                ],

                'minimum_score' => [
                    'required',
                    'numeric',
                    'min:0',
                    'max:1',
                ],

                'excerpt_length' => [
                    'required',
                    'integer',
                    'min:200',
                    'max:3000',
                ],

                /*
                 * Access
                 */

                'profile_gate' => [
                    'required',
                    'integer',
                    'min:0',
                    'max:100',
                ],

                /*
                 * Queue
                 */

                'research_queue' => [
                    'required',
                    'string',
                    'max:80',
                    'regex:/^[a-zA-Z0-9._-]+$/',
                ],

                'ingestion_queue' => [
                    'required',
                    'string',
                    'max:80',
                    'regex:/^[a-zA-Z0-9._-]+$/',
                ],

                'live_refresh_seconds' => [
                    'required',
                    'integer',
                    'min:1',
                    'max:30',
                ],

                /*
                 * Document
                 */

                'default_document_type' => [
                    'required',
                    Rule::in(
                        $documentTypes
                    ),
                ],

                'default_citation_style' => [
                    'required',
                    Rule::in(
                        $citationStyles
                    ),
                ],
            ]);

        /*
        |--------------------------------------------------------------------------
        | Cross-field Validation
        |--------------------------------------------------------------------------
        */

        if (
            (int)
                $data[
                    'chunk_overlap_words'
                ]
            >=
            (int)
                $data[
                    'chunk_words'
                ]
        ) {
            throw ValidationException::withMessages([
                'chunk_overlap_words' =>
                    'Chunk overlap harus lebih kecil daripada chunk words.',
            ]);
        }

        if (
            (int)
                $data[
                    'minimum_chunk_words'
                ]
            >
            (int)
                $data[
                    'chunk_words'
                ]
        ) {
            throw ValidationException::withMessages([
                'minimum_chunk_words' =>
                    'Minimum chunk words tidak boleh lebih besar daripada chunk words.',
            ]);
        }

        if (
            (int)
                $data[
                    'minimum_evidence'
                ]
            >
            (int)
                $data[
                    'evidence_limit'
                ]
        ) {
            throw ValidationException::withMessages([
                'minimum_evidence' =>
                    'Minimum evidence tidak boleh melebihi evidence limit.',
            ]);
        }

        if (
            (int)
                $data[
                    'max_per_document'
                ]
            >
            (int)
                $data[
                    'evidence_limit'
                ]
        ) {
            throw ValidationException::withMessages([
                'max_per_document' =>
                    'Max per document tidak boleh melebihi evidence limit.',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | BEFORE Snapshot
        |--------------------------------------------------------------------------
        |
        | PENTING:
        |
        | Audit memakai flat structure:
        |
        | [
        |     'ai.provider' => 'mock',
        |     'retrieval.minimum_evidence' => 2,
        | ]
        |
        */

        $before =
            $settings
                ->effectiveFlat();

        /*
        |--------------------------------------------------------------------------
        | Persist Whitelisted Runtime Settings
        |--------------------------------------------------------------------------
        */

        $settings->persist(
            [
                /*
                 * AI
                 */

                'ai.provider' =>
                    $data[
                        'ai_provider'
                    ],

                'ai.model' =>
                    $data[
                        'ai_model'
                    ]
                    ??
                    '',

                'ai.timeout' =>
                    $data[
                        'ai_timeout'
                    ],

                'ai.max_output_tokens' =>
                    $data[
                        'max_output_tokens'
                    ],

                'ai.context_messages' =>
                    $data[
                        'context_messages'
                    ],

                /*
                 * Ingestion
                 */

                'ingestion.max_upload_kb' =>
                    $data[
                        'upload_limit_kb'
                    ],

                'ingestion.chunk_words' =>
                    $data[
                        'chunk_words'
                    ],

                'ingestion.chunk_overlap_words' =>
                    $data[
                        'chunk_overlap_words'
                    ],

                'ingestion.minimum_chunk_words' =>
                    $data[
                        'minimum_chunk_words'
                    ],

                /*
                 * Retrieval
                 */

                'retrieval.candidate_limit' =>
                    $data[
                        'candidate_limit'
                    ],

                'retrieval.evidence_limit' =>
                    $data[
                        'evidence_limit'
                    ],

                'retrieval.max_per_document' =>
                    $data[
                        'max_per_document'
                    ],

                'retrieval.minimum_evidence' =>
                    $data[
                        'minimum_evidence'
                    ],

                'retrieval.minimum_score' =>
                    $data[
                        'minimum_score'
                    ],

                'retrieval.excerpt_length' =>
                    $data[
                        'excerpt_length'
                    ],

                /*
                 * Access
                 */

                'access.profile_gate' =>
                    $data[
                        'profile_gate'
                    ],

                /*
                 * Queue
                 */

                'queue.research_queue' =>
                    $data[
                        'research_queue'
                    ],

                'queue.ingestion_queue' =>
                    $data[
                        'ingestion_queue'
                    ],

                'queue.live_refresh_seconds' =>
                    $data[
                        'live_refresh_seconds'
                    ],

                /*
                 * Documents
                 */

                'documents.default_type' =>
                    $data[
                        'default_document_type'
                    ],

                'documents.default_citation_style' =>
                    $data[
                        'default_citation_style'
                    ],
            ],
            $request
                ->session()
                ->get(
                    'research_admin_user_id'
                )
        );

        /*
        |--------------------------------------------------------------------------
        | AFTER Snapshot
        |--------------------------------------------------------------------------
        */

        $after =
            $settings
                ->effectiveFlat();

        /*
        |--------------------------------------------------------------------------
        | Detect Changed Keys
        |--------------------------------------------------------------------------
        */

        $changed = [];

        foreach (
            $after as
            $key =>
            $value
        ) {
            if (
                (
                    $before[
                        $key
                    ]
                    ??
                    null
                )
                !=
                $value
            ) {
                $changed[] =
                    $key;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Audit
        |--------------------------------------------------------------------------
        */

        $this->audit(
            $request,
            $changed,
            $after
        );

        return back()
            ->with(
                'success',
                'Runtime Settings berhasil disimpan. API key dan credential tetap hanya berasal dari environment server.'
            );
    }

    private function audit(
        Request $request,
        array $changed,
        array $after
    ): void {
        try {
            AuditLog::create([
                'user_id' =>
                    $request
                        ->session()
                        ->get(
                            'research_admin_user_id'
                        ),

                'action' =>
                    'settings.updated',

                'auditable_type' =>
                    'research_settings',

                'auditable_id' =>
                    null,

                'metadata_json' => [
                    'changed_keys' =>
                        $changed,

                    /*
                     * Snapshot hanya berisi value dari whitelist
                     * ResearchSettingsService.
                     *
                     * Tidak termasuk:
                     *
                     * OPENAI_API_KEY
                     * Google Client Secret
                     * Source Sync Token
                     * password
                     */

                    'settings' =>
                        $after,
                ],

                'ip_address' =>
                    $request
                        ->ip(),
            ]);
        } catch (
            Throwable
        ) {
            /*
             * Kegagalan Audit Log tidak boleh
             * membuat Settings gagal disimpan.
             */
        }
    }
}