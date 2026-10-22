<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Admin
    |--------------------------------------------------------------------------
    */

    'admin_path' => env(
        'BACADULU_ADMIN_PATH',
        'panel-adminbaca-research'
    ),

    'admin_email' => env(
        'ADMIN_EMAIL'
    ),

    /*
    | Prefer ADMIN_PASSWORD_HASH (generate with `php artisan research:admin-password-hash`).
    | ADMIN_PASSWORD (plaintext) is only a fallback for installs that have not
    | migrated yet, and is ignored once a hash is configured.
    */

    'admin_password_hash' => env(
        'ADMIN_PASSWORD_HASH'
    ),

    'admin_password' => env(
        'ADMIN_PASSWORD'
    ),

    /*
    |--------------------------------------------------------------------------
    | Google OAuth (disabled by default; source code remains intact)
    |--------------------------------------------------------------------------
    |
    | Set RESEARCH_GOOGLE_AUTH_ENABLED=true to reactivate Google sign-in.
    | The login/register interface itself is unchanged.
    |
    */

    'google_auth_enabled' => (bool) env(
        'RESEARCH_GOOGLE_AUTH_ENABLED',
        false
    ),

    /*
    |--------------------------------------------------------------------------
    | Profile
    |--------------------------------------------------------------------------
    */

    'profile_gate' => (int) env(
        'RESEARCH_PROFILE_GATE',
        80
    ),

    // Full profile remains encouraged, but the signup fields are enough to start research.
    'profile_fields_optional' => (bool) env('RESEARCH_PROFILE_FIELDS_OPTIONAL', true),

    'require_email_verification' => (bool) env(
        'RESEARCH_REQUIRE_EMAIL_VERIFICATION',
        false
    ),

    /*
    |--------------------------------------------------------------------------
    | AI
    |--------------------------------------------------------------------------
    */

    'ai_provider' => env(
        'AI_PROVIDER',
        'mock'
    ),

    'ai_model' => env(
        'AI_MODEL',
        ''
    ),

    'ai' => [

        'timeout' => (int) env(
            'AI_TIMEOUT',
            90
        ),

        'max_output_tokens' => (int) env(
            'AI_MAX_OUTPUT_TOKENS',
            3500
        ),

        'context_messages' => (int) env(
            'AI_CONTEXT_MESSAGES',
            8
        ),

    ],

    /*
    |--------------------------------------------------------------------------
    | Source Ingestion
    |--------------------------------------------------------------------------
    */

    'ingestion' => [

        'disk' => env(
            'RESEARCH_SOURCE_DISK',
            'research_sources'
        ),

        'max_upload_kb' => (int) env(
            'RESEARCH_SOURCE_MAX_KB',
            102400
        ),

        /*
         * Optional.
         *
         * Jika pdftotext tidak tersedia,
         * PdfTextExtractor dapat memakai fallback parser.
         */
        'pdftotext_binary' => env(
            'PDFTOTEXT_BINARY',
            'pdftotext'
        ),

        'parser_timeout' => (int) env(
            'PDFTOTEXT_TIMEOUT',
            60
        ),

        'chunk_words' => (int) env(
            'RESEARCH_CHUNK_WORDS',
            500
        ),

        'chunk_overlap_words' => (int) env(
            'RESEARCH_CHUNK_OVERLAP_WORDS',
            75
        ),

        'minimum_chunk_words' => (int) env(
            'RESEARCH_MINIMUM_CHUNK_WORDS',
            60
        ),

    ],

    /*
    |--------------------------------------------------------------------------
    | Retrieval
    |--------------------------------------------------------------------------
    */

    'retrieval' => [

        'candidate_limit' => (int) env(
            'RESEARCH_RETRIEVAL_CANDIDATE_LIMIT',
            400
        ),

        'evidence_limit' => (int) env(
            'RESEARCH_EVIDENCE_LIMIT',
            8
        ),

        'max_per_document' => (int) env(
            'RESEARCH_EVIDENCE_MAX_PER_DOCUMENT',
            3
        ),

        'minimum_evidence' => (int) env(
            'RESEARCH_MINIMUM_EVIDENCE',
            2
        ),

        'minimum_score' => (float) env(
            'RESEARCH_MINIMUM_RETRIEVAL_SCORE',
            0.15
        ),

        'excerpt_length' => (int) env(
            'RESEARCH_EVIDENCE_EXCERPT_LENGTH',
            900
        ),

    ],

    /*
    |--------------------------------------------------------------------------
    | Queue + Live Processing
    |--------------------------------------------------------------------------
    */

    'queue' => [

        /*
         * AI Research queue.
         */
        'research_queue' => env(
            'RESEARCH_QUEUE_NAME',
            'research'
        ),

        /*
         * Kita pakai ini di #6B untuk PDF ingestion.
         */
        'ingestion_queue' => env(
            'RESEARCH_INGESTION_QUEUE_NAME',
            'ingestion'
        ),

        /*
         * Auto-refresh Workspace selama AI Run pending.
         */
        'live_refresh_seconds' => (int) env(
            'RESEARCH_LIVE_REFRESH_SECONDS',
            2
        ),

    ],

    /*
    |--------------------------------------------------------------------------
    | Document Engine
    |--------------------------------------------------------------------------
    */

    'documents' => [

        'disk' => env(
            'RESEARCH_OUTPUT_DISK',
            'research_outputs'
        ),

        'types' => [
            'Research Article',
            'Literature Review',
            'Research Proposal',
            'Research Background',
            'Research Gap',
            'Theory Review',
            'Variable Definition',
            'Hypothesis Development',
            'Conference Paper',
            'Book Chapter',
            'Research Report',
        ],

        'citation_styles' => [
            'APA 7',
            'IEEE',
            'Harvard',
        ],

    ],

];