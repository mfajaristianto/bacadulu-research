<?php

namespace App\Services\Settings;

use App\Models\AuditLog;
use App\Models\ResearchSetting;
use App\Services\Audit\AuditRedactor;
use App\Services\Research\AiProviders;
use App\Services\Research\ResearchSettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Pengaturan: model tampilan di atas ResearchSettingsService (satu-satunya sumber kebenaran, tabel research_settings, daftar putih kunci).
 * Kelas ini tidak menyimpan apa pun; ia hanya menyusun katalog, nilai efektif/bawaan/tersimpan, ringkasan, status integrasi, dan riwayat.
 *
 * Hasil audit kode (kunci => keadaan):
 *  - A. Berfungsi dan dibaca pipeline: semua kunci "editable" di bawah (konsumen disebut per kunci).
 *  - B. Tersimpan tetapi belum dipakai kode mana pun: queue.live_refresh_seconds (ditampilkan baca-saja, tidak ditulis).
 *  - C/D. Bersumber env/konfigurasi server: ditampilkan baca-saja di kelompok Sistem, tidak pernah ditulis dari form.
 *  - ai.*: kunci yang SAMA ditulis modul Provider & Analitik AI. Di sini hanya ditampilkan baca-saja dengan tautan "Kelola", agar tidak ada
 *    dua antarmuka untuk satu sumber kebenaran.
 */
class SettingsWorkspace
{
    public const TZ = 'Asia/Jakarta';

    public const CATEGORIES = [
        'riset' => ['Riset', 'Cara sistem mengumpulkan bukti sebelum jawaban dibuat.'],
        'sumber' => ['Sumber & Pustaka', 'Cara dokumen sumber diterima dan dipotong untuk pencarian.'],
        'dokumen' => ['Dokumen', 'Pilihan awal saat pengguna membuat dokumen.'],
        'operasional' => ['Akses & Antrean', 'Syarat masuk Workspace dan nama antrean pemrosesan.'],
        'sistem' => ['Sistem', 'Konfigurasi server dan status integrasi (hanya baca).'],
    ];

    /** Pengaturan yang dapat diubah. field = nama field form (dipertahankan dari form lama). */
    public const EDITABLE = [
        'ai.context_messages' => ['field' => 'context_messages', 'cat' => 'riset', 'label' => 'Pesan percakapan sebagai konteks', 'control' => 'range', 'min' => 0, 'max' => 30, 'step' => 1, 'unit' => 'pesan', 'help' => 'Berapa pesan terakhir dalam satu percakapan yang ikut dikirim sebagai konteks pada pertanyaan lanjutan. 0 berarti setiap pertanyaan berdiri sendiri.', 'used' => 'Penyusun jawaban riset'],
        'retrieval.candidate_limit' => ['field' => 'candidate_limit', 'cat' => 'riset', 'label' => 'Kandidat potongan yang diperiksa', 'control' => 'number', 'min' => 20, 'max' => 5000, 'step' => 10, 'unit' => 'potongan', 'help' => 'Jumlah maksimum potongan dokumen yang dinilai relevansinya untuk satu pertanyaan. Lebih besar = lebih teliti tetapi lebih lambat.', 'used' => 'Pencari bukti', 'advanced' => true],
        'retrieval.evidence_limit' => ['field' => 'evidence_limit', 'cat' => 'riset', 'label' => 'Bukti maksimum per jawaban', 'control' => 'range', 'min' => 1, 'max' => 30, 'step' => 1, 'unit' => 'bukti', 'help' => 'Banyaknya kutipan bukti yang diserahkan ke penyusun jawaban.', 'used' => 'Pencari bukti'],
        'retrieval.max_per_document' => ['field' => 'max_per_document', 'cat' => 'riset', 'label' => 'Bukti maksimum per dokumen', 'control' => 'range', 'min' => 1, 'max' => 10, 'step' => 1, 'unit' => 'bukti', 'help' => 'Membatasi dominasi satu dokumen agar jawaban memakai beragam sumber.', 'used' => 'Pencari bukti'],
        'retrieval.minimum_evidence' => ['field' => 'minimum_evidence', 'cat' => 'riset', 'label' => 'Bukti minimum agar jawaban dibuat', 'control' => 'range', 'min' => 1, 'max' => 10, 'step' => 1, 'unit' => 'bukti', 'help' => 'Bila bukti yang ditemukan lebih sedikit, riset berhenti tanpa jawaban alih-alih menebak.', 'used' => 'Alur bukti riset', 'impact' => 'Riset berhenti tanpa jawaban bila bukti yang ditemukan kurang dari nilai ini. Menaikkannya membuat lebih banyak riset kekurangan bukti.'],
        'retrieval.minimum_score' => ['field' => 'minimum_score', 'cat' => 'riset', 'label' => 'Skor relevansi minimum', 'control' => 'range', 'min' => 0, 'max' => 1, 'step' => 0.01, 'unit' => '', 'help' => 'Potongan dengan skor di bawah nilai ini dibuang dari bukti (skala 0 sampai 1).', 'used' => 'Pencari bukti', 'impact' => 'Potongan dengan skor di bawah nilai ini dibuang. Nilai tinggi dapat membuat banyak riset tidak menemukan bukti.'],
        'retrieval.excerpt_length' => ['field' => 'excerpt_length', 'cat' => 'riset', 'label' => 'Panjang kutipan bukti', 'control' => 'number', 'min' => 200, 'max' => 3000, 'step' => 50, 'unit' => 'karakter', 'help' => 'Panjang maksimum satu kutipan yang ditampilkan dan dikirim sebagai bukti.', 'used' => 'Alur bukti riset', 'advanced' => true],

        'ingestion.max_upload_kb' => ['field' => 'upload_limit_kb', 'cat' => 'sumber', 'label' => 'Ukuran maksimum PDF yang diunggah', 'control' => 'number', 'min' => 1024, 'max' => 512000, 'step' => 1024, 'unit' => 'KB', 'help' => 'Batas ukuran berkas pada formulir unggah Pustaka. Berkas yang sudah ada tidak terpengaruh.', 'used' => 'Formulir unggah Pustaka'],
        'ingestion.chunk_words' => ['field' => 'chunk_words', 'cat' => 'sumber', 'label' => 'Kata per potongan', 'control' => 'number', 'min' => 100, 'max' => 3000, 'step' => 50, 'unit' => 'kata', 'help' => 'Target panjang satu potongan dokumen. Hanya berlaku untuk sumber yang diproses setelah perubahan; sumber lama tidak dipotong ulang.', 'used' => 'Pemotong dokumen'],
        'ingestion.chunk_overlap_words' => ['field' => 'chunk_overlap_words', 'cat' => 'sumber', 'label' => 'Tumpang tindih antar potongan', 'control' => 'number', 'min' => 0, 'max' => 1000, 'step' => 5, 'unit' => 'kata', 'help' => 'Kata yang diulang di awal potongan berikutnya agar konteks tidak terputus. Harus lebih kecil dari kata per potongan.', 'used' => 'Pemotong dokumen', 'advanced' => true],
        'ingestion.minimum_chunk_words' => ['field' => 'minimum_chunk_words', 'cat' => 'sumber', 'label' => 'Kata minimum per potongan', 'control' => 'number', 'min' => 10, 'max' => 1000, 'step' => 5, 'unit' => 'kata', 'help' => 'Potongan yang lebih pendek dari ini tidak disimpan. Tidak boleh melebihi kata per potongan.', 'used' => 'Pemotong dokumen', 'advanced' => true],

        'documents.default_type' => ['field' => 'default_document_type', 'cat' => 'dokumen', 'label' => 'Jenis dokumen awal', 'control' => 'select', 'options' => 'types', 'help' => 'Jenis yang terpilih saat pengguna membuka formulir dokumen baru. Pengguna tetap bisa menggantinya.', 'used' => 'Formulir dokumen pengguna'],
        'documents.default_citation_style' => ['field' => 'default_citation_style', 'cat' => 'dokumen', 'label' => 'Gaya sitasi awal', 'control' => 'segmented', 'options' => 'citation_styles', 'help' => 'Gaya sitasi yang terpilih saat pengguna membuat dokumen. Format cetak dikelola di Template & Format Cetak.', 'used' => 'Formulir dokumen pengguna'],

        'access.profile_gate' => ['field' => 'profile_gate', 'cat' => 'operasional', 'label' => 'Kelengkapan profil minimum', 'control' => 'range', 'min' => 0, 'max' => 100, 'step' => 5, 'unit' => '%', 'help' => 'Pengguna dengan profil lebih lengkap dari nilai ini dapat langsung memakai Workspace; yang kurang diminta melengkapi profil dulu. 0 menonaktifkan syarat.', 'used' => 'Gerbang profil Workspace', 'impact' => 'Pengguna dengan kelengkapan profil di bawah nilai ini tidak dapat memakai Workspace sampai melengkapi profil. Nilai tinggi dapat menahan banyak pengguna.'],
        'queue.research_queue' => ['field' => 'research_queue', 'cat' => 'operasional', 'label' => 'Nama antrean riset', 'control' => 'text', 'maxlength' => 80, 'help' => 'Antrean untuk pekerjaan riset AI. Worker antrean harus mendengarkan nama ini, jika tidak riset baru tidak diproses.', 'used' => 'Job proses riset', 'advanced' => true],
        'queue.ingestion_queue' => ['field' => 'ingestion_queue', 'cat' => 'operasional', 'label' => 'Nama antrean pemrosesan sumber', 'control' => 'text', 'maxlength' => 80, 'help' => 'Antrean untuk pemrosesan PDF sumber. Worker harus mendengarkan nama ini.', 'used' => 'Job pemrosesan sumber', 'advanced' => true],
    ];

    /** Tersimpan di tabel tetapi tidak dibaca kode mana pun (hasil audit): ditampilkan baca-saja, tidak ditulis. */
    public const UNUSED = [
        'queue.live_refresh_seconds' => ['label' => 'Interval penyegaran otomatis Workspace', 'unit' => 'detik', 'note' => 'Nilai ini tersimpan, tetapi belum dibaca oleh kode mana pun, jadi mengubahnya tidak berpengaruh. Dibiarkan agar tidak ada kunci lama yang hilang.'],
    ];

    /** Dikelola modul lain: baca-saja dengan tautan "Kelola". */
    public const FOREIGN = [
        'ai.provider' => ['label' => 'Penyedia AI aktif', 'unit' => ''],
        'ai.model' => ['label' => 'Model', 'unit' => ''],
        'ai.timeout' => ['label' => 'Batas waktu permintaan AI', 'unit' => 'detik'],
        'ai.max_output_tokens' => ['label' => 'Token keluaran maksimum', 'unit' => 'token'],
    ];

    public function __construct(private readonly ResearchSettingsService $settings, private readonly AiProviders $providers, private readonly AuditRedactor $redactor) {}

    public static function label(string $key): string
    {
        return self::EDITABLE[$key]['label'] ?? self::UNUSED[$key]['label'] ?? self::FOREIGN[$key]['label'] ?? $key;
    }

    /** @return array<string, list<string>> */
    public static function options(): array
    {
        return ['types' => array_values((array) config('research.documents.types', [])), 'citation_styles' => array_values((array) config('research.documents.citation_styles', []))];
    }

    /*
    |--------------------------------------------------------------------------
    | Nilai: efektif, bawaan, tersimpan
    |--------------------------------------------------------------------------
    */

    /** @return array<string, array<string, mixed>> kunci => definisi + state */
    public function items(): array
    {
        $effective = $this->settings->effectiveFlat();
        $default = $this->settings->defaultFlat();
        $rows = $this->rows();
        $opts = self::options();
        $out = [];

        foreach (self::EDITABLE as $key => $def) {
            $row = $rows[$key] ?? null;
            $value = $effective[$key] ?? null;

            $out[$key] = $def + [
                'key' => $key, 'value' => $value, 'default' => $default[$key] ?? null, 'changed' => $row !== null && (string) ($default[$key] ?? '') !== (string) $value,
                'updatedAt' => $row ? CarbonImmutable::parse($row->updated_at, 'UTC')->setTimezone(self::TZ) : null,
                'updatedBy' => $row?->updater?->name,
                'choices' => isset($def['options']) ? $opts[$def['options']] : null,
            ];
        }

        return $out;
    }

    /** @return array<string, ResearchSetting> */
    private function rows(): array
    {
        if (! Schema::hasTable('research_settings')) {
            return [];
        }

        return ResearchSetting::query()->whereIn('key', array_keys($this->settings->definitions()))->with('updater:id,name')->get()->keyBy('key')->all();
    }

    /*
    |--------------------------------------------------------------------------
    | Ringkasan
    |--------------------------------------------------------------------------
    */

    /** @param array<string, array<string, mixed>> $items @return array<string, mixed> */
    public function overview(array $items): array
    {
        $rows = $this->rows();
        $changed = collect($items)->where('changed', true)->count();
        $last = collect($rows)->max('updated_at');
        $attention = $this->attention(collect($items)->map(fn ($i) => $i['value'])->all());
        $unused = collect(array_keys(self::UNUSED))->filter(fn ($k) => isset($rows[$k]))->count();

        return [
            'managed' => count($items), 'changed' => $changed, 'defaults' => count($items) - $changed, 'groups' => count(self::CATEGORIES) - 1,
            'attention' => $attention, 'unused' => count(self::UNUSED), 'unusedStored' => $unused, 'revision' => $this->settings->revision(),
            'updatedAt' => $last ? CarbonImmutable::parse($last, 'UTC')->setTimezone(self::TZ) : null,
            'byCategory' => collect($items)->groupBy('cat')->map(fn ($g) => ['n' => $g->count(), 'changed' => $g->where('changed', true)->count()])->all(),
        ];
    }

    /**
     * Pemeriksaan konsistensi NILAI EFEKTIF dengan aturan yang sama seperti validasi simpan. Hanya ada isi bila nilai (mis. dari env server) melanggar aturan.
     *
     * @param  array<string, mixed>  $v  @return list<string>
     */
    public function attention(array $v): array
    {
        $out = [];
        $g = fn (string $k) => $v[$k] ?? null;

        foreach (self::EDITABLE as $key => $def) {
            if (isset($def['min'], $def['max']) && is_numeric($g($key)) && ($g($key) < $def['min'] || $g($key) > $def['max'])) {
                $out[] = $def['label'].' ('.$g($key).') berada di luar rentang yang diizinkan '.$def['min'].'–'.$def['max'].'.';
            }
        }

        is_numeric($g('ingestion.chunk_overlap_words')) && is_numeric($g('ingestion.chunk_words')) && $g('ingestion.chunk_overlap_words') >= $g('ingestion.chunk_words') && $out[] = 'Tumpang tindih potongan harus lebih kecil dari kata per potongan.';
        is_numeric($g('ingestion.minimum_chunk_words')) && is_numeric($g('ingestion.chunk_words')) && $g('ingestion.minimum_chunk_words') > $g('ingestion.chunk_words') && $out[] = 'Kata minimum per potongan melebihi kata per potongan.';
        is_numeric($g('retrieval.minimum_evidence')) && is_numeric($g('retrieval.evidence_limit')) && $g('retrieval.minimum_evidence') > $g('retrieval.evidence_limit') && $out[] = 'Bukti minimum melebihi bukti maksimum: riset tidak akan pernah menghasilkan jawaban.';
        is_numeric($g('retrieval.max_per_document')) && is_numeric($g('retrieval.evidence_limit')) && $g('retrieval.max_per_document') > $g('retrieval.evidence_limit') && $out[] = 'Bukti per dokumen melebihi bukti maksimum.';

        $opts = self::options();
        $g('documents.default_type') && ! in_array($g('documents.default_type'), $opts['types'], true) && $out[] = 'Jenis dokumen awal tidak ada dalam daftar jenis yang didukung.';
        $g('documents.default_citation_style') && ! in_array($g('documents.default_citation_style'), $opts['citation_styles'], true) && $out[] = 'Gaya sitasi awal tidak ada dalam daftar gaya yang didukung.';

        return $out;
    }

    /*
    |--------------------------------------------------------------------------
    | Sistem (baca-saja) dan integrasi
    |--------------------------------------------------------------------------
    */

    /** @return list<array{label: string, value: string, note: ?string}> tanpa rahasia: hanya nilai non-sensitif atau status ada/tidak */
    public function server(): array
    {
        return [
            ['label' => 'Nama aplikasi', 'value' => (string) config('app.name'), 'note' => 'Dari APP_NAME di server.'],
            ['label' => 'Lingkungan', 'value' => (string) app()->environment(), 'note' => 'Debug '.(config('app.debug') ? 'menyala' : 'mati').'.'],
            ['label' => 'Bahasa dan zona waktu', 'value' => strtoupper((string) config('app.locale')).' · tampil WIB', 'note' => 'Basis data menyimpan waktu UTC; halaman admin menampilkan WIB.'],
            ['label' => 'Verifikasi email pengguna', 'value' => config('research.require_email_verification') ? 'Wajib' : 'Opsional', 'note' => 'Dari konfigurasi server.'],
            ['label' => 'Login Google', 'value' => config('research.google_auth_enabled') ? 'Diaktifkan' : 'Dinonaktifkan', 'note' => 'Dari konfigurasi server.'],
            ['label' => 'Koneksi antrean', 'value' => (string) config('queue.default', 'sync'), 'note' => config('queue.default') === 'sync' ? 'Pekerjaan diproses langsung saat permintaan, tanpa worker.' : 'Memerlukan worker yang berjalan.'],
        ];
    }

    /** @return list<array<string, mixed>> status: terkonfigurasi|teruji|tersedia|belum-diverifikasi|tidak-didukung|bermasalah */
    public function integrations(): array
    {
        $out = [];
        $provider = strtolower((string) config('research.ai_provider', 'mock'));
        $catalog = $this->providers->catalog();

        if ($provider === 'mock' || ! isset($catalog[$provider])) {
            $out[] = ['name' => 'Penyedia AI', 'status' => 'tersedia', 'text' => 'Mode uji (mock)', 'basis' => 'Konfigurasi research.ai_provider.', 'note' => 'Jawaban berasal dari penyedia uji, bukan AI sungguhan.', 'link' => route('admin.providers.index')];
        } else {
            $st = $this->providers->status($provider);
            $status = match ($st['connection']['key']) {
                'ok' => 'teruji', 'error' => 'bermasalah', 'missing' => 'belum-diverifikasi', default => $st['configured'] ? 'terkonfigurasi' : 'belum-diverifikasi',
            };
            $when = $st['check']['checked_at'] ?? null;
            $out[] = ['name' => 'Penyedia AI', 'status' => $status, 'text' => ($catalog[$provider]['name'] ?? ucfirst($provider)).': '.$st['connection']['label'], 'basis' => $when ? 'Tes koneksi terakhir '.CarbonImmutable::parse($when)->setTimezone(self::TZ)->locale('id')->translatedFormat('j M Y, H.i').' WIB (atas permintaan admin).' : 'Kunci terdeteksi di server; belum pernah diuji. Halaman ini tidak menguji otomatis.', 'note' => null, 'link' => route('admin.providers.index')];
        }

        $conn = (string) config('queue.default', 'sync');
        $q = ['name' => 'Antrean pekerjaan', 'link' => null];

        if ($conn === 'database' && Schema::hasTable('jobs')) {
            $pending = DB::table('jobs')->count();
            $failed = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : null;
            $out[] = $q + ['status' => 'tersedia', 'text' => $pending.' menunggu'.($failed !== null ? ', '.$failed.' gagal tercatat' : ''), 'basis' => 'Hitungan langsung dari tabel jobs dan failed_jobs.', 'note' => 'Apakah worker sedang berjalan tidak dapat diverifikasi dari halaman ini.'];
        } else {
            $out[] = $q + ['status' => $conn === 'sync' ? 'tersedia' : 'belum-diverifikasi', 'text' => 'Koneksi '.$conn, 'basis' => 'Konfigurasi queue.default.', 'note' => $conn === 'sync' ? 'Diproses langsung tanpa worker.' : 'Status worker tidak dapat diverifikasi.'];
        }

        $sync = (string) config('source-sync.provider', 'mock');
        $out[] = ['name' => 'Impor sumber', 'status' => $sync === 'mock' ? 'tersedia' : 'terkonfigurasi', 'text' => $sync === 'mock' ? 'Konektor uji (mock)' : 'Konektor '.$sync, 'basis' => 'Konfigurasi source-sync.provider.', 'note' => 'Koneksi ke layanan eksternal tidak diuji otomatis.', 'link' => route('admin.source-sync.index')];

        $disk = (string) config('research.documents.disk', 'research_outputs');
        $root = (string) config("filesystems.disks.{$disk}.root", '');
        $writable = $root !== '' && is_dir($root) && is_writable($root);
        $out[] = ['name' => 'Penyimpanan hasil dokumen', 'status' => $writable ? 'tersedia' : 'belum-diverifikasi', 'text' => $writable ? 'Folder dapat ditulis' : 'Folder belum ada atau tidak dapat ditulis', 'basis' => 'Pemeriksaan lokal pada disk "'.$disk.'".', 'note' => null, 'link' => route('admin.document-templates.index')];

        return $out;
    }

    /** @return list<array{key: string, label: string, value: mixed, unit: string, note: ?string, link: string}> ai.* dan live_refresh: baca-saja */
    public function readonly(): array
    {
        $flat = $this->settings->effectiveFlat();
        $out = [];

        foreach (self::FOREIGN as $key => $def) {
            $value = $flat[$key] ?? null;
            $out[] = ['key' => $key, 'label' => $def['label'], 'value' => $value === '' || $value === null ? '—' : $value, 'unit' => $def['unit'], 'note' => null, 'link' => route('admin.providers.index')];
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    public function unused(): array
    {
        $flat = $this->settings->effectiveFlat();

        return collect(self::UNUSED)->map(fn ($d, $k) => ['key' => $k, 'label' => $d['label'], 'value' => $flat[$k] ?? '—', 'unit' => $d['unit'], 'note' => $d['note']])->values()->all();
    }

    /** Penggunaan nyata terhadap batas unggah: berkas PDF terbesar yang tercatat. Null bila tabel/kolom tidak ada atau belum ada berkas. */
    public function uploadUtilization(int $limitKb): ?array
    {
        if (! Schema::hasTable('document_files') || ! Schema::hasColumn('document_files', 'file_size')) {
            return null;
        }

        $max = (int) DB::table('document_files')->max('file_size');

        return $max > 0 ? ['maxKb' => (int) ceil($max / 1024), 'limitKb' => $limitKb, 'pct' => $limitKb > 0 ? min(100, round($max / 1024 / $limitKb * 100, 1)) : null, 'count' => (int) DB::table('document_files')->count()] : null;
    }

    /*
    |--------------------------------------------------------------------------
    | Riwayat perubahan (dari audit settings.updated; tidak ada sistem audit kedua)
    |--------------------------------------------------------------------------
    */

    /** @return list<array<string, mixed>> */
    public function history(int $limit = 8): array
    {
        if (! Schema::hasTable('audit_logs')) {
            return [];
        }

        $logs = AuditLog::query()->where('action', 'settings.updated')->with('user:id,name')->latest('id')->limit($limit + 1)->get()->values();
        $out = [];

        foreach ($logs->take($limit) as $i => $log) {
            $meta = (array) $this->redactor->redact((array) $log->metadata_json);
            $older = (array) ($logs[$i + 1]?->metadata_json['settings'] ?? []);
            $changes = [];

            if (isset($meta['changes']) && is_array($meta['changes'])) {
                foreach ($meta['changes'] as $key => $c) {
                    $changes[] = ['label' => self::label($key), 'from' => $c['from'] ?? null, 'to' => $c['to'] ?? null, 'known' => true];
                }
            } else {
                // Event lama hanya menyimpan nama kunci dan snapshot sesudah; nilai sebelumnya hanya dapat diturunkan dari event sebelumnya.
                foreach ((array) ($meta['changed_keys'] ?? []) as $key) {
                    $changes[] = ['label' => self::label((string) $key), 'from' => array_key_exists($key, $older) ? $older[$key] : null, 'to' => $meta['settings'][$key] ?? null, 'known' => array_key_exists($key, $older)];
                }
            }

            $out[] = [
                'id' => $log->id, 'actor' => $log->user?->name ?? 'Admin', 'changes' => $changes, 'count' => count($changes),
                'time' => CarbonImmutable::parse($log->created_at, 'UTC')->setTimezone(self::TZ),
            ];
        }

        return $out;
    }
}
