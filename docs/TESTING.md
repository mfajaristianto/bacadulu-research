# Pengujian — BacaDulu Research

## 1. Status baseline (dijalankan pada sesi dokumentasi ini)

Perintah: `php artisan test` (PHP 8.5.2, PHPUnit 11, SQLite in-memory dari `phpunit.xml`).

**Hasil: 33 gagal, 17 lolos (64 assertion), durasi ±23 detik.** Tidak ada kode aplikasi yang diubah sebelum pengujian ini; hasil ini adalah kondisi apa adanya.

| Berkas tes | Hasil |
|---|---|
| `Unit/ExampleTest`, `Unit/PrintableDocumentTextTest` | Lolos |
| `Feature/ExampleTest`, `BacaDuluSourceConnectorTest`, `GoogleAuthToggleTest`, `SentryPrivacyRegressionTest` | Lolos |
| `AdminAuditCoverageTest`, `AdminConsoleLayoutTest`, `AdminPasswordHashTest`, `FinalResearchPlatformRegressionTest`, `FinalWiringRegressionTest`, `ResearchOnboardingUxTest`, `ResearchRunPipelineTest`, `SourceIngestionQueueTest`, `SourceSyncAdapterTest` | Gagal |

Gejala dominan (32 dari 33 kegagalan): `PDOException: SQLSTATE[HY000]: General error: 1 cannot start a transaction within a transaction` pada `SQLiteConnection::beginTransaction` (Laravel di PHP ≥ 8.4 menjalankan `BEGIN DEFERRED TRANSACTION`). Setidaknya sebagian berkas yang gagal (mis. `AdminAuditCoverageTest`, `AdminConsoleLayoutTest`) memakai `RefreshDatabase`.

**Penyebab akar belum dianalisis** (di luar lingkup pekerjaan ini; kandidat: interaksi migrasi + transaksi di SQLite pada PHP 8.5, atau migrasi yang membuka transaksi sendiri). Konsekuensinya: tes otorisasi/IDOR, hash admin, pipeline riset, dan regresi platform **saat ini tidak dapat dijadikan bukti** bahwa perilaku tersebut benar. Memperbaiki rangkaian tes adalah prioritas pekerjaan berikutnya (lihat `PRD.md` roadmap).

> Jangan menyimpulkan bahwa aplikasi rusak dari hasil ini; yang terverifikasi hanyalah bahwa rangkaian tes belum dapat dijalankan penuh di lingkungan ini.

## 2. PHPUnit

- Konfigurasi: `phpunit.xml` memakai `APP_ENV=testing`, `CACHE_STORE=array`, `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `QUEUE_CONNECTION=sync`, `SESSION_DRIVER=array`, `MAIL_MAILER=array`. Tes **tidak menyentuh basis data MySQL** aplikasi.
- Menjalankan:

```bash
php artisan test                                   # semua
php artisan test --filter=SentryPrivacyRegression  # satu kelas
php artisan test tests/Feature/GoogleAuthToggleTest.php
php artisan test --stop-on-failure
```

- Gunakan `RefreshDatabase` untuk tes yang menyentuh basis data; pakai factory (`UserFactory`) alih-alih data tulis tangan.
- Jangan menjalankan tes terhadap basis data nyata; jangan menjalankan `migrate:fresh` di luar SQLite uji.

## 3. Pengujian fitur

Cakupan yang sudah ada (nama kelas): pipeline riset (`ResearchRunPipelineTest`), ingestion antrean (`SourceIngestionQueueTest`), source sync (`SourceSyncAdapterTest`, `BacaDuluSourceConnectorTest`), regresi platform akhir dan wiring (`FinalResearchPlatformRegressionTest`, `FinalWiringRegressionTest`), UX onboarding (`ResearchOnboardingUxTest`), tata letak konsol admin (`AdminConsoleLayoutTest`), cakupan audit admin (`AdminAuditCoverageTest`), cetak teks dokumen (`PrintableDocumentTextTest`).

Fitur baru wajib menambah tes untuk: jalur sukses, validasi gagal, akses tanpa login, dan akses pengguna lain.

## 4. Pengujian autentikasi

Yang perlu tercakup: login sukses/gagal, akun non-aktif ditolak, rate limit login (8/menit email+IP, 20/menit IP), regenerasi sesi, logout, register + password rules, reset kata sandi (throttle), Google OAuth aktif/nonaktif (`GoogleAuthToggleTest` ada), login admin dengan hash/plaintext (`AdminPasswordHashTest` ada, kini gagal), pemisahan sesi admin vs pengguna.

Belum ada tes yang diverifikasi untuk: batas 429 login, enumerasi akun, progressive delay/lockout (fitur belum ada).

## 5. Pengujian otorisasi

Untuk setiap resource privat (Saved, Dokumen, Run, Percakapan, Profil): pengguna A **tidak boleh** membaca/mengubah/menghapus milik pengguna B (harus 403/404), tamu dialihkan ke login, pengguna non-admin tidak dapat mengakses prefix admin. Tes `other user cannot open or download someone elses generated document` sudah ada tetapi gagal karena masalah lingkungan (bagian 1).

## 6. Pengujian API / endpoint

Tidak ada API publik; uji endpoint JSON internal (`onboarding.complete`, `research.runs.status`, `research.runs.presentation`) dengan `actingAs`, `getJson/postJson`, dan assert status + struktur JSON. Uji respons 429 (JSON vs HTML). Gunakan `Http::fake()` untuk penyedia AI dan connector Source Sync; jangan memanggil layanan eksternal sungguhan.

## 7. Pengujian responsive

Viewport wajib: **360, 390, 430, 768, 1024, 1280, 1440px**.

Per halaman (publik, auth, onboarding, workspace, Saved, History, Library, profil, dokumen, admin, error):
- Tidak ada overflow horizontal: `document.documentElement.scrollWidth <= clientWidth`.
- Tidak ada teks/kontrol terpotong; target sentuh ≥ 44px.
- Drawer, dropdown, modal berfungsi dan tidak keluar layar; tabel terbaca.
- Zoom 200% tetap layak; orientasi lanskap mobile.
- Keyboard virtual tidak menutup tombol kirim.

Standar lengkap: `RESPONSIVE.md`.

## 8. Pengujian browser

Gunakan Playwright / Chrome DevTools (tersedia sebagai MCP di lingkungan Claude Code) terhadap server lokal (`php artisan serve` + `npm run dev` atau hasil `npm run build`).

Periksa: error konsol, request gagal, aset/font hilang, overflow, clipping, status interaksi (hover, fokus, aktif, disabled), perilaku animasi (termasuk `prefers-reduced-motion`), alur utama (login → workspace → kirim riset → simpan → buat dokumen → unduh).

Bila ada referensi desain: render → tangkap screenshot → bandingkan → daftarkan selisih → perbaiki selisih tingkat tinggi → periksa ulang. **Jangan menyatakan UI "sesuai desain" tanpa bukti browser.**

## 9. Pengujian regresi

Setiap perbaikan bug menambah tes yang gagal sebelum perbaikan. Daftar periksa regresi sebelum rilis:

- `npm run build` sukses tanpa error; `php artisan optimize:clear`.
- Login/sesi utuh; workspace dan resume percakapan.
- Saved: simpan/ubah/favorit/hapus/buka.
- Profil: ubah data, avatar, kredensial.
- Dokumen: buat, tampilkan, render, unduh DOCX dan PDF.
- Admin: login, unggah sumber, status ingestion, prompt, pengguna.
- Fokus keyboard, `Escape` menutup dialog, reduced motion.

Daftar periksa UI/UX lama (belum dicentang ulang): `archive/bacadulu-context/09_ACCEPTANCE_CHECKLIST.md`.

## 10. Pengujian keamanan

- `composer audit` dan `npm audit --omit=dev` (hasil terakhir di `SECURITY.md` 2.20).
- Tes IDOR per resource (bagian 5), CSRF (request tanpa token → 419), rate limit (request ke-N+1 → 429), upload (tipe/ukuran salah ditolak), unduhan hanya milik sendiri.
- Periksa header respons (CSP, HSTS di HTTPS produksi, nosniff, frame options).
- Uji injeksi prompt: dokumen sumber berisi instruksi jahat tidak boleh mengubah perilaku sistem atau membocorkan prompt sistem.
- Uji race: dua request simpan bersamaan tidak membuat duplikat Saved.
- Daftar 20 pemeriksaan pra-produksi: `SECURITY.md` bagian 3.

## 11. Batas yang jujur

Dokumen ini tidak mengklaim cakupan tes tertentu (persentase) karena tidak diukur. Pengujian browser/visual tidak dijalankan pada pekerjaan dokumentasi ini.
