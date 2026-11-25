<?php

namespace App\Services\Evidence;

/**
 * Kosakata Evidence Pipeline. Tiga konsep dijaga terpisah:
 *  - RELEVANSI sebuah sumber (skor Source Discovery) -> menentukan URUTAN sumber;
 *  - KEDALAMAN bukti (level) -> menentukan apa yang BOLEH diklaim, tidak pernah menentukan urutan;
 *  - DUKUNGAN klaim (support) -> turunan level bukti yang benar-benar masuk bundle.
 */
final class Evidence
{
    public const KIND_INTERNAL = 'internal_document';

    public const KIND_EXTERNAL_ABSTRACT = 'external_abstract';

    public const KIND_EXTERNAL_FULLTEXT = 'external_fulltext';

    public const METADATA_ONLY = 'METADATA_ONLY';

    public const ABSTRACT = 'ABSTRACT';

    public const FULL_TEXT = 'FULL_TEXT';

    public const BIBLIOGRAPHIC_ONLY = 'BIBLIOGRAPHIC_ONLY';

    public const ABSTRACT_SUPPORTED = 'ABSTRACT_SUPPORTED';

    public const FULLTEXT_SUPPORTED = 'FULLTEXT_SUPPORTED';

    /** Jenis klaim yang boleh diajukan AI. */
    public const CLAIM_BIBLIOGRAPHIC = 'bibliographic';

    public const CLAIM_ABSTRACT = 'abstract_statement';

    public const CLAIM_FULLTEXT = 'fulltext_statement';

    public const CLAIM_TYPES = [self::CLAIM_BIBLIOGRAPHIC, self::CLAIM_ABSTRACT, self::CLAIM_FULLTEXT];

    public const LEVEL_LABELS = [
        self::FULL_TEXT => 'Teks lengkap terindeks',
        self::ABSTRACT => 'Abstrak tersedia',
        self::METADATA_ONLY => 'Hanya metadata',
    ];

    public const SUPPORT_LABELS = [
        self::FULLTEXT_SUPPORTED => 'Klaim isi artikel boleh merujuk potongan teks yang dikutip',
        self::ABSTRACT_SUPPORTED => 'Klaim dibatasi pada isi abstrak',
        self::BIBLIOGRAPHIC_ONLY => 'Hanya informasi bibliografi (tahun, penulis, judul, DOI)',
    ];

    /** Klaim yang sah untuk sebuah level bukti (item evidence). */
    public static function claimsFor(string $level): array
    {
        return match ($level) {
            self::FULL_TEXT => [self::CLAIM_FULLTEXT],
            self::ABSTRACT => [self::CLAIM_BIBLIOGRAPHIC, self::CLAIM_ABSTRACT],
            default => [self::CLAIM_BIBLIOGRAPHIC],
        };
    }

    public static function supportFor(string $level): string
    {
        return match ($level) {
            self::FULL_TEXT => self::FULLTEXT_SUPPORTED,
            self::ABSTRACT => self::ABSTRACT_SUPPORTED,
            default => self::BIBLIOGRAPHIC_ONLY,
        };
    }

    /** Label halaman untuk tampilan; null bila halaman tidak diketahui. */
    public static function pageLabel(?int $start, ?int $end): ?string
    {
        if ($start === null) {
            return null;
        }

        return $end !== null && $end !== $start ? "hlm. {$start}–{$end}" : "hlm. {$start}";
    }
}
