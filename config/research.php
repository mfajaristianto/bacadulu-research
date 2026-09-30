<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Admin
    |--------------------------------------------------------------------------
    */

    'admin_path'
        => env(
            'BACADULU_ADMIN_PATH',
            'panel-adminbaca-research'
        ),

    'admin_email'
        => env(
            'ADMIN_EMAIL'
        ),

    'admin_password'
        => env(
            'ADMIN_PASSWORD'
        ),

    /*
    |--------------------------------------------------------------------------
    | Profile
    |--------------------------------------------------------------------------
    */

    'profile_gate'
        => (int) env(
            'RESEARCH_PROFILE_GATE',
            80
        ),

    'require_email_verification'
        => (bool) env(
            'RESEARCH_REQUIRE_EMAIL_VERIFICATION',
            false
        ),

    /*
    |--------------------------------------------------------------------------
    | AI
    |--------------------------------------------------------------------------
    */

    'ai_provider'
        => env(
            'AI_PROVIDER',
            'mock'
        ),

    'ai_model'
        => env(
            'AI_MODEL',
            ''
        ),

    'ai'
        => [
            'timeout'
                => (int) env(
                    'AI_TIMEOUT',
                    90
                ),

            'max_output_tokens'
                => (int) env(
                    'AI_MAX_OUTPUT_TOKENS',
                    3500
                ),

            'context_messages'
                => (int) env(
                    'AI_CONTEXT_MESSAGES',
                    8
                ),
        ],

    /*
    |--------------------------------------------------------------------------
    | Source Ingestion
    |--------------------------------------------------------------------------
    */

    'ingestion'
        => [
            'disk'
                => env(
                    'RESEARCH_SOURCE_DISK',
                    'research_sources'
                ),

            'max_upload_kb'
                => (int) env(
                    'RESEARCH_SOURCE_MAX_KB',
                    51200
                ),

            /*
             * Optional.
             *
             * Kalau pdftotext tidak ada,
             * native PDF extractor akan dicoba.
             */
            'pdftotext_binary'
                => env(
                    'PDFTOTEXT_BINARY',
                    'pdftotext'
                ),

            'parser_timeout'
                => (int) env(
                    'PDFTOTEXT_TIMEOUT',
                    60
                ),

            'chunk_words'
                => (int) env(
                    'RESEARCH_CHUNK_WORDS',
                    500
                ),

            'chunk_overlap_words'
                => (int) env(
                    'RESEARCH_CHUNK_OVERLAP_WORDS',
                    75
                ),

            'minimum_chunk_words'
                => (int) env(
                    'RESEARCH_MINIMUM_CHUNK_WORDS',
                    60
                ),
        ],

    /*
    |--------------------------------------------------------------------------
    | Retrieval
    |--------------------------------------------------------------------------
    */

    'retrieval'
        => [
            'candidate_limit'
                => (int) env(
                    'RESEARCH_RETRIEVAL_CANDIDATE_LIMIT',
                    400
                ),

            'evidence_limit'
                => (int) env(
                    'RESEARCH_EVIDENCE_LIMIT',
                    8
                ),

            'max_per_document'
                => (int) env(
                    'RESEARCH_EVIDENCE_MAX_PER_DOCUMENT',
                    3
                ),

            'minimum_evidence'
                => (int) env(
                    'RESEARCH_MINIMUM_EVIDENCE',
                    2
                ),

            'minimum_score'
                => (float) env(
                    'RESEARCH_MINIMUM_RETRIEVAL_SCORE',
                    0.15
                ),

            'excerpt_length'
                => (int) env(
                    'RESEARCH_EVIDENCE_EXCERPT_LENGTH',
                    900
                ),
        ],

    /*
    |--------------------------------------------------------------------------
    | Document Engine
    |--------------------------------------------------------------------------
    */

    'documents'
        => [
            /*
             * Private disk.
             *
             * DOCX/PDF tidak diletakkan langsung
             * di public storage.
             */
            'disk'
                => env(
                    'RESEARCH_OUTPUT_DISK',
                    'research_outputs'
                ),

            'types'
                => [
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

            'citation_styles'
                => [
                    'APA 7',
                    'IEEE',
                    'Harvard',
                ],
        ],

];