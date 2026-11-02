# BacaDulu Research

BacaDulu Research adalah **workspace riset berbasis AI yang mengutamakan bukti** untuk mahasiswa dan administrator. Pengguna mengajukan pertanyaan riset, mendapat jawaban yang ditelusurkan ke sumber di pustaka internal, menyimpan hasilnya, lalu mengubahnya menjadi dokumen akademik (DOCX/PDF) dengan gaya sitasi APA 7, IEEE, atau Harvard.

```text
Pertanyaan → Sumber → Bukti → Sintesis → Insight → Dokumen
```

## Fitur utama

- **Research Workspace** dengan pilihan Research Tool/prompt, riwayat percakapan, bukti dan sitasi.
- **Saved**: simpan, cari, favoritkan, dan lanjutkan hasil riset.
- **Pembuatan dokumen** akademik (11 tipe, 3 gaya sitasi) dengan unduhan DOCX dan PDF.
- **Profil** universal (pendidikan, perusahaan, pemerintah, nirlaba, independen).
- **Admin Console**: pustaka sumber, prompt dan versi, AI tools, glosarium, template dokumen, pengguna, audit, pengaturan, Source Sync.
- Autentikasi (Google OAuth tersedia tetapi nonaktif secara default), rate limiting, dan header keamanan.

## Teknologi

Laravel 12 (PHP ≥ 8.2) · Blade · Vite 6 · Tailwind CSS 4 · JavaScript · GSAP · MySQL · antrean database · Sentry.

## Mulai cepat (pengembangan lokal)

```powershell
composer install
npm install
copy .env.example .env     # isi nilai lokal; jangan commit .env
php artisan key:generate
php artisan serve          # terminal 1
npm run dev                # terminal 2
php artisan queue:work --queue=research,ingestion   # terminal 3 (agar riset AI berjalan)
```

Panduan lengkap, termasuk database, build, dan deployment: [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md).

> Jangan menjalankan `php artisan migrate:fresh` pada basis data yang berisi data.

## Dokumentasi

Seluruh dokumentasi teknis (dalam bahasa Indonesia) ada di **[docs/](docs/README.md)**:

[PRD](docs/PRD.md) · [Design System](docs/DESIGN_SYSTEM.md) · [Responsive](docs/RESPONSIVE.md) · [Arsitektur](docs/ARCHITECTURE.md) · [Keamanan](docs/SECURITY.md) · [Standar Kode](docs/CODE_STYLE.md) · [Database](docs/DATABASE.md) · [API](docs/API.md) · [Pengujian](docs/TESTING.md) · [Referensi Frontend](docs/FRONTEND_RESOURCES.md) · [Deployment](docs/DEPLOYMENT.md)

Aturan kerja untuk asisten pengodean: [CLAUDE.md](CLAUDE.md), [AGENTS.md](AGENTS.md), agent di `.claude/agents/`, dan skill di `.claude/skills/`.

## Pengujian

```powershell
php artisan test
```

Status baseline dan keterbatasan tes saat ini: [docs/TESTING.md](docs/TESTING.md).

## Lisensi

Framework Laravel berlisensi [MIT](https://opensource.org/licenses/MIT). Lisensi kode aplikasi BacaDulu: **belum ditetapkan di repositori** (`composer.json` menyatakan MIT; konfirmasi dengan pemilik project).
