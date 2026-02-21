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
