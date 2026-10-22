# BacaDulu Claude Team — Installation

1. Back up the current project:
   - `CLAUDE.md`
   - `.claude/` if it already exists.

2. Replace project-root `CLAUDE.md` with the provided `CLAUDE.md`.
   - The existing `<laravel-boost-guidelines>...</laravel-boost-guidelines>` bootstrap block has been preserved at the top.
   - Custom BacaDulu rules are below it.

3. Copy `.claude/agents/` into:
   `D:\Pkl\bacadulu-research\.claude\agents\`

4. Restart Claude Code.

5. Verify:
   - `/plugin`
   - `/mcp`
   - ask: `List the project agents available for BacaDulu UI work.`

6. Keep previously created custom skills separately in `.claude/skills/` or upload them in Claude Skills.

Recommended plugin set:
- frontend-design
- playwright
- context7
- claude-code-setup
- code-review
- code-simplifier
- security-guidance
- chrome-devtools
- playground
- CLAUDE.md Management

Design-source connectors:
- Figma: use the already-authenticated `claude.ai Figma` connector if present; do not authenticate a duplicate Figma connector.
- Canva: install/authenticate only if Canva source files will be used.
- GitHub: optional but useful when repository/PR workflows are needed.

MCP cleanup:
If `/mcp` shows both a User MCP `context7` and `plugin:context7:context7`, remove the User MCP:
`claude mcp remove context7 --scope user`

Then restart Claude Code and verify `/mcp`.
