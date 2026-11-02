# BacaDulu Research — Clean Build

Ini adalah source build baru berbasis Laravel 12. Tidak menggunakan Laravel Breeze.
UI dibuat custom dengan arah visual BacaDulu Research.

## Yang sengaja TIDAK disertakan

- `.env`
- `.git`
- `vendor/`
- `node_modules/`
- database production
- API/LLM credentials

Project utama tetap memakai `.env` dan `.git` milik kamu.

## Setelah replace

Pastikan terminal berada di:

`D:\Pkl\bacadulu-research`

Jalankan:

```powershell
composer install
npm install
php artisan optimize:clear
```

## Database

Migration build ini adalah schema baru. Jika database `bacadulu_research` masih berisi schema project lama dan datanya tidak diperlukan, backup terlebih dahulu lalu:

```powershell
php artisan migrate:fresh --seed
```

PERINGATAN: `migrate:fresh` MENGHAPUS tabel di database yang aktif.

Seeder membaca:

- `ADMIN_NAME`
- `ADMIN_EMAIL`
- `ADMIN_PASSWORD`

dari `.env`.

Jika database lama masih harus dipertahankan, JANGAN jalankan `migrate:fresh`.
Gunakan database baru atau lakukan migrasi/data mapping secara terpisah.

## Google OAuth

Isi di `.env`:

```env
GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
GOOGLE_REDIRECT_URI=http://127.0.0.1:8000/auth/google/callback
```

Redirect URI yang didaftarkan di Google Cloud harus sama persis.

## Run

Terminal 1:

```powershell
php artisan serve
```

Terminal 2:

```powershell
npm run dev
```

Buka:

`http://127.0.0.1:8000`

## Scope build ini

Sudah ada:

- custom landing page
- custom navbar/footer
- login/register
- Google OAuth wiring
- admin role
- admin dashboard
- internal library
- document CRUD dasar
- research workspace dasar
- profile
- research tool seed
- prompt template seed
- audit/log-ready schema
- security headers
- responsive mobile UI

Belum diaktifkan sebagai production AI:

- embedding service
- Qdrant
- LLM provider
- citation validator
- queue ingestion
- OTP/SMS
- quota enforcement
- full admin prompt versioning UI

Bagian tersebut sengaja dipisahkan agar fondasi dapat dites sebelum integrasi AI eksternal.
