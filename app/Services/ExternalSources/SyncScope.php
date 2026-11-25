<?php

namespace App\Services\ExternalSources;

/**
 * Cakupan satu sinkronisasi/kampanye. Hanya data; batas keras ditegakkan oleh ExternalSyncService::assertRunnable
 * (backend), bukan oleh nilai di sini.
 *
 * Mode:
 *   quick       verifikasi awal (maks quick_max_records)
 *   category    pencarian berbasis istilah untuk satu kategori
 *   all_mapped  satu run per pemetaan kategori yang valid (dijalankan berurutan dalam satu grup)
 *   corpus      korpus metadata lewat jalur bulk resmi penyedia (saat ini: OAI-PMH DOAJ per subjek/set)
 *   incremental perubahan sejak tanggal tertentu lewat fitur resmi penyedia (DOAJ OAI `from`, Crossref `from-index-date`)
 * "Resume" bukan mode awal, melainkan tindakan pada run yang berhenti.
 */
final class SyncScope
{
    public const TYPES = ['article', 'book', 'chapter', 'proceedings', 'dataset', 'thesis'];

    public const MODES = ['quick', 'category', 'all_mapped', 'corpus', 'incremental'];

    /** @param  array<int, string>  $terms */
    public function __construct(
        public readonly string $provider,
        public readonly ?int $categoryId,
        public readonly array $terms,
        public readonly ?int $yearFrom = null,
        public readonly ?int $yearTo = null,
        public readonly ?string $language = null,
        public readonly ?string $type = null,
        public readonly bool $openAccessOnly = false,
        public readonly int $maxRecords = 100,
        public readonly string $mode = 'category',
        public readonly ?int $maxRequests = null,
        public readonly ?int $maxMinutes = null,
        public readonly ?string $set = null,
        public readonly ?string $setLabel = null,
        public readonly ?string $since = null,
        public readonly ?string $group = null,
        public readonly int $groupIndex = 0,
        public readonly int $groupTotal = 1,
        /** Batas total rekaman diproses seluruh grup Sync All; dipaksakan di setiap batch, bukan hanya saat dibuat. Null = tidak ada. */
        public readonly ?int $groupCap = null,
    ) {}

    /** Istilah dibersihkan agar aman di sintaks query semua penyedia: huruf, angka, spasi, tanda hubung. */
    public static function cleanTerm(string $term): string
    {
        $term = preg_replace('/[^\p{L}\p{N}\s\-]+/u', ' ', $term) ?? '';

        return trim(mb_substr(preg_replace('/\s+/u', ' ', $term) ?? '', 0, 120));
    }

    public static function fromArray(array $data): self
    {
        $terms = collect($data['terms'] ?? [])->map(fn ($t) => self::cleanTerm((string) $t))->filter()->unique()->take(10)->values()->all();
        $mode = in_array($data['mode'] ?? null, self::MODES, true) ? $data['mode'] : 'category';
        $since = filled($data['since'] ?? null) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $data['since']) ? (string) $data['since'] : null;

        return new self(
            provider: (string) $data['provider'],
            categoryId: filled($data['category_id'] ?? null) ? (int) $data['category_id'] : null,
            terms: $terms,
            yearFrom: filled($data['year_from'] ?? null) ? (int) $data['year_from'] : null,
            yearTo: filled($data['year_to'] ?? null) ? (int) $data['year_to'] : null,
            language: filled($data['language'] ?? null) ? strtolower((string) $data['language']) : null,
            type: filled($data['type'] ?? null) ? (string) $data['type'] : null,
            openAccessOnly: (bool) ($data['open_access_only'] ?? false),
            maxRecords: max(1, (int) ($data['max_records'] ?? config('external-sources.limits.default_max_records', 100))),
            mode: $mode,
            maxRequests: filled($data['max_requests'] ?? null) ? max(1, (int) $data['max_requests']) : null,
            maxMinutes: filled($data['max_minutes'] ?? null) ? max(1, (int) $data['max_minutes']) : null,
            // setSpec OAI hanya karakter basis64-url/pemisah; apa pun di luar itu dibuang.
            set: filled($data['set'] ?? null) ? preg_replace('/[^A-Za-z0-9_~\-\.:=]/', '', (string) $data['set']) : null,
            setLabel: filled($data['set_label'] ?? null) ? mb_substr(trim(strip_tags((string) $data['set_label'])), 0, 160) : null,
            since: $since,
            group: filled($data['group'] ?? null) ? (string) $data['group'] : null,
            groupIndex: (int) ($data['group_index'] ?? 0),
            groupTotal: max(1, (int) ($data['group_total'] ?? 1)),
            groupCap: filled($data['group_cap'] ?? null) ? max(1, (int) $data['group_cap']) : null,
        );
    }

    /** Pencarian berbasis istilah ('') atau penelusuran berbasis set/tanggal tanpa istilah (korpus/incremental OAI). */
    public function isTermless(): bool
    {
        return $this->terms === [] && in_array($this->mode, ['corpus', 'incremental'], true);
    }

    /** @return array<int, string> daftar istilah yang dijalankan; korpus tanpa istilah = satu putaran dengan istilah kosong. */
    public function runTerms(): array
    {
        return $this->isTermless() ? [''] : $this->terms;
    }

    public function toArray(): array
    {
        return [
            'provider' => $this->provider, 'category_id' => $this->categoryId, 'terms' => $this->terms,
            'year_from' => $this->yearFrom, 'year_to' => $this->yearTo, 'language' => $this->language,
            'type' => $this->type, 'open_access_only' => $this->openAccessOnly, 'max_records' => $this->maxRecords,
            'mode' => $this->mode, 'max_requests' => $this->maxRequests, 'max_minutes' => $this->maxMinutes,
            'set' => $this->set, 'set_label' => $this->setLabel, 'since' => $this->since,
            'group' => $this->group, 'group_index' => $this->groupIndex, 'group_total' => $this->groupTotal, 'group_cap' => $this->groupCap,
        ];
    }
}
