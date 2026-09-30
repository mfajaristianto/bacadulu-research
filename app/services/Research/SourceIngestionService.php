<?php

namespace App\Services\Research;

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
     * @return array{
     *     processed: bool,
     *     extraction_method: string|null,
     *     chunks: int,
     *     status: string,
     *     message: string
     * }
     */
    public function ingest(
        Document $document,
        ?UploadedFile $uploadedFile,
        ?string $manualExtractedText,
        string $targetStatus = 'ready',
        bool $forceReprocess = false
    ): array {
        $disk = (string) config(
            'research.ingestion.disk',
            'research_sources'
        );

        $newDocumentFile = null;

        if ($uploadedFile) {
            $this->assertPdf(
                $uploadedFile
            );

            $storedPath = $uploadedFile
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
                    Storage::disk($disk)
                        ->path(
                            $storedPath
                        );

                $checksum = hash_file(
                    'sha256',
                    $absolutePath
                );

                $newDocumentFile =
                    DocumentFile::create([
                        'document_id' => $document->id,

                        'disk' => $disk,

                        'path' => $storedPath,

                        'original_name' => $uploadedFile
                            ->getClientOriginalName(),

                        'mime_type' => $uploadedFile
                            ->getMimeType()
                                ?: 'application/pdf',

                        'file_size' => $uploadedFile
                            ->getSize(),

                        'checksum' => $checksum !== false
                                ? $checksum
                                : null,

                        'status' => 'active',
                    ]);

                /*
                 * File lama tetap disimpan untuk audit,
                 * tapi tidak lagi menjadi active source file.
                 */
                DocumentFile::query()
                    ->where(
                        'document_id',
                        $document->id
                    )
                    ->where(
                        'id',
                        '<>',
                        $newDocumentFile->id
                    )
                    ->where(
                        'status',
                        'active'
                    )
                    ->update([
                        'status' => 'superseded',
                    ]);
            } catch (Throwable $exception) {
                Storage::disk($disk)
                    ->delete(
                        $storedPath
                    );

                throw $exception;
            }
        }

        $manualExtractedText = trim(
            (string) $manualExtractedText
        );

        /*
        |--------------------------------------------------------------------------
        | Tentukan apakah ingestion perlu dijalankan
        |--------------------------------------------------------------------------
        */

        $shouldProcess =
            $uploadedFile !== null
            || $manualExtractedText !== ''
            || $forceReprocess;

        if (! $shouldProcess) {
            $this->applyTargetStatus(
                $document,
                $targetStatus
            );

            return [
                'processed' => false,
                'extraction_method' => null,
                'chunks' => $document
                    ->chunks()
                    ->count(),
                'status' => $document
                    ->fresh()
                    ->status,
                'message' => 'Metadata source berhasil diperbarui.',
            ];
        }

        $document->update([
            'status' => 'processing',
            'published_at' => null,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Resolve source text
        |--------------------------------------------------------------------------
        */

        $sourceText = null;

        $extractionMethod = null;

        $sourceFile = $newDocumentFile;

        if ($manualExtractedText !== '') {
            $sourceText =
                $manualExtractedText;

            $extractionMethod =
                'manual';
        } else {
            /*
             * Reprocess file existing jika tidak ada upload baru.
             */
            $sourceFile ??=
                DocumentFile::query()
                    ->where(
                        'document_id',
                        $document->id
                    )
                    ->whereIn(
                        'status',
                        [
                            'active',
                            'parse_failed',
                        ]
                    )
                    ->latest('id')
                    ->first();

            if (! $sourceFile) {
                throw new RuntimeException(
                    'Tidak ada source PDF yang dapat diproses. Upload PDF atau isi Extracted Text.'
                );
            }

            try {
                $sourceText =
                    $this->pdfTextExtractor
                        ->extract(
                            Storage::disk(
                                $sourceFile->disk
                            )->path(
                                $sourceFile->path
                            )
                        );

                $extractionMethod =
                    'pdftotext';

                $sourceFile->update([
                    'status' => 'active',
                ]);
            } catch (Throwable $exception) {
                $sourceFile->update([
                    'status' => 'parse_failed',
                ]);

                /*
                 * File sudah tersimpan aman.
                 * Document tetap processing agar tidak
                 * ikut retrieval sebelum mempunyai chunks.
                 */
                $document->update([
                    'status' => 'processing',
                    'published_at' => null,
                ]);

                throw $exception;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Chunk
        |--------------------------------------------------------------------------
        */

        $chunks =
            $this->chunker
                ->chunk(
                    $sourceText,
                    $extractionMethod,
                    $sourceFile?->id
                );

        if ($chunks === []) {
            $document->update([
                'status' => 'processing',
                'published_at' => null,
            ]);

            throw new RuntimeException(
                'Text berhasil dibaca tetapi tidak menghasilkan chunk yang layak.'
            );
        }

        DB::transaction(
            function () use (
                $document,
                $chunks
            ): void {
                /*
                 * Reindex = replace chunk lama.
                 */
                DocumentChunk::query()
                    ->where(
                        'document_id',
                        $document->id
                    )
                    ->delete();

                foreach (
                    $chunks as $index => $chunk
                ) {
                    DocumentChunk::create([
                        'document_id' => $document->id,

                        'chunk_index' => $index,

                        'content' => $chunk[
                                'content'
                            ],

                        'metadata_json' => $chunk[
                                'metadata'
                            ],
                    ]);
                }
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Ready / Published
        |--------------------------------------------------------------------------
        */

        $this->applyTargetStatus(
            $document,
            $targetStatus
        );

        return [
            'processed' => true,

            'extraction_method' => $extractionMethod,

            'chunks' => count($chunks),

            'status' => $document
                ->fresh()
                ->status,

            'message' => 'Source berhasil diproses menjadi '
                    .count($chunks)
                    .' chunks.',
        ];
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
            $file->getRealPath();

        if (
            ! $realPath
            || ! is_file($realPath)
        ) {
            throw new RuntimeException(
                'Temporary upload file tidak ditemukan.'
            );
        }

        $handle = fopen(
            $realPath,
            'rb'
        );

        if ($handle === false) {
            throw new RuntimeException(
                'PDF tidak dapat dibaca.'
            );
        }

        try {
            $signature = fread(
                $handle,
                5
            );
        } finally {
            fclose(
                $handle
            );
        }

        if ($signature !== '%PDF-') {
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
            'archived',
        ];

        if (
            ! in_array(
                $targetStatus,
                $allowed,
                true
            )
        ) {
            $targetStatus = 'ready';
        }

        /*
         * Source tidak boleh published tanpa chunk.
         */
        if (
            $targetStatus === 'published'
            && ! $document
                ->chunks()
                ->exists()
        ) {
            $targetStatus = 'processing';
        }

        $document->update([
            'status' => $targetStatus,

            'published_at' => $targetStatus
                    === 'published'
                ? (
                    $document
                        ->published_at
                    ?: now()
                )
                : null,
        ]);
    }
}
