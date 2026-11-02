# Setup dan Deployment — BacaDulu Research

Menggabungkan panduan setup lama yang masih relevan (`archive/legacy-readmes/README_BACADULU_SETUP.md`, `README_BUILD.md`, `README_FIX_FINAL.md`, `archive/bacadulu-context/08_SETUP_BUILD_RECOVERY.md`, `archive/legacy-readmes/INSTALL_CLAUDE_TEAM.md`) dengan fakta konfigurasi saat ini.

> Jangan menyalin isi `.env` (kredensial/kunci) ke dokumen, tiket, atau paket ZIP. Nama variabel boleh dirujuk; nilainya tidak.

## 1. Prasyarat

| Komponen | Kebutuhan |
|---|---|
| PHP | ^8.2 (`composer.json`); lingkungan lokal saat ini PHP 8.5.2 |
| Composer | 2.x |
| Node.js + NPM | Versi yang kompatibel dengan Vite 6 (dikunci di `package-lock.json`) |
| Database | MySQL (produksi/dev); SQLite in-memory hanya untuk tes |
| Opsional | `pdftotext` (ekstraksi PDF lebih baik; ada fallback `smalot/pdfparser`) |

Ekstensi PHP umum Laravel (mbstring, openssl, pdo_mysql, fileinfo, xml, zip, gd/imagick bila dibutuhkan renderer) harus aktif. **Belum diverifikasi**: daftar ekstensi minimum untuk `DocxRenderer`/`PdfRenderer`.

## 2. Pengembangan lokal

```powershell
composer install
npm install
copy .env.example .env      # lalu isi nilai lokal; JANGAN commit .env
php artisan key:generate
php artisan optimize:clear
```

Siapkan basis data MySQL kosong dan isi `DB_*` di `.env`. Lalu, hanya bila perlu dan setelah memahami dampaknya:

```powershell
php artisan migrate         # menerapkan migrasi baru; BUKAN migrate:fresh
php artisan db:seed         # butuh ADMIN_EMAIL + ADMIN_PASSWORD di .env (lihat DATABASE.md bagian 5)
```

Menjalankan (terminal terpisah):

```powershell
php artisan serve                                   # http://127.0.0.1:8000
npm run dev                                         # Vite dev server (hot file: storage/framework/vite.hot)
php artisan queue:work --queue=research,ingestion   # wajib agar riset AI & ingestion PDF berjalan
```

Tanpa worker antrean, run riset tetap berstatus `queued`. Dengan `AI_PROVIDER=mock` (default) tidak ada panggilan jaringan.

## 3. Konfigurasi environment

Variabel penting (nama saja; lihat `.env.example`):

| Kelompok | Variabel |
|---|---|
| Aplikasi | `APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_DEBUG`, `APP_URL` |
| Database | `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` |
| Sesi | `SESSION_DRIVER`, `SESSION_LIFETIME`, `SESSION_SECURE_COOKIE`, `SESSION_HTTP_ONLY`, `SESSION_SAME_SITE` |
| Admin | `BACADULU_ADMIN_PATH`, `ADMIN_NAME`, `ADMIN_EMAIL`, `ADMIN_PASSWORD_HASH` (disarankan), `ADMIN_PASSWORD` (fallback lama) |
| Riset | `RESEARCH_PROFILE_GATE`, `RESEARCH_REQUIRE_EMAIL_VERIFICATION`, `RESEARCH_GOOGLE_AUTH_ENABLED` |
| AI | `AI_PROVIDER`, `AI_MODEL`, `AI_TIMEOUT`, `AI_MAX_OUTPUT_TOKENS`, `AI_CONTEXT_MESSAGES`, `OPENAI_API_KEY`, `OPENAI_BASE_URL` |
| Antrean | `QUEUE_CONNECTION`, `RESEARCH_QUEUE_NAME`, `RESEARCH_INGESTION_QUEUE_NAME`, `RESEARCH_LIVE_REFRESH_SECONDS` |
| Source Sync | `SOURCE_SYNC_PROVIDER`, `SOURCE_SYNC_BACADULU_*` |
| Google OAuth | `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI` |
| Surel | `MAIL_MAILER`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` |
| Monitoring | `SENTRY_LARAVEL_DSN`, `SENTRY_ENVIRONMENT`, `SENTRY_RELEASE`, sampel/PII |

Membuat hash kata sandi admin (disarankan, tempel dengan **tanda kutip tunggal** karena hash mengandung `$`):

```powershell
php artisan research:admin-password-hash
php artisan config:clear
```

Nilai produksi yang perlu dipertahankan: `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`, `SESSION_SAME_SITE=lax`. Aktifkan `RESEARCH_REQUIRE_EMAIL_VERIFICATION=true` setelah mailer siap. Google OAuth: Redirect URI di Google Cloud harus sama persis dengan `GOOGLE_REDIRECT_URI`.

## 4. Build aset

```powershell
npm ci            # lebih disukai di server/CI (memakai package-lock.json)
npm run build     # menghasilkan public/build
```

`public/build` ada di `.gitignore`; **build harus dijalankan di setiap lingkungan** atau hasil build diunggah sebagai bagian rilis. Dokumen lama yang menyebut "production bundle disertakan di `public/build`" merujuk ke paket ZIP, bukan repositori Git.

## 5. Deployment produksi

Urutan aman:

1. **Backup** basis data dan `storage/app/private` + `public/uploads` (lihat `DATABASE.md` bagian 10).
2. Aktifkan mode pemeliharaan bila perlu: `php artisan down`.
3. Ambil kode rilis (Git tag/commit), **tanpa menimpa `.env`**.
4. `composer install --no-dev --optimize-autoloader`
5. `npm ci && npm run build` (atau unggah `public/build` hasil build lokal).
6. `php artisan migrate --force` hanya bila ada migrasi baru dan backup sudah dibuat.
7. Cache: `php artisan config:cache && php artisan route:cache && php artisan view:cache`.
8. Restart worker antrean (`php artisan queue:restart`).
9. `php artisan up`.
10. Verifikasi (bagian 9).

Jangan menjalankan `migrate:fresh`, `composer update`, atau `npm update` pada produksi.

### Proksi dan HTTPS
`bootstrap/app.php` memakai `trustProxies(at: '*')` (dibuat untuk ngrok). Di produksi, **persempit** ke IP/CIDR proksi tepercaya agar `X-Forwarded-For` tidak dapat dipalsukan (lihat `SECURITY.md` temuan 2). Pastikan server meneruskan `X-Forwarded-Proto` dan memaksa HTTPS.

## 6. cPanel / hosting bersama

> Belum diverifikasi terhadap hosting yang dipakai; berikut panduan umum Laravel di cPanel.

- Arahkan **document root domain ke folder `public/`**. Bila tidak memungkinkan, letakkan aplikasi di luar `public_html` dan hubungkan isi `public/` (jangan membiarkan `.env`, `app/`, `storage/`, `vendor/` dapat diakses web).
- Pilih versi PHP ≥ 8.2 dan aktifkan ekstensi yang dibutuhkan lewat *Select PHP Version*.
- Jalankan `composer install` dan build aset melalui terminal cPanel (atau unggah `vendor/` dan `public/build` hasil build lokal).
- Antrean: tidak ada proses daemon di hosting bersama. Gunakan Cron Job tiap menit:

```text
* * * * * cd /home/USER/aplikasi && /usr/local/bin/php artisan queue:work --queue=research,ingestion --stop-when-empty --max-time=55 >> /dev/null 2>&1
```

  (Sesuaikan path PHP.) Bila memungkinkan, pakai worker persisten (Supervisor) untuk latensi lebih rendah. Timeout job riset 180 detik: pastikan batas eksekusi PHP CLI memadai.
- Izin tulis: `storage/` dan `bootstrap/cache/` harus dapat ditulis web user.

## 7. Cache Laravel

| Tujuan | Perintah |
|---|---|
| Bersihkan semua cache | `php artisan optimize:clear` |
| Cache untuk produksi | `php artisan config:cache`, `route:cache`, `view:cache` |
| Setelah ubah `.env` | `php artisan config:clear` (atau `config:cache` ulang) |

Setelah menyalin file dari paket lain, selalu `php artisan optimize:clear`.

## 8. Storage

| Data | Lokasi | Catatan |
|---|---|---|
| PDF sumber (privat) | `storage/app/private/research-sources` | Disk `research_sources`; jangan dipublikasikan |
| Keluaran DOCX/PDF (privat) | `storage/app/private/research-outputs` | Disk `research_outputs`; diunduh lewat route terautentikasi |
| Avatar | `public/uploads/profile-avatars` | Tidak butuh `php artisan storage:link` |
| Log | `storage/logs` | Jangan menyertakan dalam paket distribusi |

Pertahankan folder ini saat penggantian kode. Saat menyalin dengan `robocopy`, jangan memakai `/MIR`; kecualikan `.git`, `node_modules`, `vendor`, `storage\logs`, cache/sesi, `public\uploads`, dan `.env`:

```powershell
robocopy <sumber> D:\Pkl\bacadulu-research /E /COPY:DAT /DCOPY:T /R:2 /W:2 /XD .git node_modules vendor storage\logs storage\framework\sessions storage\framework\cache storage\framework\views public\uploads /XF .env database.sqlite *.log
```

## 9. Verifikasi setelah deployment

- `GET /up` → 200.
- Halaman landing, login, register terbuka tanpa error konsol/aset hilang (font, CSS, JS).
- Login pengguna biasa → workspace; kirim riset uji → status berpindah dari `queued` (worker hidup) ke `completed`/status terminal.
- Simpan hasil → muncul di Saved; buat dokumen → unduh DOCX dan PDF.
- Login admin di `/{BACADULU_ADMIN_PATH}`; unggah sumber uji → status ingestion berjalan.
- Header keamanan ada (CSP, HSTS di HTTPS, nosniff); cookie sesi `Secure` + `HttpOnly`.
- `storage/logs` bebas error baru; Sentry menerima event uji (bila dikonfigurasi).
- Jalankan checklist `TESTING.md` bagian 9 dan 20 pemeriksaan `SECURITY.md` bagian 3.

## 10. Rollback

1. Aktifkan `php artisan down`.
2. Kembalikan kode ke rilis sebelumnya: `git checkout <tag/commit>` (atau pulihkan folder cadangan). Untuk satu berkas: `git restore <path>`.
3. `composer install --no-dev --optimize-autoloader`, `npm ci && npm run build`, `php artisan optimize:clear`.
4. Bila migrasi baru sudah dijalankan dan harus dibatalkan, gunakan `php artisan migrate:rollback --step=N` hanya jika `down()` migrasi tersebut aman; jika tidak, **pulihkan dari backup basis data**.
5. `php artisan queue:restart`, `php artisan up`, ulangi verifikasi.

Sebelum penggantian UI atau kode besar, buat checkpoint Git:

```powershell
git status
git add .
git commit -m "checkpoint sebelum perubahan"
git diff
```

## 11. Setup Claude Code untuk project ini

Dari `archive/legacy-readmes/INSTALL_CLAUDE_TEAM.md`:

- `CLAUDE.md` di root memuat blok `<laravel-boost-guidelines>` + aturan BacaDulu; `AGENTS.md` memuat blok Boost yang sama (bootstrap).
- Tujuh agent project ada di `.claude/agents/` (`project-lead`, `design-critic`, `frontend-architect`, `motion-director`, `implementation-engineer`, `visual-qa`, `maintenance-reviewer`); skill project di `.claude/skills/` (mis. `token-saver`).
- Setelah menyalin `.claude/`, restart Claude Code lalu verifikasi dengan `/plugin`, `/mcp`, dan minta daftar agent project.
- Plugin yang disarankan: frontend-design, playwright, context7, claude-code-setup, code-review, code-simplifier, security-guidance, chrome-devtools, playground.
- Konektor desain: gunakan konektor Figma yang sudah terautentikasi; Canva hanya bila sumber desain Canva dipakai.
- Pembersihan MCP: bila `/mcp` menampilkan `context7` (User) **dan** `plugin:context7:context7`, hapus yang User: `claude mcp remove context7 --scope user`, lalu restart.

## 12. Masalah yang diketahui

- Rangkaian tes belum lolos penuh (`TESTING.md`).
- Dokumen lama menyebut `migrate:fresh --seed` untuk skema baru; **jangan** dijalankan pada basis data berisi data.
- Seeder membutuhkan `ADMIN_PASSWORD` plaintext (lihat `DATABASE.md`).
