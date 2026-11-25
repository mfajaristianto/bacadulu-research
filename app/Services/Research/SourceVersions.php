<?php

namespace App\Services\Research;

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Riwayat versi file sumber dan deteksi duplikat untuk Pustaka admin.
 *
 * Satu sumber (documents) punya banyak versi (document_files); file lama tidak pernah dihapus, hanya berstatus `superseded`.
 * Nomor versi = urutan id file dalam sumber itu (v1 = file tertua), jadi stabil selama baris tidak dihapus.
 * Pengunggah dan asal pemulihan dibaca dari audit log (`source.ingestion_queued`, `source.version_restored`),
 * sehingga tidak butuh kolom baru.
 */
class SourceVersions
{
    private const STATES = [
        'active' => ['active', 'Aktif'],
        'superseded' => ['previous', 'Versi sebelumnya'],
        'queued' => ['processing', 'Menunggu proses'],
        'processing' => ['processing', 'Diproses'],
    ];

    /**
     * Riwayat satu sumber, versi terbaru di atas.
     *
     * @return array<int, array<string, mixed>>
     */
    public function history(Document $document, bool $checkDisk = false): array
    {
        return $this->histories(collect([$document]), $checkDisk)[$document->id] ?? [];
    }

    /**
     * Riwayat banyak sumber sekaligus (satu query audit dan satu query pengguna).
     *
     * @param  Collection<int, Document>  $documents
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function histories(Collection $documents, bool $checkDisk = false): array
    {
        if ($documents->isEmpty()) {
            return [];
        }

        $files = DocumentFile::query()
            ->whereIn('document_id', $documents->pluck('id'))
            ->orderBy('id')
            ->get()
            ->groupBy('document_id');

        $origin = $this->origins($documents->pluck('id')->all());
        $names = User::query()->whereIn('id', collect($origin)->pluck('user_id')->filter()->unique())->pluck('name', 'id');
        $result = [];

        foreach ($documents as $document) {
            $list = ($files[$document->id] ?? collect())->values();
            $numbers = $list->pluck('id')->flip()->map(fn ($index) => $index + 1);

            $result[$document->id] = $list->map(function (DocumentFile $file) use ($numbers, $origin, $names, $checkDisk) {
                [$state, $label] = self::STATES[$file->status] ?? ['failed', 'Gagal diproses'];
                $meta = $origin[$file->id] ?? null;
                $from = $meta['restored_from'] ?? null;

                return [
                    'id' => $file->id,
                    'number' => $numbers[$file->id],
                    'label' => 'v'.$numbers[$file->id],
                    'state' => $state,
                    'state_label' => $label,
                    'is_active' => $file->status === 'active',
                    'can_restore' => $file->status === 'superseded',
                    'name' => $file->original_name ?: basename((string) $file->path),
                    'size' => (int) $file->file_size,
                    'hash' => $file->checksum ? substr($file->checksum, 0, 12) : null,
                    'uploaded_at' => $file->created_at,
                    'uploader' => $meta ? ($names[$meta['user_id']] ?? null) : null,
                    'restored_from' => $from ? ($numbers[$from] ?? null) : null,
                    'missing' => $checkDisk ? ! Storage::disk($file->disk)->exists($file->path) : false,
                ];
            })->reverse()->values()->all();
        }

        return $result;
    }

    /** @return array<int, array{user_id: ?int, restored_from: ?int}> keyed by document_file_id */
    private function origins(array $documentIds): array
    {
        if (! Schema::hasTable('audit_logs')) {
            return [];
        }

        $map = [];

        AuditLog::query()
            ->where('auditable_type', Document::class)
            ->whereIn('auditable_id', $documentIds)
            ->whereIn('action', ['source.ingestion_queued', 'source.version_restored'])
            ->orderBy('id')
            ->get(['user_id', 'metadata_json'])
            ->each(function ($log) use (&$map) {
                $meta = (array) $log->metadata_json;
                $fileId = $meta['document_file_id'] ?? null;

                if ($fileId) {
                    $map[(int) $fileId] = ['user_id' => $log->user_id, 'restored_from' => $meta['restored_from_file_id'] ?? null];
                }
            });

        return $map;
    }

    /** DOI tanpa awalan URL/`doi:`, huruf kecil; null bila kosong. */
    public function normalizeDoi(?string $doi): ?string
    {
        $doi = strtolower(trim((string) $doi));
        $doi = preg_replace('#^(?:https?://(?:dx\.)?doi\.org/|doi:\s*)#', '', $doi);

        return $doi === '' ? null : $doi;
    }

    /**
     * Periksa duplikat. `exact` = isi file identik (SHA-256) dengan file yang sudah tersimpan; `same_source` = DOI sama
     * dengan sumber lain padahal file berbeda. Judul yang mirip sengaja tidak dianggap duplikat.
     *
     * @return array{exact: ?array<string, mixed>, same_source: ?array<string, mixed>}
     */
    public function inspect(?string $hash, ?string $doi, ?Document $target = null): array
    {
        $out = ['exact' => null, 'same_source' => null];

        if ($hash) {
            $file = DocumentFile::query()->where('checksum', $hash)->orderBy('id')->first();
            $document = $file ? Document::query()->find($file->document_id) : null;

            if ($file && $document) {
                $number = DocumentFile::query()->where('document_id', $document->id)->where('id', '<=', $file->id)->count();

                $out['exact'] = $this->match($document) + [
                    'version' => 'v'.$number,
                    'file_id' => $file->id,
                    'is_active_version' => $file->status === 'active',
                    'same_target' => $target?->id === $document->id,
                ];

                return $out;
            }
        }

        $document = $this->findByDoi($doi, $target?->id);

        if ($document) {
            $out['same_source'] = $this->match($document);
        }

        return $out;
    }

    public function findByDoi(?string $doi, ?int $exceptId = null): ?Document
    {
        $normal = $this->normalizeDoi($doi);

        if (! $normal || ! Schema::hasColumn('documents', 'doi')) {
            return null;
        }

        $variants = [$normal, 'https://doi.org/'.$normal, 'http://doi.org/'.$normal, 'https://dx.doi.org/'.$normal, 'doi:'.$normal];

        return Document::query()
            ->whereRaw('lower(doi) in ('.implode(',', array_fill(0, count($variants), '?')).')', $variants)
            ->when($exceptId, fn ($q) => $q->where('id', '<>', $exceptId))
            ->orderBy('id')
            ->first();
    }

    /** @return array{id: int, title: string, url: string, status: string} */
    private function match(Document $document): array
    {
        return [
            'id' => $document->id,
            'title' => $document->title,
            'url' => route('admin.library.edit', $document),
            'status' => $document->status,
        ];
    }
}
