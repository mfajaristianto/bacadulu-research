# BacaDulu Research — Chat + Prompt Flow

This build turns the authenticated research experience into a Notebook-style chat workspace while retaining the separate admin console.

## User flow
1. Google login / normal login.
2. If profile completion is below `RESEARCH_PROFILE_GATE` (default 80), the user is sent to Profile Setup.
3. Once the gate is met, `/dashboard` redirects directly to `/workspace`.
4. The workspace shows recent conversations, a category selector, and an admin-prepared prompt picker opened with the `+` button.
5. Users type only their research question. They do not provide an AI system prompt.
6. The server selects only `published` + `public` documents in the chosen active category and records them as sources for the AI run.
7. The conversation and messages are persisted for the Recent Conversations sidebar.

## Admin flow
Admin remains under the configured path (default `panel-adminbaca-research`). The admin console manages:
- Internal Library
- Prompt Library
- Research tool / prompt activation

Only published public internal sources are eligible for normal-user retrieval. Restricted sources fail closed until explicit access control is introduced.

## New database tables
- `research_conversations`
- `research_messages`

Run once on an existing local database:

```powershell
php artisan migrate
php artisan optimize:clear
```

No `.env` is included in the build.
