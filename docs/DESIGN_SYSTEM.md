# Design System — BacaDulu

Dokumen ini menetapkan **standar desain terbaru**. Implementasi CSS saat ini belum sepenuhnya mengikuti standar ini; selisihnya dicatat pada bagian [Selisih dengan implementasi saat ini](#10-selisih-dengan-implementasi-saat-ini) sebagai pekerjaan berikutnya. Pekerjaan dokumentasi ini **tidak mengubah CSS**.

Panduan lama (token `#F26B4F`, sidebar terkunci, ukuran kompak) tersimpan di `archive/bacadulu-context/04_DESIGN_SYSTEM_UIUX.md` dan `archive/DESIGN_BACADULU_RESEARCH_V3.md`.

## 1. Karakter visual

Cerah, akademik, muda, premium, dan mudah didekati mahasiswa. Rasa yang dituju: *ruang kerja riset modern + perpustakaan akademik digital + SaaS pelajar premium*.

Hindari: gradient berlebihan, glassmorphism berlebihan, garis dekoratif yang berulang, shadow berat, UI terlalu ramai, dashboard gelap (kecuali diminta), nuansa neon, ikon "AI sparkle" berlebihan, kartu untuk setiap bagian.

## 2. Tipografi

| Aspek | Standar |
|---|---|
| Font utama | **Poppins** |
| Bobot | 400 (isi), 500 (label), 600 (judul kecil/tombol), 700 (judul besar) |
| Body utama | target minimum 16px |
| Line-height body | 1.5–1.7 |
| Line-height judul | 1.15–1.3 |
| Skala heading | Lihat `RESPONSIVE.md` (H1 desktop 48–64px, tablet 36–48px, mobile 28–32px) |

Catatan teknis: kebijakan CSP produksi (`SecurityHeaders`) hanya mengizinkan `font-src 'self' data:`. Poppins harus **di-host sendiri** di `public/fonts` (format `woff2`) dan dideklarasikan dengan `@font-face` di `resources/css/base/fonts.css`. Memuat dari Google Fonts akan diblokir CSP produksi. Periksa lisensi font (Poppins berlisensi SIL OFL) dan simpan berkas lisensinya seperti font yang sudah ada.

## 3. Warna

| Peran | Token usulan | Nilai |
|---|---|---|
| Primer | `--bd-primary` | `#EF5843` |
| Sekunder / aksen | `--bd-secondary` | `#F7AA35` |
| Latar utama | `--bd-bg` | putih `#FFFFFF` |
| Latar lembut | `--bd-bg-soft` | off-white (mis. `#FAF8F5`) |
| Latar netral | `--bd-bg-muted` | abu-abu muda (mis. `#F3F4F6`) |
| Aksen gelap | `--bd-ink` / `--bd-ink-2` | `#170F38` / `#241B52` |
| Teks pada tombol primer | — | putih |

Nilai off-white dan abu-abu muda di atas adalah **usulan**; standar hanya menyebut "off-white" dan "abu-abu muda" tanpa heksadesimal.

Aturan pemakaian:
- Aksen gelap dipakai seperlunya (teks judul, footer, panel penekanan), bukan sebagai tema gelap seluruh halaman.
- `#F7AA35` sebagai latar harus dipasangkan dengan teks gelap (`#170F38`), bukan putih.
- Status: sukses/peringatan/error memakai warna semantik terpisah dan selalu disertai ikon atau teks, tidak hanya warna.

### Kontras (perhitungan WCAG perkiraan, perlu diverifikasi dengan alat)

| Pasangan | Rasio perkiraan | Catatan |
|---|---|---|
| Putih di atas `#EF5843` | ≈ 3,4 : 1 | Lolos untuk teks besar (≥ 24px, atau ≥ 18,66px tebal) dan komponen UI; **tidak lolos AA 4,5 : 1 untuk teks normal**. |
| Putih di atas `#F7AA35` | ≈ 1,9 : 1 | Tidak boleh. |
| `#170F38` di atas `#F7AA35` | ≈ 9 : 1 | Lolos. |

Konsekuensi: standar "teks putih pada tombol oranye" harus dipasangkan dengan label tombol tebal dan cukup besar (mis. 16px bobot 600 ke atas dianjurkan diuji, atau pertimbangkan oranye lebih gelap khusus tombol). **Keputusan diperlukan dari pemilik produk** sebelum implementasi.

## 4. Spasi, radius, bayangan

- Skala spasi: 8, 16, 24, 32, 48, 64px (basis 8px).
- Radius: kecil 8px, sedang 12px, besar 16px. Hindari radius ekstrem kecuali elemen berbentuk pil.
- Bayangan: lembut dan berkontras rendah; satu lapis. Efek **glow oranye halus** hanya untuk hover/fokus elemen interaktif utama.

## 5. Komponen

### Button
- Primer: latar `#EF5843`, teks putih, tinggi minimum 44px (48px dianjurkan di mobile), radius 12px.
- Hover: glow oranye halus, mis. `box-shadow: 0 6px 16px rgba(239, 88, 67, .28)`; tanpa perubahan ukuran yang menggeser layout.
- Sekunder: latar putih, border abu-abu hangat, teks `#170F38`.
- Tersier/teks: tanpa latar, garis bawah saat hover/fokus.
- Fokus: ring 2px terlihat (jangan hanya mengandalkan glow).
- Disabled: opasitas turun, `cursor: not-allowed`, tetap terbaca.
- Satu CTA primer per area; hierarki CTA harus jelas.

### Card
- Gunakan hanya bila isi memang satu unit mandiri (item daftar, ringkasan, formulir). Jangan membungkus setiap bagian halaman dalam kartu.
- Latar putih, border 1px lembut, bayangan minimal, padding 16–24px. Hindari kartu di dalam kartu.

### Modal
- Ringkas dan terpusat di desktop; di mobile berbentuk bottom sheet atau layar penuh dengan tombol tutup jelas.
- Latar belakang tetap terlihat redup; `Escape`, klik backdrop, dan tombol tutup menutup modal.
- Fokus berpindah ke modal saat dibuka, terjebak di dalamnya, dan kembali ke pemicu saat ditutup.
- Tidak ada modal bersarang (keputusan lama: prompt picker dan `Apa ini?` tidak boleh modal di dalam modal).

### Dropdown / menu
- Muncul tepat di bawah pemicu, tidak keluar dari viewport (balik arah bila perlu).
- Dapat dioperasikan dengan keyboard (panah, `Enter`, `Escape`), item minimal 44px tinggi di layar sentuh.

### Navigasi
- Desktop: sidebar tetap + topbar. Tablet/mobile: drawer (off-canvas). Detail di `RESPONSIVE.md`.
- Item aktif ditandai warna + bobot teks, bukan warna saja.
- Sidebar riset: Recent menampilkan 5 item secara default; History tidak ada di navigasi utama (keputusan lama, tetap berlaku sampai ada instruksi lain).

### Form
- Label selalu terlihat (bukan hanya placeholder); teks bantu di bawah field; pesan error di dekat field dan dihubungkan dengan `aria-describedby`.
- Ukuran font input minimal 16px agar iOS tidak memperbesar halaman otomatis.
- Gunakan `type`, `inputmode`, dan `autocomplete` yang tepat.
- Tombol kirim menampilkan status loading dan mencegah kirim ganda.

### Animasi
- Pustaka: **GSAP** yang sudah ada. Tidak memperkenalkan Framer Motion/Motion atau pustaka animasi lain.
- Animasi harus menyampaikan hierarki, progres, sebab-akibat, atau perubahan status; jangan menganimasikan semua elemen.
- Durasi singkat (150–400ms), easing deselerasi halus, tanpa bounce sebagai default.
- Wajib menghormati `prefers-reduced-motion` (animasi dimatikan atau diganti perubahan instan).
- Sebelum menulis animasi bermakna, definisikan: elemen, pemicu, state awal/akhir, durasi, easing, stagger, urutan timeline, perilaku ScrollTrigger, perilaku mobile, dan fallback reduced-motion.

### Loading state
- Skeleton atau indikator progres untuk konten yang dimuat; spinner kecil pada tombol.
- Riset AI berjalan asinkron: tampilkan status "sedang diproses" dengan teks (sudah ada poll status; lihat `ARCHITECTURE.md`).
- Hindari layout shift saat konten datang.

### Error state
- Pesan dalam bahasa Indonesia yang jelas, menjelaskan apa yang terjadi dan langkah berikutnya.
- Jangan menampilkan jejak teknis atau detail server kepada pengguna.
- Halaman error (404, 419, 429, 500) memakai tampilan konsisten (`resources/views/errors`).
- Form menyimpan input pengguna saat validasi gagal (kecuali kata sandi).

## 6. Ikon dan ilustrasi

Gunakan SVG inline yang bermakna (buku, jurnal, kutipan, penanda, pencarian, bukti). Gaya stroke dan ukuran konsisten per konteks. Hindari ikon acak dan ikon "sparkle" berlebihan.

## 7. Kepadatan per konteks

- **Halaman publik/landing**: boleh tipografi besar dan komposisi ekspresif, tetap dalam rentang `RESPONSIVE.md`.
- **Area terautentikasi (Workspace, Saved, Profil, Dokumen, Admin)**: produktif dan padat secukupnya. Panduan lama menetapkan ukuran kompak (judul halaman ≈ 30–40px, body ≈ 11–14px). Ini **bertentangan** dengan target body 16px dan H1 48–64px pada standar baru. Lihat bagian 10, butir 5.

## 8. Anti-"AI slop" (gerbang penolakan)

Tolak layar yang: terlihat seperti SaaS AI generik, memakai kartu dekoratif tanpa alasan, memperbesar heading untuk memalsukan hierarki, beranimasi hanya demi tontonan, memakai ikon acak, berisi teks generik, menjadikan bukti sebagai tambahan, hanya mengecilkan desktop untuk mobile, atau menampilkan markdown mentah.

## 9. Kepemilikan CSS

Sebelum menambah berkas CSS atau selector, cari kelas komponen yang sudah ada dan ubah berkas pemiliknya. Hindari lapisan baru bernama "fix/final/round". Lihat `CODE_STYLE.md`.

## 10. Selisih dengan implementasi saat ini

Diverifikasi dari `resources/css/base/variables.css`, `fonts.css`, dan `public/fonts` pada saat dokumen ini ditulis.

| # | Aspek | Standar baru | Implementasi saat ini | Pekerjaan berikutnya |
|---|---|---|---|---|
| 1 | Font | Poppins | `Plus Jakarta Sans` (isi) dan `Source Serif 4` (judul) di-host sendiri; `landing.css` menyebut `"Poppins"` tetapi **tidak ada berkas Poppins** di `public/fonts`, sehingga jatuh ke font sistem | Tambah `woff2` Poppins + `@font-face`, ganti `--font-body`/`--font-display`, hapus font lama bila tidak dipakai |
| 2 | Primer | `#EF5843` | `--bd-orange: #F26B4F` (+ `--bd-orange-deep #D95A42`, `--bd-orange-soft #FCE5DF`) | Ganti token, periksa semua pemakaian nilai literal |
| 3 | Sekunder | `#F7AA35` | Tidak ada token sekunder kuning-emas sebagai peran tetap (panduan lama memakai gold `#D7A84A`) | Tambah token dan tentukan penggunaannya |
| 4 | Aksen gelap | `#170F38` / `#241B52` | `--bd-navy: #17172B`, `--bd-navy-soft: #27273D` (juga diduplikasi di `auth-portal.css`) | Selaraskan nilai, hapus duplikasi token |
| 5 | Ukuran teks area aplikasi | Body ≥ 16px, H1 sesuai skala | Panduan lama kompak (body 11–14px) | **Keputusan produk**: terapkan skala baru ke area aplikasi atau tetapkan pengecualian tertulis untuk area padat |
| 6 | Kontras tombol | Teks putih di oranye | Rasio ≈ 3,4 : 1 | Putuskan: label tebal/besar atau oranye tombol lebih gelap |
| 7 | Hover | Glow oranye halus | Belum diaudit | Audit `components/buttons.css` |
| 8 | Lapisan CSS | Satu pemilik per komponen | Banyak berkas halaman bernuansa tambalan (`research-final-revisions.css`, `research-ux-round2.css`, `workspace-fixes.css`, `bundles/overrides.css`, dll.) | Konsolidasi bertahap, per halaman, dengan tes visual |
| 9 | Token ganda | Satu sumber | Sebagian variabel didefinisikan ulang di CSS halaman | Pusatkan di `base/variables.css` |
