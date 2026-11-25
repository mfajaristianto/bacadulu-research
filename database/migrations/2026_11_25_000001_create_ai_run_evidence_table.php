<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * DRAFT, MENUNGGU PERSETUJUAN (belum dijalankan di database asli). Hanya tabel baru; tabel yang ada tidak diubah.
 *
 * Snapshot evidence per AI Run. ai_run_retrievals ber-FK ke document_chunks sehingga tidak bisa memuat chunk full-text
 * eksternal maupun abstrak. Tabel ini sengaja TANPA FK ke dokumen/publikasi: ia menyimpan SALINAN teks, checksum, dan
 * identitas sumber saat jawaban dibuat, sehingga run lama tetap menunjukkan bukti yang benar-benar diberikan ke model
 * walau publikasi/chunk diubah atau dihapus kemudian (audit akademik). Baris bersifat tidak berubah (lihat AiRunEvidence).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_run_evidence')) {
            return;
        }

        Schema::create('ai_run_evidence', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ai_run_id')->constrained('ai_runs')->cascadeOnDelete();
            $table->string('entry_type', 12);                    // source | evidence
            $table->string('evidence_id', 12)->nullable();       // EV01… (null untuk entri sumber)
            $table->string('citation_id', 90);                   // EXT:929:C12:P8-9 | INT:DOC-3 | EXT:923:ABS
            $table->string('source_id', 40);                     // EXT:929 | INT:DOC-3
            $table->string('source_kind', 24);                   // internal_document | external_abstract | external_fulltext
            $table->unsignedBigInteger('publication_id')->nullable(); // id dokumen/publikasi saat itu; BUKAN foreign key
            $table->string('evidence_level', 16);                // METADATA_ONLY | ABSTRACT | FULL_TEXT
            $table->string('claim_support_level', 24);           // BIBLIOGRAPHIC_ONLY | ABSTRACT_SUPPORTED | FULLTEXT_SUPPORTED
            $table->text('title');
            $table->json('authors_json')->nullable();
            $table->unsignedSmallInteger('publication_year')->nullable();
            $table->string('doi', 255)->nullable();
            $table->string('provider', 60)->nullable();
            $table->text('source_url')->nullable();
            $table->string('section', 80)->nullable();
            $table->unsignedInteger('page_start')->nullable();
            $table->unsignedInteger('page_end')->nullable();
            $table->longText('evidence_text')->nullable();       // null untuk METADATA_ONLY: tidak pernah ada teks bukti
            $table->char('text_sha256', 64)->nullable();         // hash salinan teks ini (deteksi perubahan snapshot)
            $table->string('source_checksum', 80)->nullable();
            $table->string('chunk_checksum', 80)->nullable();
            $table->string('license', 120)->nullable();
            $table->json('allowed_claims_json')->nullable();
            $table->json('relevance_json')->nullable();
            $table->json('provenance_json')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['ai_run_id', 'citation_id'], 'ai_run_evidence_run_citation_unique');
            $table->index('citation_id', 'ai_run_evidence_citation_index');
            $table->index(['source_kind', 'publication_id'], 'ai_run_evidence_source_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_run_evidence');
    }
};
