<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Conversation Soft Delete
        |--------------------------------------------------------------------------
        |
        | User boleh "menghapus" conversation dari UI,
        | tetapi provenance research tidak dihancurkan.
        |
        */

        if (
            Schema::hasTable('research_conversations')
            && ! Schema::hasColumn(
                'research_conversations',
                'deleted_at'
            )
        ) {
            Schema::table(
                'research_conversations',
                function (Blueprint $table): void {
                    $table->softDeletes();
                }
            );
        }

        /*
        |--------------------------------------------------------------------------
        | AI Run → Conversation
        |--------------------------------------------------------------------------
        */

        if (
            Schema::hasTable('ai_runs')
            && Schema::hasTable('research_conversations')
            && ! Schema::hasColumn(
                'ai_runs',
                'conversation_id'
            )
        ) {
            Schema::table(
                'ai_runs',
                function (Blueprint $table): void {
                    $table
                        ->foreignId('conversation_id')
                        ->nullable()
                        ->constrained(
                            'research_conversations'
                        )
                        ->nullOnDelete();
                }
            );

            /*
             * Backfill run lama dari research_messages.
             */
            DB::table('research_messages')
                ->whereNotNull('ai_run_id')
                ->orderBy('id')
                ->chunkById(
                    250,
                    function ($messages): void {
                        foreach ($messages as $message) {
                            DB::table('ai_runs')
                                ->where(
                                    'id',
                                    $message->ai_run_id
                                )
                                ->whereNull(
                                    'conversation_id'
                                )
                                ->update([
                                    'conversation_id' => $message
                                        ->conversation_id,
                                ]);
                        }
                    }
                );
        }

        /*
        |--------------------------------------------------------------------------
        | Saved Result Unique Guard
        |--------------------------------------------------------------------------
        |
        | Satu AI Run hanya boleh disimpan satu kali
        | per user.
        |
        */

        if (
            Schema::hasTable('saved_results')
            && Schema::hasColumn(
                'saved_results',
                'user_id'
            )
            && Schema::hasColumn(
                'saved_results',
                'ai_run_id'
            )
        ) {
            $duplicates =
                DB::table('saved_results')
                    ->select(
                        'user_id',
                        'ai_run_id',
                        DB::raw(
                            'MIN(id) as keep_id'
                        )
                    )
                    ->groupBy(
                        'user_id',
                        'ai_run_id'
                    )
                    ->havingRaw(
                        'COUNT(*) > 1'
                    )
                    ->get();

            foreach ($duplicates as $duplicate) {
                DB::table('saved_results')
                    ->where(
                        'user_id',
                        $duplicate->user_id
                    )
                    ->where(
                        'ai_run_id',
                        $duplicate->ai_run_id
                    )
                    ->where(
                        'id',
                        '<>',
                        $duplicate->keep_id
                    )
                    ->delete();
            }

            Schema::table(
                'saved_results',
                function (Blueprint $table): void {
                    $table->unique(
                        [
                            'user_id',
                            'ai_run_id',
                        ],
                        'saved_results_user_run_unique'
                    );
                }
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Document Templates
        |--------------------------------------------------------------------------
        |
        | Disiapkan sebagai layer template.
        | Document Engine sekarang masih dapat bekerja
        | menggunakan config default tanpa template DB.
        |
        */

        if (
            ! Schema::hasTable(
                'document_templates'
            )
        ) {
            Schema::create(
                'document_templates',
                function (Blueprint $table): void {
                    $table->id();

                    $table
                        ->string(
                            'code',
                            120
                        )
                        ->unique();

                    $table->string(
                        'name',
                        180
                    );

                    $table
                        ->string(
                            'document_type',
                            120
                        )
                        ->index();

                    $table
                        ->text('description')
                        ->nullable();

                    $table
                        ->json(
                            'schema_json'
                        )
                        ->nullable();

                    $table
                        ->string(
                            'status',
                            30
                        )
                        ->default(
                            'active'
                        )
                        ->index();

                    $table
                        ->foreignId(
                            'created_by'
                        )
                        ->nullable()
                        ->constrained(
                            'users'
                        )
                        ->nullOnDelete();

                    $table->timestamps();
                }
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Document Generations
        |--------------------------------------------------------------------------
        */

        if (
            ! Schema::hasTable(
                'document_generations'
            )
        ) {
            Schema::create(
                'document_generations',
                function (Blueprint $table): void {
                    $table->id();

                    $table
                        ->uuid('uuid')
                        ->unique();

                    $table
                        ->foreignId(
                            'user_id'
                        )
                        ->constrained(
                            'users'
                        )
                        ->cascadeOnDelete();

                    $table
                        ->foreignId(
                            'conversation_id'
                        )
                        ->nullable()
                        ->constrained(
                            'research_conversations'
                        )
                        ->nullOnDelete();

                    $table
                        ->foreignId(
                            'ai_run_id'
                        )
                        ->nullable()
                        ->constrained(
                            'ai_runs'
                        )
                        ->nullOnDelete();

                    $table
                        ->foreignId(
                            'saved_result_id'
                        )
                        ->nullable()
                        ->constrained(
                            'saved_results'
                        )
                        ->nullOnDelete();

                    $table
                        ->foreignId(
                            'document_template_id'
                        )
                        ->nullable()
                        ->constrained(
                            'document_templates'
                        )
                        ->nullOnDelete();

                    $table
                        ->string(
                            'document_type',
                            120
                        )
                        ->index();

                    $table
                        ->string(
                            'citation_style',
                            40
                        )
                        ->default(
                            'APA 7'
                        );

                    $table->string(
                        'title'
                    );

                    $table
                        ->json(
                            'content_json'
                        );

                    $table
                        ->string(
                            'status',
                            30
                        )
                        ->default(
                            'draft'
                        )
                        ->index();

                    $table
                        ->string(
                            'docx_disk',
                            80
                        )
                        ->nullable();

                    $table
                        ->string(
                            'docx_path',
                            500
                        )
                        ->nullable();

                    $table
                        ->string(
                            'pdf_disk',
                            80
                        )
                        ->nullable();

                    $table
                        ->string(
                            'pdf_path',
                            500
                        )
                        ->nullable();

                    $table
                        ->timestamp(
                            'generated_at'
                        )
                        ->nullable()
                        ->index();

                    $table
                        ->text(
                            'error_message'
                        )
                        ->nullable();

                    $table->timestamps();

                    $table->index(
                        [
                            'user_id',
                            'status',
                        ],
                        'document_generations_user_status_index'
                    );

                    $table->index(
                        [
                            'ai_run_id',
                            'status',
                        ],
                        'document_generations_run_status_index'
                    );
                }
            );
        }
    }

    public function down(): void
    {
        /*
         * Document output lebih dulu karena
         * memiliki FK ke tabel lain.
         */
        Schema::dropIfExists(
            'document_generations'
        );

        Schema::dropIfExists(
            'document_templates'
        );

        if (
            Schema::hasTable(
                'saved_results'
            )
        ) {
            Schema::table(
                'saved_results',
                function (Blueprint $table): void {
                    $table->dropUnique(
                        'saved_results_user_run_unique'
                    );
                }
            );
        }

        if (
            Schema::hasTable('ai_runs')
            && Schema::hasColumn(
                'ai_runs',
                'conversation_id'
            )
        ) {
            Schema::table(
                'ai_runs',
                function (Blueprint $table): void {
                    $table->dropForeign([
                        'conversation_id',
                    ]);

                    $table->dropColumn(
                        'conversation_id'
                    );
                }
            );
        }

        if (
            Schema::hasTable(
                'research_conversations'
            )
            && Schema::hasColumn(
                'research_conversations',
                'deleted_at'
            )
        ) {
            Schema::table(
                'research_conversations',
                function (Blueprint $table): void {
                    $table->dropSoftDeletes();
                }
            );
        }
    }
};
