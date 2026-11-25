<?php

namespace App\Services\FullText;

/**
 * Status ketersediaan isi publikasi. METADATA_ONLY dan ABSTRACT_AVAILABLE adalah status tingkat PUBLIKASI (turunan dari data);
 * sisanya dimiliki satu lokasi full-text (baris external_fulltexts) dan hanya berpindah lewat tabel transisi di bawah.
 *
 * FULL_TEXT_INDEXED hanya boleh diberikan setelah berkas berhasil diekstrak dan chunk benar-benar tersimpan.
 */
final class FullTextState
{
    public const METADATA_ONLY = 'METADATA_ONLY';

    public const ABSTRACT_AVAILABLE = 'ABSTRACT_AVAILABLE';

    public const LINK_FOUND = 'FULL_TEXT_LINK_FOUND';

    public const REVIEW_REQUIRED = 'LICENSE_REVIEW_REQUIRED';

    public const ELIGIBLE = 'DOWNLOAD_ELIGIBLE';

    public const QUEUED = 'DOWNLOAD_QUEUED';

    public const DOWNLOADING = 'DOWNLOADING';

    public const DOWNLOAD_FAILED = 'DOWNLOAD_FAILED';

    public const STORED = 'FULL_TEXT_STORED';

    public const EXTRACTION_FAILED = 'EXTRACTION_FAILED';

    public const INDEXED = 'FULL_TEXT_INDEXED';

    /** Urutan "kemajuan" (dipakai memilih status terbaik sebuah publikasi dari banyak lokasi). */
    public const RANK = [
        self::METADATA_ONLY => 0, self::ABSTRACT_AVAILABLE => 1, self::LINK_FOUND => 2, self::REVIEW_REQUIRED => 3,
        self::ELIGIBLE => 4, self::DOWNLOAD_FAILED => 5, self::QUEUED => 6, self::DOWNLOADING => 7,
        self::EXTRACTION_FAILED => 8, self::STORED => 9, self::INDEXED => 10,
    ];

    public const LABELS = [
        self::METADATA_ONLY => 'Hanya metadata',
        self::ABSTRACT_AVAILABLE => 'Ada abstrak',
        self::LINK_FOUND => 'Tautan teks lengkap ditemukan',
        self::REVIEW_REQUIRED => 'Perlu tinjauan lisensi',
        self::ELIGIBLE => 'Boleh diunduh',
        self::QUEUED => 'Antre unduh',
        self::DOWNLOADING => 'Sedang diunduh',
        self::DOWNLOAD_FAILED => 'Unduhan gagal',
        self::STORED => 'Tersimpan, belum diindeks',
        self::EXTRACTION_FAILED => 'Ekstraksi gagal',
        self::INDEXED => 'Terindeks (siap jadi evidence)',
    ];

    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        self::LINK_FOUND => [self::REVIEW_REQUIRED, self::ELIGIBLE],
        self::REVIEW_REQUIRED => [self::ELIGIBLE, self::LINK_FOUND],
        self::ELIGIBLE => [self::QUEUED, self::REVIEW_REQUIRED, self::LINK_FOUND],
        self::QUEUED => [self::DOWNLOADING, self::ELIGIBLE],
        self::DOWNLOADING => [self::STORED, self::DOWNLOAD_FAILED, self::QUEUED],
        self::DOWNLOAD_FAILED => [self::QUEUED, self::ELIGIBLE],
        self::STORED => [self::INDEXED, self::EXTRACTION_FAILED, self::ELIGIBLE],
        self::EXTRACTION_FAILED => [self::STORED, self::INDEXED, self::ELIGIBLE],
        self::INDEXED => [self::STORED, self::ELIGIBLE],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /** @return list<string> status baris lokasi (tanpa dua status tingkat publikasi) */
    public static function rowStates(): array
    {
        return array_values(array_diff(array_keys(self::RANK), [self::METADATA_ONLY, self::ABSTRACT_AVAILABLE]));
    }

    /** Status yang menandakan salinan lokal ada di disk. */
    public static function hasLocalCopy(string $state): bool
    {
        return in_array($state, [self::STORED, self::EXTRACTION_FAILED, self::INDEXED], true);
    }
}
