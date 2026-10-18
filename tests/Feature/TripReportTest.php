<?php

use App\Exports\TripsReportExport;
use App\Models\Department;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleRoute;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * The bespoke Trip Report (ReportController): filters, summary totals,
 * charts, the trip history table, and its CSV / Excel / PDF exports.
 */
beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    Permission::firstOrCreate(['name' => 'view-reports', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'export-reports', 'guard_name' => 'web']);
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
});

afterEach(function () {
    Carbon::setTestNow();
});

function tripReportUser(array $permissions = ['view-reports', 'export-reports'], array $attributes = []): User
{
    $user = User::create(array_merge([
        'email_verified_at' => now(),
        'name' => 'Trip Report '.uniqid(),
        'username' => 'trip_report_'.uniqid(),
        'email' => 'trip_report_'.uniqid().'@example.com',
        'user_type' => 'employee',
        'status' => 'active',
        'password' => Hash::make('password'),
    ], $attributes));
    $user->givePermissionTo($permissions);

    return $user;
}

function tripReportVehicle(string $registration, array $attributes = []): Vehicle
{
    return Vehicle::create(array_merge([
        'brand' => 'Toyota',
        'model' => 'Hiace',
        'registration_number' => $registration,
        'is_active' => true,
    ], $attributes));
}

function tripReportTrip(string $date, array $attributes = []): Trip
{
    return Trip::create(array_merge([
        'requested_by' => tripReportUser([])->id,
        'priority' => 'medium',
        'scheduled_date' => $date,
        'scheduled_start_time' => '08:00:00',
        'scheduled_end_time' => '09:00:00',
        'status' => 'completed',
        'schedule_type' => 'adhoc',
    ], $attributes));
}

function tripReportProps(User $user, string $query = ''): array
{
    return test()->actingAs($user)
        ->get('/reports'.($query !== '' ? '?'.$query : ''))
        ->assertOk()
        ->viewData('page')['props']['report'];
}

function tripReportNumbers(array $report): array
{
    return collect($report['trip_histories']['data'])->pluck('trip_number')->sort()->values()->all();
}

it('renders the trip report page, defaulting to the last 30 days ending today (Dhaka)', function () {
    // 20:00 UTC on Mar 14 is already Mar 15 in Dhaka
    Carbon::setTestNow(Carbon::parse('2026-03-14 20:00:00', 'UTC'));
    $inside = tripReportTrip('2026-02-14', ['trip_number' => 'TR-IN-FIRST']);
    tripReportTrip('2026-03-15', ['trip_number' => 'TR-IN-TODAY']);
    tripReportTrip('2026-02-13', ['trip_number' => 'TR-OUT-OLD']);
    tripReportTrip('2026-03-16', ['trip_number' => 'TR-OUT-FUTURE']);

    $this->actingAs(tripReportUser(['view-reports']))
        ->get('/reports')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/index')
            ->where('report.filters.from', '2026-02-14')
            ->where('report.filters.to', '2026-03-15')
            ->where('report.filters.per_page', 15)
            ->where('report.summary.total_trips', 2)
            ->has('report.trip_histories.data', 2)
            ->has('report.filter_options.statuses', 7)
            ->has('queryParams'));

    expect($inside->exists)->toBeTrue();
});

it('narrows rows to the chosen date range, inclusive of both ends', function () {
    tripReportTrip('2026-03-01', ['trip_number' => 'TR-A']);
    tripReportTrip('2026-03-05', ['trip_number' => 'TR-B']);
    tripReportTrip('2026-03-10', ['trip_number' => 'TR-C']);
    tripReportTrip('2026-03-11', ['trip_number' => 'TR-D']);

    $report = tripReportProps(tripReportUser(), 'from=2026-03-05&to=2026-03-10');

    expect(tripReportNumbers($report))->toBe(['TR-B', 'TR-C'])
        ->and($report['summary']['total_trips'])->toBe(2)
        ->and(collect($report['charts']['daily_trips'])->pluck('date')->all())->toBe(['2026-03-05', '2026-03-10']);
});

it('rejects an end date before the start date', function () {
    $this->actingAs(tripReportUser())
        ->get('/reports?from=2026-03-10&to=2026-03-01')
        ->assertSessionHasErrors('to');
});

it('filters by status and schedule type', function () {
    tripReportTrip('2026-03-05', ['trip_number' => 'TR-DONE', 'status' => 'completed', 'schedule_type' => 'training']);
    tripReportTrip('2026-03-05', ['trip_number' => 'TR-CANC', 'status' => 'cancelled', 'schedule_type' => 'training']);
    tripReportTrip('2026-03-05', ['trip_number' => 'TR-PEND', 'status' => 'pending', 'schedule_type' => 'adhoc']);
    $user = tripReportUser();

    expect(tripReportNumbers(tripReportProps($user, 'from=2026-03-01&to=2026-03-31&status=cancelled')))->toBe(['TR-CANC'])
        ->and(tripReportNumbers(tripReportProps($user, 'from=2026-03-01&to=2026-03-31&schedule_type=training')))->toBe(['TR-CANC', 'TR-DONE'])
        ->and(tripReportNumbers(tripReportProps($user, 'from=2026-03-01&to=2026-03-31&schedule_type=training&status=completed')))->toBe(['TR-DONE']);

    $this->actingAs($user)->get('/reports?status=bogus')->assertSessionHasErrors('status');
});

it('filters by vehicle and by route', function () {
    $v1 = tripReportVehicle('DHK-1111');
    $v2 = tripReportVehicle('DHK-2222');
    $route = VehicleRoute::create(['name' => 'Route 3.1']);

    tripReportTrip('2026-03-05', ['trip_number' => 'TR-V1', 'vehicle_id' => $v1->id, 'vehicle_route_id' => $route->id]);
    tripReportTrip('2026-03-05', ['trip_number' => 'TR-V2', 'vehicle_id' => $v2->id]);
    tripReportTrip('2026-03-05', ['trip_number' => 'TR-NONE']);
    $user = tripReportUser();

    $byVehicle = tripReportProps($user, "from=2026-03-01&to=2026-03-31&vehicle_id={$v2->id}");
    expect(tripReportNumbers($byVehicle))->toBe(['TR-V2'])
        ->and($byVehicle['trip_histories']['data'][0]['vehicle']['registration_number'])->toBe('DHK-2222');

    $byRoute = tripReportProps($user, "from=2026-03-01&to=2026-03-31&route_id={$route->id}");
    expect(tripReportNumbers($byRoute))->toBe(['TR-V1'])
        ->and($byRoute['trip_histories']['data'][0]['vehicle_route']['name'])->toBe('Route 3.1');

    $this->actingAs($user)->get('/reports?vehicle_id=999999')->assertSessionHasErrors('vehicle_id');
});

// Any `search` currently 500s: the top-routes query joins vehicle_routes, which
// also has a `description` column, so the unqualified search clause is ambiguous
// (ReportController.php:94 with the join at :153).
it('searches trip number, locations and vehicle registration')->todo();

it('totals trips, completion/cancellation rates and passengers for the filtered trips only', function () {
    $department = Department::create(['name' => 'Inspection', 'code' => 'INSP']);
    $route = VehicleRoute::create(['name' => 'Route 6']);

    $done = tripReportTrip('2026-03-05', ['status' => 'completed', 'schedule_type' => 'training', 'vehicle_route_id' => $route->id, 'department_id' => $department->id]);
    tripReportTrip('2026-03-05', ['status' => 'completed', 'schedule_type' => 'adhoc', 'vehicle_route_id' => $route->id]);
    tripReportTrip('2026-03-06', ['status' => 'cancelled', 'schedule_type' => 'adhoc']);
    tripReportTrip('2026-03-06', ['status' => 'pending', 'schedule_type' => 'adhoc']);
    // Outside the range: must not count, nor its passengers
    $outside = tripReportTrip('2026-04-02', ['status' => 'completed']);

    $done->passengers()->create(['user_id' => tripReportUser([])->id, 'status' => 'completed']);
    $done->passengers()->create(['user_id' => tripReportUser([])->id, 'status' => 'no_show']);
    $done->passengers()->create(['user_id' => tripReportUser([])->id, 'status' => 'pending']);
    $outside->passengers()->create(['user_id' => tripReportUser([])->id, 'status' => 'completed']);

    $report = tripReportProps(tripReportUser(), 'from=2026-03-01&to=2026-03-31');

    expect($report['summary'])->toMatchArray([
        'total_trips' => 4,
        'completed_trips' => 2,
        'cancelled_trips' => 1,
        'completion_rate' => 50,
        'cancellation_rate' => 25,
        'total_passengers' => 3,
        'completed_passengers' => 1,
        'no_show_passengers' => 1,
    ]);

    expect(collect($report['charts']['trips_by_status'])->pluck('value', 'status')->sortKeys()->all())
        ->toBe(['cancelled' => 1, 'completed' => 2, 'pending' => 1])
        ->and(collect($report['charts']['trips_by_schedule_type'])->pluck('value', 'schedule_type')->all())
        ->toBe(['adhoc' => 3, 'training' => 1])
        ->and($report['charts']['daily_trips'])->toBe([
            ['date' => '2026-03-05', 'total' => 2, 'completed' => 2],
            ['date' => '2026-03-06', 'total' => 2, 'completed' => 0],
        ])
        ->and($report['top_routes'])->toBe([['route_name' => 'Route 6', 'trip_count' => 2]]);

    $row = collect($report['trip_histories']['data'])->firstWhere('id', $done->id);
    expect($row['department']['name'])->toBe('Inspection');
});

it('reports zero rates rather than dividing by zero when nothing matches', function () {
    $report = tripReportProps(tripReportUser(), 'from=2026-03-01&to=2026-03-31');

    expect($report['summary'])->toMatchArray([
        'total_trips' => 0,
        'completion_rate' => 0,
        'cancellation_rate' => 0,
        'total_passengers' => 0,
    ])->and($report['trip_histories']['data'])->toBe([]);
});

it('paginates the trip history, newest first', function () {
    foreach (range(1, 12) as $day) {
        tripReportTrip(sprintf('2026-03-%02d', $day), ['trip_number' => sprintf('TR-P%02d', $day)]);
    }

    $report = tripReportProps(tripReportUser(), 'from=2026-03-01&to=2026-03-31&per_page=10');

    expect($report['trip_histories']['total'])->toBe(12)
        ->and($report['trip_histories']['data'])->toHaveCount(10)
        ->and($report['trip_histories']['data'][0]['trip_number'])->toBe('TR-P12')
        ->and($report['summary']['total_trips'])->toBe(12);

    $this->actingAs(tripReportUser())->get('/reports?per_page=500')->assertSessionHasErrors('per_page');
});

it('downloads the filtered trips as Excel', function () {
    Carbon::setTestNow(Carbon::parse('2026-03-20 10:00:00'));
    Excel::fake();
    $vehicle = tripReportVehicle('DHK-5555');
    tripReportTrip('2026-03-05', ['trip_number' => 'TR-X1', 'status' => 'completed', 'vehicle_id' => $vehicle->id]);
    tripReportTrip('2026-03-06', ['trip_number' => 'TR-X2', 'status' => 'cancelled']);
    tripReportTrip('2026-02-01', ['trip_number' => 'TR-X-OLD', 'status' => 'completed']);

    $this->actingAs(tripReportUser())
        ->get('/reports/export?format=excel&from=2026-03-01&to=2026-03-31&status=completed')
        ->assertOk();

    Excel::assertDownloaded('trip-report-20260320_100000.xlsx', function (TripsReportExport $export) {
        $trips = $export->query()->get();
        $row = $export->map($trips->first());

        return $trips->pluck('trip_number')->all() === ['TR-X1']
            && $export->headings()[0] === 'Trip Number'
            && $row[0] === 'TR-X1'
            && $row[5] === 'DHK-5555';
    });
});

it('downloads the filtered trips as CSV by default and as a PDF', function () {
    tripReportTrip('2026-03-05', ['trip_number' => 'TR-CSV-1']);
    tripReportTrip('2026-03-06', ['trip_number' => 'TR-CSV-2', 'status' => 'cancelled']);
    $user = tripReportUser();

    $csv = $this->actingAs($user)
        ->get('/reports/export?from=2026-03-01&to=2026-03-31&status=completed')
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=utf-8')
        ->streamedContent();

    expect($csv)->toContain('Trip Number')
        ->toContain('TR-CSV-1')
        ->not->toContain('TR-CSV-2');

    $this->actingAs($user)
        ->get('/reports/export?format=pdf&from=2026-03-01&to=2026-03-31')
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    $this->actingAs($user)->get('/reports/export?format=docx')->assertSessionHasErrors('format');
});

it('requires view-reports to see the report and export-reports to download it', function () {
    $this->actingAs(tripReportUser([]))->get('/reports')->assertForbidden();
    $this->actingAs(tripReportUser(['export-reports']))->get('/reports')->assertForbidden();

    $viewer = tripReportUser(['view-reports']);
    $this->actingAs($viewer)->get('/reports')->assertOk();
    $this->actingAs($viewer)->get('/reports/export?format=excel')->assertForbidden();
    $this->actingAs($viewer)->get('/reports/export?format=pdf')->assertForbidden();
});

it('redirects guests to the login page', function () {
    $this->get('/reports')->assertRedirect(route('login'));
    $this->get('/reports/export')->assertRedirect(route('login'));
});

// ReportController never applies Zone::accessIds(), unlike the Monthly Vehicle
// and analytics reports: a viewer limited to one zone sees every zone's trips.
it('limits rows to the zones the viewer can access')->todo();
