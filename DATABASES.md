# Pyrodactyl — Dashboard Modularization & Docker/Container Dashboard Plan

Purpose
-------
This document captures the design and implementation plan we discussed for making dashboards modular and adding a reusable Docker/Container dashboard type that can be used for databases, WordPress, generic containers, and similar services.

Goals
- Make dashboard routing pluggable so new dashboard types are added with minimal edits.
- Provide a small, consistent contract each dashboard implements (router + pages).
- Reuse existing components where possible (console, stat blocks, backups, DB list, etc).
- Keep backend changes minimal: accept the new dashboard type and show it in admin UIs.

What I inspected
- Frontend:
  - `resources/scripts/routers/DashboardRouterFactory.tsx`
  - `resources/scripts/routers/ServerRouter.tsx` (game server router)
  - `resources/scripts/routers/DatabaseRouter.tsx` (database router example)
  - `resources/scripts/components/dashboard/DashboardContainer.tsx` (account-level dashboard listing)
  - server components under `resources/scripts/components/server/*` (console, database-dashboard)
- Backend:
  - Blade admin UI: `resources/views/admin/nests/*.blade.php`, `resources/views/admin/eggs/*.blade.php`
  - Request validators: `app/Http/Requests/Admin/Nest/StoreNestFormRequest.php`, `app/Http/Requests/Admin/Egg/EggFormRequest.php`
  - Models: `app/Models/Egg.php`, `app/Models/Nest.php`, `app/Models/Server.php` (dashboard type accessors already implemented)

High-level design
-----------------
1. Dashboard registry (frontend)
   - A single registry module mapping known dashboard_type keys to lazy-loaded router components and optional metadata (label, icon).
   - This registry allows static imports that Vite can analyze, but centralizes registration so adding types is simply: add registry entry + router files.

2. Dashboard router modules
   - Each dashboard type gets its own router module (same pattern as `DatabaseRouter` and `ServerRouter`).
   - Put routers under `resources/scripts/routers/` (top-level) or `resources/scripts/routers/dashboards/`.
   - A router does:
     - Use `ServerContext` to fetch the server on mount.
     - Render `MainSidebar` + `MainWrapper` with NavLinks and `Routes` mapping to page components.
     - Use `PermissionRoute` and `Can` components as needed.
   - Page components live under `resources/scripts/components/server/<type>-dashboard/`.

3. Dashboard contract (minimal)
   - Export default route component (Router).
   - Pages accept server data from `ServerContext`.
   - Optional metadata export: { key, label, icon } helpful for admin UI or dashboard selector later.

4. Reuse shared components
   - Reuse existing `ServerConsoleContainer.tsx`, `StatBlock.tsx`, backup components, and database components where applicable.
   - Create small shared components if multiple dashboard types need similar UI (CredentialsPanel, VolumeList, PortBindings).

Concrete changes (files to add / update)
----------------------------------------
Frontend
- Add registry
  - `resources/scripts/routers/dashboardRegistry.ts`
    - Exports typed map like:
      - `'game-server'` -> `lazy(() => import('@/routers/ServerRouter'))`
      - `'database'` -> `lazy(() => import('@/routers/DatabaseRouter'))`
      - `'docker'` -> `lazy(() => import('@/routers/DockerRouter'))` (new)
- Update factory
  - `resources/scripts/routers/DashboardRouterFactory.tsx`
    - Replace the switch with registry lookup. Still fallback to `ServerRouter` for unknown types.
- New dashboard router(s)
  - `resources/scripts/routers/DockerRouter.tsx` (copy/adapt `DatabaseRouter.tsx` style)
  - `resources/scripts/routers/dockerRoutes.ts` (optional: route definitions array)
- New components
  - `resources/scripts/components/server/docker-dashboard/OverviewContainer.tsx`
  - `resources/scripts/components/server/docker-dashboard/ConsoleContainer.tsx` (may reuse `ServerConsoleContainer.tsx`)
  - `resources/scripts/components/server/docker-dashboard/LogsContainer.tsx`
  - `resources/scripts/components/server/docker-dashboard/SettingsContainer.tsx` (envs, ports, volumes)
  - Optional specific folders for specialized stacks, e.g. `docker-dashboard/wordpress/`

Backend (minimal)
- Add `docker` to allowlist for dashboard_type in validators:
  - `app/Http/Requests/Admin/Nest/StoreNestFormRequest.php` — update `in:` list
  - `app/Http/Requests/Admin/Egg/EggFormRequest.php` — update `in:` list
- Add option to admin blades:
  - `resources/views/admin/nests/new.blade.php`
  - `resources/views/admin/nests/view.blade.php`
  - `resources/views/admin/eggs/new.blade.php`
  - `resources/views/admin/eggs/view.blade.php`
  - Add `<option value="docker">Docker (Container)</option>` alongside existing options.
- Update badge/display mapping:
  - `resources/views/admin/nests/index.blade.php` — include `docker` in badge mapping.

Implementation notes & constraints
---------------------------------
- Vite static import constraint: avoid fully dynamic imports keyed by arbitrary strings. Use a static registry map where each possible router import is explicit so the bundler can include chunks.
- DashboardRouterFactory should remain responsible for retrieving server data via `ServerContext` (like it already does).
- Keep server-scoped API calls and permissions inside the dashboard routers (use `PermissionRoute`).
- Reuse server components and share helper utilities under `components/server/*` or `components/dashboard/common`.

Phased implementation plan (recommended)
---------------------------------------
Phase 1 — Backend small changes
  - Update validators to accept `docker`.
  - Add blade select options in admin blades.
  - Confirm admins can save `dashboard_type = docker` for nests/eggs.

Phase 2 — Registry + Factory refactor
  - Add `dashboardRegistry.ts`.
  - Update `DashboardRouterFactory.tsx` to use registry.
  - Ensure existing behavior and fallbacks remain.

Phase 3 — DockerRouter skeleton
  - Create `DockerRouter.tsx` (copy `DatabaseRouter.tsx` and adapt).
  - Add `dockerRoutes.ts` that defines route list and component import placeholders.
  - Add route placeholders that render basic stubs (Overview, Console, Logs, Settings).

Phase 4 — Pages & components
  - Implement `OverviewContainer`, `LogsContainer`, `SettingsContainer` etc.
  - Reuse `ServerConsoleContainer.tsx` for console if appropriate.
  - Wire APIs for container-specific actions (ports, env, volumes) — reuse or create new endpoints as required.

Phase 5 — Testing & polish
  - Manual testing with a Nest/Egg configured to `dashboard_type = docker`.
  - Add unit tests for `DashboardRouterFactory` and router components if you have test infra.
  - Add docs: README/CONTRIBUTING notes for adding new dashboard types.

Testing checklist
- DashboardRouterFactory renders:
  - `game-server` for that type
  - `database` for that type
  - `docker` for that type
  - fallback behavior for unknown types
- Admin UI accepts `docker` for nests/eggs and persists to DB.
- `DockerRouter` pages load server info via `ServerContext`.
- Shared components render correctly within the new dashboard.

Risks & mitigations
- Bundler/runtime import issues:
  - Use explicit registry imports, not runtime string-based dynamic imports.
- Permission mismatch between dashboard types:
  - Use `PermissionRoute` and `Can` guards in routers like `DatabaseRouter` does.
- UI duplication:
  - Resist copy-pasting entire components; favor small shared components and composition.

Next steps (pick one)
- I can implement Phase 2 (dashboard registry + DashboardRouterFactory refactor) first — small, low-risk.
- Or I can implement Phase 3 (DockerRouter skeleton) with stub pages that you can iterate on.
- Or I can apply the backend blade & validator updates now.

Tell me which phase you want me to start and I will:
- create the files,
- run diagnostics,
- open a PR-style patch here (or apply changes directly if you prefer).

Notes
-----
I did not change any code in this step — this file is the plan. If you want, I can proceed to implement any of the phases above.

---

Phases — Detailed breakdown, checklists and acceptance criteria
--------------------------------------------------------------

Below are the phases split into more detailed tasks with explicit checklists, commands to run for local validation, acceptance criteria, estimated effort, and rollback notes. Use this as a step-by-step playbook while you implement.

Phase 1 — Backend small changes (ETA: 15–30 minutes)
  Tasks
  - Edit `app/Http/Requests/Admin/Nest/StoreNestFormRequest.php` and ensure `dashboard_type` validation includes `'docker'`.
  - Edit `app/Http/Requests/Admin/Egg/EggFormRequest.php` and ensure `dashboard_type` validation includes `'docker'`.
  - Edit blade files to show option:
    - `resources/views/admin/nests/new.blade.php`
    - `resources/views/admin/nests/view.blade.php`
    - `resources/views/admin/eggs/new.blade.php`
    - `resources/views/admin/eggs/view.blade.php`
    - Add `<option value="docker">Docker (Container)</option>`
  - Update badge mapping:
    - `resources/views/admin/nests/index.blade.php` — include `docker` => display name and class.
  - Run php linter/validation (optional) and ensure app compiles.

  Commands / local checks
  - php artisan config:clear
  - php artisan view:clear
  - Open admin UI and create/update a Nest/Egg with `docker` selected.
  - Use tinker to check saved value:
    - `php artisan tinker`
    - `\App\Models\Nest::find(<id>)->dashboard_type` (should be `docker`)
  Acceptance criteria
  - Admin form saves `dashboard_type = docker` without validation errors.
  - Blade shows option in the dropdown.
  Rollback
  - Revert the three edited files if something breaks.

Phase 2 — Registry + DashboardRouterFactory refactor (ETA: 30–60 minutes)
  Tasks
  - Create `resources/scripts/routers/dashboardRegistry.ts` with a static map:
    - `game-server` -> `lazy(() => import('@/routers/ServerRouter'))`
    - `database` -> `lazy(() => import('@/routers/DatabaseRouter'))`
    - `docker` -> `lazy(() => import('@/routers/DockerRouter'))` (router file may not exist yet; keep as placeholder)
  - Update `resources/scripts/routers/DashboardRouterFactory.tsx`:
    - Import the registry and use `registry[dashboardType]` to render the router inside `<Suspense>`.
    - Keep fallback to `ServerRouter` if dashboardType not in registry.
  - Add small metadata types to the registry for future UI usage (label/icon).
  - Ensure TypeScript types compile.

  Commands / local checks
  - npm/yarn dev build: `pnpm run dev` or `npm run dev` (depending on your project)
  - Inspect compiled client to ensure no missing import errors for `DockerRouter` (if declared as lazy but missing, ensure TypeScript still builds; if it errors at runtime, guard registry entries until file exists).
  Acceptance criteria
  - DashboardRouterFactory renders using the registry without behavioral changes.
  - No runtime import errors for registry entries that exist.
  Rollback
  - Restore DashboardRouterFactory.tsx to the previous switch statement.

Phase 3 — DockerRouter skeleton (ETA: 60–120 minutes)
  Tasks
  - Create `resources/scripts/routers/DockerRouter.tsx`:
    - Copy `DatabaseRouter.tsx` as a starting point.
    - Adapt nav items to: Overview, Console, Logs, Settings, (optional) Images, (optional) Backups.
    - Use `Server
Context` to fetch server, `PermissionRoute`, `MainSidebar`, `MainWrapper`, and `<Routes>` to mount page components.
  - Create `resources/scripts/routers/dockerRoutes.ts` with route definitions:
    - Example:
      - `/server/:id` -> `OverviewContainer`
      - `/server/:id/console` -> `ConsoleContainer`
      - `/server/:id/logs` -> `LogsContainer`
      - `/server/:id/settings` -> `SettingsContainer`
  - Add placeholder components under `resources/scripts/components/server/docker-dashboard/` that render simple headings.

  Commands / local checks
  - Rebuild frontend: `pnpm run build` or run dev server.
  - Visit `/server/<uuid>` for a server configured with `dashboard_type = docker`. Confirm the skeleton loads and nav items highlight.
  Acceptance criteria
  - `DockerRouter` loads without errors and renders stub pages.
  - Navigation links are functional and switch routes inside the router.
  Rollback
  - Remove DockerRouter.tsx and dockerRoutes.ts and revert DashboardRegistry if necessary.

Phase 4 — Pages & components (ETA: variable; small MVP 2–6 hours)
  Tasks
  - Implement `OverviewContainer.tsx`:
    - Display server metadata: name, image, ports, volumes, env vars.
    - Show `StatBlock` usage when relevant.
  - Implement `ConsoleContainer.tsx`:
    - Prefer reusing `ServerConsoleContainer.tsx`. If server console endpoints differ, adapt as needed.
  - Implement `LogsContainer.tsx`:
    - Reuse existing logs viewer components (if available) or create a thin wrapper for tailing logs.
  - Implement `SettingsContainer.tsx`:
    - Allow editing environment variables, port mappings, volumes where applicable.
    - Add cautionary UI and permission checks.
  - Wire to backend APIs:
    - Reuse existing server endpoints (if available) or create new API endpoints in the backend for container-specific features.
  - Add integration to backups if supported (reuse backup components).
  - Add tests for pages if you maintain frontend tests.

  Commands / local checks
  - Start backend + frontend dev servers.
  - Create/boot a container-backed server and exercise each page: change env vars, open console, view logs, adjust ports.
  Acceptance criteria
  - Core features (view metadata, console, logs, basic settings) are functional.
  - Permissions respected and errors surfaced properly.
  Rollback
  - Keep changes behind feature flags or ensure you can revert commits.

Phase 5 — Testing, QA & polish (ETA: 1–3 hours)
  Tasks
  - Manual QA cross-browser and mobile.
  - Add unit / integration tests for factory and router-permissions if possible.
  - Add documentation:
    - `resources/scripts/routers/README.md` documenting how to add a new dashboard type (registry + router contract).
  - Update admin copy, icons, and UX polish (tooltips, labels).
  - Optional: add admin preview or icon in the nests/eggs list.

  Commands / local checks
  - Run frontend tests (`pnpm test` / `npm test`) if available.
  - Run any backend tests.
  Acceptance criteria
  - No regressions on existing dashboards.
  - Documentation present for future contributors.

Acceptance criteria (global)
----------------------------
- New `docker` dashboard type is selectable in admin UIs and stored correctly.
- Frontend routing uses a registry and loads dashboard routers by type.
- DockerRouter loads and provides the core set of pages (Overview, Console, Logs, Settings) with shared component reuse.
- Permissions and error handling align with existing dashboard patterns.

Developer hints & tips
----------------------
- Keep the registry file small and explicit — explicit lazy imports are friendlier to bundlers than dynamic import-by-string patterns.
- When copying `DatabaseRouter.tsx`, remove database-specific assumptions (DB icons, DB-only nav item checks) and add container-specific nav entries.
- If you expect many dashboard types in future, consider exporting types & registry helper functions and adding a small README in the routers folder describing the contract.

Rollback plan
-------------
- Use git branches for each phase. If a released change causes issues, revert the merge commit for that branch.
- For backend blade/validation changes, revert the PHP files edited.
- For frontend registry or router changes, revert the JS/TS files and re-run the build.

Questions / decisions for you
-----------------------------
1. Dashboard key name: should we use `docker` or `container` as the canonical `dashboard_type` key? (Current suggestion: `docker`.)
2. Do you want WordPress, MySQL, and other stacks mapped to the same `docker` dashboard and then have stack-specific sub-pages (e.g., `wordpress/*`) or do you prefer distinct dashboard types like `wordpress`?
3. Would you like me to implement Phase 2 now (registry + factory) or jump to Phase 3 (DockerRouter skeleton)? I recommend Phase 2 first.

If you confirm, I will begin implementing the selected phase and produce a PR-style patch with the exact file diffs for review.
