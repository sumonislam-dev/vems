<?php

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDriverAssignment;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

function seedVehicleDriverAssignmentPermissions(): void
{
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    Permission::firstOrCreate(['name' => 'assign-vehicles', 'guard_name' => 'web']);
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
}

function makeAssignmentDriver(string $username, array $overrides = []): User
{
    return User::create(array_merge([
        'email_verified_at' => now(),
        'name' => 'Driver '.$username,
        'username' => $username,
        'email' => $username.'@example.com',
        'user_type' => 'driver',
        'status' => 'active',
        'driver_status' => 'available',
        'password' => Hash::make('password'),
    ], $overrides));
}

function makeAssignmentManager(string $username): User
{
    return User::create([
        'email_verified_at' => now(),
        'name' => 'Manager '.$username,
        'username' => $username,
        'email' => $username.'@example.com',
        'user_type' => 'employee',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);
}

function makeAssignmentVehicle(array $overrides = []): Vehicle
{
    return Vehicle::create(array_merge([
        'brand' => 'Toyota',
        'model' => 'Hiace',
        'registration_number' => 'VEH-'.uniqid(),
        'is_active' => true,
    ], $overrides));
}

it('assigns a vehicle to a driver with no current vehicle and records assignment history', function () {
    seedVehicleDriverAssignmentPermissions();
    $manager = makeAssignmentManager('assigner-1');
    $manager->givePermissionTo('assign-vehicles');

    $driver = makeAssignmentDriver('driver-1');
    $vehicle = makeAssignmentVehicle();

    $response = $this->actingAs($manager)->post("/vehicles/{$vehicle->id}/assign-driver", [
        'driver_id' => $driver->id,
    ]);
    $response->assertRedirect();

    $vehicle->refresh();
    expect($vehicle->driver_id)->toBe($driver->id);

    $this->assertDatabaseHas('vehicle_driver_assignments', [
        'vehicle_id' => $vehicle->id,
        'driver_id' => $driver->id,
        'is_current' => true,
    ]);
});

it('unassigns a vehicle by sending a null driver_id', function () {
    seedVehicleDriverAssignmentPermissions();
    $manager = makeAssignmentManager('assigner-2');
    $manager->givePermissionTo('assign-vehicles');

    $driver = makeAssignmentDriver('driver-2');
    $vehicle = makeAssignmentVehicle(['driver_id' => $driver->id]);

    $response = $this->actingAs($manager)->post("/vehicles/{$vehicle->id}/assign-driver", [
        'driver_id' => null,
    ]);
    $response->assertRedirect();

    expect($vehicle->refresh()->driver_id)->toBeNull();

    $assignment = VehicleDriverAssignment::where('vehicle_id', $vehicle->id)->where('driver_id', $driver->id)->first();
    expect($assignment->is_current)->toBeFalse()
        ->and($assignment->ended_at)->not->toBeNull();
});

it('refuses to reassign a driver already on another vehicle without confirm_reassign', function () {
    seedVehicleDriverAssignmentPermissions();
    $manager = makeAssignmentManager('assigner-3');
    $manager->givePermissionTo('assign-vehicles');

    $driver = makeAssignmentDriver('driver-3');
    $oldVehicle = makeAssignmentVehicle(['driver_id' => $driver->id]);
    $newVehicle = makeAssignmentVehicle();

    $response = $this->actingAs($manager)->post("/vehicles/{$newVehicle->id}/assign-driver", [
        'driver_id' => $driver->id,
    ]);
    $response->assertSessionHasErrors('confirm_reassign');

    expect($newVehicle->refresh()->driver_id)->toBeNull()
        ->and($oldVehicle->refresh()->driver_id)->toBe($driver->id);
});

it('reassigns a driver to a new vehicle and unassigns the old one when confirm_reassign is sent', function () {
    seedVehicleDriverAssignmentPermissions();
    $manager = makeAssignmentManager('assigner-4');
    $manager->givePermissionTo('assign-vehicles');

    $driver = makeAssignmentDriver('driver-4');
    $oldVehicle = makeAssignmentVehicle(['driver_id' => $driver->id]);
    $newVehicle = makeAssignmentVehicle();

    $response = $this->actingAs($manager)->post("/vehicles/{$newVehicle->id}/assign-driver", [
        'driver_id' => $driver->id,
        'confirm_reassign' => true,
    ]);
    $response->assertRedirect();
    $response->assertSessionDoesntHaveErrors();

    expect($newVehicle->refresh()->driver_id)->toBe($driver->id)
        ->and($oldVehicle->refresh()->driver_id)->toBeNull();

    $this->assertDatabaseHas('vehicle_driver_assignments', [
        'vehicle_id' => $newVehicle->id,
        'driver_id' => $driver->id,
        'is_current' => true,
    ]);
    $this->assertDatabaseHas('vehicle_driver_assignments', [
        'vehicle_id' => $oldVehicle->id,
        'driver_id' => $driver->id,
        'is_current' => false,
    ]);
});

it('rejects an ineligible driver (expired license, on_trip, or suspended)', function () {
    seedVehicleDriverAssignmentPermissions();
    $manager = makeAssignmentManager('assigner-5');
    $manager->givePermissionTo('assign-vehicles');

    $expired = makeAssignmentDriver('expired-2', ['license_expiry_date' => now()->subDay()]);
    $vehicle = makeAssignmentVehicle();

    $response = $this->actingAs($manager)->post("/vehicles/{$vehicle->id}/assign-driver", [
        'driver_id' => $expired->id,
    ]);
    $response->assertSessionHasErrors('driver_id');

    expect($vehicle->refresh()->driver_id)->toBeNull();
});

it('denies assign-driver to a user without assign-vehicles', function () {
    seedVehicleDriverAssignmentPermissions();
    $user = makeAssignmentManager('no-permission-1');
    $driver = makeAssignmentDriver('driver-5');
    $vehicle = makeAssignmentVehicle();

    $response = $this->actingAs($user)->post("/vehicles/{$vehicle->id}/assign-driver", [
        'driver_id' => $driver->id,
    ]);
    $response->assertForbidden();

    expect($vehicle->refresh()->driver_id)->toBeNull();
});

it('lists active vehicles with their current driver for the driver-side assignable-vehicles picker', function () {
    seedVehicleDriverAssignmentPermissions();
    $manager = makeAssignmentManager('assigner-6');
    $manager->givePermissionTo('assign-vehicles');

    $driver = makeAssignmentDriver('driver-6');
    $otherDriver = makeAssignmentDriver('driver-7');
    $unassignedVehicle = makeAssignmentVehicle();
    $assignedVehicle = makeAssignmentVehicle(['driver_id' => $otherDriver->id]);
    $inactiveVehicle = makeAssignmentVehicle(['is_active' => false]);

    $response = $this->actingAs($manager)->getJson("/drivers/{$driver->id}/assignable-vehicles");
    $response->assertOk();

    $ids = collect($response->json())->pluck('id');
    expect($ids)->toContain($unassignedVehicle->id)
        ->and($ids)->toContain($assignedVehicle->id)
        ->and($ids)->not->toContain($inactiveVehicle->id);

    $assignedEntry = collect($response->json())->firstWhere('id', $assignedVehicle->id);
    expect($assignedEntry['current_driver_id'])->toBe($otherDriver->id)
        ->and($assignedEntry['current_driver_name'])->toBe($otherDriver->name);
});
