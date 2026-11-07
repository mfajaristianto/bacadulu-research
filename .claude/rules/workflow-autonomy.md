# BacaDulu Research — Workflow Autonomy

Aturan utama cara kerja sehari-hari di project ini.

Prioritas bila aturan bertabrakan: (1) instruksi eksplisit pengguna di percakapan → (2) daftar "Wajib minta persetujuan" di file ini dan aturan keamanan `CLAUDE.md` → (3) file ini → (4) bagian lain `CLAUDE.md`, `docs/*.md`, agent, skill, plugin. Aturan Markdown tidak menggantikan sistem izin tool (`.claude/settings*.json`).

## Prinsip
Selesaikan task rutin secara mandiri bila scope pengguna sudah jelas. Jangan meminta persetujuan kedua untuk keputusan yang sudah tercakup dalam permintaan pengguna.

## Mode diskusi (Discussion Gate)
Pemicu: "jelasin dulu", "bedah dulu", "audit", "kasih konsep", "menurut lu gimana", "jangan implementasi dulu", "jangan ubah kode", atau permintaan analisis/review/perbandingan.
- Baca project seperlunya, analisis, beri rekomendasi.
- JANGAN mengedit source code dan JANGAN menjalankan perubahan destruktif.
- Diskusi bukan izin implementasi. Berhenti setelah rekomendasi.

## Mode implementasi (Implementation Gate)
Pemicu: "kerjain", "implementasikan", "perbaiki", "redesign", "buat", "ubah", "lanjutkan implementasinya".

Langsung jalankan tanpa approval tambahan:
1. audit file terkait;
2. pahami dependency;
3. pilih solusi teknis (Design Gate `CLAUDE.md` dijalankan internal);
4. gunakan skill existing yang relevan;
5. edit file dalam scope;
6. jalankan test;
7. jalankan Playwright bila task UI;
8. perbaiki bug dalam scope;
9. build;
10. final review;
11. laporkan hasil.

## Keputusan desain
Bila pengguna sudah meminta redesign/implementasi UI, tentukan sendiri berdasarkan design system existing (`docs/DESIGN_SYSTEM.md`) dan brief: spacing, layout, breakpoint, ukuran card, animation timing, icon placement, visual hierarchy, hover/focus behavior, responsive treatment. Jangan meminta approval untuk tiap keputusan kecil. Perubahan token/font global tetap mengikuti "Catatan konflik" di `CLAUDE.md`.

## Skill / tool
- Skill/plugin/tool relevan yang SUDAH tersedia: gunakan otomatis, pilih yang paling relevan, jangan jalankan banyak skill yang tumpang tindih.
- Jangan bertanya "boleh pakai Playwright?", "skill mana yang dipakai?", "perlu frontend-design?".
- Skill/plugin/package yang belum tersedia dan memang perlu dipasang: minta izin sebelum instalasi.

## Bug dalam scope
- Bug yang disebabkan perubahan yang sedang dibuat, atau berada tepat di bagian yang sedang dikerjakan: perbaiki otomatis dan test ulang.
- Bug di halaman/fitur lain: catat di laporan, jangan perluas scope.

## Playwright
- Dipakai sebagai checkpoint: implementasi section/batch → Playwright review → fix → final responsive QA (desktop dan mobile, console, interaksi form/dropdown/modal/tombol).
- Jangan membuka browser setelah setiap deklarasi CSS, dan jangan memenuhi laporan dengan detail tiap call.
- Jangan melakukan aksi destruktif (hapus/ubah data nyata) atau memakai kredensial rahasia lewat browser tanpa otorisasi.
- Bila browser gagal: diagnosis, coba alternatif aman. Jangan mengklaim visual QA atau membuat screenshot/laporan palsu bila browser tidak berhasil dijalankan.

## Perintah aman
Boleh dijalankan tanpa bertanya bila sesuai scope:
- `git status`, `git diff`, `git log`;
- `npm run build`;
- `php artisan test` (SQLite in-memory; bedakan kegagalan baseline `docs/TESTING.md` dari kegagalan baru);
- Artisan inspeksi/cache yang non-destruktif: `route:list`, `view:clear`, `config:clear`, `cache:clear`;
- Playwright/browser QA;
- lint/check yang sudah ada di project.

Jangan menganggap semua `php artisan *` aman (contoh tidak aman: `migrate*`, `db:wipe`, `db:seed`, `tinker` yang menulis data, `queue:flush`).

## Wajib minta persetujuan (Critical Gate)
Berhenti sebelum:
- `migrate:fresh`, `migrate:reset`, `migrate:rollback`, `db:wipe`, drop/reset database;
- penghapusan data massal;
- force push, `reset --hard`, rewrite history Git, serta commit/push yang tidak diminta;
- deploy production, perubahan DNS, perubahan OAuth production;
- rotasi credential/secret, perubahan `.env`;
- perubahan schema database besar yang belum disetujui;
- breaking change kontrak API/route;
- dependency/package baru yang signifikan, `composer update`, `npm update`, instalasi Laravel Boost;
- penghapusan fitur existing;
- perubahan besar di luar scope, atau overwrite file penting yang tidak terkait.

Cara bertanya: SATU pertanyaan berisi risiko, rekomendasi, dan alternatif. Bila bagian kritis bisa dipisahkan, selesaikan bagian aman dulu lalu laporkan blocker-nya.

## Anti pertanyaan berulang
- Setelah pengguna memberi satu keputusan besar, jangan pecah menjadi banyak pertanyaan kecil.
- Jangan meminta informasi yang sudah ada di percakapan, file project, dokumentasi, atau source code.
- Dua alternatif teknis yang sama-sama aman: pilih yang maintenance risk-nya lebih rendah, sebut alasannya di laporan.

Contoh — pengguna: "Redesign Beranda Admin, sidebar jangan disentuh." Jangan lalu bertanya boleh ubah metric card, edit CSS, pakai SVG, jalankan Playwright, atau perbaiki responsive. Semua itu sudah termasuk task; yang dilarang hanya sidebar.

## Kualitas implementasi
Ubah file sesedikit mungkin, pakai komponen existing, hindari komponen duplikat dan override CSS bertumpuk/`!important`, jangan buat data dummy yang disamarkan sebagai data asli, jangan ubah backend hanya demi masalah visual, jangan merusak fitur existing.

## Hasil akhir
Laporan ringkas dalam Bahasa Indonesia:
1. apa yang ditemukan;
2. apa yang diubah;
3. file yang diubah;
4. hasil test;
5. masalah yang masih tersisa.

Jangan membuat laporan konsultansi panjang untuk task sederhana, dan jangan menutup dengan pertanyaan lanjutan bila task sudah tuntas.
