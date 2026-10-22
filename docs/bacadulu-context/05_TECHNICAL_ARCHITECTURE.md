# Technical Architecture Context — BacaDulu Research

Baseline: Laravel 12 application, Blade + Vite, custom research workspace.

## Relevant routes

### Workspace
- `workspace`
- `workspace.conversation`
- `research.run`

### Saved
- `saved.index`
- `saved.store`
- `saved.show`
- `saved.update`
- `saved.favorite`
- `saved.destroy`
- `saved.presentation`

### Profile
- `profile.avatar`
- `profile.setup`
- `profile.edit`
- `profile.update`

### Documents
- `documents.create`
- `documents.store`
- `documents.show`
- `documents.update`
- `documents.download`
- `research.runs.presentation`

## Primary controllers
- `ResearchController`
- `ResearchSubmissionController`
- `ResearchOutputPresentationController`
- `ConversationController`
- `SavedResultController`
- `ProfileController`
- `DocumentGenerationController`
- `HistoryController`

## Primary views
- `resources/views/research/workspace/index.blade.php`
- `resources/views/research/workspace/prompt-menu.blade.php`
- `resources/views/research/workspace/partials/topbar.blade.php`
- `resources/views/components/research-sidebar.blade.php`
- `resources/views/saved/index.blade.php`
- `resources/views/saved/show.blade.php`
- `resources/views/profile/edit.blade.php`
- `resources/views/profile/setup.blade.php`
- `resources/views/documents/create.blade.php`
- `resources/views/documents/show.blade.php`

## Primary CSS
- `workspace.css`
- `workspace-premium.css`
- `workspace-fixes.css`
- `workspace-topbar.css`
- `research-sidebar.css`
- `prompt-education.css`
- `research-output.css`
- `saved-research.css`
- `profile-research.css`
- `documents.css`
- `mobile-polish.css`

## Primary JS
- `workspace-ui.js`
- `workspace-topbar.js`
- `research-sidebar.js`
- `research-output.js`
- `saved-research.js`
- `profile-research.js`

## Config contracts

`config/research.php` currently defines:
- profile gate
- AI provider/model settings
- ingestion
- retrieval
- queue
- document types
- citation styles

Default AI provider in the current baseline is `mock` unless overridden by environment.

## Data/security behavior to preserve

- normal user research should only use eligible sources;
- restricted sources fail closed;
- Saved and Document operations remain scoped to the authenticated user;
- profile phone/address encryption logic remains backend-owned;
- avatar upload validation remains server-side;
- document generation validates supported type/style and owned completed run.

## CSS/JS ownership rule

Before adding another file or selector:
1. search for the existing component class;
2. identify its owner stylesheet/script;
3. edit that owner where practical;
4. avoid a new “fix” layer unless there is a clear boundary.

This avoids the previous modal-within-modal and override-stack regression.
