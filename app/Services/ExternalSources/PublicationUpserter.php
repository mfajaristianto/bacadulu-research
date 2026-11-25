<?php

namespace App\Services\ExternalSources;

use App\Models\ExternalPublication;
use App\Models\ExternalPublicationCategory;
use App\Models\ExternalPublicationSource;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
 * Menyimpan satu rekaman baku dengan dedup konservatif dan jejak asal (provenance):
 *   1. (provider, external_id) yang sama                         → rekaman itu (idempoten, sync ulang aman);
 *   2. DOI kanonis yang sama                                      → publikasi yang sama dari penyedia lain;
 *   3. tanpa DOI: ISBN yang sama DAN judul ternormalisasi sama    → buku yang sama;
 *   4. selain itu → publikasi baru. Judul mirip saja TIDAK pernah menggabungkan dua publikasi.
 * Hasil: new | updated | duplicate | skipped.
 */
class PublicationUpserter
{
    public function upsert(array $r, ?int $categoryId = null, ?string $term = null): string
    {
        try {
            return DB::transaction(fn () => $this->apply($r, $categoryId, $term));
        } catch (UniqueConstraintViolationException) {
            // Dua job menulis DOI/sumber yang sama bersamaan: baca ulang, kini pasti ditemukan.
            return DB::transaction(fn () => $this->apply($r, $categoryId, $term));
        }
    }

    private function apply(array $r, ?int $categoryId, ?string $term): string
    {
        $now = now();
        $source = ExternalPublicationSource::query()->where('provider', $r['provider'])->where('external_id', $r['external_id'])->first();
        $publication = $source ? ExternalPublication::query()->find($source->publication_id) : null;
        $publication ??= $r['doi'] ? ExternalPublication::query()->where('doi', $r['doi'])->first() : null;
        $publication ??= $this->byIsbn($r);

        if (! $publication) {
            $publication = ExternalPublication::create($this->attributes($r) + ['uuid' => (string) Str::uuid(), 'first_seen_at' => $now, 'last_seen_at' => $now]);
            $this->attachSource($publication, $r, $now);
            $this->classify($publication, $categoryId, $term, $r['provider']);

            return 'new';
        }

        $result = 'duplicate';
        $sameProvider = $source !== null;

        if ($sameProvider && $source->checksum !== $r['checksum']) {
            // Tidak destruktif: nilai kosong dari penyedia (mis. format OAI yang tak memuat lisensi) tidak menghapus nilai yang sudah tersimpan.
            $publication->fill(collect($this->attributes($r))->filter(fn ($v) => filled($v))->all());
            $this->persist($publication, $now);
            $result = 'updated';
        } else {
            // Penyedia lain atau tidak berubah: hanya lengkapi bidang yang masih kosong, jangan menimpa.
            // Penyedia lain hanya MELENGKAPI yang kosong; "unknown" pada status OA dianggap belum diketahui; tipe tidak pernah ditimpa. Data penyedia pertama tidak pernah ditimpa.
            $unknown = ['oa_status' => 'unknown'];
            $publication->fill(collect($this->attributes($r))->filter(fn ($v, $k) => (blank($publication->{$k}) || ($unknown[$k] ?? null) === $publication->{$k}) && filled($v) && ($unknown[$k] ?? null) !== $v)->except(['availability', 'publication_type'])->all());
            $publication->availability = Normalize::availability(['oa_status' => $publication->oa_status, 'fulltext_url' => $publication->fulltext_url, 'abstract' => $publication->abstract]);
            $this->persist($publication, $now);
        }

        $this->attachSource($publication, $r, $now);
        $this->classify($publication, $categoryId, $term, $r['provider']);

        return $result;
    }

    private function byIsbn(array $r): ?ExternalPublication
    {
        if ($r['doi'] || $r['isbn'] === []) {
            return null;
        }

        return ExternalPublication::query()->whereNull('doi')->where('title_hash', Normalize::titleHash($r['title']))->get()
            ->first(fn ($p) => array_intersect($p->isbn_json ?? [], $r['isbn']) !== []);
    }

    private function attributes(array $r): array
    {
        return [
            'doi' => $r['doi'], 'isbn_json' => $r['isbn'] ?: null, 'issn_json' => $r['issn'] ?: null, 'title' => $r['title'],
            'authors_json' => $r['authors'] ?: null, 'publication_year' => $r['year'], 'published_date' => $r['published_date'],
            'publisher' => $r['publisher'], 'container_title' => $r['container_title'], 'abstract' => $r['abstract'],
            'keywords_json' => $r['keywords'] ?: null, 'language' => $r['language'], 'publication_type' => $r['type'],
            'landing_url' => $r['landing_url'], 'fulltext_url' => $r['fulltext_url'], 'license' => $r['license'],
            'oa_status' => $r['oa_status'], 'availability' => $r['availability'], 'title_hash' => Normalize::titleHash($r['title']),
            'checksum' => $r['checksum'],
        ];
    }

    /**
     * Membedakan tiga hal: ISI metadata berubah (updated_at ikut berubah), rekaman TERLIHAT LAGI oleh sinkronisasi (last_seen_at saja),
     * dan sinkronisasi hanya memeriksa. Bila hanya last_seen_at yang berubah, updated_at dibiarkan: tanda "diperbarui" harus berarti
     * isi memang berubah. Jejak "terlihat lagi" tetap ditulis.
     */
    private function persist(ExternalPublication $publication, $now): void
    {
        $publication->last_seen_at = $now;
        $contentChanged = array_diff(array_keys($publication->getDirty()), ['last_seen_at']) !== [];

        $publication->timestamps = $contentChanged;
        $publication->save();
        $publication->timestamps = true;
    }

    private function attachSource(ExternalPublication $publication, array $r, $now): void
    {
        $source = ExternalPublicationSource::query()->where('provider', $r['provider'])->where('external_id', $r['external_id'])->first();
        $values = ['publication_id' => $publication->id, 'source_url' => $r['landing_url'], 'checksum' => $r['checksum']];

        if (! $source) {
            ExternalPublicationSource::query()->create(['provider' => $r['provider'], 'external_id' => $r['external_id'], 'last_seen_at' => $now] + $values);

            return;
        }

        // Baris sumber yang tidak berubah hanya mencatat kapan terakhir terlihat; updated_at tidak disentuh.
        $source->fill($values);
        $changed = $source->isDirty();
        $source->last_seen_at = $now;
        $source->timestamps = $changed;
        $source->save();
        $source->timestamps = true;
    }

    /** Usulan kategori bersifat transparan (alasan + istilah asal) dan tidak pernah menurunkan status yang sudah diverifikasi admin. */
    private function classify(ExternalPublication $publication, ?int $categoryId, ?string $term, string $provider): void
    {
        if (! $categoryId) {
            return;
        }

        ExternalPublicationCategory::query()->firstOrCreate(
            ['publication_id' => $publication->id, 'category_id' => $categoryId],
            ['status' => 'suggested', 'provider' => $provider, 'query_term' => $term,
                'reason' => Str::limit('Cocok dengan istilah "'.($term ?? '-').'" pada sinkronisasi '.$provider.'.', 480, '')]
        );
    }
}
