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

## Core working rule: ANALYZE BEFORE EDITING

Never treat a supplied design as an automatic implementation order.

For any UI/UX task, first determine which mode the user is asking for:

### REVIEW / PLAN MODE
If the user asks to review, inspect, analyze, compare, audit, design, propose, or "lihat dulu":
- do not modify files;
- do not create files;
- do not run destructive commands;
- inspect the design source and current application first;
- return a design and technical assessment;
- wait for explicit implementation approval.

### IMPLEMENTATION MODE
Only edit after the user explicitly asks to implement, execute, fix, or continue implementation.

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

## DESIGN GATE — mandatory before implementation

Before coding from a design, perform these reviews:

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

### 6. Approval Gate
After the design, feasibility, maintainability, responsive, and motion assessments:
- present the recommendation;
- clearly identify what should be kept, changed, or rejected;
- wait for user approval before implementation when the task began as review/plan work.

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

When a design is provided, the first useful response should normally state:
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
