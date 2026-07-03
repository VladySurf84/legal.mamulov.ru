# AGENTS.md

This file contains project instructions for AI coding agents working in this
repository. Treat these instructions as repository-level guidance and follow
them unless a higher-priority user/developer/system instruction says otherwise.

## Project

This repository is the Laravel application for `legal.mamulov.ru`.

Keep it separate from adjacent projects such as `api.mamulov.ru`,
`mamulov.com`, `mamulov.ru`, and `LaravelMamulov`. Do not copy assumptions,
schemas, deploy paths, or runtime behavior between those projects without
checking the real code and environment.

## Runtime And Commands

- Local PHP is usually available at `C:\tools\php85\php.exe`.
- Prefer explicit commands such as:
  - `C:\tools\php85\php.exe -l path\to\File.php`
  - `C:\tools\php85\php.exe artisan test tests\Feature\SomeTest.php`
- Production deploy target is:
  - `/var/www/eugene/data/www/legal.mamulov.ru`
- Before any commit/deploy, inspect `git status --short --branch`.
- Never include unrelated local changes in a commit or deploy.
- Be careful with deploy scripts that stage everything. If the worktree has
  unrelated dirty files, stage only the intended files explicitly.

## Coding Rules

- Follow existing Laravel patterns in this codebase.
- Prefer controllers, services, commands, Blade components, and migrations that
  match nearby code.
- Keep changes tightly scoped to the requested behavior.
- Do not perform broad refactors unless they are necessary for the task.
- Do not revert user changes or unrelated dirty files.
- Use `rg`/`rg --files` for search before slower alternatives.
- Use structured parsing/APIs for structured data instead of fragile string
  manipulation when practical.

## Database And Accounting Architecture

The accounting model is organized conceptually into four layers:

- Acquisition layer: how data entered the system. Examples:
  `legal.api_sync_runs`, `legal.api_sync_requests`, imports, uploaded files.
- Source layer: raw facts from APIs, files, banks, EDO, VAT books, or manual
  input.
- Canonical layer: our current best version of business documents and parties.
  Examples: `legal.documents`, `legal.document_sources`,
  `legal.document_parties`.
- Interpretation layers: recalculable conclusions derived from source/canonical
  data. Examples: money, cash, VAT, accountant report links, obligations.

When adding or changing tables, decide which layer they belong to. If data is
important for audit, preserve the original source value first, then normalize or
interpret it separately.

Interpretation layers should be rebuildable when possible. Avoid making
calculated tables the only source of truth.

## UI Guidance

- Prefer existing Blade/UI components in `resources/views/components/ui`.
- For table-heavy operational screens, prefer dense, scannable interfaces over
  marketing-style cards.
- For Tailwind Plus examples, see `docs/tailwind-plus-components.md`.
- Use Tailwind Plus HTML examples as references, then adapt them into Blade.
- Do not introduce React/Vue for a page unless there is a deliberate decision to
  do so.
- For scheduler/run/request screens, favor master-detail or expandable-row
  patterns over decorative card layouts.

## Important Areas

- User access and permissions live around `App\Support\UserAccess`.
- Scheduler UI and API-sync run display live around `SchedulerController`,
  `ScheduleDefinitions`, and `legal.api_sync_runs` /
  `legal.api_sync_requests`.
- NSI SGR sync lives around `App\Services\Nsi\NsiSgrSyncService`.
- HH resume tooling lives around `HhResumeController`,
  `HhBrowserCaptureService`, `HhResumeBatchAnalysisService`, and
  `legal.hh_negotiations`.
- Bank/accounting flows often require tracing controller SQL, services, and DB
  writes before answering architecture questions.

## Testing

- Run the narrowest meaningful test for the touched behavior.
- For PHP edits, run `php -l` on changed PHP files when cheap.
- If a test cannot be run, state that clearly in the final response.
- Prefer adding or updating feature tests when behavior changes in controllers,
  commands, routes, permissions, or user-facing workflows.

## Production Checks

When checking production:

- Use read-only probes first.
- Keep temporary scripts small and remove them from both local and server after
  use.
- Do not leave debug files on the server.
- Do not expose secrets, tokens, private resumes, or personal data in final
  responses.

## Answering The User

- The user prefers concrete, code-backed answers.
- For "why did it fail?" questions, diagnose the real runtime/log/code path.
- For "is this the same thing?" questions, trace the actual controller, SQL,
  service, or data flow instead of answering from names alone.
- In Russian conversations, answer in Russian unless the user asks otherwise.
