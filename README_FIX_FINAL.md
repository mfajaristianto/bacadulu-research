# BacaDulu Research — Fix Final

Build ini dibuat sebagai drop-in replacement dari source `bacadulu-research-final(1).zip`.

## Fokus perbaikan

- UI/UX dirapikan menjadi editorial research workspace dengan palette navy, teal, coral, dan warm canvas.
- Workspace tetap memiliki navigasi di mobile; conversation history menjadi slide-over.
- Motion memakai Web Animations API + CSS transition sehingga tidak membutuhkan CDN GSAP.
- Avatar user disimpan di `public/uploads/profile-avatars`; tidak bergantung pada `public/storage` symlink.
- Avatar Google, local upload, preview, dan initials fallback didukung.
- Source `restricted` tidak dapat dibrowse atau dipakai user research.
- Source dari halaman Library dapat dibawa langsung ke Workspace lewat context source.
- User non-active/suspended diblokir pada login dan request authenticated berikutnya.
- Seeder tidak memiliki default password.
- Admin audit identity tidak lagi mengambil `auth()->id()` dari normal-user session.
- CSP/HSTS, rate limiting, input bounds, dan sanitized OAuth logging diperketat.
- CSS dipisah menjadi base / components / pages.
- Production bundle sudah tersedia di `public/build`, jadi hasil copy tidak tergantung Vite dev server.

## Robocopy

Pertahankan `.env` milik project tujuan. Source ZIP ini memang tidak berisi `.env`.

Contoh dari folder hasil extract:

```powershell
robocopy . D:\Pkl\bacadulu-research /E /COPY:DAT /DCOPY:T /R:2 /W:2 /XD .git node_modules /XF .env
```

`/E` dipilih agar file `.env` dan `.git` project tujuan tidak terhapus. Jangan gunakan `/MIR` jika source hasil extract tidak membawa `.env` production Anda.

Jika project tujuan pernah menjalankan `config:cache`, `route:cache`, atau `optimize`, jalankan setelah copy:

```powershell
php artisan optimize:clear
```

Tidak diperlukan `php artisan storage:link` untuk foto profil pada build ini.

## Production env yang perlu dipertahankan

```dotenv
APP_ENV=production
APP_DEBUG=false
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
```

Email verification untuk local account dapat diaktifkan setelah mailer siap:

```dotenv
RESEARCH_REQUIRE_EMAIL_VERIFICATION=true
```

Google OAuth account ditandai verified berdasarkan callback provider.
