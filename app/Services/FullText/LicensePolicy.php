<?php

namespace App\Services\FullText;

/**
 * Gerbang lisensi dan izin. DEFAULT-DENY: berkas hanya boleh diunduh otomatis bila SEMUA syarat terpenuhi
 * (kind berkas langsung, status open access terkonfirmasi, lisensi di daftar `auto_licenses`, versi artikel dikenal,
 * lisensi tingkat artikel). Flag `is_open_access` saja TIDAK pernah cukup. Selain itu: tinjauan admin atau ditolak.
 */
final class LicensePolicy
{
    public const PERMISSION_ALLOW = 'allow';

    public const PERMISSION_REVIEW = 'review';

    public const PERMISSION_DENY = 'deny';

    /** Lisensi yang syaratnya (pembatasan turunan/komersial/bagi-serupa) butuh penilaian hak spesifik. */
    private const RESTRICTED = ['cc-by-nc', 'cc-by-nd', 'cc-by-sa', 'cc-by-nc-nd', 'cc-by-nc-sa'];

    /** @return array{code: ?string, url: ?string} kode lisensi baku dan URL lisensi (bila ada) dari nilai mentah penyedia */
    public static function normalize(?string $raw, ?string $url = null): array
    {
        $candidates = array_filter([trim((string) $url), trim((string) $raw)], fn ($v) => $v !== '');
        $licenseUrl = null;
        foreach ($candidates as $c) {
            if (preg_match('#^https?://#i', $c)) {
                $licenseUrl = mb_substr($c, 0, 500);
            }
        }

        foreach ($candidates as $c) {
            $v = strtolower($c);

            if (preg_match('#creativecommons\.org/publicdomain/zero#', $v) || preg_match('/^cc[\s_-]*0$|^cc[\s_-]*zero|^zero$/', $v)) {
                return ['code' => 'cc0', 'url' => $licenseUrl];
            }
            if (preg_match('#creativecommons\.org/licenses/(by(?:-nc)?(?:-sa|-nd)?)/#', $v, $m)) {
                return ['code' => 'cc-'.$m[1], 'url' => $licenseUrl];
            }
            if (preg_match('/^cc[\s_-]*(by(?:[\s_-]*(?:nc|sa|nd))*)(?:[\s_-]*\d(?:\.\d)?)?$/', $v, $m)) {
                return ['code' => 'cc-'.preg_replace('/[\s_]+/', '-', trim($m[1])), 'url' => $licenseUrl];
            }
            if (in_array($v, ['public-domain', 'pd', 'public domain'], true)) {
                return ['code' => 'public-domain', 'url' => $licenseUrl];
            }
            if (in_array($v, ['publisher-specific-oa', 'implied-oa', 'other-oa', 'publisher-specific, author manuscript', 'unspecified-oa'], true)) {
                return ['code' => $v, 'url' => $licenseUrl];
            }
        }

        return ['code' => $candidates === [] ? null : 'other', 'url' => $licenseUrl];
    }

    /**
     * @param  array{kind?: string, license_code?: ?string, version?: ?string, license_scope?: ?string, oa?: ?bool}  $loc
     * @return array{permission: string, reason: string, state: string}
     */
    public static function decide(array $loc): array
    {
        $kind = $loc['kind'] ?? 'landing';
        $code = $loc['license_code'] ?? null;
        $cfg = (array) config('external-sources.fulltext');

        $review = fn (string $reason) => ['permission' => self::PERMISSION_REVIEW, 'reason' => $reason, 'state' => FullTextState::REVIEW_REQUIRED];
        $linkOnly = fn (string $perm, string $reason) => ['permission' => $perm, 'reason' => $reason, 'state' => FullTextState::LINK_FOUND];

        if ($kind === 'tdm') {
            return $linkOnly(self::PERMISSION_REVIEW, 'Tautan text-mining penerbit: bukan izin unduh, perlu syarat/token penerbit.');
        }
        if (! in_array($kind, ['pdf', 'xml'], true)) {
            return $linkOnly(self::PERMISSION_REVIEW, 'Halaman arahan, bukan berkas langsung: tidak diunduh otomatis.');
        }
        if (($loc['oa'] ?? null) === false) {
            return $linkOnly(self::PERMISSION_DENY, 'Penyedia menandai lokasi ini tertutup (bukan open access).');
        }
        if ($code === null) {
            return $review('Lisensi tidak diketahui. Flag open access saja bukan izin menyimpan atau mengindeks.');
        }
        if (in_array($code, self::RESTRICTED, true)) {
            return $review("Lisensi {$code} memiliki pembatasan; perlu penilaian hak untuk penyimpanan dan pengindeksan.");
        }
        if (! in_array($code, (array) ($cfg['auto_licenses'] ?? []), true)) {
            return $review("Lisensi {$code} tidak ada di daftar otomatis; perlu tinjauan admin.");
        }
        if (($loc['license_scope'] ?? 'article') === 'journal' && ! ($cfg['trust_journal_license'] ?? false)) {
            return $review('Lisensi tingkat jurnal, bukan bukti lisensi artikel ini.');
        }
        if (! in_array($loc['version'] ?? null, (array) ($cfg['auto_versions'] ?? []), true)) {
            return $review('Versi artikel tidak diketahui atau bukan versi terbit/diterima; lisensi mungkin tidak berlaku untuk versi ini.');
        }
        if (($loc['oa'] ?? null) !== true) {
            return $review('Status open access lokasi ini tidak terkonfirmasi oleh penyedia.');
        }

        return ['permission' => self::PERMISSION_ALLOW, 'reason' => "Lisensi {$code} terverifikasi dari data penyedia untuk versi {$loc['version']}.", 'state' => FullTextState::ELIGIBLE];
    }

    /** Teks atribusi dari data tersimpan (tanpa mengarang penulis/judul yang tidak ada). */
    public static function attribution(?string $title, array $authors, ?int $year, ?string $code, ?string $licenseUrl, ?string $doi): string
    {
        $who = $authors === [] ? 'Penulis tidak dicatat' : implode(', ', array_slice($authors, 0, 3)).(count($authors) > 3 ? ' et al.' : '');

        return mb_substr(trim(sprintf('%s%s. "%s".%s%s',
            $who, $year ? " ({$year})" : '', (string) $title,
            $doi ? " doi:{$doi}." : '',
            $code ? ' Lisensi: '.strtoupper($code).($licenseUrl ? " ({$licenseUrl})" : '').'.' : ''
        )), 0, 500);
    }
}
