<?php

namespace Tests\Feature;

use App\Jobs\ProcessSourceIngestion;
use App\Models\Category;
use App\Models\Document;
use App\Models\User;
use App\Services\Research\DocumentChunker;
use App\Services\Research\PdfTextExtractor;
use App\Services\Research\SourceIngestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class SourceIngestionQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set(
            'research.ingestion.disk',
            'research_sources'
        );

        config()->set(
            'research.ingestion.chunk_words',
            100
        );

        config()->set(
            'research.ingestion.chunk_overlap_words',
            20
        );

        config()->set(
            'research.ingestion.minimum_chunk_words',
            20
        );

        Storage::fake(
            'research_sources'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | 01
    | Upload hanya staging
    |--------------------------------------------------------------------------
    */

    public function test_upload_is_staged_without_running_extraction_in_http_request(): void
    {
        $document =
            $this->document();

        $service =
            $this->successfulService();

        $file =
            $service->stageUpload(
                $document,
                $this->fakePdf(
                    'first.pdf'
                )
            );

        $document->refresh();
        $file->refresh();

        $this->assertSame(
            'processing',
            $document->status
        );

        $this->assertSame(
            'queued',
            $document->processing_stage
        );

        $this->assertSame(
            5,
            $document->processing_progress
        );

        $this->assertSame(
            $file->id,
            $document->processing_file_id
        );

        $this->assertSame(
            'queued',
            $file->status
        );

        /*
         * HTTP staging tidak boleh melakukan
         * extraction/chunking.
         */

        $this->assertSame(
            0,
            $document
                ->chunks()
                ->count()
        );
    }

    /*
    |--------------------------------------------------------------------------
    | 02
    | Queue memproses PDF sampai Ready
    |--------------------------------------------------------------------------
    */

    public function test_queue_job_extracts_chunks_and_moves_source_to_ready(): void
    {
        $document =
            $this->document();

        $service =
            $this->successfulService();

        $file =
            $service->stageUpload(
                $document,
                $this->fakePdf(
                    'good.pdf'
                )
            );

        $job =
            new ProcessSourceIngestion(
                $document->id,
                $file->id,
                'ready',
                null
            );

        $job->handle(
            $service
        );

        $document->refresh();
        $file->refresh();

        $this->assertSame(
            'ready',
            $document->status
        );

        $this->assertSame(
            'completed',
            $document->processing_stage
        );

        $this->assertSame(
            100,
            $document->processing_progress
        );

        $this->assertNull(
            $document->processing_error
        );

        $this->assertNotNull(
            $document->processing_started_at
        );

        $this->assertNotNull(
            $document->processing_finished_at
        );

        $this->assertSame(
            'active',
            $file->status
        );

        $this->assertGreaterThan(
            0,
            $document
                ->chunks()
                ->count()
        );

        /*
         * Status READY belum boleh masuk
         * user retrieval.
         *
         * Admin tetap harus Publish manual.
         */

        $this->assertFalse(
            Document::eligibleForResearch()
                ->whereKey(
                    $document->id
                )
                ->exists()
        );
    }

    /*
    |--------------------------------------------------------------------------
    | 03
    | Expected parser failure
    |--------------------------------------------------------------------------
    */

    public function test_expected_pdf_extraction_failure_becomes_failed_state_without_throwing_from_job(): void
    {
        $document =
            $this->document();

        $service =
            $this->failingService();

        $file =
            $service->stageUpload(
                $document,
                $this->fakePdf(
                    'broken.pdf'
                )
            );

        $job =
            new ProcessSourceIngestion(
                $document->id,
                $file->id,
                'ready',
                null
            );

        /*
         * SourceIngestionException adalah expected
         * domain failure.
         *
         * Job harus menangkapnya.
         */

        $job->handle(
            $service
        );

        $document->refresh();
        $file->refresh();

        $this->assertSame(
            'failed',
            $document->status
        );

        $this->assertSame(
            'failed',
            $document->processing_stage
        );

        $this->assertSame(
            100,
            $document->processing_progress
        );

        $this->assertNotNull(
            $document->processing_finished_at
        );

        $this->assertStringContainsString(
            'PDF test gagal dibaca',
            (string)
                $document->processing_error
        );

        $this->assertSame(
            'parse_failed',
            $file->status
        );

        $this->assertSame(
            0,
            $document
                ->chunks()
                ->count()
        );
    }

    /*
    |--------------------------------------------------------------------------
    | 04
    | Stale worker protection
    |--------------------------------------------------------------------------
    */

    public function test_old_queued_file_cannot_overwrite_newer_upload(): void
    {
        $document =
            $this->document();

        $service =
            $this->successfulService();

        /*
         * Upload pertama.
         */

        $oldFile =
            $service->stageUpload(
                $document,
                $this->fakePdf(
                    'old.pdf'
                )
            );

        /*
         * Sebelum worker pertama selesai,
         * admin upload file baru.
         */

        $newFile =
            $service->stageUpload(
                $document,
                $this->fakePdf(
                    'new.pdf'
                )
            );

        $oldFile->refresh();
        $newFile->refresh();
        $document->refresh();

        $this->assertSame(
            'superseded',
            $oldFile->status
        );

        $this->assertSame(
            'queued',
            $newFile->status
        );

        $this->assertSame(
            $newFile->id,
            $document->processing_file_id
        );

        /*
         * Simulasikan worker lama baru berjalan.
         */

        $result =
            $service->processStoredFile(
                $document,
                $oldFile,
                'ready'
            );

        $this->assertTrue(
            (bool) (
                $result['stale']
                ?? false
            )
        );

        /*
         * Worker lama tidak boleh membuat chunks.
         */

        $this->assertSame(
            0,
            $document
                ->chunks()
                ->count()
        );

        $document->refresh();
        $newFile->refresh();

        /*
         * Processing pointer tetap menunjuk
         * PDF terbaru.
         */

        $this->assertSame(
            $newFile->id,
            $document->processing_file_id
        );

        $this->assertSame(
            'queued',
            $newFile->status
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Successful Service
    |--------------------------------------------------------------------------
    */

    private function successfulService(): SourceIngestionService
    {
        /*
         * IMPORTANT:
         *
         * PdfTextExtractor::lastMethod()
         * return type-nya STRING.
         *
         * Jadi anonymous subclass juga wajib:
         *
         *     lastMethod(): string
         *
         * BUKAN:
         *
         *     lastMethod(): ?string
         */

        $extractor =
            new class extends PdfTextExtractor
            {
                public function extract(
                    string $absolutePdfPath
                ): string {
                    return implode(
                        ' ',
                        array_fill(
                            0,
                            40,
                            'ESG disclosure firm value sustainability evidence research relationship governance'
                        )
                    );
                }

                public function lastMethod(): string
                {
                    return 'test-parser';
                }
            };

        return new SourceIngestionService(
            $extractor,
            new DocumentChunker()
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Failing Service
    |--------------------------------------------------------------------------
    */

    private function failingService(): SourceIngestionService
    {
        $extractor =
            new class extends PdfTextExtractor
            {
                public function extract(
                    string $absolutePdfPath
                ): string {
                    throw new RuntimeException(
                        'PDF test gagal dibaca.'
                    );
                }

                /*
                 * Parent return type adalah string.
                 *
                 * Karena extraction gagal sebelum
                 * method ini berguna, cukup return
                 * empty string.
                 */

                public function lastMethod(): string
                {
                    return '';
                }
            };

        return new SourceIngestionService(
            $extractor,
            new DocumentChunker()
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Fake PDF
    |--------------------------------------------------------------------------
    */

    private function fakePdf(
        string $name
    ): UploadedFile {
        /*
         * SourceIngestionService mengecek magic bytes:
         *
         * %PDF-
         *
         * Jadi fake file tetap harus punya
         * signature PDF.
         */

        return UploadedFile::fake()
            ->createWithContent(
                $name,
                "%PDF-1.4\n"
                ."% BacaDulu automated test PDF\n"
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Test Document
    |--------------------------------------------------------------------------
    */

    private function document(): Document
    {
        $user =
            User::factory()
                ->create();

        $category =
            Category::create([
                'name' =>
                    'Queue Test',

                'slug' =>
                    'queue-test-'
                    .Str::lower(
                        Str::random(
                            8
                        )
                    ),

                'status' =>
                    'active',
            ]);

        return Document::create([
            'uuid' =>
                (string)
                    Str::uuid(),

            'title' =>
                'Queued Source',

            'category_id' =>
                $category->id,

            'document_type' =>
                'pdf',

            'publication_year' =>
                2026,

            'language' =>
                'en',

            'publisher' =>
                'BacaDulu Queue Test',

            'source_label' =>
                'Curated Source',

            'status' =>
                'draft',

            'visibility' =>
                'public',

            'uploaded_by' =>
                $user->id,

            'published_at' =>
                null,

            'processing_stage' =>
                null,

            'processing_progress' =>
                0,

            'processing_file_id' =>
                null,

            'processing_error' =>
                null,

            'processing_started_at' =>
                null,

            'processing_finished_at' =>
                null,
        ]);
    }
}