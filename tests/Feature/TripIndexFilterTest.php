<?php

use App\Models\Trip;
use App\Models\TripFeedback;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * The trips index table (base-data-table.tsx) sends its "Filters" dropdown
 * selections as filters[key][index]=value (see useServerSideTable), not as
 * flat query params. TripController::index() previously only read flat
 * status/schedule_type params (which the frontend never sent) and never
 * supported priority/trip_type at all, so every filter silently did nothing.
 */
function seedTripIndexFilterPermissions(): void
{
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    Permission::firstOrCreate(['name' => 'view-trips', 'guard_name' => 'web']);
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
}

function makeTripIndexFilterUser(): User
{
    $username = 'trip-filter-'.uniqid();

    return User::create([
        'email_verified_at' => now(),
        'name' => 'Trip Filter User',
        'username' => $username,
        'email' => $username.'@example.com',
        'user_type' => 'employee',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);
}

function makeTripIndexFilterTrip(User $requester, array $overrides = []): Trip
{
    return Trip::create(array_merge([
        'trip_number' => 'TRIP-FLT-'.uniqid(),
        'requested_by' => $requester->id,
        'priority' => 'medium',
        'scheduled_date' => '2026-05-03',
        'scheduled_start_time' => '08:00:00',
        'scheduled_end_time' => '09:00:00',
        'status' => 'pending',
    ], $overrides));
}

it('filters the trip index by status using the filters[] array format the table sends', function () {
    seedTripIndexFilterPermissions();
    $user = makeTripIndexFilterUser();
    $user->givePermissionTo('view-trips');

    $pending = makeTripIndexFilterTrip($user, ['status' => 'pending']);
    $approved = makeTripIndexFilterTrip($user, ['status' => 'approved']);

    $response = $this->actingAs($user)->get('/trips?'.http_build_query([
        'filters' => ['status' => ['approved']],
    ]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('trips.data', fn ($trips) => collect($trips)->pluck('id')->contains($approved->id)
            && ! collect($trips)->pluck('id')->contains($pending->id)
        )
    );
});

it('filters the trip index by priority using the filters[] array format the table sends', function () {
    seedTripIndexFilterPermissions();
    $user = makeTripIndexFilterUser();
    $user->givePermissionTo('view-trips');

    $urgent = makeTripIndexFilterTrip($user, ['priority' => 'urgent']);
    $low = makeTripIndexFilterTrip($user, ['priority' => 'low']);

    $response = $this->actingAs($user)->get('/trips?'.http_build_query([
        'filters' => ['priority' => ['urgent']],
    ]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('trips.data', fn ($trips) => collect($trips)->pluck('id')->contains($urgent->id)
            && ! collect($trips)->pluck('id')->contains($low->id)
        )
    );
});

/**
 * Every value the trip_type enum column (create_trips_table migration) and the
 * "Trip Type" filter dropdown (trips/index.tsx) can produce, checked one at a
 * time so a future enum/dropdown drift is caught immediately.
 */
it('filters the trip index by every trip_type value', function (string $tripType) {
    seedTripIndexFilterPermissions();
    $user = makeTripIndexFilterUser();
    $user->givePermissionTo('view-trips');

    $matching = makeTripIndexFilterTrip($user, ['trip_type' => $tripType]);
    $others = collect([
        'inspection', 'pick-up', 'drop-off', 'training', 'complaints',
        'CVV', 'Incident Inspection', 'officials', 'Assigned',
    ])
        ->reject(fn ($type) => $type === $tripType)
        ->map(fn ($type) => makeTripIndexFilterTrip($user, ['trip_type' => $type]));

    $response = $this->actingAs($user)->get('/trips?'.http_build_query([
        'filters' => ['trip_type' => [$tripType]],
    ]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('trips.data', function ($trips) use ($matching, $others) {
            $ids = collect($trips)->pluck('id');

            return $ids->contains($matching->id)
                && $ids->intersect($others->pluck('id'))->isEmpty();
        })
    );
})->with([
    'inspection',
    'pick-up',
    'drop-off',
    'training',
    'complaints',
    'CVV',
    'Incident Inspection',
    'officials',
    'Assigned',
]);

/**
 * schedule_type's option list (add_trip_type_values_to_schedule_type_on_trips_table
 * migration) was deliberately widened to parity with trip_type's, so every one of
 * these 13 values is checked individually here too.
 */
it('filters the trip index by every schedule_type value', function (string $scheduleType) {
    seedTripIndexFilterPermissions();
    $user = makeTripIndexFilterUser();
    $user->givePermissionTo('view-trips');

    $allScheduleTypes = [
        'pick-and-drop', 'pick-up', 'drop-off', 'engineer', 'training', 'adhoc', 'reposition',
        'inspection', 'complaints', 'CVV', 'Incident Inspection', 'officials', 'Assigned',
    ];

    $matching = makeTripIndexFilterTrip($user, ['schedule_type' => $scheduleType]);
    $others = collect($allScheduleTypes)
        ->reject(fn ($type) => $type === $scheduleType)
        ->map(fn ($type) => makeTripIndexFilterTrip($user, ['schedule_type' => $type]));

    $response = $this->actingAs($user)->get('/trips?'.http_build_query([
        'filters' => ['schedule_type' => [$scheduleType]],
    ]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('trips.data', function ($trips) use ($matching, $others) {
            $ids = collect($trips)->pluck('id');

            return $ids->contains($matching->id)
                && $ids->intersect($others->pluck('id'))->isEmpty();
        })
    );
})->with([
    'pick-and-drop',
    'pick-up',
    'drop-off',
    'engineer',
    'training',
    'adhoc',
    'reposition',
    'inspection',
    'complaints',
    'CVV',
    'Incident Inspection',
    'officials',
    'Assigned',
]);

it('keeps pick-up and drop-off as distinct schedule_type values from each other and from pick-and-drop', function () {
    seedTripIndexFilterPermissions();
    $user = makeTripIndexFilterUser();
    $user->givePermissionTo('view-trips');

    $pickUp = makeTripIndexFilterTrip($user, ['schedule_type' => 'pick-up']);
    $dropOff = makeTripIndexFilterTrip($user, ['schedule_type' => 'drop-off']);
    $pickAndDrop = makeTripIndexFilterTrip($user, ['schedule_type' => 'pick-and-drop']);

    $response = $this->actingAs($user)->get('/trips?'.http_build_query([
        'filters' => ['schedule_type' => ['pick-up']],
    ]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('trips.data', function ($trips) use ($pickUp, $dropOff, $pickAndDrop) {
            $ids = collect($trips)->pluck('id');

            return $ids->contains($pickUp->id)
                && ! $ids->contains($dropOff->id)
                && ! $ids->contains($pickAndDrop->id);
        })
    );
});

// The trips list and trip page read the driver from the vehicle (trips.driver_id
// is never filled) and the feedback from feedback_entries.
it('sends the vehicle driver and the feedback entries the trip page shows', function () {
    seedTripIndexFilterPermissions();
    $user = makeTripIndexFilterUser();
    $user->givePermissionTo('view-trips');
    $driver = makeTripIndexFilterUser();
    $driver->update(['user_type' => 'driver', 'name' => 'Karim Driver']);
    $vehicle = Vehicle::create(['brand' => 'Toyota', 'model' => 'Hiace', 'registration_number' => 'DHK-1234', 'driver_id' => $driver->id]);
    $trip = makeTripIndexFilterTrip($user, ['status' => 'completed', 'vehicle_id' => $vehicle->id]);
    TripFeedback::create([
        'trip_id' => $trip->id, 'submitted_by' => $user->id, 'type' => 'feedback', 'category' => 'other',
        'subject' => 'Smooth ride', 'description' => 'All good.', 'priority' => 'low', 'status' => 'open',
    ]);

    $this->actingAs($user)->get("/trips/{$trip->id}")->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('trip.vehicle.driver.name', 'Karim Driver')
            ->where('trip.feedback_entries.0.subject', 'Smooth ride'));

    $this->actingAs($user)->get('/trips')->assertOk()
        ->assertInertia(fn ($page) => $page->where('trips.data.0.vehicle.driver.name', 'Karim Driver'));
});

it('searches trips by description and location', function () {
    seedTripIndexFilterPermissions();
    $user = makeTripIndexFilterUser();
    $user->givePermissionTo('view-trips');
    $byDescription = makeTripIndexFilterTrip($user, ['description' => 'Audit visit to Gazipur']);
    $byLocation = makeTripIndexFilterTrip($user, ['end_location' => 'Savar EPZ']);
    makeTripIndexFilterTrip($user);

    $ids = fn (string $search) => collect($this->actingAs($user)->get(route('trips.index', ['search' => $search]))->assertOk()
        ->viewData('page')['props']['trips']['data'])->pluck('id')->all();

    expect($ids('Gazipur'))->toBe([$byDescription->id])
        ->and($ids('Savar'))->toBe([$byLocation->id]);
});
