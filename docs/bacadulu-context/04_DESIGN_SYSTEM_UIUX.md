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
