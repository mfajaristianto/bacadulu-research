<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Document;
use App\Models\SourceSyncItem;
use App\Models\User;
use App\Services\Research\Sync\SourceSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SourceSyncAdapterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set(
            'source-sync.provider',
            'mock'
        );

        config()->set(
            'source-sync.auto_publish',
            false
        );

        config()->set(
            'research.ingestion.chunk_words',
            120
        );

        config()->set(
            'research.ingestion.chunk_overlap_words',
            20
        );

        config()->set(
            'research.ingestion.minimum_chunk_words',
            20
        );
    }

    public function test_scan_stages_remote_items_without_changing_library(): void
    {
        $admin =
            User::factory()
                ->create();

        $run =
            app(
                SourceSyncService::class
            )
                ->scan(
                    $admin->id
                );

        $this->assertSame(
            'review_ready',
            $run->status
        );

        $this->assertSame(
            2,
            $run->discovered_count
        );

        $this->assertSame(
            2,
            $run->new_count
        );

        $this->assertSame(
            0,
            $run->changed_count
        );

        $this->assertSame(
            0,
            $run->unchanged_count
        );

        $this->assertSame(
            2,
            SourceSyncItem::query()
                ->count()
        );

        /*
         * Scan tidak boleh langsung mengubah Library.
         */

        $this->assertSame(
            0,
            Document::query()
                ->count()
        );
    }

    public function test_admin_import_ingests_chunks_but_never_auto_publishes(): void
    {
        $admin =
            User::factory()
                ->create();

        $category =
            $this
                ->activeCategory();

        $run =
            app(
                SourceSyncService::class
            )
                ->scan(
                    $admin->id
                );

        $item =
            $run
                ->items()
                ->where(
                    'external_id',
                    'MOCK-ESG-001'
                )
                ->firstOrFail();

        $document =
            app(
                SourceSyncService::class
            )
                ->import(
                    $item,
                    $category->id,
                    $admin->id
                );

        $this->assertSame(
            'ready',
            $document->status
        );

        $this->assertNull(
            $document->published_at
        );

        $this->assertSame(
            'mock',
            $document
                ->source_connector
        );

        $this->assertSame(
            'MOCK-ESG-001',
            $document
                ->external_source_id
        );

        $this->assertNotEmpty(
            $document
                ->external_checksum
        );

        $this->assertNotNull(
            $document
                ->last_synced_at
        );

        $this->assertGreaterThan(
            0,
            $document
                ->chunks()
                ->count()
        );

        /*
         * READY belum eligible untuk user retrieval.
         */

        $this->assertFalse(
            Document::eligibleForResearch()
                ->whereKey(
                    $document->id
                )
                ->exists()
        );

        $item->refresh();

        $this->assertSame(
            'imported',
            $item->status
        );

        $this->assertSame(
            $document->id,
            $item
                ->imported_document_id
        );
    }

    public function test_next_scan_marks_previously_imported_source_as_unchanged(): void
    {
        $admin =
            User::factory()
                ->create();

        $category =
            $this
                ->activeCategory();

        $sync =
            app(
                SourceSyncService::class
            );

        $firstRun =
            $sync->scan(
                $admin->id
            );

        $firstItem =
            $firstRun
                ->items()
                ->where(
                    'external_id',
                    'MOCK-ESG-001'
                )
                ->firstOrFail();

        $sync->import(
            $firstItem,
            $category->id,
            $admin->id
        );

        $secondRun =
            $sync->scan(
                $admin->id
            );

        $secondItem =
            $secondRun
                ->items()
                ->where(
                    'external_id',
                    'MOCK-ESG-001'
                )
                ->firstOrFail();

        $this->assertSame(
            'unchanged',
            $secondItem->status
        );

        $this->assertNotNull(
            $secondItem
                ->matched_document_id
        );

        $this->assertSame(
            $secondItem
                ->previous_checksum,

            $secondItem
                ->external_checksum
        );
    }

    public function test_checksum_difference_is_staged_as_changed_and_requires_review(): void
    {
        $admin =
            User::factory()
                ->create();

        $category =
            $this
                ->activeCategory();

        $sync =
            app(
                SourceSyncService::class
            );

        $firstRun =
            $sync->scan(
                $admin->id
            );

        $firstItem =
            $firstRun
                ->items()
                ->where(
                    'external_id',
                    'MOCK-ESG-001'
                )
                ->firstOrFail();

        $document =
            $sync->import(
                $firstItem,
                $category->id,
                $admin->id
            );

        /*
         * Simulasi checksum Library berbeda dari remote.
         */

        $document->update([
            'external_checksum' =>
                str_repeat(
                    '0',
                    64
                ),
        ]);

        $secondRun =
            $sync->scan(
                $admin->id
            );

        $changed =
            $secondRun
                ->items()
                ->where(
                    'external_id',
                    'MOCK-ESG-001'
                )
                ->firstOrFail();

        $this->assertSame(
            'changed',
            $changed->status
        );

        $this->assertSame(
            $document->id,
            $changed
                ->matched_document_id
        );

        $this->assertNotSame(
            $changed
                ->previous_checksum,

            $changed
                ->external_checksum
        );

        $this->assertSame(
            'review_ready',
            $secondRun
                ->fresh()
                ->status
        );
    }

    private function activeCategory(): Category
    {
        return Category::create([
            'name' =>
                'Source Sync Test',

            'slug' =>
                'source-sync-test-'
                .Str::lower(
                    Str::random(
                        8
                    )
                ),

            'status' =>
                'active',
        ]);
    }
}