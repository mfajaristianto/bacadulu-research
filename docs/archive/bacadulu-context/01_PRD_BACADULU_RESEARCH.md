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
