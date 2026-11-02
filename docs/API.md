# API dan Endpoint — BacaDulu Research

BacaDulu Research adalah aplikasi **server-rendered (Blade)**. Tidak ada API publik berversi dan tidak ada `routes/api.php`. Dokumen ini mencatat endpoint HTTP aktual dari `php artisan route:list` (dijalankan pada sesi ini) serta integrasi eksternal. Tidak ada endpoint yang dikarang.

Semua route web berada di grup middleware `web` (sesi + CSRF). Mutasi (POST/PATCH/PUT/DELETE) wajib menyertakan token CSRF (`@csrf` atau header `X-CSRF-TOKEN`).

Singkatan middleware: **U** = `auth, active, verified.if.required, profile.complete` (area pengguna lengkap); **U-lite** = `auth, active, verified.if.required` (tanpa gerbang profil); **A** = `admin` (sesi admin).

## 1. Publik dan autentikasi

| Method | URI | Nama | Akses / Rate limit |
|---|---|---|---|
| GET | `/` | `home` | Publik |
| GET | `/login` | `login` | guest |
| POST | `/login` | `login.store` | guest, `throttle:login` (8/mnt email+IP, 20/mnt IP) |
| GET | `/register` | `register` | guest |
| POST | `/register` | `register.store` | guest, `throttle:register` (5/mnt, 20/jam) |
| GET | `/auth/google`, `/auth/google/callback` | `google.redirect`, `google.callback` | guest; **hanya terdaftar bila `RESEARCH_GOOGLE_AUTH_ENABLED=true`** (nonaktif default) |
| GET/POST | `/forgot-password` | `password.request`, `password.email` | POST `throttle:3,10` |
| GET | `/reset-password/{token}` | `password.reset` | Publik |
| POST | `/reset-password` | `password.update` | `throttle:5,10` |
| POST | `/logout` | `logout` | auth |
| GET | `/register/success` | `register.success` | auth, active |
| POST | `/onboarding/complete` | `onboarding.complete` | auth, active, `throttle:10,1`; respons JSON `{ "ok": true }` |
| GET | `/email/verify`, `/email/verify/{id}/{hash}` | `verification.notice`, `verification.verify` | auth, active; verify: `signed`, `throttle:6,1` |
| POST | `/email/verification-notification` | `verification.send` | auth, active, `throttle:6,1` |
| GET | `/up` | — | Health check bawaan Laravel |

## 2. Workspace, riset, dan percakapan (area pengguna: U)

| Method | URI | Nama | Rate limit |
|---|---|---|---|
| GET | `/dashboard` | `dashboard` | — (kompatibilitas, mengalihkan ke workspace) |
| GET | `/workspace` | `workspace` | — |
| GET | `/workspace/{conversation}` | `workspace.conversation` | — |
| POST | `/research/run` | `research.run` | `throttle:research` (12/mnt per pengguna) |
| GET | `/research/runs/{aiRun}/status` | `research.runs.status` | 120/mnt |
| GET | `/research/runs/{aiRun}/presentation` | `research.runs.presentation` | 60/mnt |
| PATCH | `/research/conversations/{conversation}/rename` | `conversations.rename` | — |
| PATCH | `/research/conversations/{conversation}/pin` / `unpin` | `conversations.pin` / `conversations.unpin` | — |
| GET | `/research/conversations/{conversation}/delete` | `conversations.delete.confirmation` | — |
| DELETE | `/research/conversations/{conversation}` | `conversations.destroy` | — |
| GET | `/history` | `history.index` | — |
| GET | `/library`, `/library/{document}` | `library.index`, `library.show` | `throttle:library` (60/mnt) |

### Contoh: `POST /research/run`
- Request (form): pertanyaan, pilihan prompt/tool, konteks percakapan/sumber (nama field persisnya: **lihat `ResearchSubmissionController`**; tidak disalin di sini agar tidak menyimpang dari kode).
- Efek: membuat `ai_runs` berstatus `queued` dan men-dispatch job `ProcessResearchRun` (antrean `research`, timeout 180 detik, `tries = 1`).
- Respons: redirect ke workspace (atau JSON bila klien meminta JSON; **perilaku persis belum diverifikasi**).
- Status lanjutan: poll `GET /research/runs/{aiRun}/status`.

### Pola status HTTP yang dipakai
`200` sukses; `302` redirect (login, validasi, aksi form); `403` melanggar kepemilikan (Saved); `404` resource bukan milik pengguna/tidak ada; `419` token CSRF/sesi kedaluwarsa; `422` validasi (permintaan JSON); `429` terlalu banyak permintaan (JSON `{"message": "Terlalu banyak permintaan. Silakan coba lagi nanti."}` bila `expectsJson`, halaman `errors.429` bila tidak).

## 3. Saved (U)

| Method | URI | Nama |
|---|---|---|
| GET | `/saved` | `saved.index` |
| POST | `/saved` | `saved.store` |
| GET | `/saved/{savedResult}` | `saved.show` |
| PATCH | `/saved/{savedResult}` | `saved.update` |
| PATCH | `/saved/{savedResult}/favorite` | `saved.favorite` |
| DELETE | `/saved/{savedResult}` | `saved.destroy` |
| GET | `/saved/{savedResult}/presentation` | `saved.presentation` (60/mnt) |

`DELETE` menghapus bookmark Saved, bukan AI Run asalnya.

## 4. Dokumen (U)

| Method | URI | Nama | Rate limit |
|---|---|---|---|
| GET | `/documents` | `documents.index` | — |
| GET | `/documents/create` | `documents.create` | — |
| POST | `/documents` | `documents.store` | 10/mnt |
| GET | `/documents/{documentGeneration}` | `documents.show` | — |
| PATCH | `/documents/{documentGeneration}` | `documents.update` | 30/mnt |
| DELETE | `/documents/{documentGeneration}` | `documents.destroy` | 15/mnt |
| GET | `/documents/{documentGeneration}/preview` | `documents.preview` | 60/mnt |
| POST | `/documents/{documentGeneration}/render` | `documents.render` | 10/mnt |
| GET | `/documents/{documentGeneration}/download/{format}` | `documents.download` | 30/mnt |

`{format}` adalah `docx` atau `pdf` (**pastikan nilai valid di `DocumentGenerationController::download`**). Berkas dibaca dari disk privat `research_outputs` setelah pengecekan kepemilikan.

## 5. Profil (U-lite)

| Method | URI | Nama | Rate limit |
|---|---|---|---|
| GET | `/profile` | `profile.edit` | — |
| PATCH | `/profile` | `profile.update` | `throttle:profile` (10/mnt) |
| GET | `/profile/setup` | `profile.setup` | — |
| GET | `/profile/avatar` | `profile.avatar` | — |
| PATCH | `/profile/credentials` | `profile.credentials.update` | 20 per 15 mnt |

## 6. Admin Console

Prefix: `/{BACADULU_ADMIN_PATH}` (default `panel-adminbaca-research`). Login admin: `GET/POST …/login` (`admin.login`, `admin.login.store` dengan `throttle:admin-login`, 5/mnt/IP). Semua route lain memakai middleware `admin`.

| Modul | Route (nama `admin.*`) |
|---|---|
| Dashboard | `dashboard` |
| Pustaka | `library.index/create/store/edit/update`, `library.ingestion-status` |
| Kategori | `categories.index/create/store/edit/update` |
| Koleksi | `collections.index/create/store/edit/update` |
| Prompt | `prompts.index/create/store/edit/update` |
| Versi prompt | `prompt-versions.index`, `prompt-versions.activate` (PATCH), `prompt-versions.duplicate` (POST) |
| AI Tools | `tools.index/create/store/edit/update` |
| Glosarium | `glossary.index/create/store/edit/update/destroy` |
| Template dokumen | `document-templates.index/create/store/edit/update/destroy` |
| AI Runs | `ai-runs.index`, `ai-runs.show` |
| Audit | `audit.index` |
| Pengguna | `users.index`, `users.show`, `users.status` (PATCH) |
| Pengaturan | `settings.index`, `settings.update` (PUT) |
| Keamanan | `security.index` |
| Source Sync | `source-sync.index`, `source-sync.scan` (POST), `source-sync.import` (POST), `source-sync.ignore` (PATCH) |
| Logout | `logout` (POST) |

Catatan: route admin tidak memiliki rate limit selain login. **Belum diverifikasi**: apakah aksi mutasi admin perlu throttle tambahan.

## 7. Integrasi eksternal

### 7.1 Source Sync — API BacaDulu utama
Konfigurasi: `config/source-sync.php`. Provider aktif: `SOURCE_SYNC_PROVIDER` (default **`mock`**; connector nyata `bacadulu` menunggu endpoint remote).

Alur aman: `Remote → Staging → Review Admin → Import → Ready`. `auto_publish` sengaja `false` dan tidak berasal dari env.

Endpoint yang **diharapkan** dari API remote (kontrak di konfigurasi, belum tentu tersedia):

```text
GET {base}/api/research/sources                  → { "data": [ { external_id, title, checksum, updated_at, metadata } ] }
GET {base}/api/research/sources/{external_id}    → { "data": { external_id, title, checksum, updated_at, extracted_text, metadata } }
```

Pengaturan (env `SOURCE_SYNC_BACADULU_*`):

| Pengaturan | Default |
|---|---|
| `TIMEOUT` | 30 detik |
| `CONNECT_TIMEOUT` | 10 detik |
| `RETRY_TIMES` / `RETRY_SLEEP_MS` | 2 kali / 300 ms |
| `PAGE_SIZE` / `MAX_PAGES` | 50 / 25 |
| `REQUIRE_TOKEN` | `true` (Bearer token) |
| `VERIFY_SSL` | `true` |
| `ALLOW_INSECURE_HTTP` | `false` (hanya untuk localhost pengembangan) |
| `USER_AGENT` | `BacaDulu-Research-SourceSync/1.0` |

Keamanan: lihat `SECURITY.md` 2.11 (SSRF). PDF remote tidak diwajibkan; teks diekstrak dikirim sebagai `extracted_text`.

### 7.2 Penyedia AI
`AI_PROVIDER` = `mock` (default; tanpa jaringan) atau `openai` (`OpenAiResponsesProvider`, `Http::withToken`, timeout dari `AI_TIMEOUT`, default 90 detik; base URL dari `OPENAI_BASE_URL`). Kunci `OPENAI_API_KEY` hanya di server. Retry khusus penyedia AI: **tidak ditemukan** (job riset `tries = 1`).

### 7.3 BPS
**Belum diverifikasi / tidak ditemukan.** Tidak ada klien, config, route, atau env untuk BPS di project. Jika integrasi direncanakan, definisikan: sumber data, autentikasi API, kuota, caching, timeout/retry, dan model data sebelum implementasi.

### 7.4 Google OAuth
Laravel Socialite; nonaktif default. Redirect URI harus sama persis dengan yang didaftarkan di Google Cloud.

## 8. Pedoman error handling

- Validasi gagal → kembali ke form dengan pesan bahasa Indonesia dan input lama (kecuali kata sandi).
- Kegagalan eksternal (AI, connector) harus ditangkap dan diubah menjadi status run/sync (`failed`) tanpa membocorkan detail teknis ke pengguna; detail masuk Sentry/log.
- Hormati `Retry-After` pada 429 saat membuat klien JS.

## 9. Kemungkinan pekerjaan berikutnya

- Dokumentasikan field request/response tiap endpoint dari `FormRequest`/`validate()` (saat ini tersebar di controller).
- Pertimbangkan `Retry-After` dan respons JSON seragam untuk poll status.
