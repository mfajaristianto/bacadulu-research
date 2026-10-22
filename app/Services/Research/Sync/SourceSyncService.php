<?php

namespace App\Services\Research\Sync;

use App\Models\Category;
use App\Models\Document;
use App\Models\SourceSyncItem;
use App\Models\SourceSyncRun;
use App\Services\Research\SourceIngestionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SourceSyncService
{
    public function __construct(
        private readonly SourceConnectorManager $connectors,
        private readonly SourceIngestionService $ingestion
    ) {}

    public function scan(
        ?int $initiatedBy = null
    ): SourceSyncRun {
        $connector =
            $this
                ->connectors
                ->driver();

        $run =
            SourceSyncRun::create([
                'uuid' =>
                    (string)
                        Str::uuid(),

                'connector' =>
                    $connector
                        ->code(),

                'status' =>
                    'running',

                'initiated_by' =>
                    $initiatedBy,

                'started_at' =>
                    now(),

                'metadata_json' => [
                    'auto_publish' =>
                        false,
                ],
            ]);

        try {
            $discovered =
                $connector
                    ->discover();

            foreach (
                $discovered as
                $item
            ) {
                $this
                    ->stageDiscoveredItem(
                        $run,
                        $connector
                            ->code(),
                        $item
                    );
            }

            $this
                ->refreshRunState(
                    $run
                );

            return $run
                ->fresh(
                    'items'
                );
        } catch (
            Throwable $exception
        ) {
            $run->update([
                'status' =>
                    'failed',

                'error_message' =>
                    $exception
                        ->getMessage(),

                'finished_at' =>
                    now(),
            ]);

            throw $exception;
        }
    }

    public function import(
        SourceSyncItem $item,
        int $categoryId,
        ?int $reviewedBy = null
    ): Document {
        $item =
            SourceSyncItem::query()
                ->with(
                    'run'
                )
                ->findOrFail(
                    $item->id
                );

        if (
            ! in_array(
                $item->status,
                [
                    'new',
                    'changed',
                ],
                true
            )
        ) {
            throw new RuntimeException(
                'Item Source Sync ini tidak lagi menunggu import.'
            );
        }

        $category =
            Category::query()
                ->whereKey(
                    $categoryId
                )
                ->where(
                    'status',
                    'active'
                )
                ->first();

        if (! $category) {
            throw new RuntimeException(
                'Kategori import tidak tersedia atau tidak aktif.'
            );
        }

        $connector =
            $this
                ->connectors
                ->driver(
                    $item
                        ->connector
                );

        $payload =
            $connector
                ->fetch(
                    $item
                        ->external_id
                );

        $fetchedChecksum =
            trim(
                (string) (
                    $payload[
                        'checksum'
                    ]
                    ?? ''
                )
            );

        if (
            $fetchedChecksum
            === ''
        ) {
            throw new RuntimeException(
                'Connector tidak mengembalikan checksum source.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | TOCTOU Guard
        |--------------------------------------------------------------------------
        |
        | Kalau source remote berubah sesudah scan tapi sebelum import,
        | jangan import versi yang belum direview.
        |
        */

        if (
            filled(
                $item
                    ->external_checksum
            )
            &&
            ! hash_equals(
                (string)
                    $item
                        ->external_checksum,

                $fetchedChecksum
            )
        ) {
            $item->update([
                'status' =>
                    'failed',

                'error_message' =>
                    'Source berubah setelah proses scan. Jalankan Scan Sources lagi sebelum import.',

                'reviewed_by' =>
                    $reviewedBy,

                'reviewed_at' =>
                    now(),
            ]);

            $this
                ->refreshRunState(
                    $item->run
                );

            throw new RuntimeException(
                'Source berubah setelah proses scan. Scan ulang diperlukan.'
            );
        }

        $text =
            trim(
                (string) (
                    $payload[
                        'extracted_text'
                    ]
                    ?? ''
                )
            );

        if ($text === '') {
            throw new RuntimeException(
                'Connector tidak mengembalikan extracted text yang dapat diingest.'
            );
        }

        try {
            $document =
                DB::transaction(
                    function () use (
                        $item,
                        $payload,
                        $category,
                        $reviewedBy,
                        $fetchedChecksum,
                        $text
                    ): Document {
                        $lockedItem =
                            SourceSyncItem::query()
                                ->lockForUpdate()
                                ->findOrFail(
                                    $item->id
                                );

                        if (
                            ! in_array(
                                $lockedItem
                                    ->status,
                                [
                                    'new',
                                    'changed',
                                ],
                                true
                            )
                        ) {
                            throw new RuntimeException(
                                'Item Source Sync sudah diproses oleh proses lain.'
                            );
                        }

                        $document =
                            Document::query()
                                ->where(
                                    'source_connector',
                                    $lockedItem
                                        ->connector
                                )
                                ->where(
                                    'external_source_id',
                                    $lockedItem
                                        ->external_id
                                )
                                ->first();

                        $metadata =
                            is_array(
                                $payload[
                                    'metadata'
                                ]
                                ?? null
                            )
                                ? $payload[
                                    'metadata'
                                ]
                                : [];

                        $attributes = [
                            'title' =>
                                trim(
                                    (string) (
                                        $payload[
                                            'title'
                                        ]
                                        ??
                                        $lockedItem
                                            ->title
                                    )
                                ),

                            'abstract' =>
                                $metadata[
                                    'abstract'
                                ]
                                ?? null,

                            'category_id' =>
                                $category
                                    ->id,

                            'document_type' =>
                                'external_text',

                            'publication_year' =>
                                $this
                                    ->publicationYear(
                                        $metadata[
                                            'publication_year'
                                        ]
                                        ?? null
                                    ),

                            'language' =>
                                $metadata[
                                    'language'
                                ]
                                ?? 'id',

                            'doi' =>
                                $metadata[
                                    'doi'
                                ]
                                ?? null,

                            'isbn' =>
                                $metadata[
                                    'isbn'
                                ]
                                ?? null,

                            'publisher' =>
                                $metadata[
                                    'publisher'
                                ]
                                ?? null,

                            'source_label' =>
                                $metadata[
                                    'source_label'
                                ]
                                ??
                                'Synced Source · '
                                .strtoupper(
                                    $lockedItem
                                        ->connector
                                ),

                            'source_connector' =>
                                $lockedItem
                                    ->connector,

                            'external_source_id' =>
                                $lockedItem
                                    ->external_id,

                            'external_checksum' =>
                                $fetchedChecksum,

                            'last_synced_at' =>
                                now(),

                            'visibility' =>
                                'public',
                        ];

                        if (! $document) {
                            $document =
                                Document::create(
                                    $attributes
                                    +
                                    [
                                        'uuid' =>
                                            (string)
                                                Str::uuid(),

                                        'uploaded_by' =>
                                            $reviewedBy,

                                        'status' =>
                                            'draft',

                                        'published_at' =>
                                            null,
                                    ]
                                );
                        } else {
                            $document
                                ->update(
                                    $attributes
                                );
                        }

                        /*
                        |--------------------------------------------------------------------------
                        | Ingest
                        |--------------------------------------------------------------------------
                        |
                        | Target sengaja READY.
                        |
                        | Tidak boleh langsung published.
                        |
                        */

                        $this
                            ->ingestion
                            ->ingest(
                                $document,
                                null,
                                $text,
                                'ready',
                                true
                            );

                        $document
                            ->refresh();

                        if (
                            $document
                                ->status
                            ===
                            'published'
                        ) {
                            throw new RuntimeException(
                                'Source Sync tidak boleh melakukan auto-publish.'
                            );
                        }

                        $lockedItem->update([
                            'category_id' =>
                                $category
                                    ->id,

                            'matched_document_id' =>
                                $document
                                    ->id,

                            'imported_document_id' =>
                                $document
                                    ->id,

                            'status' =>
                                'imported',

                            'error_message' =>
                                null,

                            'reviewed_by' =>
                                $reviewedBy,

                            'reviewed_at' =>
                                now(),

                            'imported_at' =>
                                now(),
                        ]);

                        return $document;
                    }
                );

            $this
                ->refreshRunState(
                    $item->run
                );

            return $document
                ->fresh([
                    'category',
                    'chunks',
                ]);
        } catch (
            Throwable $exception
        ) {
            SourceSyncItem::query()
                ->whereKey(
                    $item->id
                )
                ->whereIn(
                    'status',
                    [
                        'new',
                        'changed',
                    ]
                )
                ->update([
                    'status' =>
                        'failed',

                    'error_message' =>
                        $exception
                            ->getMessage(),

                    'reviewed_by' =>
                        $reviewedBy,

                    'reviewed_at' =>
                        now(),
                ]);

            $this
                ->refreshRunState(
                    $item->run
                );

            throw $exception;
        }
    }

    public function ignore(
        SourceSyncItem $item,
        ?int $reviewedBy = null
    ): SourceSyncItem {
        $item =
            SourceSyncItem::query()
                ->with(
                    'run'
                )
                ->findOrFail(
                    $item->id
                );

        if (
            ! in_array(
                $item->status,
                [
                    'new',
                    'changed',
                ],
                true
            )
        ) {
            throw new RuntimeException(
                'Item ini tidak lagi menunggu review.'
            );
        }

        $item->update([
            'status' =>
                'ignored',

            'error_message' =>
                null,

            'reviewed_by' =>
                $reviewedBy,

            'reviewed_at' =>
                now(),
        ]);

        $this
            ->refreshRunState(
                $item->run
            );

        return $item
            ->fresh();
    }

    private function stageDiscoveredItem(
        SourceSyncRun $run,
        string $connector,
        array $item
    ): SourceSyncItem {
        $externalId =
            trim(
                (string) (
                    $item[
                        'external_id'
                    ]
                    ?? ''
                )
            );

        $title =
            trim(
                (string) (
                    $item[
                        'title'
                    ]
                    ?? ''
                )
            );

        if (
            $externalId === ''
            ||
            $title === ''
        ) {
            throw new RuntimeException(
                'Connector mengembalikan item tanpa external_id atau title.'
            );
        }

        $checksum =
            filled(
                $item[
                    'checksum'
                ]
                ?? null
            )
                ? trim(
                    (string)
                        $item[
                            'checksum'
                        ]
                )
                : null;

        $existingDocument =
            Document::query()
                ->where(
                    'source_connector',
                    $connector
                )
                ->where(
                    'external_source_id',
                    $externalId
                )
                ->first();

        $previousChecksum =
            $existingDocument
                ?->external_checksum;

        $status =
            match (true) {
                ! $existingDocument =>
                    'new',

                filled(
                    $checksum
                )
                &&
                filled(
                    $previousChecksum
                )
                &&
                hash_equals(
                    (string)
                        $previousChecksum,

                    (string)
                        $checksum
                ) =>
                    'unchanged',

                default =>
                    'changed',
            };

        return SourceSyncItem::create([
            'source_sync_run_id' =>
                $run->id,

            'connector' =>
                $connector,

            'external_id' =>
                $externalId,

            'title' =>
                $title,

            'external_checksum' =>
                $checksum,

            'previous_checksum' =>
                $previousChecksum,

            'remote_updated_at' =>
                $this
                    ->parseDate(
                        $item[
                            'updated_at'
                        ]
                        ?? null
                    ),

            'status' =>
                $status,

            'matched_document_id' =>
                $existingDocument
                    ?->id,

            'payload_json' => [
                'metadata' =>
                    is_array(
                        $item[
                            'metadata'
                        ]
                        ?? null
                    )
                        ? $item[
                            'metadata'
                        ]
                        : [],
            ],
        ]);
    }

    private function refreshRunState(
        SourceSyncRun $run
    ): void {
        $run =
            SourceSyncRun::query()
                ->findOrFail(
                    $run->id
                );

        $counts =
            $run
                ->items()
                ->selectRaw(
                    'status, COUNT(*) as aggregate'
                )
                ->groupBy(
                    'status'
                )
                ->pluck(
                    'aggregate',
                    'status'
                );

        $newCount =
            (int) (
                $counts[
                    'new'
                ]
                ?? 0
            );

        $changedCount =
            (int) (
                $counts[
                    'changed'
                ]
                ?? 0
            );

        $unchangedCount =
            (int) (
                $counts[
                    'unchanged'
                ]
                ?? 0
            );

        $importedCount =
            (int) (
                $counts[
                    'imported'
                ]
                ?? 0
            );

        $ignoredCount =
            (int) (
                $counts[
                    'ignored'
                ]
                ?? 0
            );

        $failedCount =
            (int) (
                $counts[
                    'failed'
                ]
                ?? 0
            );

        $pendingReview =
            $newCount
            +
            $changedCount;

        $status =
            match (true) {
                $pendingReview > 0 =>
                    'review_ready',

                $failedCount > 0 =>
                    'completed_with_errors',

                default =>
                    'completed',
            };

        $run->update([
            'status' =>
                $status,

            'discovered_count' =>
                (int)
                    $run
                        ->items()
                        ->count(),

            'new_count' =>
                $newCount,

            'changed_count' =>
                $changedCount,

            'unchanged_count' =>
                $unchangedCount,

            'imported_count' =>
                $importedCount,

            'ignored_count' =>
                $ignoredCount,

            'finished_at' =>
                now(),
        ]);
    }

    private function parseDate(
        mixed $value
    ): ?Carbon {
        if (
            blank(
                $value
            )
        ) {
            return null;
        }

        try {
            return Carbon::parse(
                (string)
                    $value
            );
        } catch (
            Throwable
        ) {
            return null;
        }
    }

    private function publicationYear(
        mixed $value
    ): ?int {
        if (
            ! is_numeric(
                $value
            )
        ) {
            return null;
        }

        $year =
            (int)
                $value;

        if (
            $year < 1000
            ||
            $year > 9999
        ) {
            return null;
        }

        return $year;
    }
}