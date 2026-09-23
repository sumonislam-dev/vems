# VEMS — Vehicle & Employee Management System

VEMS manages a company's transport fleet end-to-end: trip requests and
approvals, driver and vehicle assignment, routes and stops, passenger
check-in/out, vendor-supplied drivers, factories/departments, and
attendance — all in one Laravel + Inertia + React app.

## Tech stack

- **Backend:** Laravel 13 (PHP 8.2+), `spatie/laravel-permission` for
  roles/permissions, `spatie/laravel-activitylog`, Pest for testing.
- **Frontend:** Inertia.js 3 + React 19 + TypeScript, Tailwind v4,
  shadcn/ui-style components.
- **Maps:** Leaflet + OpenStreetMap by default, or Google Maps — switchable
  via `.env` (see [docs/GOOGLE_MAPS_SETUP.md](docs/GOOGLE_MAPS_SETUP.md)).
- **Exports:** `maatwebsite/excel` (Excel), `barryvdh/laravel-dompdf` (PDF).

## Features

- Trip lifecycle (`pending → approved → assigned → in_progress →
  completed`), with rejection/cancellation, recurring trips, and a full
  audit log of every change.
- Route/stop planning with an interactive map picker and per-leg distance
  calculation.
- Vehicle and driver assignment history (append-only, so past assignments
  are never lost), plus document-expiry tracking.
- Passenger check-in/out and attendance tracking.
- Vendor management for outsourced drivers.
- Role-based access control (`super-admin`, `admin`, `employee`, `driver`,
  extendable via `spatie/laravel-permission`).
- Real-time-feeling in-app notifications (trip status changes, complaint
  assignment) via Laravel's database notifications.
- Admin dashboard with live fleet/trip/complaint metrics (cached, not
  recomputed on every request).

## Requirements

- PHP 8.2+
- Composer
- Node.js 18+ and npm
- MySQL (dev/prod) — the test suite runs against in-memory SQLite instead,
  no separate test DB needed.

## Setup

```bash
git clone <repo-url>
cd vems

composer install
npm install

cp .env.example .env
php artisan key:generate
```

Edit `.env` — at minimum set `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` to
match a MySQL database you've created. Then:

```bash
php artisan migrate --seed
php artisan setup:permissions   # creates roles/permissions, makes the first user super-admin
```

## Running it

```bash
composer dev        # PHP server + queue listener + Vite, all together
```

Or individually:

```bash
php artisan serve   # backend only
npm run dev          # frontend only (Vite)
```

For server-side rendering: `composer dev:ssr`.

The app will be at whatever `APP_URL` is set to in `.env` (default
`http://localhost`).

## Configuration notes

- **Map provider:** the stop-picker map defaults to free
  OpenStreetMap/Nominatim. To switch to Google Maps, or to change the
  region it's locked to (default: Bangladesh), see
  [docs/GOOGLE_MAPS_SETUP.md](docs/GOOGLE_MAPS_SETUP.md) and the
  `VITE_MAP_*` variables in `.env.example`.
- **Queue/cache:** both default to the `database` driver — no Redis
  required to run locally.
- **Mail:** defaults to the `log` driver in local dev (mail is written to
  the log instead of actually sent).

## Testing & code quality

```bash
php artisan test                 # or: composer test / vendor/bin/pest
vendor/bin/pest tests/Feature/DriverEnforcementTest.php   # a single file
vendor/bin/pint                  # PHP code style (auto-fixes)

npm run types                    # TypeScript type-check
npm run lint                     # ESLint (auto-fixes)
npm run format                   # Prettier (auto-fixes)
```

## Project structure

```
app/Http/Controllers/   Controllers, one per resource; Inertia::render(...) for pages
app/Models/              Eloquent models (Trip is the largest — see its relations)
resources/js/pages/      One folder per resource: index/create/edit/show.tsx
resources/js/components/ Domain components (MapStopPicker, dashboards, etc.)
resources/js/base-components/  Shared table/form primitives used across resources
database/migrations/    Source of truth for the schema
docs/                    Planning notes and setup guides (see caveats in CLAUDE.md)
```

See [CLAUDE.md](CLAUDE.md) for a deeper architectural walkthrough (request
flow, auth/permissions model, the trip domain, and known gotchas).

## License

MIT.
