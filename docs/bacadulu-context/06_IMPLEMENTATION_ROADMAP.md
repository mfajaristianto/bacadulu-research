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
