<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Active Connector
    |--------------------------------------------------------------------------
    |
    | Jangan pindah ke bacadulu dulu.
    |
    | Selama endpoint remote belum ada:
    |
    | SOURCE_SYNC_PROVIDER=mock
    |
    */

    'provider' => env(
        'SOURCE_SYNC_PROVIDER',
        'mock'
    ),

    /*
    |--------------------------------------------------------------------------
    | Safety
    |--------------------------------------------------------------------------
    |
    | Nilai ini sengaja FALSE dan tidak berasal dari ENV.
    |
    | Source yang datang dari connector:
    |
    | Remote
    | → Staging
    | → Admin Review
    | → Import
    | → Ready
    |
    | BUKAN:
    |
    | Remote
    | → Published
    |
    */

    'auto_publish' => false,

    'connectors' => [

        /*
        |--------------------------------------------------------------------------
        | Mock Connector
        |--------------------------------------------------------------------------
        |
        | Development + automated test.
        |
        */

        'mock' => [

            'items' => [

                [
                    'external_id' =>
                        'MOCK-ESG-001',

                    'title' =>
                        'Mock ESG Disclosure and Firm Value Evidence',

                    'updated_at' =>
                        '2026-10-02T09:00:00+07:00',

                    'metadata' => [

                        'publisher' =>
                            'BacaDulu Mock Connector',

                        'publication_year' =>
                            2026,

                        'language' =>
                            'en',

                        'source_label' =>
                            'Synced Source · Mock',

                        'abstract' =>
                            'Development-only source used to validate staging, checksum diff, review, ingestion, and no-auto-publish behaviour.',

                    ],

                    'extracted_text' => <<<'TEXT'
Environmental, social, and governance disclosure has become an important research topic because investors increasingly use non-financial information when evaluating long-term firm prospects. Prior studies frequently examine whether ESG disclosure quality is associated with firm value, market valuation, information asymmetry, corporate reputation, and investor confidence. Several findings indicate that broader and more credible ESG disclosure can support firm value when stakeholders perceive the information as decision useful and when governance mechanisms increase the reliability of sustainability reporting. However, the relationship is not always consistent across institutional settings, industries, ownership structures, and measurement approaches.

Evidence also suggests that corporate governance quality, assurance, regulatory pressure, profitability, firm size, and investor attention can alter the relationship between ESG disclosure and firm value. Some studies report positive associations, while others find weak, insignificant, or context-dependent effects. These differences create a defensible research opportunity to examine mediating and moderating mechanisms rather than assuming one universal effect. Emerging-market settings remain particularly useful because disclosure quality, enforcement, investor sophistication, and sustainability regulation can differ materially from developed markets. Future research can therefore compare ESG dimensions, test governance as a moderator, examine information asymmetry as a mediator, and use longitudinal designs to distinguish short-term market reactions from longer-term valuation effects.
TEXT,
                ],

                [
                    'external_id' =>
                        'MOCK-AI-ACC-001',

                    'title' =>
                        'Mock AI Governance in Accounting Education Evidence',

                    'updated_at' =>
                        '2026-10-02T09:05:00+07:00',

                    'metadata' => [

                        'publisher' =>
                            'BacaDulu Mock Connector',

                        'publication_year' =>
                            2026,

                        'language' =>
                            'en',

                        'source_label' =>
                            'Synced Source · Mock',

                        'abstract' =>
                            'Development-only evidence for validating the Source Sync adapter.',

                    ],

                    'extracted_text' => <<<'TEXT'
Artificial intelligence is increasingly used in accounting education for drafting, feedback, data analysis, tutoring, and assessment support. The educational value of these systems depends not only on technical capability but also on governance choices concerning student agency, transparency, privacy, fairness, academic integrity, and professional responsibility. Institutions therefore need policies that distinguish acceptable assistance from substitution of student judgement. Educators also need processes for explaining when AI-generated output requires verification and how students should document material use of generative tools.

Research in this area highlights unresolved questions about algorithmic bias, unequal access to advanced systems, disclosure requirements, data protection, and the risk that automated assistance can weaken independent professional judgement. Institutional responses vary from prohibition to guided integration. This variation creates opportunities for comparative research on policy design, student behaviour, assessment redesign, and professional ethics. Future studies can investigate whether structured AI-use disclosure improves accountability, whether assessment design preserves student agency, and how accounting programmes can align AI governance with professional standards while still supporting experimentation and digital competence.
TEXT,
                ],

            ],

        ],

        /*
        |--------------------------------------------------------------------------
        | Real BacaDulu Connector
        |--------------------------------------------------------------------------
        |
        | Adapter ini berada di sisi BacaDulu Research.
        |
        | Ketika API BacaDulu utama sudah tersedia:
        |
        | SOURCE_SYNC_PROVIDER=bacadulu
        |
        | Expected discovery:
        |
        | GET /api/research/sources
        |
        | Response:
        |
        | {
        |   "data": [
        |       {
        |           "external_id": "...",
        |           "title": "...",
        |           "checksum": "...",
        |           "updated_at": "...",
        |           "metadata": {...}
        |       }
        |   ]
        | }
        |
        | Expected detail:
        |
        | GET /api/research/sources/{external_id}
        |
        | {
        |   "data": {
        |       "external_id": "...",
        |       "title": "...",
        |       "checksum": "...",
        |       "updated_at": "...",
        |       "extracted_text": "...",
        |       "metadata": {...}
        |   }
        | }
        |
        | Remote PDF tidak wajib diberikan.
        |
        */

        'bacadulu' => [

            'base_url' => env(
                'SOURCE_SYNC_BACADULU_BASE_URL',
                ''
            ),

            'token' => env(
                'SOURCE_SYNC_BACADULU_TOKEN',
                ''
            ),

            'require_token' =>
                filter_var(
                    env(
                        'SOURCE_SYNC_BACADULU_REQUIRE_TOKEN',
                        true
                    ),
                    FILTER_VALIDATE_BOOL
                ),

            'discover_path' => env(
                'SOURCE_SYNC_BACADULU_DISCOVER_PATH',
                '/api/research/sources'
            ),

            'fetch_path' => env(
                'SOURCE_SYNC_BACADULU_FETCH_PATH',
                '/api/research/sources/{external_id}'
            ),

            /*
            |--------------------------------------------------------------------------
            | Pagination
            |--------------------------------------------------------------------------
            */

            'page_size' => (int) env(
                'SOURCE_SYNC_BACADULU_PAGE_SIZE',
                50
            ),

            /*
             * Safety guard.
             *
             * Sekalipun remote pagination rusak,
             * connector tidak looping tanpa batas.
             */

            'max_pages' => (int) env(
                'SOURCE_SYNC_BACADULU_MAX_PAGES',
                25
            ),

            /*
            |--------------------------------------------------------------------------
            | Network
            |--------------------------------------------------------------------------
            */

            'timeout' => (int) env(
                'SOURCE_SYNC_BACADULU_TIMEOUT',
                30
            ),

            'connect_timeout' => (int) env(
                'SOURCE_SYNC_BACADULU_CONNECT_TIMEOUT',
                10
            ),

            'retry_times' => (int) env(
                'SOURCE_SYNC_BACADULU_RETRY_TIMES',
                2
            ),

            'retry_sleep_ms' => (int) env(
                'SOURCE_SYNC_BACADULU_RETRY_SLEEP_MS',
                300
            ),

            /*
             * Production tetap TRUE.
             */

            'verify_ssl' =>
                filter_var(
                    env(
                        'SOURCE_SYNC_BACADULU_VERIFY_SSL',
                        true
                    ),
                    FILTER_VALIDATE_BOOL
                ),

            /*
             * Hanya development localhost jika benar-benar perlu.
             */

            'allow_insecure_http' =>
                filter_var(
                    env(
                        'SOURCE_SYNC_BACADULU_ALLOW_INSECURE_HTTP',
                        false
                    ),
                    FILTER_VALIDATE_BOOL
                ),

            'user_agent' => env(
                'SOURCE_SYNC_BACADULU_USER_AGENT',
                'BacaDulu-Research-SourceSync/1.0'
            ),

        ],

    ],

];