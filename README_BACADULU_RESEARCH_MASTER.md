# BACADULU RESEARCH — MASTER CONTEXT ALL-IN-ONE

This is the one-file context intended to be pasted/read first by an implementation agent.

Order of authority: latest user instruction → visual references → current codebase → master prompt → current docs → legacy docs.

# MASTER KEY — BacaDulu Research

Status: **ACTIVE SOURCE OF TRUTH**
Version: 2.0
Baseline code: `BacaResearch_4research_sidebar_checkpoint.zip`
Purpose: one operational key for future design, coding, review, and regression work.

> `MASTER KEY` here means **context key / decision key**, not an API secret, credential, or encryption key.

## 1. Precedence order

When two documents conflict, use this order:

1. **Latest explicit user instruction in the active conversation**
2. **Latest user-provided visual/reference material**, including the 10 Instagram references registered below
3. **Current working codebase / uploaded `4research(1).zip` baseline**
4. **Master Prompt — Design, Audit, Security, dan Revisi Codebase**
5. **This MASTER KEY and current PRD**
6. `DESIGN_BACADULU_RESEARCH_V3.md`
7. Existing README/build/setup/fix notes
8. Older experiments, prototypes, mockups, or abandoned ZIPs

Do not revive an older design merely because it exists in a legacy README.

## 2. Product identity

BacaDulu Research is an **evidence-first AI Research Workspace**.

It is not:
- a generic chatbot;
- a raw internal-library browser;
- a dashboard made from unrelated SaaS cards;
- a landing-page visual language pasted into authenticated research screens.

Research logic should feel layered:

`Question → Source → Chunk → Evidence → Synthesis → Insight → Document`

Evidence and provenance are first-class product concepts.

## 3. Locked design language

Canonical colors from the existing design specification:
- Orange: `#F26B4F`
- Navy: `#17172B`
- Ivory: `#F8F3EB`
- Paper: `#FFFCF7`
- Teal: `#21A6A0`
- Gold: `#D7A84A`

Authenticated workspace UI must remain **compact and operational**. Do not apply giant landing-page typography to Workspace/Profile/Saved/Document.

Operational sizing direction:
- Page title: ~30–40px depending on viewport/reference
- Modal title: ~24–32px
- Card title: ~13–18px
- Body: ~11–14px
- Meta: ~9–11px
- Card padding: ~14–22px
- Radius: 8 / 12 / 16px; 22px only for showcase-like surfaces when justified

These are guardrails, not arbitrary fixed values. Final dimensions follow the visual reference.

## 4. Sidebar is LOCKED

Do not redesign the sidebar again unless fixing a real integration bug.

Required behavior:
- shared across Workspace, Saved, Profile, Document;
- Recent displays **5 items by default**;
- `Show more` / `Show less` expands and collapses the remainder;
- no History item in primary sidebar navigation;
- History route/page may remain in code;
- no bottom promo card “Research lebih dalam / dengan evidence-aware AI”;
- mobile behavior uses drawer/off-canvas;
- touch/keyboard behavior remains accessible.

Main entries:
- Dashboard
- Dokumen Saya
- Saved
- Profil

## 5. Workspace lock

Workspace must preserve the current research shell and become visually aligned with the supplied reference instead of being reinvented.

Required:
- active Research Tool is obvious but compact;
- question and AI result have clear hierarchy;
- Sources / Evidence / Citations are visible;
- Saved action is discoverable and actually connected to Saved;
- Create Document entry is clear;
- raw Markdown markers such as `##` must not leak into the final presentation layer;
- next research action is explicit;
- no giant generic hero inside authenticated workspace.

## 6. Prompt Picker lock

Prompt picker must follow the reference direction:
- compact list;
- numbered badge;
- meaningful SVG/icon;
- English title;
- Indonesian name/subtitle;
- short description;
- select arrow/action;
- `Apa ini?` secondary action.

Canonical prompt education fields:
- English term
- Indonesian meaning
- short description
- when to use
- when not to use
- example
- expected output
- explicit Use Prompt action

Do not build a nested modal inside another modal.

## 7. “Apa ini?” modal lock

This is a major regression guard.

Do:
- centered, compact desktop modal;
- workspace remains visually present behind backdrop;
- clear heading + Indonesian meaning;
- concise explanation sections;
- example prompt;
- expected output;
- CTA `Gunakan Prompt`;
- close/back action;
- mobile can use a sheet-like layout if needed.

Do not:
- use near-full-screen desktop modal;
- use oversized title/illustration;
- create huge white empty areas;
- show content as a raw AI template;
- introduce a second nested modal shell.

## 8. Saved lock

Saved is not decorative storage. It is a continuation surface.

Required:
- filters/tabs for all / AI answer / document as supported by data;
- search;
- grouped list;
- item type, title, research tool/prompt, tags/metadata, time;
- `Buka` resumes relevant Workspace/conversation context;
- rename/update uses existing Saved update contract;
- delete removes the Saved bookmark, not the underlying research run;
- right rail contains **real data-driven visualization**, not hard-coded fake values;
- chart can summarize count by Research Tool/prompt/topic;
- subtle continuous motion is allowed, but chart information must also be available as text/labels;
- recent save activity can sit below chart.

Existing backend to reuse:
- `SavedResultController`
- `SavedResult::aiRun`
- `SavedResult::documentGenerations`
- existing Saved routes

## 9. Profile lock

Profile follows the provided profile reference as closely as practical:
- hero card with avatar, identity, professional/institution context, orange visual accent;
- only **one global `Edit Profil` action** at upper right;
- stat cards;
- 2-column information cards on desktop, stacked on mobile;
- no repeated edit button per card.

Profile must be universal, not campus-only.

Supported organization contexts include:
- education
- company
- government
- nonprofit
- research
- independent
- other

Information model can surface:
- name
- phone
- date of birth
- address
- bio
- professional role
- institution/organization
- organization type
- division
- department
- organization location
- research interests
- public profile URL

Avatar update must render from the same authoritative user/avatar source in Profile, topbar, Workspace, Saved, and Document shell.

Security:
- existing stored password is never displayed in plaintext;
- show/hide is only for current/new password input values;
- sensitive credential changes should retain current backend protection/re-auth rules.

## 10. Create Document lock

Required flow:

`Research Result → Create Document → Document Type → Citation Style → Review → Confirm → Generate → Generated Result → Download`

Citation styles currently supported by config:
- APA 7
- IEEE
- Harvard

The UI must briefly explain the distinction before generate.

Minimum education copy:
- APA 7: author-date; common in social science, business, accounting, education
- IEEE: numbered citation `[1]`; common in engineering/computing/technology
- Harvard: author-date; exact formatting can vary by institution/publisher

Before generation:
- settings are visible and understandable;
- user receives an explicit confirmation such as `Apakah Anda sudah yakin?`;
- if not sure, UI points back to the editable settings.

After generation:
- do not show only generic labels `PDF` and `DOCX`;
- use explicit actions `Download PDF` and `Download DOCX`;
- explain PDF as fixed-layout and DOCX as editable;
- generated content must render semantic headings, not raw `##` Markdown.

## 11. Responsive lock

Desktop:
- preserve reference density;
- sidebar/shell stable;
- right rail where applicable;
- modal compact.

Tablet:
- reduce spacing carefully;
- right rail may stack below;
- drawer/collapse where appropriate.

Mobile:
- sidebar drawer;
- single-column cards;
- prompt picker/modal optimized for touch;
- minimum practical target around 44px for important controls;
- no horizontal overflow;
- do not merely shrink desktop.

## 12. Motion lock

Allowed:
- short fade/slide;
- smooth drawer;
- subtle chart motion;
- selection feedback;
- reduced-motion fallback.

Avoid:
- bounce as default;
- long theatrical sequences;
- animation that competes with research reading;
- fast perpetual rotation.

## 13. Visual reference registry

| ID | Type | URL | Role |
|---|---|---|---|
| IG-01 | Reel | https://www.instagram.com/reel/DeebsTrx_3e/ | Visual/motion reference; inspect manually before implementation |
| IG-02 | Reel | https://www.instagram.com/reel/DecN_gbx_eD/ | Visual/motion reference; inspect manually before implementation |
| IG-03 | Reel | https://www.instagram.com/reel/Dea5G_fP7WX/ | Visual/motion reference; inspect manually before implementation |
| IG-04 | Reel | https://www.instagram.com/reel/DedJ2BTD_IJ/ | Visual/motion reference; inspect manually before implementation |
| IG-05 | Post | https://www.instagram.com/p/DebnbytmvVt/ | Visual/motion reference; inspect manually before implementation |
| IG-06 | Post | https://www.instagram.com/p/DeVERjTgMRE/ | Visual/motion reference; inspect manually before implementation |
| IG-07 | Post | https://www.instagram.com/p/DeE0-thCDFp/ | Visual/motion reference; inspect manually before implementation |
| IG-08 | Reel | https://www.instagram.com/reel/DdbLJv1jAI0/ | Visual/motion reference; inspect manually before implementation |
| IG-09 | Post | https://www.instagram.com/p/DeHgoP8kxyV/ | Visual/motion reference; inspect manually before implementation |
| IG-10 | Post | https://www.instagram.com/p/Dd9IBiPFmI_/ | Visual/motion reference; inspect manually before implementation |

Important: the URLs are registered as official visual/motion references. Their exact visual content must be inspected in a browser or provided as screenshots/video captures before claiming pixel-level reproduction. Do not invent what is inside an unavailable Instagram asset.

## 14. Technical contracts that must not be broken

Current known route names:
- `workspace`
- `workspace.conversation`
- `saved.index`
- `saved.store`
- `saved.show`
- `saved.update`
- `saved.favorite`
- `saved.destroy`
- `profile.edit`
- `profile.update`
- `documents.create`
- `documents.store`
- `documents.show`
- `documents.update`
- `documents.download`
- `saved.presentation`
- `research.runs.presentation`

Preserve:
- authorization/ownership checks;
- CSRF;
- auth/session logic;
- retrieval restriction to eligible published/public sources;
- existing research generation contracts;
- existing `.env`;
- database data unless a migration is explicitly approved.

## 15. File ownership map

Primary files likely involved:
- `resources/views/components/research-sidebar.blade.php`
- `resources/views/research/workspace/index.blade.php`
- `resources/views/research/workspace/prompt-menu.blade.php`
- `resources/views/research/workspace/partials/topbar.blade.php`
- `resources/views/saved/index.blade.php`
- `resources/views/profile/edit.blade.php`
- `resources/views/documents/create.blade.php`
- `resources/views/documents/show.blade.php`
- `resources/css/pages/workspace*.css`
- `resources/css/pages/prompt-education.css`
- `resources/css/pages/saved-research.css`
- `resources/css/pages/profile-research.css`
- `resources/css/pages/documents.css`
- `resources/js/workspace-ui.js`
- `resources/js/saved-research.js`
- `resources/js/profile-research.js`
- `resources/js/research-output.js`

Before adding new CSS/JS, confirm an existing file does not already own that component.

## 16. Change protocol

Before implementation:
1. inspect actual current file;
2. identify owner CSS/JS;
3. make a checkpoint;
4. avoid unrelated refactor.

After implementation:
1. `php artisan optimize:clear`
2. `npm run build`
3. test desktop/tablet/mobile
4. test keyboard/escape/focus
5. test save/resume/avatar/document paths
6. regression-check unaffected pages

Never run `migrate:fresh` on a database containing needed data.


# PRD — BacaDulu Research

Version: 2.0
Status: implementation-ready product context
Product type: Evidence-first AI Research Workspace

## 1. Problem

Research users need more than a chat answer. They need an environment that helps them:
- choose an analysis approach;
- understand what the tool/prompt means;
- trace answer claims to evidence and sources;
- save useful research results;
- resume prior research context;
- manage a professional profile;
- turn validated research output into a document.

The current codebase already implements much of the workflow, but visual consistency, prompt education, Saved utility, profile presentation, and document-generation guidance require alignment.

## 2. Product goal

Make BacaDulu Research feel like one coherent research product from question to document:

`Question → Evidence → Analysis → Insight → Saved Context → Document`

Success means a user can understand where an answer came from, what to do next, and how to continue without losing context.

## 3. Non-goals

This scope does not require:
- rebuilding authentication from scratch;
- replacing the AI/retrieval architecture;
- opening unrestricted access to the internal source repository;
- introducing a subscription/paywall;
- redesigning unrelated public/admin pages;
- changing route/database contracts without demonstrated need.

## 4. Core user journeys

### Journey A — Research
1. User opens Workspace.
2. User selects or retains a Research Tool.
3. User asks a research question.
4. Result presents answer, evidence/source context, and next action.
5. User can save the result or create a document.

### Journey B — Understand Research Tool
1. User opens Prompt/Research Tool picker.
2. User sees compact list with title, Indonesian meaning, description and icon.
3. User opens `Apa ini?`.
4. User reads meaning, use case, expected output and example.
5. User selects `Gunakan Prompt`.

### Journey C — Saved
1. User saves a useful result.
2. Saved page reflects the new item.
3. Summary/chart updates from real Saved data.
4. User can open the item.
5. The app resumes the relevant conversation/workspace context.

### Journey D — Profile
1. User opens Profile.
2. Profile shows hero, stats, personal, research, affiliation and security information.
3. User clicks the single global `Edit Profil`.
4. User updates profile/avatar/institution information.
5. Updated avatar appears consistently in shared shell surfaces.

### Journey E — Document
1. User chooses Create Document from a completed research result.
2. User selects document type and citation style.
3. App explains APA 7 / IEEE / Harvard.
4. User reviews settings.
5. User confirms generation.
6. Generated page presents explicit `Download DOCX` and `Download PDF` actions.

## 5. Functional requirements

### FR-01 Shared Research Shell
- consistent topbar/sidebar across authenticated research screens;
- Recent shows five by default;
- Show more/less expands recent list;
- no primary History nav item;
- mobile drawer available;
- avatar source consistent.

### FR-02 Workspace
- show active tool;
- show question/result;
- show evidence/source/citation context;
- Save action is visible and functional;
- Create Document action is visible;
- no raw Markdown headings in final presentation.

### FR-03 Prompt Picker
Each active prompt/tool must support, where data exists:
- English name;
- Indonesian name;
- description;
- icon;
- selection action;
- `Apa ini?`.

### FR-04 Prompt Education Modal
Display:
- meaning;
- when to use;
- when not to use;
- expected output;
- example prompt;
- Use Prompt action.

### FR-05 Saved
- list current user’s saved results;
- support filters/search;
- differentiate AI answer vs document;
- summarize Saved data;
- visualize real Saved distribution by useful dimension;
- resume source conversation/workspace;
- preserve ownership checks.

### FR-06 Profile
- single Edit entry;
- support universal organization types;
- display profile completion context without forcing student/campus identity;
- allow avatar update using current backend;
- email/password changes use secure existing flows;
- profile avatar synchronizes visually across shell.

### FR-07 Document
- supported types come from `config/research.php`;
- supported citation styles come from config;
- review/confirm step occurs before final generate;
- generated screen exposes explicit download actions;
- semantic HTML presentation replaces raw Markdown markers.

## 6. Current configured document types

- Research Article
- Literature Review
- Research Proposal
- Research Background
- Research Gap
- Theory Review
- Variable Definition
- Hypothesis Development
- Conference Paper
- Book Chapter
- Research Report

## 7. Current configured citation styles

- APA 7
- IEEE
- Harvard

## 8. UX requirements

- reference-first fidelity;
- authenticated UI stays compact;
- one shell, many screens;
- no unnecessary cards;
- restrained semantic color;
- meaningful SVG iconography;
- evidence visually prioritized;
- short, purposeful motion;
- responsive layouts intentionally rearrange, not merely shrink.

## 9. Accessibility

- visible keyboard focus;
- close dialogs with Escape;
- dialog focus order/trap as appropriate;
- semantic labels;
- 44px-ish touch targets for important mobile actions;
- charts retain text/labels;
- reduced motion support.

## 10. Data/security requirements

- user can only mutate/read owned private resources;
- Saved actions retain user ownership filters;
- document generation only uses owned completed runs;
- restricted sources remain inaccessible to normal user research unless explicit access control is added;
- no secrets in client-side code or docs;
- avatar uploads retain MIME/size validation;
- `.env` is never bundled into distribution ZIPs.

## 11. Acceptance criteria

A release is not accepted if:
- `Apa ini?` is oversized on desktop;
- prompt picker has nested modal behavior;
- Profile repeats Edit on each card;
- Saved chart is hard-coded;
- avatar differs between Profile and Workspace after refresh;
- generated result displays raw `##`;
- PDF/DOCX actions are ambiguous;
- mobile has horizontal overflow;
- sidebar changes visual direction again without explicit instruction.


# Visual Reference Registry — BacaDulu Research

This file registers the 10 references supplied by the user as design/motion inputs.

| ID | Type | URL | Implementation use |
|---|---|---|---|
| IG-01 | Reel | https://www.instagram.com/reel/DeebsTrx_3e/ | Visual/motion reference; inspect manually before implementation |
| IG-02 | Reel | https://www.instagram.com/reel/DecN_gbx_eD/ | Visual/motion reference; inspect manually before implementation |
| IG-03 | Reel | https://www.instagram.com/reel/Dea5G_fP7WX/ | Visual/motion reference; inspect manually before implementation |
| IG-04 | Reel | https://www.instagram.com/reel/DedJ2BTD_IJ/ | Visual/motion reference; inspect manually before implementation |
| IG-05 | Post | https://www.instagram.com/p/DebnbytmvVt/ | Visual/motion reference; inspect manually before implementation |
| IG-06 | Post | https://www.instagram.com/p/DeVERjTgMRE/ | Visual/motion reference; inspect manually before implementation |
| IG-07 | Post | https://www.instagram.com/p/DeE0-thCDFp/ | Visual/motion reference; inspect manually before implementation |
| IG-08 | Reel | https://www.instagram.com/reel/DdbLJv1jAI0/ | Visual/motion reference; inspect manually before implementation |
| IG-09 | Post | https://www.instagram.com/p/DeHgoP8kxyV/ | Visual/motion reference; inspect manually before implementation |
| IG-10 | Post | https://www.instagram.com/p/Dd9IBiPFmI_/ | Visual/motion reference; inspect manually before implementation |

## Usage rule

These references are part of the source-of-truth stack. When implementing a screen:
1. inspect the relevant reference visually;
2. extract composition, spacing, hierarchy, SVG/icon style, interaction and motion;
3. adapt copy/data to BacaDulu Research;
4. preserve BacaDulu brand tokens;
5. do not invent details that were not visible.

## Current access note

The URLs have been recorded exactly. In the current automated web environment, Instagram did not expose readable page content, so this documentation intentionally does **not** fabricate per-link visual descriptions. For pixel-level reproduction, use the actual browser view or user-provided screenshots/video captures.

## Reference fidelity checklist

For every implementation:
- layout geometry compared to reference;
- card density compared;
- typography scale compared;
- icon/SVG stroke/size compared;
- color roles compared;
- borders/radius/shadows compared;
- modal dimensions compared;
- desktop/tablet/mobile checked separately;
- animations checked for duration, direction and easing;
- no unrelated visual invention.


# Design System & UI/UX Rules — BacaDulu Research

## North star

Editorial Research Workspace × Modern Product Interface.

Character:
premium, editorial, academic, warm, intelligent, calm, restrained, intentional.

## Canonical tokens

### Colors
- `--bd-orange: #F26B4F`
- `--bd-navy: #17172B`
- `--bd-ivory: #F8F3EB`
- `--bd-paper: #FFFCF7`
- `--bd-teal: #21A6A0`
- `--bd-gold: #D7A84A`

### Spacing
`4, 8, 12, 16, 24, 32, 40, 48, 64, 80, 96, 128`

### Radius
- small 8px
- medium 12px
- large 16px
- showcase-only max 22px unless justified

### Shadow
Soft, low-contrast, contextual. No glow-first design.

### Motion
Short, purposeful, smooth deceleration, interruptible where practical, reduced-motion fallback.

## Product-density rule

Landing and authenticated workspace are different contexts.

Landing may use display typography and dramatic composition.
Workspace/Saved/Profile/Document are productivity surfaces and must stay compact.

Avoid:
- 60px page titles in normal authenticated screens;
- giant empty hero space;
- excessively tall buttons;
- modal taking nearly the entire desktop viewport;
- card-on-card-on-card nesting.

## Screen-specific rules

### Workspace
Hierarchy:
`Active Tool → Question → Answer → Evidence → Sources → Next Action → Composer`

### Saved
Hierarchy:
`Heading/Filters → Saved List → Real Summary/Chart → Activity`

### Profile
Hierarchy:
`Profile Hero → Stats → Personal / Research / Affiliation / Security`

### Create Document
Hierarchy:
`Research Context → Settings → Citation Education → Review → Confirmation → Generate → Downloads`

## Prompt Picker
Compact list, readable at a glance.
Secondary info action is visually quieter than selecting the tool.

## “Apa ini?”
Desktop should look like a focused explanation dialog, not a new page.
Mobile can become a sheet, but content remains structured and scannable.

## Anti-slop gate
Reject a screen when:
- it looks like a generic AI SaaS;
- it uses decorative cards without product reason;
- headings are oversized to fake hierarchy;
- motion exists only for spectacle;
- icons are random;
- copy is generic;
- evidence looks like an afterthought;
- mobile is simply scaled desktop;
- output exposes raw Markdown.


# Technical Architecture Context — BacaDulu Research

Baseline: Laravel 12 application, Blade + Vite, custom research workspace.

## Relevant routes

### Workspace
- `workspace`
- `workspace.conversation`
- `research.run`

### Saved
- `saved.index`
- `saved.store`
- `saved.show`
- `saved.update`
- `saved.favorite`
- `saved.destroy`
- `saved.presentation`

### Profile
- `profile.avatar`
- `profile.setup`
- `profile.edit`
- `profile.update`

### Documents
- `documents.create`
- `documents.store`
- `documents.show`
- `documents.update`
- `documents.download`
- `research.runs.presentation`

## Primary controllers
- `ResearchController`
- `ResearchSubmissionController`
- `ResearchOutputPresentationController`
- `ConversationController`
- `SavedResultController`
- `ProfileController`
- `DocumentGenerationController`
- `HistoryController`

## Primary views
- `resources/views/research/workspace/index.blade.php`
- `resources/views/research/workspace/prompt-menu.blade.php`
- `resources/views/research/workspace/partials/topbar.blade.php`
- `resources/views/components/research-sidebar.blade.php`
- `resources/views/saved/index.blade.php`
- `resources/views/saved/show.blade.php`
- `resources/views/profile/edit.blade.php`
- `resources/views/profile/setup.blade.php`
- `resources/views/documents/create.blade.php`
- `resources/views/documents/show.blade.php`

## Primary CSS
- `workspace.css`
- `workspace-premium.css`
- `workspace-fixes.css`
- `workspace-topbar.css`
- `research-sidebar.css`
- `prompt-education.css`
- `research-output.css`
- `saved-research.css`
- `profile-research.css`
- `documents.css`
- `mobile-polish.css`

## Primary JS
- `workspace-ui.js`
- `workspace-topbar.js`
- `research-sidebar.js`
- `research-output.js`
- `saved-research.js`
- `profile-research.js`

## Config contracts

`config/research.php` currently defines:
- profile gate
- AI provider/model settings
- ingestion
- retrieval
- queue
- document types
- citation styles

Default AI provider in the current baseline is `mock` unless overridden by environment.

## Data/security behavior to preserve

- normal user research should only use eligible sources;
- restricted sources fail closed;
- Saved and Document operations remain scoped to the authenticated user;
- profile phone/address encryption logic remains backend-owned;
- avatar upload validation remains server-side;
- document generation validates supported type/style and owned completed run.

## CSS/JS ownership rule

Before adding another file or selector:
1. search for the existing component class;
2. identify its owner stylesheet/script;
3. edit that owner where practical;
4. avoid a new “fix” layer unless there is a clear boundary.

This avoids the previous modal-within-modal and override-stack regression.


# Implementation Roadmap — Locked Scope

This roadmap reflects the latest direction. Sidebar is considered visually locked.

## Phase 0 — Safety checkpoint
- backup current working tree;
- preserve `.env`;
- preserve database;
- record `git status`;
- do not run `migrate:fresh`.

## Phase 1 — Workspace Result
- inspect current Blade/CSS/JS;
- align result density to reference;
- make Saved action discoverable;
- preserve source/evidence rail;
- remove raw Markdown presentation;
- keep next-action flow.

## Phase 2 — Prompt Picker
- use current `prompt-menu.blade.php`;
- preserve data attributes and existing prompt selection logic;
- refine rows, iconography, spacing, selection state;
- avoid nested modal.

## Phase 3 — “Apa ini?” Modal
- use existing prompt education fields/data;
- compact desktop modal;
- mobile sheet;
- Use Prompt action;
- keyboard close/focus;
- no oversized hero.

## Phase 4 — Saved
- reuse `SavedResultController` data;
- filters/search/grouping;
- real summary counts;
- data-driven chart by available tool/topic data;
- animated but accessible;
- `Buka` resumes relevant conversation;
- overflow actions remain ownership-safe.

## Phase 5 — Profile
- reference-faithful hero;
- one Edit button;
- universal organization fields;
- avatar uses same rendering source as shared shell;
- security section is concise and safe.

## Phase 6 — Create Document
- preserve current `documents.store` contract;
- expose types/styles from config;
- add citation education;
- review/confirmation;
- generated view uses semantic HTML;
- explicit PDF/DOCX download cards.

## Phase 7 — Responsive
- desktop fidelity;
- tablet layout transitions;
- mobile drawer/stack/sheet;
- no horizontal overflow.

## Phase 8 — Regression
Test:
- login/session remains intact;
- Workspace conversation resume;
- Saved create/update/delete/favorite;
- Profile update/avatar;
- Document generate/show/download;
- build assets;
- keyboard/escape/focus;
- reduced motion.

## Definition of done
A screen is done only when visual fidelity, real data integration, responsive behavior, and existing feature behavior all pass together.


# Security & Change Guardrails — BacaDulu Research

## Non-negotiable

Do not weaken existing security to simplify UI implementation.

## Authentication/session
- preserve current auth/session flow;
- do not expose admin auth through user session;
- preserve CSRF protection;
- do not add secrets to Blade/JS.

## Authorization
- user-owned resources must remain user-scoped;
- Saved operations must not permit User A to access User B items;
- Document generation must resolve an owned completed run;
- profile update applies only to current authenticated user.

## XSS/output
- keep normal Blade escaping for profile titles, saved titles, tags, prompt metadata;
- sanitize any rich content path deliberately;
- do not render arbitrary user HTML to solve formatting.

## Upload
Avatar:
- MIME whitelist;
- image validation;
- size cap;
- generated/isolated filenames;
- safe public path behavior.

## Credentials
- never display stored password plaintext;
- password visibility toggle affects only the input being edited;
- email/password change should preserve current confirmation/re-auth behavior.

## Sources
- normal user retrieval remains constrained to permitted source status/visibility;
- restricted source access fails closed.

## Database
- never run `migrate:fresh` on data that matters;
- migration only when required and after backup;
- UI-only changes should not invent database migrations.

## Environment
Never package:
- `.env`
- API keys
- OAuth secrets
- admin password
- production DB dump
- private tokens

## Change discipline
- smallest safe change first;
- no broad refactor during visual correction;
- no dead-code deletion below high confidence;
- preserve route contracts unless explicitly approved.


# Setup, Build & Recovery — Consolidated

## Baseline
Laravel 12 project, custom Blade/Vite UI.

## Preserve
- existing `.env`
- `.git`
- production data
- working OAuth configuration
- uploaded avatars/user files

## Safe dependency/build sequence
From the project root:

```powershell
composer install
npm install
php artisan optimize:clear
npm run build
```

For local development, run the normal Laravel server and Vite dev server as required by the project.

## Database
Do **not** run:

```powershell
php artisan migrate:fresh
```

on a database whose data must be kept.

Run normal migrations only when there are actual unapplied migrations and after backup.

## UI replacement safety
Recommended:
1. create Git checkpoint or folder backup;
2. extract update to a separate folder;
3. compare affected files;
4. apply selectively;
5. clear Laravel caches;
6. build assets;
7. test routes.

## Rollback
Best: restore from Git checkpoint.

Before replacement:
```powershell
git status
git add .
git commit -m "checkpoint before BacaDulu UI update"
```

Inspect changes:
```powershell
git diff
```

Restore a specific file:
```powershell
git restore path\to\file
```

Do not use destructive mirroring casually when new work exists after the backup.

## Production build notes retained from legacy docs
- no `.env` should be shipped;
- production assets may be built into `public/build`;
- preserve secure admin path/environment values;
- profile avatars use the project’s current configured location;
- keep security headers/rate limits in place.


# UI/UX Acceptance Checklist — BacaDulu Research

## Shared shell
- [ ] Sidebar visual not redesigned
- [ ] Recent = 5 default
- [ ] Show more / Show less works
- [ ] History not in main sidebar
- [ ] Bottom promo absent
- [ ] Mobile drawer works
- [ ] Avatar consistent

## Workspace
- [ ] Active Research Tool compact
- [ ] Save action visible
- [ ] Sources/evidence clear
- [ ] Create Document clear
- [ ] No raw `##`
- [ ] No oversized internal hero

## Prompt Picker
- [ ] List matches reference density
- [ ] Meaningful SVGs/icons
- [ ] English + Indonesian naming
- [ ] Description readable
- [ ] `Apa ini?` visible
- [ ] Selection does not break existing JS

## “Apa ini?”
- [ ] Compact desktop dimensions
- [ ] No nested modal
- [ ] Meaning / use / output / example shown
- [ ] `Gunakan Prompt` works
- [ ] Escape/backdrop/close works
- [ ] Mobile layout intentional

## Saved
- [ ] Uses real Saved data
- [ ] Chart is data-driven
- [ ] Chart changes when Saved data changes
- [ ] Motion subtle/reduced-motion compatible
- [ ] `Buka` resumes research context
- [ ] Answer/document types clear
- [ ] Rename/delete/favorite ownership preserved

## Profile
- [ ] One Edit button only
- [ ] Universal organization model
- [ ] Hero/reference composition
- [ ] Personal/research/affiliation/security cards
- [ ] Avatar sync after refresh
- [ ] Existing password never displayed

## Create Document
- [ ] Document type clear
- [ ] APA 7 explained
- [ ] IEEE explained
- [ ] Harvard explained
- [ ] Review/confirmation before generate
- [ ] Generated page has `Download DOCX`
- [ ] Generated page has `Download PDF`
- [ ] Download differences explained
- [ ] Semantic output, no raw Markdown

## Responsive/accessibility
- [ ] Desktop
- [ ] Tablet
- [ ] Mobile
- [ ] No horizontal overflow
- [ ] Keyboard focus visible
- [ ] Escape closes dialogs
- [ ] Important mobile targets practical
- [ ] Reduced motion respected

## Regression
- [ ] `npm run build`
- [ ] `php artisan optimize:clear`
- [ ] Workspace routes
- [ ] Saved routes
- [ ] Profile routes
- [ ] Document routes
- [ ] Auth/session unchanged


## Legacy documentation traceability

All previous README/MD source content is preserved separately in `10_ALL_PREVIOUS_READMES_VERBATIM.md`.