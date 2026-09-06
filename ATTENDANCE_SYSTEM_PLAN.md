# Employee Attendance, Break & Overtime System — Implementation Plan

Status: design agreed, not yet implemented.
Owner: VEMS backend/frontend.
Related existing subsystem (do not merge into this one — see [§3](#3-relationship-to-the-existing-trip-passenger-system)): `trip_passengers` / `trip_passenger_events`.

## 1. Goal

Track every employee's working day — check-in, check-out, and break periods — and derive gross hours, net hours, and overtime from it, with enough data integrity (GPS/device stamps, idempotency, immutable correction trail) that the numbers can be trusted for payroll.

Attendance must be captured from **two different real-world sources** depending on employee group, and reported on uniformly regardless of source:

| Group | Where they work | Capture source |
|---|---|---|
| Office staff (incl. anyone using the pickup/dropoff transport to get there) | At the office | ZKTeco biometric device, via BioTime |
| Inspection & Training staff | In the field, never at the office | Self-service app check-in, GPS-backed |

## 2. Non-goals (out of scope for this plan)

- Leave management (paid/sick/annual leave requests) — separate feature.
- Payroll calculation/export itself — this system produces the hours; payroll consumes them.
- Shift scheduling / rostering — a single configurable standard-shift-length is assumed for now.
- Geofencing enforcement (hard-rejecting a punch outside a radius) — GPS is captured and shown, not policed, in v1.

## 3. Relationship to the existing trip-passenger system

`trip_passenger_events` already tracks passengers boarding/alighting a vehicle for a specific `Trip`. It is **not reused** for employee attendance, for reasons worth recording so this doesn't get re-litigated later:

| | `trip_passenger_events` | `attendance_events` (this plan) |
|---|---|---|
| Scoped to | a specific `Trip` instance | a calendar day (`work_date`) |
| Exists for | only employees riding a company vehicle that day | every employee, every day |
| Typical actor | driver/admin marks the passenger boarded (actor ≠ subject) | the employee themself, or HR on override |
| Answers | "did this passenger board/alight this trip's vehicle?" | "was this employee on the clock, and for how long?" |

A pickup/dropoff ride is a transport detail, not a work-attendance detail — the employee still punches the office biometric device once they arrive. Merging the two would bloat one table with nullable columns for two unrelated concerns and couple payroll data to trip-schema changes. What **is** reused is the architectural pattern proven in that subsystem: status snapshot + append-only event log, idempotency keys, GPS/device stamps, void-and-supersede corrections instead of edits.

An optional, non-authoritative soft link is allowed: `attendance_events.related_trip_passenger_event_id` (nullable FK) so the UI can show "arrived via Trip #245 pickup at 7:42 AM" as context.

**Auto-populated at check-in, passenger side only** (scope decision: driver-side linking is out of scope for this plan — a driver has no `trip_passengers` row and would need a separate `related_trip_id` FK; not built here). When a `check_in` event is created, look up whether the subject user has a `trip_passengers` row for `work_date` on a trip that is currently `in_progress` (or, failing that, any trip that day — same lookup already used for `used_transport` in §5.1, just run at event time instead of only at record-recalculation time). If found, set `related_trip_passenger_event_id` on the event automatically — the employee never has to pick a trip manually. Full trip info (route, vehicle, stops, current status) is then derivable by joining `related_trip_passenger_event_id` → `trip_passenger_events.trip_passenger_id` → `trip_passengers.trip_id` → `trips`, so the attendance UI/report can show trip context next to the check-in without duplicating any trip data onto `attendance_events` itself.

### 3.1 One button, not two (UX decision)

The dashboard already lets an employee self-mark their own trip boarding (the existing "Active Trip Attendance Banner", §11.1) by pressing Check In/Check Out on `trip_passenger_events`. Requiring that same employee to *also* press a separate Check In for work attendance minutes later is bad UX — two taps for what is, to the employee, one moment. Decision: **there is exactly one Check In button and one Check Out button, ever** — not one pair for trips and a second pair for attendance. Both tables stay separate underneath (the reasons in the table above still hold), but the single button's backend action decides what needs writing:

- **Check In pressed, and the user has a `trip_passengers` row for today not yet `boarded`** (a pending pickup): the action writes **both** — creates the `trip_passenger_events` check_in row (same as the existing trip flow) and updates `trip_passengers.status` to `boarded`, **and** creates the `attendance_events` check_in row in the same request, with `related_trip_passenger_event_id` pointing at the row it just created.
- **Check In pressed, and the user's `trip_passengers` row for today is already `boarded`** (someone else — e.g. a driver — already marked them, or a prior action already ran): no new trip write happens; the action only creates the `attendance_events` check_in row and links it to the existing boarding event (the read-only lookup described above).
- **Check In pressed, and there's no `trip_passengers` row for today at all**: only `attendance_events` is written, exactly as if trips didn't exist.
- **Check Out mirrors this** against a pending drop-off (`trip_passengers.status = boarded`, not yet `completed`): fan out to both tables if the drop-off is still pending, link-only if it's already been recorded by someone else, attendance-only if there's no trip. The factory/location dialog (§4.2.1) pre-fills the factory from the trip's `dropoff_stop_id` when one applies, and the employee just confirms or edits it rather than re-entering it.
- **Start Break / End Break never fan out.** A trip has no concept of a break, so these two actions only ever write `attendance_events` — regardless of whether the day involves a trip or not.

This keeps the data model exactly as designed (two independent, differently-scoped event logs) while giving the employee a single, always-in-the-same-place action for each real-world moment.

## 4. Data model

### 4.1 `attendance_records` — one row per user per day (fast-read snapshot)

```
id
user_id                 FK users
work_date               date
status                  enum: checked_in, on_break, checked_out
check_in_at             datetime, nullable
check_out_at            datetime, nullable
break_minutes           int, default 0      -- sum of all break pairs that day
gross_minutes           int, nullable       -- check_out_at - check_in_at
net_minutes             int, nullable       -- gross_minutes - break_minutes
overtime_minutes        int, default 0      -- max(0, net_minutes - standard_shift_minutes)
source                  enum: biometric_device, self_service, manual
used_transport           bool, default false -- true when a trip_passengers pickup/dropoff exists for this user/day, see §5.1
trip_passenger_event_id  FK trip_passenger_events, nullable -- which boarding event, set alongside used_transport
has_anomaly             bool, default false -- surfaced on the report; true if any linked event is voided/corrected/auto_closed, or punch count from device was unrecognized
auto_closed             bool, default false -- forced closed by the nightly job
created_at / updated_at

unique (user_id, work_date)
index (work_date, status)
index (has_anomaly)
index (used_transport)
```

### 4.2 `attendance_events` — append-only log, the source of truth

```
id
attendance_record_id       FK attendance_records
user_id                     FK users              -- subject
actor_user_id               FK users, nullable     -- who performed it (self, HR, or null = system/device)
event_type                  enum: check_in, check_out, break_start, break_end, correction
event_time                  datetime
latitude, longitude         decimal, nullable
gps_accuracy_meters         int, nullable
ip_address                  string, nullable
device_id                   string, nullable       -- app/browser device fingerprint, NOT the biometric device
source                      enum: biometric_device, self_service, manual
external_ref                string, nullable       -- BioTime transaction id, for idempotency + traceability
idempotency_key             string, unique
is_valid                    bool, default true
voided_at                   datetime, nullable
void_reason                 string, nullable
superseded_by_event_id      FK attendance_events, nullable, self-referencing
related_trip_passenger_event_id  FK trip_passenger_events, nullable  -- optional context link, see §3
factory_id                  FK factories, nullable  -- which factory this event relates to; set on check_out for field visits, see §4.3.1
location_name                string, nullable       -- free-text label for where the event happened (e.g. "Factory main gate", "Client site B"), typically entered alongside factory_id at check_out
metadata                    json, nullable
created_at

index (user_id, event_time)
index (attendance_record_id)
unique (idempotency_key)
```

#### 4.2.1 Factory visit at checkout

Field staff (Inspection & Training) commonly check out at a factory they visited, not at the office. `factory_id` reuses the existing `factories` table (no new lookup table) and is optional — most check-ins won't set it, but self-service checkout should offer a factory picker plus a `location_name` free-text field alongside the GPS coordinates already captured in `latitude`/`longitude`. This is independent of `used_transport`/`related_trip_passenger_event_id` (§3): `used_transport` answers "did they ride a company vehicle that day," `factory_id`/`location_name` on a checkout event answers "where did the workday actually end."

`attendance_records` does not need its own `factory_id` column — the checkout event already carries it, and the record can expose it via its latest `check_out` event when reporting (§11).

Corrections never mutate or delete a row: a bad event is voided (`voided_at`/`void_reason`) and a new `correction` event supersedes it. `attendance_records` is always derived by replaying/aggregating its valid `attendance_events` — never hand-edited directly.

### 4.3 Supporting columns on existing tables

```
departments.attendance_mode   enum: biometric, self_service   default 'biometric'
users.attendance_mode_override enum: biometric, self_service, nullable  -- per-user exception
users.biometric_id            string, nullable, unique   -- BioTime emp_code
```

`User::attendanceMode()` resolves `attendance_mode_override` if set, else `department.attendance_mode`.

### 4.4 Sync bookkeeping

```
attendance_sync_states
  id
  source          string   -- e.g. 'biotime'
  last_synced_at  datetime
  updated_at
```

Single small table rather than a config value, so multiple future sources (a second BioTime server, another device brand) can each track their own cursor.

## 5. State machine

Enforced in model methods on `AttendanceRecord` — never raw attribute writes, matching how `Trip::canTransitionTo()` guards its lifecycle:

```
checkIn()     : allowed only when no open record exists for today (status absent or checked_out from a prior correction reopen)
startBreak()  : allowed only from status = checked_in
endBreak()    : allowed only from status = on_break
checkOut()    : allowed from checked_in or on_break
                -> if on_break, auto-closes the open break first and logs it as a correction event (never left dangling)
```

Each transition:
1. Validates current status.
2. Requires an `idempotency_key` (client-generated for self-service; derived from BioTime's transaction id for device punches).
3. For `checkIn()`/`checkOut()` only: runs the fan-out check from §3.1 first — write/link the matching `trip_passenger_events` row if a pending trip action applies, or link-only, or skip entirely if no trip exists.
4. Writes one `attendance_events` row with whatever GPS/device/source data is available for that channel (plus `related_trip_passenger_event_id` if §3.1 produced one).
5. Recalculates `break_minutes` / `gross_minutes` / `net_minutes` / `overtime_minutes` on the parent `attendance_records` row.

### 5.1 Mixed-source days

An Inspection & Training employee (`attendanceMode()` = `self_service`) sometimes physically visits the office and badges the ZKTeco device that day, so BioTime sync also produces a `check_in` for them — same user, same `work_date`, two sources. `attendance_records.source` must resolve to a single value, so a priority rule decides which channel is authoritative when both fire on the same day:

- **Trip pickup exists for that user/day** (a `trip_passengers` row shows them boarded a pickup to the office that `work_date` — see §3's `related_trip_passenger_event_id` link): **biometric wins**, regardless of which event arrived first. If self-service check-in landed first, it is voided (`void_reason: "superseded — biometric authoritative, trip pickup on this day"`) once the BioTime sync event lands, and the record's `source`/`check_in_at` are recalculated from the biometric event. If the biometric event lands first, a later self-service check-in attempt is rejected the normal way `checkIn()` already rejects a second check-in — no special-case error, just the existing "already checked in" guard — and is logged as an `is_valid = false` event for the trail, not flagged `has_anomaly` (this is expected, not an error state).
- **No trip pickup that day**: **first valid check_in wins**, whichever channel it came from; the later one from the other channel is likewise logged as an `is_valid = false` informational event, not flagged `has_anomaly`.

`has_anomaly` stays reserved for genuinely unexplained cases (§8) — an expected dual-source day is not one of them. This means the BioTime sync job (§6.2) and `checkIn()` both need to check for an existing valid check-in for that user/day — and, when the trip-pickup condition applies, be able to void-and-supersede an earlier self-service event — rather than treating a same-day second check-in as a hard failure.

**Recording transport usage** (`used_transport`, §4.1): whenever `attendance_records` is created/recalculated for a user/day, look up `trip_passengers` for that `user_id`/`work_date` regardless of which check-in source wins or which `attendanceMode()` the user has — an office-staff biometric user who also rode a company pickup counts too. If a boarded pickup/dropoff is found, set `used_transport = true` and `trip_passenger_event_id` to that boarding event; otherwise leave both at their defaults. This is independent of the source-priority rule above — a self-service user with no trip pickup can still have `used_transport = false` while their biometric-vs-self-service priority is decided purely by arrival order.

## 6. Capture sources

### 6.1 Self-service (Inspection & Training staff)

- Only rendered in the UI for users whose `attendanceMode()` resolves to `self_service`.
- One status-driven button: Check In → Start Break → End Break → Check Out.
- GPS captured on every event (mandatory permission prompt) since there is no physical device to prove presence; not hard-enforced against a geofence in v1 (see §2).
- Client generates an idempotency key per action (e.g. UUID stored in local state) so a slow network retry or double-tap never creates a duplicate event.
- Check Out additionally offers an optional factory picker (existing `factories` table) and a `location_name` free-text field (§4.2.1), submitted alongside the GPS coordinates already captured for that event.

### 6.2 Biometric device, via BioTime (Office staff)

You are running ZKTeco devices in push/cloud mode, aggregated through **BioTime**. Integrate against BioTime's REST API rather than the raw device (ADMS/iclock) protocol — BioTime already handles device communication, offline buffering, and multi-device aggregation.

**Employee mapping**: `users.biometric_id` stores BioTime's `emp_code`, entered once per employee via the existing `UserController` create/edit form.

**Scheduled sync job** — `php artisan attendance:sync-biotime`, run every 5–15 minutes:
1. Authenticate against BioTime's token endpoint, cache the token.
2. Read `attendance_sync_states` for `source = 'biotime'` to get `last_synced_at`.
3. Pull attendance transactions newer than that cursor (paginated).
4. For each transaction:
   - Map `emp_code` → `users.biometric_id` → `user_id`. Unmapped codes are logged for HR to fix (a mapping gap must never silently drop a punch).
   - If BioTime exposes a `punch_state` code (0=check-in, 1=check-out, 2=break-out, 3=break-in in common BioTime 8.x installs), map it **directly** to `event_type` — no guessing.
   - If `punch_state` isn't available/reliable on this BioTime version, fall back to per-day sequence interpretation: 2 raw punches that day → check_in + check_out; 4 → check_in, break_start, break_end, check_out; any other count → do not guess, flag `has_anomaly = true` for manual HR review.
   - Build `idempotency_key` from BioTime's transaction id (`external_ref`) so re-running the sync is always safe.
   - Feed into the same `AttendanceRecord` methods as self-service, tagged `source = biometric_device`, `actor_user_id = null`. If a valid check-in already exists for that user/day from self-service, apply the mixed-source priority rule (§5.1) rather than erroring.
5. Advance `last_synced_at`.

**Action needed before building this**: confirm the exact BioTime version's endpoint path and whether `punch_state` is present in the transaction payload (check the BioTime admin panel's API docs page, typically under `/att/api/`, or share a sample response). Everything else in this plan is unaffected either way.

### 6.3 Manual / HR override

HR can create or correct an event directly (permission-gated, see §9) — e.g. an employee forgot their badge and HR enters a manual check-in. Always logged with `actor_user_id` = the HR user, `source = manual`.

## 7. Working hours & overtime calculation

```
gross_minutes    = check_out_at - check_in_at
break_minutes    = sum(break_end_at - break_start_at) over all break pairs that day
net_minutes      = gross_minutes - break_minutes
overtime_minutes = max(0, net_minutes - standard_shift_minutes)
```

`standard_shift_minutes`: a single global config value in v1 (e.g. 480 = 8 hours). Not per-department/per-user yet — add that only if it turns out to be needed, rather than building it speculatively.

## 8. Anomaly detection & correction

`attendance_records.has_anomaly` is set true when:
- A device sync produced an unrecognized punch count for that user/day.
- The nightly job had to force-close the record (`auto_closed = true`).
- Any linked `attendance_events` row has been voided/superseded.

Anomalies are surfaced as a flag column on the report — never hidden, never silently auto-corrected without a trace. Resolution path: HR opens the day's full raw event trail, and either accepts it as-is or issues a correction (voids the disputed event, submits a new one with a required `void_reason`). Both the original and the correction remain visible in the trail permanently.

## 9. Permissions (`RolePermissionSeeder.php`)

| Permission | Who | Grants |
|---|---|---|
| `capture-own-attendance` | all authenticated users | self check-in/out/break |
| `manage-attendance` | admin, HR-designated role | manual override, void/correct events for any user |
| `view-attendance-reports` | admin, transport_manager (or a new HR-facing role if one exists) | reports screen, export |

`super-admin`/`admin` continue to get everything via the existing `Gate::before` short-circuit.

## 10. Backend

**Models**: `AttendanceRecord`, `AttendanceEvent` (relations to `User`; `AttendanceRecord::attendanceEvents()`, `AttendanceEvent::attendanceRecord()`, `AttendanceEvent::supersededBy()` self-relation).

**Controller — `AttendanceController`**:
- `POST attendance/check-in`
- `POST attendance/check-out`
- `POST attendance/break-start`
- `POST attendance/break-end`
- `GET attendance` — the authenticated user's own history + event trail
- `GET attendance/reports` — admin/manager cross-employee view, filterable by date range/department/source/`used_transport`/`factory_id`, exportable via `maatwebsite/excel` (same pattern as the existing Users export)
- `POST attendance/events/{event}/correct` — HR correction (permission: `manage-attendance`)

**Console command**: `attendance:sync-biotime` (§6.2).

**Scheduled command**: nightly auto-close job — force-closes any record still `checked_in`/`on_break` past midnight, sets `auto_closed = true`, logs a `correction` event with reason `"auto-closed: no checkout recorded"`.

## 11. Frontend

### 11.1 Placement

The dashboard (`resources/js/pages/dashboard.tsx`) already has an "Active Trip Attendance Banner" (L239-313) that shows a Check In/Check Out button **only when `activeAttendanceAction` is present** — i.e. only when the user has a trip pickup/drop-off happening, and its click handler only posts to the trip-passenger endpoint. Per §3.1, this banner is **replaced**, not kept alongside a second one: there is one status card, always visible, and its button is the single fan-out Check In/Check Out action.

The card's content changes with `attendance_records.status` (per §5), and shows trip context inline when it applies, rather than as a separate element:

```
Not checked in                       →  [ Check In ]
Checked in since 07:42 (Trip #245    →  [ Start Break ]  [ Check Out ]
  pickup — Uttara Circle 3)
On break since 13:00                 →  [ End Break ]
Checked out at 17:00 · 8h25m         →  (no button — day is closed)
  · overtime 25m
```

Check In / Start Break / End Break post immediately; Check Out opens the factory/location dialog (§4.2.1), pre-filled from the trip's drop-off stop when a pending drop-off applies. `Start Break`/`Check Out` are both offered together once checked in — an employee can go straight to Check Out without ever taking a break; there is no rule forcing a break first.

Recommended placement, in order of how much is built:
- **Dashboard**: the always-visible status card described above, in the exact slot the old trip-only banner occupied.
- **Global header pill** (optional, in `AppSidebarHeader` next to the notification bell) — same always-visible status, reachable from any page, not just the dashboard.
- **Full page** `resources/js/pages/attendance/index.tsx` — status-driven action button, today's summary, full event trail (not gated on `attendanceMode()`, since biometric/manual-sourced users should still be able to view their own history even though they don't self-capture).

### 11.2 Pages/types

- `resources/js/pages/attendance/index.tsx` — see §11.1.
- `resources/js/pages/attendance/reports.tsx` — admin table via `base-data-table.tsx`, filters (date range, department, source, used-transport, factory), anomaly-flag column, used-transport column, factory-visited/location-name columns, `base-export-button.tsx`.
- `resources/js/types/attendance.ts` — TS mirror of `AttendanceRecord`/`AttendanceEvent`.
- Sidebar entry in `app-sidebar.tsx`, gated with the existing `hasPermission()` helper (`resources/js/lib/permissions.ts`).

## 12. Testing plan (Pest, mirroring `TripPassengerEventTest.php`)

- State-machine guards: can't check in twice, can't break before checking in, can't end a break that isn't open, checkout auto-closes a dangling break.
- Idempotency: replaying the same `idempotency_key` never creates a second event.
- Calculation correctness: gross/net/overtime minutes across single- and multi-break days.
- Correction flow: voiding an event and superseding it leaves both visible, recalculates the parent record correctly.
- Permission checks: a non-HR user cannot correct another user's event or view the cross-employee report.
- BioTime sync: unmapped `emp_code` is logged and skipped, not silently dropped; unrecognized punch counts flag `has_anomaly`; re-running the sync with overlapping data doesn't duplicate events.
- Mixed-source days (§5.1): self-service check-in followed by a same-day biometric punch with a linked trip pickup supersedes the self-service event and flips the record's source to biometric, without `has_anomaly`; the same scenario with no trip pickup keeps whichever check-in came first; either order, the losing event is retained as `is_valid = false`, not deleted.
- `used_transport`: a record for a user with a boarded `trip_passengers` pickup/dropoff that day gets `used_transport = true` and a populated `trip_passenger_event_id` regardless of `attendanceMode()` or which source won check-in; a day with no trip pickup leaves it `false`.
- Factory visit at checkout (§4.2.1): a `check_out` event with `factory_id`/`location_name` set persists both and surfaces on the report; omitting them at checkout leaves both `null` without blocking the checkout.
- Auto-linked trip on check-in (§3): a `check_in` event for a user with an `in_progress` trip's `trip_passengers` row that day gets `related_trip_passenger_event_id` set automatically, and trip info is retrievable through the join chain; a user with no trip that day leaves it `null` without blocking check-in.
- Unified check-in/check-out fan-out (§3.1): pressing Check In with a pending (`confirmed`, not yet `boarded`) `trip_passengers` row creates both the `trip_passenger_events` row and the `attendance_events` row in one action, correctly linked; pressing Check In when the trip row is already `boarded` (e.g. a driver marked it first) creates only the `attendance_events` row and links to the existing boarding event, without a duplicate trip write; pressing Check In with no trip row at all writes only `attendance_events`. Same three cases mirrored for Check Out against `dropoff_stop_id`/`completed`. Start Break/End Break never write to `trip_passenger_events` under any of these cases.

## 13. Build order

1. **Core schema + self-service capture + report** — migrations, models, permissions, `AttendanceController` (self-service routes + own history + report screen), frontend pages, Pest tests. No external dependency; ships value immediately for Inspection & Training staff.
2. **BioTime sync integration** — once the BioTime version's endpoint/`punch_state` details are confirmed (§6.2), add the sync command and wire it into the same pipeline built in step 1.
3. **Anomaly/correction UI polish** — once both sources are live and real anomalies are visible, refine the correction workflow based on what actually shows up.

## 14. Open questions

- BioTime version and whether `punch_state` is present in the transaction API response (§6.2) — needed before step 2 can start.
- Is there a distinct HR role, or does `manage-attendance` / `view-attendance-reports` get attached to the existing `admin`/`transport_manager` roles?
- Standard shift length in minutes (assumed 8h/480 min pending confirmation).
