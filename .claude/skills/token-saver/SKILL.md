---
name: token-saver
description: Gunakan saat memperbaiki, menelusuri, atau mengubah kode BacaDulu agar pembacaan file, perubahan kode, dan output tetap efisien.
---

# Token Saver — BacaDulu

Tujuan: menyelesaikan tugas dengan membaca, mengubah, dan menulis **sekecil mungkin yang diperlukan**, tanpa mengorbankan keamanan, kebenaran, atau pengujian.

## 1. Baca file yang relevan saja

- Mulai dari titik masuk tugas: route → controller → Blade/komponen → CSS → JS. Telusuri hanya dependensi yang benar-benar disentuh.
- Tentukan dulu file mana yang dibutuhkan, baru baca. Jangan membuka file "untuk berjaga-jaga".
- Hindari `node_modules`, `vendor`, `public/build`, `storage/logs`, migrasi yang tidak terkait, berkas besar hasil generate, dan aset.

## 2. Cari dulu sebelum membaca file besar

- Gunakan pencarian (Grep/Glob, atau Serena/codebase-memory bila tersedia) untuk menemukan simbol, kelas CSS, ID, atau `data-*`.
- Baca rentang baris yang relevan (`offset`/`limit`), bukan seluruh file. Controller dan CSS di project ini bisa sangat panjang (mis. `DocumentGenerationController` > 1400 baris).
- Untuk daftar route gunakan `php artisan route:list --except-vendor --json` dan saring, bukan membaca semua file route.

## 3. Jangan scan seluruh repositori tanpa kebutuhan

Satu halaman/fitur tidak memerlukan pemahaman seluruh repo. Pindai luas hanya bila tugasnya memang lintas modul (audit, migrasi besar), dan batasi dengan pola pencarian.

## 4. Jangan mengulang pembacaan

- Jangan membaca ulang file yang sudah ada di percakapan atau baru saja diedit (tool Edit/Write sudah gagal bila ada masalah).
- Simpan temuan penting (path, nama simbol, kesimpulan) dalam ringkasan singkat dan pakai kembali.
- Bila konteks panjang: `/compact` untuk melanjutkan tugas yang sama, `/clear` sebelum tugas yang tidak berkaitan.

## 5. Ubah hanya yang diperlukan

- Edit file dan blok kode yang menjadi akar masalah; jangan menulis ulang file utuh bila cukup `Edit`.
- Pilih file pemilik yang sudah ada sebelum membuat file baru. Jangan menambah lapisan CSS "fix/override" baru; ubah pemilik aturan.

## 6. Hindari refactor yang tidak terkait

Jangan mengganti nama, memformat ulang, memindahkan file, atau "merapikan" kode di luar tugas. Jangan menjalankan Pint/formatter ke seluruh repo; hanya pada file yang Anda ubah.

## 7. Pertahankan seluruh fungsi existing

Pertahankan nama route, kontrak controller, nama field form, `@csrf`, validasi, auth/otorisasi, pemisahan user/admin, ID dan `data-*` yang dipakai JS, binding data nyata, alur unggah, OAuth, dan perilaku database. Jangan menjalankan `migrate:fresh`, `composer update`, atau `npm update` tanpa persetujuan.

## 8. Baca dokumentasi sesuai jenis tugas

Baca hanya dokumen yang relevan (indeks: `docs/README.md`):

| Tugas | Dokumen |
|---|---|
| UI / CSS / animasi | `docs/DESIGN_SYSTEM.md`, `docs/RESPONSIVE.md`, `docs/CODE_STYLE.md` |
| Fitur backend / controller / service | `docs/ARCHITECTURE.md`, `docs/CODE_STYLE.md` |
| Skema / query / migrasi | `docs/DATABASE.md` |
| Route / endpoint / integrasi | `docs/API.md` |
| Auth, upload, data pengguna, input eksternal | `docs/SECURITY.md` |
| Tes / verifikasi | `docs/TESTING.md` |
| Setup / rilis | `docs/DEPLOYMENT.md` |
| Konteks produk | `docs/PRD.md` |

Dokumen di `docs/archive/` hanya dibaca bila diminta atau untuk menelusuri keputusan historis.

## 9. Jalankan pengujian yang relevan

- Jalankan tes yang menyentuh area yang diubah (`php artisan test --filter=...`), bukan selalu seluruh suite; jalankan seluruh suite sebelum menyatakan selesai untuk perubahan lintas modul.
- Setelah perubahan frontend: `npm run build`; periksa tampilan di browser (Playwright/DevTools) pada viewport 360/390/768/1024/1440 bila tugas menyentuh UI.
- Laporkan hasil apa adanya, termasuk tes yang gagal (sebelumnya atau karena perubahan Anda). Catatan: baseline tes saat ini punya banyak kegagalan lingkungan (`docs/TESTING.md`); bedakan dari kegagalan baru.
- Jangan mengklaim verifikasi runtime/visual yang tidak benar-benar dijalankan.

## 10. Ringkas laporan hasil

Laporan akhir singkat: apa yang diubah (file), mengapa, bagaimana diverifikasi (perintah + hasil), dan risiko/tindak lanjut. Tanpa mengulang isi diff atau narasi proses.

## 11. Jangan cetak file panjang ke percakapan

Rujuk dengan `path:baris`. Tampilkan hanya potongan kecil yang diperlukan. Simpan keluaran perintah panjang ke berkas sementara (scratchpad) dan tampilkan ringkasannya (`| head`, filter).

## 12. Jangan mengorbankan keamanan atau kualitas demi hemat token

Hemat token tidak boleh berarti: melewati pemeriksaan otorisasi/validasi, melewati tes yang relevan, menebak isi file yang belum dibaca, atau menyembunyikan kegagalan. Bila ragu, baca lebih banyak (terarah) daripada mengasumsikan.

## Daftar periksa cepat

- [ ] File yang dibaca = file yang dibutuhkan
- [ ] Pencarian sebelum membaca file besar
- [ ] Perubahan minimal, tanpa refactor tak terkait
- [ ] Fungsi dan kontrak existing utuh
- [ ] Dokumen relevan dibaca, bukan semuanya
- [ ] Tes/build relevan dijalankan dan dilaporkan jujur
- [ ] Laporan ringkas, tanpa dump file
