<?php

use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

/**
 * DriverController::show() eager-loads driverTrips with an explicit column
 * select for the "Recent Trips" table. No test ever exercised this route
 * with an actual trip attached to the driver, so a typo'd column name
 * ('purpose', which doesn't exist on trips — the real column is
 * 'description') went undetected until it 500'd in the browser.
 */
function seedDriverShowPermissions(): void
{
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    Permission::firstOrCreate(['name' => 'view-drivers', 'guard_name' => 'web']);
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
}

function makeDriverShowViewer(): User
{
    $user = User::create([
        'email_verified_at' => now(),
        'name' => 'Driver Show Viewer',
        'username' => 'driver_show_'.uniqid(),
        'email' => 'driver_show_'.uniqid().'@example.com',
        'user_type' => 'employee',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);

    $user->givePermissionTo('view-drivers');

    return $user;
}

it('shows a driver profile with recent trips without a query error', function () {
    seedDriverShowPermissions();
    $viewer = makeDriverShowViewer();

    $driver = User::create([
        'email_verified_at' => now(),
        'name' => 'Trip Driver',
        'username' => 'trip_driver_'.uniqid(),
        'email' => 'trip_driver_'.uniqid().'@example.com',
        'user_type' => 'driver',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);

    $trip = Trip::create([
        'trip_number' => 'TRIP-'.uniqid(),
        'driver_id' => $driver->id,
        'requested_by' => $viewer->id,
        'priority' => 'medium',
        'scheduled_date' => now()->toDateString(),
        'scheduled_start_time' => '08:00:00',
        'scheduled_end_time' => '09:00:00',
        'status' => 'completed',
        'trip_type' => 'adhoc',
        'description' => 'Airport run',
    ]);

    $response = $this->actingAs($viewer)->get("/drivers/{$driver->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('drivers/show')
        ->where('recentTrips.0.id', $trip->id)
        ->where('recentTrips.0.description', 'Airport run')
    );
});
