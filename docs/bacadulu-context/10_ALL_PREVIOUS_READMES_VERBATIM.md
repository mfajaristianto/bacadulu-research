# All Previous README / MD Files — Verbatim Consolidation

Source: `BacaResearch_4research_sidebar_checkpoint.zip`

This file merges the prior documentation found in the latest ZIP into one place for traceability.
Where a legacy document conflicts with the current MASTER KEY, the MASTER KEY wins.


---

# SOURCE FILE: `AGENTS.md`

<laravel-boost-guidelines>
# Laravel Application

This repository contains a Laravel application. Complete the following setup before working on the user's request.

## Prerequisites

Verify that PHP and Composer are available:

```sh
php -v
composer -V
```

If either command is unavailable, detect the user's operating system and install the prerequisites with the appropriate command:

macOS:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/mac/8.5)"
```

Windows PowerShell:

```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072; iex ((New-Object System.Net.WebClient).DownloadString('https://php.new/install/windows/8.5'))
```

Linux:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/linux/8.5)"
```

After installation, ask the user to restart their terminal. If the agent needs the restarted shell to continue, ask the user to reopen their terminal and rerun their original prompt.

## Agent Setup

Install Laravel Boost from the application root before making application changes:

```sh
composer require laravel/boost --dev
php artisan boost:install
```

Boost replaces these bootstrap instructions with guidelines tailored to the application. After installation, read `AGENTS.md` again and continue with the user's original request using the generated guidelines.
</laravel-boost-guidelines>


---

# SOURCE FILE: `BACARESEARCH_IMPLEMENTATION_STATUS.md`

# BacaResearch — Baseline 4research + Sidebar Update

STATUS: PARTIAL IMPLEMENTATION — NOT FINAL REDESIGN

This package preserves the uploaded 4research codebase and implements only:
- Sidebar History link removed (history route and backend preserved)
- Sidebar bottom promotional card removed
- Recent: first 5 shown; Show more / Show less for remaining items
- Small CSS and JS additions

NOT IMPLEMENTED YET: reference-accurate Workspace, Prompt Picker, Apa ini modal,
Saved redesign/chart, Profile redesign/avatar sync, Create Document confirmation/download UX.
Do not treat this archive as a finished visual redesign.

The reference images/screenshots for the ten-screen design board are not included
in this uploaded ZIP. Accurate reproduction needs those actual visual assets.

Safety:
1. Back up your working folder and database before replacement.
2. Extract to a SEPARATE folder; do not overwrite the working project.
3. Compare changed files before selectively applying.
4. Build with npm run build and check Laravel Blade rendering.

Changed files:
- resources/views/components/research-sidebar.blade.php
- resources/css/pages/research-sidebar.css
- resources/js/research-sidebar.js
- resources/js/app.js
- resources/css/app.css


---

# SOURCE FILE: `CLAUDE.md`

<laravel-boost-guidelines>
# Laravel Application

This repository contains a Laravel application. Complete the following setup before working on the user's request.

## Prerequisites

Verify that PHP and Composer are available:

```sh
php -v
composer -V
```

If either command is unavailable, detect the user's operating system and install the prerequisites with the appropriate command:

macOS:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/mac/8.5)"
```

Windows PowerShell:

```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072; iex ((New-Object System.Net.WebClient).DownloadString('https://php.new/install/windows/8.5'))
```

Linux:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/linux/8.5)"
```

After installation, ask the user to restart their terminal. If the agent needs the restarted shell to continue, ask the user to reopen their terminal and rerun their original prompt.

## Agent Setup

Install Laravel Boost from the application root before making application changes:

```sh
composer require laravel/boost --dev
php artisan boost:install
```

Boost replaces these bootstrap instructions with guidelines tailored to the application. After installation, read `AGENTS.md` again and continue with the user's original request using the generated guidelines.
</laravel-boost-guidelines>


---

# SOURCE FILE: `DESIGN_BACADULU_RESEARCH_V3.md`

# BacaDulu Research V3 — Design Specification

Status: Phase 1 Design
Source of truth: PRD_BacaDulu_Research_V3_FINAL + README_BacaDulu_Research_V3_FINAL
Implementation baseline: app(7).zip

## 1. Product design north star

BacaDulu Research is an evidence-first AI Research Workspace, not a generic chatbot and not a raw internal-library browser.

The interface must help the user understand:
1. what a research prompt means;
2. where an answer came from;
3. what the next useful research action is;
4. how to turn research into a real document.

## 2. Visual direction

Editorial Research Workspace × Modern Product Interface.

Character:
- premium;
- editorial;
- academic;
- warm;
- intelligent;
- calm;
- restrained;
- intentional.

Visual metaphor:
- warm research desk / canvas;
- paper-like research surfaces;
- deep navy editorial ink;
- teal as evidence/source semantic color;
- orange as primary action/accent;
- muted gold as limited secondary annotation/highlight.

## 3. Visual Reference Library

### Reference A — User-provided screenshots

Take:
- composition;
- visual hierarchy;
- spacing;
- visual rhythm;
- density;
- presentation quality;
- color harmony;
- interaction/motion ideas.

Do not take:
- brand;
- logo;
- copy;
- exact layout;
- pixel values;
- proprietary illustration;
- exact UI clone.

Application:
- landing hero composition;
- auth responsiveness;
- workspace hierarchy;
- color balance;
- motion direction.

Motion preference:
- soft zoom;
- fade;
- backdrop transition;
- short movement;
- reduced-motion fallback.

### Reference B — Design Spells

Take:
- small meaningful feedback;
- state transitions;
- microinteraction;
- short reveal/selection motion.

Avoid:
- decorative animation everywhere;
- long theatrical sequences;
- bounce as default.

Application:
- sidebar open/close;
- prompt selection;
- evidence reveal;
- upload progress;
- success/error feedback;
- AI state transitions.

### Reference C — Rotato

Take:
- controlled depth;
- layered product presentation;
- camera/perspective thinking;
- keyframe-like staged motion;
- smooth easing.

Avoid:
- making the operational workspace 3D;
- excessive perspective;
- decorative 3D during tasks.

Application:
- landing hero product showcase;
- research pipeline illustration;
- document preview showcase.

### Reference D — UI Guideline

Take:
- component anatomy;
- variants;
- interaction states;
- explicit empty/loading/error/success behavior;
- consistent naming.

Avoid:
- copying a third-party design system visually;
- turning every content block into a card.

Application:
- buttons;
- inputs;
- select;
- textarea;
- sidebar;
- tabs;
- modal/dialog;
- toast;
- file upload;
- stepper;
- empty/error/success states.

### Reference E — Iteration X

Take:
- context-preserving actions;
- annotated context;
- relationship between action and the content it affects;
- contextual feedback while the work remains visible.

Application:
- evidence inspection;
- source context panel;
- prompt explanation;
- document review;
- research next-actions.

### Reference F — Design Skills

Take:
- spec-first before code;
- token-driven design;
- responsive planning;
- accessibility planning;
- explicit component state coverage;
- design QA.

Application:
- entire redesign process.

### Reference G — Taste Skill

Take:
- brief-first design inference;
- audit-first redesign;
- anti-template thinking;
- deliberate layout selection;
- motion direction;
- pre-flight review.

Avoid:
- replacing product requirements with aesthetic rules.

Application:
- landing;
- auth;
- workspace;
- profile;
- admin surfaces.

### Reference H — Hallmark

Take:
- macrostructure with intent;
- typography pairing;
- asymmetric composition;
- restraint;
- anti-default audit.

Avoid:
- generic purple-gradient hero;
- centered-everything;
- one-font personality;
- gradient pill CTA;
- icon-tile feature grids.

Application:
- landing macrostructure;
- typography;
- section rhythm;
- CTA treatment;
- page-level audit.

### Reference I — Anti-Slop

Take:
- high-fidelity discipline;
- explicit art direction;
- editorial visual character;
- visually differentiated but usable composition.

Avoid:
- uniqueness without product reason;
- blindly copying a preset style.

Application:
- final visual QA.

## 4. Canonical tokens

### Colors
- --bd-orange: #F26B4F
- --bd-navy: #17172B
- --bd-ivory: #F8F3EB
- --bd-paper: #FFFCF7
- --bd-teal: #21A6A0
- --bd-gold: #D7A84A

### Spacing
4, 8, 12, 16, 24, 32, 40, 48, 64, 80, 96, 128

### Radius
- small: 8px
- medium: 12px
- large: 16px
- showcase-only: 22px max unless explicitly justified

### Shadow
- soft;
- low-contrast;
- contextual;
- no glow.

### Motion
- short and purposeful;
- ease-out / smooth deceleration;
- no default bounce;
- interruptible where practical;
- respect prefers-reduced-motion.

## 5. Target screen direction

### Landing
Flow:
Navbar → Hero → Evidence/AI Workflow → Research Tools → How Research Works → Document Output → Source Methodology → CTA → Footer

Hero narrative:
Question → Retrieved Evidence → Analysis → Insight

Do not present the raw internal library as the user's primary product.

### Workspace
Desktop structure:
Sidebar / history + Research Canvas + optional Context Panel.

Research Canvas hierarchy:
Active Tool/Prompt → Question → AI Process → Answer → Evidence → Sources → Next Research Action → Composer.

### Sidebar
- New Research
- PINNED
- RECENT
- Saved
- History
- Profile

### Prompt experience
Every prompt needs:
- English term;
- Indonesian meaning;
- description;
- when to use;
- when not to use;
- example;
- expected output;
- explicit Use Prompt action.

### Evidence
Evidence is first-class UI, not a hidden attachment at the end of a chat message.

### Source context
User sees metadata and relevant evidence context, not unrestricted browsing of the full internal repository.

### Document
Research Result → Document Type → Template → Citation Style → Generate → Preview → Edit → Export.

## 6. Existing baseline — preserve vs redesign

Preserve as implementation foundation:
- separated user/admin auth;
- warm canvas direction;
- navy/coral/teal family;
- asymmetric landing direction;
- sidebar workspace concept;
- modular CSS files;
- subtle reveal motion;
- custom Laravel/Blade/Vite stack.

Redesign/alignment required:
- remove user-facing raw Library concept from final product navigation;
- replace “Internal Knowledge Assistant” framing with Research Workspace framing;
- turn prompt picker into prompt education experience;
- promote evidence/source hierarchy;
- complete conversation management states;
- add next-research-action system;
- add document-generation workflow;
- align tokens with canonical PRD palette;
- complete responsive/accessibility states.

## 7. Anti-slop gate

Before a screen is approved:
- layout has a product reason;
- typography has real hierarchy;
- color is restrained and semantic;
- cards are used only when structurally useful;
- motion explains state/change;
- icons have meaning;
- copy is BacaDulu-specific;
- mobile remains intentional;
- empty/loading/error/success/offline are covered;
- result feels like a research product, not an AI template.

## 8. Implementation order after audits/approval

1. Canonical design tokens and typography foundation
2. Global shell/navigation
3. Landing page
4. User authentication screens
5. Workspace shell/sidebar
6. Prompt education/picker
7. Conversation/evidence/source UI
8. History/Saved
9. Profile/avatar
10. Admin visual alignment
11. Document generator/preview
12. Responsive/accessibility/motion QA

No application-code execution before Security Audit + Cleanup Audit + approval.


---

# SOURCE FILE: `README.md`

<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).


---

# SOURCE FILE: `README_BACADULU_SETUP.md`

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


---

# SOURCE FILE: `README_BUILD.md`

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


---

# SOURCE FILE: `README_CHAT_PHASE.md`

# BacaDulu Research — Chat + Prompt Flow

This build turns the authenticated research experience into a Notebook-style chat workspace while retaining the separate admin console.

## User flow
1. Google login / normal login.
2. If profile completion is below `RESEARCH_PROFILE_GATE` (default 80), the user is sent to Profile Setup.
3. Once the gate is met, `/dashboard` redirects directly to `/workspace`.
4. The workspace shows recent conversations, a category selector, and an admin-prepared prompt picker opened with the `+` button.
5. Users type only their research question. They do not provide an AI system prompt.
6. The server selects only `published` + `public` documents in the chosen active category and records them as sources for the AI run.
7. The conversation and messages are persisted for the Recent Conversations sidebar.

## Admin flow
Admin remains under the configured path (default `panel-adminbaca-research`). The admin console manages:
- Internal Library
- Prompt Library
- Research tool / prompt activation

Only published public internal sources are eligible for normal-user retrieval. Restricted sources fail closed until explicit access control is introduced.

## New database tables
- `research_conversations`
- `research_messages`

Run once on an existing local database:

```powershell
php artisan migrate
php artisan optimize:clear
```

No `.env` is included in the build.


---

# SOURCE FILE: `README_FIX_FINAL.md`

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


---

# SOURCE FILE: `README_LANDING_REDESIGN_V1.txt`

BacaDulu Research — Landing Redesign V1

Files in this overlay:
- resources/views/pages/landing.blade.php
- resources/css/pages/landing.css

No migration, database, package, route, or environment changes are included.
No new dependency is required.

Install by extracting this folder and running:

robocopy "D:\Pkl\bacadulu-research-landing-redesign-v1" "D:\Pkl\bacadulu-research" /E /COPY:DAT /DCOPY:T /R:2 /W:2 /XD .git node_modules vendor storage\logs storage\framework\sessions storage\framework\cache storage\framework\views public\uploads /XF .env database.sqlite *.log

Then:

npm run build

For local development you may then run:

npm run dev

The redesign uses existing Laravel routes and existing CSS variables. It does not add external icon/font dependencies; SVG icons are inline in the Blade template.
