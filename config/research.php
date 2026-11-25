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
    | AI cost (Beranda Admin)
    |--------------------------------------------------------------------------
    |
    | Kosong secara default: biaya hanya dihitung bila tarif dan kurs diisi
    | beserta sumber dan tanggal verifikasinya. Tidak ada angka bawaan.
    | RESEARCH_AI_PRICING = JSON {"<nama model>": {"input": USD per 1 juta token,
    | "output": ..., "cached_input": ... (opsional)}}.
    |
    */

    'pricing' => [
        'models' => json_decode((string) env('RESEARCH_AI_PRICING', ''), true) ?: [],
        'source' => env('RESEARCH_AI_PRICING_SOURCE'),
        'verified_at' => env('RESEARCH_AI_PRICING_VERIFIED_AT'),
    ],

    'fx' => [
        'usd_idr' => env('RESEARCH_FX_USD_IDR'),
        'source' => env('RESEARCH_FX_SOURCE'),
        'as_of' => env('RESEARCH_FX_AS_OF'),
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

        // Detik menunggu giliran unggah (pemeriksaan duplikat berjalan satu per satu).
        'upload_lock_wait' => (int) env('RESEARCH_UPLOAD_LOCK_WAIT', 15),

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

    /*
    |--------------------------------------------------------------------------
    | Source Discovery (referensi tanpa AI)
    |--------------------------------------------------------------------------
    |
    | Retrieval eksternal TIDAK memotong menurut ID: SQL menghitung batas atas skor tiap baris, baris diperiksa menurut batas itu dan
    | berhenti saat skor ke-rank_limit terbukti (lihat ExternalCandidateFinder).
    | scan_limit: baris (id + batas atas) maksimum yang dibaca dari SQL. examine_limit: baris maksimum yang dinilai skornya di PHP.
    | Bila salah satunya tercapai sebelum terbukti, hasil ditandai tidak lengkap (counts.exact = false), bukan dipotong diam-diam.
    | examine_chunk: ukuran kelompok pemeriksaan. internal_limit: dokumen Pustaka maksimum yang cocok per pencarian.
    | rank_limit: jumlah hasil urut yang disimpan/dipaginasi. top_n: jumlah referensi paling relevan di halaman utama.
    */
    'discovery' => [
        'top_n' => 5,
        'page_size' => 10,
        'scan_limit' => (int) env('DISCOVERY_SCAN_LIMIT', 50000),
        'examine_limit' => (int) env('DISCOVERY_EXAMINE_LIMIT', 3000),
        'examine_chunk' => 250,
        'internal_limit' => 2000,
        'rank_limit' => 100,
        'cache_seconds' => 120,
    ],

    /*
    | Evidence Bundle (bukti untuk sintesis AI di tahap berikutnya). Semua batas dipaksa di backend.
    | max_publications: sumber teratas (urutan RELEVANSI Source Discovery; ketersediaan PDF tidak mengubah urutan).
    | max_chunks / max_per_source / max_chars: anggaran bukti; dipilih bergiliran antar sumber agar satu sumber tidak membanjiri.
    */
    'evidence' => [
        'max_publications' => 5,
        'max_chunks' => (int) env('RESEARCH_EVIDENCE_BUNDLE_CHUNKS', 12),
        'max_per_source' => 3,
        'max_chars' => (int) env('RESEARCH_EVIDENCE_BUNDLE_CHARS', 16000),
        'snippet_chars' => 420,
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Research Synthesis V1 (Tahap 16)
    |--------------------------------------------------------------------------
    | Sintesis hanya dari Evidence Bundle. Pada tahap ini HANYA provider uji (mock) yang tersambung; provider nyata ditolak.
    | mock_scenario: perilaku Mock untuk menguji validator (valid | fake_citation | wrong_page | ...). Bukan opsi pengguna.
    */
    'synthesis' => [
        'mock_scenario' => env('RESEARCH_SYNTHESIS_MOCK_SCENARIO', 'valid'),
        'max_answer_chars' => 12000,
        'max_claims' => 30,
        'max_text_chars' => 1500,
        'context_messages' => 4,

        /*
         * Provider NYATA untuk sintesis (Tahap 17B). Aman secara default: provider 'mock', live OFF.
         * Provider sintesis TERPISAH dari research.ai_provider (alur riset lama): mengisi OPENAI_API_KEY atau memilih openai di
         * alur lama TIDAK mengaktifkan sintesis nyata. Real-enabled butuh: provider=openai + live_enabled (admin) + kunci + model + tarif.
         * Tarif TIDAK diisi bawaan (tidak mengarang harga): tanpa tarif, provider nyata tetap nonaktif.
         */
        'provider' => env('RESEARCH_SYNTHESIS_PROVIDER', 'mock'),
        'live_enabled' => (bool) env('RESEARCH_SYNTHESIS_LIVE', false),
        'model' => env('RESEARCH_SYNTHESIS_MODEL', ''),
        'timeout' => (int) env('RESEARCH_SYNTHESIS_TIMEOUT', 60),
        'connect_timeout' => 10,
        'total_timeout' => 110,
        'max_output_tokens' => (int) env('RESEARCH_SYNTHESIS_MAX_OUTPUT_TOKENS', 2500),
        'max_input_tokens' => (int) env('RESEARCH_SYNTHESIS_MAX_INPUT_TOKENS', 20000),
        'temperature' => null,
        'reasoning_effort' => null,
        'price_input_per_mtok_usd' => env('RESEARCH_SYNTHESIS_PRICE_INPUT'),
        'price_output_per_mtok_usd' => env('RESEARCH_SYNTHESIS_PRICE_OUTPUT'),
        'max_cost_per_request_usd' => (float) env('RESEARCH_SYNTHESIS_MAX_COST', 0.05),
        'max_retries' => (int) env('RESEARCH_SYNTHESIS_MAX_RETRIES', 1),
        'user_requests_per_day' => (int) env('RESEARCH_SYNTHESIS_USER_DAILY', 10),
        'global_requests_per_day' => (int) env('RESEARCH_SYNTHESIS_GLOBAL_DAILY', 100),
        'requests_per_minute' => (int) env('RESEARCH_SYNTHESIS_PER_MINUTE', 6),
    ],

    'retrieval' => [

        // Pagar pengaman jumlah chunk yang dinilai per pencarian (bukan pemotong diam-diam: KeywordRetriever::wasScanLimited()).
        'scan_limit' => (int) env('RESEARCH_RETRIEVAL_SCAN_LIMIT', 50000),

        // Jumlah kandidat TERBAIK (setelah dinilai) yang dipertahankan untuk diversifikasi; bukan pemotong sebelum penilaian.
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

    /*
    | Keamanan akun: retensi riwayat login (hari, minimum 30). IP bukan lokasi GPS.
    */
    'security' => [
        'login_retention_days' => (int) env('LOGIN_RETENTION_DAYS', 180),
    ],

    /* Video tutorial login pertama (opsional; kosong = hanya panduan langkah). */
    'tutorial_video_url' => env('TUTORIAL_VIDEO_URL'),

    /*
    | Generate Document (satu-satunya tempat AI dipanggil pada alur produk). Penyedia dicoba berurutan, gratis dulu; penyedia
    | berbayar dilewati kecuali allow_paid = true (keputusan eksplisit Admin). Tambah penyedia = satu kelas SectionWriter.
    */
    'document_generation' => [
        'providers' => [\App\Services\DocumentFlow\MockSectionWriter::class],
        'allow_paid' => (bool) env('DOCUMENT_AI_ALLOW_PAID', false),
        // free_only (bawaan) | free_first (gratis dulu, lalu berbayar) | paid_only. Kosong = turunan dari allow_paid. Tanpa penagihan live.
        'policy' => env('DOCUMENT_AI_POLICY'),
    ],

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