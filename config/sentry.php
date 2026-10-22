<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Connection
    |--------------------------------------------------------------------------
    */

    'dsn' => env(
        'SENTRY_LARAVEL_DSN',
        env(
            'SENTRY_DSN'
        )
    ),

    /*
     * Kalau null, Sentry menggunakan environment Laravel.
     */
    'environment' => env(
        'SENTRY_ENVIRONMENT'
    ),

    /*
     * Bisa diisi commit/release pada production.
     */
    'release' => env(
        'SENTRY_RELEASE'
    ),

    /*
    |--------------------------------------------------------------------------
    | Error Sampling
    |--------------------------------------------------------------------------
    |
    | 1.0 = seluruh error dikirim.
    |
    | Karena tujuan kita maintenance/error tracking,
    | ini tetap 100%.
    |
    */

    'sample_rate' =>
        env(
            'SENTRY_SAMPLE_RATE'
        )
            ===
            null
                ? 1.0
                : (float)
                    env(
                        'SENTRY_SAMPLE_RATE'
                    ),

    'attach_stacktrace' =>
        true,

    /*
    |--------------------------------------------------------------------------
    | Privacy
    |--------------------------------------------------------------------------
    |
    | User ID kita isi sendiri.
    |
    | Default PII seperti email/IP tidak perlu.
    |
    */

    'send_default_pii' =>
        false,

    /*
     * PENTING:
     *
     * Jangan kirim request body.
     *
     * Jadi:
     * research question
     * prompt input
     * form
     * credential
     *
     * tidak ikut sebagai request body Sentry.
     */
    'max_request_body_size' =>
        'never',

    /*
    |--------------------------------------------------------------------------
    | Performance
    |--------------------------------------------------------------------------
    |
    | Maintenance saja.
    |
    | Tidak perlu transaction tracing/profiling.
    |
    */

    'traces_sample_rate' =>
        (float)
            env(
                'SENTRY_TRACES_SAMPLE_RATE',
                0.0
            ),

    'profiles_sample_rate' =>
        (float)
            env(
                'SENTRY_PROFILES_SAMPLE_RATE',
                0.0
            ),

    /*
    |--------------------------------------------------------------------------
    | Logs / Metrics
    |--------------------------------------------------------------------------
    |
    | Jangan jadikan Sentry pengganti laravel.log.
    |
    */

    'enable_logs' =>
        false,

    'enable_metrics' =>
        false,

    /*
    |--------------------------------------------------------------------------
    | Ignore
    |--------------------------------------------------------------------------
    */

    'ignore_transactions' => [
        '/up',
    ],

    /*
    |--------------------------------------------------------------------------
    | Breadcrumbs
    |--------------------------------------------------------------------------
    |
    | Untuk maintenance:
    |
    | ✓ Laravel log breadcrumb
    | ✓ queue info
    |
    | ✗ SQL query
    | ✗ bindings
    | ✗ cache
    | ✗ outbound HTTP details
    |
    */

    'breadcrumbs' => [

        'logs' =>
            true,

        'cache' =>
            false,

        'livewire' =>
            false,

        'sql_queries' =>
            false,

        'sql_bindings' =>
            false,

        'queue_info' =>
            true,

        'command_info' =>
            true,

        'http_client_requests' =>
            false,

        'notifications' =>
            false,

    ],

    /*
    |--------------------------------------------------------------------------
    | Tracing Integrations
    |--------------------------------------------------------------------------
    |
    | Sample rate sudah 0,
    | kita matikan juga collector yang tidak dibutuhkan.
    |
    */

    'tracing' => [

        'queue_job_transactions' =>
            false,

        'queue_jobs' =>
            false,

        'sql_queries' =>
            false,

        'sql_bindings' =>
            false,

        'sql_origin' =>
            false,

        'sql_origin_threshold_ms' =>
            100,

        'views' =>
            false,

        'livewire' =>
            false,

        'http_client_requests' =>
            false,

        'http_client_requests_origin' =>
            false,

        'http_client_requests_origin_threshold_ms' =>
            250,

        'cache' =>
            false,

        'redis_commands' =>
            false,

        'redis_origin' =>
            false,

        'notifications' =>
            false,

        'missing_routes' =>
            false,

        'continue_after_response' =>
            false,

        'gen_ai' =>
            false,

        'gen_ai_invoke_agent' =>
            false,

        'gen_ai_chat' =>
            false,

        'gen_ai_execute_tool' =>
            false,

        'gen_ai_embeddings' =>
            false,

        'default_integrations' =>
            false,

    ],

];