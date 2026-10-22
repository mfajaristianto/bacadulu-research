<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('documents')) {
            return;
        }

        Schema::table('documents', function (Blueprint $table): void {
            if (! Schema::hasColumn('documents', 'processing_stage')) {
                $table
                    ->string('processing_stage', 40)
                    ->nullable()
                    ->after('status')
                    ->index();
            }

            if (! Schema::hasColumn('documents', 'processing_progress')) {
                $table
                    ->unsignedTinyInteger('processing_progress')
                    ->default(0)
                    ->after('processing_stage');
            }

            if (! Schema::hasColumn('documents', 'processing_file_id')) {
                $table
                    ->unsignedBigInteger('processing_file_id')
                    ->nullable()
                    ->after('processing_progress')
                    ->index();
            }

            if (! Schema::hasColumn('documents', 'processing_error')) {
                $table
                    ->text('processing_error')
                    ->nullable()
                    ->after('processing_file_id');
            }

            if (! Schema::hasColumn('documents', 'processing_started_at')) {
                $table
                    ->timestamp('processing_started_at')
                    ->nullable()
                    ->after('processing_error');
            }

            if (! Schema::hasColumn('documents', 'processing_finished_at')) {
                $table
                    ->timestamp('processing_finished_at')
                    ->nullable()
                    ->after('processing_started_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('documents')) {
            return;
        }

        $columns = [
            'processing_stage',
            'processing_progress',
            'processing_file_id',
            'processing_error',
            'processing_started_at',
            'processing_finished_at',
        ];

        $existing = array_values(
            array_filter(
                $columns,
                fn (string $column): bool =>
                    Schema::hasColumn(
                        'documents',
                        $column
                    )
            )
        );

        if ($existing === []) {
            return;
        }

        Schema::table(
            'documents',
            function (Blueprint $table) use ($existing): void {
                $table->dropColumn(
                    $existing
                );
            }
        );
    }
};