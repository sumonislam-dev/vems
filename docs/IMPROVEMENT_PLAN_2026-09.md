# VEMS — Improvement Plan (2026-09-23)

A fresh, code-verified audit of the project as it stands today. Every item
below was confirmed against the actual code (file/line cited), not
inferred from general best practice — if you don't see a citation, don't
act on it without checking first.

> There's an older review at
> [PROJECT_IMPROVEMENTS.md](PROJECT_IMPROVEMENTS.md) (2026-07-21), focused
> on trip-lifecycle bugs. Some of those items (e.g. dual permission
> bootstraps, the `is_completed`/`end_time` gap) may already be fixed —
> re-verify each one against current code before treating it as still
> open; this new doc doesn't re-litigate that one, it's a fresh pass.

## How to read this

- **High** — real risk of silent wrong behavior, a security gap, or a
  broken user-facing feature.
- **Medium** — inconsistent or fragile, but not currently causing harm.
- **Low** — worth knowing, not urgent.

---

## 1. Testing & CI — the single highest-leverage gap

**High**
- **No CI at all.** There's no `.github/workflows/` (or any other CI
  config) in the repo. `composer test`, `vendor/bin/pint`, `npm run lint`,
  `npm run types` all exist and work, but nothing runs them automatically
  on push/PR — they only run if someone remembers to run them locally.
  This is the one gap that makes everything below it easy to regress
  silently. **Recommendation:** add a workflow that runs `vendor/bin/pest`,
  `vendor/bin/pint --test`, `npm run types`, and `npm run lint` on every
  push/PR to `main`. This alone would have caught several of the findings
  below automatically going forward.

- **Zero test coverage on `UserController`'s core CRUD** (create/edit/
  delete accounts, role assignment). `tests/Feature/UserSelfEditTest.php`
  only covers the narrow self-edit-restriction path — the actual
  admin-facing "create a user," "change someone's role," and "delete a
  user" flows (including the business rule blocking deletion of drivers
  with active trips) have no regression test at all. This is a daily-use,
  high-blast-radius feature.

- **`VehicleController::update`/`destroy` untested.** Existing tests
  (`VehicleDocumentUploadTest.php`, `VehicleStatusTest.php`) only cover
  document upload and status-change slices; general vehicle edit/delete
  and `store()`'s non-document validation path aren't exercised.

- **`TripController::update`/`destroy` untested.** `trips.store` is well
  covered (`TripSchedulingValidationTest.php`), but editing a trip
  (re-validates vehicle availability/capacity, re-syncs passengers/
  factories/departments/logistics) and deleting one (which decrements
  `total_trips` on the recurring group) have no test.

- **Whole controllers with no test file and no route ever hit in
  `tests/`:** `DepartmentController`, `VehicleRouteController`,
  `RoleController`, `PermissionController`, `FactoryController`,
  `LogisticsController`, `VendorController`, `ActivityLogController`,
  `ReportController`, `StopController`. `RoleController`/
  `PermissionController` are especially notable — they directly control
  RBAC, with no automated check that the permission middleware actually
  blocks unauthorized users from managing roles.

**Suggested order:** stand up CI first (cheap, immediately valuable), then
add feature tests in this order: `UserController` CRUD → `VehicleController`
CRUD → `TripController::update/destroy` → the untested controllers list.

---

## 2. Security & authorization

**Medium**
- **Driver/employee ID documents are served with no access control.**
  `DriverController.php:389-393` and `:552-559` store NID scans and
  driving-license copies via `->store('driver_documents', 'public')` — the
  `public` disk is symlinked straight into `public/storage` and served by
  the webserver with no auth check (`config/filesystems.php:41-48`).
  Filenames are randomized so the directory isn't browsable, but anyone
  who obtains a direct URL (a leaked link, browser history, a future admin
  UI echoing the raw path) can view someone's ID document without logging
  in. The same gap applies to vehicle documents (tax token/insurance/
  registration), lower sensitivity but same pattern.
  **Fix direction:** move to a private disk and serve these through an
  authenticated, permission-checked route instead of a public URL.

- **`TripFeedbackController::store` doesn't verify the submitter has any
  relationship to the trip** (`TripFeedbackController.php:128-147`) —
  validation only checks `trip_id => exists:trips,id`, not that the user
  requested, rode, or drove that trip. Since ratings submitted here feed
  `User::average_rating` (shown on the dashboard's driver-performance
  chart), anyone holding `create-complaints` can currently rate a driver
  on a trip they had nothing to do with.

- **Raw exception messages are shown to end users.** `TripController::
  store/storeRecurring/update` (~lines 440, 555, 792) and
  `VehicleController::store/update` catch `\Exception` broadly and flash
  `$e->getMessage()` straight to the UI — in production this can leak raw
  DB error text to non-technical users. Compare against `AttendanceController`,
  which scopes its exception handling more deliberately.

**Low**
- `.env.example` ships `APP_DEBUG=true` with no warning comment that it
  must be `false` in production (standard starter default, but worth a
  one-line callout since it's the file people actually copy from).
- `SESSION_SECURE_COOKIE` is unset in `.env.example` — nothing currently
  documents that it needs to be set for an HTTPS production deploy (see
  §5, no deployment checklist exists to catch this).
- `VehicleController::store` logs the full validated payload at
  `Log::info('Validated data:', $validated)` (`VehicleController.php:500`),
  including `owner_nid`/`owner_phone`/`owner_email` on every vehicle
  creation — minor PII-into-logs concern.

**Confirmed clean** — spot-checked `TripController`, `TripPassengerController`,
`TripFeedbackStateController`, `AttendanceController`, `DriverController`:
all have consistent `permission:` middleware plus explicit in-controller
ownership checks where route middleware alone isn't enough (e.g. "driver
must be assigned to this trip"). `$fillable` on the models checked was
deliberately scoped, not blanket. No secrets found committed to git.

---

## 3. Performance

**Nothing significant found.** `TripController::index/show`,
`DashboardController`, `ReportController`, and `DriverController::index`
all eager-load relations (`with([...])`/`withCount`) and use single
aggregate `selectRaw` queries rather than per-row loops. The dashboard is
already cache-wrapped (5-minute TTL). No N+1 pattern was found in the
controllers checked — this area is in good shape, no action needed.

---

## 4. Feature gaps / inconsistencies users will actually notice

**High**
- **"Export" on the Users page doesn't work.** `UserController::export()`
  and `import()` (`UserController.php:521-536`) are stub placeholders that
  return `{"message": "Export/Import functionality coming soon"}` as JSON
  — but the routes are live (`routes/web.php:84-85`) and presumably linked
  from the Users UI, unlike Departments/Drivers, which have real
  Excel-backed export. Anyone clicking Export on Users today gets a raw
  JSON response instead of a file. Either implement it (mirroring
  `DepartmentController`'s pattern) or remove the button until it's ready
  — a silently-broken button is worse than a missing one.

**Medium**
- **The Permissions page is create/edit-incapable in the UI** even though
  the controller supports the full CRUD — only `permissions/index.tsx`
  exists, no `create`/`edit` page files. Confirm whether this is
  intentional (permissions managed only via roles) and, if so, consider
  removing the unused controller actions/routes to avoid confusion; if
  not, the frontend is incomplete.

---

## 5. Configuration / deployment readiness

**Medium**
- No CI (see §1) doubles as a deployment-readiness gap — there's no
  automated gate before code reaches `main`.
- No deployment checklist/doc exists to catch the `APP_DEBUG`/
  `SESSION_SECURE_COOKIE` items in §2. Even a short "before you deploy"
  section in the README would close this cheaply.

---

## 6. Code consistency

**Low**
- The newer modules (`TripController`, `DashboardController`, the
  permission-middleware layer) are noticeably more carefully written —
  defensive comments explaining *why*, test cross-references — than the
  older `UserController`/`VehicleController`/`DriverController` trio,
  which still lean on `$request->all()` plus manual "none"-string-to-null
  coercion before validation (`VehicleController.php:434-441`, `:580-587`)
  rather than a dedicated Form Request rule. Not broken, just worth
  knowing when planning where to spend refactor time first.
- No dead component files or stray `console.log`/`debugger` statements
  were found in `resources/js` — frontend housekeeping is in good shape.

---

## Suggested priority order for the next few weeks

1. Stand up CI (`pest`, `pint --test`, `tsc --noEmit`, `eslint`) — cheapest, highest-leverage item on this whole list.
2. Add feature tests for `UserController` and `VehicleController` full CRUD — zero coverage today on daily-use features.
3. Fix or hide the `users.export`/`users.import` stub — currently a broken button in production.
4. Decide on and implement a private, authenticated route for driver/vehicle documents instead of the public disk.
5. Replace the generic `catch (\Exception) { ...$e->getMessage()... }` pattern in `TripController`/`VehicleController` with scoped exceptions and generic user-facing messages.
6. Add an ownership check to `TripFeedbackController::store` (submitter must actually be tied to the trip being rated).
