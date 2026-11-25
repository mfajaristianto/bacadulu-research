<?php

namespace App\Services\FullText;

use App\Jobs\ProcessExternalFullText;
use App\Models\ExternalFulltext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Pipeline per lokasi: unduh -> simpan -> ekstrak -> chunk -> indeks. Setiap langkah:
 *  - idempoten (status yang sudah lewat tidak diulang) dan checkpoint (berkas yang sudah tersimpan tidak diunduh ulang);
 *  - aman dibatalkan (token antrean usang dan perpindahan status atomik membuat job lama tidak berefek);
 *  - aman duplikat (checksum identik tidak disimpan dua kali) dan aman crash (berkas ditulis ke .part lalu di-rename).
 * FULL_TEXT_INDEXED hanya diberikan di dalam transaksi yang sama dengan penyimpanan chunk.
 */
final class FullTextIngestor
{
    public function __construct(private SecureDownloader $downloader, private FullTextExtractor $extractor, private FullTextChunker $chunker) {}

    /** @return string status akhir baris setelah pemrosesan */
    public function process(ExternalFulltext $ft, ?string $token = null): string
    {
        $ft->refresh();
        if ($token !== null && $ft->queue_token !== $token) {
            return $ft->state; // job usang (dibatalkan/antre ulang)
        }

        if ($ft->state === FullTextState::QUEUED) {
            if (! $this->download($ft)) {
                return $ft->refresh()->state;
            }
        }
        if ($ft->refresh()->state === FullTextState::STORED) {
            $this->index($ft);
        }

        return $ft->refresh()->state;
    }

    /** @return bool true bila berkas kini tersimpan lokal (lanjut ekstraksi) */
    private function download(ExternalFulltext $ft): bool
    {
        $cfg = (array) config('external-sources.fulltext');
        $dl = (array) $cfg['download'];

        if ($ft->next_attempt_at && $ft->next_attempt_at->isFuture()) {
            $this->redispatch($ft, $ft->next_attempt_at);

            return false;
        }
        if (DB::table('external_fulltexts')->where('state', FullTextState::DOWNLOADING)->count() >= (int) $cfg['queue_limits']['max_concurrent']) {
            $this->redispatch($ft, now()->addSeconds(30));

            return false;
        }
        if (! $ft->transition(FullTextState::QUEUED, FullTextState::DOWNLOADING, ['attempts' => $ft->attempts + 1, 'locked_at' => now(), 'error_code' => null, 'error_message' => null])) {
            return false;
        }

        // Izin diperiksa ULANG saat unduh (bisa berubah sejak antre).
        if ($ft->permission !== LicensePolicy::PERMISSION_ALLOW || ! in_array($ft->kind, ['pdf', 'xml'], true)) {
            return $this->failDownload($ft, 'not_permitted', 'Izin unduh tidak lagi terpenuhi.');
        }

        $disk = Storage::disk((string) $cfg['disk']);
        $disk->makeDirectory('tmp');
        $root = $disk->path('');
        $need = ((int) $dl['min_free_disk_mb'] * 1048576) + (int) $dl['max_bytes'];
        $free = @disk_free_space($root);
        if ($free !== false && $free < $need) {
            return $this->failDownload($ft, 'disk_space', 'Ruang disk tidak cukup untuk unduhan aman.');
        }
        if ($this->usedBytes() + (int) $dl['max_bytes'] > (int) $cfg['queue_limits']['quota_mb'] * 1048576) {
            return $this->failDownload($ft, 'quota_exceeded', 'Kuota penyimpanan full-text penuh.');
        }

        $tmp = $disk->path('tmp/'.Str::uuid().'.part');

        try {
            $got = $this->downloader->download($ft->url, $tmp, $ft->kind);
        } catch (FullTextException $e) {
            SecureDownloader::cleanup($tmp);

            return $this->failOrRetry($ft, $e);
        } catch (Throwable) {
            SecureDownloader::cleanup($tmp);

            return $this->failDownload($ft, 'download_error', 'Unduhan gagal karena galat tak terduga.');
        }

        $dup = ExternalFulltext::query()->where('sha256', $got['sha256'])->whereKeyNot($ft->id)
            ->whereIn('state', [FullTextState::STORED, FullTextState::EXTRACTION_FAILED, FullTextState::INDEXED])->first();
        $tmp = $got['path'];
        if ($dup) {
            @unlink($tmp);

            return $this->failDownload($ft, 'duplicate_file', "Berkas identik (checksum sama) sudah tersimpan pada lokasi #{$dup->id}; tidak disimpan ganda.", ['duplicate_of_id' => $dup->id, 'sha256' => $got['sha256']]);
        }

        $rel = $ft->publication_id.'/'.Str::lower((string) Str::ulid()).($ft->kind === 'xml' ? '.xml' : '.pdf');
        $disk->makeDirectory((string) $ft->publication_id);
        if (! @rename($tmp, $disk->path($rel))) {
            @unlink($tmp);

            return $this->failDownload($ft, 'store_failed', 'Berkas tidak dapat disimpan ke penyimpanan privat.');
        }

        $moved = $ft->transition(FullTextState::DOWNLOADING, FullTextState::STORED, [
            'disk' => $cfg['disk'], 'path' => $rel, 'sha256' => $got['sha256'], 'size_bytes' => $got['bytes'], 'mime' => $got['content_type'],
            'downloaded_at' => now(), 'locked_at' => null, 'next_attempt_at' => null, 'error_code' => null, 'error_message' => null,
        ]);
        if (! $moved) {
            $disk->delete($rel); // dibatalkan saat berjalan: jangan tinggalkan berkas yatim

            return false;
        }

        return true;
    }

    /** Antrean `sync` (uji/lokal) tidak bisa menunda: job tidak dijadwal ulang agar tidak berputar; baris tetap antre sampai admin mencoba lagi. */
    private function redispatch(ExternalFulltext $ft, \DateTimeInterface $at): void
    {
        if (config('queue.default') !== 'sync') {
            ProcessExternalFullText::dispatch($ft->id, (string) $ft->queue_token)->delay($at);
        }
    }

    private function failOrRetry(ExternalFulltext $ft, FullTextException $e): bool
    {
        $dl = (array) config('external-sources.fulltext.download');
        if ($e->transient && $ft->attempts < (int) $dl['max_attempts']) {
            $delay = max($e->retryAfter ?? 0, (int) $dl['backoff_seconds'] * $ft->attempts);
            if ($ft->transition(FullTextState::DOWNLOADING, FullTextState::QUEUED, ['next_attempt_at' => now()->addSeconds($delay), 'locked_at' => null, 'error_code' => $e->errorCode, 'error_message' => $e->getMessage()])) {
                $this->redispatch($ft, now()->addSeconds($delay));
            }

            return false;
        }

        return $this->failDownload($ft, $e->errorCode, $e->getMessage());
    }

    private function failDownload(ExternalFulltext $ft, string $code, string $message, array $extra = []): bool
    {
        $ft->transition(FullTextState::DOWNLOADING, FullTextState::DOWNLOAD_FAILED, $extra + ['error_code' => $code, 'error_message' => mb_substr($message, 0, 500), 'locked_at' => null, 'next_attempt_at' => null]);

        return false;
    }

    /** Ekstrak + chunk + simpan, atomik: INDEXED hanya bila chunk benar-benar tersimpan. */
    public function index(ExternalFulltext $ft): void
    {
        if ($ft->state !== FullTextState::STORED) {
            return;
        }
        $disk = Storage::disk((string) $ft->disk);

        try {
            if (! $ft->path || ! $disk->exists($ft->path)) {
                throw new FullTextException('file_missing', 'Berkas lokal hilang; unduh ulang diperlukan.');
            }
            $abs = $disk->path($ft->path);
            if (hash_file('sha256', $abs) !== $ft->sha256) {
                throw new FullTextException('checksum_mismatch', 'Checksum berkas berubah sejak diunduh; berkas tidak dipercaya.');
            }

            $x = $this->extractor->extract($abs, $ft->kind);
            $chunks = $this->chunker->chunk($x['blocks']);
            if ($chunks === []) {
                throw new FullTextException('no_chunks', 'Tidak ada teks yang cukup untuk dijadikan chunk.');
            }

            DB::transaction(function () use ($ft, $chunks, $x): void {
                $ft->chunks()->delete();
                $now = now();
                $rows = array_map(fn ($c) => [
                    'fulltext_id' => $ft->id, 'publication_id' => $ft->publication_id, 'chunk_index' => $c['index'], 'section' => $c['section'],
                    'page_start' => $c['page_start'], 'page_end' => $c['page_end'], 'is_references' => $c['is_references'], 'content' => $c['content'],
                    'content_sha' => $c['content_sha'], 'source_checksum' => $ft->sha256, 'word_count' => $c['word_count'],
                    'provenance_json' => json_encode(['provider' => $ft->provider, 'url' => $ft->url, 'method' => $x['method'], 'version' => $ft->version, 'license' => $ft->license_code]),
                    'created_at' => $now, 'updated_at' => $now,
                ], $chunks);
                foreach (array_chunk($rows, 50) as $batch) {
                    DB::table('external_fulltext_chunks')->insert($batch);
                }

                $ok = $ft->transition(FullTextState::STORED, FullTextState::INDEXED, [
                    'pages_total' => $x['pages_total'], 'pages_with_text' => $x['pages_with_text'], 'text_chars' => $x['chars'], 'extraction_method' => $x['method'],
                    'chunk_count' => count($chunks), 'extracted_at' => $now, 'indexed_at' => $now, 'error_code' => null, 'error_message' => null,
                ]);
                if (! $ok) {
                    throw new FullTextException('state_changed', 'Status berubah saat pengindeksan; dibatalkan.');
                }
            });
        } catch (FullTextException $e) {
            $this->failExtraction($ft, $e->errorCode, $e->getMessage());
        } catch (Throwable) {
            $this->failExtraction($ft, 'extract_error', 'Ekstraksi gagal karena galat tak terduga.');
        }
    }

    private function failExtraction(ExternalFulltext $ft, string $code, string $message): void
    {
        $ft->refresh();
        if ($ft->state === FullTextState::STORED) {
            $ft->transition(FullTextState::STORED, FullTextState::EXTRACTION_FAILED, ['error_code' => $code, 'error_message' => mb_substr($message, 0, 500), 'extracted_at' => now()]);
        }
    }

    /** Total byte salinan lokal (untuk kuota). */
    public function usedBytes(): int
    {
        return (int) DB::table('external_fulltexts')->whereIn('state', [FullTextState::STORED, FullTextState::EXTRACTION_FAILED, FullTextState::INDEXED])->sum('size_bytes');
    }
}
