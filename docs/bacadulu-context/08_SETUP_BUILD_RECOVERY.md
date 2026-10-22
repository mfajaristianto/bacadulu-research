# Setup, Build & Recovery — Consolidated

## Baseline
Laravel 12 project, custom Blade/Vite UI.

## Preserve
- existing `.env`
- `.git`
- production data
- working OAuth configuration
- uploaded avatars/user files

## Safe dependency/build sequence
From the project root:

```powershell
composer install
npm install
php artisan optimize:clear
npm run build
```

For local development, run the normal Laravel server and Vite dev server as required by the project.

## Database
Do **not** run:

```powershell
php artisan migrate:fresh
```

on a database whose data must be kept.

Run normal migrations only when there are actual unapplied migrations and after backup.

## UI replacement safety
Recommended:
1. create Git checkpoint or folder backup;
2. extract update to a separate folder;
3. compare affected files;
4. apply selectively;
5. clear Laravel caches;
6. build assets;
7. test routes.

## Rollback
Best: restore from Git checkpoint.

Before replacement:
```powershell
git status
git add .
git commit -m "checkpoint before BacaDulu UI update"
```

Inspect changes:
```powershell
git diff
```

Restore a specific file:
```powershell
git restore path\to\file
```

Do not use destructive mirroring casually when new work exists after the backup.

## Production build notes retained from legacy docs
- no `.env` should be shipped;
- production assets may be built into `public/build`;
- preserve secure admin path/environment values;
- profile avatars use the project’s current configured location;
- keep security headers/rate limits in place.
