# VEMS walkthrough checklist (before go-live)

Use this to try VEMS by hand with the demo data, one role at a time. Tick each box. When something doesn't match the **Expect** line, note the step number, the account, and what you saw.

The main flow (sections 2–5) is also checked automatically by `tests/Feature/EndToEndWorkflowTest.php`. This checklist is for what a test can't judge: whether screens are clear, buttons are where you'd look, and the wording makes sense.

## 1. Setup (local only: this wipes the local database)

```bash
php artisan migrate:fresh --seed
php artisan storage:link
composer dev
```

Open http://localhost:8000. Every demo password is `password`.

| Role | Login | Office |
|---|---|---|
| Super admin | `admin` | Dhaka |
| Transport manager (Logistics) | `sarah.manager` | All offices |
| Department head (Administration) | `khadiza.rahman` | Dhaka |
| Management (read-only) | `hasan.sarker` | Dhaka |
| Employee (Administration) | `maria.admin` | Dhaka |
| Employee (HR) | `john.hr` | Dhaka |
| Driver | `ahmed.driver` | per seed |

Use a private browser window for each account, so you can stay logged in as several people at once.

## 2. Employee requests a trip (`maria.admin`)

- [ ] **2.1** Trips → Create. Fill in a date, times and destination. Choose department **Administration**.
  **Expect:** no vehicle field, just a note that Logistics will assign one.
- [ ] **2.2** Save.
  **Expect:** the trip shows **Pending**, and the trips list shows **Awaiting vehicle**.
- [ ] **2.3** Log in as `john.hr` and try to open Maria's trip (paste its URL).
  **Expect:** access denied.

## 3. Department head approves (`khadiza.rahman`)

- [ ] **3.1** The bell shows **New trip request … from Maria**.
- [ ] **3.2** Trips: she sees only Administration's trips. Open Maria's trip and click **Approve**.
  **Expect:** a confirmation dialog, then **Approved**.
- [ ] **3.3** Try rejecting a trip from another department (if there is one).
  **Expect:** no Reject button.
- [ ] **3.4** Reject a second Administration request.
  **Expect:** a dialog asking for a reason; the reason can't be left empty.

## 4. Logistics assigns a vehicle (`sarah.manager`)

- [ ] **4.1** The bell shows **Trip … is approved and needs a vehicle**.
- [ ] **4.2** Vehicles → open **ABC-123** → assign driver **Ahmed**.
- [ ] **4.3** Open Maria's trip → **Assign vehicle** → ABC-123.
  **Expect:** no reason is asked for, since this is the first assignment.
- [ ] **4.4** Click **Change vehicle**.
  **Expect:** this time a reason **is** required.
- [ ] **4.5** Maria's bell shows **ABC-123 is assigned to your trip**, and Ahmed's bell shows **You are driving trip …**.

## 5. Driver runs the trip (`ahmed.driver`)

- [ ] **5.1** The dashboard shows the trip. Open it → **Start**, entering an odometer reading.
- [ ] **5.2** **Complete**, entering a higher odometer reading.
  **Expect:** **Completed**, with the distance calculated.
- [ ] **5.3** Check passenger check-in and check-out work, if the trip has passengers.

## 6. Complaint (`maria.admin` → `sarah.manager`)

- [ ] **6.1** Maria → Feedback & Complaints → new complaint on the completed trip.
- [ ] **6.2** Sarah's bell shows **New complaint**. Assign it to herself, resolve it, then **Close** it.
  **Expect:** a confirmation dialog before closing.

## 7. Management is read-only (`hasan.sarker`)

- [ ] **7.1** Can open Trips, Vehicles, Drivers and every report under **Reports**.
- [ ] **7.2** Sees **no** Create, Edit, Delete, Approve or Assign buttons anywhere.
- [ ] **7.3** Users, Roles and Zones are not in the sidebar.

## 8. Offices (zones)

- [ ] **8.1** As `admin`, create a Chattogram vehicle and a Chattogram-only user with `view-vehicles`.
- [ ] **8.2** Log in as that user.
  **Expect:** only Chattogram vehicles. Opening a Dhaka vehicle's URL says not found.
- [ ] **8.3** A user with several offices sees the office switcher in the top bar.

## 9. Private documents

- [ ] **9.1** Upload a driver's NID and a vehicle's tax token. Both open from the driver and vehicle pages.
- [ ] **9.2** Copy a document link, log out, and paste it.
  **Expect:** a login page, not the file.
- [ ] **9.3** As `maria.admin` (no driver permissions), paste a driver's document link.
  **Expect:** access denied.

## 10. Reports and exports (`sarah.manager`)

- [ ] **10.1** Open each report: Trip Report, Monthly Vehicle, Complaints & SLA, Driver Performance, Compliance & Expiry, Department Usage.
- [ ] **10.2** Change the filters. The numbers should make sense for the data you created above.
- [ ] **10.3** Download Excel and PDF from at least two reports, and open both files.

## 11. Daily jobs (run them by hand)

- [ ] **11.1** Give a vehicle a tax token expiring **7 days from today** (alerts enabled), then run:
  ```bash
  php artisan vems:expiry-reminders
  ```
  **Expect:** Sarah's bell shows the reminder.
- [ ] **11.2** Leave a trip **Pending** with yesterday's date, then run:
  ```bash
  php artisan vems:trips-needing-attention
  ```
  **Expect:** approvers get **Trips need attention**.
- [ ] **11.3** Run `php artisan backup:run`.
  **Expect:** a zip appears in `storage/app/backups` (needs `mysqldump` on the PATH).

## 12. Phone check

- [ ] **12.1** Open the site on a phone, or a narrow browser window. Check the trip, vehicle and vendor pages.
  **Expect:** nothing cut off, and tables scroll sideways.
- [ ] **12.2** As the driver, start and complete a trip from the phone.

## 13. Before go-live

- [ ] Every issue found above is fixed or accepted.
- [ ] The server is set up per `docs/DEPLOYMENT.md`: `.env`, cron, queue worker, backups copied off the server.
- [ ] Production database seeded with `php artisan db:seed --force`, then `php artisan vems:create-admin`.
- [ ] Real departments, offices, users (with roles and office access), vendors, vehicles and drivers entered.
- [ ] Each department's **Head** set, so department heads can approve.
