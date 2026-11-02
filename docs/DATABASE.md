# Database — BacaDulu Research

Dokumen ini menjelaskan basis data **sebagaimana adanya**. Tidak ada perubahan skema pada pekerjaan dokumentasi ini.

## 1. Mesin dan konfigurasi

| Lingkungan | Mesin | Sumber |
|---|---|---|
| Pengembangan/produksi | **MySQL** (`DB_CONNECTION=mysql` di `.env.example`) | `.env.example` |
| Fallback kode | `sqlite` bila `DB_CONNECTION` kosong | `config/database.php` |
| Pengujian otomatis | SQLite **in-memory** (`:memory:`) | `phpunit.xml` |
| Sesi, cache, antrean | Driver `database` secara default (`SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION`) | `config/*.php`, `.env.example` |

Berkas `database/database.sqlite` ada di folder `database/` (artefak bawaan). Berkas ini bukan basis data aplikasi yang dipakai dengan MySQL dan tidak boleh diperlakukan sebagai sumber data produksi.

## 2. Eloquent ORM

Model di `app/Models` (23 model): `AiRun`, `AiRunCitation`, `AiRunResult`, `AiRunRetrieval`, `AiTool`, `AuditLog`, `Category`, `Collection`, `Conversation`, `Document`, `DocumentChunk`, `DocumentFile`, `DocumentGeneration`, `DocumentTemplate`, `GlossaryTerm`, `PromptTemplate`, `ResearchMessage`, `ResearchSetting`, `SavedResult`, `SourceSyncItem`, `SourceSyncRun`, `User`, `Workspace`.

Aturan pakai:
- Akses data lewat Eloquent/Query Builder dengan binding parameter. Hindari SQL mentah dengan input pengguna (lihat `SECURITY.md` 2.5).
- Selalu batasi query resource privat dengan `user_id` pengguna yang sedang login.
- Gunakan eager loading (`with`) pada daftar untuk menghindari N+1.

## 3. Tabel utama (dari migrasi)

| Kelompok | Tabel |
|---|---|
| Pengguna & sesi | `users`, `password_reset_tokens`, `sessions` |
| Pustaka sumber | `categories`, `documents`, `document_files`, `document_chunks`, `collections`, `collection_document` |
| Riset & AI | `ai_tools`, `prompt_templates`, `ai_runs`, `ai_run_sources`, `ai_run_results` (+ tabel retrieval/sitasi pada migrasi `…000004`) |
| Percakapan | `research_conversations`, `research_messages` |
| Hasil & dokumen | `saved_results`, tabel generasi dokumen dan `document_templates` (migrasi `…000005`, `…10_02_000003`) |
| Glosarium & pengaturan | glosarium, `research_settings` |
| Sinkronisasi sumber | `source_sync_runs`, `source_sync_items` |
| Workspace pribadi | `workspaces`, `workspace_documents` (model ada; pemakaian belum terverifikasi, lihat `PRD.md` 3.5) |
| Audit | `audit_logs` |
| Infrastruktur Laravel | `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs` |

Status `ai_runs` yang muncul di kode: `queued`, `running`, `evidence_ready`, `completed`, `insufficient_evidence`, `citation_failed`, `failed`. Status terminal (`completed`, `insufficient_evidence`, `citation_failed`) tidak diproses ulang oleh job.

## 4. Migration

- 22 berkas di `database/migrations`, termasuk `0000_00_00_000000_existing_research_schema.php` (migrasi kosong sebagai penanda skema warisan).
- Banyak migrasi bersifat **idempoten** (`Schema::hasTable(...) return`), dirancang agar aman di basis data yang sudah ada.
- Ada migrasi data/seed kanonis: `…_seed_canonical_research_tools.php`.
- Terdapat dua migrasi `date_of_birth` (`2026_09_21_000001` dan `2026_09_21_064450`); pastikan keduanya memeriksa keberadaan kolom sebelum menambah. **Belum diverifikasi**.
- Aturan: perubahan skema selalu lewat migrasi baru dan **setelah backup**. Jangan menjalankan `php artisan migrate:fresh` pada basis data berisi data.

## 5. Seeder

`DatabaseSeeder` membuat pengguna admin (bila `ADMIN_EMAIL` terisi), kategori, `AiTool`, dan `PromptTemplate` awal. Seeder **melempar error bila `ADMIN_EMAIL` ada tetapi `ADMIN_PASSWORD` kosong**, sehingga seeder mengharuskan kata sandi plaintext di `.env` meskipun login admin mendukung `ADMIN_PASSWORD_HASH`. Catatan perbaikan: izinkan seeder memakai hash. (Bukan dikerjakan sekarang.)

## 6. Relasi utama

```text
User 1─* Conversation 1─* ResearchMessage *─1 AiRun
User 1─* AiRun *─1 PromptTemplate *─1 AiTool
AiRun 1─* AiRunRetrieval / AiRunCitation / AiRunResult(1)
Document 1─* DocumentChunk ; Document *─* Collection ; Document *─1 Category
User 1─* SavedResult *─1 AiRun ; SavedResult 1─* DocumentGeneration
DocumentGeneration *─1 DocumentTemplate (snapshot)
SourceSyncRun 1─* SourceSyncItem *─1 Document (hasil impor)
User 1─* AuditLog ; User 1─* Workspace *─* Document
```

Diagram ini ringkasan dari method relasi di `app/Models/*`; nama kolom tepat harus dicek di migrasi.

## 7. Foreign key, index, dan unique constraint

- Foreign key: sekitar **103** pemakaian `foreignId/constrained/cascadeOnDelete/nullOnDelete` pada migrasi. Pola umum: data milik pengguna `cascadeOnDelete`, referensi opsional `nullOnDelete` (mis. `categories.parent_id`, `audit_logs.user_id`).
- Index pada kolom filter: `status`, `is_favorite`, `action` (audit), dan lainnya.
- Unique constraint yang ditemukan: `users.email`, `users.google_id`, `categories.slug`, `collections.slug`, `ai_tools.code`, uuid pada `documents`/`ai_runs`, `(document_id, chunk_index)`, `(ai_tool_id, name, version)` pada prompt, `(ai_run_id, document_id)`, `(workspace_id, document_id)`, `(collection_id, document_id)`, `saved_results_user_run_unique (user_id, ai_run_id)`, serta unique pada tabel source sync dan pengaturan.

## 8. Transaksi dan race condition

- `DB::transaction` dipakai di: `ResearchSubmissionController`, `ResearchController`, `ResearchEvidencePipeline`, `SourceIngestionService`, `SourceSyncService`, `PromptController`, `PromptVersionController`, `DocumentTemplateController`.
- `lockForUpdate()` dipakai di `SourceIngestionService` dan `SourceSyncService`.
- Pola "satu kali per pengguna" (Saved) memakai `firstOrCreate` yang ditopang unique index.
- Aturan baru: operasi baca-lalu-tulis pada data bersama harus dalam transaksi dengan lock atau didukung unique constraint; tangani `UniqueConstraintViolationException` bila perlu.

## 9. Data pribadi

Telepon dan alamat profil disimpan terenkripsi (`phone_encrypted`, `address_encrypted`). Jangan mencatat nilai ini di log. Kunci enkripsi adalah `APP_KEY`; kehilangannya membuat data tidak dapat dibaca, sehingga `APP_KEY` wajib ikut strategi backup rahasia (disimpan terpisah dari dump).

## 10. Backup dan restore

Belum ada skrip backup di repo (**Belum diterapkan**). Prosedur yang dianjurkan:

```bash
# backup (jalankan di server, hasil disimpan di luar web root)
mysqldump --single-transaction --routines --no-tablespaces -u <user> -p <database> > backup-YYYYMMDD.sql

# uji restore ke database sementara (bukan produksi)
mysql -u <user> -p <database_uji> < backup-YYYYMMDD.sql
```

- Cadangkan juga `storage/app/private` (PDF sumber dan keluaran dokumen) serta `public/uploads/profile-avatars`.
- Simpan salinan terenkripsi di lokasi terpisah; uji restore berkala dan catat hasilnya.
- Jangan menaruh dump atau `.env` di repositori atau paket ZIP.
