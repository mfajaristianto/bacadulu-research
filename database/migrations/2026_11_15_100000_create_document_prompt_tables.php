<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Modul "Prompt Dokumen": struktur template penulisan dan bagian-bagiannya. Hanya struktur dan konfigurasi.
 *
 * Isi prompt dan riwayat versinya TIDAK diduplikasi: tetap di prompt_templates (satu baris = satu versi). Sebuah bagian
 * menunjuk versi efektifnya lewat active_prompt_template_id, sama seperti ai_tools. Identitas instruksi sebuah bagian
 * adalah prompt_templates.name = "document-section:{uuid bagian}" dengan ai_tool_id NULL, sehingga aktivasi satu bagian
 * tidak menyentuh bagian lain maupun alat riset. Migration ini aditif: tidak mengubah atau menulis data apa pun yang sudah ada,
 * dan tidak menyisipkan template (template Fajar disimpan lewat aksi admin yang eksplisit, bukan lewat migration).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('document_prompt_templates')) {
            Schema::create('document_prompt_templates', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('name', 180);
                $table->string('slug', 190)->unique();            // nama dinormalisasi: mencegah duplikat tanpa peduli huruf besar/kecil
                $table->string('document_type', 40)->index();     // research | article | book_chapter | book | other
                $table->string('type_label', 80)->nullable();     // nama jenis bebas bila document_type = other
                $table->text('description')->nullable();
                $table->string('status', 20)->default('draft')->index(); // draft | active | archived; "siap" dihitung, bukan disimpan
                $table->string('language', 8)->default('id');
                $table->string('citation_style', 40)->default('APA 7');
                $table->string('qa_mode', 20)->default('none');   // none | inline | appendix | separate
                $table->string('source_preset', 60)->nullable();  // asal template bila diimpor dari preset
                $table->json('example_json')->nullable();         // contoh judul/rumusan: metadata, bukan isi wajib
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('activated_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('document_prompt_sections')) {
            Schema::create('document_prompt_sections', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                // Template tidak dihapus (hanya diarsipkan); restrict mencegah bagian dan versinya yatim.
                $table->foreignId('document_prompt_template_id')->constrained('document_prompt_templates')->restrictOnDelete();
                $table->string('key', 60);
                $table->string('title', 180);
                $table->text('description')->nullable();
                $table->text('purpose')->nullable();
                $table->unsignedSmallInteger('position')->default(1);
                $table->boolean('is_required')->default(true);
                $table->boolean('is_enabled')->default(true);
                $table->string('role', 20)->default('body');      // body | references | qa
                $table->unsignedInteger('min_words')->nullable();
                $table->unsignedInteger('max_words')->nullable();
                $table->unsignedInteger('target_words')->nullable();
                $table->unsignedSmallInteger('item_count')->nullable();   // mis. jumlah pertanyaan Q&A
                $table->unsignedSmallInteger('min_sources')->nullable();
                $table->text('requirements')->nullable();         // persyaratan khusus, satu per baris
                $table->json('depends_on')->nullable();           // daftar key bagian prasyarat (harus berada lebih awal)
                // Versi efektif. Menunjuk prompt_templates; null bila belum ada versi aktif. Versi lama tidak pernah dihapus.
                $table->foreignId('active_prompt_template_id')->nullable()->constrained('prompt_templates')->nullOnDelete();
                $table->timestamps();

                $table->unique(['document_prompt_template_id', 'key'], 'dp_sections_template_key_unique');
                $table->index(['document_prompt_template_id', 'position'], 'dp_sections_template_position_index');
            });
        }
    }

    /**
     * Rollback hanya membuang struktur baru. Baris prompt_templates bernama "document-section:*" sengaja TIDAK dihapus
     * (riwayat versi tidak boleh hilang); tanpa tabel ini baris itu tidak dipakai apa pun.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_prompt_sections');
        Schema::dropIfExists('document_prompt_templates');
    }
};
