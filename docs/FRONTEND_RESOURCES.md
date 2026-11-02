# Sumber Referensi Frontend — BacaDulu

Dokumen ini mencatat sumber inspirasi visual/UI yang boleh dijadikan **bahan referensi**. Semuanya adalah referensi, **bukan perintah memasang framework, pustaka, atau aset baru**.

## 1. Aturan umum

1. **Referensi ≠ dependensi.** Menyukai sebuah komponen tidak berarti menambah paket. Stack project tetap: Laravel Blade + Vite + Tailwind CSS 4 + JavaScript + GSAP. React, Vue, Next.js, Framer Motion/Motion, dan pustaka UI lain memerlukan persetujuan eksplisit.
2. **Adaptasi, jangan salin.** Ambil prinsip (komposisi, ritme spasi, perilaku interaksi), lalu tulis ulang memakai token dan komponen BacaDulu (`DESIGN_SYSTEM.md`).
3. **Periksa lisensi sebelum memakai aset apa pun** (kode, ikon, ilustrasi, font, model 3D, gambar). Catat sumber, pemilik, dan jenis lisensi; simpan berkas lisensi di repo bila aset didistribusikan (seperti `public/fonts/OFL-*`). Bila lisensi tidak jelas atau melarang penggunaan komersial/redistribusi, jangan dipakai.
4. **Tanpa pelacakan/CDN pihak ketiga baru.** CSP produksi hanya mengizinkan sumber `'self'`; aset harus di-host sendiri.
5. **Performa dan aksesibilitas tetap menang.** Efek 3D/kaca/animasi berat tidak boleh merusak keterbacaan, kinerja mobile, atau `prefers-reduced-motion`.
6. **Jangan mengarang isi referensi.** Bila sebuah tautan tidak dapat dibuka/diperiksa, catat sebagai "belum diverifikasi".

## 2. Daftar referensi

> URL dan lisensi tiap sumber di bawah **belum diverifikasi** karena brief tugas hanya menyebut nama. Kolom "Tujuan penggunaan" dan "Batas penerapan" adalah **usulan** yang belum dikonfirmasi pemilik produk. Isi kolom "Tautan" dan "Lisensi" saat sudah diperiksa.

| Sumber | Tujuan penggunaan di BacaDulu | Batas penerapan | Tautan | Lisensi |
|---|---|---|---|---|
| **ThreeUI** | Inspirasi komposisi dan komponen antarmuka | Hanya referensi gaya; tidak mengimpor pustakanya | Belum diverifikasi | Belum diverifikasi |
| **KitBitz** | Inspirasi elemen/komponen UI | Hanya referensi; tulis ulang dengan token BacaDulu | Belum diverifikasi | Belum diverifikasi |
| **Create with Swift** | Referensi pola antarmuka dan alur belajar ramah pemula (konteks pendidikan) | Hanya inspirasi UX; bukan alasan memakai Swift/SwiftUI pada project web ini | Belum diverifikasi | Belum diverifikasi |
| **Glass3D** | Inspirasi efek permukaan kaca/3D | Gunakan sangat hemat: panduan BacaDulu **menghindari glassmorphism berlebihan**; tidak untuk area membaca/riset | Belum diverifikasi | Belum diverifikasi |

Catatan untuk Glass3D dan efek serupa: bila dipakai sama sekali, batasi pada satu elemen dekoratif di halaman publik, sediakan fallback tanpa efek untuk perangkat lemah dan `prefers-reduced-motion`, dan jangan menumpuk di atas teks yang harus dibaca.

## 3. Referensi visual terdahulu (konsolidasi)

Dari `archive/bacadulu-context/03_VISUAL_REFERENCE_REGISTRY.md` dan `CONTEXT_MANIFEST.json`: sepuluh referensi Instagram (IG-01 … IG-10) pernah didaftarkan sebagai masukan visual/gerak. Tautannya:

| ID | Jenis | URL |
|---|---|---|
| IG-01 | Reel | https://www.instagram.com/reel/DeebsTrx_3e/ |
| IG-02 | Reel | https://www.instagram.com/reel/DecN_gbx_eD/ |
| IG-03 | Reel | https://www.instagram.com/reel/Dea5G_fP7WX/ |
| IG-04 | Reel | https://www.instagram.com/reel/DedJ2BTD_IJ/ |
| IG-05 | Post | https://www.instagram.com/p/DebnbytmvVt/ |
| IG-06 | Post | https://www.instagram.com/p/DeVERjTgMRE/ |
| IG-07 | Post | https://www.instagram.com/p/DeE0-thCDFp/ |
| IG-08 | Reel | https://www.instagram.com/reel/DdbLJv1jAI0/ |
| IG-09 | Post | https://www.instagram.com/p/DeHgoP8kxyV/ |
| IG-10 | Post | https://www.instagram.com/p/Dd9IBiPFmI_/ |

Isi visual tautan tersebut **tidak pernah dapat diperiksa secara otomatis** (Instagram tidak menampilkan konten yang dapat dibaca), sehingga tidak ada deskripsi per tautan yang dikarang. Untuk reproduksi tingkat piksel, gunakan screenshot atau tangkapan video yang diberikan langsung.

## 4. Cara memakai referensi dalam pekerjaan UI

1. Analisis dahulu (intake): tujuan halaman, pengguna, aksi utama, hierarki, tata letak, tipografi, warna, spasi, radius, bayangan, ikon, state, implikasi responsif, peluang gerak.
2. Kritik: apakah terlalu dekoratif, terlalu banyak kartu, CTA tidak jelas, tidak realistis di mobile?
3. Nilai kelayakan: kompleksitas, risiko pemeliharaan, perubahan backend, persentase komponen yang dapat dipakai ulang.
4. Minta persetujuan sebelum implementasi bila tugas dimulai sebagai review.
5. Setelah implementasi, verifikasi di browser (`TESTING.md`).
