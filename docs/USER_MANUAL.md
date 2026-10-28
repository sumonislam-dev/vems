# VEMS User Manual

A step-by-step guide to the RSC Vehicle Management System (VEMS): requesting
and running trips, managing the fleet, and administering users. It is written
for the people who use the app every day, not for developers.

**How to use this manual:** find your role in the table below and read the
sections listed for it. Everyone should read **Part 1**.

| Your role | What you do in VEMS | Read |
|---|---|---|
| **Employee** | Request trips, follow your trips, give feedback, record your attendance | Part 1, Part 2 |
| **Department Head** | Everything an employee does, plus approve or reject your department's trip requests | Part 1, Part 2, Part 3 |
| **Driver** | Run the trips assigned to your vehicle, check passengers in and out | Part 1, Part 4 |
| **Transport Manager (Logistics)** | Approve trips, assign vehicles, run the fleet (vehicles, drivers, vendors, routes, factories), handle complaints, read reports | Part 1, Part 2, Part 5, Part 6 |
| **Management** | View all trips, the fleet, complaints and reports (read only) | Part 1, Part 2, Part 6 |
| **Admin / Super Admin** | Everything above, plus users, departments, zones, roles and settings | All parts |

> **You only see what you are allowed to use.** The menu on the left shows
> only the pages your role can open, and buttons such as "Create", "Edit" or
> "Delete" appear only if you may use them. If something in this manual is
> missing from your screen, ask your administrator; it is a permission
> setting, not a fault.

---

## Contents

- [Part 1 — Getting started (everyone)](#part-1--getting-started-everyone)
- [Part 2 — Trips, feedback and attendance (employees)](#part-2--trips-feedback-and-attendance-employees)
- [Part 3 — Approving trips (department heads)](#part-3--approving-trips-department-heads)
- [Part 4 — Driving trips (drivers)](#part-4--driving-trips-drivers)
- [Part 5 — Running transport (transport managers)](#part-5--running-transport-transport-managers)
- [Part 6 — Reports](#part-6--reports)
- [Part 7 — Administration (admins)](#part-7--administration-admins)
- [Part 8 — Reference](#part-8--reference)

---

# Part 1 — Getting started (everyone)

## 1.1 Getting an account

There is no "Sign up" page. An administrator creates your account and tells
you your **username** (or email) and **password**. If you don't have one, ask
your administrator.

## 1.2 Logging in

1. Open the VEMS address in your browser.
2. On **"Log in to your account"**, enter your **"Username or Email"**. Either works.
3. Enter your **"Password"**.
4. Tick **"Remember me"** if this is your own computer.
5. Click **"Log in"**.

If you see **"These credentials do not match our records."**, check the
username and password and try again. After 5 wrong attempts, logging in is
blocked for a short while; wait a minute and try again.

## 1.3 Forgot your password

This only works if your account has an email address.

1. On the login page, click **"Forgot password?"**.
2. Enter your **"Email address"** and click **"Email password reset link"**.
3. Open the email and click the link.
4. Enter your **"Email"**, a new **"Password"** and **"Confirm password"**, then click **"Reset password"**.
5. Log in with the new password.

If your account has **no email address**, or the email doesn't arrive, ask
your administrator to set a new password for you (see [7.1](#71-users)).

## 1.4 "Verify email" page

If you see a **"Verify email"** page instead of the app, VEMS needs you to
confirm your email address (this happens after an email address is added or
changed). Open the email VEMS sent you and click the link. If it didn't
arrive, click **"Resend verification email"**.

## 1.5 Your settings (name, email, password, theme)

1. Click your name at the bottom of the left menu (or your avatar at the top right).
2. Click **"Settings"**.
3. Choose a page on the left:
   - **"Profile"**: change your **"Name"** and **"Email address"**, then click **"Save"**. (Changing your email asks you to verify the new one.)
   - **"Password"**: enter your **"Current password"**, a **"New password"** and **"Confirm password"**, then click **"Save password"**.
   - **"Appearance"**: choose **"Light"**, **"Dark"** or **"System"**.

Other details (phone numbers, department, zone, role, status) can only be
changed by an administrator.

To log out, click your name and choose **"Log out"**.

## 1.6 Finding your way around

- **Left menu:** grouped into **Overview**, **Operations**, **Fleet**,
  **Reports** and **Administration**. Click the toggle at the top left to
  shrink it to icons.
- **Top bar, from right to left:** your avatar menu, the light/dark theme
  switch, the **notification bell**, and (if you work in more than one zone)
  the **zone switcher**.
- **Breadcrumbs** at the top left show where you are; click one to go back.

### Zones (offices)

VEMS is split into **zones**, such as Dhaka (DHK) and Chattogram (CTG).
Vehicles, drivers, vendors, routes, factories and users each belong to a zone.

- If you have access to **specific zones**, you only see records in those zones.
- If you have access to **all zones**, you see everything.
- If you have access to **two or more zones**, a **zone switcher** appears in
  the top bar. Click it and choose a zone under **"Viewing zone"** to narrow
  every list to that zone, or choose **"All Zones"** to see all of *your*
  zones again. This only changes what you are looking at; it never gives you
  access to more.

## 1.7 The notification bell

The bell in the top bar shows a red number when you have unread
notifications. VEMS sends notifications **in the app only** (not by email or
SMS), and never notifies you about something you did yourself.

1. Click the bell to see your latest 10 notifications. Unread ones are highlighted.
2. Click a notification to open the trip, complaint, vehicle or driver it is about.
3. Click **"Mark all read"** to clear the red number.

See [8.2](#82-what-you-get-notified-about) for the full list of notifications.

## 1.8 Working with lists (tables)

Most pages show a table with the same controls:

- **Search box:** type and wait a moment; the list updates on its own. Click the **X** to clear it.
- **"Filters"** button: opens **"Filter Options"**. Pick one or more values per filter. Active filters appear under **"Active Filters:"**; click **"Clear all"** to remove them.
- **Sorting:** click a column heading. Click again to reverse the order.
- **Pages:** at the bottom, choose **"Show 10 / 20 / 50 / 100 per page"** and use the page buttons.
- **Row icons:** hover over an icon to see what it does (for example "View vehicle", "Edit vehicle").
- **"Export"** (on some lists): choose **"CSV File"**, **"Excel File"** or **"PDF Report"**. The export uses your current search and filters.

## 1.9 Messages and confirmations

- After you save something, a message appears at the top right: **green** for
  success, **red** for an error, **amber** for a warning. It closes by itself
  after a few seconds, or click the X.
- Before anything is deleted, VEMS asks **"Please confirm"**. Click
  **"Delete"** to go ahead or **"Cancel"** to stop.
- **"Server error. Please try again, or contact support if it keeps happening."**
  means something went wrong on the server. Try again once; if it repeats,
  tell your administrator what you were doing and when.

## 1.10 Your dashboard

**Dashboard** (top of the left menu) is the first page after logging in. What
it shows depends on your role:

- **Employees and department heads:** **"My Dashboard"** with your attendance
  card, the buttons **"Request a Trip"** and **"Submit Feedback/Complaint"**,
  **"My Trips"** (pending approval, upcoming, in progress, and your next
  trips) and **"My Complaints"**.
- **Drivers:** **"My Dashboard"** with your attendance card, a warning if
  your driving licence is missing, expiring or expired, **"Today's Trips"**
  (trips assigned to your vehicle today and upcoming), and **"My Complaints"**.
- **Transport managers, management and admins:** **"Status At A Glance"**,
  with summary cards for vehicles, drivers, routes, trips, complaints and
  document alerts, charts, **"Upcoming Schedules"** and **"Active Issues &
  Complaints"**. These figures can be up to 5 minutes old.

---

# Part 2 — Trips, feedback and attendance (employees)

## 2.1 How a trip works

```
 You request        Approver            Logistics           Driver              Driver
 the trip     →     approves it    →    assigns a     →     starts it     →     completes it
 (Pending)          (Approved)          vehicle             (In Progress)       (Completed)
                         ↓
                 or rejects it (Rejected)

 A transport manager can cancel the trip at any stage before it is completed (Cancelled).
```

| Status | Meaning |
|---|---|
| **Pending** | Waiting for approval. |
| **Approved** | Approved. Logistics will assign a vehicle if it doesn't have one yet. |
| **In Progress** | The driver has started the trip. |
| **Completed** | The trip has finished. |
| **Rejected** | The request was turned down. The reason is shown on the trip page under **"Rejection Reason"**. |
| **Cancelled** | The trip was cancelled. The reason is shown on the trip page. |

You get a bell notification when your trip is approved or rejected, when a
vehicle is assigned, and if it is cancelled.

## 2.2 Request a trip

1. Click **"Request a Trip"** on your dashboard, or go to **Trips** and click **"Create Trip"**.
2. In **"Trip Details"**:
   - **"Vehicle"**: you can't choose one; Logistics assigns a vehicle after the request is approved.
   - **"Route (Optional)"**: choose a regular route if the trip follows one.
   - **"Trip Type"**: Inspection, Pick-up, Drop-off, Training, Complaints, CVV, Incident Inspection, Officials or Assigned.
   - **"Priority"**: Low, Medium (default), High or Urgent.
   - **"Requesting Department"**: **choose your department.** Your department head can only see and approve the request if this is filled in.
   - Optional: **"Team Number"**, **"Start Location"**, **"End Location"**, **"Remarks"**, **"Description"**, and tick **"Return Trip"** if you need to come back.
3. Optional cards:
   - **"Factories"**: the factories the trip will visit.
   - **"Headcount by Department"**: when a trip is shared, add each department and use **+ / −** to set how many seats it needs.
   - **"Logistics"**: the logistics unit(s) the trip is for.
4. In **"Schedule"**, enter the **"Date"**, **"Start Time"** and **"End Time"**. The end must be after the start.
5. In **"Passengers"**, add who is travelling:
   - Type in **"Add passengers..."** and pick people by name, or
   - choose a group in **"Add by group..."** to add everyone in it at once.
6. Add any **"Notes"**.
7. Click **"Create Trip"**.

The trip is created as **Pending** with a number like **TRP-20261028-0001**,
and the approvers and your department head are notified. You return to the
Trips list.

### Repeating trips (one trip every day for a period)

1. In **"Schedule"**, tick **"Recurring Trip (Multiple Days)"**.
2. Enter the **"Start Date"** and **"End Date"** (the start can't be in the past).
3. Enter the **"Daily Start Time"** and **"Daily End Time"**.
4. Optionally give it a **"Group Name"**, e.g. *Daily Office Transport January 2026*.
5. Check the note **"N trips will be created"** and click **"Create N Trips"**.

VEMS creates **one separate trip for every calendar day** in the range,
**including weekends**, and each needs approval. Keep the range to the days
you actually need.

## 2.3 Follow your trips

1. Go to **Trips**. You see trips you requested, trips you ride on as a
   passenger, and (for department heads) your department's trips.
2. Use the search box (trip number, vehicle registration or requester name)
   and **"Filters"** (Status, Priority, Trip Type, Zone).
3. Click the eye icon on a row to open the trip page. It shows the schedule,
   passengers, the vehicle and driver (once assigned: **"Awaiting vehicle"**
   until then), and the trip's history.

**Need to change or cancel your request?** Employees can't edit or cancel a
trip themselves. Contact your transport manager (Logistics); they can edit
a Pending or Approved trip, or cancel it.

## 2.4 Give feedback or make a complaint about a trip

You can only give feedback on a trip you requested, rode on, or drove.

1. Click **"Submit Feedback/Complaint"** on your dashboard, or go to
   **Feedback & Complaints** and click **"Submit New"**. (You can also use
   **"Submit Feedback / Complaint"** on the trip page.)
2. Fill in **"Submit Feedback or Complaint"**:
   - **"Trip"**: the trip it is about.
   - **"Type"**: **Feedback** or **Complaint**.
   - **"Category"**: Driver Behavior, Vehicle Condition, Punctuality, Safety, Route or Other.
   - **"Subject"** and **"Description"**.
   - For **Feedback**: optional **"Driver Rating"** and **"Vehicle Rating"** (1–5 stars).
   - For a **Complaint**: **"Priority"** (Low, Medium, High, Critical), and tick **"Keep my identity hidden from the driver"** if you wish (your name then shows as "Anonymous").
3. Click **"Submit Feedback"** or **"Submit Complaint"**.

A complaint is sent to the complaint handlers. Follow it under **Feedback &
Complaints**; its status moves from **Open** → **In Review** → **Resolved** →
**Closed**.

## 2.5 Record your attendance

Your attendance card is on your **Dashboard** and on the **Attendance** page
(Operations → **Attendance**).

1. When you start work, click **"Check In"**.
2. When you take a break, click **"Start Break"**; when you return, click **"End Break"**.
3. When you finish, click **"Check Out"**. In the dialog you can add a
   **"Factory visited (optional)"** and a **"Location name (optional)"**,
   then click **"Confirm Check Out"**.

Good to know:
- Your browser asks for your **location**. Allow it so the location is
  recorded. If you refuse, the action still works, without a location.
- You can check in **once per day**. If you check out while on a break, the break is ended for you.
- If you are a passenger on a trip that is **In Progress**, checking in also
  marks you as boarded and checking out marks you as dropped off.
- A check-in far (over 300 m) from any known factory or stop is flagged for
  review; it is still recorded.
- Work over 8 hours a day is counted as overtime.
- The **Attendance** page also shows your **"Recent history"** (last 30 days)
  and **"Today's event trail"**. If something is wrong, ask an administrator
  to correct it.

---

# Part 3 — Approving trips (department heads)

As a department head you can approve or reject trip requests whose
**"Requesting Department"** is a department you head. (An administrator sets
the head on each department; see [7.3](#73-departments).)

You get a bell notification for each new request from your department, and a
daily reminder for requests that are past their date and still pending.

## 3.1 Approve or reject one trip

1. Go to **Trips**. Your department's trips are listed with your own.
2. Open the trip (eye icon).
3. In the **"Actions"** card:
   - Click **"Approve Trip"**, then **"Approve"** to confirm, or
   - click **"Reject Trip"**, type the **"Reason"** (the requester will see it), and click **"Reject"**.

You can also use the **⋮** menu on the trip's row in the list:
**"Approve Trip"** or **"Reject Trip"** (enter the **"Rejection reason"**).

## 3.2 Approve several trips at once

1. On **Trips**, tick the boxes of the Pending trips you want to approve
   (boxes appear only on trips you may approve).
2. Click **"Approve Selected"** in the bar that appears.
3. Click **"Approve N Trip(s)"** to confirm.

Up to 100 trips can be approved at once. This is handy for recurring trips.

After approval, the requester is notified, and Logistics is told the trip
needs a vehicle.

---

# Part 4 — Driving trips (drivers)

## 4.1 Your dashboard and trips

- **"Today's Trips"** on your dashboard lists the trips assigned to your
  vehicle today, plus upcoming ones. Click **"View My Trips"** for all of them.
- You get a notification **"You are driving trip … on …"** when a trip is
  assigned to your vehicle, and if it is cancelled.
- A banner warns you when your **driving licence** is missing, expiring soon
  or expired. You also get licence reminders in the bell. Tell your transport
  manager when you renew it.

You see the trips of the vehicle you are assigned to. If a trip is missing,
check with your transport manager that you are set as that vehicle's driver.

## 4.2 Start a trip

1. Open the trip from your dashboard or the **Trips** list (eye icon).
2. Click **"Start Trip"**. (The trip must be **Approved** and have a vehicle.)
3. Enter the **"Odometer start (optional)"** reading, or leave it empty to add it later.
4. Click **"Start Trip"**. The trip is now **In Progress**.

The reading can't be lower than the vehicle's last recorded reading.

> Use the trip page to start and complete trips. The ⋮ menu on the Trips
> list may not show these options to drivers.

## 4.3 Check passengers in and out

On the trip page, the **"Passenger Attendance"** card lists each passenger
with their planned pickup and drop-off and their actual check-in/out.

1. When a passenger gets in, click **"Check In"** next to their name.
2. When they get out, click **"Check Out"**.
3. If they don't turn up, click **"No Show"**.
4. In the dialog, check the **"Event time"** and **"Stop"**. You can add an
   **"Area or landmark"** and, if you know them, the **"Latitude"** and
   **"Longitude"**. Click **"Save Event"**.

A passenger must be checked in before they can be checked out. If you made a
mistake, ask your transport manager to correct it (drivers can't correct
events).

## 4.4 Complete a trip

1. On the trip page, click **"Complete Trip"**.
2. Fill in what you have (all optional):
   - **"Odometer end (optional)"**: not lower than the start reading, and not more than 3,000 km above it.
   - **"Fuel consumed"**, **"Fuel cost"**, **"Other costs"**, **"Notes"**.
3. Click **"Complete Trip"**. The trip is now **Completed**.

The distance is worked out from the two odometer readings.

## 4.5 Your attendance

Use the attendance card on your dashboard, exactly as described in
[2.5](#25-record-your-attendance).

---

# Part 5 — Running transport (transport managers)

Transport managers (also called **Logistics** in the app) approve trips,
assign vehicles, run trips and look after the fleet.

## 5.1 Daily routine

1. Check the **bell**. Each morning you may get:
   - **"Trips need attention: …"** (07:30): trips still pending approval after their date, approved trips never started, and trips in progress for over 24 hours.
   - Document and licence expiry reminders (07:00).
2. Approve or reject new requests ([5.2](#52-approve-or-reject-trips)).
3. Assign vehicles to approved trips ([5.3](#53-assign-or-change-a-vehicle)).
4. Follow trips in progress and close them ([5.4](#54-start-complete-cancel-and-edit-trips)).
5. Handle new complaints ([5.6](#56-handle-feedback-and-complaints)).

## 5.2 Approve or reject trips

You can approve or reject **any** trip. The steps are the same as for
department heads: see [3.1](#31-approve-or-reject-one-trip) and
[3.2](#32-approve-several-trips-at-once).

## 5.3 Assign or change a vehicle

You get a notification **"Trip … is approved and needs a vehicle."** for each
approved trip without one.

1. Open the trip.
2. In the **"Vehicle & Driver"** card, click **"Assign vehicle"** (or **"Change vehicle"** if one is already assigned).
3. Choose the **"Vehicle"**. The list shows each vehicle's current driver.
4. When **changing** a vehicle, choose a **"Reason"** (Replacement, Breakdown, Damaged, Maintenance or Other).
5. Add **"Notes"** if needed and click **"Assign"** (or **"Change"**).

VEMS refuses the vehicle if it is **already booked on an overlapping trip**
or if it **has fewer seats than the named passengers**. The driver is the
vehicle's current driver; the requester and the driver are both notified.
Every change is kept in **"Vehicle Assignment History"** on the trip page.

> You can also pick a vehicle directly when *you* create a trip.

## 5.4 Start, complete, cancel and edit trips

**Start and complete** work as described for drivers in
[4.2](#42-start-a-trip) and [4.4](#44-complete-a-trip). A trip can't start
without a vehicle.

**Cancel a trip** (any time before it is completed):
1. Open the trip and click **"Cancel Trip"**.
2. Choose the **"Cancellation reason"**: Passenger No-Show, Vehicle Breakdown, Driver Unavailable, Route Blocked, Weather Conditions, Emergency or Other.
3. Add **"Notes"** and click **"Cancel Trip"** (or **"Keep Trip"** to go back).

The requester and the driver are notified.

> Recurring trips are separate trips: cancel each day's trip on its own.

**Edit a trip** (only while **Pending** or **Approved**):
1. Open the trip and click **"Edit Trip"** (or the pencil icon in the list).
2. Change what you need. On this page you can also set each passenger's
   **"Pickup Stop"** and **"Dropoff Stop"** once a route is chosen.
3. Click **"Update Trip"**.

Note: editing an **Approved** trip does not send it back for approval.
Passengers who already have check-in records can't be removed.

## 5.5 Passenger attendance and corrections

You can check passengers in and out exactly as drivers do
([4.3](#43-check-passengers-in-and-out)). You can also **correct** mistakes:

1. On the trip page, scroll to **"Attendance Event History"**.
2. Click **"Correct Event"** on the wrong entry.
3. Choose the **"Replacement event type"** (Check In, Check Out or No Show), adjust the time and location, and enter a **"Correction reason"**.
4. Click **"Apply Correction"**.

The original entry is kept and marked **Voided**, so the history is never lost.

**Passenger Events** (Operations → **Passenger Events**) lists check-ins,
check-outs and no-shows across all trips. Click **"Show"** to open the
filters (Event Type, Validity, Source, Trip, dates, area), then **"Apply Filters"**.

## 5.6 Handle feedback and complaints

New **complaints** (not plain feedback) in your zone trigger a notification.

1. Go to **Feedback & Complaints** and open the complaint.
2. Click **"Assign to Me"**. The status becomes **In Review**.
   *(Do this first: a complaint must be In Review before it can be resolved.)*
3. Look into it. When it is sorted, enter the **"Resolution notes"** in the
   **"Resolve"** card and click **"Mark Resolved"**.
4. Then either:
   - click **"Confirm & Close"** to finish it, or
   - click **"Reopen"** if it needs more work.

To close an **Open** complaint that needs no action, click **"Close Without
Resolving"** (before assigning it).

## 5.7 Vehicles

Fleet → **Vehicles**.

### Add a vehicle
1. Click **"Add Vehicle"**.
2. **"Vehicle Information"** (required fields marked *):
   - **"Brand"**, **"Model"**, **"Registration Number"** (must be unique), optional **"Color"**.
   - **"Vehicle Type"**: Sedan, SUV, Van, Microbus, Coaster, Bus, Pickup, Truck or Other.
   - **"Rental Type"**: Own (Company Owned), Pool (Shared Vehicle), Rental (Leased), Ad-hoc Rental or Support Vehicle.
   - **"Capacity (Seats)"**, **"Service Provider"** (the vendor), **"Driver"**, **"Zone"**, **"Active Status"**.
   - For an **Ad-hoc Rental** whose details you don't know yet, you may leave brand, model, registration, provider and driver empty; VEMS fills in placeholders (registration **ADHOC-001**, …) and marks it **"Details pending"**.
3. Optional sections: **"Parking Location"**, **"Vehicle Details"** (year, fuel, engine and chassis numbers).
4. **Documents**: for **"Documentation"** (tax token, fitness certificate), **"Insurance"**, **"Route Permit"** and **"Registration & Owner"**, enter the number, the **expiry date** and upload the file (JPG, PNG or PDF, up to 2 MB).
5. **"Alert Settings"**: **"Alert Days Before Expiry"** (default 30) and which documents to send alerts for.
6. Click **"Create Vehicle"**.

> **Keep expiry dates up to date.** Expiry badges, reminders and the
> Compliance report all rely on them. When a document is renewed, edit the
> vehicle and enter the new date and file.

### View, edit and delete
- Click a row to open the vehicle page. A red box at the top lists documents
  that are expiring or expired; **"View file"** opens a document.
- Click **"Edit Vehicle"**, change what you need, and click **"Update Vehicle"**.
  Uploading a new file replaces the old one.
- To **activate or deactivate several vehicles**, tick them in the list and
  click **"Activate Selected"** or **"Deactivate Selected"**.
- **Delete** (admins only) is refused while the vehicle has open trips. Past
  trips and reports keep the deleted vehicle's details.

### Assign a driver to a vehicle
1. Click **"Assign Driver"** on the vehicle page (or the assign icon in the list).
2. Choose the **"Driver"** (or **"— Unassign —"**) and click **"Save"**.

Only **active**, **available** drivers with a **valid licence** can be
assigned. If the driver already drives another vehicle, VEMS says so: tick
**"Confirm reassign"** and click **"Save"** again to move them. Every change
is kept in **"Driver Assignment History"**.

### Record downtime (vehicle out of service)
On the vehicle page, in the **"Downtime"** card:
1. Click **"Add downtime"**.
2. Enter **"From"** and, if it is already back, **"To"** (leave **"To"** empty while it is still out of service).
3. Choose the **"Reason"** (Repair, Accident, Servicing, Document renewal or Other), an optional **"Replacement vehicle"** and **"Notes"**.
4. Click **"Add"**.

When the vehicle is back, click **"End now"**. Downtime periods can't
overlap. Downtime is used by the **Monthly Vehicle Report**; it does **not**
stop the vehicle being picked for trips, so make sure you don't assign a
vehicle that is in the workshop.

## 5.8 Drivers

Fleet → **Drivers**. The list shows each driver's vehicle, vendor and a
licence badge: **Valid**, **Expiring Soon** (within 30 days), **Expired** or
**No Expiry Set**.

### Add a driver
1. Click **"Add Driver"**.
2. Fill in **"Full Name"**, optional **"Username"** (left blank, it is made
   from the email or name; see [7.1](#71-users)), and the zone access.
3. In **"Driver Information"**: **"Vendor"**, **"License Class"** (A–D),
   **"Driving License Number"** and **"License Expiry Date"** are required.
   Upload the **"License Scan"** if you have it.
4. In **"Additional Details"**: **"Personal Phone"** and **"WhatsApp ID"** are
   required (even though they have no *), and **"Status"**.
5. Enter a **"Password"** and **"Confirm Password"** and give them to the driver.
6. Click **"Create Driver"** (it becomes active once all required fields are filled).

### Import many drivers from Excel
1. Click **"Import Drivers"**, then **"Download template"**.
2. Open the file. On the **"Drivers"** sheet, the **shaded headers are required**:
   **name, personal_phone, vendor, zone_code, driving_license_no, license_class, license_expiry_date**.
   The **"Instructions"** sheet explains every column; the **"Vendors"**,
   **"Zones"** and **"Departments"** sheets list the values you can use.
3. **Delete the example row**, then add one row per driver. Dates may be
   typed like **2030-01-14** or **14/01/2030**.
4. Save, then in **"Import Drivers"** choose the **"File"** and click **"Import"**.
5. Read the result: **"Imported N driver(s) successfully."**, or which rows
   were skipped and why (fix those rows and import just them again).

A licence number that is already in VEMS is skipped, so importing the same
file twice doesn't create duplicates. Imported drivers get an unknown
password: **set a password for each one** (Edit → **"New Password"**) before
they log in.

### Other driver tasks
- **Edit:** open the driver and click **"Edit Driver"**. Leave **"New Password"** empty to keep the current one.
- **Change status of several drivers:** tick them, click **"Change Driver
  Status"**, choose **"Driver Status"** (Available, On Trip, On Leave,
  Inactive, Suspended) and click **"Update N Driver(s)"**.
- **Assign a vehicle:** click **"Assign Vehicle"** on the driver page and pick
  the vehicle. If the vehicle already has a driver, that driver is replaced.
- **Export:** use **"Export"** above the table.

## 5.9 Vendors (service providers)

Fleet → **Vendors**.

1. Click **"Add Vendor"**.
2. **"Basic Information"**: **"Service Provider Name"** and **"Status"** are required; zone, phone, email, website, address and description are optional.
3. **"Documents & Bank Details"** (optional): trade licence, TIN, BIN and tax return numbers with their files, and bank details.
4. **"Contact Persons"**: at least one person with a **"Name"**. Click **"Add Person"** for more and tick **"Primary contact"** on the main one.
5. Click **"Create Vendor"**.

Only **active** vendors can be chosen for vehicles and drivers. A vendor that
still has vehicles can't be deleted.

## 5.10 Routes and stops

Fleet → **Routes**. A route is a named, ordered list of stops that trips can follow.

1. Click **"Create Route"**.
2. In **"Route Information"**, choose the **"Zone"** and enter the **"Route Name"** and **"Description"**.
3. In **"Route Stops"**, click **"Add Stop"** for each stop, then in **"Stop Location"**:
   - type to **search existing stops**, or
   - choose **"Create new stop"** and enter its name, or
   - click the **map icon** to pick it on the map: click a saved stop and
     **"Add to Route"**, or click **"Create New Stop"**, click the spot on the
     map, enter **"Stop Name"** and **"Address"**, and click **"Create Stop"**.
4. Optionally enter each stop's **"Arrival Time"** and **"Departure Time"**.
5. Put the stops in order with the arrow buttons (move up / down / to top / to bottom).
6. Click **"Create Route"**.

Distances between stops are calculated automatically from their map
positions (straight-line, not road distance). Switch to **"Manual Distance"**
to type real distances instead.

## 5.11 Factories

Fleet → **Factories**. Factories are the places trips visit.

1. Click **"Add Factory"** and fill in **"Account ID"** (unique), **"Factory
   Name"**, **"Status"**, and optionally city, latitude/longitude, one-way
   mileage, address and zone.
2. Click **"Create Factory"**.

A deleted factory can be brought back: click **"Show deleted"**, then **"Restore"**.

---

# Part 6 — Reports

The **Reports** section of the menu is available to transport managers,
management and admins. Each report has filters at the top; set them and
click **"Apply"** / **"Apply Filters"**. Export with **"Excel"**, **"PDF"** or
the **"Export"** button where shown. Reports only include your zones.

| Report | What it answers |
|---|---|
| **Trip Report** (page title "Reports Module") | How many trips, passengers and no-shows in a period; trends by day, status, route and schedule type; full trip history. Default: last 30 days. |
| **Monthly Vehicle Report** | For up to 31 days: vehicles used per day by purpose, trips by purpose, vehicles down (from downtime records), ad-hoc vehicles needed, by office, service quality and a vendor scorecard. **"Summary"** and **"Details"** tabs. |
| **Complaints & SLA** | Complaints in the period, how fast they were resolved, which are still open, and which are past their target (Critical 24 h, High 48 h, Medium 72 h, Low 120 h). |
| **Driver Performance** | Trips, completion, on-time starts, ratings and complaints per driver. |
| **Compliance & Expiry** | Vehicle documents (tax token, fitness, insurance, route permit) and driving licences that are expired or due within 30/60/90 days, as of today, plus records missing an expiry date. |
| **Department Usage** | Trips, shared-trip headcount, passengers, no-shows and kilometres per department. |

**Attendance Reports** (Operations → **Attendance Reports**, management and
admins) shows everyone's attendance: filter by **"User"**, dates, **"Used
Transport"** and **"Anomaly"**, choose columns with **"Columns"**, and export.
Click the eye icon to see a day's events. Admins can **"Correct"** an event's
time (a **"Reason"** is required; the original is kept).

---

# Part 7 — Administration (admins)

## 7.1 Users

Administration → **Users**. This page lists employee accounts. Drivers are
managed under **Drivers** ([5.8](#58-drivers)).

### Add a user
1. Click **"Add User"**.
2. Fill in:
   - **"Full Name"** (required).
   - **"Username"** (optional). Left blank, VEMS makes one:
     - from the email: **rowshan.runa@rsc-bd.org → rowshan.runa**;
     - with no email, from the first and last name without titles (Md, Mr, Mrs, Ms, Miss, Dr, Engr, Prof): **Md. Shajahan Mia → shajahan.mia**;
     - if that is taken: **mdshajahan.mia**, then **shajahan.mia2**, **shajahan.mia3**, …
   - **"Employee ID"**, **"Email Address"**, **"Designation"**, **"Department"**.
   - **"Zone Access"** (required): **"All Zones"**, or one or more zones.
   - **"Roles"** (required): e.g. employee, department-head, management, transport-manager.
3. In **"Additional Details"**, **"Personal Phone"** and **"WhatsApp ID"** are
   required (even though they have no *), and **"Status"**. The rest is optional.
4. Enter a **"Password"** and **"Confirm Password"**.
5. Click **"Create User"** (it becomes active when the form reaches 100%).
6. Give the user their username and password.

> **Give every user an email address if you can.** Without one they can't
> use "Forgot password", and you will have to reset their password for them.

### Import many users from Excel
1. Click **"Import Users"**, then **"Download template"**.
2. Fill in the **"Users"** sheet. Required: **name** and **zone_code**
   (one code, several separated by commas, or **ALL**). Optional: username
   (made automatically if blank), email, employee_id, designation,
   department_code, personal_phone, whatsapp_id, role (blank = employee) and
   status. The other sheets list valid department codes, zones and roles.
3. Delete the example row and save.
4. Choose the **"File"** and click **"Import"**. Read the result message;
   skipped rows say why.

Drivers and super-admins can't be imported here. Imported users get an
unknown password: they use **"Forgot password"** (if they have an email), or
you set one for them.

> Before re-importing a file, check the user list: rows with no email and no
> employee ID would be created again under a new username.

### Edit a user or reset a password
1. Open the user and click **"Edit User"**.
2. Change details. To reset the password, fill in **"New Password"** and
   **"Confirm New Password"**; leave them empty to keep the current one.
3. Click **"Update User"**.

Only users allowed to manage roles can change roles, and only a super admin
can give or remove the super-admin role.

### Deactivate, activate, delete
- **Deactivate:** click the deactivate icon. **"Remove from all groups"** is
  ticked by default. Click **"Deactivate"**.
- **Activate:** click the activate icon. **"Add back to the groups they were
  removed from"** is ticked by default. Click **"Activate"**.
- **Delete:** only possible for users with no trip history. Otherwise
  deactivate them instead.

**"Export Users"** downloads all employee accounts as a CSV file.

## 7.2 User groups

Administration → **User Groups**. Groups let requesters add a whole team as
trip passengers in one click (**"Add by group..."**).

1. Click **"Create Group"**, enter a unique **"Group Name"** and a **"Description"**.
2. Click **"Add Users"**, search, and select members. (Drivers can't be added.)
3. Click **"Create Group"**.

On a group's page, use **"Add Members"** or the remove icon. Only **Active**
groups are offered on the trip form.

## 7.3 Departments

Administration → **Departments**.

1. Click **"Add Department"**. Enter **"Department Name"** and
   **"Department Code"** (up to 10 characters, unique), and optionally
   description, location, phone and email.
2. **"Department Head"**: choose the person who approves this department's
   trips. **Set a head for every department**, otherwise department trip
   requests can only be approved by transport managers.
3. Leave **"Active Department"** on and click **"Create Department"**.

**"Export Departments"** and **"Import Departments"** work with CSV/Excel
(import columns: name, code, description, location, phone, email, is_active,
attendance_mode). A department with users can't be deleted.

## 7.4 Zones

Administration → **Zones**. Click **"Add Zone"**, enter a **"Zone Name"**
(e.g. Dhaka) and **"Zone Code"** (e.g. DHK, up to 10 characters), and click **"Create Zone"**. A zone
can't be deleted while users, vehicles, stops, routes, factories, vendors or
logistics still belong to it.

## 7.5 Logistics units

Fleet → **Logistics**. A logistics unit is a named team (e.g. *Fleet
Management*) belonging to a department. Requesters can tag a trip with the
unit(s) it is for.

1. Click **"Create Logistics"**, enter **"Name"**, **"Department"**, **"Status"** and an optional description and zone.
2. Use the lock icon to switch a unit between active and inactive. Only active units appear on the trip form.

## 7.6 Roles and permissions

Administration → **Roles**. A role is a set of permissions; each user has one
or more roles.

| Built-in role | In short |
|---|---|
| **super-admin** | Everything. |
| **admin** | Everything (operations and administration). |
| **transport-manager** | Trips (approve, assign vehicles, run, cancel), fleet, drivers, vendors, routes, factories, complaints, reports. Cannot delete records or manage users, roles or zones. |
| **management** | Read-only view of trips, fleet, complaints, reports and attendance reports; can request trips and submit complaints. |
| **department-head** | An employee who can also approve/reject their own departments' trips. |
| **employee** | Request trips, see own trips, submit feedback, own attendance. |
| **driver** | Run trips on their vehicle, check passengers in/out, own attendance. |

- **Create a role:** click **"Create Role"**, enter the **"Role Name"**, leave
  **"Guard Name"** on **Web**, tick permissions (use **"Search permissions..."**,
  **"Select All"** / **"Deselect All"**), and save.
- **Edit a role:** open it, change the ticked permissions, click **"Update Role"**.
- The 7 built-in roles can't be renamed or deleted, and a role that still has users can't be deleted.

> Changing a built-in role's permissions here works, but those defaults are
> restored if a developer re-runs the setup command. Tell your developer
> about permission changes you want to keep.

## 7.7 Activity Log

Administration → **Activity Log** shows who did what: logins, and changes to
users, roles, groups, departments, factories, logistics, routes, vehicles and
vendors. Search, and filter by **"Area"**, **"Event"** and **"User"**.
Entries are kept for a year.

---

# Part 8 — Reference

## 8.1 Who can do what

| Task | Employee | Dept. Head | Driver | Transport Mgr | Management | Admin |
|---|:-:|:-:|:-:|:-:|:-:|:-:|
| Request a trip | ✓ | ✓ | | ✓ | ✓ | ✓ |
| See own trips | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| See all trips | | own dept. | | ✓ | ✓ | ✓ |
| Approve / reject trips | | own dept. | | ✓ | | ✓ |
| Assign vehicles | | | | ✓ | | ✓ |
| Start / complete trips | | | own vehicle | ✓ | | ✓ |
| Edit / cancel trips | | | | ✓ | | ✓ |
| Check passengers in/out | | | own vehicle | ✓ | | ✓ |
| Correct passenger events | | | | ✓ | | ✓ |
| Submit feedback / complaints | ✓ | ✓ | ✓ | | ✓ | ✓ |
| Handle complaints | | | | ✓ | view | ✓ |
| Own attendance | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Attendance reports | | | | | ✓ | ✓ |
| Vehicles, drivers, vendors, routes, factories | | | view vehicles | ✓ (no delete) | view | ✓ |
| Reports | | | | ✓ | ✓ | ✓ |
| Users, groups, departments, zones, roles, logistics, activity log | | | | | | ✓ |

## 8.2 What you get notified about

| When | Who is notified |
|---|---|
| A trip is requested | Approvers in that zone, and the head of the requesting department |
| A trip is approved or rejected | The requester |
| An approved trip has no vehicle | Logistics (transport managers) in that zone |
| A vehicle is assigned to a trip | The requester and the vehicle's driver |
| A trip is cancelled | The requester and the driver |
| A complaint is submitted | Complaint handlers in that zone |
| A complaint is assigned to you | You |
| Daily, 07:00: vehicle documents or driving licences expiring | Transport managers/admins in that zone, and the driver for their own licence. Sent 30 days before (or the vehicle's alert days), 7 days, 1 day, on the day, then weekly once expired. |
| Daily, 07:30: trips needing attention | Approvers and Logistics, for trips overdue for approval, never started, or running over 24 hours |

## 8.3 Common problems

| Problem | What to do |
|---|---|
| A menu item or button described here is missing | Your role doesn't include it. Ask your administrator. |
| My department head can't approve my trip | Make sure **"Requesting Department"** was filled in on the trip, and that the department has a **"Department Head"** set. |
| I can't edit or cancel my own trip | Only transport managers can. Contact Logistics. |
| "Forgot password" email doesn't arrive | Check spam. If your account has no email, ask an administrator to set a new password. |
| A trip can't be started | It needs to be **Approved** and have a vehicle assigned. |
| "This vehicle is already booked on an overlapping trip" | Choose another vehicle, or change the trip's time. |
| "This vehicle seats X, but N passengers were selected" | Choose a bigger vehicle, or split the trip. |
| A driver can't be assigned to a vehicle | The driver must be active, **Available** and have a valid licence. |
| "Mark Resolved" fails on a complaint | Click **"Assign to Me"** first so it is In Review. |
| An import says some rows were skipped | The message lists the row numbers and reasons. Fix those rows and import only them again. |
| "Server error" | Try again once. If it repeats, tell your administrator what you did and when. |

## 8.4 Known limitations

These are current gaps in VEMS. Work around them as described until they are fixed.

- **Deactivating a user doesn't yet stop them logging in.** If someone must
  lose access immediately, also change their password.
- **Admin accounts don't appear on the Users list** (it shows employee
  accounts only).
- **Transport managers can't use "Add Driver"** (they get "You don't have
  permission"). Use **"Import Drivers"**, or ask an admin.
- **Editing a transport manager from the Drivers page turns them into a
  driver.** Edit transport managers under **Users** instead.
- **A vehicle's "Condition" and regular "Pick & Drop Route"** are shown but
  can't be changed yet.
- **Routes can't be deleted** from the Routes list.
- **A whole recurring trip series can't be cancelled in one go**; cancel each day.
- **Trips can't be deleted**; cancel them instead.
- **The trip page doesn't yet show** the odometer, fuel, costs or distance
  entered on completion, or the feedback given on that trip (see it under
  **Feedback & Complaints** instead).
- **The "Driver" column on the Trips list** may show "Not assigned"; the trip
  page shows the vehicle and its driver.
- **The Feedback & Complaints list filters** don't filter yet; use the search box.
- **Attendance mode** (self-service / biometric) has no effect yet: everyone
  can use Check In / Check Out.
- On the **Edit Driver** page, choose only **Available, On Leave, Inactive or
  Suspended** as driver status, and only licence classes **A–D**. The other
  options are refused.

## 8.5 For administrators: server commands

These are run on the server by IT, not in the app.

| Command | What it does |
|---|---|
| `php artisan vems:create-admin` | Creates the first super-admin on a new installation. |
| `php artisan permissions:update` | Adds new permissions and roles after an update. **Safe** on a live system: never removes anything. |
| `php artisan setup:permissions` | Resets all roles and permissions to the defaults and **deletes custom roles**. Do **not** run on a live system. `php artisan db:seed` does the same. |
| `php artisan vems:test-mail you@rsc-bd.org` | Sends a test email and shows the mail settings, to check password-reset emails work. |
| `php artisan vems:expiry-reminders` | Sends the document and licence reminders (runs daily at 07:00). |
| `php artisan vems:trips-needing-attention` | Sends the "trips need attention" summary (runs daily at 07:30). |
| `php artisan backup:run` / `backup:list` | Takes a backup now / lists backups (backups run nightly). |

Scheduled jobs only run if the server's cron calls `php artisan schedule:run`
every minute. See [DEPLOYMENT.md](DEPLOYMENT.md).
