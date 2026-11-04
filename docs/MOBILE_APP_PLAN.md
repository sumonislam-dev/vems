# VEMS Mobile App: Plan (MVP)

Status: **Draft, not started.** This document describes a limited-scope mobile app for VEMS. It does not change any existing code.

Related docs: [ROLES.md](ROLES.md), [USER_MANUAL.md](USER_MANUAL.md), [DEPLOYMENT.md](DEPLOYMENT.md), [UAT_CHECKLIST.md](UAT_CHECKLIST.md).

---

## 1. Goals

- Let **drivers** see their assigned trips and passenger lists, start and complete trips, and check passengers in and out from a phone.
- Let **employees** see their own trips and trip history.
- Let **approvers** (department heads, transport managers) approve or reject pending trips from a phone.
- Reuse existing business rules. The mobile app is a new client for the same Laravel backend, not a second system.

### Non-goals (v1)

- Offline-first operation (only a simple retry queue for check-in/out, see §8).
- Creating or editing trips, vehicles, users, routes, or stops.
- Reports, exports, document uploads, complaints with photos.
- iOS release (Android first; iOS can follow from the same codebase).

---

## 2. Decisions

| Topic | Decision | Reason |
|---|---|---|
| App count | **One app** with role-based screens | One codebase, one login, one store listing. |
| Framework | **React Native with Expo** (TypeScript) | Team already uses React 19 + TypeScript. Expo covers builds, OTA updates, push. |
| Platform order | **Android first**, iOS later | Lower cost to ship and test. |
| API style | **JSON API under `/api/v1`**, separate from Inertia web routes | Current app returns `Inertia::render()` only; mobile needs JSON. |
| Auth | **Laravel Sanctum personal access tokens** | Standard for Laravel mobile clients. Web session auth stays unchanged. |
| Authorization | Reuse **`spatie/laravel-permission`** and existing model scopes | One access model. Never trust the client to hide data. |
| Push | **Expo Push** (or FCM) triggered from the existing `TripNotifier` | Events already exist. |

### Current state of the repo (verified)

- `laravel/sanctum` is **not** installed (`composer.json`).
- There is **no** `routes/api.php` file. The only `/api/*` routes are in `routes/web.php` (`/api/stops`, `/api/geocoding/search`).
- Trip visibility already exists in `Trip::scopeVisibleTo()`.
- Passenger check-in/out already exists in `TripPassengerController`.
- **Employee attendance already exists** in `AttendanceController` (check-in, check-out, break start/end, reports), with `attendance_records` and `attendance_events` tables and the `capture-own-attendance` permission. Its design is in [ATTENDANCE_SYSTEM_PLAN.md](ATTENDANCE_SYSTEM_PLAN.md). That doc says "not yet implemented", but the code has the core flow, so treat the doc as partly out of date.
- **Complaints already exist** on the web: `trip_feedback` table, `TripFeedbackController` (create, list, show) and `TripFeedbackStateController` (assign, resolve, close, reopen). Employees already have `create-complaints` and `view-own-complaints`. The mobile app needs to call these, not build a second system.
- The web Check In button already does both things at once: it writes the trip pickup event if the employee has one today, and it writes the attendance event (`resolveTripFanOut()`). The mobile app should call the same flow, not a second one.

---

## 3. Users and roles

The app is one codebase. The screens shown depend on the permissions returned by the API.

| User | Typical role | Mobile screens |
|---|---|---|
| Driver | `driver` (user_type `driver`) | Today's trips, trip detail, passenger list, check-in/out, start/complete trip, trip history (own) |
| Employee / passenger | `employee` | My trips, trip detail, trip history, trip status |
| Department head | `department-head` + `approve-department-trips` | Pending approvals for their departments, approve/reject |
| Transport manager / admin | `transport-manager`, `admin` | Pending approvals, all trips (read), attendance correction (optional, v2) |

Screens are shown from the `permissions` array returned by `GET /api/v1/auth/me`. The server still checks every request.

---

## 4. MVP scope

### 4.1 Driver

1. Log in; stay logged in on the device.
2. **Today's trips** (assigned to the driver's vehicle, scheduled for today).
3. **Trip detail**: trip number, date, route, vehicle, status, passenger list with per-passenger status.
4. **Check in** a passenger (optional stop and GPS).
5. **Check out** a passenger.
6. **Mark no-show**.
7. **Start trip** and **complete trip** (only through the model methods, see §6.3).
8. **Trip history** for the driver's own trips.
9. For vehicles **without** a vendor tracker, send the vehicle's GPS position from the phone while the trip is in progress (fallback, §4A.9). Vehicles with a tracker need no driver action.
10. **Live map of the vehicle on the trip:** own position, route, next stop, ETA (§4A.7).
11. Push notification when a trip is assigned or changed.

### 4.2 Employee

1. Log in.
2. **Today's attendance card**: current status (not checked in / checked in / on break / checked out), check-in time, break time so far.
3. **Check in** (one button). If the employee has a pickup today, it also marks them boarded on that trip, as the web flow does. Optional GPS and device ID are sent.
4. **Start break** and **end break**. These only write attendance events, never trip events.
5. **Check out** (one button), with optional factory picker and free-text location, matching the web form.
6. **My trips**: upcoming and past, with status.
7. **Trip detail**: pickup, drop, time, vehicle (once assigned), driver name and phone (if the business agrees).
8. **Track the vehicle:** live map of the vehicle on their trip, with position, speed, route, next stop, and ETA to their pickup. Shown only in the window in §4A.7.
9. **Trip history** and **attendance history** (own records, with break minutes and totals per day).
10. **Complaints and feedback** about their trips (§4.3A).
11. Push notification when a request is approved, rejected, or a vehicle is assigned.

**Who sees the check-in controls:** only users whose `attendanceMode()` is `self_service` (Inspection & Training staff, per the attendance plan). Office staff on the biometric device see their status and history read-only. The server should also enforce this. See §6.4.

### 4.3 Approver

1. **Pending approvals** (for their departments, or all if they have `approve-trips`).
2. Approve or reject with an optional reason.
3. Push notification when a new request needs approval.

### 4.3A Employee complaints and feedback

Employees can report a problem with a trip, or give feedback, from the phone. The web app already does this; the mobile app calls the same controller logic.

**Who can file:** any user with `create-complaints` (employees, and others as the roles allow), for a trip they **requested, rode on, or drove**. This is the same rule as `TripFeedbackController::store()` (`scopeTripsToUser()`). The app shows only trips that pass this check.

**Form fields** (from `TripFeedbackController::store()` validation):

| Field | Rule |
|---|---|
| Trip | required; picked from the user's own trips (recent, for example the last 60 days) |
| Type | `complaint` or `feedback` |
| Category | `driver_behavior`, `vehicle_condition`, `punctuality`, `safety`, `route`, `other` |
| Subject | required, up to 255 characters |
| Description | required |
| Driver rating | optional, 1–5 |
| Vehicle rating | optional, 1–5 |
| Anonymous | optional, default off |

- **Priority is not shown to employees.** The web form accepts it, but the default `low` is safer for the app, and staff set priority during triage. Confirm in §14.
- **Safety:** when the employee picks `safety`, show a short notice with the emergency contact the company wants used (to be provided, not invented). The complaint is still filed.
- **After submit:** the complaint cannot be edited (the web app has no edit route either). Only staff with `delete-complaints` can delete.
- **My complaints:** the list shows the employee's own complaints (`view-own-complaints`) with status `open`, `in_review`, `resolved`, or `closed`, and shows resolution notes once resolved.
- **Anonymity:** the web hides the submitter's name from other viewers (`TripFeedback::hideAnonymousSubmitterFrom()`), but `submitted_by` is still stored. The app must not say "fully anonymous" until management confirms what is hidden and from whom (§14).
- **Notifications:** today the web sends a notification only when a complaint is submitted (`TripNotifier::complaintSubmitted()`). There is no notification to the submitter when the status changes. Add one in phase C1 so employees know when their complaint is handled.
- **Photos:** not in the current table. Attachments, if wanted, go on the private disk through `PrivateDocument`. Phase 2 at the earliest.

### 4.4 Shared

- Login, logout, "me" (profile and permissions).
- Clear error messages for 401, 403, 404, 422, and network errors.
- App version check (force update when the API version is too old).

---

## 4A. Live GPS tracking of vehicles

### 4A.1 Goal

Transport managers and logistics staff see where each vehicle is now, and where it has been during the trip. Employees on a trip see where their vehicle is relative to their pickup. The system raises alerts when a vehicle is late, stopped too long, off route, or silent (no position received).

### 4A.2 Current state of the repo (verified)

- No vehicle location table, no tracking endpoints, and no realtime/broadcast package (`composer.json` has no Reverb or Pusher).
- `vehicles` has only `parking_latitude` / `parking_longitude`.
- Location points exist today only as snapshots on events: `trip_passenger_events`, `attendance_events`, and `factories`/`stops` coordinates. They are not a stream.
- Maps use Leaflet (`MapStopPicker`) with OpenStreetMap tiles. Location search goes through a backend proxy (commit `ddab696`).
- **An existing vendor GPS API is in use:** NITS (`atom.nits.com.bd`), queried by device number and a time window. See §4A.13. This changes the recommended source (§4A.3).

### 4A.3 Source of position: decide first

| Option | How it works | Pros | Cons |
|---|---|---|---|
| **A. Driver's phone (recommended for v1)** | The Expo app sends GPS from the driver's phone while a trip is in progress | No hardware cost. Uses the same app and login. Fast to build. | Depends on the phone being on, charged, with data, and the app allowed to run in the background. Android needs a foreground service and a Play Store justification for background location. |
| **B. Hardware GPS tracker (telematics box)** | A device fitted in the vehicle sends to the vendor, which forwards to a VEMS webhook | Works without a phone. Harder to tamper with or switch off. Usually has ignition and fuel data. | Hardware and monthly fees per vehicle. Vendor integration work. Installation. |
| **C. Existing vendor API (NITS) (recommended where a tracker is fitted)** | The VEMS backend polls the NITS API on a schedule for each vehicle's `device_number` and stores the points | Trackers already installed and paid for. No driver action needed. Works even if the phone is off. No new hardware. | Depends on the vendor's uptime, update rate and data quality, which are not yet confirmed (§4A.13). |

All three should feed **the same table** (`vehicle_locations`). Use **C** for vehicles that already have a tracker. Use **A** as a fallback for vehicles without one. B is only needed if the company wants new hardware. Confirm the NITS details before building (see §4A.13 and §14).

### 4A.4 Data model (draft)

**`vehicle_locations`** (append-only, one row per received point)

```
id                      bigint PK
vehicle_id              FK vehicles, indexed
trip_id                 FK trips, nullable (the trip active when the point was taken)
recorded_at             datetime (from the device clock, validated against server time)
received_at             datetime (server time)
latitude                decimal(10,7)
longitude               decimal(10,7)
accuracy_meters         decimal(8,2), nullable
speed_kmh               decimal(6,2), nullable
heading_degrees         smallint, nullable
source                  enum: 'vendor_api', 'phone', 'device'
device_id               string, nullable (phone ID, or vendor device number)
idempotency_key         string, unique per vehicle
index (vehicle_id, recorded_at)
```

**`vehicles`** (add a snapshot for fast list views, updated on each accepted point)

```
last_latitude, last_longitude, last_location_at, last_speed_kmh
tracker_device_number   string, nullable, unique (the NITS device_number for this vehicle)
```

Volume: one point every 10–30 seconds per moving vehicle. Example: 50 vehicles × 10 hours × 4 points per minute is about 120,000 rows a day. Keep raw points for a fixed period (for example 90 days, to be decided), then delete or archive them. Keep trip-level summaries (distance, stops, max speed) if reports need them after that.

### 4A.5 Ingest rules (the server must enforce these)

- Accept points in **batches** (array of up to 100 points per request), because phones buffer points when the signal drops.
- Only the **assigned driver** (`vehicles.driver_id`) or a user with `send-vehicle-location` for that vehicle may send points. Same pattern as attendance (`authorizeAttendanceAction`).
- Reject or flag points where:
  - `accuracy_meters` is above a threshold (for example 50 m).
  - `recorded_at` is more than a few minutes off server time, or in the future.
  - the implied speed from the previous point is physically impossible (for example over 150 km/h).
- Each point has an `idempotency_key`. A repeated key is accepted and ignored, which makes the retry queue safe.
- Update `vehicles.last_*` only if the point is newer than the current one. Out-of-order points still get stored, but do not move the "current position".
- Return per-point results (`accepted`, `duplicate`, `rejected` with reason) so the app can clear its queue correctly.
- **Vendor points** (source C) are not pushed by a phone. A scheduled job calls the vendor API per vehicle with a `tracker_device_number` and writes the points through the same validation. The dedupe key is the vendor device number plus the vendor timestamp, since the vendor gives no idempotency key.

### 4A.6 Realtime delivery to viewers

| Option | Effort | Latency | Notes |
|---|---|---|---|
| **Polling** every 10–15 s (recommended for v1) | Low | 10–15 s | Uses the existing JSON endpoints. Works on web and mobile without new infrastructure. |
| **Laravel Reverb** (WebSockets) with private channels | Medium | About 1 s | Needs a process to keep running on the server (add it to `composer dev` and to the deployment docs). Channels authorized by permission. |

Start with polling. Move to Reverb when the map page is in daily use and the 10–15 s delay is a complaint. The ingest code should dispatch a `VehicleLocationUpdated` event either way, so switching later is a small change.

### 4A.7 Who sees what

| Viewer | Sees | Permission |
|---|---|---|
| Transport manager, admin, super-admin | All vehicles, live map, trails | new `view-vehicle-tracking` |
| Logistics (`assign-vehicles`) | All vehicles, live map | `view-vehicle-tracking` |
| Management | Live map, read-only | `view-vehicle-tracking` |
| Employee on a trip | Vehicle on their trip: current position, speed, route, next stop, ETA to their pickup | new `track-trip-vehicle` + trip visible to them (`Trip::scopeVisibleTo()`) |
| Driver | Vehicle they drive: same map as employees, plus their own position as the vehicle's | `send-vehicle-location` + `track-trip-vehicle` |

**Window (proposed):** employees and drivers see the live position from 30 minutes before the scheduled start until the trip completes. Outside that window the map shows "No live position for this trip". The window length is a business decision (§14).

**Employees and drivers do not see the trail.** They see the current position and the trip's route and stops only. Trails stay with managers and logistics (`view-vehicle-tracking`). This is the main privacy limit in this design.

The new permission `track-trip-vehicle` is granted to `employee` and `driver` in `RolePermissionSeeder`.

Permissions are added in `RolePermissionSeeder` only, then deployed with `php artisan permissions:update` (add-only). No migration for permissions, per project rule.

### 4A.8 Alerts

Use the existing `TripNotifier` and `AlertNotification`, and the scheduled `vems:trips-needing-attention` job:

- **Silent vehicle:** no accepted point for N minutes (for example 10) while a trip is in progress.
- **Stopped too long:** speed near zero for N minutes away from a planned stop.
- **Off route:** position farther than X metres from the planned route for more than Y minutes.
- **Late to pickup:** estimated arrival at the next stop is later than the scheduled pickup time (estimate from distance and recent average speed; v2 unless a routing API is added).
- **Arrived at stop:** optional, used for auto-marking stops in the trip.

Recipients are the transport managers for the vehicle's zone, through `NotificationRecipients::withPermission()`.

### 4A.9 Driver phone behaviour (fallback, for vehicles without a tracker)

- Start sending when the trip is set to `in_progress` (`startTrip()`) and stop when it completes or is cancelled.
- Foreground: every 10 seconds while moving, every 60 seconds while stopped. Background: a foreground service on Android with a visible notification ("Sending vehicle location"). iOS later.
- Ask for location permission with a clear explanation before the first trip.
- Store points in the local queue when offline and send in batches when the signal returns. Show a "Location: last sent 3 min ago" line on the driver screen so the driver knows when it is not working.
- Use the phone's fused location (Expo Location). Check the battery impact on a real phone during the pilot.
- If the driver turns location off, the app must say so clearly. Silent vehicle alerts (§4A.8) cover this case.

### 4A.10 Web map (recommended first step)

Before the mobile tracking app, build a **live map page on the existing web app** for transport managers: Inertia page, Leaflet (already in use), markers for each active vehicle, a click to show the trail for the last hour, and the planned route stops. It uses the same API endpoints, so the mobile work later reuses it. This gives value even before drivers' phones are rolled out, and it lets you test the ingest with a few real trips first.

### 4A.11 Privacy, policy and retention

- Vehicle position is linked to the driver, so it is personal data. Tell drivers what is collected, why, how long it is kept, and who can see it, and get it into the company policy before the pilot.
- Retention period is a business decision (§14). Delete old raw points on a schedule, and log who viewed a driver's trail.
- Employees see only their own trip's vehicle, and only during the trip (§4A.7).
- Do not use tracking data for performance scoring without a written decision from management.

### 4A.12 Cost and infrastructure notes

- Map tiles: OpenStreetMap is free but has usage policy limits. Google Maps SDK in the mobile app needs an API key and billing. Choose one before phase T3.
- Storage: the volume in §4A.4 is manageable in MySQL with the index above. Partition or archive if the table grows past what backups handle comfortably (see `docs/DEPLOYMENT.md` backups).
- Queue: the ingest should be fast (a single insert or a small batch), with alerts dispatched to the queue so they do not slow requests.
- Hardware option (§4A.3 B) adds vendor fees per vehicle.

### 4A.13 Vendor API: NITS (to confirm before building)

**What is known** (from the URL the team uses):

- Endpoint: `https://atom.nits.com.bd:8080/ntrack/nits_api/rscbd.php`
- Parameters: `user_name` (the vendor account), `device_number` (the tracker ID), `date`, `s_time`, `e_time` (a time window on that date).
- Response: HTTP 200 with a `text/html` content type, containing a JSON object in a `<pre>` block. Keys seen so far: `success`, `message`, `device_details`.
- A test call for device `33803090` on 2025-05-12 between 10:45:08 and 11:45:08 returned `"message": "Record Found."` but `"device_details": "No Record Found."`. So no sample position record has been seen yet, and the **position field names, units and timezone are unknown**.

**What this means for the design:**

- The API is a **query by time window**. Live tracking needs either a "latest position" call, or a short rolling window (for example the last 2–5 minutes) polled every 30–60 seconds. Which one is possible depends on the vendor, and must be checked.
- VEMS calls the vendor from the **backend only**. Apps read positions from VEMS, so the vendor account and URL never reach the phone.
- The vendor account name is in the query string. Full URLs end up in server logs, so the integration must not log them unmasked.
- Store the account name and any secret in `.env` / config, never in the app bundle.
- If the vendor is down or slow, the system shows "Tracker data unavailable" for that vehicle and, if a driver phone is available, falls back to source A.

**Questions to ask NITS (or check in their documentation):**

1. Full list of fields in each record, with units (speed in km/h or knots?) and the timezone of the timestamp (Asia/Dhaka?).
2. Update interval for each device, and the typical delay between a position and when it appears in the API.
3. Is there a "latest position" call? If not, what is the largest time window allowed, and what is the rate limit?
4. Is there a machine-readable format (JSON with `application/json`), and is the response stable (same keys over time)?
5. What happens with no data (today it returns "No Record Found" inside an HTTP 200)? Should VEMS treat that as "no movement" or "tracker offline"?
6. Uptime, support contact, and any cost per device or per call.
7. Terms: can VEMS store positions for the retention period in §4A.11, and can the data be shown to employees for their trip?
8. Credentials: who owns the account, and can it be a dedicated VEMS account rather than the shared `rscbd` user?

**Next step:** one successful call with a date and time window that has data, so the record fields can be mapped into `vehicle_locations`. Then add the `tracker_device_number` column and a scheduled job (phase T0).

## 5. Architecture

```
┌──────────────────────┐        HTTPS + Bearer token        ┌──────────────────────────┐
│ Expo app (one app)   │ ─────────────────────────────────▶ │ Laravel (existing)        │
│ - screens by role    │ ◀───────────────────────────────── │ routes/api.php  /api/v1   │
│ - secure token store │          JSON                      │ Sanctum auth              │
│ - retry queue        │                                    │ Controllers → Models      │
└──────────┬───────────┘                                    │ TripNotifier              │
           │ push token                                     └────────────┬─────────────┘
           ▼                                                              │ push
   Expo Push / FCM  ◀─────────────────────────────────────────────────────┘
```

### Backend changes

- `composer require laravel/sanctum`, publish config and migration (`personal_access_tokens`).
- Create `routes/api.php` with a `v1` prefix and `auth:sanctum` middleware.
- Add `app/Http/Controllers/Api/V1/` controllers. Each one is thin: validate, check permission, call the same model methods the web controllers call, return a Resource.
- Add API Resources (`app/Http/Resources/Api/V1/`) so responses have stable shapes independent of Inertia props.
- Move any duplicated logic (for example attendance rules in `TripPassengerController`) into shared services or model methods. Do not copy it into the API controllers.
- Add a `device_tokens` table (user_id, token, platform, last_seen_at) for push.

### Mobile app structure (suggested)

```
mobile/
  app/                 # Expo Router screens (file-based routes)
    (auth)/login.tsx
    (tabs)/trips.tsx
    (tabs)/history.tsx
    (tabs)/approvals.tsx     # only if permission approve-trips or approve-department-trips
    trip/[id].tsx
  src/
    api/client.ts        # fetch wrapper: base URL, token header, 401 handling
    api/types.ts         # hand-written types matching API Resources
    auth/                # token storage (expo-secure-store), permissions
    queue/               # offline retry queue for check-in/out (AsyncStorage/SQLite)
    push/                # token registration
```

Types should mirror the API Resources, the same way `resources/js/types/` mirrors backend models today.

---

## 6. API design

### 6.1 Conventions

- Base URL: `https://<host>/api/v1`
- Auth: `Authorization: Bearer <token>`
- Content type: `application/json`
- Dates: ISO 8601 in `Asia/Dhaka` (`config('app.timezone')`).
- Errors: `{ "message": "...", "errors": { "field": ["..."] } }` (Laravel default for 422).
- Lists are paginated: `?page=`, `?per_page=` (max 50). Response includes `meta` and `links`.
- Every write endpoint accepts an optional `idempotency_key` (see §8).
- Each mobile request sends `X-App-Version` so the server can refuse very old clients.

### 6.2 Endpoints

| Method | Path | Purpose | Permission (server-side) | Maps to existing |
|---|---|---|---|---|
| POST | `/auth/login` | Email/employee ID + password → token | none (rate-limited) | `Auth\LoginRequest` logic |
| POST | `/auth/logout` | Revoke current token | auth | new |
| GET | `/auth/me` | User, roles, permissions | auth | `HandleInertiaRequests` shape |
| POST | `/devices` | Register push token | auth | new |
| GET | `/trips` | Trip list, filters: `date`, `status`, `scope=today\|upcoming\|history` | `view-trips` or `view-own-trips` | `TripController@index`, `Trip::scopeVisibleTo()` |
| GET | `/trips/{trip}` | Trip detail with passengers and their status | same as above, plus `Trip::scopeVisibleTo()` | `TripController@show` |
| POST | `/trips/{trip}/start` | Start trip | driver of vehicle or `edit-trips` | `TripStateController@start` |
| POST | `/trips/{trip}/complete` | Complete trip | driver of vehicle or `edit-trips` | `TripStateController@complete` |
| POST | `/trips/{trip}/passengers/{tp}/check-in` | Check in | `capture-passenger-attendance` + driver/`edit-trips` | `TripPassengerController@checkIn` |
| POST | `/trips/{trip}/passengers/{tp}/check-out` | Check out | same | `TripPassengerController@checkOut` |
| POST | `/trips/{trip}/passengers/{tp}/no-show` | Mark no-show | same | `TripPassengerController@markNoShow` |
| GET | `/trips/{trip}/passenger-events` | Event history for one trip | `view-trips` or driver of the trip | `TripController@passengerEvents` (filtered) |
| GET | `/approvals` | Pending trips for the user | `approve-trips` or `approve-department-trips` | `TripController@index` with `status=pending` |
| POST | `/trips/{trip}/approve` | Approve | `approve-trips` or department head | `TripStateController@approve` |
| POST | `/trips/{trip}/reject` | Reject with reason | `reject-trips` or department head | `TripStateController@reject` |
| GET | `/attendance/today` | Today's record: status, check-in/out times, breaks, `attendance_mode`, pickup info | `capture-own-attendance` | `AttendanceController@index` (new JSON wrapper) |
| POST | `/attendance/check-in` | Check in (fans out to the pickup if one applies) | `capture-own-attendance`, mode `self_service` | `AttendanceController@checkIn` |
| POST | `/attendance/break-start` | Start break | `capture-own-attendance`, mode `self_service` | `AttendanceController@breakStart` |
| POST | `/attendance/break-end` | End break | `capture-own-attendance`, mode `self_service` | `AttendanceController@breakEnd` |
| POST | `/attendance/check-out` | Check out, optional `factory_id` / `location_name` | `capture-own-attendance`, mode `self_service` | `AttendanceController@checkOut` |
| GET | `/attendance/history` | Own attendance records, paginated, filter by date range | `capture-own-attendance` | new (scoped to own user) |

| POST | `/vehicles/{vehicle}/locations` | Batch of GPS points (max 100), returns per-point result | assigned driver or `send-vehicle-location` | new (§4A.5) |
| GET | `/vehicles/live` | Active vehicles with last position and status, for the map | `view-vehicle-tracking` | new |
| GET | `/vehicles/{vehicle}/trail?from=&to=` | Points for a time window | `view-vehicle-tracking` | new |
| GET | `/trips/{trip}/vehicle-location` | Vehicle's last position, route, next stop and ETA, only inside the window in §4A.7 | `track-trip-vehicle` + trip visible to user (`Trip::scopeVisibleTo()`) | new (§4A.7) |
| GET | `/complaints/options` | Categories, and the user's trips eligible for a complaint | `create-complaints` | new (wraps `TripFeedbackController::create`) |
| GET | `/complaints` | Own complaints (or all, with `view-complaints`), paginated | `view-own-complaints` / `view-complaints` | `TripFeedbackController@index` |
| POST | `/complaints` | File complaint or feedback (§4.3A) | `create-complaints`, trip in user's scope | `TripFeedbackController@store` |
| GET | `/complaints/{complaint}` | One complaint with status and resolution notes | owner or `view-complaints` | `TripFeedbackController@show` |

Endpoints deliberately **not** in v1: create/update/delete trips, vehicle reassignment, correction of attendance events, user and vehicle admin.

### 6.3 Business rules the API must keep

These already exist in the backend. The API must use them, not reimplement them.

- **Status lifecycle** (`App\Enums\TripStatus`): `pending → approved → assigned → in_progress → completed`. `rejected` and `cancelled` are terminal. Transitions are enforced by `Trip::canTransitionTo()` and `transitionTo()`.
- **Start/complete** go through `startTrip()` and `completeTrip()` (or `TripStateController`). Do not write `status` or `is_completed` directly, because `TripObserver` sets `start_time`/`end_time` and flips status together.
- A trip **cannot start without a vehicle**.
- **Check-in** is refused if the passenger is not `pending` or `no_show` (returns "already checked in").
- **Check-in/out** is refused if the trip does not accept attendance (`ensureTripAcceptsAttendance`).
- **Attendance authority**: user must have `edit-trips`, or be the driver assigned to the trip's vehicle (`authorizeAttendanceAction`). Permission alone is not enough.
- **Passenger status values**: `pending`, `boarded`, `completed`, `no_show`.
- **Event types** stored in `trip_passenger_events`: `check_in`, `check_out`, `no_show`, plus corrections (`source = CORRECTION_SOURCE`). Events are append-only.
- Every passenger status change writes an event (the model handles this in `recordPassengerEvent`).
- **Trip visibility** (`Trip::scopeVisibleTo()`): users with `view-trips` see all trips. Others see trips where they are the requester, a passenger, the assigned driver, or (with `approve-department-trips`) a trip in a department they head.

---

### 6.4 Attendance rules the API must keep

Taken from [ATTENDANCE_SYSTEM_PLAN.md](ATTENDANCE_SYSTEM_PLAN.md) §3.1, §5 and §5.1, and from `AttendanceRecord` model methods:

- **State machine** (enforced by `AttendanceRecord` methods, never raw writes):
  - `checkIn()`: only when no open record exists for today.
  - `startBreak()`: only from `checked_in`.
  - `endBreak()`: only from `on_break`.
  - `checkOut()`: from `checked_in` or `on_break`. If on a break, the open break is closed first and logged as a correction, never left open.
- **Idempotency:** every write needs a client-generated `idempotency_key`. A repeated key returns the original event. This is what makes the offline retry queue (§8) safe.
- **Trip fan-out:** only `checkIn()` and `checkOut()` can write a trip event. Breaks never do.
- **Mixed-source days:** a `self_service` user who also badges the office device gets one authoritative check-in. If they have a pickup that day, biometric wins; otherwise the first valid check-in wins. The second one is logged as invalid, not as an error. The API should show the result, not the rule.
- **Mode gate:** the server must refuse check-in, break and check-out for users whose `attendanceMode()` is `biometric`. Today `AttendanceController::checkIn()` does not appear to check this (it only passes the mode to the page), so it needs to be added for the API, and probably for the web too.
- **Location:** GPS is captured and shown, not enforced. A check-in outside the geofence is flagged (`flagIfOutsideGeofence`), not rejected.

## 7. Data shapes (draft)

### Login response

```json
{
  "token": "1|abc...",
  "user": { "id": 12, "name": "Rahim", "employee_id": "E-0042", "user_type": "driver" },
  "roles": ["driver"],
  "permissions": ["view-own-trips", "capture-passenger-attendance"]
}
```

### Trip (list item)

```json
{
  "id": 318,
  "trip_number": "T-2026-0318",
  "scheduled_date": "2026-11-05",
  "start_time": "07:30",
  "status": "approved",
  "is_completed": false,
  "vehicle": { "id": 7, "registration_number": "DHAKA-METRO-LA-12-3456" },
  "route": { "id": 3, "name": "Uttara to Gulshan" },
  "passenger_count": 22
}
```

### Trip (detail) adds passengers

```json
{
  "passengers": [
    {
      "id": 901,
      "user": { "id": 55, "name": "Karim", "employee_id": "E-0101" },
      "status": "pending",
      "pickup_stop": { "id": 14, "name": "Uttara Sector 7" },
      "dropoff_stop": { "id": 2, "name": "Head Office" }
    }
  ]
}
```

Field names above are a draft. Confirm them against the actual columns in `trips`, `trip_passengers`, and `vehicles` before writing the Resources, since the existing `resources/js/types/trip.ts` is hand-written and may differ.

### Check-in request

```json
{
  "event_time": "2026-11-05T07:42:10+06:00",
  "stop_id": 14,
  "latitude": 23.8759,
  "longitude": 90.3795,
  "gps_accuracy_meters": 12,
  "area_name": "Uttara",
  "device_id": "android-7f3c...",
  "idempotency_key": "b1f0c2e8-..."
}
```

All fields except the idempotency key are optional, matching the web validation.

---

## 8. Offline and retry behaviour

Drivers often have weak signal. v1 does **not** support full offline use, but it should:

1. Save each check-in/out/no-show locally with a generated `idempotency_key` (UUID) before sending.
2. Send it. On success, remove it from the queue.
3. On network failure, keep it in the queue and retry when the app is open or the network returns.
4. The server must treat a repeated `idempotency_key` as "already done" and return the original result. **This is a backend change and must be added with the API.** The current `checkIn` only checks status, so a retry after a timeout would return "already checked in" as an error, which the app must handle as success.
5. Show a visible "pending sync" badge on passengers with queued actions.

---

## 9. Push notifications

| Event | Recipient | Source |
|---|---|---|
| Trip request needs approval | approvers + department head | `TripNotifier` (new request) |
| Trip approved, no vehicle | Logistics (not mobile in v1) | `TripNotifier` |
| Vehicle assigned / changed | requester, assigned driver | `TripNotifier` (vehicle assigned) |
| Trip cancelled | requester, driver | `TripNotifier` (cancelled) |
| Request approved / rejected | requester | `TripNotifier` |

Implementation:
- Add a push channel to the existing notifications, or a listener on `TripNotifier` that sends to all `device_tokens` of recipients.
- Tokens are removed when the server gets an invalid-token error from Expo/FCM.
- Respect recipient zones: `App\Support\NotificationRecipients::withPermission()` already filters recipients, so reuse it.

---

## 10. Security

- Tokens: Sanctum personal access tokens with an expiry (for example 30 days), revocable per device. Store them with `expo-secure-store`, never in AsyncStorage.
- Login rate limit (`throttle:` middleware) on `/auth/login`.
- Every endpoint checks permission and model visibility on the server. Hiding a screen in the app is not access control.
- Passenger personal data (employee ID, name) shown only to users who can already see it on the web.
- No sensitive documents (NID, licence scans) in the API. Those stay on the private `DocumentController` routes.
- HTTPS only. Certificate pinning is optional for v1.
- Log API access for attendance writes (who checked whom in, from which device), since that data is operational evidence.
- Test the 403 cases: a driver must not check in passengers on another driver's vehicle.

---

## 11. Testing

- **Backend**: Pest feature tests under `tests/Feature/Api/V1/` for each endpoint: success, 401 without token, 403 without permission, 403 for a driver on a different vehicle, 422 validation, idempotent retry. The suite runs on in-memory SQLite (`phpunit.xml`).
- **Mobile**: unit tests for the API client and the retry queue; manual test script on Android (login, check-in on poor signal, airplane mode and back).
- **UAT**: add a mobile section to `docs/UAT_CHECKLIST.md` with real drivers on real trips for one week before rollout.

---

## 12. Delivery plan

| Phase | Work | Output |
|---|---|---|
| 0. Decisions | Confirm role list, first audience (drivers), Android-only, hosting URL, store accounts | Signed-off scope |
| 1. Backend auth | Install Sanctum, `routes/api.php`, login/me/logout, device registration, rate limit, tests | Token login works from Postman |
| 2. Backend trips | Trip list/detail, start/complete, check-in/out/no-show with idempotency, tests | API complete for drivers |
| 3. Mobile skeleton | Expo project, login, secure storage, API client, role-based tabs | Installable dev build |
| 4. Driver screens | Today's trips, trip detail, check-in/out queue, start/complete, history | Driver flow works end-to-end |
| 5. Employee screens | My trips, detail, history | Employee flow |
| 6. Approvals | Pending list, approve/reject | Approver flow |
| 7. Push | Token registration, notifications from `TripNotifier` | Push working on test devices |
| T0. Vendor integration (NITS) | Confirm fields and rate limits (§4A.13). Backend client, `tracker_device_number` on vehicles, scheduled poll, mapping into `vehicle_locations`, masked logging | Live positions for vehicles with a tracker |
| T1. Tracking backend | `vehicle_locations` table, ingest endpoint with rules in §4A.5, `vehicles.last_*`, permissions in seeder, tests | Points stored and deduplicated |
| T2. Web live map | Inertia page with Leaflet, active vehicles, trail, route stops (§4A.10) | Managers see live vehicles on the web |
| T3. Driver background sending (fallback) | Expo Location, foreground service (Android), offline batching (§4A.9), only for vehicles without a vendor tracker | Phone sends points during a real trip |
| T4. Alerts | Silent, stopped, off-route alerts via `TripNotifier` and scheduled command (§4A.8) | Alerts reach managers in pilot |
| T5. Employee and driver view | `track-trip-vehicle` permission, live vehicle map on the trip inside the window (§4A.7) | Employees and drivers see their vehicle |
| T6. Realtime (optional) | Laravel Reverb and `VehicleLocationUpdated` broadcast (§4A.6) | Map updates in about 1 second |
| C1. Complaints | Employee file/list/detail (§4.3A), status-change push to the submitter | Employees file and track complaints in the app |
| 8. Pilot | Internal Play track, 5–10 drivers, one week | Bug list, go/no-go |
| 9. Release | Play Store production, runbook, support contact | Live |

Rough effort for one developer with Laravel and React experience: phases 1–2 about 2 weeks, phases 3–4 about 3 weeks, phases 5–7 about 3 weeks, pilot and release about 2 weeks. Treat these as estimates to refine after phase 0.

---

## 13. Store and operations

- **Google Play**: one-time developer account fee. Needs a privacy policy URL (the app handles personal data and location), data-safety form, and a target SDK that meets current Play requirements at release time.
- **Apple App Store** (later): yearly developer fee, review guidelines for location and background use.
- **Hosting**: confirm the production URL and TLS certificate before phase 3. Follow `docs/DEPLOYMENT.md` for queue worker and scheduler, since push notifications run through the queue.
- **Updates**: Expo OTA for JavaScript-only changes. Native changes need a new store build.
- **Support**: a named contact for drivers, and a simple way to report a bad check-in.

---

## 14. Open questions

1. Which users get the app first: drivers only, or drivers and approvers together?
2. Should employees see the driver's name and phone number?
3. **Resolved:** employees check themselves in. The web app already allows this for `self_service` users, and it also marks their pickup as boarded. Drivers still check in passengers from the trip screen. Open follow-up: should the mobile app let an employee check in for a pickup even when the driver has already marked them (the current web flow already links to the existing event)?
4. Should check-in require GPS, or is it optional?
5. Is offline use required for v1, or is the retry queue enough?
6. Which permission should drive the check-in screen: `capture-passenger-attendance` (used by the routes today) or `check-in-trips` / `check-out-trips` (defined in the seeder but not used by the routes)? Pick one and align the seeder and routes.
7. Who owns the production API host and the Play Console account?
8. Target Android versions and the mix of devices in use.
9. **Tracking source:** partly answered. An existing NITS API is in use (§4A.13), so C is the main source. Still open: which vehicles have a tracker and a `device_number`, and whether vehicles without one need the phone fallback (A) or new hardware (B).
16. **NITS details:** the questions in §4A.13 (field list, units, timezone, update rate, latest-position call, rate limit, uptime, terms, dedicated account).
10. **Tracking retention:** how long are raw GPS points kept, and who may view a driver's trail?
11. **Who sees live positions:** only transport managers and logistics, or also management and employees (employees on their own trip only, as proposed in §4A.7)?
12. **Map provider:** OpenStreetMap tiles, or Google Maps (needs API key and billing)?
13. **Tracking window:** is 30 minutes before the scheduled start, until completion, the right window for employees and drivers?
14. **Complaint anonymity:** what exactly is hidden when someone files anonymously, and may management see the submitter? The app's wording depends on this.
15. **Complaint priority and emergency contact:** should employees set priority at all, and what emergency contact should show for `safety` complaints?

---

## 15. Checklist before coding

- [ ] Open questions in §14 answered
- [ ] Permission used for attendance decided (§14.6)
- [ ] Server-side mode gate for attendance writes added (§6.4), with a test for a biometric user
- [ ] Attendance plan status updated (it says "not yet implemented")
- [ ] NITS record fields and update rate confirmed from one successful call with data (§4A.13)
- [ ] NITS credentials stored in `.env`, not in code or the app; logs masked
- [ ] Which vehicles have a tracker and a `device_number` (for `vehicles.tracker_device_number`)
- [ ] Map provider chosen (§14.12)
- [ ] Tracking retention and privacy text agreed with management (§4A.11)
- [ ] `view-vehicle-tracking`, `send-vehicle-location` and `track-trip-vehicle` added to `RolePermissionSeeder`, deployed with `permissions:update`
- [ ] Tracking window agreed (§14.13)
- [ ] Complaint anonymity wording and emergency contact agreed (§14.14–15)
- [ ] Status-change notification to complaint submitters added (§4.3A)
- [ ] Sanctum added in a branch, tests passing
- [ ] Production API host and TLS confirmed
- [ ] Play Console account created
- [ ] Privacy policy drafted
- [ ] Pilot drivers named
