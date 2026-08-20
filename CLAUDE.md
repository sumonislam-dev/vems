# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project overview

VEMS is a Vehicle/Employee Management System built on the Laravel + React starter kit: **Laravel 12** (PHP 8.2) backend with **Inertia.js 2** driving a **React 19 + TypeScript** frontend (no separate REST/JSON API layer — controllers return `Inertia::render()` with page props). Styling is Tailwind v4 with shadcn/ui-style components. Auth/RBAC uses `spatie/laravel-permission`.

Core domain: trip/transport management for a company fleet — vehicles, drivers (who are just `User` records), routes/stops, vendors, factories, departments, and a fairly elaborate trip lifecycle with audit logging, recurring trips, passenger check-in/out, and vehicle/route reassignment history.

## Commands

**Local dev** (runs PHP server + queue listener + Vite together):
```bash
composer dev
```
For SSR: `composer dev:ssr`.

**Backend only:**
```bash
php artisan serve
php artisan migrate            # apply migrations (MySQL in dev, see .env)
php artisan migrate:fresh --seed
php artisan test                # or: composer test / vendor/bin/pest
php artisan test --filter=TestName
vendor/bin/pest tests/Feature/DriverEnforcementTest.php   # single file
vendor/bin/pint                 # PHP code style (Laravel Pint)
```
Tests run against an in-memory SQLite DB (`phpunit.xml`), not the dev MySQL DB — no setup needed to run the suite.

**Frontend only:**
```bash
npm run dev                     # Vite dev server
npm run build                   # production build
npm run build:ssr
npm run lint                    # eslint --fix
npm run format / format:check   # prettier
npm run types                   # tsc --noEmit
```

**Permissions/roles bootstrap** (creates roles + permissions, assigns Super Admin to first user):
```bash
php artisan setup:permissions
```
Related one-off commands in `app/Console/Commands/`: `CheckUserPermissions`, `BackfillVehicleAssignments`, `ShowRouteData`.

## Architecture

### Request flow
Every page is a Laravel route (`routes/web.php`, plus `routes/auth.php` / `routes/settings.php`) handled by a controller that calls `Inertia::render('pages/path', [...props])`. There is no client-side router or fetch-based API for CRUD — Inertia forms (`useForm` + `post`/`put`) submit directly to these routes. A handful of plain JSON endpoints exist under `/api/*` (e.g. `/api/stops`, `/api/drivers/available`) for autocomplete/lookup use from React components.

`HandleInertiaRequests` (`app/Http/Middleware/HandleInertiaRequests.php`) shares `auth.user`, `auth.permissions`, `auth.roles`, flash messages, and Ziggy route data on every request — this is how the frontend gets the current user's roles/permissions without a separate call.

### Auth & permissions
- Roles/permissions via `spatie/laravel-permission` (`Role`, `Permission` models, `HasRoles` trait on `User`).
- `AppServiceProvider::boot()` registers a global `Gate::before` that short-circuits to `true` for `hasRole('super-admin')`. Roles are seeded lowercase-kebab (`super-admin`, `admin`, `employee`, `driver` — see `RolePermissionSeeder`, the single source of truth for both `php artisan setup:permissions` and `db:seed`).
- Route-level role gating uses `Route::middleware('role:super-admin')->group(...)` (see the `/debug/auth` route).
- Permission names follow `verb-resource` (`view-users`, `edit-vehicles`, etc.), defined in `RolePermissionSeeder`. `super-admin`/`admin` get every permission; `employee`/`driver` get a scoped-down subset for their day-to-day flows.

### Users, drivers, vendors
There is no separate `Driver` model — drivers are `User` rows with `user_type` (`driver`, `transport_manager`, `employee`, `admin`, ...) and a `driver_status` enum (`available`, `on_trip`, etc.). `User::isDriver()`, `scopeDrivers`, `scopeAvailableDrivers`, `canDrive()` encode the driver-specific rules (license expiry, status). `DriverController` is a thin, filtered view over the same `users` table/`UserController` logic (same Form Requests, same table) rather than a distinct resource.

`Vendor` / `VendorContactPerson` model an external vendor org; `users.vendor_id` links a user (e.g. an outsourced driver) to a `Vendor`.

**Dead files to be aware of:** `app/Http/Requests/UserStoreRequest.php` and `UserUpdateRequest.php` are unused leftovers — `UserController` and `DriverController` both actually use `StoreUserRequest` / `UpdateUserRequest`. Don't assume the `User*Request` variants are the active validation path.

### Trips (the core domain)
`Trip` (`app/Models/Trip.php`) is the largest model and centers a web of related tables:

- `trip_passengers` — passengers with pickup/dropoff stops and status (`trip_passenger_events` records check-in/check-out/no-show events, handled by `TripPassengerController`).
- `trip_stops` — ordered stops per trip (`ordered()`/`destinations()` scopes), optionally linked to a `Factory`.
- `trip_vehicle_assignments` / `trip_route_assignments` — append-only history of vehicle/route reassignment, each with an `is_current` flag; `currentVehicleAssignment()`/`currentRouteAssignment()` pull the latest. Use `Trip::assignRoute()` / the vehicle-reassign endpoint rather than writing `vehicle_id`/`vehicle_route_id` directly, so history and audit logs stay consistent.
- `trip_audit_logs` — append-only change log. **Trip's `static::updating` boot hook auto-logs every dirty attribute on every save** (except `updated_at`), so any `$trip->update([...])` — even incidental ones — creates an audit row. Bear this in mind when writing bulk-update code or backfills.
- `trip_recurring_groups` — a trip created with a date range creates one `Trip` row per day, all linked via `recurring_group_id` (see `recurring_start_date`/`recurring_end_date`). This is simpler than the "weekly pattern" design described in some of the root-level planning docs — the *actual* implementation is the date-range/group approach.
- `trip_logistics`, `factory_trip`, `department_trip` — many-to-many pivots (logistics coordinators, factories visited, multi-department headcount split).
- Status lifecycle is `pending → approved → assigned → in_progress → completed`, with `rejected`/`cancelled` as terminal off-ramps — enforced by `Trip::canTransitionTo()`/`transitionTo()`. State-changing endpoints (`approve`, `reject`, `start`, `complete`, `cancel`) live in `TripStateController`, not `TripController`. `TripObserver` auto-sets `start_time`/`end_time` and flips `is_completed`/`status` together, so update those fields through the model methods (`startTrip()`, `completeTrip()`, `cancel()`) instead of raw attribute writes where possible.

### Routes, stops, vehicles
`VehicleRoute` has many `RouteStop` (pivot with ordering + per-leg distance), which reference `Stop`. `resources/js/lib/distance-calculator.ts` + `geolib` compute distances client-side for the Leaflet-based map picker (`MapStopPicker`, `components/ui/map-stop-picker.tsx`). `Vehicle` has a driver assignment history via `VehicleDriverAssignment` (mirrors the trip vehicle-assignment pattern), tracked by `VehicleObserver`.

### Frontend structure
- `resources/js/pages/<resource>/{index,create,edit,show}.tsx` — one folder per resource, matching controller resource routes.
- `resources/js/components/ui/` — shadcn-style primitives (`components.json` configures the shadcn aliases: `@/components`, `@/components/ui`, `@/lib`, `@/hooks`). Domain-specific components (trip attendance, passenger selection, etc.) live directly in `resources/js/components/`, not under `ui/`.
- `resources/js/base-components/` — higher-level reusable patterns shared across resource pages: `base-data-table.tsx`, `base-form.tsx`, `advanced-form.tsx`, `base-export-button.tsx`, `base-multi-select.tsx`, `page-header.tsx`. Prefer extending/reusing these over hand-rolling a new table/form per resource.
- `resources/js/types/` — hand-written TS interfaces mirroring backend models (`trip.ts`, `user.ts`, `vehicle.ts`, etc.), not auto-generated from PHP.
- Path alias `@/*` → `resources/js/*` (see `tsconfig.json` and `vite.config.ts`).
- Excel import/export uses `maatwebsite/excel` server-side (see `*.export`/`*.import` routes on Users/Departments/Products); PDF via `barryvdh/laravel-dompdf`.

### Database
Dev/prod uses MySQL (`DB_DATABASE=vems`); the test suite runs against in-memory SQLite regardless of `.env` (`phpunit.xml` overrides `DB_CONNECTION`/`DB_DATABASE`). Queue/cache default to `database` driver, mail to `log`.

### Root-level `*.md` planning docs
`VMS_DEVELOPMENT_PLAN.md`, `TRIP_IMPLEMENTATION_GUIDE.md`, `TRIP_SYSTEM_FINAL.md`, `TRIP_DYNAMIC_FIELDS_GUIDE.md`, `RECURRING_TRIPS_SIMPLE.md`, `MIGRATION_CONSOLIDATION.md`, `FRONTEND_UPDATES.md` are historical design/planning notes written at different stages of the project. They're useful for *why* a feature exists but several describe designs (e.g. weekly recurrence patterns, `TripService` classes, tables like `trip_recurring_series`/`trip_series_occurrences`) that were later simplified or never actually implemented. Treat `database/migrations/` and the current models as the source of truth over these docs, and verify anything you read there against the actual code before relying on it.
