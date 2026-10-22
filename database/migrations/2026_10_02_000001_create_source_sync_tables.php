<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('source_sync_runs')) {
            Schema::create('source_sync_runs', function (Blueprint $table): void {
                $table->id();

                $table
                    ->uuid('uuid')
                    ->unique();

                $table
                    ->string('connector', 80)
                    ->index();

                $table
                    ->string('status', 30)
                    ->default('running')
                    ->index();

                $table
                    ->unsignedInteger('discovered_count')
                    ->default(0);

                $table
                    ->unsignedInteger('new_count')
                    ->default(0);

                $table
                    ->unsignedInteger('changed_count')
                    ->default(0);

                $table
                    ->unsignedInteger('unchanged_count')
                    ->default(0);

                $table
                    ->unsignedInteger('imported_count')
                    ->default(0);

                $table
                    ->unsignedInteger('ignored_count')
                    ->default(0);

                $table
                    ->text('error_message')
                    ->nullable();

                $table
                    ->json('metadata_json')
                    ->nullable();

                $table
                    ->foreignId('initiated_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table
                    ->timestamp('started_at')
                    ->nullable()
                    ->index();

                $table
                    ->timestamp('finished_at')
                    ->nullable();

                $table->timestamps();
            });
        }

        if (! Schema::hasTable('source_sync_items')) {
            Schema::create('source_sync_items', function (Blueprint $table): void {
                $table->id();

                $table
                    ->foreignId('source_sync_run_id')
                    ->constrained('source_sync_runs')
                    ->cascadeOnDelete();

                $table
                    ->string('connector', 80)
                    ->index();

                $table
                    ->string('external_id', 190);

                $table->string('title');

                $table
                    ->string('external_checksum', 128)
                    ->nullable()
                    ->index();

                $table
                    ->string('previous_checksum', 128)
                    ->nullable();

                $table
                    ->timestamp('remote_updated_at')
                    ->nullable();

                $table
                    ->string('status', 30)
                    ->default('new')
                    ->index();

                $table
                    ->foreignId('category_id')
                    ->nullable()
                    ->constrained('categories')
                    ->nullOnDelete();

                $table
                    ->foreignId('matched_document_id')
                    ->nullable()
                    ->constrained('documents')
                    ->nullOnDelete();

                $table
                    ->foreignId('imported_document_id')
                    ->nullable()
                    ->constrained('documents')
                    ->nullOnDelete();

                $table
                    ->json('payload_json')
                    ->nullable();

                $table
                    ->text('error_message')
                    ->nullable();

                $table
                    ->foreignId('reviewed_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table
                    ->timestamp('reviewed_at')
                    ->nullable();

                $table
                    ->timestamp('imported_at')
                    ->nullable();

                $table->timestamps();

                $table->unique(
                    [
                        'source_sync_run_id',
                        'connector',
                        'external_id',
                    ],
                    'source_sync_items_run_connector_external_unique'
                );

                $table->index(
                    [
                        'connector',
                        'external_id',
                    ],
                    'source_sync_items_connector_external_index'
                );
            });
        }

        if (Schema::hasTable('documents')) {
            Schema::table('documents', function (Blueprint $table): void {
                if (! Schema::hasColumn('documents', 'source_connector')) {
                    $table
                        ->string('source_connector', 80)
                        ->nullable()
                        ->after('source_label')
                        ->index();
                }

                if (! Schema::hasColumn('documents', 'external_source_id')) {
                    $table
                        ->string('external_source_id', 190)
                        ->nullable()
                        ->after('source_connector')
                        ->index();
                }

                if (! Schema::hasColumn('documents', 'external_checksum')) {
                    $table
                        ->string('external_checksum', 128)
                        ->nullable()
                        ->after('external_source_id');
                }

                if (! Schema::hasColumn('documents', 'last_synced_at')) {
                    $table
                        ->timestamp('last_synced_at')
                        ->nullable()
                        ->after('external_checksum')
                        ->index();
                }
            });

            Schema::table('documents', function (Blueprint $table): void {
                $table->unique(
                    [
                        'source_connector',
                        'external_source_id',
                    ],
                    'documents_source_connector_external_unique'
                );
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('documents')) {
            Schema::table('documents', function (Blueprint $table): void {
                $table->dropUnique(
                    'documents_source_connector_external_unique'
                );

                $table->dropColumn([
                    'source_connector',
                    'external_source_id',
                    'external_checksum',
                    'last_synced_at',
                ]);
            });
        }

        Schema::dropIfExists('source_sync_items');
        Schema::dropIfExists('source_sync_runs');
    }
};