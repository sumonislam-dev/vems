# VEMS User Manual

This guide explains how to use the Vehicle & Employee Management System
(VEMS) day-to-day. It's written for the people who actually use the app —
admins/transport managers, employees, and drivers — not developers. (For
technical/architecture details, see [CLAUDE.md](../CLAUDE.md) and the
[README](../README.md).)

> Accounts are created by an admin — there is no public "Sign Up" page.
> If you don't have a login, ask your administrator to create one for you.

---

## 1. Getting started

### 1.1 Logging in

Go to the app's URL and sign in with the email/username and password your
admin gave you. If you forget your password, use **Forgot Password** on the
login screen to receive a reset link by email.

### 1.2 Your role

What you see in VEMS depends on your role:

| Role | Who | Can generally do |
|---|---|---|
| **Super Admin / Admin** | IT/fleet management staff | Everything — manage users, vehicles, routes, trips, vendors, departments, reports, and settings. |
| **Employee** | Staff who request transport | Request trips, view their own trips and complaints, check in/out their own attendance. |
| **Driver** | Vehicle drivers | View their assigned trips, check passengers in/out, log fuel, submit complaints, check in/out their own attendance. |

Your menu on the left only shows the sections you have access to — if
something described here is missing from your menu, you don't have
permission for it; ask your admin if you believe you should.

### 1.3 The notification bell

The bell icon in the top-right header shows a red badge when you have
unread notifications. Click it to see recent updates — currently VEMS
notifies you when:
- **A trip you requested is approved or rejected.**
- **A complaint/feedback item is assigned to you** (for whoever handles it).

Click a notification to jump straight to the related trip or complaint, or
use **Mark all read** to clear the badge.

### 1.4 Your profile

Click your name/avatar in the sidebar footer to reach **Settings**, where
you can update your profile details, change your password, and switch the
app's appearance (light/dark).

---

## 2. Dashboard

Your dashboard (the first page you land on after login) is tailored to
your role:

- **Employees** see: your attendance status for today (checked in/out,
  on break), your upcoming/pending/in-progress trip counts with a shortcut
  to request a new trip, and your open/total complaint counts.
- **Drivers** see: your attendance status, today's and upcoming assigned
  trips, your driver status (available/on trip) and license expiry, and
  your recent complaints.
- **Admins/Managers** see the full fleet overview: vehicle/user/vendor
  totals, recently added vehicles and users, per-module stat cards
  (vehicles, drivers, routes, trips, complaints), role distribution, a
  recent-activity feed, upcoming schedules, open issues needing attention,
  key performance metrics (completion rate, fuel efficiency, customer
  satisfaction, vehicle utilization, driver ratings, maintenance
  compliance), and charts (weekly trip volume, vehicle status split,
  6-month trend, top routes, issue categories, driver ratings).

The admin dashboard's numbers refresh at least every 5 minutes; you don't
need to do anything to keep them current.

---

## 3. Trips

This is the core of VEMS — requesting, approving, and running vehicle
trips.

### 3.1 Requesting a trip (Employees, or anyone with trip-create access)

1. Go to **Trips → Add Trip** (or **Request a Trip** from your dashboard's
   empty-state link if you have none yet).
2. Fill in: vehicle (searchable list), optional pre-defined route, trip
   type, priority (low/medium/high/urgent), requesting department,
   scheduled date and start/end time, start and end locations, and a
   description/remarks.
3. Add passengers either:
   - **By name** — search and add specific people, each with their own
     pickup/dropoff stop, or
   - **By department headcount** — pick a department and a number of
     seats, without naming individuals.
4. Optionally select factories to visit and any logistics items (cargo/
   equipment) the trip needs to carry.
5. **Recurring trips:** toggle "Recurring" to turn the single date into a
   date range — this creates one trip per day for that range instead of a
   single trip. A whole recurring series can later be cancelled together
   from the trip's details.
6. Submit. Your trip starts as **Pending** until a manager approves it.

### 3.2 Trip status flow

```
Pending → Approved → In Progress → Completed
   |          |
   ↓          ↓
Rejected   Cancelled
```

- **Pending** — awaiting manager approval.
- **Approved** — approved, waiting for the driver to start it.
- **In Progress** — the driver has started the trip.
- **Completed** — the trip finished normally.
- **Rejected** — a manager declined the request (with a reason).
- **Cancelled** — cancelled after being pending/approved/in progress
  (requires a reason: no-show, breakdown, driver unavailable, blocked
  route, weather, emergency, or other).

You'll get a notification when your trip is approved or rejected.

### 3.3 Approving trips (Managers/Admins)

On the **Trips** list, pending trips can be approved or rejected
individually from the trip's page, or in bulk: tick the checkboxes next to
several pending trips and use **Approve N Trip(s)**. Rejecting requires a
reason.

### 3.4 Running a trip (Drivers, or managers)

From the trip's detail page:
- **Start Trip** — optionally record the starting odometer reading.
- **Complete Trip** — optionally record the ending odometer reading, fuel
  consumed, fuel cost, other costs, and notes.
- **Cancel Trip** — pick a reason and add notes.
- **Reassign Vehicle** — swap the assigned vehicle at any point before
  completion (requires a reason); VEMS re-checks the new vehicle's
  availability and seat capacity automatically.

### 3.5 Checking passengers in/out (Drivers, or managers)

On the trip's page, each passenger has **Check In**, **Check Out**, and
**No Show** buttons. Your device's location is captured automatically when
you use these — you don't need to enter coordinates yourself. If an event
was recorded by mistake, an authorized manager can correct it (a reason is
required for the correction, and it's logged).

A separate **Passenger Events** page lists these check-in/out records
fleet-wide, for managers reviewing attendance across all trips.

---

## 4. Attendance (your own)

Go to **Attendance** to manage your own workday:

- **Check In** when you start work.
- **Start Break** / **End Break** while on a break.
- **Check Out** when you finish — you can optionally note a factory you
  visited and a location name.

Your device's GPS location is captured automatically with each action. A
history table below shows your past days (check-in/out times, break time,
net hours, overtime, and a flag if any entry looks unusual). If something
looks wrong, ask an admin to review/correct it — corrections are tracked so
there's always a record of what changed and why.

Admins/managers have a separate **Attendance Reports** page to view and
export attendance across everyone, and to correct erroneous entries.

> Note: if your department uses biometric attendance devices instead of
> self-service, you won't see the check-in/out buttons — your attendance is
> captured automatically by the device instead.

---

## 5. Fleet management (Admins/Managers)

### 5.1 Vehicles

**Vehicles** lists every vehicle with filters (brand, type, color, rental
type, fuel type, vendor, status, condition) and CSV export. Creating or
editing a vehicle covers:

- Basic details: brand, model, color, registration number, type, rental
  type, seat capacity, manufacture year, fuel type, engine/chassis
  numbers, assigned vendor, active/operational status.
- **Document tracking** — tax token, fitness certificate, and insurance
  each have a number, expiry date, an optional file upload, and their own
  "alert enabled" toggle. Set a shared "days before expiry to warn"
  threshold — the dashboard's expiry-warning count depends on these being
  kept accurate, so update them whenever a document is renewed.
- Owner details (name, phone, email, NID) and parking location.

You can bulk-activate/deactivate several vehicles at once by selecting
their checkboxes, and assign or reassign a driver directly from a
vehicle's page (VEMS warns you if that driver is already assigned
elsewhere).

### 5.2 Drivers

**Drivers** is a filtered view of your driver accounts, with license
number/class/expiry, blood group, NID, current status (available/on
trip/etc.), and quick call/WhatsApp links. You can update a driver's
status individually or in bulk, and export the list to CSV. A driver whose
license has expired cannot be assigned to a vehicle.

### 5.3 Routes & Stops

**Routes** lets you build named, reusable routes made of ordered stops.
When adding a stop, use the built-in map: click a location to drop a pin
and create a stop with its coordinates already filled in — you don't need
to type latitude/longitude by hand. Distances between consecutive stops
are calculated automatically once both have coordinates.

### 5.4 Vendors

**Vendors** manages external service providers who supply vehicles or
outsourced drivers — contact details plus compliance documents (trade
license, TIN, BIN, tax return, each with an upload) and bank details.

### 5.5 Factories, Departments, Logistics

- **Factories** — locations trips can visit (name, address, city, map
  coordinates), also used for dashboard pickup-point counts.
- **Departments** — organizational units with a head, contact info,
  budget, and an attendance mode (self-service vs. biometric, which
  controls whether that department's staff see attendance check-in/out
  buttons). Supports CSV export/import and an active/inactive toggle.
- **Logistics** — cargo/equipment items that can be attached to a trip;
  each item can be locked to prevent further edits once in active use.

### 5.6 Products

**Products** is a general item catalog (name, description, price,
category, status) — independent of trips/vehicles. It has its own
export options (CSV, Excel, or PDF).

### 5.7 Users, User Groups, Roles & Permissions

- **Users** — create/edit accounts: name, username, contact info,
  department, user type, one or more roles, and (for drivers) license
  details. CSV export is available; import lets you bulk-create users from
  a file.
- **User Groups** — organize users into named groups with a description
  and status; add or remove members from a searchable list.
- **Roles** — admins can view, create, edit, and delete roles.
- **Permissions** — currently view-only in the interface; permission
  assignment happens through roles, not by editing individual permissions
  directly.

### 5.8 Complaints & Feedback

Anyone can submit **feedback or a complaint** tied to a trip: type
(feedback/complaint), category (driver behavior, vehicle condition,
punctuality, safety, route, other), priority, subject, description, and
optional 1–5 star ratings for the driver and vehicle. You can submit
anonymously if you prefer.

Managers handle these from the complaint's page:
- **Assign to Me** (if unassigned) — the assignee gets a notification.
- **Resolve** — with resolution notes.
- **Confirm & Close** — after it's resolved.
- **Reopen** or **Close Without Resolving**, if needed.

### 5.9 Reports

**Reports** gives you a filterable trip-history report, exportable as CSV
or PDF. **Attendance Reports** (under Attendance) does the same for
attendance data.

### 5.10 Activity Log

**Activity Log** is a fleet-wide, filterable audit trail (by module, event
type, user, and date range) recording every tracked change — useful for
answering "who changed what, and when."

---

## 6. Tips

- **GPS-based actions** (attendance, passenger check-in/out) need your
  browser's location permission — if you're prompted, allow it so your
  location is recorded correctly. If you deny it, the action still goes
  through, just without a location on record.
- **Keep vehicle document dates current.** The expiry-alert system on the
  dashboard is only as accurate as the dates and toggles you maintain on
  each vehicle.
- **Recurring trip series** are cancelled as a group — cancelling one
  occurrence doesn't cancel the whole series; use the series-level cancel
  option for that.
- If you can't find a menu item described here, it's most likely a
  permissions issue — check with your administrator rather than assuming
  it's broken.
