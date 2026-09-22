<?php

use App\Models\Trip;
use App\Models\TripPassenger;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

/**
 * Seed the permissions the trip-visibility scoping checks.
 * Spatie's caching means we must flush cache when creating permissions.
 */
function seedTripVisibilityPermissions(): void
{
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    Permission::firstOrCreate(['name' => 'view-trips', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'view-own-trips', 'guard_name' => 'web']);
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
}

function makeVisibilityUser(string $username): User
{
    return User::create([
        'email_verified_at' => now(),
        'name' => 'Visibility User ' . $username,
        'username' => $username,
        'email' => $username . '@example.com',
        'user_type' => 'employee',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);
}

function makeVisibilityTrip(User $requester, array $overrides = []): Trip
{
    return Trip::create(array_merge([
        'trip_number' => 'TRIP-VIS-' . uniqid(),
        'requested_by' => $requester->id,
        'priority' => 'medium',
        'scheduled_date' => '2026-05-03',
        'scheduled_start_time' => '08:00:00',
        'scheduled_end_time' => '09:00:00',
        'status' => 'pending',
    ], $overrides));
}

it('scopes the trip index to only trips the view-own-trips user requested, rides, or drives', function () {
    seedTripVisibilityPermissions();
    $viewer = makeVisibilityUser('own-trips-viewer');
    $viewer->givePermissionTo('view-own-trips');
    $stranger = makeVisibilityUser('stranger-requester');

    $ownRequestedTrip = makeVisibilityTrip($viewer);
    $passengerTrip = makeVisibilityTrip($stranger);
    TripPassenger::create(['trip_id' => $passengerTrip->id, 'user_id' => $viewer->id, 'status' => 'pending']);

    $vehicle = Vehicle::create([
        'brand' => 'Toyota', 'model' => 'Hiace',
        'registration_number' => 'VEH-' . uniqid(),
        'driver_id' => $viewer->id, 'is_active' => true,
    ]);
    $drivenTrip = makeVisibilityTrip($stranger, ['vehicle_id' => $vehicle->id]);

    $notMyTrip = makeVisibilityTrip($stranger);

    $response = $this->actingAs($viewer)->get('/trips');
    $response->assertOk();

    $visibleIds = null;
    $response->assertInertia(function ($page) use (&$visibleIds) {
        $visibleIds = collect($page->toArray()['props']['trips']['data'])->pluck('id')->all();
        return $page->component('trips/index');
    });

    expect($visibleIds)->toContain($ownRequestedTrip->id, $passengerTrip->id, $drivenTrip->id)
        ->and($visibleIds)->not->toContain($notMyTrip->id);
});

it('shows every trip to a user with full view-trips', function () {
    seedTripVisibilityPermissions();
    $manager = makeVisibilityUser('full-view-manager');
    $manager->givePermissionTo('view-trips');
    $requester = makeVisibilityUser('some-requester');

    $trip = makeVisibilityTrip($requester);

    $response = $this->actingAs($manager)->get('/trips');
    $response->assertOk();

    $visibleIds = null;
    $response->assertInertia(function ($page) use (&$visibleIds) {
        $visibleIds = collect($page->toArray()['props']['trips']['data'])->pluck('id')->all();
        return $page->component('trips/index');
    });
    expect($visibleIds)->toContain($trip->id);
});

it('lets a view-own-trips user open a trip they requested but not someone else\'s', function () {
    seedTripVisibilityPermissions();
    $viewer = makeVisibilityUser('own-trip-show-viewer');
    $viewer->givePermissionTo('view-own-trips');
    $stranger = makeVisibilityUser('other-requester');

    $ownTrip = makeVisibilityTrip($viewer);
    $othersTrip = makeVisibilityTrip($stranger);

    $this->actingAs($viewer)->get("/trips/{$ownTrip->id}")->assertOk();
    $this->actingAs($viewer)->get("/trips/{$othersTrip->id}")->assertForbidden();
});

it('lets a view-own-trips passenger and assigned driver open trips that are not theirs by request', function () {
    seedTripVisibilityPermissions();
    $passengerUser = makeVisibilityUser('passenger-show-viewer');
    $passengerUser->givePermissionTo('view-own-trips');
    $driverUser = makeVisibilityUser('driver-show-viewer');
    $driverUser->givePermissionTo('view-own-trips');
    $stranger = makeVisibilityUser('requester-show');

    $passengerTrip = makeVisibilityTrip($stranger);
    TripPassenger::create(['trip_id' => $passengerTrip->id, 'user_id' => $passengerUser->id, 'status' => 'pending']);

    $vehicle = Vehicle::create([
        'brand' => 'Toyota', 'model' => 'Hiace',
        'registration_number' => 'VEH-' . uniqid(),
        'driver_id' => $driverUser->id, 'is_active' => true,
    ]);
    $drivenTrip = makeVisibilityTrip($stranger, ['vehicle_id' => $vehicle->id]);

    $this->actingAs($passengerUser)->get("/trips/{$passengerTrip->id}")->assertOk();
    $this->actingAs($driverUser)->get("/trips/{$drivenTrip->id}")->assertOk();
});

it('denies the cross-trip passenger-events log to a view-own-trips-only user', function () {
    seedTripVisibilityPermissions();
    $viewer = makeVisibilityUser('passenger-events-viewer');
    $viewer->givePermissionTo('view-own-trips');

    $this->actingAs($viewer)->get('/trips/passenger-events')->assertForbidden();
});
