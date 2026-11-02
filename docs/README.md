# Indeks Dokumentasi Teknis — BacaDulu Research

Pengenalan project ada di [README utama](../README.md). Aturan kerja untuk Claude Code ada di [CLAUDE.md](../CLAUDE.md) dan [AGENTS.md](../AGENTS.md). Dokumen berikut adalah dokumentasi teknis yang menjadi **sumber aturan aktif**.

## Dokumen aktif

| Dokumen | Isi |
|---|---|
| [PRD.md](PRD.md) | Tujuan produk, target pengguna, fitur yang sudah ada, kebutuhan fungsional/nonfungsional, roadmap. |
| [DESIGN_SYSTEM.md](DESIGN_SYSTEM.md) | Standar visual (Poppins, `#EF5843`, `#F7AA35`), komponen, dan selisih dengan CSS saat ini. |
| [RESPONSIVE.md](RESPONSIVE.md) | Breakpoint, grid, tipografi fluid, target sentuh, navigasi, pencegahan overflow, pengujian viewport. |
| [ARCHITECTURE.md](ARCHITECTURE.md) | Struktur Laravel aktual: route, middleware, controller, service, job, model, Vite, penyimpanan, alur riset dan dokumen. |
| [SECURITY.md](SECURITY.md) | Kontrol keamanan dengan status berbasis bukti, temuan prioritas, dan 20 pemeriksaan pra-produksi. |
| [CODE_STYLE.md](CODE_STYLE.md) | Standar PHP, Laravel, Blade, JavaScript, CSS, penamaan, error handling, minimal refactor. |
| [DATABASE.md](DATABASE.md) | Basis data, Eloquent, migrasi, seeder, relasi, constraint, transaksi, backup/restore. |
| [API.md](API.md) | Daftar endpoint aktual, rate limit, integrasi eksternal (Source Sync, AI), status BPS. |
| [TESTING.md](TESTING.md) | PHPUnit, status baseline tes, pengujian otorisasi/API/responsive/browser/regresi/keamanan. |
| [FRONTEND_RESOURCES.md](FRONTEND_RESOURCES.md) | Referensi visual (ThreeUI, KitBitz, Create with Swift, Glass3D), batas pemakaian, lisensi. |
| [DEPLOYMENT.md](DEPLOYMENT.md) | Setup lokal, build, environment, deployment produksi, cPanel, cache, storage, rollback, verifikasi. |

## Arsip

Dokumentasi lama dipindahkan (bukan dihapus) ke [archive/](archive/README.md): paket konteks `bacadulu-context` (PRD, Master Key, Design System, Roadmap, dll.), spesifikasi desain V3, dan README setup/build lama. Isi unik darinya sudah dikonsolidasikan ke dokumen aktif di atas.

## Urutan baca menurut jenis tugas

| Tugas | Baca |
|---|---|
| Mengubah UI/CSS | `DESIGN_SYSTEM.md`, `RESPONSIVE.md`, `CODE_STYLE.md` |
| Mengubah fitur backend | `ARCHITECTURE.md`, `DATABASE.md`, `API.md`, `SECURITY.md` |
| Menambah route/endpoint | `API.md`, `SECURITY.md`, `TESTING.md` |
| Menyiapkan/merilis | `DEPLOYMENT.md`, `SECURITY.md` bagian 3, `TESTING.md` bagian 9 |
| Memahami produk | `PRD.md` |

## Konvensi dokumentasi

- Seluruh isi dokumentasi dalam bahasa Indonesia; nama berkas, teknologi, sintaks kode, dan istilah teknis standar boleh bahasa Inggris.
- Informasi yang tidak dapat dipastikan ditandai **"Belum diverifikasi"**; jangan mengarang.
- Jangan menaruh rahasia (isi `.env`, kunci API, kata sandi) di dokumentasi.
- Perbarui dokumen yang relevan bersamaan dengan perubahan kode; jangan membuat dokumen baru bila dokumen yang ada sudah memenuhi fungsinya.
