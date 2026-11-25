<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * DRAFT, MENUNGGU PERSETUJUAN (belum dijalankan di database asli). Hanya tabel baru; tabel yang ada tidak diubah.
 * Full-text artikel eksternal dikelola terpisah dari Pustaka internal (`documents`/`document_chunks`).
 *
 *  external_fulltexts       : satu baris = satu LOKASI full-text sebuah publikasi (beberapa lokasi/versi boleh ada),
 *                             beserta keputusan izin, status pipeline, dan berkas lokal bila sudah diunduh.
 *  external_fulltext_chunks : potongan teks hasil ekstraksi, lengkap dengan provenance (berkas, halaman, bagian, checksum).
 *
 * Nama indeks diberi eksplisit dan pendek (batas MariaDB 64 karakter; SQLite tidak menangkap pelanggaran ini).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_fulltexts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('publication_id')->constrained('external_publications')->cascadeOnDelete();
            $table->string('provider', 40);
            $table->string('url', 1000);
            $table->char('url_hash', 40);
            $table->string('landing_url', 1000)->nullable();
            $table->string('kind', 12)->default('landing');            // pdf | xml | landing | tdm
            $table->string('license', 120)->nullable();                // nilai mentah dari penyedia
            $table->string('license_code', 40)->nullable();            // hasil normalisasi (cc0, cc-by, ...)
            $table->string('license_url', 500)->nullable();
            $table->string('license_source', 60)->nullable();          // asal bukti (mis. openalex.locations)
            $table->string('license_scope', 12)->default('article');   // article | journal
            $table->string('version', 30)->nullable();                 // publishedVersion | acceptedVersion | submittedVersion
            $table->string('attribution', 500)->nullable();
            $table->string('permission', 10)->default('review');       // allow | review | deny
            $table->string('permission_reason', 300)->nullable();
            $table->string('state', 30)->default('FULL_TEXT_LINK_FOUND');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 500)->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->string('queue_token', 36)->nullable();
            $table->string('error_code', 40)->nullable();
            $table->string('error_message', 500)->nullable();
            $table->string('disk', 40)->nullable();
            $table->string('path', 255)->nullable();
            $table->char('sha256', 64)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('mime', 80)->nullable();
            $table->unsignedSmallInteger('pages_total')->nullable();
            $table->unsignedSmallInteger('pages_with_text')->nullable();
            $table->unsignedInteger('text_chars')->nullable();
            $table->string('extraction_method', 40)->nullable();
            $table->unsignedInteger('chunk_count')->default(0);
            $table->unsignedBigInteger('duplicate_of_id')->nullable();
            $table->json('evidence_json')->nullable();                 // bukti mentah izin (tanpa rahasia)
            $table->timestamp('checked_at')->nullable();
            $table->timestamp('downloaded_at')->nullable();
            $table->timestamp('extracted_at')->nullable();
            $table->timestamp('indexed_at')->nullable();
            $table->timestamps();

            $table->unique(['publication_id', 'url_hash'], 'ext_ft_pub_url_unique');
            $table->index('state', 'ext_ft_state_idx');
            $table->index('sha256', 'ext_ft_sha_idx');
            $table->index('permission', 'ext_ft_permission_idx');
        });

        Schema::create('external_fulltext_chunks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fulltext_id')->constrained('external_fulltexts')->cascadeOnDelete();
            $table->foreignId('publication_id')->constrained('external_publications')->cascadeOnDelete();
            $table->unsignedInteger('chunk_index');
            $table->string('section', 40)->nullable();                 // hanya bila heading dikenali; null = tidak diketahui
            $table->unsignedSmallInteger('page_start')->nullable();
            $table->unsignedSmallInteger('page_end')->nullable();
            $table->boolean('is_references')->default(false);
            $table->longText('content');
            $table->char('content_sha', 40);
            $table->char('source_checksum', 64);                       // SHA-256 berkas sumber
            $table->unsignedSmallInteger('word_count')->default(0);
            $table->json('provenance_json')->nullable();
            $table->timestamps();

            $table->unique(['fulltext_id', 'chunk_index'], 'ext_ftc_file_index_unique');
            $table->index(['publication_id', 'is_references'], 'ext_ftc_pub_ref_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_fulltext_chunks');
        Schema::dropIfExists('external_fulltexts');
    }
};
