# Pyrodactyl — Databases Dashboard (egg-driven) Implementation Plan

Purpose
-------
This document replaces the previous generic "docker" plan and describes a focused, phased implementation plan for a Databases dashboard. The dashboard will be egg-driven: it should surface and manage database servers created from the eggs in `database/Seeders/eggs/databases`. The goal is a focused UI that consolidates database operations (list databases, query, credentials, backups, logs, settings) and reuses existing components where possible.

Scope
-----
- Build a dedicated Databases dashboard (server-scoped) that appears for servers whose egg/nest `dashboard_type` resolves to `database`.
- Reuse and extend existing database components already in the repo (`resources/scripts/components/server/database-dashboard/*`).
- Ensure admin/egg seeding and egg metadata (from `database/Seeders/eggs/databases`) drive the behavior/UI where appropriate (e.g., supported features).
- Keep the change modular and follow existing router/component patterns (`DatabaseRouter.tsx` style).

High-level UX & functional requirements
--------------------------------------
- Dashboard shows when a server's effective `dashboard_type` is `database`.
- Sidebar entries: Overview, Databases (list), Tables / Browser, Query Interface, Logs, Settings, Backups (if supported).
- Overview shows server metadata (image, version, ports, host info), quick credentials, and health/stats.
- Databases page lists created databases & credentials (with rotate password action).
- Query interface allows running SELECT queries (read-only by default), with pagination and syntax highlighting.
- Logs page shows daemon/log tails where available.
- Settings provide env vars, port mappings and user management actions (rotate credentials).
- Backup integration: list backups, restore, download (reuse backups UI).
- Permissions: Actions gated by same `Can`/`PermissionRoute` patterns used elsewhere.

Assumptions & repo cues
-----------------------
- Eggs for database types live in `database/Seeders/eggs/databases` (these eggs contain metadata & features we can use).
- The server model already exposes an effective `dashboard_type` via the accessor (see `app/Models/Server.php`).
- There is already a `DatabaseRouter.tsx` in `resources/scripts/routers/` and `resources/scripts/components/server/database-dashboard/` components — we will iterate on and extend these.
- Admin blades and request validators already allow `database` as a `dashboard_type`.

Phases (egg-driven)
-------------------

Phase 0 — Repo & eggs review (ETA: 30–60 min)
  Goal
  - Confirm which database eggs exist and what metadata/features they expose (supported features, scripts, ports, env names).
  Tasks
  - Inspect `database/Seeders/eggs/databases` to list eggs and note fields:
    - `name`, `uuid`, `author`
    - `features` (e.g., `databases`, `backups`, `remote-access`)
    - `docker_images` / `startup` hints
  - Map egg-level features to dashboard capabilities (e.g., if `features` contains `databases`, show Databases page).
  Deliverable
  - A short mapping file (or table in this doc) that lists each egg + the UI features it should enable.
  Acceptance criteria
  - You can answer: "This egg supports X/Y/Z features" for each seeded database egg.

Phase 1 — Backend: egg metadata & API readiness (ETA: 1–2 hours)
  Goal
  - Make sure server API responses contain the egg/nest metadata required by the frontend to render database-specific UI.
  Tasks
  - Ensure the server transformer (`ServerTransformer` or `app/Http/Controllers/Api/Client/Servers/Wings/ServerController::index`) returns:
    - `egg` and nested `egg.features` or `egg.attributes` required by client.
    - `server` metadata: port mappings, allocations, env vars (if present).
  - If not already present, extend the API to expose:
    - List of DBs on the server (`GET /api/client/servers/:uuid/databases`) — may already exist under `resources/scripts/components/server/databases` features.
    - Query endpoint for simple SQL execution (`POST /api/client/servers/:uuid/databases/query`) with permission checks and rate limiting.
    - Credential rotation endpoint (`POST /api/client/servers/:uuid/databases/:name/rotate`).
  - Ensure API responses are consistent across eggs (return structure should be generic).
  Commands / local checks
  - Use `php artisan tinker` to inspect an egg: `\App\Models\Egg::where('name','like','%mysql%')->first()`.
  - Curl or Postman the server API endpoints above to verify responses.
  Acceptance criteria
  - Required data available to client via existing or new endpoints; errors returned in panel-friendly format.

Phase 2 — Router & routing wiring (ETA: 30–60 minutes)
  Goal
  - Ensure Dashboard routing displays database router when `dashboard_type` is `database` and wires pages.
  Tasks
  - Confirm `resources/scripts/routers/DashboardRouterFactory.tsx` routes `database` to `DatabaseRouter` (already present).
  - If using a registry pattern, add a `database` entry to `dashboardRegistry.ts` (if implemented).
  - Ensure `DatabaseRouter.tsx` mounts routes for:
    - `/server/:id` (Overview)
    - `/server/:id/databases` (Databases list)
    - `/server/:id/tables` (Table Browser)
    - `/server/:id/query` (Query Interface)
    - `/server/:id/logs` (Logs)
    - `/server/:id/settings` (Settings)
  - Use `PermissionRoute` and `Can` guards for actions.
  Commands / local checks
  - Build and visit `/server/<uuid>` for servers whose egg is in `database/Seeders/eggs/databases`.
  Acceptance criteria
  - The router renders and sub-routes are reachable; 404s only for missing pages.

Phase 3 — Pages & components (ETA: 4–8 hours; incremental rollout recommended)
  Goal
  - Implement and polish pages specific to database management, reusing existing components where possible.
  Tasks & notes
  - Overview (`OverviewContainer.tsx`)
    - Show server metadata (name, image, port, version), quick actions (Open console, open backups), and recent activity/statistics (re-use `StatBlock`/`StatGraphs`).
    - Show egg-provided hints (e.g., default DB user names, connection strings).
  - Databases list (`DatabaseListContainer.tsx`)
    - Show list of databases created on the server with actions:
      - View credentials (masked)
      - Rotate password (with confirmation)
      - Delete DB (with safety prompts)
    - Reuse existing components in `resources/scripts/components/server/databases/*`.
  - Table browser (`TableBrowserContainer.tsx`)
    - Browse tables for a selected database, show column types, row counts.
    - Use pagination and lightweight preview (not full export).
  - Query interface (`QueryInterfaceContainer.tsx`)
    - Text editor with syntax highlighting (reuse existing monaco/codemirror integrations if present).
    - Allow read-only queries by default; require explicit permission for writes (or show clear warnings).
    - Show results table + count + timing.
  - Logs (`DatabaseLogsContainer.tsx`)
    - Tail server logs relevant to DB engine if available.
    - Provide download/clear options if permitted.
  - Settings (`DatabaseSettingsContainer.tsx`)
    - Manage environment variables, port mapping, backups settings.
    - Show connection string templates and quick copy buttons.
  - Backups integration
    - Reuse existing backup UI.
    - Ensure database backups are discoverable and restorable.
  UI/UX considerations
  - Use `Can` for feature gating (rotate credentials, delete DBs).
  - Provide clear warnings for destructive operations.
  - Respect egg `features` to hide/show actions (e.g., if `backups` feature missing, hide Backups).
  Commands / local checks
  - Start frontend dev server and manually exercise all pages.
  Acceptance criteria
  - Users can list, query, view tables, and manage DB credentials as allowed by permissions.

Phase 4 — Egg-driven UX: dynamic behavior based on egg features (ETA: 2–4 hours)
  Goal
  - Use egg metadata to adapt the UI automatically per egg type (e.g., MySQL vs Postgres vs Redis).
  Tasks
  - Parse egg `features` or other metadata loaded into `server.egg` and:
    - Show connection string templates using the egg's `docker_image` / `startup` hints.
    - If egg lists supported DB engines/versions, expose the version on the Overview page.
    - If egg provides custom scripts (install, migrations), show appropriate UI buttons (e.g., run migration, rebuild).
  - Provide an extension point: per-egg UI fragments (e.g., `components/server/database-dashboard/eggs/<egg-slug>/ExtraSettings.tsx`) that are loaded if present.
  Acceptance criteria
  - Dashboard adapts to egg-level features without code rewiring; badges/hints reflect egg-level metadata.

Phase 5 — Security, permissions, and rate-limiting (ETA: 2–4 hours)
  Goal
  - Make sure database-sensitive operations are gated and protected.
  Tasks
  - Backend:
    - Ensure query endpoint enforces maximum execution time and permissions; optionally disable dangerous statements by default.
    - Add rate-limiting/CSRF protections for credential rotations and write queries.
  - Frontend:
    - Show clear UI warnings and require confirmations for destructive actions.
    - Mask credentials until revealed (with a reveal and copy control).
  Acceptance criteria
  - No privileged action can be performed without proper permission; logs/auditing recorded where available.

Phase 6 — Tests, QA, and documentation (ETA: 2–6 hours)
  Goal
  - Test flows, add docs, and ensure maintainability.
  Tasks
  - Manual QA:
    - Create a server using an egg from `database/Seeders/eggs/databases`.
    - Walk through Overview → Databases → Table Browser → Query → Backups → Settings.
  - Automated tests:
    - Add unit tests for `DatabaseRouter` rendering and permissions (if you have test infra).
    - Add API integration tests for the database endpoints (query, rotate).
  - Docs:
    - Add `resources/scripts/routers/README.md` with the dashboard contract (how to add pages, how egg metadata is used).
    - Add a short `docs/databases-dashboard.md` describing how egg authors can expose features the dashboard will surface.
  Acceptance criteria
  - Tests pass; docs present; behavior consistent across eggs.

Testing checklist (concrete)
----------------------------
- API level:
  - `GET /api/client/servers/:uuid` returns `egg.features` and server allocations.
  - `GET /api/client/servers/:uuid/databases` returns list of DBs.
  - `POST /api/client/servers/:uuid/databases/query` returns results for simple SELECT.
  - `POST /api/client/servers/:uuid/databases/:name/rotate` rotates password and invalidates old creds.
- Frontend:
  - Dashboard shows for `dashboard_type = database`.
  - Overview shows connection string and quick copy.
  - Databases list shows DBs and allows rotate/delete where permitted.
  - Query interface runs queries and shows results; destructive queries are blocked or warned.
  - Backups UI integrates and shows DB-specific backups.
- Security:
  - Only authorized users can run queries or rotate credentials.
  - Actions are rate-limited and audited/logged.

Acceptance criteria (summary)
-----------------------------
- Servers created from eggs in `database/Seeders/eggs/databases` display a rich Databases dashboard.
- The dashboard supports listing databases, browsing tables, running queries (safely), managing credentials, viewing logs and backups.
- The UI dynamically adapts based on egg-provided features.
- Existing server dashboards (game-server, generic database routers) remain unaffected.

Developer notes and recommended file map
---------------------------------------
- Routers
  - `resources/scripts/routers/DatabaseRouter.tsx` — ensure routes exist and mount pages
  - (optional) `resources/scripts/routers/databaseRoutes.ts` — centralize route metadata
- Components (likely already present; extend as needed)
  - `resources/scripts/components/server/database-dashboard/OverviewContainer.tsx`
  - `resources/scripts/components/server/database-dashboard/DatabaseListContainer.tsx`
  - `resources/scripts/components/server/database-dashboard/TableBrowserContainer.tsx`
  - `resources/scripts/components/server/database-dashboard/QueryInterfaceContainer.tsx`
  - `resources/scripts/components/server/database-dashboard/DatabaseLogsContainer.tsx`
  - `resources/scripts/components/server/database-dashboard/DatabaseSettingsContainer.tsx`
  - `resources/scripts/components/server/databases/*` — existing helper components (rotate password, row, list)
- Backend APIs to confirm/implement
  - `GET /api/client/servers/:uuid` (already used by ServerController)
  - `GET /api/client/servers/:uuid/databases`
  - `POST /api/client/servers/:uuid/databases/query`
  - `POST /api/client/servers/:uuid/databases/:name/rotate`
  - Backup-related endpoints (reuse existing backup API)
- Egg/seed source
  - `database/Seeders/eggs/databases` — authoritative list of supported database eggs and their metadata.

Open questions for you
----------------------
1. Do you want each seeded egg to have a distinct "skin" (egg-specific UI) or should the dashboard be a single UI that adapts via egg `features` (recommended)?
2. Which eggs in `database/Seeders/eggs/databases` require special query behavior (e.g., No-SQL vs SQL engines)?
3. Do you prefer the Query interface to allow write statements for staff/admins only, or disabled entirely for end users?

Next step suggestion
--------------------
If you confirm the egg-driven approach and answer the open questions, I recommend starting Phase 0 (eggs review) and Phase 1 (API readiness). I can then produce a PR-style patch for Phase 2 (routing wiring) or Phase 3 (page skeletons) based on your preference.
