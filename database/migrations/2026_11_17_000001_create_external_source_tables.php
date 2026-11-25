<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * DRAFT, MENUNGGU PERSETUJUAN. Hanya tabel baru (tidak menyentuh tabel yang ada). Metadata bibliografi eksternal
 * disimpan terpisah dari `documents` agar Pustaka internal (PDF terbit) tidak tercampur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_publications', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('doi', 255)->nullable()->unique();
            $table->json('isbn_json')->nullable();
            $table->json('issn_json')->nullable();
            $table->string('title', 1000);
            $table->json('authors_json')->nullable();
            $table->unsignedSmallInteger('publication_year')->nullable()->index();
            $table->date('published_date')->nullable();
            $table->string('publisher', 255)->nullable();
            $table->string('container_title', 500)->nullable();
            $table->text('abstract')->nullable();
            $table->json('keywords_json')->nullable();
            $table->string('language', 12)->nullable()->index();
            $table->string('publication_type', 30)->default('other')->index();
            $table->string('landing_url', 1000)->nullable();
            $table->string('fulltext_url', 1000)->nullable();
            $table->string('license', 120)->nullable();
            $table->string('oa_status', 20)->default('unknown')->index();
            $table->string('availability', 30)->default('METADATA_ONLY')->index();
            $table->string('title_hash', 64)->index();
            $table->string('checksum', 64);
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('external_publication_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('publication_id')->constrained('external_publications')->cascadeOnDelete();
            $table->string('provider', 40);
            $table->string('external_id', 255);
            $table->string('source_url', 1000)->nullable();
            $table->string('checksum', 64)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'external_id']);
        });

        Schema::create('external_publication_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('publication_id')->constrained('external_publications')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->string('status', 20)->default('suggested')->index();
            $table->string('reason', 500)->nullable();
            $table->string('provider', 40)->nullable();
            $table->string('query_term', 160)->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            // Nama eksplisit: nama otomatis Laravel 65 karakter, melebihi batas pengenal 64 karakter MySQL/MariaDB.
            $table->unique(['publication_id', 'category_id'], 'ext_pubcat_publication_category_unique');
        });

        Schema::create('external_category_mappings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->string('provider', 40);
            $table->json('terms_json');
            $table->json('filters_json')->nullable();
            $table->unsignedInteger('max_records')->default(100);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->unique(['category_id', 'provider']);
        });

        Schema::create('external_sync_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('provider', 40)->index();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('status', 20)->default('queued')->index();
            $table->json('scope_json');
            $table->json('checkpoint_json')->nullable();
            $table->unsignedInteger('found_count')->default(0);
            $table->unsignedInteger('processed_count')->default(0);
            $table->unsignedInteger('new_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('duplicate_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->json('errors_json')->nullable();
            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['external_sync_runs', 'external_category_mappings', 'external_publication_categories', 'external_publication_sources', 'external_publications'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
