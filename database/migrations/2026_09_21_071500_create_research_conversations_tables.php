<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('research_conversations')) {
            Schema::create('research_conversations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
                $table->string('title', 220)->default('New research');
                $table->string('status', 30)->default('active')->index();
                $table->timestamps();
                $table->index(['user_id', 'updated_at']);
            });
        }

        if (! Schema::hasTable('research_messages')) {
            Schema::create('research_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('conversation_id')->constrained('research_conversations')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('role', 30)->index();
                $table->longText('content');
                $table->foreignId('ai_run_id')->nullable()->constrained('ai_runs')->nullOnDelete();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['conversation_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('research_messages');
        Schema::dropIfExists('research_conversations');
    }
};
