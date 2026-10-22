<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_run_retrievals')) {
            Schema::create(
                'ai_run_retrievals',
                function (Blueprint $table): void {
                    $table->id();

                    $table->foreignId('ai_run_id')
                        ->constrained('ai_runs')
                        ->cascadeOnDelete();

                    $table->foreignId('document_id')
                        ->constrained('documents')
                        ->cascadeOnDelete();

                    $table->foreignId('document_chunk_id')
                        ->constrained('document_chunks')
                        ->cascadeOnDelete();

                    $table->unsignedInteger(
                        'rank_no'
                    );

                    $table->decimal(
                        'score',
                        12,
                        6
                    )->default(0);

                    $table->string(
                        'retrieval_type',
                        40
                    )
                        ->default('keyword')
                        ->index();

                    $table->string(
                        'citation_key',
                        40
                    );

                    $table->longText(
                        'excerpt'
                    );

                    $table->json(
                        'metadata_json'
                    )->nullable();

                    $table->timestamps();

                    $table->unique(
                        [
                            'ai_run_id',
                            'document_chunk_id',
                        ],
                        'ai_run_retrievals_run_chunk_unique'
                    );

                    $table->unique(
                        [
                            'ai_run_id',
                            'citation_key',
                        ],
                        'ai_run_retrievals_run_citation_unique'
                    );

                    $table->index(
                        [
                            'ai_run_id',
                            'rank_no',
                        ],
                        'ai_run_retrievals_run_rank_index'
                    );
                }
            );
        }

        if (! Schema::hasTable('ai_run_citations')) {
            Schema::create(
                'ai_run_citations',
                function (Blueprint $table): void {
                    $table->id();

                    $table->foreignId('ai_run_id')
                        ->constrained('ai_runs')
                        ->cascadeOnDelete();

                    $table->foreignId(
                        'ai_run_result_id'
                    )
                        ->nullable()
                        ->constrained(
                            'ai_run_results'
                        )
                        ->nullOnDelete();

                    /*
                     * Nullable supaya citation invalid juga
                     * tetap bisa dicatat sebagai audit trail.
                     */
                    $table->foreignId(
                        'document_id'
                    )
                        ->nullable()
                        ->constrained('documents')
                        ->nullOnDelete();

                    $table->foreignId(
                        'document_chunk_id'
                    )
                        ->nullable()
                        ->constrained(
                            'document_chunks'
                        )
                        ->nullOnDelete();

                    $table->string(
                        'citation_key',
                        40
                    );

                    $table->string(
                        'claim_label',
                        255
                    )->nullable();

                    $table->text(
                        'quote_snippet'
                    )->nullable();

                    $table->boolean(
                        'is_valid'
                    )
                        ->default(false)
                        ->index();

                    $table->text(
                        'validation_message'
                    )->nullable();

                    $table->json(
                        'metadata_json'
                    )->nullable();

                    $table->timestamps();

                    $table->index(
                        [
                            'ai_run_id',
                            'citation_key',
                        ],
                        'ai_run_citations_run_key_index'
                    );
                }
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'ai_run_citations'
        );

        Schema::dropIfExists(
            'ai_run_retrievals'
        );
    }
};
