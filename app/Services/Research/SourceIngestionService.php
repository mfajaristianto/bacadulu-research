<?php

namespace App\Services\Research;

use App\Exceptions\SourceIngestionException;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\DocumentFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class SourceIngestionService
{
    public function __construct(
        private readonly PdfTextExtractor $pdfTextExtractor,
        private readonly DocumentChunker $chunker
    ) {}

    /**
     * Backward-compatible synchronous entry point.
     *
     * Source Sync masih memakai manual extracted text lewat method ini.
     * Admin Library sekarang memakai stageUpload() + queue worker.
     *
     * @return array{
     *     processed: bool,
     *     extraction_method: string|null,
     *     chunks: int,
     *     status: string,
     *     message: string,
     *     stale?: bool
     * }
     */
    public function ingest(
        Document $document,
        ?UploadedFile $uploadedFile,
        ?string $manualExtractedText,
        string $targetStatus = 'ready',
        bool $forceReprocess = false
    ): array {
        $manualExtractedText =
            trim(
                (string)
                    $manualExtractedText
            );

        if ($uploadedFile) {
            $sourceFile =
                $this->stageUpload(
                    $document,
                    $uploadedFile
                );

            return $this->processStoredFile(
                $document,
                $sourceFile,
                $targetStatus
            );
        }

        if (
            $manualExtractedText !== ''
        ) {
            return $this->processText(
                $document,
                $manualExtractedText,
                'manual',
                null,
                $targetStatus
            );
        }

        if ($forceReprocess) {
            $sourceFile =
                $this->queueExistingFile(
                    $document
                );

            return $this->processStoredFile(
                $document,
                $sourceFile,
                $targetStatus
            );
        }

        $this->transitionStatus(
            $document,
            $targetStatus
        );

        return [
            'processed' =>
                false,

            'extraction_method' =>
                null,

            'chunks' =>
                $document
                    ->chunks()
                    ->count(),

            'status' =>
                $document
                    ->fresh()
                    ->status,

            'message' =>
                'Metadata source berhasil diperbarui.',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Stage Upload
    |--------------------------------------------------------------------------
    |
    | Hanya simpan PDF.
    |
    | Tidak melakukan:
    |
    | - pdftotext
    | - parser
    | - chunking
    |
    | Jadi HTTP request cepat selesai.
    |
    */

    public function stageUpload(
        Document $document,
        UploadedFile $uploadedFile
    ): DocumentFile {
        $this->assertPdf(
            $uploadedFile
        );

        $disk =
            (string)
                config(
                    'research.ingestion.disk',
                    'research_sources'
                );

        $storedPath =
            $uploadedFile
                ->store(
                    '',
                    $disk
                );

        if (! $storedPath) {
            throw new RuntimeException(
                'PDF gagal disimpan.'
            );
        }

        try {
            $absolutePath =
                Storage::disk(
                    $disk
                )
                    ->path(
                        $storedPath
                    );

            $checksum =
                hash_file(
                    'sha256',
                    $absolutePath
                );

            return DB::transaction(
                function () use (
                    $document,
                    $uploadedFile,
                    $disk,
                    $storedPath,
                    $checksum
                ): DocumentFile {

                    $sourceFile =
                        DocumentFile::create([
                            'document_id' =>
                                $document->id,

                            'disk' =>
                                $disk,

                            'path' =>
                                $storedPath,

                            'original_name' =>
                                $uploadedFile
                                    ->getClientOriginalName(),

                            'mime_type' =>
                                $uploadedFile
                                    ->getMimeType()
                                ?:
                                'application/pdf',

                            'file_size' =>
                                $uploadedFile
                                    ->getSize(),

                            'checksum' =>
                                $checksum !== false
                                    ? $checksum
                                    : null,

                            'status' =>
                                'queued',
                        ]);

                    /*
                     * PDF lama tetap ada untuk provenance,
                     * tapi tidak lagi menjadi source aktif.
                     */

                    DocumentFile::query()
                        ->where(
                            'document_id',
                            $document->id
                        )
                        ->where(
                            'id',
                            '<>',
                            $sourceFile->id
                        )
                        ->whereIn(
                            'status',
                            [
                                'active',
                                'queued',
                                'processing',
                                'parse_failed',
                            ]
                        )
                        ->update([
                            'status' =>
                                'superseded',
                        ]);

                    $document->update([
                        'status' =>
                            'processing',

                        'published_at' =>
                            null,

                        'processing_stage' =>
                            'queued',

                        'processing_progress' =>
                            5,

                        'processing_file_id' =>
                            $sourceFile->id,

                        'processing_error' =>
                            null,

                        'processing_started_at' =>
                            null,

                        'processing_finished_at' =>
                            null,
                    ]);

                    return $sourceFile;
                }
            );
        } catch (
            Throwable $exception
        ) {
            Storage::disk(
                $disk
            )
                ->delete(
                    $storedPath
                );

            throw $exception;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Reprocess Existing File
    |--------------------------------------------------------------------------
    */

    public function queueExistingFile(
        Document $document
    ): DocumentFile {
        $sourceFile =
            $this->latestProcessableFile(
                $document
            );

        if (! $sourceFile) {
            throw new SourceIngestionException(
                'Tidak ada source PDF yang dapat diproses ulang.'
            );
        }

        DB::transaction(
            function () use (
                $document,
                $sourceFile
            ): void {

                $sourceFile->update([
                    'status' =>
                        'queued',
                ]);

                $document->update([
                    'status' =>
                        'processing',

                    'published_at' =>
                        null,

                    'processing_stage' =>
                        'queued',

                    'processing_progress' =>
                        5,

                    'processing_file_id' =>
                        $sourceFile->id,

                    'processing_error' =>
                        null,

                    'processing_started_at' =>
                        null,

                    'processing_finished_at' =>
                        null,
                ]);
            }
        );

        return $sourceFile
            ->fresh();
    }

    public function latestProcessableFile(
        Document $document
    ): ?DocumentFile {
        return DocumentFile::query()
            ->where(
                'document_id',
                $document->id
            )
            ->whereIn(
                'status',
                [
                    'active',
                    'queued',
                    'processing',
                    'parse_failed',
                ]
            )
            ->latest(
                'id'
            )
            ->first();
    }

    /*
    |--------------------------------------------------------------------------
    | Background PDF Processing
    |--------------------------------------------------------------------------
    */

    public function processStoredFile(
        Document $document,
        DocumentFile $sourceFile,
        string $targetStatus = 'ready'
    ): array {
        $document->refresh();
        $sourceFile->refresh();

        if (
            (int)
                $sourceFile
                    ->document_id
            !==
            (int)
                $document
                    ->id
        ) {
            throw new SourceIngestionException(
                'Source file tidak dimiliki oleh document ini.'
            );
        }

        /*
         * File sudah diganti oleh upload yang lebih baru.
         */

        if (
            $this->isStaleFile(
                $document,
                $sourceFile
            )
        ) {
            return $this->staleResult(
                $document
            );
        }

        $sourceFile->update([
            'status' =>
                'processing',
        ]);

        $document->update([
            'status' =>
                'processing',

            'published_at' =>
                null,

            'processing_stage' =>
                'extracting',

            'processing_progress' =>
                20,

            'processing_file_id' =>
                $sourceFile->id,

            'processing_error' =>
                null,

            'processing_started_at' =>
                $document
                    ->processing_started_at
                ?: now(),

            'processing_finished_at' =>
                null,
        ]);

        /*
        |--------------------------------------------------------------------------
        | PDF → Text
        |--------------------------------------------------------------------------
        */

        try {
            $absolutePath =
                Storage::disk(
                    $sourceFile
                        ->disk
                )
                    ->path(
                        $sourceFile
                            ->path
                    );

            $sourceText =
                $this
                    ->pdfTextExtractor
                    ->extract(
                        $absolutePath
                    );

            $extractionMethod =
                method_exists(
                    $this->pdfTextExtractor,
                    'lastMethod'
                )
                    ? (
                        $this
                            ->pdfTextExtractor
                            ->lastMethod()
                        ?:
                        'pdf'
                    )
                    : 'pdf';
        } catch (
            Throwable $exception
        ) {
            $this->markProcessingFailure(
                $document,
                $sourceFile,
                $exception
                    ->getMessage()
            );

            throw new SourceIngestionException(
                $exception
                    ->getMessage(),
                previous:
                    $exception
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Text → Chunks
        |--------------------------------------------------------------------------
        */

        $document->update([
            'processing_stage' =>
                'chunking',

            'processing_progress' =>
                60,
        ]);

        $chunks =
            $this
                ->chunker
                ->chunk(
                    $sourceText,
                    $extractionMethod,
                    $sourceFile->id
                );

        if ($chunks === []) {
            $message =
                'Text berhasil dibaca tetapi tidak menghasilkan chunk yang layak.';

            $this->markProcessingFailure(
                $document,
                $sourceFile,
                $message
            );

            throw new SourceIngestionException(
                $message
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Stale Worker Protection
        |--------------------------------------------------------------------------
        |
        | Contoh:
        |
        | worker memproses PDF A
        | admin upload PDF B
        | PDF A selesai belakangan
        |
        | PDF A TIDAK BOLEH mengganti chunks PDF B.
        |
        */

        $document->refresh();
        $sourceFile->refresh();

        if (
            $this->isStaleFile(
                $document,
                $sourceFile
            )
        ) {
            return $this->staleResult(
                $document
            );
        }

        DB::transaction(
            function () use (
                $document,
                $sourceFile,
                $chunks,
                $targetStatus
            ): void {

                $lockedDocument =
                    Document::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $document->id
                        );

                $lockedFile =
                    DocumentFile::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $sourceFile->id
                        );

                /*
                 * Cek sekali lagi setelah lock.
                 */

                if (
                    $lockedFile->status
                    ===
                    'superseded'
                    ||
                    (int)
                        $lockedDocument
                            ->processing_file_id
                    !==
                    (int)
                        $lockedFile
                            ->id
                ) {
                    return;
                }

                DocumentChunk::query()
                    ->where(
                        'document_id',
                        $lockedDocument
                            ->id
                    )
                    ->delete();

                foreach (
                    $chunks as
                    $index => $chunk
                ) {
                    DocumentChunk::create([
                        'document_id' =>
                            $lockedDocument
                                ->id,

                        'chunk_index' =>
                            $index,

                        'content' =>
                            $chunk[
                                'content'
                            ],

                        'metadata_json' =>
                            $chunk[
                                'metadata'
                            ],
                    ]);
                }

                DocumentFile::query()
                    ->where(
                        'document_id',
                        $lockedDocument
                            ->id
                    )
                    ->where(
                        'id',
                        '<>',
                        $lockedFile
                            ->id
                    )
                    ->whereIn(
                        'status',
                        [
                            'active',
                            'queued',
                            'processing',
                            'parse_failed',
                        ]
                    )
                    ->update([
                        'status' =>
                            'superseded',
                    ]);

                $lockedFile->update([
                    'status' =>
                        'active',
                ]);

                $this->applyTargetStatus(
                    $lockedDocument,
                    $targetStatus
                );

                $lockedDocument->update([
                    'processing_stage' =>
                        'completed',

                    'processing_progress' =>
                        100,

                    'processing_file_id' =>
                        $lockedFile
                            ->id,

                    'processing_error' =>
                        null,

                    'processing_started_at' =>
                        $lockedDocument
                            ->processing_started_at
                        ?: now(),

                    'processing_finished_at' =>
                        now(),
                ]);
            }
        );

        $document->refresh();

        return [
            'processed' =>
                true,

            'extraction_method' =>
                $extractionMethod,

            'chunks' =>
                count(
                    $chunks
                ),

            'status' =>
                $document
                    ->status,

            'message' =>
                'Source berhasil diproses menjadi '
                .count($chunks)
                .' chunks.',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Lifecycle
    |--------------------------------------------------------------------------
    */

    public function transitionStatus(
        Document $document,
        string $targetStatus
    ): Document {
        $document->refresh();

        if (
            $targetStatus
            ===
            'published'
            &&
            $document
                ->is_processing
        ) {
            throw new SourceIngestionException(
                'Source masih diproses. Tunggu sampai status Ready sebelum Publish.'
            );
        }

        if (
            $targetStatus
            ===
            'published'
            &&
            ! $document
                ->chunks()
                ->exists()
        ) {
            throw new SourceIngestionException(
                'Source belum dapat dipublish karena chunks belum tersedia.'
            );
        }

        /*
         * Archive juga membatalkan queued worker.
         */

        if (
            $targetStatus
            ===
            'archived'
        ) {
            DocumentFile::query()
                ->where(
                    'document_id',
                    $document->id
                )
                ->whereIn(
                    'status',
                    [
                        'queued',
                        'processing',
                    ]
                )
                ->update([
                    'status' =>
                        'superseded',
                ]);

            $document->update([
                'processing_stage' =>
                    'archived',

                'processing_progress' =>
                    100,

                'processing_file_id' =>
                    null,

                'processing_error' =>
                    null,

                'processing_finished_at' =>
                    now(),
            ]);
        }

        $this->applyTargetStatus(
            $document,
            $targetStatus
        );

        return $document
            ->fresh();
    }

    /*
    |--------------------------------------------------------------------------
    | Manual / Connector Text
    |--------------------------------------------------------------------------
    |
    | Dipertahankan karena Source Sync memakai extracted_text langsung.
    |
    */

    private function processText(
        Document $document,
        string $sourceText,
        string $extractionMethod,
        ?int $sourceFileId,
        string $targetStatus
    ): array {
        $document->update([
            'status' =>
                'processing',

            'published_at' =>
                null,

            'processing_stage' =>
                'chunking',

            'processing_progress' =>
                60,

            'processing_file_id' =>
                $sourceFileId,

            'processing_error' =>
                null,

            'processing_started_at' =>
                now(),

            'processing_finished_at' =>
                null,
        ]);

        $chunks =
            $this
                ->chunker
                ->chunk(
                    $sourceText,
                    $extractionMethod,
                    $sourceFileId
                );

        if ($chunks === []) {
            $message =
                'Text berhasil dibaca tetapi tidak menghasilkan chunk yang layak.';

            $document->update([
                'status' =>
                    'failed',

                'processing_stage' =>
                    'failed',

                'processing_progress' =>
                    100,

                'processing_error' =>
                    $message,

                'processing_finished_at' =>
                    now(),

                'published_at' =>
                    null,
            ]);

            throw new SourceIngestionException(
                $message
            );
        }

        DB::transaction(
            function () use (
                $document,
                $chunks,
                $targetStatus
            ): void {

                DocumentChunk::query()
                    ->where(
                        'document_id',
                        $document->id
                    )
                    ->delete();

                foreach (
                    $chunks as
                    $index => $chunk
                ) {
                    DocumentChunk::create([
                        'document_id' =>
                            $document->id,

                        'chunk_index' =>
                            $index,

                        'content' =>
                            $chunk[
                                'content'
                            ],

                        'metadata_json' =>
                            $chunk[
                                'metadata'
                            ],
                    ]);
                }

                $this->applyTargetStatus(
                    $document,
                    $targetStatus
                );

                $document->update([
                    'processing_stage' =>
                        'completed',

                    'processing_progress' =>
                        100,

                    'processing_error' =>
                        null,

                    'processing_finished_at' =>
                        now(),
                ]);
            }
        );

        return [
            'processed' =>
                true,

            'extraction_method' =>
                $extractionMethod,

            'chunks' =>
                count(
                    $chunks
                ),

            'status' =>
                $document
                    ->fresh()
                    ->status,

            'message' =>
                'Source berhasil diproses menjadi '
                .count($chunks)
                .' chunks.',
        ];
    }

    private function isStaleFile(
        Document $document,
        DocumentFile $sourceFile
    ): bool {
        return $sourceFile
                ->status
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
            );
    }

    private function staleResult(
        Document $document
    ): array {
        return [
            'processed' =>
                false,

            'extraction_method' =>
                null,

            'chunks' =>
                $document
                    ->chunks()
                    ->count(),

            'status' =>
                $document
                    ->status,

            'message' =>
                'Job ingestion dilewati karena PDF ini sudah digantikan oleh file yang lebih baru.',

            'stale' =>
                true,
        ];
    }

    private function markProcessingFailure(
        Document $document,
        DocumentFile $sourceFile,
        string $message
    ): void {
        $document->refresh();
        $sourceFile->refresh();

        /*
         * Jangan tandai document gagal kalau failure
         * berasal dari worker lama yang sudah superseded.
         */

        if (
            $this->isStaleFile(
                $document,
                $sourceFile
            )
        ) {
            return;
        }

        $sourceFile->update([
            'status' =>
                'parse_failed',
        ]);

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
                mb_substr(
                    $message,
                    0,
                    4000
                ),

            'processing_finished_at' =>
                now(),
        ]);
    }

    private function assertPdf(
        UploadedFile $file
    ): void {
        if (! $file->isValid()) {
            throw new RuntimeException(
                'Upload PDF tidak valid.'
            );
        }

        $realPath =
            $file
                ->getRealPath();

        if (
            ! $realPath
            ||
            ! is_file(
                $realPath
            )
        ) {
            throw new RuntimeException(
                'Temporary upload file tidak ditemukan.'
            );
        }

        $handle =
            fopen(
                $realPath,
                'rb'
            );

        if (
            $handle === false
        ) {
            throw new RuntimeException(
                'PDF tidak dapat dibaca.'
            );
        }

        try {
            $signature =
                fread(
                    $handle,
                    5
                );
        } finally {
            fclose(
                $handle
            );
        }

        if (
            $signature
            !==
            '%PDF-'
        ) {
            throw new RuntimeException(
                'File bukan PDF yang valid.'
            );
        }
    }

    private function applyTargetStatus(
        Document $document,
        string $targetStatus
    ): void {
        $allowed = [
            'draft',
            'processing',
            'ready',
            'published',
            'failed',
            'archived',
        ];

        if (
            ! in_array(
                $targetStatus,
                $allowed,
                true
            )
        ) {
            $targetStatus =
                'ready';
        }

        if (
            $targetStatus
            ===
            'published'
            &&
            ! $document
                ->chunks()
                ->exists()
        ) {
            $targetStatus =
                'ready';
        }

        $document->update([
            'status' =>
                $targetStatus,

            'published_at' =>
                $targetStatus
                ===
                'published'
                    ? (
                        $document
                            ->published_at
                        ?: now()
                    )
                    : null,
        ]);
    }
}