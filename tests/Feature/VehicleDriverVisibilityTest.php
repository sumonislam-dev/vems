<?php

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

/**
 * Seed the permissions the vehicle-show driver-detail scoping checks.
 * Spatie's caching means we must flush cache when creating permissions.
 */
function seedVehicleVisibilityPermissions(): void
{
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    Permission::firstOrCreate(['name' => 'view-vehicles', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'view-drivers', 'guard_name' => 'web']);
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
}

function makeVehicleVisibilityUser(string $username): User
{
    return User::create([
        'name' => 'Vehicle Visibility User ' . $username,
        'username' => $username,
        'email' => $username . '@example.com',
        'user_type' => 'employee',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);
}

it('hides the assigned driver\'s contact/identity details from a plain view-vehicles viewer', function () {
    seedVehicleVisibilityPermissions();
    $viewer = makeVehicleVisibilityUser('vehicle-viewer-basic');
    $viewer->givePermissionTo('view-vehicles');

    $driver = makeVehicleVisibilityUser('assigned-driver-profile');
    $driver->update([
        'driving_license_no' => 'DL-SECRET-001',
        'nid_number' => '1234567890123',
        'personal_phone' => '+8801711111111',
        'present_address' => 'Secret Address, Dhaka',
    ]);

    $vehicle = Vehicle::create([
        'brand' => 'Toyota', 'model' => 'Hiace',
        'registration_number' => 'VEH-' . uniqid(),
        'driver_id' => $driver->id, 'is_active' => true,
    ]);

    $response = $this->actingAs($viewer)->get("/vehicles/{$vehicle->id}");
    $response->assertOk();

    $response->assertInertia(function ($page) {
        $driverProp = $page->toArray()['props']['vehicle']['driver'];
        expect($driverProp)->toHaveKey('name')
            ->and($driverProp)->not->toHaveKey('driving_license_no')
            ->and($driverProp)->not->toHaveKey('nid_number')
            ->and($driverProp)->not->toHaveKey('personal_phone')
            ->and($driverProp)->not->toHaveKey('present_address');

        return $page->component('vehicles/show');
    });
});

it('shows the assigned driver\'s full profile to a viewer with view-drivers', function () {
    seedVehicleVisibilityPermissions();
    $manager = makeVehicleVisibilityUser('vehicle-viewer-manager');
    $manager->givePermissionTo(['view-vehicles', 'view-drivers']);

    $driver = makeVehicleVisibilityUser('assigned-driver-full');
    $driver->update(['driving_license_no' => 'DL-VISIBLE-001']);

    $vehicle = Vehicle::create([
        'brand' => 'Toyota', 'model' => 'Hiace',
        'registration_number' => 'VEH-' . uniqid(),
        'driver_id' => $driver->id, 'is_active' => true,
    ]);

    $response = $this->actingAs($manager)->get("/vehicles/{$vehicle->id}");
    $response->assertOk();

    $response->assertInertia(function ($page) {
        $driverProp = $page->toArray()['props']['vehicle']['driver'];
        expect($driverProp)->toHaveKey('driving_license_no')
            ->and($driverProp['driving_license_no'])->toBe('DL-VISIBLE-001');

        return $page->component('vehicles/show');
    });
});
