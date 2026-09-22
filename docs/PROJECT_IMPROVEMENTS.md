# VEMS — Project Review & Improvement Suggestions

_Generated 2026-07-21 from a code review of the trip lifecycle, passenger attendance flow, and general project health. Treat this as a working checklist, not a spec — verify against current code before acting on any item, especially after time has passed._

## How to read this doc

- **Bugs** — things that are actually broken today and likely producing wrong data.
- **Add** — things the project doesn't have yet and probably should.
- **Improve** — things that exist but are inconsistent, duplicated, or risky.

---

## 1. Bugs (fix first)

### 1.1 `is_completed` / `end_time` never set when a trip is completed
`TripStateController::complete()` (`app/Http/Controllers/TripStateController.php:95-134`) sets `actual_end_time` but never touches `is_completed` or `end_time`. `TripObserver::updating()` only back-fills `end_time` when `is_completed` is dirty *and* true — so via the real "Complete Trip" endpoint, these two fields are permanently stuck at their defaults. Any dashboard/report filtering on `is_completed` is silently wrong.

**Fix:** either call `Trip::completeTrip()` (already written, unused, `app/Models/Trip.php:513-538`-ish) from the controller, or explicitly set `is_completed = true` / `end_time = now()` in the controller alongside `actual_end_time`.

### 1.2 Three different, disagreeing definitions of the trip status lifecycle
- `Trip::canBeStarted()/canBeCompleted()/...` — simple booleans.
- `Trip::canTransitionTo()` — a `match` statement (only allows `in_progress` from `assigned`, NOT `approved`).
- `TripStateController`'s own inline `in_array(...)` checks — the ones that actually run, and which *do* allow `approved → in_progress` directly.

None of these is authoritative; they contradict each other. Pick one source of truth (probably the model's `canTransitionTo()`), make the controller call it, and delete the others.

### 1.3 `cancel()` doesn't record who/why
`TripStateController::cancel()` only sets `status = cancelled` — it ignores `cancellation_reason`, `cancelled_by`, `cancelled_at`, even though `Trip::cancel($reason, $notes)` (model) fills all of those in plus a semantic audit entry. Right now, a cancelled trip has no recorded reason.

### 1.4 Dead model state-machine (`startTrip()`, `completeTrip()`, `cancel()`, `transitionTo()`)
All unused (verified via grep) — the "nice" API exists but nothing calls it, and controllers duplicate a worse, ad-hoc version inline. This is the root cause of 1.1–1.3. Decide: wire controllers to use these methods, or delete them if the ad-hoc approach is intentional. Don't leave both.

### 1.5 Dual, conflicting permission bootstraps
- `php artisan setup:permissions` (`app/Console/Commands/SetupPermissions.php`) creates **capitalized** roles (`Super Admin`, `Admin`, `Manager`, `Employee`, `Driver`) with a small permission set — **no trip/passenger/complaint permissions at all**.
- `database/seeders/RolePermissionSeeder.php` (the one actually wired into `DatabaseSeeder` and referenced by `AppServiceProvider`'s `Gate::before(hasRole('super-admin'))`) creates **lowercase-kebab** roles (`super-admin`, `transport-manager`, `driver`, ...) with the full permission set actually used across the app.

Running the console command on a fresh install creates a role set the rest of the app doesn't recognize — a serious onboarding footgun. Either delete `SetupPermissions`/point its docs at the seeder, or merge them into one source of truth.

### 1.6 Vestigial route middleware referencing non-existent methods
`TripController` declares middleware for `'start','complete','cancel'` (`app/Http/Controllers/TripController.php:26,28`), but those actions live entirely on `TripStateController`. Harmless today, but confusing — remove it.

### 1.7 Stray backup files committed to the repo
`resources/js/pages/vendors/edit.tsx.bak` and `edit.tsx.new` — not built by Vite, but repo clutter. Delete or move out of version control.

---

## 2. Add (missing pieces)

### 2.1 Test coverage for the state-changing endpoints
`tests/Feature/` has 12 files; only `TripPassengerEventTest.php` and `DriverEnforcementTest.php` touch the trip domain. **Zero tests** exist for:
- `TripStateController` (approve/reject/start/complete/cancel) — a test on `complete()` alone would have caught bug 1.1.
- `TripController` CRUD, recurring-trip creation, vehicle reassignment.
- The entire new **Feedback/Complaints module** (`TripFeedbackController`, `TripFeedbackStateController`) — functionally complete end-to-end but untested.
- `TripObserver`, `VehicleObserver`, audit-log creation.

`tests/Unit/` only has the stock `ExampleTest.php`.

### 2.2 Audit/history UI on the trip show page
`trip_audit_logs`, `trip_vehicle_assignments`, and `trip_route_assignments` exist specifically to track "who changed what and when," but `resources/js/pages/trips/show.tsx` shows none of it — no "approved/rejected by," no vehicle/route reassignment history. Compare with `resources/js/pages/vehicles/show.tsx`, which **does** have a "Driver Assignment History" card — the same pattern should be replicated on the trip page.

### 2.3 Rate limiting on API endpoints
There's no `routes/api.php`; the JSON lookup endpoints (`/api/stops`, `/api/drivers/available`) live in `routes/web.php` under plain `auth,verified` with **no `throttle:` middleware** anywhere except the stock Laravel auth flows. Low risk today (all auth-gated), but cheap to add.

### 2.4 Sanity-bound validation on numeric trip fields
`complete()` validates `odometer_end >= odometer_start`, which is good, but there's no upper bound / sanity check on `fuel_consumed` or cost fields — a typo'd extra zero would sail through.

### 2.5 API/architecture docs
No OpenAPI/Swagger or README section describing the handful of JSON `/api/*` endpoints — minor, but worth a short doc if the frontend team grows.

---

## 3. Improve (consistency / cleanup)

### 3.1 Consolidate duplicated "which statuses show this button" logic
The set of trip statuses that show the "Start Trip" button is defined **twice** — once in `resources/js/components/trip-action-buttons.tsx` and again inline in `resources/js/pages/trips/show.tsx` (lines 679-688). Extract to one shared helper/constant so they can't drift apart.

### 3.2 Dead frontend badge states
`trips/show.tsx` (lines 113-130) defines badge colors for passenger statuses `confirmed` and `cancelled`, but the backend `TripPassenger.status` enum only ever produces `pending`, `boarded`, `completed`, `no_show`. Remove the dead cases or confirm they're genuinely reachable from somewhere not yet reviewed.

### 3.3 Two near-duplicate timestamp fields
`start_time` (set via `TripObserver` side-effect) vs `actual_start_time` (set directly by the controller) both get populated on trip start through two independent code paths. Consider consolidating to one field, or clearly documenting why both exist (e.g., planned vs actual).

### 3.4 Reconsider whether drivers should be able to start their own trip
Currently only transport-manager/officer/assistant roles have `edit-trips` and can start/complete/cancel a trip; the assigned driver — who has the vehicle and is the one who knows when they've actually departed — cannot. This may be an intentional control (dispatcher confirms departure), but worth confirming it matches how the fleet actually operates, since it means a manager must be available/reachable every time a trip needs to start.

### 3.5 Feedback/Complaints module — add bulk actions
The module (migration → model → controllers → routes → 3 pages) is complete and wired into the trip show page, but the index page has no bulk assign/resolve actions for managers handling many complaints at once. Minor UX nice-to-have, not urgent.

---

## Suggested priority order

1. Fix 1.1 (`is_completed`/`end_time`) — silent data-correctness bug affecting reporting.
2. Resolve 1.5 (dual permission bootstraps) — real onboarding risk for any new environment setup.
3. Decide on 1.2/1.4 (pick one state-machine source of truth) — unblocks fixing 1.1 and 1.3 cleanly in one pass.
4. Add tests for `TripStateController` and the Feedback module (2.1) — locks in the above fixes.
5. Everything else (2.2–2.5, section 3) as capacity allows.
