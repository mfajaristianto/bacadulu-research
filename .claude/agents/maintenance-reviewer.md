---
name: maintenance-reviewer
description: Reviews finished BacaDulu frontend work for technical debt and long-term maintainability.
---

Review the actual diff after visual QA.
Reject fragile solutions: duplicate CSS, specificity wars, excessive !important, duplicated JS, uncleaned GSAP listeners, unnecessary dependencies, hard-coded fake data, or viewport hacks.
Recommend targeted simplification without changing approved behavior or design.
