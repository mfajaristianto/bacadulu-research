# Arsitektur — BacaDulu Research

Aplikasi **Laravel 12 (PHP ≥ 8.2)** dengan antarmuka Blade, Vite 6, Tailwind CSS 4, JavaScript modul, dan GSAP. Dokumen ini dibuat dari struktur kode aktual (route, middleware, controller, service, model).

## 1. Gambaran umum

```text
Browser (Blade + CSS/JS dari Vite)
   │  HTTP (sesi + CSRF)
   ▼
routes/*.php ──► middleware ──► Controller ──► Service ──► Model/Eloquent ──► MySQL
                                   │               │
                                   │               └─► Disk privat (PDF sumber, DOCX/PDF keluaran)
                                   ▼
                              Job (antrean database)
                                   │
                                   ├─► ProcessResearchRun ──► ResearchRunService ──► AI Provider (mock | OpenAI)
                                   └─► ProcessSourceIngestion ──► SourceIngestionService (ekstraksi PDF, chunking)
```

## 2. Routes

Diregistrasi di `bootstrap/app.php`; `research-queue.php` **harus tetap terakhir**.

| Berkas | Isi |
|---|---|
| `routes/web.php` | Landing, auth, profil, workspace/dashboard, Saved, History, Library, percakapan |
| `routes/google-auth.php` | Rute Google OAuth (tambahan, bersyarat) |
| `routes/documents.php` | Pembuatan, tampilan, render, unduh dokumen |
| `routes/admin-monitoring.php` | Route admin `ai-runs` (daftar dan detail run) |
| `routes/admin-console.php` | Konsol admin (library, prompt, tool, glosarium, template, pengguna, audit, source sync, dll.) |
| `routes/research-queue.php` | `research.run`, status, presentasi (versi antrean) |
| `routes/console.php` | Perintah Artisan `research:admin-password-hash` |

Daftar lengkap route: `API.md`.

## 3. Middleware

| Alias / kelas | Fungsi |
|---|---|
| `active` → `EnsureActiveUser` | Logout paksa bila `users.status != active` |
| `admin` → `EnsureAdmin` | Mensyaratkan sesi admin (`research_admin_authenticated`); memeriksa status pengguna admin bila ada |
| `verified.if.required` → `EnsureEmailVerifiedWhenRequired` | Verifikasi email hanya bila `RESEARCH_REQUIRE_EMAIL_VERIFICATION` |
| `profile.complete` → `EnsureProfileComplete` | Alihkan ke `profile.setup` bila kelengkapan < `RESEARCH_PROFILE_GATE`; bila `profile_fields_optional` dan nama+email+institusi terisi, lolos |
| `research.live-refresh` → `ResearchLiveRefresh` | Penyegaran otomatis saat run tertunda |
| `SecurityHeaders` (grup web) | Header keamanan dan CSP |
| `SentryRequestContext` (grup web) | Konteks aman untuk Sentry |
| `SourceProcessingUi` (grup web) | Menangani penyegaran UI saat detail sumber (`Document`) masih diproses |
| `trustProxies(at: '*')` | Mempercayai proksi (ngrok/reverse proxy) — lihat `SECURITY.md` |

Rate limiter bernama: `login`, `register`, `admin-login`, `research`, `profile`, `library` (di `AppServiceProvider`).

## 4. Controllers (`app/Http/Controllers`)

- **Pengguna**: `ResearchController` (workspace/dashboard), `ResearchSubmissionController` (kirim riset), `ResearchRunStatusController`, `ResearchOutputPresentationController`, `ConversationController`, `SavedResultController`, `DocumentGenerationController`, `HistoryController`, `LibraryController`, `ProfileController`.
- **Auth**: `AuthenticatedSessionController`, `RegisteredUserController`, `PasswordResetController`, `GoogleController`.
- **Admin** (`Admin/`): `AuthController`, `DashboardController`, `LibraryController`, `CategoryController`, `CollectionController`, `PromptController`, `PromptVersionController`, `AiToolController`, `GlossaryController`, `DocumentTemplateController`, `AiRunController`, `AuditLogController`, `UserController`, `SettingsController`, `SecurityController`, `SourceSyncController`, `SourceIngestionStatusController`.

Catatan: beberapa controller sangat besar (mis. `DocumentGenerationController` > 1400 baris) dan menyatukan validasi, logika bisnis, dan presentasi. Pemecahan ke service/FormRequest adalah pekerjaan terpisah (lihat `CODE_STYLE.md`).

## 5. Services (`app/Services`)

| Folder | Kelas | Peran |
|---|---|---|
| `Research/` | `ResearchRunService` | Orkestrasi satu run: retrieval → sintesis |
| | `ResearchEvidencePipeline` | Menyiapkan bukti (kandidat, skor, batas per dokumen, status `evidence_ready`/`insufficient_evidence`) |
| | `KeywordRetriever` | Retrieval berbasis kata kunci pada chunk dokumen eligible |
| | `ResearchSynthesisService` | Menyusun prompt, memanggil provider, memvalidasi sitasi |
| | `CitationValidator`, `GlossaryContextService` | Validasi sitasi; glosarium sebagai konteks saja |
| | `AiProvider`, `AiProviderManager`, `MockResearchProvider`, `OpenAiResponsesProvider` | Abstraksi penyedia AI (`AI_PROVIDER`) |
| | `SourceIngestionService`, `PdfTextExtractor`, `DocumentChunker` | Ingestion PDF: ekstraksi (pdftotext atau fallback `smalot/pdfparser`), chunking (500 kata, overlap 75) |
| | `ResearchSettingsService` | Override pengaturan runtime dari tabel `research_settings` |
| | `Sync/` (`SourceSyncService`, `SourceConnector`, `SourceConnectorManager`, `MockSourceConnector`, `BacaDuluSourceConnector`) | Sinkronisasi sumber dari API BacaDulu utama |
| `Documents/` | `DocumentRenderService`, `DocxRenderer`, `PdfRenderer`, `AcademicDocumentBuilder`, `PrintableText` | Membangun dan merender dokumen akademik DOCX/PDF |
| `Monitoring/` | `SentryContext` | Konteks Sentry untuk request dan job antrean |

## 6. Jobs

- `ProcessResearchRun` — antrean `research`, `tries=1`, `timeout=180`, `failOnTimeout`; tidak memproses ulang run berstatus terminal.
- `ProcessSourceIngestion` — antrean `ingestion`.

Worker: `php artisan queue:work --queue=research,ingestion` (dengan `QUEUE_CONNECTION=database`). Tanpa worker, run tertahan di `queued`.

## 7. Model

23 model Eloquent; relasi utama ada di `DATABASE.md`. `User` memiliki kolom `role` dan `status` serta akses terenkripsi untuk telepon/alamat. Model `Workspace` ada tetapi pemakaiannya belum terverifikasi.

## 8. Blade dan JavaScript

`resources/views`: `layouts/` (`app`, `admin`, `admin-auth`), `components/` (navbar, footer, sidebar riset, aksi percakapan, `ui/`), `pages/` (landing), `auth/`, `research/workspace/`, `saved/`, `history/`, `documents/`, `profile/`, `admin/`, `errors/`.

`resources/js`: `app.js`, `admin.js`, `bootstrap.js`, `workspace-ui.js`, `workspace-topbar.js`, `research-sidebar.js`, `research-output.js`, `saved-research.js`, `profile-research.js`, `profile-security.js`, `document-guidance.js`, `auth-research.js`, `bacadulu-onboarding.js`, `landing-motion.js`.

Hook JS bergantung pada **ID dan atribut `data-*`** di Blade; jangan mengubahnya tanpa menelusuri skrip terkait.

## 9. Vite dan CSS

`vite.config.js` (laravel-vite-plugin + `@tailwindcss/vite`, hot file di `storage/framework/vite.hot`) memiliki input: `css/app.css`, `css/admin.css`, `js/admin.js`, `js/app.js`, `js/workspace-ui.js`, `js/landing-motion.js`, `js/research-output.js`, `js/auth-research.js`, `js/bacadulu-onboarding.js`. Layout admin memuat `admin.css`/`admin.js` sebagai ganti `app.css`.

CSS di `resources/css`: `base/` (variabel, reset, tipografi, font), `components/` (buttons, forms, navbar, feedback), `bundles/` (`core.css`, `overrides.css`), `pages/` (±35 berkas halaman). Banyak berkas halaman berlapis (lihat `DESIGN_SYSTEM.md` bagian 10).

Build produksi: `npm run build` → `public/build`.

## 10. Database dan penyimpanan

- MySQL (lihat `DATABASE.md`).
- Disk privat: `research_sources` → `storage/app/private/research-sources`; `research_outputs` → `storage/app/private/research-outputs`. Unduhan lewat route terautentikasi.
- Avatar: `public/uploads/profile-avatars` (tidak membutuhkan `storage:link`).

## 11. Alur riset (end-to-end)

```text
1. POST /research/run  (throttle research; validasi; DB::transaction membuat AiRun 'queued' + pesan)
2. Job ProcessResearchRun → ResearchRunService::execute
3.   ResearchEvidencePipeline::prepare  → bukti dari dokumen published+public (KeywordRetriever)
        ├─ bukti kurang → status 'insufficient_evidence'
        └─ cukup        → 'evidence_ready'
4.   ResearchSynthesisService::synthesize → AiProvider (mock/openai) → CitationValidator
        ├─ sitasi gagal → 'citation_failed'
        └─ sukses       → 'completed' + ResearchMessage asisten
5. Browser memoll /research/runs/{id}/status lalu memuat presentasi
```

## 12. Alur generasi dokumen

```text
Saved/Run completed ──► documents.create (tipe & gaya sitasi dari config/research.php)
        ──► documents.store (throttle 10/mnt; hanya run milik pengguna & selesai)
        ──► AcademicDocumentBuilder (membangun isi; template admin disnapshot pada generasi, sesuai tes regresi)
        ──► documents.render ──► DocumentRenderService ─► DocxRenderer / PdfRenderer ─► disk research_outputs
        ──► documents.download/{docx|pdf} (cek kepemilikan)
```

## 13. Integrasi BPS

**Tidak ada di arsitektur saat ini.** Satu-satunya integrasi data eksternal adalah Source Sync ke API BacaDulu utama (`API.md` 7.1) dan penyedia AI.

## 14. Observabilitas

Sentry (`sentry/sentry-laravel`), `audit_logs` untuk tindakan admin, log Laravel (`config/logging.php`). Tes privasi memastikan PII tidak dikirim secara default.
