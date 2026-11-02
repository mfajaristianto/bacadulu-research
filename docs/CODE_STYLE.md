# Standar Kode — BacaDulu Research

Prinsip utama: **ikuti pola yang sudah ada selama masih layak**, ubah seminimal mungkin, dan jangan merapikan kode yang tidak terkait tugas. Dokumen ini merangkum pola aktual project plus aturan untuk kode baru.

## 1. Aturan umum

- Encoding UTF-8, akhir baris LF, indentasi 4 spasi, baris kosong di akhir berkas (`.editorconfig`). YAML 2 spasi.
- Satu perubahan = satu tujuan. Jangan mencampur perbaikan fungsional dengan pemformatan massal.
- Komentar menjelaskan **mengapa**, bukan mengulang apa yang kode lakukan. Project sudah memakai blok komentar bergaya header (`|---|`) untuk aturan bisnis penting; teruskan pola itu hanya untuk keputusan non-obvious.
- Bahasa: pesan untuk pengguna dalam bahasa Indonesia; nama kode (kelas, method, variabel, kolom) dalam bahasa Inggris; komentar boleh Indonesia atau Inggris, ikuti berkas sekitarnya.
- Jangan mengubah route name, nama field form, ID/`data-*` yang dipakai JS, kontrak controller, dan binding data nyata.

## 2. PHP

- PHP ≥ 8.2; gunakan `declare(strict_types=1)` hanya bila berkas sekitarnya memakainya.
- Kelas: PascalCase; method/variabel: camelCase; konstanta: UPPER_SNAKE_CASE.
- Type hint parameter dan return type pada method baru; gunakan properti `readonly` dan constructor promotion seperti di `ProcessResearchRun`.
- Gaya format existing berupa chaining vertikal dengan satu argumen per baris. Tidak ada `pint.json` (preset Laravel default). `vendor/bin/pint --test <berkas>` lolos untuk contoh berkas yang diperiksa, tetapi **jangan menjalankan Pint ke seluruh repo** (menghasilkan diff besar yang tidak terkait). Jalankan hanya pada berkas yang Anda ubah: `vendor/bin/pint <berkas>`.
- Hindari method sangat panjang; ekstrak ke private method atau service bila menambah logika baru. Controller yang sudah >1000 baris (mis. `DocumentGenerationController`) **tidak** direfaktor tanpa tugas khusus.

## 3. Laravel

- **Controller** tipis: validasi → panggil service → kembalikan respons. Logika bisnis baru ke `app/Services/<Domain>`.
- **Validasi**: gunakan `$request->validate()` atau `FormRequest` untuk fitur baru; pesan dalam bahasa Indonesia; `Rule::in` untuk nilai enum; batas panjang/ukuran selalu ada.
- **Otorisasi**: setiap resource privat diperiksa kepemilikannya (`abort_unless` / query terfilter `user_id`). Rute baru masuk grup middleware yang tepat (`auth`, `active`, `verified.if.required`, `profile.complete`; admin: `admin`).
- **Rate limit**: tambahkan `throttle` pada rute mutasi atau mahal.
- **Eloquent**: eager load relasi, `fillable` eksplisit, tidak ada `$guarded = []`. Gunakan transaksi + lock untuk operasi bersama (lihat `DATABASE.md`).
- **Config**: akses env hanya lewat `config()`; env langsung di kode aplikasi dilarang (kecuali seeder yang sudah ada).
- **Job**: tentukan antrean, `tries`, `timeout`; job harus idempoten terhadap status terminal.
- **Migrasi**: file baru, idempoten (`hasTable/hasColumn`), selalu ada `down()` yang masuk akal; tanpa `migrate:fresh`.
- Jangan membuat route atau controller baru bila yang ada sudah memenuhi kebutuhan.

## 4. Blade

- Output default `{{ }}` (ter-escape). `{!! !!}` hanya untuk HTML yang sudah disanitasi (mis. `Str::markdown(..., ['html_input' => 'strip', 'allow_unsafe_links' => false])`) atau fragmen kelas statis. Beri komentar alasan.
- Setiap form mutasi: `@csrf`, `@method` bila perlu, `action` memakai `route()`, dan `old()` untuk input (bukan kata sandi).
- Komponen yang dapat dipakai ulang: `components/` dan `components/ui/` (`button`, `badge`, `field`). Cari dan pakai ulang sebelum membuat varian baru. Hindari duplikat hampir identik.
- Jaga ID dan atribut `data-*` yang dipakai JS. Telusuri: route → controller → Blade → CSS → JS sebelum mengubah markup.
- Aksesibilitas: label terhubung ke input, `aria-*` untuk dialog/menu, urutan heading benar, `alt` bermakna.
- Tanpa data palsu yang di-hardcode untuk "mempercantik"; gunakan data nyata atau state kosong yang jelas.

## 5. JavaScript

- ES module; `const`/`let`, tanpa `var`. Tidak menambah pustaka UI baru (tanpa React/Vue/Next.js/Framer Motion) tanpa persetujuan.
- Animasi memakai **GSAP** yang sudah ada; hormati `prefers-reduced-motion`.
- Pilih elemen lewat `data-*` atau ID yang stabil, bukan kelas gaya. Inisialisasi per halaman dengan pengecekan keberadaan elemen agar tidak error di halaman lain.
- Event delegation untuk daftar dinamis; bersihkan listener/timeline saat tidak dibutuhkan.
- Jangan menyalin fungsi antar-berkas; ekstrak ke modul bersama bila dipakai ≥ 2 tempat.
- Tangani error `fetch` (status non-2xx, 419, 429) dan tampilkan pesan yang ramah; sertakan header CSRF untuk mutasi.
- Tanpa `console.log` tertinggal di kode rilis. Tanpa inline script (CSP produksi `script-src 'self'`).

## 6. CSS

- Tailwind CSS 4 tersedia; project juga memakai CSS biasa per halaman. Ikuti pola berkas tempat Anda bekerja.
- Token (warna, radius, spasi, font) didefinisikan di `resources/css/base/variables.css`; gunakan `var(--…)`, jangan nilai literal baru. Target token terbaru ada di `DESIGN_SYSTEM.md`.
- **Satu pemilik per komponen.** Cari kelas yang sudah ada dan ubah berkas pemiliknya. Dilarang rantai `aturan lama → override → override lagi → !important → override akhir`. Konsolidasikan aturan usang bila aman.
- Hindari `!important`, `position: absolute` berlebihan, dan hack khusus viewport tertentu. Gunakan mobile-first dengan `min-width` (lihat `RESPONSIVE.md`).
- Penamaan: kebab-case; pola BEM ringan (`.blok__elemen--varian`) bila berkas sekitarnya memakainya.
- Hormati `prefers-reduced-motion`; fokus terlihat dengan `:focus-visible`.
- Catatan utang teknis: ada banyak berkas "lapisan perbaikan" (`workspace-fixes.css`, `research-final-revisions.css`, `research-ux-round2.css`, `bundles/overrides.css`, dll.). Jangan menambah lapisan baru; konsolidasi dikerjakan per halaman dengan verifikasi visual.

## 7. Struktur class dan fungsi

- Satu tanggung jawab per kelas; nama menyatakan peran (`…Service`, `…Pipeline`, `…Renderer`, `…Validator`).
- Dependensi disuntikkan lewat constructor; hindari `app()` / facade di dalam logika inti yang perlu diuji.
- Fungsi pendek dengan nama kata kerja (`prepare`, `render`, `validate`); hindari parameter boolean yang mengubah perilaku (pakai dua method).
- Kembalikan nilai/array terstruktur yang konsisten (lihat kontrak `ResearchRunService::execute`).

## 8. Error handling

- Jangan menelan exception diam-diam. Tangkap di batas yang tepat, ubah ke status domain (mis. run `failed`) dan laporkan ke Sentry/log tanpa PII.
- Pesan ke pengguna: Indonesia, jelas, tanpa jejak teknis. Gunakan `abort(403/404)` untuk pelanggaran akses.
- Jangan mencatat kata sandi, token, telepon, atau alamat di log.
- Validasi di perbatasan sistem (input pengguna, API eksternal); jangan menambah validasi defensif berlebihan di dalam kode internal.

## 9. Minimal refactor

- Perbaiki akar masalah, bukan gejala, tetapi **batasi cakupan** pada file dan kode yang diperlukan tugas.
- Jangan mengganti nama, memindahkan berkas, atau memformat ulang kode yang tidak terkait.
- Pertahankan seluruh fungsi existing; jangan menghapus kode mati bila keyakinan di bawah tinggi.
- Setelah perubahan signifikan: periksa `git diff`, jalankan `npm run build`, dan tes yang relevan (`TESTING.md`).

## 10. Manajemen dependensi

- Jangan menjalankan `composer update` / `npm update` tanpa persetujuan; tambahkan paket hanya dengan alasan kuat, versi terkunci di lockfile.
- Periksa lisensi dan pemeliharaan paket; jalankan `composer audit` dan `npm audit` (lihat `SECURITY.md`).
- Stack frontend dibekukan pada Blade + Vite + Tailwind 4 + GSAP. Pustaka baru memerlukan persetujuan eksplisit.
- Hapus dependensi yang tidak lagi dipakai hanya setelah memastikan tidak ada referensi.
