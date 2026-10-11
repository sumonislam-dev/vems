<?php

use App\Exports\AnalyticsReportExport;
use App\Models\Department;
use App\Models\Trip;
use App\Models\TripFeedback;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDriverAssignment;
use App\Models\Zone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * The table-style reports behind AnalyticsReportController: Complaints & SLA,
 * Driver Performance, Compliance & Expiry and Department Usage.
 */
beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    Permission::firstOrCreate(['name' => 'view-reports', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'export-reports', 'guard_name' => 'web']);
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $this->dhaka = Zone::create(['name' => 'Dhaka', 'code' => 'DHK', 'is_active' => true]);
    $this->ctg = Zone::create(['name' => 'Chattogram', 'code' => 'CTG', 'is_active' => true]);
});

function analyticsUser(array $permissions = ['view-reports', 'export-reports'], array $attributes = []): User
{
    $user = User::create(array_merge([
        'email_verified_at' => now(),
        'name' => 'Analytics '.uniqid(),
        'username' => 'analytics_'.uniqid(),
        'email' => 'analytics_'.uniqid().'@example.com',
        'user_type' => 'employee',
        'status' => 'active',
        'password' => Hash::make('password'),
    ], $attributes));
    $user->givePermissionTo($permissions);

    return $user;
}

function analyticsVehicle(Zone $zone, array $attributes = []): Vehicle
{
    return Vehicle::create(array_merge(['brand' => 'Toyota', 'model' => 'Hiace', 'registration_number' => 'AN-'.uniqid(), 'is_active' => true, 'zone_id' => $zone->id], $attributes));
}

function analyticsTrip(array $attributes = []): Trip
{
    return Trip::create(array_merge([
        'requested_by' => analyticsUser([])->id,
        'priority' => 'medium',
        'scheduled_date' => '2026-03-10',
        'scheduled_start_time' => '08:00',
        'scheduled_end_time' => '09:00',
        'status' => 'completed',
    ], $attributes));
}

function analyticsComplaint(Trip $trip, string $created, array $attributes = []): TripFeedback
{
    $complaint = TripFeedback::create(array_merge([
        'trip_id' => $trip->id,
        'submitted_by' => $trip->requested_by,
        'type' => 'complaint',
        'category' => 'punctuality',
        'subject' => 'Late',
        'description' => 'Late pickup',
        'priority' => 'medium',
        'status' => 'open',
    ], $attributes));
    $complaint->forceFill(['created_at' => $created])->saveQuietly();

    return $complaint;
}

function summaryValue(array $report, string $label): mixed
{
    return collect($report['summary'])->firstWhere('label', $label)['value'] ?? null;
}

function section(array $report, string $key): array
{
    return collect($report['sections'])->firstWhere('key', $key);
}

// --- access & exports -----------------------------------------------------------------

it('shows every report to report viewers only', function (string $slug) {
    $this->actingAs(analyticsUser(['view-reports']))->get("/reports/{$slug}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('reports/analytics')->where('slug', $slug));
    $this->actingAs(analyticsUser([]))->get("/reports/{$slug}")->assertForbidden();
})->with(['complaints', 'driver-performance', 'compliance', 'department-usage']);

it('exports a report to Excel and PDF', function () {
    Excel::fake();
    $user = analyticsUser();

    $this->actingAs($user)->get('/reports/complaints/export?format=excel&from=2026-03-01&to=2026-03-31')->assertOk();
    Excel::assertDownloaded('complaints-report-20260301-20260331.xlsx', fn (AnalyticsReportExport $export) => count($export->sheets()) === 8);

    $this->actingAs($user)->get('/reports/department-usage/export?format=pdf&from=2026-03-01&to=2026-03-31')
        ->assertOk()
        ->assertDownload('department-usage-report-20260301-20260331.pdf');

    $this->actingAs(analyticsUser(['view-reports']))->get('/reports/complaints/export?format=excel')->assertForbidden();
});

// --- complaints & SLA ---------------------------------------------------------------------

it('measures complaint resolution against the SLA, within the viewer\'s offices', function () {
    Carbon::setTestNow('2026-03-31 12:00:00');
    $trip = analyticsTrip(['requested_by' => analyticsUser([], ['zone_id' => $this->dhaka->id])->id]);
    // medium = 72h SLA
    analyticsComplaint($trip, '2026-03-02 09:00:00', ['status' => 'resolved', 'resolved_at' => '2026-03-03 09:00:00']); // 24h, within
    analyticsComplaint($trip, '2026-03-05 09:00:00', ['status' => 'resolved', 'resolved_at' => '2026-03-10 09:00:00']); // 120h, late
    analyticsComplaint($trip, '2026-03-20 09:00:00', ['priority' => 'critical']); // open 11 days, past its 24h SLA
    analyticsComplaint(analyticsTrip(['requested_by' => analyticsUser([], ['zone_id' => $this->ctg->id])->id]), '2026-03-06 09:00:00'); // other office

    $report = $this->actingAs(analyticsUser(['view-reports'], ['zone_id' => $this->dhaka->id]))
        ->get('/reports/complaints?from=2026-03-01&to=2026-03-31')
        ->assertOk()
        ->viewData('page')['props']['report'];

    expect(summaryValue($report, 'Complaints'))->toBe(3)
        ->and(summaryValue($report, 'Within SLA'))->toBe(50.0)
        ->and(summaryValue($report, 'Avg hours to resolve'))->toBe(72.0)
        ->and(summaryValue($report, 'Open past SLA (any date)'))->toBe(1)
        ->and(section($report, 'overdue')['rows'][0]['priority'])->toBe('Critical')
        ->and(collect(section($report, 'ageing')['rows'])->firstWhere('age', '8–14 days')['open'])->toBe(1);

    Carbon::setTestNow();
});

// --- driver performance ---------------------------------------------------------------

it('credits each trip to whoever drove the vehicle that day, and checks on-time starts', function () {
    $vehicle = analyticsVehicle($this->dhaka);
    $karim = analyticsUser([], ['name' => 'Karim', 'user_type' => 'driver']);
    $rahim = analyticsUser([], ['name' => 'Rahim', 'user_type' => 'driver']);
    VehicleDriverAssignment::create(['vehicle_id' => $vehicle->id, 'driver_id' => $karim->id, 'started_at' => '2026-03-01 00:00:00', 'ended_at' => '2026-03-05 23:59:59', 'is_current' => false]);
    VehicleDriverAssignment::create(['vehicle_id' => $vehicle->id, 'driver_id' => $rahim->id, 'started_at' => '2026-03-06 00:00:00', 'is_current' => true]);

    // 08:00 Dhaka is 02:00 UTC: 02:10 UTC is on time, 03:00 UTC is late
    analyticsTrip(['vehicle_id' => $vehicle->id, 'scheduled_date' => '2026-03-03', 'actual_start_time' => '2026-03-03 02:10:00', 'odometer_start' => 1000, 'odometer_end' => 1040]);
    analyticsTrip(['vehicle_id' => $vehicle->id, 'scheduled_date' => '2026-03-08', 'actual_start_time' => '2026-03-08 03:00:00']);
    analyticsTrip(['vehicle_id' => $vehicle->id, 'scheduled_date' => '2026-03-09', 'status' => 'cancelled', 'cancellation_reason' => 'driver_unavailable']);

    $report = $this->actingAs(analyticsUser())->get('/reports/driver-performance?from=2026-03-01&to=2026-03-31')
        ->viewData('page')['props']['report'];
    $rows = collect(section($report, 'drivers')['rows'])->keyBy('driver');

    expect($rows['Karim'])->toMatchArray(['trips' => 1, 'completed' => 1, 'on_time' => 100.0, 'km' => 40.0])
        ->and($rows['Rahim'])->toMatchArray(['trips' => 2, 'completed' => 1, 'cancelled' => 1, 'driver_unavailable' => 1, 'on_time' => 0.0]);
});

// --- compliance -------------------------------------------------------------------------

it('lists expired and due documents within the chosen window, and missing dates', function () {
    Carbon::setTestNow('2026-03-15 10:00:00');
    analyticsVehicle($this->dhaka, [
        'registration_number' => 'DHK-1',
        'tax_token_last_date' => '2026-03-10',          // expired
        'fitness_certificate_last_date' => '2026-03-20', // 5 days
        'insurance_last_date' => '2026-04-29',          // 45 days
        // route permit missing
    ]);
    analyticsUser([], ['name' => 'No Licence', 'user_type' => 'driver', 'zone_id' => $this->dhaka->id]);
    $viewer = analyticsUser();

    $report = $this->actingAs($viewer)->get('/reports/compliance')->viewData('page')['props']['report'];
    $due = collect(section($report, 'due')['rows']);

    expect($due->pluck('document')->all())->toBe(['Tax token', 'Fitness certificate'])
        ->and($due->first()['_tone'])->toBe('danger')
        ->and(summaryValue($report, 'Expired'))->toBe(1)
        ->and(summaryValue($report, 'Due in 7 days'))->toBe(1)
        ->and(collect(section($report, 'missing')['rows'])->pluck('missing')->all())->toBe(['Route permit', 'Driving licence']);

    $wider = $this->actingAs($viewer)->get('/reports/compliance?window=60')->viewData('page')['props']['report'];
    expect(collect(section($wider, 'due')['rows'])->pluck('document'))->toContain('Insurance');

    Carbon::setTestNow();
});

// --- department usage -------------------------------------------------------------------

it('sums each department\'s trips, shared headcount and passengers', function () {
    $inspection = Department::create(['name' => 'Inspection', 'code' => 'INS']);
    $training = Department::create(['name' => 'Training', 'code' => 'TRN']);
    $own = analyticsTrip(['department_id' => $inspection->id, 'odometer_start' => 500, 'odometer_end' => 525]);
    analyticsTrip(['department_id' => $inspection->id, 'status' => 'cancelled']);
    $shared = analyticsTrip(['department_id' => $training->id]);
    DB::table('department_trip')->insert([
        ['trip_id' => $shared->id, 'department_id' => $inspection->id, 'count' => 3],
        ['trip_id' => $shared->id, 'department_id' => $training->id, 'count' => 2],
    ]);
    $own->passengers()->create(['user_id' => analyticsUser([], ['department_id' => $inspection->id])->id, 'status' => 'completed']);
    $own->passengers()->create(['user_id' => analyticsUser([], ['department_id' => $inspection->id])->id, 'status' => 'no_show']);

    $report = $this->actingAs(analyticsUser())->get('/reports/department-usage?from=2026-03-01&to=2026-03-31')
        ->viewData('page')['props']['report'];
    $rows = collect(section($report, 'departments')['rows'])->keyBy('department');

    expect($rows['Inspection'])->toMatchArray(['requested' => 2, 'completed' => 1, 'cancelled' => 1, 'seats' => 3, 'passengers' => 1, 'no_shows' => 1, 'km' => 25.0])
        ->and($rows['Training'])->toMatchArray(['requested' => 1, 'seats' => 2])
        ->and(summaryValue($report, 'Headcount seats'))->toBe(5);
});
