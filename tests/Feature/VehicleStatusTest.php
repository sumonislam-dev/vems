<?php

use App\Models\User;
use App\Models\Vehicle;
use App\Models\Vendor;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

/**
 * Vehicle::$status (available/assigned/in_transit/maintenance/out_of_service)
 * existed in the schema and model but was never in store()/update()'s
 * validation rules, so it could never actually be set to anything but its
 * DB default. This covers the fix: create/update can now set it, and the
 * index filter (also newly wired up) narrows by it correctly.
 */
function seedVehicleStatusPermissions(): void
{
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    Permission::firstOrCreate(['name' => 'view-vehicles', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'create-vehicles', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'edit-vehicles', 'guard_name' => 'web']);
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
}

function makeVehicleStatusUser(): User
{
    $user = User::create([
        'email_verified_at' => now(),
        'name' => 'Vehicle Status User',
        'username' => 'vehicle_status_'.uniqid(),
        'email' => 'vehicle_status_'.uniqid().'@example.com',
        'user_type' => 'employee',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);

    $user->givePermissionTo(['view-vehicles', 'create-vehicles', 'edit-vehicles']);

    return $user;
}

it('sets the operational status when creating a vehicle', function () {
    seedVehicleStatusPermissions();
    $user = makeVehicleStatusUser();
    $vendor = Vendor::create(['name' => 'V1', 'status' => 'active']);
    $driver = User::create([
        'email_verified_at' => now(),
        'name' => 'Driver 1',
        'username' => 'driver1_'.uniqid(),
        'email' => 'driver1_'.uniqid().'@example.com',
        'user_type' => 'driver',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);

    $response = $this->actingAs($user)->post('/vehicles', [
        'brand' => 'Toyota',
        'model' => 'Camry',
        'registration_number' => 'STA-'.uniqid(),
        'vehicle_type' => 'sedan',
        'rental_type' => 'own',
        'vendor_id' => $vendor->id,
        'driver_id' => $driver->id,
        'is_active' => true,
        'status' => 'maintenance',
    ]);

    $response->assertRedirect();
    expect(Vehicle::where('registration_number', 'like', 'STA-%')->first()->status)->toBe('maintenance');
});

it('updates the operational status when editing a vehicle', function () {
    seedVehicleStatusPermissions();
    $user = makeVehicleStatusUser();
    $vendor = Vendor::create(['name' => 'V2', 'status' => 'active']);
    $driver = User::create([
        'email_verified_at' => now(),
        'name' => 'Driver 2',
        'username' => 'driver2_'.uniqid(),
        'email' => 'driver2_'.uniqid().'@example.com',
        'user_type' => 'driver',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);

    $vehicle = Vehicle::create([
        'brand' => 'Toyota', 'model' => 'Camry',
        'registration_number' => 'STA-'.uniqid(),
        'is_active' => true,
        'status' => 'available',
    ]);

    $response = $this->actingAs($user)->put("/vehicles/{$vehicle->id}", [
        'brand' => 'Toyota',
        'model' => 'Camry',
        'registration_number' => $vehicle->registration_number,
        'vehicle_type' => 'sedan',
        'rental_type' => 'own',
        'vendor_id' => $vendor->id,
        'driver_id' => $driver->id,
        'is_active' => true,
        'status' => 'out_of_service',
    ]);

    $response->assertSessionDoesntHaveErrors();
    $response->assertRedirect();
    expect($vehicle->fresh()->status)->toBe('out_of_service');
});

it('can still edit a vehicle that currently has no vendor or driver assigned (the "Assign Driver" unassign flow leaves it that way)', function () {
    seedVehicleStatusPermissions();
    $user = makeVehicleStatusUser();

    $vehicle = Vehicle::create([
        'brand' => 'Toyota', 'model' => 'Camry',
        'registration_number' => 'STA-'.uniqid(),
        'is_active' => true,
        'status' => 'available',
        'vendor_id' => null,
        'driver_id' => null,
    ]);

    $response = $this->actingAs($user)->put("/vehicles/{$vehicle->id}", [
        'brand' => 'Toyota',
        'model' => 'Camry',
        'registration_number' => $vehicle->registration_number,
        'vehicle_type' => 'sedan',
        'rental_type' => 'own',
        'vendor_id' => 'none',
        'driver_id' => 'none',
        'is_active' => true,
        'status' => 'maintenance',
    ]);

    $response->assertSessionDoesntHaveErrors();
    $response->assertRedirect();
    expect($vehicle->fresh()->status)->toBe('maintenance')
        ->and($vehicle->fresh()->vendor_id)->toBeNull()
        ->and($vehicle->fresh()->driver_id)->toBeNull();
});

it('filters the vehicle index by status using the filters[] array format the table sends', function () {
    seedVehicleStatusPermissions();
    $user = makeVehicleStatusUser();

    $available = Vehicle::create(['brand' => 'A', 'model' => 'A1', 'registration_number' => 'STA-'.uniqid(), 'status' => 'available']);
    $maintenance = Vehicle::create(['brand' => 'B', 'model' => 'B1', 'registration_number' => 'STA-'.uniqid(), 'status' => 'maintenance']);

    $response = $this->actingAs($user)->get('/vehicles?'.http_build_query([
        'filters' => ['status' => ['maintenance']],
    ]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('vehicles.data', fn ($vehicles) => collect($vehicles)->pluck('id')->contains($maintenance->id)
            && ! collect($vehicles)->pluck('id')->contains($available->id)
        )
    );
});
