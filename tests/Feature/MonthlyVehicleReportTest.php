<?php

use App\Exports\MonthlyVehicleReportExport;
use App\Models\Factory;
use App\Models\Trip;
use App\Models\TripFeedback;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleRoute;
use App\Models\Vendor;
use App\Models\Zone;
use App\Services\Reports\MonthlyVehicleReport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * The Monthly Vehicle Report's counting rules: distinct vehicles per purpose
 * per day, staff pick-and-drop kept separate, trip counts alongside, ad-hoc
 * vs pool, previous-period comparison, and the vehicle-by-day details sheet.
 */
function seedMonthlyReportPermissions(): void
{
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    Permission::firstOrCreate(['name' => 'view-reports', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'export-reports', 'guard_name' => 'web']);
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
}

function monthlyReportUser(array $permissions = ['view-reports', 'export-reports'], array $attrs = []): User
{
    $user = User::create(array_merge([
        'email_verified_at' => now(),
        'name' => 'Report User',
        'username' => 'report_user_'.uniqid(),
        'email' => 'report_user_'.uniqid().'@example.com',
        'user_type' => 'employee',
        'status' => 'active',
        'password' => Hash::make('password'),
    ], $attrs));
    $user->givePermissionTo($permissions);

    return $user;
}

function monthlyReportTrip(Vehicle $vehicle, string $date, ?string $type, array $overrides = []): Trip
{
    return Trip::create(array_merge([
        'requested_by' => monthlyReportUser([])->id,
        'vehicle_id' => $vehicle->id,
        'trip_type' => $type,
        'priority' => 'medium',
        'scheduled_date' => $date,
        'scheduled_start_time' => '08:00:00',
        'scheduled_end_time' => '09:00:00',
        'status' => 'completed',
    ], $overrides));
}

/**
 * Jan 5–6 2026 (previous period Jan 3–4), Dhaka:
 *  - V1 (pool, route 3.1): Jan 5 pick-up on route "6" + inspection at a Gazipur factory + drop-off; Jan 6 inspection (still assigned)
 *  - V2 (pool): Jan 5 inspection (Savar) + complaints visit (Narayanganj, with an open complaint); Jan 3 inspection
 *  - A1 (ad-hoc): Jan 5 training
 *  - V3 (pool): Jan 5 inspection, cancelled — not usage
 */
function seedMonthlyReportScenario(): array
{
    $dhaka = Zone::create(['name' => 'Dhaka', 'code' => 'DHK']);
    $vendor = Vendor::create(['name' => 'Hello', 'status' => 'active']);
    $driver = User::create([
        'name' => 'Rafiq', 'username' => 'rafiq_'.uniqid(), 'email' => 'rafiq_'.uniqid().'@example.com',
        'user_type' => 'driver', 'status' => 'active', 'official_phone' => '01700000000', 'password' => Hash::make('password'),
    ]);
    $regular = VehicleRoute::create(['name' => '3.1', 'zone_id' => $dhaka->id]);
    $ran = VehicleRoute::create(['name' => '6', 'zone_id' => $dhaka->id]);

    $base = ['brand' => 'Toyota', 'model' => 'Hiace', 'vehicle_type' => 'van', 'is_active' => true, 'zone_id' => $dhaka->id, 'vendor_id' => $vendor->id];
    $v1 = Vehicle::create([...$base, 'registration_number' => 'V1', 'rental_type' => 'pool', 'driver_id' => $driver->id, 'default_route_id' => $regular->id]);
    $v2 = Vehicle::create([...$base, 'registration_number' => 'V2', 'rental_type' => 'pool']);
    $v3 = Vehicle::create([...$base, 'registration_number' => 'V3', 'rental_type' => 'pool']);
    $a1 = Vehicle::create([...$base, 'registration_number' => 'ADHOC-001', 'rental_type' => 'adhoc']);

    $gazipur = Factory::create(['account_id' => 'F-'.uniqid(), 'name' => 'Gazipur Knit', 'city' => 'Gazipur']);

    monthlyReportTrip($v1, '2026-01-05', 'pick-up', ['vehicle_route_id' => $ran->id]);
    monthlyReportTrip($v1, '2026-01-05', 'inspection')->factories()->attach($gazipur->id);
    monthlyReportTrip($v1, '2026-01-05', 'drop-off', ['vehicle_route_id' => $ran->id]);
    monthlyReportTrip($v1, '2026-01-06', 'inspection', ['status' => 'assigned', 'end_location' => 'Savar']);

    monthlyReportTrip($v2, '2026-01-05', 'inspection', ['end_location' => 'Savar']);
    $complaintTrip = monthlyReportTrip($v2, '2026-01-05', 'complaints', ['end_location' => 'Narayanganj']);
    monthlyReportTrip($v2, '2026-01-03', 'inspection');

    monthlyReportTrip($a1, '2026-01-05', 'training');
    monthlyReportTrip($v3, '2026-01-05', 'inspection', ['status' => 'cancelled']);

    TripFeedback::create([
        'trip_id' => $complaintTrip->id, 'submitted_by' => $driver->id, 'type' => 'complaint',
        'subject' => 'Late', 'description' => 'Vehicle was late', 'status' => 'open',
    ])->forceFill(['created_at' => '2026-01-05 12:00:00'])->save();

    return compact('dhaka', 'vendor', 'v1', 'v2', 'v3', 'a1');
}

function buildMonthlyReport(array $filters = [], ?User $viewer = null): array
{
    return (new MonthlyVehicleReport(Carbon::parse('2026-01-05'), Carbon::parse('2026-01-06'), $filters, $viewer))->build();
}

function dailyRow(array $report, string $key): array
{
    return collect($report['daily'])->firstWhere('key', $key)['values'];
}

it('counts distinct vehicles per purpose per day, with staff transport and trips shown separately', function () {
    seedMonthlyReportScenario();
    $report = buildMonthlyReport();

    expect(dailyRow($report, 'inspection'))->toBe([2, 1])        // V1+V2 on the 5th (V3 cancelled), V1 on the 6th
        ->and(dailyRow($report, 'complaints'))->toBe([1, 0])     // V2 counted under both purposes
        ->and(dailyRow($report, 'training'))->toBe([1, 0])
        ->and(dailyRow($report, 'staff_transport'))->toBe([1, 0]) // V1's pick-up + drop-off = 1 vehicle
        ->and(dailyRow($report, 'total_vehicles'))->toBe([3, 1]) // each vehicle once per day
        ->and(dailyRow($report, 'total_trips'))->toBe([6, 1])
        ->and(dailyRow($report, 'pool_vehicles'))->toBe([2, 1])
        ->and(dailyRow($report, 'adhoc_vehicles'))->toBe([1, 0]);

    $inspection = collect($report['purposes'])->firstWhere('key', 'inspection');
    expect($inspection['vehicle_days'])->toBe(3)->and($inspection['trips'])->toBe(3);
});

it('compares key numbers and service quality with the previous period', function () {
    seedMonthlyReportScenario();
    $report = buildMonthlyReport();
    $key = collect($report['key_numbers'])->keyBy('key');
    $service = collect($report['service'])->keyBy('key');

    expect($report['period']['previous_from'])->toBe('2026-01-03')
        ->and($key['trips']['current'])->toBe(7)
        ->and($key['trips']['previous'])->toBe(1)
        ->and($key['working_days']['current'])->toBe(2)
        ->and($key['adhoc_vehicle_days']['current'])->toBe(1)
        ->and($key['max_pool_in_day']['current'])->toBe(2)
        ->and($service['requests']['current'])->toBe(8)
        ->and($service['completed']['current'])->toBe(6)
        ->and($service['cancelled']['current'])->toBe(1)
        ->and($service['still_open']['current'])->toBe(1)
        ->and($service['fulfilment_rate']['current'])->toBe(85.7)
        ->and($service['complaints_open']['current'])->toBe(1)
        ->and($service['late_arrivals']['current'])->toBeNull();

    $hello = collect($report['vendors'])->firstWhere('vendor', 'Hello');
    expect($hello['trips'])->toBe(7)->and($hello['complaints'])->toBe(1)->and($hello['complaints_open'])->toBe(1);
});

it('builds the vehicle-by-day details with trip counts, groups, route remarks and idle counts', function () {
    seedMonthlyReportScenario();
    $details = buildMonthlyReport()['details'];
    $rows = collect($details['groups'])->flatMap(fn ($g) => collect($g['rows'])->map(fn ($r) => [...$r, 'group' => $g['key']]))->keyBy('registration_number');

    expect($rows['V1']['group'])->toBe('inspection')
        ->and($rows['V1']['cells'][0])->toBe(['text' => 'Gazipur', 'trips' => 3])
        ->and($rows['V1']['total_trips'])->toBe(4)
        ->and($rows['V1']['remarks'])->toBe('6')          // the route it actually ran beats its regular "3.1"
        ->and($rows['V1']['phone'])->toBe('01700000000')
        ->and($rows['V2']['cells'][0]['text'])->toBe('Savar, Complaints - Narayanganj')
        ->and($rows['V2']['remarks'])->toBeNull()
        ->and($rows['ADHOC-001']['group'])->toBe('adhoc')
        ->and($rows['V3']['group'])->toBe('unused')
        ->and($rows['V3']['cells'])->toBe([null, null])
        ->and($details['fleet_size'])->toBe(3)
        ->and($details['assigned_per_day'])->toBe([2, 1])
        ->and($details['idle_per_day'])->toBe([1, 2]);
});

it('applies the rental type and vendor filters', function () {
    $s = seedMonthlyReportScenario();

    expect(dailyRow(buildMonthlyReport(['rental_type' => 'adhoc']), 'total_vehicles'))->toBe([1, 0])
        ->and(dailyRow(buildMonthlyReport(['vendor_id' => $s['vendor']->id + 999]), 'total_vehicles'))->toBe([0, 0]);
});

it('only counts trips in the viewer\'s zones', function () {
    seedMonthlyReportPermissions();
    $s = seedMonthlyReportScenario();
    $ctg = Zone::create(['name' => 'Chattogram', 'code' => 'CTG']);
    $ctgVehicle = Vehicle::create(['brand' => 'Toyota', 'model' => 'Noah', 'registration_number' => 'CTG-1', 'rental_type' => 'pool', 'zone_id' => $ctg->id, 'is_active' => true]);
    monthlyReportTrip($ctgVehicle, '2026-01-05', 'inspection');

    expect(dailyRow(buildMonthlyReport(), 'inspection'))->toBe([3, 1]);

    $dhakaUser = monthlyReportUser(attrs: ['zone_id' => $s['dhaka']->id]);
    $this->actingAs($dhakaUser);
    expect(dailyRow(buildMonthlyReport(viewer: $dhakaUser), 'inspection'))->toBe([2, 1]);
});

it('renders the report page for users with view-reports, defaulting to the current month', function () {
    seedMonthlyReportPermissions();

    $this->actingAs(monthlyReportUser())->get('/reports/monthly-vehicle')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/monthly-vehicle')
            ->where('filters.from', now()->startOfMonth()->toDateString())
            ->where('filters.to', now()->endOfMonth()->toDateString())
            ->has('report.days', now()->daysInMonth));
});

it('forbids the report without view-reports', function () {
    seedMonthlyReportPermissions();

    $this->actingAs(monthlyReportUser([]))->get('/reports/monthly-vehicle')->assertForbidden();
});

it('rejects a period longer than 31 days', function () {
    seedMonthlyReportPermissions();

    $this->actingAs(monthlyReportUser())->get('/reports/monthly-vehicle?from=2026-01-01&to=2026-02-15')
        ->assertSessionHasErrors('to');
});

it('downloads the report as a two-sheet Excel workbook and as a PDF', function () {
    seedMonthlyReportPermissions();
    seedMonthlyReportScenario();
    $user = monthlyReportUser();
    Excel::fake();

    $this->actingAs($user)->get('/reports/monthly-vehicle/export?format=excel&from=2026-01-05&to=2026-01-06')->assertOk();
    Excel::assertDownloaded('monthly-vehicle-report-20260105-20260106.xlsx', function (MonthlyVehicleReportExport $export) {
        $sheets = $export->sheets();

        return count($sheets) === 2
            && $sheets[0]->title() === 'Summary'
            && $sheets[1]->title() === 'Details'
            && collect($sheets[1]->array())->contains(fn ($row) => in_array('Gazipur (3)', $row, true));
    });

    $this->actingAs($user)->get('/reports/monthly-vehicle/export?format=pdf&from=2026-01-05&to=2026-01-06')
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('requires export-reports to download', function () {
    seedMonthlyReportPermissions();

    $this->actingAs(monthlyReportUser(['view-reports']))->get('/reports/monthly-vehicle/export?format=excel')->assertForbidden();
});

it('counts complaints as open at period end by when they were raised, resolved and closed — not their status today', function () {
    $s = seedMonthlyReportScenario();
    $trip = Trip::where('vehicle_id', $s['v2']->id)->where('trip_type', 'complaints')->firstOrFail();
    $complaint = fn (array $attrs) => TripFeedback::create([
        'trip_id' => $trip->id, 'submitted_by' => $trip->requested_by, 'type' => 'complaint',
        'subject' => 'x', 'description' => 'x', 'status' => $attrs['status'],
    ])->forceFill($attrs)->save();

    // Resolved after the period ended: still open at period end (Jan 6)
    $complaint(['status' => 'resolved', 'created_at' => '2026-01-05 10:00', 'resolved_at' => '2026-01-10 10:00']);
    // Resolved within the period: not open
    $complaint(['status' => 'resolved', 'created_at' => '2026-01-05 10:00', 'resolved_at' => '2026-01-06 17:00']);
    // Closed within the period without being resolved: not open
    $complaint(['status' => 'closed', 'created_at' => '2026-01-05 10:00', 'closed_at' => '2026-01-06 09:00']);
    // Raised after the period ended: not open at period end
    $complaint(['status' => 'open', 'created_at' => '2026-01-08 10:00']);

    $service = collect(buildMonthlyReport()['service'])->keyBy('key');

    // + the scenario's own complaint (raised Jan 5, still open)
    expect($service['complaints']['current'])->toBe(5)
        ->and($service['complaints_open']['current'])->toBe(2);
});
