<?php

/*
 * Sumber Akademik Eksternal (metadata). Semua nilai aman secara bawaan: tidak ada kunci, tidak ada alamat kontak,
 * dan setiap penyedia nonaktif sampai admin mengaktifkannya di halaman admin. Rahasia (API key) hanya dibaca dari
 * env atau disimpan terenkripsi lewat halaman admin; tidak pernah dicatat ke log.
 */

return [
    // Antrean untuk job sinkronisasi; bawaannya antrean ingestion yang sudah ada.
    'queue' => env('EXTERNAL_SOURCES_QUEUE', env('RESEARCH_INGESTION_QUEUE_NAME', 'ingestion')),

    // Alamat kontak yang dikirim ke penyedia (User-Agent/mailto). Kosong = tidak dikirim. Jangan diisi email pribadi.
    'contact' => env('EXTERNAL_SOURCES_CONTACT', ''),

    'http' => [
        'timeout' => (int) env('EXTERNAL_SOURCES_TIMEOUT', 20),
        // Halaman OAI-PMH DOAJ (300 rekaman, oai_doaj) ~1,1 MB; 5 MB memberi ruang tanpa membuka pintu respons raksasa.
        'max_bytes' => (int) env('EXTERNAL_SOURCES_MAX_BYTES', 5 * 1024 * 1024),
    ],

    /*
     * BATAS UKURAN (dipaksa di backend, bukan hanya di formulir).
     *   quick_max_records     : mode Quick (verifikasi awal).
     *   campaign_max_records  : total rekaman satu kampanye (satu run). Jenjang yang direncanakan: 100 -> 1.000 -> 10.000;
     *                           100.000+ hanya setelah audit kapasitas (naikkan lewat env). TIDAK ADA mode tanpa batas.
     *   pages_per_job         : berapa halaman (batch) diproses satu job sebelum menjadwal ulang dirinya.
     */
    'limits' => [
        'default_max_records' => 100,
        'quick_max_records' => 100,
        'campaign_max_records' => (int) env('EXTERNAL_SOURCES_CAMPAIGN_MAX', env('EXTERNAL_SOURCES_HARD_MAX', 10000)),
        'hard_max_records' => (int) env('EXTERNAL_SOURCES_CAMPAIGN_MAX', env('EXTERNAL_SOURCES_HARD_MAX', 10000)),
        'preview_size' => 15,
        'pages_per_job' => 3,
        'max_attempts' => 3,
        'backoff_base_seconds' => 30,
        'backoff_max_seconds' => 900,
    ],

    /*
     * ANGGARAN & SUMBER DAYA per run/kampanye. Habis salah satu = run berhenti "sebagian" (resumable), bukan gagal.
     * Resume memberi jendela anggaran baru; batas jumlah rekaman total tidak direset.
     */
    'budget' => [
        'max_requests' => (int) env('EXTERNAL_SOURCES_MAX_REQUESTS', 2000),
        'max_minutes' => (int) env('EXTERNAL_SOURCES_MAX_MINUTES', 180),
        'min_free_disk_mb' => (int) env('EXTERNAL_SOURCES_MIN_FREE_DISK_MB', 2048),
        // Tempat yang dicek ruang bebasnya. Kosong = disk storage aplikasi. Isi dengan folder data DB bila berbeda disk (mis. C:\xampp\mysql\data).
        'disk_path' => env('EXTERNAL_SOURCES_DISK_PATH', ''),
        'memory_soft_pct' => 70, // 0 = nonaktif
        'memory_limit_override_mb' => null, // hanya untuk uji; null = memakai memory_limit PHP
        // Run yang boleh aktif bersamaan (semua penyedia). Sisanya antre dalam grup dan berjalan berurutan.
        'max_active_runs' => (int) env('EXTERNAL_SOURCES_MAX_ACTIVE', 2),
        'max_timeline' => 300,
        // Run berstatus aktif tanpa kemajuan selama ini dianggap "tampak macet" (worker mungkin mati, mis. laptop ditutup) dan boleh dilanjutkan admin.
        'stale_minutes' => (int) env('EXTERNAL_SOURCES_STALE_MINUTES', 10),
    ],

    // Kunci opsional dari env; nilai yang disimpan admin (terenkripsi) menimpa ini.
    'providers' => [
        'openalex' => ['api_key' => env('OPENALEX_API_KEY', '')],
        'crossref' => ['api_key' => ''],
        'doaj' => ['api_key' => ''],
    ],

    /*
     * FULL-TEXT ACQUISITION (berkas isi artikel). Default-deny: tanpa bukti lisensi yang jelas tidak ada yang diunduh otomatis.
     * Semua batas dipaksa di backend. Berkas hanya disimpan di disk privat (tidak pernah di public/).
     */
    'fulltext' => [
        'queue' => env('FULLTEXT_QUEUE', 'fulltext'),
        'disk' => 'external_fulltext',

        // Lisensi yang boleh diunduh OTOMATIS bila bukti terstruktur dan versi artikel jelas. Selainnya = tinjauan admin.
        'auto_licenses' => ['cc0', 'cc-by'],
        'auto_versions' => ['publishedVersion', 'acceptedVersion'],
        // Lisensi tingkat jurnal (mis. DOAJ) bukan bukti lisensi artikel: dipercaya otomatis hanya bila true.
        'trust_journal_license' => false,

        // Kosong = semua host publik lolos pemeriksaan SSRF. Berisi = hanya host yang sama atau subdomainnya.
        'allowed_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env('FULLTEXT_ALLOWED_HOSTS', ''))))),

        'download' => [
            'max_bytes' => (int) env('FULLTEXT_MAX_BYTES', 25 * 1024 * 1024),
            'timeout' => 45,
            'connect_timeout' => 10,
            'max_redirects' => 3,
            'max_attempts' => 3,
            'backoff_seconds' => 120,
            'host_interval_ms' => 1500,
            'min_free_disk_mb' => (int) env('FULLTEXT_MIN_FREE_DISK_MB', 1024),
        ],

        // Batas ekstraksi dan chunking.
        'extract' => [
            'max_pages' => 400,
            'max_chars' => 2_000_000,
            'max_chunks' => 800,
            'timeout' => 90,
            'chunk_words' => 400,
            'chunk_overlap_words' => 60,
            'min_chunk_words' => 15,
            // Di bawah rasio halaman-bertext ini, PDF dianggap scan/tanpa lapisan teks (butuh OCR, tidak otomatis).
            'min_text_page_ratio' => 0.3,
        ],

        // Antrean dan kuota. Tidak ada ribuan job sekaligus.
        'queue_limits' => [
            'max_pending' => 25,
            'max_concurrent' => 2,
            'quota_mb' => (int) env('FULLTEXT_QUOTA_MB', 2048),
            'stale_minutes' => 15,
        ],
    ],

    // Pemeriksaan registrasi DOI (doi.org) dibatasi karena tiap pemeriksaan = satu permintaan jaringan.
    'doi_check' => [
        'ttl_days' => 30,
        'per_hour' => 60,
    ],
];
