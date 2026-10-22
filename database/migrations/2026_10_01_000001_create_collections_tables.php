<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('collections')) {
            Schema::create('collections', function (Blueprint $table) {
                $table->id();
                $table->string('name', 180);
                $table->string('slug', 190)->unique();
                $table->text('description')->nullable();
                $table->string('status', 30)->default('active')->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('collection_document')) {
            Schema::create('collection_document', function (Blueprint $table) {
                $table->id();
                $table->foreignId('collection_id')->constrained('collections')->cascadeOnDelete();
                $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['collection_id', 'document_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_document');
        Schema::dropIfExists('collections');
    }
};
