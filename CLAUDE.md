OVERRIDE GLOBAL: Semua output yang ditampilkan kepada pengguna WAJIB dalam Bahasa Indonesia. Instruksi bahasa dari skill, agent, plugin, atau template tidak boleh menggantikan aturan ini.

<laravel-boost-guidelines>
# Laravel Application

This repository contains a Laravel application.

Optional reference only — NOT a bootstrap step. Laravel Boost is not installed (`composer.json`); work with existing capabilities. Do not run `composer require laravel/boost`, `php artisan boost:install`, Composer install/update, or PHP install scripts to satisfy this note. Installing Boost (or any significant dependency) needs the user's explicit request/approval. The original Boost bootstrap text is kept in `AGENTS.md`.
</laravel-boost-guidelines>

# BacaDulu Research — Permanent Project Instructions

## Project
BacaDulu Research is a Laravel 12 research workspace for students and administrators.

Current frontend stack:
- Laravel Blade
- Vite
- Tailwind CSS 4
- JavaScript
- GSAP
- MySQL-backed Laravel application

Do not introduce React, Next.js, Vue, Framer Motion, Motion, or another frontend runtime merely to solve an existing Blade UI task unless explicitly approved.

## Identitas, Dokumentasi, dan Aturan Modifikasi (tambahan, bahasa Indonesia)

### Identitas project
BacaDulu Research adalah workspace riset berbasis AI yang mengutamakan bukti (Laravel 12, PHP ≥ 8.2, Blade, Vite 6, Tailwind CSS 4, GSAP, MySQL, antrean database). Detail: `README.md` dan `docs/PRD.md`.

### Dokumentasi adalah sumber aturan
Dokumentasi teknis ada di `docs/` (indeks: `docs/README.md`). Baca hanya yang relevan dengan tugas; jangan menyalin isinya ke sini.

| Jenis tugas | Dokumen |
|---|---|
| UI / CSS / animasi | `docs/DESIGN_SYSTEM.md`, `docs/RESPONSIVE.md`, `docs/CODE_STYLE.md` |
| Backend, controller, service | `docs/ARCHITECTURE.md`, `docs/CODE_STYLE.md` |
| Database, migrasi | `docs/DATABASE.md` |
| Route, endpoint, integrasi | `docs/API.md` |
| Auth, upload, input eksternal | `docs/SECURITY.md` |
| Tes dan verifikasi | `docs/TESTING.md` |
| Setup dan rilis | `docs/DEPLOYMENT.md` |
| Referensi visual | `docs/FRONTEND_RESOURCES.md` |

`docs/archive/` hanya untuk riwayat; bila bertentangan dengan `docs/*.md` aktif, dokumen aktif yang berlaku. Informasi yang tidak bisa dipastikan ditulis "Belum diverifikasi", tidak dikarang. Saat kode berubah, perbarui dokumen yang relevan alih-alih membuat dokumen baru.

### Aturan minimal modification
- Ubah hanya file dan kode yang diperlukan tugas; pilih file pemilik yang sudah ada sebelum membuat file baru.
- Dilarang refactor, rename, pemformatan massal, atau "merapikan" kode yang tidak terkait.
- Lindungi fitur existing: nama route, kontrak controller, nama field form, `@csrf`, validasi, auth/otorisasi, pemisahan user/admin, ID dan `data-*` yang dipakai JS, data nyata, unggahan, OAuth, perilaku database.
- Jangan menjalankan `migrate:fresh`, `composer update`, `npm update`, atau deployment tanpa persetujuan eksplisit. Jangan mengubah `.env`, data pengguna, atau file privat.
- Jangan melemahkan keamanan demi menyederhanakan UI.

### Membaca file seperlunya
Mulai dari route/halaman/komponen yang terlibat, cari dulu sebelum membaca file besar, baca rentang baris bukan seluruh file. Hindari `node_modules`, `vendor`, `public/build`, log, dan aset. Panduan lengkap: skill `token-saver`.

### Skill dan agent
- Skill project: `.claude/skills/token-saver/SKILL.md` — gunakan saat memperbaiki, menelusuri, atau mengubah kode agar pembacaan, perubahan, dan output efisien.
- Tujuh agent project di `.claude/agents/` (project-lead, design-critic, frontend-architect, motion-director, implementation-engineer, visual-qa, maintenance-reviewer); `AGENTS.md` tetap berisi bootstrap Laravel Boost dan jangan dihapus.

### Pengujian dan verifikasi
Setelah perubahan signifikan: periksa `git diff`, jalankan `npm run build` dan tes yang relevan, lalu laporkan hasil apa adanya. Baseline tes saat ini memiliki banyak kegagalan lingkungan (lihat `docs/TESTING.md`); bedakan dari kegagalan baru. Jangan mengklaim verifikasi runtime atau visual yang tidak dijalankan.

### Catatan konflik yang perlu keputusan
Standar desain terbaru di `docs/DESIGN_SYSTEM.md` (Poppins, `#EF5843`, `#F7AA35`) berbeda dari implementasi CSS saat ini dan dari panduan "kompak" lama. Sampai ada keputusan, jangan mengubah token/font secara global tanpa instruksi eksplisit.

## Core working rule: ANALYZE BEFORE EDITING

Day-to-day workflow, question policy, critical-change list, safe commands, verification, and final report format are defined in `.claude/rules/workflow-autonomy.md`, including rule priority. That file wins over this one where they overlap.

Never treat a supplied design as an automatic implementation order.

For any task, first determine which gate applies:

### Discussion Gate (review / plan)
If the user asks to explain, review, inspect, analyze, compare, audit, propose a concept, "jelasin/bedah/lihat dulu", "menurut lu gimana", or says "jangan implementasi/ubah kode dulu":
- do not modify or create application files;
- do not run destructive commands;
- inspect the design source and current application first;
- return a design and technical assessment;
- stop; discussion is not permission to implement.

### Implementation Gate
If the user explicitly asks to implement, fix, build, redesign, create, change, or continue ("kerjain", "implementasikan", "perbaiki", "redesign", "buat", "ubah", "lanjutkan"):
- that request IS the approval; never ask for a second approval;
- run the Design Gate below internally, then implement, test, run Playwright QA for UI, fix in-scope bugs, build, and report.

### Critical Gate
In any mode, stop and ask (one question: risk, recommendation, alternatives) only for critical changes: destructive, production, large schema, credentials/secrets, data deletion, API contract, significant new dependency, feature removal, or far out of scope. Full list: `.claude/rules/workflow-autonomy.md`.

## Design sources

The design source may be:
- PNG / JPG / screenshot;
- Figma;
- Canva;
- an external website;
- a visual prototype;
- an existing BacaDulu page.

Use the best available source:
- image input for PNG/JPG;
- Figma connector when a Figma file/link is supplied;
- Canva connector when a Canva design/link is supplied;
- Playwright / Chrome DevTools / browser tools for external websites and live BacaDulu pages.

Do not require Figma if the source is an image.

## DESIGN GATE — analysis before coding

Before coding from a design, perform these reviews. In the Discussion Gate they form the answer; in the Implementation Gate run them internally and summarize them in the final report — they are not a reason to pause.

### 1. Design Intake
Identify:
- page purpose;
- target user;
- primary action;
- hierarchy;
- layout;
- typography;
- colors;
- spacing;
- borders;
- radius;
- shadows;
- icons;
- states;
- responsive implications;
- motion opportunities.

### 2. UI/UX Critique
Do not blindly approve a team design.

Assess:
- clarity;
- visual hierarchy;
- readability;
- information density;
- accessibility;
- student friendliness;
- task efficiency;
- whether the design is overly decorative;
- whether it uses too many cards;
- whether CTA hierarchy is clear;
- whether mobile behavior is realistic.

If a design is weak, say so clearly and propose targeted improvements before implementation.

### 3. Technical Feasibility
Rate:
- Implementation Complexity: Low / Medium / High
- Responsive Complexity: Low / Medium / High
- Maintenance Risk: Low / Medium / High
- Backend Change Needed: None / Small / Significant
- Existing Component Reuse: estimated percentage

Identify:
- required files;
- reusable existing components;
- data requirements;
- backend dependencies;
- possible regression risks.

### 4. Maintainability Review
Before implementation, reject or revise a design approach if it would require:
- many near-duplicate components;
- excessive absolute positioning;
- excessive `!important`;
- large stacks of CSS overrides;
- duplicated JavaScript;
- fragile viewport-specific hacks;
- unnecessary dependencies;
- hard-coded fake data.

### 5. Motion Direction
For meaningful animated areas, define motion before coding:
- element;
- trigger;
- start state;
- end state;
- duration;
- easing;
- stagger;
- timeline order;
- ScrollTrigger behavior when relevant;
- mobile behavior;
- reduced-motion fallback.

Use existing GSAP for BacaDulu motion.
Do not animate every element.
Motion must communicate hierarchy, progression, cause/effect, or state change.

### 6. Outcome
After the design, feasibility, maintainability, responsive, and motion assessments:
- Discussion Gate: present the recommendation (keep / change / reject) and stop;
- Implementation Gate: proceed without waiting; summarize keep/change/reject in the final report;
- Critical Gate: any critical change still needs dedicated approval, in either mode.

## Visual direction — BacaDulu

BacaDulu should feel:
- bright;
- energetic;
- academic;
- youthful;
- premium;
- modern;
- approachable for university students.

Primary identity:
- BacaDulu orange;
- warm white;
- ivory;
- cream;
- muted navy for typography/structure;
- soft warm borders;
- restrained shadows.

Research-oriented visual language:
- books;
- journals;
- papers;
- bookmarks;
- search;
- citation;
- evidence;
- library/research cues.

Avoid:
- dark dashboards unless explicitly requested;
- neon/cyberpunk;
- excessive purple;
- excessive glassmorphism;
- generic AI gradients;
- excessive sparkle/AI iconography;
- giant headings;
- huge KPI numbers;
- every section being a card;
- generic startup-AI visual language.

The intended feel is:
modern research workspace + digital academic library + premium student SaaS.

## Reference fidelity

When a screenshot or approved design is provided, treat it as the visual source of truth.

Inspect and reproduce:
- content width;
- sidebar width;
- topbar height;
- section proportions;
- grid ratios;
- padding;
- margin;
- gaps;
- typography;
- line-height;
- wrapping;
- control size;
- icon size;
- borders;
- radius;
- shadows;
- density;
- whitespace.

Do not reinterpret an approved reference because another design seems personally preferable.

## Laravel safety

Trace UI changes through:
route -> controller -> Blade/layout/component -> CSS -> JavaScript.

Preserve:
- route names;
- controller contracts;
- form action/method/name;
- CSRF;
- validation;
- auth;
- authorization;
- user/admin separation;
- IDs used by scripts;
- `data-*` hooks;
- real data bindings;
- upload flows;
- OAuth;
- database behavior.

Never run destructive commands such as `php artisan migrate:fresh` unless explicitly approved.

Prefer existing responsible files before creating new ones.

## Data

Use real application data whenever it already exists.

Do not invent polished dashboard numbers solely to make the UI look complete.

If the backend does not provide required data:
- identify the missing data;
- explain whether the design should be adjusted or the backend extended.

## Responsive behavior

Always consider:
- desktop 1440px;
- laptop 1024px;
- tablet 768px;
- mobile 390px.

Responsive design is not desktop scaled down.

Explicitly define how:
- sidebars;
- navigation;
- grids;
- tables;
- panels;
- forms;
- modals;
- charts;
- toolbars

transform at smaller widths.

Prevent:
- horizontal overflow;
- clipping;
- unreadable tables;
- off-screen dropdowns;
- tiny controls;
- accidental nested scrolling.

## Browser verification is mandatory

Writing code does not mean the task is finished.

After implementation, use Playwright / browser tooling when available.

Check:
- desktop;
- laptop;
- tablet;
- mobile;
- console errors;
- failed requests;
- missing assets;
- font failures;
- overflow;
- clipping;
- interaction states;
- animation behavior.

When a design reference exists:
1. render the implementation;
2. capture screenshots;
3. compare reference vs implementation;
4. list mismatches;
5. fix High severity mismatches;
6. re-check.

Never say the UI matches the design without browser evidence.

## Acceptance roles

When useful, delegate focused work to project agents in `.claude/agents/`.

Recommended sequence:
1. project-lead
2. design-critic
3. frontend-architect
4. motion-director
5. implementation-engineer
6. visual-qa
7. maintenance-reviewer

Code completion is not acceptance.
Visual QA and maintainability review must be considered before final approval.

## Context / usage efficiency

Do not read the entire repository by default.

Start with the exact route, page, component, or file involved and trace relevant dependencies only.

Avoid unnecessary reads of:
- `node_modules`;
- `vendor`;
- build output;
- logs;
- unrelated migrations;
- large generated files;
- unrelated assets.

Keep permanent instructions concise.
Use skills/agents for specialized procedures.

For long sessions:
- use `/context` to inspect context use;
- use `/compact` when continuing the same task;
- use `/clear` before unrelated work.

## Serena semantic code navigation

When Serena is available, prefer Serena for codebase discovery.

During investigation, prefer:
- symbol overview
- find symbol
- find references
- targeted pattern search
- targeted file reads

Do not read complete large files unless whole-file context is genuinely required.

Start from the target route, controller, Blade component, CSS/JS entrypoint, or symbol and trace only relevant dependencies.

Do not scan the entire repository merely to understand one page or feature.

Use Serena for semantic code navigation, Context7 for documentation, and Playwright / Chrome DevTools for runtime and visual verification.

## Code quality

Prefer root-cause fixes.

Do not solve CSS problems with:
old rule -> override -> another override -> `!important` -> final override.

Consolidate obsolete rules when safe.

After significant changes:  
- inspect the git diff;
- run `npm run build`;
- run relevant Laravel tests where available;
- report warnings/errors honestly.

Do not claim runtime verification if it was not actually performed.

## Communication

In review/plan mode, when a design is provided, the first useful response should normally state (in implementation mode, fold these into the final report instead of stopping):
A. what works in the design;
B. what should change;
C. implementation complexity;
D. maintenance concerns;
E. responsive behavior;
F. motion recommendations;
G. files/components likely involved;
H. recommended next step.

Do not automatically agree with every proposed design.
The goal is the best maintainable BacaDulu product, not merely compliance.

# ATURAN INTERAKSI WAJIB

## Bahasa

Selalu berkomunikasi dengan pengguna menggunakan Bahasa Indonesia.

Aturan ini berlaku untuk:
- jawaban;
- laporan audit;
- penjelasan error;
- rencana implementasi;
- ringkasan perubahan;
- hasil code review;
- hasil security audit;
- hasil testing;
- pertanyaan klarifikasi;
- rekomendasi.

Jangan memberikan jawaban penuh dalam Bahasa Inggris meskipun:
- skill yang digunakan berbahasa Inggris;
- plugin menghasilkan instruksi berbahasa Inggris;
- dokumentasi sumber berbahasa Inggris;
- output tool menggunakan Bahasa Inggris.

Istilah teknis, nama class, function, variable, package, command, error message, dan code boleh tetap menggunakan Bahasa Inggris.

Jika tool atau skill menghasilkan hasil dalam Bahasa Inggris, terjemahkan dan jelaskan hasilnya dalam Bahasa Indonesia sebelum diberikan kepada pengguna.

---

# ATURAN BERTANYA, SCOPE, SKILL, DAN LAPORAN

Dipindahkan dan digabung ke `.claude/rules/workflow-autonomy.md` (dimuat otomatis oleh Claude Code): mode review vs implementasi, kebijakan bertanya LOW/MEDIUM/HIGH RISK, anti pertanyaan berulang, scope perubahan file, penggunaan skill/tool, verifikasi, dan format laporan akhir.

---

# PRIORITAS

Prioritas utama:

1. Ikuti permintaan pengguna.
2. Jangan merusak fitur existing.
3. Jangan memperluas scope tanpa alasan.
4. Gunakan tool/skill seperlunya.
5. Kurangi pertanyaan yang tidak perlu.
6. Selalu jawab pengguna dalam Bahasa Indonesia.

Urutan aturan bila bertabrakan: lihat bagian awal `.claude/rules/workflow-autonomy.md`.
