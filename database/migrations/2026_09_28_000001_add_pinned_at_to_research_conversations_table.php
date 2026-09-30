<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('research_conversations')
            && ! Schema::hasColumn('research_conversations', 'pinned_at')
        ) {
            Schema::table('research_conversations', function (Blueprint $table): void {
                $table->timestamp('pinned_at')
                    ->nullable()
                    ->index();
            });
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('research_conversations')
            && Schema::hasColumn('research_conversations', 'pinned_at')
        ) {
            Schema::table('research_conversations', function (Blueprint $table): void {
                $table->dropIndex(['pinned_at']);
                $table->dropColumn('pinned_at');
            });
        }
    }
};
