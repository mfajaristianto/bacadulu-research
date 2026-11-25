# Product Flow & UX Refactor V1

Alur produk: **Research = tanpa AI. Generate Document = AI.**

```
Login -> Tutorial (sekali) -> Mulai Riset (kategori, alat riset, topik[, rumusan masalah])
      -> Hasil riset ringkas (tanpa AI) -> [opsional] Generate Document -> Dokumen Saya -> Unduh
```

## Research (tanpa AI)
- Rute: `GET /workspace` (form, `ResearchFlowController@start`) dan `GET /research/result` (`@result`). Hanya membaca; tidak ada AiRun, provider AI, atau penulisan database riset.
- `App\Services\ResearchFlow\ResearchResultComposer`: Source Discovery + Evidence Bundle -> gambaran (jumlah sumber, abstrak, full-text, metadata saja), temuan (cakupan dimensi), perbandingan per dimensi, referensi. Deterministik.
- Alat Riset = **ruleset**, bukan prompt LLM: teks alat (`question_template`, `task_prompt`, dst.) dibaca hanya untuk menentukan dimensi (objek, sampel, metode, variabel, teori, hasil). Tanpa sebutan dimensi = keenamnya. `DimensionExtractor` mengutip **kalimat apa adanya** dari evidence (abstrak/chunk); tanpa kecocokan = "Data tidak tersedia pada evidence." Tidak ada inferensi.
- Riset buku: `prompt_templates.input_mode = 'book'` -> topik + 1–8 rumusan masalah. Tiap rumusan = sub-query sendiri (`subQuery`: bila < 2 istilah inti, topik ditambahkan). Hasil per rumusan + "Ringkasan keseluruhan" (sumber unik, tingkat bukti, rumusan tanpa sumber).
- Bilingual search BELUM ada (tahap lain); komposer memakai pencarian existing dan titik integrasinya tetap `SourceDiscovery::discover`.

## Generate Document (AI)
- `POST /research/document` -> `DocumentGeneration` (status `generating`) -> job `GenerateResearchDocument` -> `DocumentPipeline`.
- Hasil Research dihitung ulang di server dari input (klien tidak dipercaya). Opsi form hanya yang bekerja: jenis dokumen (`research.documents.types`), format (DOCX/PDF), bahasa (id/en), panjang, gaya sitasi (`research.documents.citation_styles`), bagian (dari rencana).
- Rencana bagian: Prompt Dokumen Admin aktif & siap (`research` / `book_chapter`) bila ada, selain itu bawaan (Pendahuluan, Kajian Literatur, Metode [opsional], Pembahasan [per rumusan masalah pada mode buku], Kesimpulan).
- Per bagian: `SectionWriterRouter` -> bersihkan markdown (`DocumentTextCleaner`) -> validasi sitasi `[EXT:…]` terhadap Evidence Bundle -> checkpoint di `content_json` (bagian selesai) dan `settings_snapshot_json.generation` (status, penyedia, model, percobaan, sitasi).
- **Failover**: penyedia gagal (rate limit, kuota, timeout, tidak tersedia, atau keluaran tidak valid) -> keluarannya DIBUANG; penyedia berikutnya menulis ulang bagian yang sama dari awal dengan `SectionRequest` identik (outline, evidence, bagian sebelumnya, gaya). Penyedia `paid` dilewati kecuali `research.document_generation.allow_paid` (env `DOCUMENT_AI_ALLOW_PAID`) = true. Kegagalan non-failover menghentikan bagian. Semua penyedia gagal -> dokumen `failed`, bagian sebelumnya tetap tersimpan.
- Menambah penyedia = satu kelas `SectionWriter` + satu baris di `research.document_generation.providers`. Saat ini hanya `MockSectionWriter` (tanpa jaringan). **Belum ada adaptor penyedia gratis nyata** dan tidak ada panggilan live pada tahap ini.
- Halaman `documents.ready`: tersimpan di Dokumen Saya; unduhan otomatis SEKALI (sessionStorage + status unduhan dari audit `document.downloaded`); tombol "Unduh lagi" dan "Temukan dokumen" (`/documents?focus={id}`: buka halaman yang memuat kartu, scroll, sorot singkat, hormati `prefers-reduced-motion`). Server hanya tahu endpoint unduh dipanggil, bukan berkas tersimpan di perangkat.

## Tutorial
Memakai `users.onboarding_completed_at` (null = tampil). Admin > Pengguna > **Reset Tutorial** mengosongkannya (data riset tidak disentuh). Video opsional: `TUTORIAL_VIDEO_URL` (CSP situs harus mengizinkan sumbernya). Konsep `tutorial_version` BELUM ada (butuh kolom; ditunda).

## Admin
- Menu: Library Internal, Sumber Eksternal (juga memuat Impor Sumber), Kategori Riset, Pengaturan Pencarian > Kamus Istilah Pencarian, Pengguna, Admin & Keamanan. Koleksi tidak di sidebar (tautan dari Library Internal).
- Pengguna: tabel, urut default email A–Z, kolom device/IP terakhir, aksi: detail, lihat riset, Reset Tutorial, Nonaktifkan/Aktifkan, Hapus riset (konfirmasi + ketik email), Hapus pengguna (konfirmasi + jumlah data terkait + ketik email; email tidak disimpan di audit; berkas dokumen ikut dihapus). Akun administrator dan akun sesi aktif tidak dapat dihapus.
- Admin & Keamanan: daftar administrator; **hanya Super Admin** (dicek server -> 403) yang menambah, mengubah peran, mencabut. Super Admin utama = `ADMIN_EMAIL` (tidak dapat diubah dari UI). Admin tambahan = `users.admin_role`; masuk panel dengan kata sandi akunnya; peran dibaca ulang tiap permintaan (pencabutan berlaku seketika).

## Keamanan login
- `user_login_events`: login_at, last_activity_at (maks sekali per 5 menit), IP, user agent (255), jenis perangkat/OS/browser dari header (bukan model perangkat, bukan fingerprinting). IP bukan lokasi GPS. Retensi `LOGIN_RETENTION_DAYS` (default 180, minimum 30), `php artisan research:prune-login-events` (terjadwal harian).
- Trusted proxy: `TRUSTED_PROXIES` (default `127.0.0.1,::1`; sebelumnya `*`). Isi IP load balancer produksi; `X-Forwarded-For` dari sumber lain diabaikan.

## Migration DRAFT (belum dijalankan di DB asli)
`2026_11_26_000001_product_flow_ux_v1`: tabel `user_login_events`, kolom `users.admin_role`, kolom `prompt_templates.input_mode`. Aditif; kode tetap berjalan tanpa migration (riwayat login & peran admin tambahan nonaktif jujur; semua alat dianggap `standard`).
