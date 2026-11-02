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
