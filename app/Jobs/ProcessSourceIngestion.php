<?php

namespace App\Jobs;

use App\Exceptions\SourceIngestionException;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Services\Research\SourceIngestionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Throwable;

class ProcessSourceIngestion implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 240;

    public bool $failOnTimeout = true;

    public function __construct(
        public readonly int $documentId,
        public readonly int $documentFileId,
        public readonly string $targetStatus = 'ready',
        public readonly ?int $adminUserId = null
    ) {
        $this->onQueue(
            (string)
                config(
                    'research.queue.ingestion_queue',
                    'ingestion'
                )
        );
    }

    public function handle(
        SourceIngestionService $ingestion
    ): void {
        $document =
            Document::query()
                ->find(
                    $this->documentId
                );

        $sourceFile =
            DocumentFile::query()
                ->find(
                    $this->documentFileId
                );

        if (
            ! $document
            ||
            ! $sourceFile
        ) {
            return;
        }

        if (
            (int)
                $sourceFile
                    ->document_id
            !==
            (int)
                $document
                    ->id
        ) {
            return;
        }

        /*
         * Worker lama tidak boleh menyentuh upload baru.
         */

        if (
            $sourceFile->status
            ===
            'superseded'
            ||
            (
                $document
                    ->processing_file_id
                !==
                null
                &&
                (int)
                    $document
                        ->processing_file_id
                !==
                (int)
                    $sourceFile
                        ->id
            )
        ) {
            return;
        }

        try {
            $result =
                $ingestion
                    ->processStoredFile(
                        $document,
                        $sourceFile,
                        $this->targetStatus
                    );

            $this->audit(
                'source.ingestion_completed',
                $document,
                [
                    'document_file_id' =>
                        $sourceFile->id,

                    'status' =>
                        $result[
                            'status'
                        ],

                    'chunks' =>
                        $result[
                            'chunks'
                        ],

                    'extraction_method' =>
                        $result[
                            'extraction_method'
                        ],

                    'stale' =>
                        (bool) (
                            $result[
                                'stale'
                            ]
                            ?? false
                        ),
                ]
            );
        } catch (
            SourceIngestionException $exception
        ) {
            /*
             * PDF tidak bisa diekstrak bukan infrastructure crash.
             *
             * Document sudah berstatus failed.
             * Jangan masukkan ke failed_jobs.
             */

            $this->audit(
                'source.ingestion_failed',
                $document,
                [
                    'document_file_id' =>
                        $sourceFile->id,

                    'message' =>
                        Str::limit(
                            $exception
                                ->getMessage(),
                            1000,
                            '…'
                        ),
                ]
            );

            return;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Actual Queue Failure
    |--------------------------------------------------------------------------
    |
    | Timeout / DB crash / uncaught infrastructure exception.
    |
    */

    public function failed(
        ?Throwable $exception
    ): void {
        $document =
            Document::query()
                ->find(
                    $this->documentId
                );

        $sourceFile =
            DocumentFile::query()
                ->find(
                    $this->documentFileId
                );

        if (! $document) {
            return;
        }

        $document->refresh();

        /*
         * Jangan menimpa upload yang lebih baru.
         */

        if (
            $document
                ->processing_file_id
            !==
            null
            &&
            (int)
                $document
                    ->processing_file_id
            !==
            (int)
                $this
                    ->documentFileId
        ) {
            return;
        }

        if (
            $sourceFile
            &&
            $sourceFile
                ->status
            !==
            'superseded'
        ) {
            $sourceFile->update([
                'status' =>
                    'parse_failed',
            ]);
        }

        $document->update([
            'status' =>
                'failed',

            'published_at' =>
                null,

            'processing_stage' =>
                'failed',

            'processing_progress' =>
                100,

            'processing_error' =>
                Str::limit(
                    $exception
                        ?->getMessage()
                    ?: 'Queue ingestion gagal.',
                    4000,
                    '…'
                ),

            'processing_finished_at' =>
                now(),
        ]);

        $this->audit(
            'source.ingestion_queue_failed',
            $document,
            [
                'document_file_id' =>
                    $this
                        ->documentFileId,

                'message' =>
                    Str::limit(
                        $exception
                            ?->getMessage()
                        ?: 'Queue ingestion gagal.',
                        1000,
                        '…'
                    ),
            ]
        );
    }

    private function audit(
        string $action,
        Document $document,
        array $metadata = []
    ): void {
        try {
            AuditLog::create([
                'user_id' =>
                    $this
                        ->adminUserId,

                'action' =>
                    $action,

                'auditable_type' =>
                    Document::class,

                'auditable_id' =>
                    $document
                        ->id,

                'metadata_json' =>
                    $metadata,

                'ip_address' =>
                    null,
            ]);
        } catch (
            Throwable
        ) {
            /*
             * Audit tidak boleh menggagalkan ingestion.
             */
        }
    }
}

