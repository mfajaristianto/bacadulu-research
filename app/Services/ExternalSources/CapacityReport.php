<?php

namespace App\Services\ExternalSources;

use App\Models\ExternalSyncRun;
use Illuminate\Support\Facades\DB;
use Throwable;

/*
 * Estimasi kapasitas yang berasal dari DATA NYATA: ukuran payload rata-rata per rekaman diukur dari sampel yang tersimpan
 * (publikasi + provenance + tautan kategori + kunci indeks), laju diukur dari timeline batch run sebelumnya. Faktor overhead
 * penyimpanan InnoDB adalah ASUMSI umum (ditampilkan apa adanya), bukan hasil ukur pada server hosting. Tidak ada angka
 * yang dikarang bila sampel kosong.
 */
final class CapacityReport
{
    /** Asumsi overhead halaman/indeks/log InnoDB di atas payload terukur (rentang). */
    public const OVERHEAD = [1.3, 2.0];

    private const SCALES = [1000, 10000, 100000, 1000000];

    public function report(): array
    {
        $sample = (int) DB::table('external_publications')->count();
        $payload = $sample > 0 ? $this->payloadPerRecord() : null;
        $rate = $this->measuredRate();

        return [
            'sample' => $sample,
            'payload_bytes' => $payload,
            'overhead' => self::OVERHEAD,
            'projections' => $payload === null ? [] : array_map(fn (int $n) => [
                'records' => $n,
                'payload_mb' => round($payload * $n / 1048576, 1),
                'low_mb' => round($payload * $n * self::OVERHEAD[0] / 1048576, 1),
                'high_mb' => round($payload * $n * self::OVERHEAD[1] / 1048576, 1),
                'minutes' => $rate ? (int) ceil($n / $rate) : null,
            ], self::SCALES),
            'rate_per_min' => $rate,
            'infra' => $this->infra(),
        ];
    }

    /** Rata-rata byte per rekaman dari kolom nyata (variabel + tetap) + provenance + tautan kategori + kunci indeks. */
    private function payloadPerRecord(): int
    {
        $cols = ['doi', 'title', 'publisher', 'container_title', 'abstract', 'landing_url', 'fulltext_url', 'license', 'title_hash', 'checksum', 'uuid', 'language', 'publication_type', 'oa_status', 'availability', 'authors_json', 'keywords_json', 'issn_json', 'isbn_json'];
        $sel = implode(',', array_map(fn ($c) => "coalesce(avg(length({$c})),0) as {$c}", $cols));
        $avg = (array) DB::selectOne("select {$sel} from external_publications");
        $variable = array_sum(array_map('floatval', $avg));
        $indexed = array_sum(array_map(fn ($c) => (float) $avg[$c], ['doi', 'uuid', 'title_hash', 'language', 'publication_type', 'oa_status', 'availability'])) + 2 + 7 * 8;
        $fixed = 8 + 2 + 3 + 4 * 4; // id, tahun, tanggal, empat timestamp
        $src = (float) (DB::selectOne("select coalesce(avg(length(external_id)+length(coalesce(source_url,''))+length(provider)+length(coalesce(checksum,''))),0) as b from external_publication_sources")->b ?? 0);
        $cat = (float) (DB::selectOne("select coalesce(avg(length(coalesce(reason,''))+length(coalesce(query_term,''))+length(status)+length(coalesce(provider,''))),0) as b from external_publication_categories")->b ?? 0);

        return (int) round($variable + $fixed + $indexed + $src + $cat);
    }

    /**
     * Rekaman/menit nyata dari SELANG WAKTU antar-batch (cap waktu tiap batch), jadi sudah termasuk jeda gerbang laju, latensi penyedia,
     * dan jadwal ulang job. Selang > 3 menit (jeda admin, cooldown 429, worker mati) dikecualikan agar tidak menurunkan laju kerja aktif.
     * null bila belum ada cukup batch berurutan: tidak ada angka yang dikarang.
     */
    private function measuredRate(): ?float
    {
        $records = 0;
        $seconds = 0.0;
        foreach (ExternalSyncRun::query()->whereNotNull('checkpoint_json')->latest('id')->limit(40)->get(['checkpoint_json']) as $run) {
            $t = $run->checkpoint_json['timeline'] ?? [];
            for ($i = 1; $i < count($t); $i++) {
                $dt = strtotime((string) ($t[$i]['at'] ?? '')) - strtotime((string) ($t[$i - 1]['at'] ?? ''));
                if ($dt > 0 && $dt <= 180) {
                    $records += (int) ($t[$i]['n'] ?? 0);
                    $seconds += $dt;
                }
            }
        }

        return $records >= 20 && $seconds >= 5 ? round($records / ($seconds / 60), 1) : null;
    }

    private function infra(): array
    {
        $out = ['driver' => DB::getDriverName(), 'free_disk_mb' => ResourceGuard::freeDiskMb(), 'disk_path' => ResourceGuard::diskPath(),
            'php_memory_limit' => ini_get('memory_limit'), 'memory_used_pct' => ResourceGuard::memoryUsedPct(), 'min_free_disk_mb' => (int) config('external-sources.budget.min_free_disk_mb', 2048)];

        try {
            if ($out['driver'] === 'mysql') {
                $out['version'] = DB::selectOne('select version() as v')->v;
                $out['innodb_buffer_pool_mb'] = (int) round((int) DB::selectOne("show variables like 'innodb_buffer_pool_size'")->Value / 1048576);
                $out['flush_log_at_trx_commit'] = (int) DB::selectOne("show variables like 'innodb_flush_log_at_trx_commit'")->Value;
                $out['db_size_mb'] = (float) DB::selectOne('select round(sum(data_length+index_length)/1048576, 2) as mb from information_schema.tables where table_schema = database()')->mb;
            }
        } catch (Throwable) {
            // Informasi infrastruktur bersifat tambahan; kegagalan membacanya tidak boleh merusak halaman.
        }

        return $out;
    }
}
