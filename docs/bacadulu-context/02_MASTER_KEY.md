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
