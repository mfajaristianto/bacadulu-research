# Standar Responsive — BacaDulu

Berlaku untuk **seluruh halaman**: publik (landing), autentikasi, onboarding, dashboard/workspace, Saved, History, Library, profil, dokumen, admin, dan halaman error.

> Dokumen sumber "BacaDulu — Project Documentation Standard" tidak tersedia di workspace. Isi di bawah disusun dari spesifikasi pada brief tugas dan dari praktik yang sudah ada di project. Nilai di luar brief diberi label **usulan**.

## 1. Breakpoint

| Nama | Rentang | Viewport uji utama |
|---|---|---|
| Mobile | maks. 767px | 360, 390, 430 |
| Tablet | 768–1024px | 768, 1024 |
| Desktop | min. 1025px | 1280 |
| Large Desktop | min. 1440px | 1440 |

Pendekatan: **mobile-first**. CSS dasar untuk layar kecil, lalu `min-width` untuk memperluas.

```css
/* dasar = mobile */
.panel { padding: 16px; }

@media (min-width: 768px)  { .panel { padding: 24px; } }
@media (min-width: 1025px) { .panel { padding: 32px; } }
@media (min-width: 1440px) { .panel { padding: 32px; max-width: 1280px; } }
```

Catatan: rentang tablet berakhir di 1024px dan desktop mulai di 1025px, sehingga tidak ada celah atau tumpang tindih.

## 2. Grid

| Layar | Kolom | Gutter | Margin sisi (usulan) |
|---|---|---|---|
| Desktop | 12 | 24px | 32–48px |
| Tablet | 8 | 16–24px | 24px |
| Mobile | 4 | 16px | 16px |

Tailwind CSS 4 tersedia; kelas utilitas boleh dipakai selama konsisten dengan pola berkas yang sudah ada.

### Lebar container vs lebar area konten (mencegah overflow)

Bedakan **lebar total container** (termasuk padding) dari **lebar area konten** (di dalam padding). Gunakan `box-sizing: border-box` secara global dan jangan menambahkan padding pada elemen yang sudah `width: 100%` tanpa `border-box`.

```css
*, *::before, *::after { box-sizing: border-box; }

.container {
  width: 100%;                 /* total, termasuk padding karena border-box */
  max-width: 1280px;           /* batas lebar total container */
  margin-inline: auto;
  padding-inline: 16px;        /* area konten = total - 32px */
}

@media (min-width: 768px)  { .container { padding-inline: 24px; } }
@media (min-width: 1025px) { .container { padding-inline: 32px; } }

.grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr)); /* minmax(0,1fr): cegah item melebihi kolom */
  gap: 16px;
}
@media (min-width: 768px)  { .grid { grid-template-columns: repeat(8, minmax(0, 1fr));  gap: 20px; } }
@media (min-width: 1025px) { .grid { grid-template-columns: repeat(12, minmax(0, 1fr)); gap: 24px; } }
```

Hindari: `width: 100vw` pada elemen di dalam container (`100vw` menyertakan lebar scrollbar dan menyebabkan overflow), `width: 100%` + `padding` tanpa `border-box`, margin negatif tanpa pembungkus `overflow: clip`, dan nilai `px` tetap pada lebar kolom.

## 3. Tipografi fluid

| Level | Desktop | Tablet | Mobile |
|---|---|---|---|
| H1 | 48–64px | 36–48px | 28–32px |
| Body utama | ≥ 16px | ≥ 16px | ≥ 16px |

Gunakan `clamp()` agar transisi halus tanpa lompatan:

```css
h1 { font-size: clamp(1.75rem, 1.2rem + 2.4vw, 4rem); line-height: 1.15; } /* 28px → 64px */
body { font-size: 1rem; line-height: 1.6; }                                  /* 16px */
```

Aturan: jangan memakai satuan `vw` saja (tidak ikut zoom pengguna); selalu kombinasikan dengan `rem` di dalam `clamp()`. Teks harus tetap terbaca pada zoom 200%.

> **Konflik yang perlu diputuskan**: H1 48–64px dan body 16px adalah standar baru, sedangkan panduan aplikasi lama menetapkan antarmuka terautentikasi yang kompak (judul ≈ 30–40px, body ≈ 11–14px) dan `CLAUDE.md` melarang heading raksasa. Sebelum implementasi, putuskan apakah rentang H1 ini berlaku untuk halaman publik saja atau juga area aplikasi. Lihat `DESIGN_SYSTEM.md` bagian 10.

## 4. Spasi

Skala: **8, 16, 24, 32, 48, 64px**. Gunakan hanya nilai ini untuk padding, margin, dan gap antar-bagian; nilai 4px boleh dipakai untuk jarak mikro di dalam komponen (usulan).

## 5. Target sentuh

- Minimum praktis **44×44px**; target yang dianjurkan **48×48px** untuk banyak kontrol mobile.
- Pedoman platform memakai satuan berbeda: Apple (HIG) menyebut 44 **pt**, Material Design menyebut 48 **dp**. Satuan pt dan dp mengikuti kerapatan layar perangkat dan **tidak selalu identik dengan CSS px**. Pada browser mobile, 1 CSS px mendekati 1 dp pada layout viewport standar, namun jangan menganggap konversi eksak; ukur pada perangkat nyata.
- Area klik boleh lebih besar dari tampilan visual (padding atau pseudo-element), dengan jarak antar-target minimal 8px agar tidak salah tekan.

## 6. Navigasi responsif

| Layar | Pola |
|---|---|
| Desktop | Topbar + sidebar tetap (area aplikasi); navbar horizontal (halaman publik) |
| Tablet | Sidebar menjadi drawer yang dibuka tombol; navbar ringkas |
| Mobile | Drawer/off-canvas; navbar publik menjadi menu hamburger layar penuh atau sheet |

Syarat: tombol menu berlabel aksesibel (`aria-label`, `aria-expanded`, `aria-controls`); drawer terbuka menjebak fokus, menutup dengan `Escape` dan klik backdrop, mengunci scroll body, dan mengembalikan fokus ke pemicu. Sidebar riset mempertahankan perilaku lama: Recent 5 item + Show more/less.

## 7. Sidebar

- Desktop: lebar tetap (ikuti referensi desain yang disetujui), tidak menyusut di bawah kebutuhan label.
- Tablet/mobile: tersembunyi di luar layar (`transform: translateX(-100%)`), bukan `display: none` agar transisi dan aksesibilitas terjaga; pastikan elemen tersembunyi tidak dapat difokuskan (`inert` atau `visibility: hidden` setelah transisi).
- Konten utama tidak boleh bergeser atau tertimpa tanpa overlay.

## 8. Reflow layout

- Grid multi-kolom turun menjadi satu kolom di mobile; panel samping (right rail) menumpuk di bawah konten utama di tablet.
- Tabel lebar: ubah menjadi daftar kartu per baris di mobile, atau bungkus dengan wadah `overflow-x: auto` **yang disengaja** dan diberi indikator geser. Jangan membiarkan tabel melebarkan halaman.
- Toolbar: aksi sekunder masuk ke menu "lainnya"; satu aksi primer tetap terlihat.
- Bagan: tetap menyertakan label teks/nilai; ubah orientasi atau sederhanakan seri di layar kecil.

## 9. Prioritas konten

Di layar kecil urutkan berdasarkan kebutuhan tugas, bukan urutan desktop:

- Workspace: tool aktif → pertanyaan → jawaban → bukti/sumber → aksi berikutnya → composer.
- Saved: filter/cari → daftar → ringkasan → aktivitas.
- Profil: hero → statistik → kartu informasi.
- Dokumen: konteks riset → pengaturan → penjelasan sitasi → review → konfirmasi → unduh.

Konten sekunder boleh dilipat (accordion) atau dipindah ke halaman lain, bukan dipotong.

## 10. Mouse, sentuh, dan keyboard

- Jangan menggantungkan fungsi pada `:hover`; sediakan padanan untuk sentuh (tap) dan keyboard (`:focus-visible`).
- Gunakan `@media (hover: hover) and (pointer: fine)` untuk efek hover (mis. glow oranye) agar tidak "menempel" di layar sentuh.
- Semua kontrol dapat dijangkau dengan `Tab`, urutan fokus logis, indikator fokus terlihat (jangan `outline: none` tanpa pengganti).
- Hormati `prefers-reduced-motion`.

## 11. Pencegahan overflow

Daftar periksa:

- `box-sizing: border-box` global; anak flex/grid memakai `min-width: 0`.
- Teks panjang: `overflow-wrap: anywhere` pada judul/URL/nama berkas.
- Gambar/video: `max-width: 100%; height: auto`.
- Hindari `100vw` dan `100vh` untuk tinggi penuh di mobile; gunakan `100dvh` atau `min-height` dengan fallback (bilah alamat mobile mengubah tinggi viewport).
- Tidak ada elemen `position: absolute/fixed` dengan lebar tetap yang melebihi viewport.
- Dropdown/popover dibatasi `max-width: calc(100vw - 32px)` dan membalik arah bila hampir keluar layar.
- Hindari scroll bersarang yang tidak disengaja (dua area scroll vertikal yang saling tumpuk).

Uji cepat di konsol browser:

```js
document.documentElement.scrollWidth > document.documentElement.clientWidth // harus false
```

## 12. Modal responsif

- Desktop: dialog terpusat, lebar maksimum ringkas (bukan hampir layar penuh).
- Mobile: bottom sheet atau layar penuh dengan header tetap (judul + tombol tutup) dan area isi yang dapat digulir; tombol aksi utama selalu terlihat.
- Tinggi memakai `max-height: 100dvh` dikurangi ruang aman; gunakan `env(safe-area-inset-*)` untuk perangkat dengan notch.
- Kunci scroll body saat modal terbuka; pulihkan saat ditutup.

## 13. Keyboard mobile

- Saat keyboard virtual muncul, viewport visual mengecil: field aktif harus tetap terlihat (`scrollIntoView({ block: 'center' })` bila perlu) dan tombol kirim tidak tertutup.
- Composer Workspace menempel di bawah memakai `dvh`/`visualViewport`, bukan `vh` tetap.
- Input font ≥ 16px untuk mencegah zoom otomatis iOS.
- Pakai `inputmode`, `type`, `autocomplete`, dan `enterkeyhint` yang sesuai.

## 14. Pengujian

Uji pada viewport: **360, 390, 430, 768, 1024, 1280, 1440px**, orientasi potret dan lanskap untuk mobile/tablet.

Yang diperiksa tiap halaman: tidak ada overflow horizontal, tidak ada teks terpotong, kontrol ≥ 44px, drawer/modal berfungsi, tabel terbaca, zoom 200% tetap layak pakai, keyboard dan fokus, reduced motion, tidak ada error konsol atau aset gagal dimuat. Prosedur dan alat ada di `TESTING.md`.

## 15. Status penerapan

**Belum diverifikasi** pada dokumen ini: kepatuhan CSS saat ini terhadap breakpoint 767/768/1024/1025/1440 dan skala spasi. Audit visual per halaman termasuk pekerjaan berikutnya (lihat `TESTING.md`, bagian responsive).
