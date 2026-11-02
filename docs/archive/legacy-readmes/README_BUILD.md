# BacaDulu Research build

Build ini mengikuti schema BacaDulu Research yang sudah ada dan tidak membawa `.env`, `.git`, atau database production.

## Important

- Pertahankan `.env` milik project yang sudah bekerja.
- Jangan menjalankan `migrate:fresh` pada database yang berisi data.
- `/dashboard` dipertahankan sebagai compatibility redirect menuju `/workspace`.
- Admin path dikonfigurasi lewat `BACADULU_ADMIN_PATH` dengan default `/panel-adminbaca-research`.
- Admin login membaca `ADMIN_EMAIL` dan `ADMIN_PASSWORD` dari `.env`.
- AI execution masih membuat run berstatus `queued`; provider/orchestrator AI eksternal tidak di-hard-code.
- User retrieval hanya menggunakan document dengan `status=published` dan `visibility=public`.
- Avatar baru disimpan pada `public/uploads/profile-avatars` sehingga tidak membutuhkan symlink `public/storage`.
- Production asset bundle disertakan di `public/build`.

## Recommended after source replacement

```powershell
php artisan optimize:clear
```

Jalankan migration hanya bila memang ada migration baru yang belum tercatat dan database target sudah dibackup.
