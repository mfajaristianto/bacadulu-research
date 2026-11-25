<?php

namespace App\Services\Evidence;

use JsonSerializable;

/**
 * Evidence Bundle: satu-satunya masukan bukti yang boleh diteruskan ke AI Research tool (tahap berikutnya). Nilai tidak berubah
 * setelah dibangun. Memuat query, kategori, sumber terpilih, item evidence bernomor, peta sitasi, dan keterbatasan otomatis
 * (`limitations`, wajib diteruskan ke prompt dan tidak boleh dihilangkan AI). Kelas ini tidak memanggil AI.
 */
final class ResearchEvidenceBundle implements JsonSerializable
{
    public const SCHEMA_VERSION = 1;

    /** @param  array<string, mixed>  $data */
    public function __construct(private readonly array $data) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['schema_version' => self::SCHEMA_VERSION] + $this->data;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /** @return list<array<string, mixed>> */
    public function sources(): array
    {
        return $this->data['selected_sources'];
    }

    /** @return list<array<string, mixed>> */
    public function items(): array
    {
        return $this->data['evidence_items'];
    }

    /** @return array<string, array<string, mixed>> citation_id => keterangan */
    public function citationMap(): array
    {
        return $this->data['citation_map'];
    }

    /** @return list<string> */
    public function limitations(): array
    {
        return $this->data['limitations'];
    }

    /** @return array<string, mixed> */
    public function summary(): array
    {
        return $this->data['summary'];
    }

    public function query(): string
    {
        return $this->data['query'];
    }

    /** @return array<string, mixed>|null */
    public function citation(string $id): ?array
    {
        return $this->data['citation_map'][$id] ?? null;
    }

    /** @return array<string, mixed>|null */
    public function itemByCitation(string $citationId): ?array
    {
        foreach ($this->data['evidence_items'] as $item) {
            if ($item['citation_id'] === $citationId) {
                return $item;
            }
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    public function sourceById(string $sourceId): ?array
    {
        foreach ($this->data['selected_sources'] as $s) {
            if ($s['source_id'] === $sourceId) {
                return $s;
            }
        }

        return null;
    }

    public function hasEvidenceText(): bool
    {
        return $this->data['evidence_items'] !== [];
    }
}
