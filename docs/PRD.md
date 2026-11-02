# PRD — BacaDulu Research

Status dokumen: diperbarui dari konteks lama (`archive/bacadulu-context/01_PRD_BACADULU_RESEARCH.md`) dan diverifikasi terhadap kode aktual.

## 1. Tujuan platform

BacaDulu Research adalah **workspace riset berbasis AI yang mengutamakan bukti (evidence-first)** untuk mahasiswa dan administrator. Pengguna tidak hanya menerima jawaban chat, tetapi dapat menelusuri klaim ke sumber, menyimpan hasil, melanjutkan konteks riset, dan mengubah hasil riset menjadi dokumen.

Alur nilai produk:

```text
Pertanyaan → Sumber → Chunk → Bukti → Sintesis → Insight → Dokumen
```

Platform ini **bukan**: chatbot generik, penjelajah mentah perpustakaan internal, atau dashboard kartu SaaS umum.

## 2. Target pengguna

| Pengguna | Kebutuhan utama | Bukti di kode |
|---|---|---|
| Mahasiswa / peneliti pemula | Bertanya, mendapat jawaban bersitasi, menyimpan, membuat dokumen | Route `workspace`, `saved.*`, `documents.*` |
| Pengguna profesional (universal) | Profil organisasi non-kampus (perusahaan, pemerintah, nirlaba, independen) | `ProfileController`, field organisasi di `users` |
| Administrator | Mengelola pustaka sumber, prompt, tool AI, pengguna, audit | Prefix admin `panel-adminbaca-research` (`routes/admin-console.php`) |

## 3. Fitur yang sudah ada (terverifikasi di routes/kode)

### 3.1 Autentikasi dan onboarding
- Login, register, logout, lupa/reset password (`routes/web.php`).
- Google OAuth ada di kode tetapi **nonaktif secara default** (`RESEARCH_GOOGLE_AUTH_ENABLED=false`).
- Verifikasi email bersifat opsional (`RESEARCH_REQUIRE_EMAIL_VERIFICATION`, default `false`).
- Gerbang kelengkapan profil (`RESEARCH_PROFILE_GATE`, default 80) lewat middleware `profile.complete`.
- Onboarding tersimpan di kolom `users.onboarding_completed_at` (route `onboarding.complete`).

### 3.2 Research Workspace
- Halaman `/workspace` dan `/workspace/{conversation}` dengan riwayat percakapan di sidebar (Recent 5 item, Show more/less).
- Pemilihan **Research Tool / prompt** yang disiapkan admin; pengguna hanya mengetik pertanyaan.
- Pengiriman riset `POST /research/run` (rate limit `research`, 12/menit/pengguna) membuat `ai_runs` berstatus antrean, diproses job `ProcessResearchRun`.
- Status run dapat dipoll (`research.runs.status`) dan presentasi hasil dirender server (`research.runs.presentation`).
- Retrieval hanya memakai dokumen `published` + `public` dengan chunk (aturan fail-closed untuk sumber `restricted`).
- Sitasi divalidasi (`CitationValidator`); glosarium hanya konteks, bukan sumber sitasi.
- Percakapan dapat di-pin, di-rename, dihapus (dengan halaman konfirmasi).

### 3.3 Saved (hasil tersimpan)
- Model `SavedResult` terhubung ke `AiRun` dan `DocumentGeneration`.
- Daftar, filter, buka kembali konteks, ubah judul, favorit, hapus (hanya bookmark, bukan run asli).

> Catatan istilah: brief tugas menyebut **"Saved Dataset"**. Di kode yang ada, fitur Saved menyimpan **hasil riset AI dan dokumen**, bukan dataset. Tidak ada entitas dataset.

### 3.4 History dan Library
- `GET /history` masih ada di kode; menu History **tidak** ada di navigasi utama sidebar (keputusan desain lama yang terkunci).
- `GET /library` dan `/library/{document}` untuk pengguna dibatasi `throttle:library` dan hanya menampilkan sumber yang layak.

### 3.5 Personal Workspace
- Model `Workspace` dan tabel `workspaces` + `workspace_documents` ada di skema (`app/Models/Workspace.php`).
- **Belum diverifikasi**: tidak ditemukan pemakaian `Workspace::` di kode aplikasi, sehingga fitur "Personal Workspace" sebagai fitur pengguna terpisah belum dapat dikonfirmasi. Route `/workspace` adalah ruang kerja riset (percakapan), bukan model `Workspace`.

### 3.6 Document Generation
- Alur: hasil riset selesai → `documents.create` → pilih tipe dan gaya sitasi → review/konfirmasi → generate → unduh.
- 11 tipe dokumen dan 3 gaya sitasi (APA 7, IEEE, Harvard) dari `config/research.php`.
- Render DOCX (`DocxRenderer`) dan PDF (`PdfRenderer`) lewat `DocumentRenderService`; file disimpan di disk privat `research_outputs` dan diunduh via route terautentikasi.
- Snapshot template dokumen tidak berubah ketika template admin diedit (diuji di `FinalResearchPlatformRegressionTest`; hasil uji saat ini lihat `TESTING.md`).

### 3.7 Profil
- Satu aksi `Edit Profil`, model organisasi universal, avatar (JPG/PNG/WebP maks 5 MB), telepon dan alamat dienkripsi (`Crypt::encryptString`).
- Perubahan kredensial lewat route terpisah `profile.credentials.update`.

### 3.8 Admin Console
Modul yang ada (route `admin.*`): dashboard, pustaka sumber (unggah PDF, status ingestion), kategori, koleksi, prompt dan versi prompt, AI tools, glosarium, template dokumen, AI runs, audit log, pengaturan runtime riset, keamanan, **source sync**, dan manajemen pengguna (lihat daftar, detail, ubah status).

### 3.9 Dataset dan integrasi BPS
- **Belum diverifikasi / tidak ditemukan.** Kata "BPS" atau "Badan Pusat Statistik" tidak muncul di kode, config, route, maupun dokumentasi lama.
- Integrasi eksternal yang benar-benar ada adalah **Source Sync** ke API BacaDulu utama (`config/source-sync.php`, provider `mock` secara default; provider `bacadulu` menunggu endpoint remote). Detail di `API.md`.
- Jika integrasi BPS memang direncanakan, perlu spesifikasi tersendiri sebelum dimasukkan ke PRD sebagai fitur.

## 4. Kebutuhan fungsional

| ID | Kebutuhan | Status |
|---|---|---|
| FR-01 | Shell riset konsisten (topbar/sidebar, drawer mobile) | Ada |
| FR-02 | Workspace menampilkan tool aktif, pertanyaan, hasil, bukti/sumber, aksi Save dan Create Document | Ada |
| FR-03 | Prompt picker dengan nama Inggris/Indonesia, deskripsi, ikon, `Apa ini?` | Ada (data dari `prompt_templates`) |
| FR-04 | Modal edukasi prompt (arti, kapan dipakai, contoh, output) | Ada |
| FR-05 | Saved: daftar, filter/cari, ringkasan dan grafik dari data nyata, resume | Ada (kelengkapan visual: lihat `archive/bacadulu-context/09_ACCEPTANCE_CHECKLIST.md`, belum dicentang ulang) |
| FR-06 | Profil universal dengan satu tombol edit | Ada |
| FR-07 | Dokumen: tipe/gaya dari config, konfirmasi sebelum generate, unduhan eksplisit PDF dan DOCX | Ada |
| FR-08 | Admin mengelola sumber, prompt, tool, pengguna, audit | Ada |
| FR-09 | Source Sync dari API BacaDulu dengan staging dan review admin | Ada (provider nyata menunggu endpoint remote) |

## 5. Kebutuhan nonfungsional

- **Keamanan**: kepemilikan data per pengguna, CSRF, rate limiting, header keamanan, enkripsi data pribadi tertentu. Lihat `SECURITY.md`.
- **Performa**: pekerjaan berat (riset AI, ingestion PDF) diproses lewat antrean (`research`, `ingestion`).
- **Aksesibilitas**: fokus keyboard terlihat, `Escape` menutup dialog, dukungan reduced motion, target sentuh memadai. Lihat `RESPONSIVE.md`.
- **Responsif**: tanpa overflow horizontal pada 360–1440px. Lihat `RESPONSIVE.md`.
- **Keterpeliharaan**: pemilik CSS/JS jelas, hindari lapisan override. Lihat `CODE_STYLE.md`.
- **Kepatuhan data**: `.env`, kunci API, dan dump database tidak pernah ikut dalam paket distribusi.

## 6. Bukan tujuan (non-goals)

- Membangun ulang autentikasi dari nol.
- Mengganti arsitektur AI/retrieval.
- Membuka akses bebas ke pustaka internal.
- Paywall/langganan.
- Mengubah kontrak route atau database tanpa kebutuhan terbukti.

## 7. Roadmap (berdasarkan dokumen yang ada)

Roadmap lama (`archive/bacadulu-context/06_IMPLEMENTATION_ROADMAP.md`) memiliki fase 0–8. Status faktual tiap fase **belum diaudit ulang** pada pekerjaan dokumentasi ini. Prioritas yang sudah terdokumentasi:

1. Penyelarasan Design System ke standar baru (Poppins, `#EF5843`, `#F7AA35`). Lihat `DESIGN_SYSTEM.md` bagian "Selisih dengan implementasi".
2. Perbaikan rangkaian pengujian otomatis (33 dari 50 tes gagal pada baseline). Lihat `TESTING.md`.
3. Penerapan perlindungan brute-force lanjutan (progressive delay, lockout sementara). Lihat `SECURITY.md`.
4. Klarifikasi kebutuhan integrasi BPS dan Personal Workspace sebelum direncanakan.
5. Konsolidasi CSS lapisan "fix/final/round2" yang menumpuk. Lihat `CODE_STYLE.md`.

## 8. Kriteria penerimaan

Rilis tidak diterima jika terdapat: overflow horizontal di mobile, avatar berbeda antara Profil dan Workspace, markdown mentah (`##`) tampil di hasil, aksi unduh PDF/DOCX ambigu, grafik Saved berisi angka palsu, atau perubahan visual sidebar tanpa instruksi eksplisit.
