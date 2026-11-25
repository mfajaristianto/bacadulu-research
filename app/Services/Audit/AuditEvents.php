<?php

namespace App\Services\Audit;

/*
 * Registri kode event audit -> narasi, kategori, dan pelaku. Audit log hanya menyimpan kode `action`, tipe/id objek, user_id, metadata bebas, dan IP;
 * tidak ada kolom severity, kategori, atau tipe pelaku. Semua pengelompokan di sini adalah PEMETAAN dari kode event yang benar-benar ditulis aplikasi
 * (lihat AuditLog::create di controller dan job), bukan data dari database. Kode yang tidak dikenal tetap ditampilkan apa adanya dan tidak dikelompokkan
 * ke kategori mana pun ("Lainnya").
 */
final class AuditEvents
{
    public const CATEGORIES = [
        'ai' => 'Konfigurasi AI',
        'dokumen' => 'Dokumen & Template',
        'pustaka' => 'Pustaka & Sumber',
        'riset' => 'Proses Riset',
        'akses' => 'Akses Admin',
        'pengaturan' => 'Pengaturan',
    ];

    public const VERBS = [
        'create' => 'Membuat', 'update' => 'Mengubah', 'activate' => 'Mengaktifkan', 'archive' => 'Mengarsipkan', 'revise' => 'Merevisi',
        'copy' => 'Menyalin', 'import' => 'Impor', 'publish' => 'Menerbitkan', 'access' => 'Akses admin', 'process' => 'Proses sistem',
        'document' => 'Dokumen pengguna', 'check' => 'Pengujian', 'failure' => 'Kegagalan', 'blocked' => 'Ditolak', 'other' => 'Lainnya',
    ];

    public const ACTOR_KINDS = ['admin' => 'Admin', 'pengguna' => 'Pengguna', 'sistem' => 'Sistem', 'tidak-diketahui' => 'Tidak diketahui'];

    /** Urutan penting: awalan yang lebih spesifik lebih dulu. [kategori, produsen] */
    private const PREFIXES = [
        'document_prompt.' => ['ai', 'admin'], 'document_template.' => ['dokumen', 'admin'], 'document.' => ['dokumen', 'pengguna'],
        'answer_behavior.' => ['ai', 'admin'], 'tool.' => ['ai', 'admin'], 'prompt.' => ['ai', 'admin'], 'ai.' => ['ai', 'admin'],
        'category.' => ['pustaka', 'admin'], 'collection.' => ['pustaka', 'admin'], 'glossary.' => ['pustaka', 'admin'],
        'external_source.' => ['pustaka', 'admin'], 'source_sync.' => ['pustaka', 'admin'], 'source.' => ['pustaka', 'admin'],
        'research.' => ['riset', 'sistem'], 'admin.' => ['akses', 'admin'], 'user.' => ['akses', 'admin'], 'settings.' => ['pengaturan', 'admin'],
    ];

    /** Event yang ditulis oleh proses latar (queue job), bukan oleh aksi langsung pengguna. */
    private const SYSTEM_EXACT = ['source.ingestion_completed', 'source.ingestion_failed', 'source.ingestion_queue_failed'];

    /** kode => [frasa kerja, kata kerja, mengubah konfigurasi?, punya objek?] */
    private const KNOWN = [
        'tool.created' => ['membuat alat AI', 'create', true, true],
        'tool.updated' => ['mengubah alat AI', 'update', true, true],
        'prompt.created' => ['membuat instruksi', 'create', true, true],
        'prompt.updated' => ['mengubah instruksi', 'update', true, true],
        'prompt.duplicated' => ['menyalin instruksi', 'copy', true, true],
        'prompt.activated' => ['mengaktifkan instruksi', 'activate', true, true],
        'prompt.archived' => ['mengarsipkan instruksi', 'archive', true, true],
        'answer_behavior.created' => ['membuat aturan perilaku jawaban', 'create', true, true],
        'answer_behavior.preset_installed' => ['memasang preset perilaku jawaban', 'import', true, true],
        'answer_behavior.updated' => ['mengubah aturan perilaku jawaban', 'update', true, true],
        'answer_behavior.revised' => ['merevisi aturan perilaku jawaban', 'revise', true, true],
        'answer_behavior.activated' => ['mengaktifkan aturan perilaku jawaban', 'activate', true, true],
        'answer_behavior.archived' => ['mengarsipkan aturan perilaku jawaban', 'archive', true, true],
        'answer_behavior.deactivated' => ['menonaktifkan aturan perilaku jawaban', 'archive', true, true],
        'ai.config.updated' => ['mengubah konfigurasi provider AI', 'update', true, false],
        'ai.synthesis.updated' => ['mengubah konfigurasi sintesis AI berbasis bukti', 'update', true, false],
        'ai.provider.tested' => ['menguji koneksi provider AI', 'check', false, false],
        'document_prompt.template.created' => ['membuat template Prompt Dokumen', 'create', true, true],
        'document_prompt.template.imported' => ['mengimpor template Prompt Dokumen', 'import', true, true],
        'document_prompt.template.updated' => ['mengubah template Prompt Dokumen', 'update', true, true],
        'document_prompt.template.status' => ['mengubah status template Prompt Dokumen', 'update', true, true],
        'document_prompt.template.duplicated' => ['menyalin template Prompt Dokumen', 'copy', true, true],
        'document_prompt.prompt.created' => ['membuat instruksi bagian dokumen', 'create', true, true],
        'document_prompt.prompt.updated' => ['mengubah instruksi bagian dokumen', 'update', true, true],
        'document_prompt.prompt.revised' => ['merevisi instruksi bagian dokumen', 'revise', true, true],
        'document_prompt.prompt.activated' => ['mengaktifkan instruksi bagian dokumen', 'activate', true, true],
        'document_prompt.prompt.archived' => ['mengarsipkan instruksi bagian dokumen', 'archive', true, true],
        'document_prompt.section.created' => ['menambah bagian pada Prompt Dokumen', 'create', true, true],
        'document_prompt.section.updated' => ['mengubah bagian pada Prompt Dokumen', 'update', true, true],
        'document_prompt.section.moved' => ['memindahkan urutan bagian pada Prompt Dokumen', 'update', true, true],
        'document_template.created' => ['membuat template format cetak', 'create', true, true],
        'document_template.duplicated' => ['menyalin template format cetak', 'copy', true, true],
        'document_template.layout_saved' => ['menyimpan draf format cetak', 'update', true, true],
        'document_template.deactivated' => ['mengarsipkan template format cetak', 'archive', true, true],
        'document_template.restored' => ['memulihkan template format cetak', 'activate', true, true],
        'document_template.default_set' => ['menjadikan template format cetak default', 'update', true, true],
        'document_template.layout_adopted' => ['memulai format cetak dari format lama', 'create', true, true],
        'document_template.layout_revised' => ['merevisi versi format cetak', 'revise', true, true],
        'document_template.layout_activated' => ['mengaktifkan versi format cetak', 'activate', true, true],
        'document_template.layout_archived' => ['mengarsipkan draf format cetak', 'archive', true, true],
        'category.created' => ['membuat kategori', 'create', false, true],
        'category.updated' => ['mengubah kategori', 'update', false, true],
        'collection.created' => ['membuat koleksi', 'create', false, true],
        'collection.updated' => ['mengubah koleksi', 'update', false, true],
        'collection.sources_added' => ['menambahkan sumber ke koleksi', 'update', false, true],
        'collection.source_removed' => ['mengeluarkan sumber dari koleksi', 'update', false, true],
        'glossary.created' => ['menambah istilah glosarium', 'create', false, true],
        'glossary.updated' => ['mengubah istilah glosarium', 'update', false, true],
        'glossary.deactivated' => ['menonaktifkan istilah glosarium', 'archive', false, true],
        'source.ingestion_queued' => ['mengunggah sumber dan menjadwalkan pemrosesan', 'import', false, true],
        'source.archive' => ['mengarsipkan sumber', 'archive', false, true],
        'source.reprocess_queued' => ['menjadwalkan pemrosesan ulang sumber', 'process', false, true],
        'source.publish' => ['menerbitkan sumber', 'publish', false, true],
        'source.unpublish' => ['menarik sumber dari terbitan', 'publish', false, true],
        'source.save' => ['menyimpan sumber', 'update', false, true],
        'source.file_downloaded' => ['mengunduh berkas sumber', 'other', false, true],
        'source.version_restored' => ['memulihkan versi berkas sumber', 'revise', false, true],
        'source.duplicate_blocked' => ['mencoba mengunggah sumber duplikat (ditolak)', 'blocked', false, true],
        'source.ingestion_completed' => ['menyelesaikan pemrosesan sumber', 'process', false, true],
        'source.ingestion_failed' => ['mencatat kegagalan pemrosesan sumber', 'failure', false, true],
        'source.ingestion_queue_failed' => ['gagal menjadwalkan pemrosesan sumber', 'failure', false, true],
        'source.ingestion_stage_failed' => ['gagal menyiapkan berkas sumber', 'failure', false, true],
        'external_source.settings_updated' => ['mengubah pengaturan konektor sumber akademik', 'update', true, false],
        'external_source.connection_tested' => ['menguji koneksi konektor sumber akademik', 'check', false, false],
        'external_source.previewed' => ['melihat pratinjau data sumber akademik (tanpa menyimpan)', 'check', false, false],
        'external_source.sync_started' => ['memulai sinkronisasi sumber akademik', 'import', false, true],
        'external_source.sync_all_started' => ['memulai sinkronisasi semua sumber akademik aktif', 'import', false, false],
        'external_source.sync_paused' => ['menjeda sinkronisasi sumber akademik', 'other', false, true],
        'external_source.group_paused' => ['menjeda kampanye sinkronisasi sumber akademik', 'other', false, false],
        'external_source.group_resumed' => ['melanjutkan kampanye sinkronisasi sumber akademik', 'import', false, false],
        'external_source.group_cancelled' => ['membatalkan kampanye sinkronisasi sumber akademik', 'other', false, false],
        'external_source.emergency_stop' => ['mengaktifkan henti darurat sinkronisasi sumber akademik', 'other', true, false],
        'external_source.emergency_release' => ['melepas henti darurat sinkronisasi sumber akademik', 'other', true, false],
        'external_source.doi_checked' => ['memeriksa registrasi DOI publikasi eksternal', 'check', false, true],
        'external_source.sync_cancelled' => ['membatalkan sinkronisasi sumber akademik', 'other', false, true],
        'external_source.sync_resumed' => ['melanjutkan sinkronisasi sumber akademik dari checkpoint', 'import', false, true],
        'external_source.mapping_saved' => ['menyimpan pemetaan kategori sumber akademik', 'update', true, false],
        'external_source.mapping_deleted' => ['menghapus pemetaan kategori sumber akademik', 'archive', true, true],
        'external_source.category_verified' => ['memverifikasi kategori publikasi eksternal', 'update', false, true],
        'external_fulltext.checked' => ['memeriksa lokasi teks lengkap publikasi eksternal', 'check', false, true],
        'external_fulltext.license_reviewed' => ['meninjau lisensi teks lengkap publikasi eksternal', 'update', true, true],
        'external_fulltext.queued' => ['mengantre unduhan teks lengkap publikasi eksternal', 'import', false, true],
        'external_fulltext.cancelled' => ['membatalkan antrean unduhan teks lengkap', 'other', false, true],
        'external_fulltext.retried' => ['mencoba ulang pemrosesan teks lengkap', 'import', false, true],
        'external_fulltext.local_copy_deleted' => ['menghapus salinan lokal teks lengkap beserta chunk-nya', 'archive', true, true],
        'source_sync.scanned' => ['memindai sumber eksternal', 'import', false, false],
        'source_sync.imported' => ['mengimpor sumber hasil pindai', 'import', false, true],
        'source_sync.ignored' => ['mengabaikan sumber hasil pindai', 'other', false, true],
        'settings.updated' => ['mengubah pengaturan runtime', 'update', true, false],
        'user.suspended' => ['menangguhkan akun pengguna', 'update', false, true],
        'user.reactivated' => ['mengaktifkan kembali akun pengguna', 'activate', false, true],
        'admin.login' => ['masuk ke panel admin', 'access', false, false],
        'admin.logout' => ['keluar dari panel admin', 'access', false, false],
        'admin.login.failed' => ['gagal masuk ke panel admin', 'failure', false, false],
        'research.completed' => ['menyelesaikan proses riset', 'process', false, true],
        'research.insufficient_evidence' => ['menyelesaikan riset dengan bukti yang belum cukup', 'process', false, true],
        'research.failed' => ['mencatat kegagalan proses riset', 'failure', false, true],
        'research.citation_failed' => ['mencatat kegagalan validasi sitasi riset', 'failure', false, true],
        'research.queue_failed' => ['gagal menjadwalkan proses riset', 'failure', false, true],
        'research.synthesis_completed' => ['menyelesaikan sintesis AI berbasis bukti (sitasi tervalidasi)', 'process', false, true],
        'research.synthesis_partial' => ['menyelesaikan sintesis AI berbasis bukti dengan sebagian klaim tidak lolos validasi', 'process', false, true],
        'research.synthesis_citation_failed' => ['mencatat kegagalan validasi sintesis AI berbasis bukti', 'failure', false, true],
        'research.synthesis_insufficient_evidence' => ['menghentikan sintesis AI karena bukti belum cukup', 'process', false, true],
        'research.synthesis_budget_exceeded' => ['menolak sintesis AI karena anggaran atau kuota terlampaui', 'blocked', false, true],
        'research.synthesis_rate_limited' => ['menolak sintesis AI karena batas laju', 'blocked', false, true],
        'research.synthesis_failed' => ['mencatat kegagalan proses sintesis AI berbasis bukti', 'failure', false, true],
        'document.draft_created' => ['membuat draf dokumen', 'document', false, true],
        'document.draft_updated' => ['menyimpan perubahan draf dokumen', 'document', false, true],
        'document.generated' => ['membuat berkas dokumen', 'document', false, true],
        'document.downloaded' => ['mengunduh dokumen', 'document', false, true],
        'document.deleted' => ['menghapus dokumen', 'document', false, true],
        'document.generation_started' => ['memulai pembuatan dokumen (Generate Document)', 'document', false, true],
        'user.tutorial_reset' => ['mereset tutorial pengguna', 'update', false, true],
        'user.research_deleted' => ['menghapus riwayat riset pengguna', 'archive', false, true],
        'user.deleted' => ['menghapus akun pengguna', 'archive', false, true],
        'admin.account_created' => ['menambahkan akun administrator', 'create', true, true],
        'admin.account_role_changed' => ['mengubah peran administrator', 'update', true, true],
        'admin.account_removed' => ['mencabut akses administrator', 'archive', true, true],
    ];

    public static function known(string $action): bool
    {
        return isset(self::KNOWN[$action]);
    }

    /** @return array{phrase: string, verb: string, config: bool, object: bool, known: bool} */
    public static function describe(string $action): array
    {
        if (isset(self::KNOWN[$action])) {
            [$phrase, $verb, $config, $object] = self::KNOWN[$action];

            return ['phrase' => $phrase, 'verb' => $verb, 'config' => $config, 'object' => $object, 'known' => true];
        }

        return ['phrase' => 'mencatat event "'.$action.'"', 'verb' => self::isFailure($action) ? 'failure' : 'other', 'config' => false, 'object' => true, 'known' => false];
    }

    /** @return array{0: string, 1: string}|null [kategori, produsen] */
    public static function rule(string $action): ?array
    {
        foreach (self::PREFIXES as $prefix => $rule) {
            if (str_starts_with($action, $prefix)) {
                return $rule;
            }
        }

        return null;
    }

    public static function category(string $action): ?string
    {
        return self::rule($action)[0] ?? null;
    }

    /** admin | pengguna | sistem: produsen event menurut kode, bukan data tersimpan. */
    public static function producer(string $action): string
    {
        if (in_array($action, self::SYSTEM_EXACT, true)) {
            return 'sistem';
        }

        return self::rule($action)[1] ?? 'admin';
    }

    public static function isFailure(string $action): bool
    {
        return str_contains($action, 'failed');
    }

    /** Jenis pelaku yang dapat dipertanggungjawabkan dari data: produsen + ada tidaknya user_id. */
    public static function actorKind(string $action, ?int $userId): string
    {
        return match (self::producer($action)) {
            'sistem' => 'sistem',
            'pengguna' => $userId ? 'pengguna' : 'tidak-diketahui',
            default => $userId ? 'admin' : 'tidak-diketahui',
        };
    }

    /** @return list<string> awalan kode untuk satu kategori */
    public static function prefixesOf(string $category): array
    {
        return array_keys(array_filter(self::PREFIXES, fn ($rule) => $rule[0] === $category));
    }

    /** @return list<string> kode yang mengubah konfigurasi */
    public static function configActions(): array
    {
        return array_keys(array_filter(self::KNOWN, fn ($e) => $e[2]));
    }

    /** @return list<string> */
    public static function systemExact(): array
    {
        return self::SYSTEM_EXACT;
    }

    /** @return list<string> awalan produsen sistem */
    public static function systemPrefixes(): array
    {
        return array_keys(array_filter(self::PREFIXES, fn ($rule) => $rule[1] === 'sistem'));
    }

    /** @return list<string> awalan produsen pengguna */
    public static function userPrefixes(): array
    {
        return array_keys(array_filter(self::PREFIXES, fn ($rule) => $rule[1] === 'pengguna'));
    }

    /** @return list<string> kode yang labelnya memuat kata kunci (untuk pencarian narasi) */
    public static function matchingLabel(string $needle): array
    {
        $needle = mb_strtolower($needle);

        return array_keys(array_filter(self::KNOWN, fn ($e) => str_contains(mb_strtolower($e[0]), $needle)));
    }
}
