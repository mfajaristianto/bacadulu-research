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
