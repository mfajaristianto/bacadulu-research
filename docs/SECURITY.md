# Keamanan — BacaDulu Research

Dokumen ini mencatat kontrol keamanan **berdasarkan bukti di source code dan konfigurasi**. Setiap butir diberi status:

- **Diterapkan** — ada bukti di kode/konfigurasi (lokasi disebut).
- **Sebagian** — ada, tetapi dengan celah yang dicatat.
- **Belum diterapkan** — tidak ditemukan di kode.
- **Belum diverifikasi** — tidak dapat dipastikan dari pemeriksaan yang dilakukan.

> Dokumen sumber "BacaDulu — Project Documentation Standard" tidak tersedia di workspace. Daftar 20 pemeriksaan (bagian 3) disusun dari daftar topik pada brief tugas, sehingga **nomor dan redaksinya dapat berbeda dari dokumen aslinya**. Tidak ada perlindungan yang diklaim aktif tanpa bukti. Tidak ada perubahan backend keamanan pada pekerjaan dokumentasi ini.

Panduan guardrail lama: `archive/bacadulu-context/07_SECURITY_CHANGE_GUARDRAILS.md`.

## 1. Ringkasan temuan prioritas

| # | Temuan | Tingkat | Bukti |
|---|---|---|---|
| 1 | Enumerasi akun saat login: email tak terdaftar menghasilkan pesan khusus "Email ini belum terdaftar…" dan akun nonaktif menghasilkan pesan berbeda | Sedang | `Auth/AuthenticatedSessionController.php` |
| 2 | `trustProxies(at: '*')` mempercayai semua proxy; bila aplikasi dapat diakses langsung (tanpa proxy tepercaya), `X-Forwarded-For` dapat dipalsukan sehingga rate limit berbasis IP dapat dihindari | Sedang | `bootstrap/app.php` |
| 3 | Tidak ada progressive delay maupun lockout sementara; hanya rate limit per menit | Sedang | `AppServiceProvider::boot` (limiter `login`) |
| 4 | `composer audit` melaporkan 3 advisory: `league/commonmark` (1 high, 1 medium) dan `symfony/mime` (1 medium) | Tinggi–sedang | Hasil `composer audit` pada sesi ini; `npm audit --omit=dev`: 0 kerentanan |
| 5 | Arsip `research final.zip` (±5 MB) ter-track di Git dan berisi `database/database.sqlite`; isi database tidak diperiksa | Perlu dicek | `git ls-files`, `unzip -l` (tanpa ekstraksi) |
| 6 | Kredensial admin berasal dari `.env`; fallback `ADMIN_PASSWORD` plaintext masih didukung | Sedang | `config/research.php`, `Admin/AuthController.php` |
| 7 | SSRF connector hanya memvalidasi skema dan host; tidak memblokir IP privat/loopback | Rendah–sedang | `Services/Research/Sync/BacaDuluSourceConnector.php` |
| 8 | Tidak ada prosedur backup/restore atau incident response tertulis di repo | Sedang | Tidak ditemukan |

## 2. Kontrol per topik

### 2.1 Authentication
- **Diterapkan**: `Auth::attempt` dengan syarat `status = active`; sesi diregenerasi setelah login dan register (`regenerate()`); logout membatalkan sesi dan token CSRF; kata sandi register memakai `Rules\Password::defaults()`.
- **Diterapkan**: reset kata sandi memakai broker Laravel dengan throttle (`3/10 menit` untuk permintaan, `5/10 menit` untuk reset) dan `remember_token` diganti setelah reset.
- **Diterapkan**: pengguna `non-active/suspended` diblokir pada request berikutnya oleh middleware `active` (`EnsureActiveUser`).
- **Sebagian**: verifikasi email opsional (`RESEARCH_REQUIRE_EMAIL_VERIFICATION`, default `false`).
- **Sebagian**: cookie sesi `http_only=true`, `same_site=lax`, `secure` otomatis saat `APP_ENV=production`; enkripsi sesi (`SESSION_ENCRYPT`) default `false`; masa berlaku 120 menit.
- **Sebagian**: admin memakai sesi terpisah berbasis flag (`research_admin_authenticated`) dan bukan guard Laravel. Perbandingan email memakai `hash_equals`, kata sandi `Hash::check` untuk hash, dan fallback plaintext `hash_equals`. Sesi diregenerasi saat login/logout admin. Path admin default `panel-adminbaca-research` hanya keamanan melalui ketidakjelasan (nilainya tertulis di repo).
- **Belum diterapkan**: MFA/OTP (README lama menyebut OTP/SMS belum diaktifkan).

### 2.2 Authorization, ownership check, dan IDOR
- **Diterapkan**: pemeriksaan kepemilikan eksplisit pada resource privat:
  - Saved: `abort_unless(..., 403)` dan query terfilter `user_id` (`SavedResultController`).
  - Dokumen: query/pengecekan `user_id` (`DocumentGenerationController`).
  - Run dan presentasi: `abort_unless(user_id sama, 404)` (`ResearchRunStatusController`, `ResearchOutputPresentationController`).
  - Percakapan: `abort_unless(user_id sama, 404)` (`ConversationController`).
- **Diterapkan**: seluruh route area pengguna berada dalam middleware `auth`, `active`, `verified.if.required`, `profile.complete`; seluruh route admin dalam middleware `admin`.
- **Belum diverifikasi**: tes IDOR (`other user cannot open or download someone elses generated document`) ada, tetapi saat ini gagal karena masalah lingkungan uji (lihat `TESTING.md`), sehingga belum dapat dijadikan bukti runtime.
- Aturan untuk kode baru: gunakan route model binding **disertai** pemeriksaan kepemilikan; jangan mengandalkan ID tidak tertebak.

### 2.3 Indirect Prompt Injection
Risiko: teks di dokumen sumber/PDF atau glosarium berisi instruksi yang mencoba membajak model.
- **Sebagian**: prompt sintesis menandai pertanyaan, konteks percakapan, glosarium, dan bukti sebagai **data tidak tepercaya** (`ResearchSynthesisService`), bukti diberi label `UNTRUSTED SOURCE CONTENT`; sitasi divalidasi (`CitationValidator`); glosarium tidak bisa menjadi sitasi akademik; sumber `restricted` gagal tertutup (fail-closed); hanya `published` + `public` yang dapat diambil.
- **Belum diterapkan**: pemindaian/sanitasi instruksi di dalam teks sumber saat ingestion, pembatasan kemampuan model (tidak ada tool/aksi, ini mengurangi dampak), dan pengujian adversarial khusus.
- **Sebagian**: keluaran Markdown dirender dengan `html_input: strip` dan `allow_unsafe_links: false` di Workspace; penggunaan serupa di halaman dokumen **belum diaudit satu per satu**. Advisory `league/commonmark` (lihat 2.20) perlu ditinjau terhadap konfigurasi ini.
- Pedoman: model tidak boleh diberi akses tool, URL fetch, atau kunci rahasia; keluaran model diperlakukan sebagai input tidak tepercaya.

### 2.4 Database race condition
- **Sebagian**: operasi multi-langkah memakai `DB::transaction` (ingestion, sync, submission riset, template dokumen); `lockForUpdate()` dipakai di `SourceIngestionService` dan `SourceSyncService`; job riset memiliki guard status terminal sehingga tidak diproses ulang.
- **Diterapkan**: `SavedResult::firstOrCreate` (satu AI Run hanya sekali disimpan per pengguna) didukung unique index `saved_results_user_run_unique` pada migrasi `2026_09_28_000005_finalize_research_provenance_and_document_engine.php`. Indeks dibuat secara kondisional di migrasi; **keberadaannya di database produksi belum diverifikasi** (`SHOW INDEX FROM saved_results`).
- Untuk kode baru: aksi "buat jika belum ada" wajib memiliki unique index di database; jangan hanya `exists()` lalu `create()`.

### 2.5 SQL Injection dan Prepared Statements
- **Diterapkan**: akses data utama lewat Eloquent/Query Builder (parameter di-bind). `whereRaw('LOWER(email) = ?', [..])` memakai binding.
- **Belum diverifikasi**: `orderByRaw` di `Admin/AiToolController` dan `Admin/SourceSyncController`, serta `DB::table($table)` di `Admin/DashboardController` (nama tabel dari kode internal). Pastikan tidak ada input pengguna yang masuk ke fragmen mentah.
- Aturan: jangan menyisipkan input ke string SQL; gunakan binding dan daftar putih untuk nama kolom/arah urutan.

### 2.6 Validasi server-side
- **Diterapkan**: `validate()`/`Validator` dipakai di 24 controller (auth, profil, dokumen, riset, Saved, seluruh controller admin). Batas panjang dan tipe ditetapkan (mis. profil `max:120…1200`).
- Aturan: validasi di server selalu, validasi klien hanya kenyamanan; gunakan `Rule::in` untuk nilai enum (tipe dokumen, gaya sitasi dari `config/research.php`).

### 2.7 Rate limiting backend
| Limiter / route | Batas | Kunci |
|---|---|---|
| `login` | 8/menit | email (huruf kecil) + IP |
| `login` | 20/menit | IP |
| `register` | 5/menit dan 20/jam | IP |
| `admin-login` | 5/menit | IP |
| `research` (`POST /research/run`) | 12/menit | user id (fallback IP) |
| `profile` | 10/menit | user id |
| `library` | 60/menit | user id |
| `password.email` / `password.update` | 3/10 menit / 5/10 menit | default throttle (IP) |
| `profile.credentials.update` | 20/15 menit | default throttle |
| `documents.store` / `render` | 10/menit | default throttle |
| `documents.download` / `update` | 30/menit | default throttle |
| `documents.preview`, `research.runs.presentation` | 60/menit | default throttle |
| `research.runs.status` | 120/menit | default throttle |

Respons 429 diseragamkan (JSON bila `expectsJson`, halaman `errors.429` bila tidak). **Diterapkan.** Cache driver default `database`; pada produksi dengan banyak proses, pastikan store cache dibagikan antar-proses.

### 2.8 Anti-brute-force tiga lapis
Standar yang dituju:

1. **Batas per akun/IP** — **Sebagian**: ada kombinasi `email|IP` 8/menit dan `IP` 20/menit (pengguna), `IP` 5/menit (admin). Tidak ada batas harian per akun.
2. **Progressive delay** — **Belum diterapkan**: jeda tidak meningkat seiring kegagalan beruntun.
3. **Temporary lockout** — **Belum diterapkan**: akun tidak dikunci sementara setelah N kegagalan.

Rancangan yang dianjurkan (belum diimplementasikan):
- Hitung kegagalan per `email` dan per `IP` terpisah, di cache dengan TTL.
- Setelah N kegagalan, jeda meningkat (mis. 1 → 2 → 4 → 8 menit) dan dikembalikan sebagai 429 + `Retry-After`.
- Lockout sementara (mis. 15 menit), bukan permanen, dan reset otomatis setelah login sukses.
- **Cegah penyalahgunaan lockout** (penyerang mengunci akun korban): kunci berdasarkan pasangan `email|IP` dan tambahkan tantangan (CAPTCHA/verifikasi email) alih-alih mengunci semua IP; jangan membedakan pesan "akun terkunci" dari "kredensial salah" kepada pihak tak terotentikasi bila memungkinkan; beri pemilik akun jalur pemulihan lewat reset kata sandi; catat kejadian di audit log.
- Respons login harus seragam (lihat temuan 1) agar rate limit tidak menjadi satu-satunya penghalang enumerasi.

### 2.9 CSRF
- **Diterapkan**: grup middleware `web` standar Laravel 12 memverifikasi token CSRF; `bootstrap/app.php` tidak mengecualikan route apa pun. Semua formulir mutasi memakai `@csrf` (**belum dihitung satu per satu**). `onboarding.complete` (POST JSON) bergantung pada header token CSRF dari JS.

### 2.10 CORS
- **Tidak relevan saat ini**: tidak ada `config/cors.php` dan tidak ada route API lintas-origin; aplikasi melayani Blade pada origin yang sama. Bila API publik dibuat, tetapkan daftar origin eksplisit; jangan memakai `*` bersama kredensial.

### 2.11 SSRF
- Satu-satunya klien HTTP keluar: `OpenAiResponsesProvider` (host dari `OPENAI_BASE_URL`) dan `BacaDuluSourceConnector` (host dari `SOURCE_SYNC_BACADULU_BASE_URL`). Keduanya ditetapkan oleh konfigurasi server, bukan input pengguna akhir.
- **Diterapkan** di connector: skema harus `https` kecuali `allow_insecure_http` diaktifkan eksplisit (default `false`), host wajib ada, `verify_ssl` default `true`, timeout dan retry dibatasi, jumlah halaman dibatasi (`max_pages`).
- **Belum diterapkan**: blokir IP privat/loopback/link-local (mis. metadata cloud `169.254.169.254`) dan pembatasan redirect.
- **Belum diverifikasi**: apakah pengaturan runtime admin (`ResearchSettingsService`) dapat mengubah host tujuan.

### 2.12 Path Traversal
- **Diterapkan**: unduhan dokumen dibaca dari disk privat `research_outputs` memakai path yang tersimpan di database (`docx_path`, `pdf_path`), bukan dari input pengguna, dan lewat route terautentikasi. Disk `research_sources` juga privat.
- **Belum diverifikasi**: penamaan berkas unggahan PDF admin dan avatar (panduan lama menyebut nama berkas dibuat sistem). Aturan: jangan pernah memakai nama berkas dari klien sebagai path; gunakan nama acak dan `basename`.

### 2.13 Keamanan API
- Tidak ada API publik. Endpoint JSON internal hanya `onboarding.complete`, status run, dan presentasi, semuanya di balik `auth`. Source Sync memanggil API eksternal (lihat `API.md`).
- **Diterapkan**: token Bearer connector disimpan di env, `require_token` default `true`.
- Untuk API baru: autentikasi (Sanctum/token), otorisasi per resource, rate limit, validasi skema, versi, dan jangan membocorkan field internal.

### 2.14 Verifikasi webhook
- **Tidak relevan saat ini**: tidak ada penerima webhook di route. Bila ditambahkan: verifikasi HMAC dari header tanda tangan dengan `hash_equals`, tolak timestamp lama (anti-replay), dan idempotensi berdasarkan ID event.

### 2.15 Security headers
**Diterapkan** (`SecurityHeaders` di grup `web`): `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy` (kamera, mikrofon, geolokasi dimatikan), `Content-Security-Policy` (produksi: `script-src 'self'`, `style-src 'self' 'unsafe-inline'`, `form-action 'self'`, `frame-ancestors 'self'`, `base-uri 'self'`), dan `Strict-Transport-Security` (hanya HTTPS + produksi).
Catatan: `style-src 'unsafe-inline'` melemahkan CSP untuk gaya; font eksternal diblokir (`font-src 'self' data:`), sehingga font harus self-host.

### 2.16 Manajemen secret
- **Diterapkan**: `.env`, `.env.backup`, `.env.production` ada di `.gitignore`; `.env.example` berisi placeholder; kunci API OpenAI hanya di sisi server; Sentry diatur tanpa PII default dan ada tes privasi.
- **Belum diverifikasi**: isi `research final.zip` (temuan 5) dan riwayat Git terhadap secret yang pernah ter-commit.
- Aturan: jangan menyalin isi `.env` ke dokumen, log, atau paket distribusi; rotasi kunci bila pernah bocor.

### 2.17 Backup dan uji restore
- **Belum diterapkan/diverifikasi**: tidak ada skrip atau prosedur backup di repo. Lihat `DEPLOYMENT.md` untuk prosedur yang dianjurkan; uji restore ke lingkungan terpisah secara berkala.

### 2.18 Enkripsi data
- **Diterapkan**: telepon dan alamat profil dienkripsi dengan `Crypt::encryptString` (kolom `phone_encrypted`, `address_encrypted`). Kata sandi di-hash oleh Laravel.
- **Belum diterapkan**: enkripsi sesi (default `false`).

### 2.19 Unggahan berkas
- **Diterapkan**: avatar `image|mimes:jpg,jpeg,png,webp|max:5120`; PDF pustaka `mimes:pdf` dengan batas ukuran (`RESEARCH_SOURCE_MAX_KB`, default 102400 KB); SVG tidak diizinkan untuk avatar.
- **Belum diverifikasi**: pemindaian malware dan pemeriksaan isi PDF melebihi MIME.

### 2.20 Audit dependensi
- `composer audit` (dijalankan pada sesi ini) melaporkan: `league/commonmark` — *DisallowedRawHtml bypass* (medium) dan *DoS kuadratik pada ekstensi tabel GFM* (high); `symfony/mime` — CVE-2026-45070 (medium). Perbaikan memerlukan pembaruan paket yang **tidak dilakukan** pada pekerjaan ini (larangan `composer update`).
- `npm audit --omit=dev`: 0 kerentanan.
- Rencanakan pembaruan terkontrol, jalankan tes, lalu ulangi audit.

### 2.21 Logging dan incident response
- **Diterapkan**: Sentry (`sentry/sentry-laravel`) dengan konteks aman, `send_default_pii` dimatikan; tabel `audit_logs` dengan 14 titik pencatatan aksi admin; `password` tidak ikut di-flash.
- **Belum diterapkan**: runbook incident response tertulis. Usulan minimal: (1) deteksi dan catat waktu; (2) isolasi (nonaktifkan akun/kunci, maintenance mode); (3) rotasi secret; (4) analisis log/audit; (5) pemulihan dari backup; (6) notifikasi pihak terdampak; (7) tinjauan pascainsiden.

## 3. Dua puluh pemeriksaan keamanan sebelum production

Gunakan sebagai gerbang rilis. Status mencerminkan pemeriksaan pada sesi ini.

| # | Pemeriksaan | Status | Catatan |
|---|---|---|---|
| 1 | `APP_ENV=production`, `APP_DEBUG=false` | Belum diverifikasi | Bergantung `.env` server |
| 2 | `APP_KEY` unik dan tidak pernah di-commit | Belum diverifikasi | |
| 3 | Cookie sesi aman (`secure`, `http_only`, `same_site`) | Sebagian | Default benar; `SESSION_ENCRYPT=false` |
| 4 | HTTPS dipaksa + HSTS | Sebagian | HSTS ada; pemaksaan HTTPS bergantung server |
| 5 | Security headers + CSP aktif | Diterapkan | `SecurityHeaders` |
| 6 | CSRF aktif di semua form/POST | Diterapkan | Grup `web` |
| 7 | Rate limiting login/register/reset/admin/riset | Diterapkan | Bagian 2.7 |
| 8 | Anti-brute-force tiga lapis | Sebagian | Hanya lapis 1 |
| 9 | Respons login tidak membocorkan keberadaan akun | Belum diterapkan | Temuan 1 |
| 10 | Otorisasi/ownership di semua resource privat | Diterapkan | Bagian 2.2; tes belum dapat membuktikan |
| 11 | Admin dipisah dari sesi pengguna; hash kata sandi admin | Sebagian | Hapus fallback plaintext |
| 12 | Validasi server-side di semua input | Diterapkan | 24 controller |
| 13 | Tidak ada SQL mentah dengan input pengguna | Belum diverifikasi | Audit `orderByRaw`/`DB::table` |
| 14 | Output di-escape; HTML pengguna tidak dirender | Sebagian | `{!! !!}` dipakai untuk Markdown ter-strip; audit sisanya |
| 15 | Unggahan: whitelist MIME, batas ukuran, nama aman, disk privat | Sebagian | Bagian 2.19 |
| 16 | Mitigasi prompt injection tidak langsung | Sebagian | Bagian 2.3 |
| 17 | Race condition: transaksi, lock, unique index | Sebagian | Bagian 2.4; cek indeks di DB produksi |
| 18 | SSRF/Path traversal dimitigasi | Sebagian | Bagian 2.11–2.12 |
| 19 | Audit dependensi bersih; secret tidak bocor | Belum diterapkan | 3 advisory Composer; cek zip & riwayat Git |
| 20 | Backup + uji restore + logging/incident response siap | Belum diterapkan | Bagian 2.17, 2.21 |

## 4. Aturan perubahan aman

- Jangan melemahkan keamanan demi menyederhanakan UI.
- Perubahan terkecil yang aman lebih dulu; tanpa refactor luas saat koreksi visual.
- Jangan menghapus kode mati bila keyakinan di bawah tinggi.
- Pertahankan kontrak route kecuali disetujui.
- Jangan menjalankan `migrate:fresh` pada data yang penting; migrasi hanya setelah backup.
- Jangan mengemas `.env`, kunci API, kredensial OAuth, kata sandi admin, dump database produksi, atau token privat.
