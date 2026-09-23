<?php

use App\Models\Trip;
use App\Models\TripFeedback;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

function seedDashboardPermissions(): void
{
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    foreach (['view-trips', 'view-own-trips', 'create-trips', 'view-complaints', 'view-own-complaints', 'create-complaints'] as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
}

function makeDashboardVehicleWithDriver(User $driver): Vehicle
{
    return Vehicle::create([
        'brand' => 'Toyota',
        'model' => 'Hiace',
        'registration_number' => 'VEH-DASH-'.uniqid(),
        'driver_id' => $driver->id,
        'is_active' => true,
    ]);
}

function makeDashboardUser(string $username): User
{
    return User::create([
        'email_verified_at' => now(),
        'name' => 'Dashboard User '.$username,
        'username' => $username,
        'email' => $username.'@example.com',
        'user_type' => 'employee',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);
}

function makeDashboardTrip(User $requester, string $status = 'pending'): Trip
{
    return Trip::create([
        'trip_number' => 'TRIP-DASH-'.uniqid(),
        'requested_by' => $requester->id,
        'priority' => 'medium',
        'scheduled_date' => now()->addDay()->toDateString(),
        'scheduled_start_time' => '08:00:00',
        'scheduled_end_time' => '09:00:00',
        'status' => $status,
    ]);
}

test('guests are redirected to the login page', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

test('authenticated users can visit the dashboard', function () {
    $this->actingAs($user = User::factory()->create());

    $this->get('/dashboard')->assertOk();
});

test('a user without view-trips gets the employee dashboard scoped to their own trips and complaints', function () {
    seedDashboardPermissions();
    $employee = makeDashboardUser('emp-1');
    $employee->givePermissionTo(['view-own-trips', 'create-trips', 'view-own-complaints', 'create-complaints']);
    $other = makeDashboardUser('other-1');
    $other->givePermissionTo(['view-own-trips', 'create-trips']);

    $myTrip = makeDashboardTrip($employee);
    makeDashboardTrip($other);

    $myComplaint = TripFeedback::create([
        'trip_id' => $myTrip->id,
        'submitted_by' => $employee->id,
        'type' => 'complaint',
        'category' => 'safety',
        'subject' => 'Mine',
        'description' => 'Description.',
        'priority' => 'medium',
        'status' => 'open',
    ]);
    TripFeedback::create([
        'trip_id' => $myTrip->id,
        'submitted_by' => $other->id,
        'type' => 'complaint',
        'category' => 'safety',
        'subject' => 'Not mine',
        'description' => 'Description.',
        'priority' => 'medium',
        'status' => 'open',
    ]);

    $this->actingAs($employee)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('variant', 'employee')
            ->where('myTrips.counts.pending', 1)
            ->where('myTrips.upcoming.0.id', $myTrip->id)
            ->where('myComplaints.counts.total', 1)
            ->where('myComplaints.recent.0.id', $myComplaint->id)
        );
});

test('a user with view-trips gets the management dashboard', function () {
    seedDashboardPermissions();
    $admin = makeDashboardUser('admin-1');
    $admin->givePermissionTo(['view-trips', 'view-complaints']);

    $this->actingAs($admin)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('variant', 'management')
            ->has('moduleStats')
        );
});

test('a driver gets the driver dashboard scoped to trips assigned via their vehicle', function () {
    seedDashboardPermissions();

    Role::firstOrCreate(['name' => 'driver', 'guard_name' => 'web']);

    $driver = makeDashboardUser('driver-1');
    $driver->assignRole('driver');
    $driver->givePermissionTo(['view-own-trips', 'view-own-complaints', 'create-complaints']);
    $vehicle = makeDashboardVehicleWithDriver($driver);

    $otherDriver = makeDashboardUser('driver-2');
    $otherVehicle = makeDashboardVehicleWithDriver($otherDriver);

    $requester = makeDashboardUser('requester-1');

    $myTodayTrip = Trip::create([
        'trip_number' => 'TRIP-DASH-'.uniqid(),
        'vehicle_id' => $vehicle->id,
        'requested_by' => $requester->id,
        'priority' => 'medium',
        'scheduled_date' => now()->toDateString(),
        'scheduled_start_time' => '08:00:00',
        'scheduled_end_time' => '09:00:00',
        'status' => 'assigned',
    ]);

    Trip::create([
        'trip_number' => 'TRIP-DASH-'.uniqid(),
        'vehicle_id' => $otherVehicle->id,
        'requested_by' => $requester->id,
        'priority' => 'medium',
        'scheduled_date' => now()->toDateString(),
        'scheduled_start_time' => '08:00:00',
        'scheduled_end_time' => '09:00:00',
        'status' => 'assigned',
    ]);

    $this->actingAs($driver)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('variant', 'driver')
            ->where('myTrips.counts.today', 1)
            ->where('myTrips.today_trips.0.id', $myTodayTrip->id)
            ->has('license')
            ->has('driverStatus')
        );
});
