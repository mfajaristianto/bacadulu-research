<?php

namespace App\Services\FullText;

use App\Jobs\ProcessExternalFullText;
use App\Models\Document;
use App\Models\ExternalFulltext;
use App\Models\ExternalPublication;
use App\Services\ExternalSources\ExternalSourceException;
use App\Services\ExternalSources\Normalize;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Koordinator: mencatat lokasi + keputusan izin, tinjauan admin, antrean (dengan batas), pembatalan, retry, penghapusan salinan,
 * pemulihan job macet, dan statistik. Semua perpindahan status lewat ExternalFulltext::transition (atomik).
 */
final class FullTextService
{
    private static ?bool $ready = null;

    public function __construct(private FullTextLocator $locator, private FullTextIngestor $ingestor) {}

    /** Tabel full-text sudah dimigrasi? (migration belum dijalankan = fitur tampil nonaktif, bukan galat). */
    public static function ready(): bool
    {
        try {
            return self::$ready ??= Schema::hasTable('external_fulltexts') && Schema::hasTable('external_fulltext_chunks');
        } catch (Throwable) {
            return false;
        }
    }

    public static function forgetReady(): void
    {
        self::$ready = null;
    }

    // ---- penemuan lokasi --------------------------------------------------------------------------------------------------

    /**
     * Memeriksa full-text sebuah publikasi. $remote = boleh memanggil OpenAlex/Crossref (satu permintaan per penyedia).
     *
     * @return array{locations: list<ExternalFulltext>, internal_document_id: ?int, errors: list<string>}
     */
    public function check(ExternalPublication $p, bool $remote = false): array
    {
        $errors = [];
        $internal = $this->internalDocument($p);
        $found = $this->locator->stored($p);

        if ($remote) {
            foreach (['openAlex' => 'OpenAlex', 'crossref' => 'Crossref'] as $method => $label) {
                try {
                    $found = array_merge($found, $this->locator->{$method}($p));
                } catch (ExternalSourceException $e) {
                    $errors[] = "{$label}: ".$e->getMessage();
                } catch (Throwable) {
                    $errors[] = "{$label}: galat tak terduga.";
                }
            }
        }

        $found = $this->mergeSameUrl($found);

        $rows = [];
        // Dokumen Pustaka Internal yang sah sudah ada: tidak membuat salinan ganda.
        if (! $internal) {
            foreach ($found as $loc) {
                if ($row = $this->upsert($p, $loc)) {
                    $rows[$row->id] = $row;
                }
            }
        }

        return ['locations' => array_values($rows), 'internal_document_id' => $internal?->id, 'errors' => $errors];
    }

    /**
     * URL yang sama dari beberapa penyedia = SATU lokasi; hasilnya tidak boleh bergantung urutan pemanggilan.
     * Deskripsi berkas langsung (pdf/xml) menjadi utama; penyedia lain yang sepakat pada lisensi hanya menjadi korroborasi di bukti.
     * Lisensi berbeda antar penyedia = bukti bertentangan: lisensi dikosongkan (tinjauan). Satu penyedia menandai tertutup = ditolak.
     *
     * @param  list<array<string, mixed>>  $found
     * @return list<array<string, mixed>>
     */
    private function mergeSameUrl(array $found): array
    {
        $groups = [];
        foreach ($found as $loc) {
            $groups[sha1((string) Normalize::url($loc['url'] ?? null))][] = $loc;
        }

        $out = [];
        foreach ($groups as $group) {
            // Utama: berkas langsung dulu, lalu bukti penyedia langsung di atas data tersimpan (yang tidak punya versi/asal bukti).
            $rank = fn ($l) => [(int) ! in_array($l['kind'], ['pdf', 'xml'], true), (int) (($l['evidence']['origin'] ?? '') === 'stored_metadata')];
            usort($group, fn ($a, $b) => $rank($a) <=> $rank($b));
            $primary = array_shift($group);
            $code = LicensePolicy::normalize($primary['license'] ?? null, $primary['license_url'] ?? null)['code'];

            foreach ($group as $other) {
                $otherCode = LicensePolicy::normalize($other['license'] ?? null, $other['license_url'] ?? null)['code'];
                $note = ['provider' => $other['provider'], 'kind' => $other['kind'], 'license' => $otherCode, 'version' => $other['version'] ?? null];

                if (($other['oa'] ?? null) === false) {
                    $primary['oa'] = false;
                }
                if ($code !== null && $otherCode !== null && $code !== $otherCode) {
                    $primary['license'] = null;
                    $primary['license_url'] = null;
                    $note['conflict'] = true;
                } elseif ($code === null && $otherCode !== null) {
                    $note['note'] = 'lisensi dari penyedia ini tidak dipakai: penyedia utama tidak menyatakan lisensi';
                } else {
                    $note['corroborates'] = $code !== null;
                }
                $primary['evidence']['also_described_by'][] = $note;
            }
            $out[] = $primary;
        }

        return $out;
    }

    private function upsert(ExternalPublication $p, array $loc): ?ExternalFulltext
    {
        $url = Normalize::url($loc['url'] ?? null);
        if (! $url) {
            return null;
        }
        $hash = sha1($url);
        $lic = LicensePolicy::normalize($loc['license'] ?? null, $loc['license_url'] ?? null);
        $d = LicensePolicy::decide([
            'kind' => $loc['kind'], 'license_code' => $lic['code'], 'version' => $loc['version'] ?? null,
            'license_scope' => $loc['license_scope'] ?? 'article', 'oa' => $loc['oa'] ?? null,
        ]);
        $attrs = [
            'provider' => (string) $loc['provider'], 'url' => $url, 'landing_url' => Normalize::url($loc['landing_url'] ?? null), 'kind' => $loc['kind'],
            'license' => isset($loc['license']) ? mb_substr((string) $loc['license'], 0, 120) : null, 'license_code' => $lic['code'], 'license_url' => $lic['url'],
            'license_source' => $loc['license_source'] ?? null, 'license_scope' => $loc['license_scope'] ?? 'article', 'version' => $loc['version'] ?? null,
            'attribution' => LicensePolicy::attribution($p->title, (array) $p->authors_json, $p->publication_year, $lic['code'], $lic['url'], $p->doi),
            'permission' => $d['permission'], 'permission_reason' => $d['reason'], 'evidence_json' => $loc['evidence'] ?? null, 'checked_at' => now(),
        ];

        $ft = ExternalFulltext::query()->where('publication_id', $p->id)->where('url_hash', $hash)->first();
        if (! $ft) {
            return ExternalFulltext::create($attrs + ['publication_id' => $p->id, 'url_hash' => $hash, 'state' => $d['state']]);
        }

        // Lokasi yang sudah diproses atau sudah ditinjau admin tidak diubah kebijakannya; hanya catatan pemeriksaan.
        $open = in_array($ft->state, [FullTextState::LINK_FOUND, FullTextState::REVIEW_REQUIRED, FullTextState::ELIGIBLE], true);
        if (! $open || $ft->reviewed_at || ($ft->license_code !== null && $lic['code'] === null)) {
            $ft->forceFill(['checked_at' => now()])->save();

            return $ft;
        }

        $ft->forceFill($attrs)->save();
        if ($ft->state !== $d['state']) {
            $ft->transition($ft->state, $d['state']);
        }

        return $ft->refresh();
    }

    /** Dokumen Pustaka yang sah dipakai pengguna (aturan yang sama dengan Source Discovery) dan DOI-nya sama. */
    public function internalDocument(ExternalPublication $p): ?Document
    {
        return $p->doi ? Document::query()->eligibleForResearch()->where('doi', $p->doi)->first(['id', 'title', 'doi']) : null;
    }

    // ---- keputusan admin --------------------------------------------------------------------------------------------------

    /** Tinjauan lisensi: setujui. Hanya untuk berkas langsung (pdf/xml); catatan bukti wajib. */
    public function approve(ExternalFulltext $ft, int $adminId, string $note): bool
    {
        $note = trim($note);
        if (mb_strlen($note) < 10 || ! in_array($ft->kind, ['pdf', 'xml'], true) || ! in_array($ft->state, [FullTextState::REVIEW_REQUIRED, FullTextState::LINK_FOUND], true)) {
            return false;
        }

        return $ft->transition($ft->state, FullTextState::ELIGIBLE, [
            'permission' => LicensePolicy::PERMISSION_ALLOW, 'permission_reason' => 'Disetujui admin setelah tinjauan lisensi.',
            'reviewed_by' => $adminId, 'reviewed_at' => now(), 'review_note' => mb_substr($note, 0, 500),
        ]);
    }

    public function reject(ExternalFulltext $ft, int $adminId, string $note): bool
    {
        if (! in_array($ft->state, [FullTextState::REVIEW_REQUIRED, FullTextState::ELIGIBLE], true)) {
            return false;
        }

        return $ft->transition($ft->state, FullTextState::LINK_FOUND, [
            'permission' => LicensePolicy::PERMISSION_DENY, 'permission_reason' => 'Ditolak admin setelah tinjauan lisensi.',
            'reviewed_by' => $adminId, 'reviewed_at' => now(), 'review_note' => mb_substr(trim($note), 0, 500) ?: null,
        ]);
    }

    // ---- antrean ----------------------------------------------------------------------------------------------------------

    /** @throws FullTextException bila batas antrean/kuota tercapai atau izin tidak memenuhi */
    public function queue(ExternalFulltext $ft): bool
    {
        $limits = (array) config('external-sources.fulltext.queue_limits');
        if ($ft->permission !== LicensePolicy::PERMISSION_ALLOW || ! in_array($ft->kind, ['pdf', 'xml'], true)) {
            throw new FullTextException('not_permitted', 'Izin unduh belum terpenuhi untuk lokasi ini.');
        }
        if (ExternalFulltext::query()->whereIn('state', [FullTextState::QUEUED, FullTextState::DOWNLOADING])->count() >= (int) $limits['max_pending']) {
            throw new FullTextException('queue_full', "Antrean penuh (maksimum {$limits['max_pending']} unduhan sekaligus). Tunggu sebagian selesai.");
        }
        if ($this->ingestor->usedBytes() + (int) config('external-sources.fulltext.download.max_bytes') > (int) $limits['quota_mb'] * 1048576) {
            throw new FullTextException('quota_exceeded', 'Kuota penyimpanan full-text penuh. Hapus salinan lokal yang tidak dipakai.');
        }

        $token = (string) Str::uuid();
        if (! $ft->transition(FullTextState::ELIGIBLE, FullTextState::QUEUED, ['queue_token' => $token, 'attempts' => 0, 'next_attempt_at' => null, 'error_code' => null, 'error_message' => null])) {
            return false;
        }
        ProcessExternalFullText::dispatch($ft->id, $token);

        return true;
    }

    /** Batalkan antrean (belum berjalan). Job lama keluar sendiri karena token berubah. */
    public function cancel(ExternalFulltext $ft): bool
    {
        return $ft->transition(FullTextState::QUEUED, FullTextState::ELIGIBLE, ['queue_token' => null, 'next_attempt_at' => null]);
    }

    /** Coba lagi: unduhan gagal -> antre; ekstraksi gagal -> ekstrak ulang dari berkas lokal (tanpa unduh ulang). */
    public function retry(ExternalFulltext $ft): bool
    {
        $token = (string) Str::uuid();

        if ($ft->state === FullTextState::DOWNLOAD_FAILED) {
            if ($ft->permission !== LicensePolicy::PERMISSION_ALLOW || ! $ft->transition(FullTextState::DOWNLOAD_FAILED, FullTextState::QUEUED, ['queue_token' => $token, 'attempts' => 0, 'next_attempt_at' => null, 'error_code' => null, 'error_message' => null, 'duplicate_of_id' => null])) {
                return false;
            }
        } elseif ($ft->state === FullTextState::EXTRACTION_FAILED) {
            if (! $ft->transition(FullTextState::EXTRACTION_FAILED, FullTextState::STORED, ['queue_token' => $token, 'error_code' => null, 'error_message' => null])) {
                return false;
            }
        } elseif ($ft->state === FullTextState::STORED) {
            $ft->forceFill(['queue_token' => $token])->save();
        } else {
            return false;
        }

        ProcessExternalFullText::dispatch($ft->id, $token);

        return true;
    }

    /** Hapus salinan lokal + chunk; lokasi kembali ke "boleh diunduh". Tidak menghapus keputusan izin. */
    public function deleteLocalCopy(ExternalFulltext $ft): bool
    {
        if (! FullTextState::hasLocalCopy($ft->state)) {
            return false;
        }
        $from = $ft->state;
        $disk = $ft->disk;
        $path = $ft->path;

        $ok = DB::transaction(function () use ($ft, $from): bool {
            $ft->chunks()->delete();

            return $ft->transition($from, FullTextState::ELIGIBLE, [
                'disk' => null, 'path' => null, 'sha256' => null, 'size_bytes' => null, 'mime' => null, 'pages_total' => null, 'pages_with_text' => null,
                'text_chars' => null, 'extraction_method' => null, 'chunk_count' => 0, 'downloaded_at' => null, 'extracted_at' => null, 'indexed_at' => null,
                'queue_token' => null, 'error_code' => null, 'error_message' => null, 'attempts' => 0,
            ]);
        });

        if ($ok && $disk && $path) {
            Storage::disk($disk)->delete($path);
        }

        return $ok;
    }

    /** Job DOWNLOADING yang kuncinya usang (worker mati) kembali ke antrean; berkas .part yatim dibersihkan. */
    public function recoverStale(): int
    {
        if (! self::ready()) {
            return 0;
        }
        $n = 0;
        $stale = now()->subMinutes((int) config('external-sources.fulltext.queue_limits.stale_minutes', 15));

        foreach (ExternalFulltext::query()->where('state', FullTextState::DOWNLOADING)->where('locked_at', '<', $stale)->get() as $ft) {
            $token = (string) Str::uuid();
            if ($ft->transition(FullTextState::DOWNLOADING, FullTextState::QUEUED, ['queue_token' => $token, 'locked_at' => null])) {
                ProcessExternalFullText::dispatch($ft->id, $token);
                $n++;
            }
        }

        $disk = Storage::disk((string) config('external-sources.fulltext.disk'));
        foreach ($disk->files('tmp') as $f) {
            if ($disk->lastModified($f) < now()->subDay()->getTimestamp()) {
                $disk->delete($f);
            }
        }

        return $n;
    }

    // ---- laporan ----------------------------------------------------------------------------------------------------------

    /** Angka nyata dari database (tanpa simulasi). */
    public function stats(): array
    {
        $byState = ExternalFulltext::query()->select('state', DB::raw('count(*) as n'))->groupBy('state')->pluck('n', 'state')->all();
        $pubs = ExternalPublication::query();
        $indexedPubs = ExternalFulltext::query()->where('state', FullTextState::INDEXED)->distinct()->count('publication_id');

        return [
            'publications' => (clone $pubs)->count(),
            'abstract_available' => (clone $pubs)->whereNotNull('abstract')->where('abstract', '<>', '')->count(),
            'locations' => array_sum($byState),
            'by_state' => $byState,
            'link_found' => (int) ($byState[FullTextState::LINK_FOUND] ?? 0),
            'review_required' => (int) ($byState[FullTextState::REVIEW_REQUIRED] ?? 0),
            'eligible' => (int) ($byState[FullTextState::ELIGIBLE] ?? 0),
            'queued' => (int) (($byState[FullTextState::QUEUED] ?? 0) + ($byState[FullTextState::DOWNLOADING] ?? 0)),
            'stored' => (int) ($byState[FullTextState::STORED] ?? 0),
            'indexed' => (int) ($byState[FullTextState::INDEXED] ?? 0),
            'indexed_publications' => $indexedPubs,
            'download_failed' => (int) ($byState[FullTextState::DOWNLOAD_FAILED] ?? 0),
            'extraction_failed' => (int) ($byState[FullTextState::EXTRACTION_FAILED] ?? 0),
            'chunks' => (int) DB::table('external_fulltext_chunks')->count(),
            'storage_bytes' => $this->ingestor->usedBytes(),
            'quota_bytes' => (int) config('external-sources.fulltext.queue_limits.quota_mb') * 1048576,
        ];
    }

    /**
     * Status full-text untuk sekumpulan publikasi (dipakai Source Discovery). Tidak memengaruhi peringkat.
     *
     * @param  list<int>  $publicationIds
     * @return array<int, array{checked: bool, state: string, label: string, discovered: bool, open_text: bool, stored: bool, indexed: bool, license: ?string, permission: ?string, chunks: int, locations: int}>
     */
    public function statusFor(array $publicationIds): array
    {
        if ($publicationIds === [] || ! self::ready()) {
            return [];
        }

        $out = [];
        $checked = [];
        foreach (ExternalFulltext::query()->whereIn('publication_id', $publicationIds)->get(['id', 'publication_id', 'state', 'kind', 'license_code', 'permission', 'chunk_count']) as $r) {
            $cur = $out[$r->publication_id] ?? ['state' => FullTextState::METADATA_ONLY, 'rank' => -1, 'license' => null, 'permission' => null, 'chunks' => 0, 'locations' => 0, 'open_text' => false];
            $checked[$r->publication_id] = true;
            $cur['locations']++;
            $cur['open_text'] = $cur['open_text'] || in_array($r->kind, ['pdf', 'xml'], true);
            if ((FullTextState::RANK[$r->state] ?? 0) > $cur['rank']) {
                $cur = ['state' => $r->state, 'rank' => FullTextState::RANK[$r->state], 'license' => $r->license_code, 'permission' => $r->permission, 'chunks' => (int) $r->chunk_count] + $cur;
            }
            $out[$r->publication_id] = $cur;
        }

        foreach ($publicationIds as $id) {
            $out[$id] ??= ['state' => FullTextState::METADATA_ONLY, 'rank' => -1, 'license' => null, 'permission' => null, 'chunks' => 0, 'locations' => 0, 'open_text' => false];
        }
        foreach ($out as $id => $s) {
            $out[$id] = [
                'checked' => isset($checked[$id]), 'state' => $s['state'], 'label' => FullTextState::LABELS[$s['state']], 'discovered' => true, 'open_text' => $s['open_text'],
                'stored' => FullTextState::hasLocalCopy($s['state']), 'indexed' => $s['state'] === FullTextState::INDEXED,
                'license' => $s['license'], 'permission' => $s['permission'], 'chunks' => $s['chunks'], 'locations' => $s['locations'],
            ];
        }

        return $out;
    }
}
