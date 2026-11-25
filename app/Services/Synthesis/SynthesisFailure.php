<?php

namespace App\Services\Synthesis;

/**
 * Keadaan gagal sintesis yang dibedakan secara jujur. Pesan untuk pengguna FIXED (tidak pernah memuat galat mentah provider,
 * yang dapat memantulkan kunci atau data sensitif). Disimpan sebagai `failure_state`; status AiRun mengikuti pipeline lama
 * (failed / citation_failed / insufficient_evidence).
 */
final class SynthesisFailure
{
    public const PROVIDER_UNAVAILABLE = 'provider_unavailable';

    public const AUTH_FAILED = 'provider_auth_failed';

    public const RATE_LIMITED = 'provider_rate_limited';

    public const QUOTA_EXHAUSTED = 'provider_quota_exhausted';

    public const TIMEOUT = 'provider_timeout';

    public const INVALID_RESPONSE = 'provider_invalid_response';

    public const SCHEMA_FAILED = 'provider_schema_failed';

    public const CITATION_FAILED = 'citation_failed';

    public const BUDGET_EXCEEDED = 'budget_exceeded';

    public const MESSAGES = [
        self::PROVIDER_UNAVAILABLE => 'Layanan AI tidak tersedia saat ini. Tidak ada hasil yang dibuat dan bukti Anda tidak berubah.',
        self::AUTH_FAILED => 'Layanan AI menolak kredensial server. Hubungi admin; tidak ada hasil yang dibuat.',
        self::RATE_LIMITED => 'Layanan AI sedang membatasi permintaan. Coba lagi beberapa saat lagi.',
        self::QUOTA_EXHAUSTED => 'Kuota layanan AI habis. Hubungi admin; tidak ada hasil yang dibuat.',
        self::TIMEOUT => 'Layanan AI tidak menjawab dalam batas waktu. Tidak ada hasil yang dibuat.',
        self::INVALID_RESPONSE => 'Layanan AI mengembalikan respons yang tidak dapat dipakai. Tidak ada hasil yang dibuat.',
        self::SCHEMA_FAILED => 'Keluaran AI tidak sesuai format yang diwajibkan sehingga tidak dapat diverifikasi dan ditolak.',
        self::CITATION_FAILED => 'Keluaran AI tidak lolos validasi sitasi.',
        self::BUDGET_EXCEEDED => 'Permintaan melewati batas anggaran atau kuota yang ditetapkan admin. Tidak ada permintaan yang dikirim ke layanan AI.',
    ];

    public static function message(string $state): string
    {
        return self::MESSAGES[$state] ?? self::MESSAGES[self::PROVIDER_UNAVAILABLE];
    }

    public static function known(string $state): bool
    {
        return isset(self::MESSAGES[$state]);
    }
}
