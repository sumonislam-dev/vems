<?php

use App\Models\User;
use App\Models\Vehicle;
use App\Models\Vendor;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * VehicleController's core CRUD had only narrow coverage before this file —
 * VehicleDocumentUploadTest.php/VehicleStatusTest.php exercise file-upload
 * and status-change slices, but general store() validation (registration
 * number/vendor/driver requirements), the plain update/destroy paths, and
 * permission gating on every action were untested. This fills that gap.
 */
function seedVehicleCrudPermissions(): void
{
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    Permission::firstOrCreate(['name' => 'view-vehicles', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'create-vehicles', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'edit-vehicles', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'delete-vehicles', 'guard_name' => 'web']);
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
}

function makeVehicleCrudActor(array $permissions = []): User
{
    $user = User::create([
        'email_verified_at' => now(),
        'name' => 'Vehicle CRUD Actor',
        'username' => 'vehicle_crud_actor_'.uniqid(),
        'email' => 'vehicle_crud_actor_'.uniqid().'@example.com',
        'user_type' => 'employee',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);

    if (! empty($permissions)) {
        $user->givePermissionTo($permissions);
    }

    return $user;
}

function makeVehicleCrudDriver(): User
{
    return User::create([
        'email_verified_at' => now(),
        'name' => 'Vehicle CRUD Driver',
        'username' => 'vehicle_crud_driver_'.uniqid(),
        'email' => 'vehicle_crud_driver_'.uniqid().'@example.com',
        'user_type' => 'driver', 'driver_status' => 'available',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);
}

// --- index / show -------------------------------------------------------

it('lists vehicles for someone with view-vehicles permission', function () {
    seedVehicleCrudPermissions();
    $actor = makeVehicleCrudActor(['view-vehicles']);
    $vehicle = Vehicle::create([
        'brand' => 'Toyota', 'model' => 'Corolla',
        'registration_number' => 'CRUD-'.uniqid(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($actor)->get('/vehicles');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('vehicles/index')
        ->where('vehicles.data', fn ($vehicles) => collect($vehicles)->pluck('id')->contains($vehicle->id))
    );
});

it('denies the vehicles index to someone without view-vehicles permission', function () {
    seedVehicleCrudPermissions();
    $actor = makeVehicleCrudActor();

    $this->actingAs($actor)->get('/vehicles')->assertForbidden();
});

it('shows a single vehicle to someone with view-vehicles permission', function () {
    seedVehicleCrudPermissions();
    $actor = makeVehicleCrudActor(['view-vehicles']);
    $vehicle = Vehicle::create([
        'brand' => 'Toyota', 'model' => 'Corolla',
        'registration_number' => 'CRUD-'.uniqid(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($actor)->get("/vehicles/{$vehicle->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('vehicles/show')
        ->where('vehicle.id', $vehicle->id)
    );
});

// --- store ----------------------------------------------------------------

it('creates a vehicle with valid data when authorized', function () {
    seedVehicleCrudPermissions();
    $actor = makeVehicleCrudActor(['create-vehicles']);
    $vendor = Vendor::create(['name' => 'CRUD Vendor', 'status' => 'active']);
    $driver = makeVehicleCrudDriver();

    $response = $this->actingAs($actor)->post('/vehicles', [
        'zone_id' => testZoneId(),
        'brand' => 'Toyota',
        'model' => 'Camry',
        'registration_number' => 'CRUD-'.uniqid(),
        'vehicle_type' => 'sedan',
        'rental_type' => 'own',
        'vendor_id' => $vendor->id,
        'driver_id' => $driver->id,
        'is_active' => true,
    ]);

    $response->assertSessionDoesntHaveErrors();
    $response->assertRedirect(route('vehicles.index'));
    expect(Vehicle::where('registration_number', 'like', 'CRUD-%')->exists())->toBeTrue();
});

it('requires a zone to create or edit a vehicle, even an ad-hoc one', function () {
    seedVehicleCrudPermissions();
    $actor = makeVehicleCrudActor(['create-vehicles', 'edit-vehicles']);
    $vehicle = Vehicle::create(['brand' => 'Toyota', 'model' => 'Hiace', 'registration_number' => 'CRUD-'.uniqid(), 'vehicle_type' => 'van', 'rental_type' => 'pool']);

    $this->actingAs($actor)->post('/vehicles', ['vehicle_type' => 'van', 'rental_type' => 'adhoc', 'zone_id' => 'none'])
        ->assertSessionHasErrors('zone_id');
    $this->actingAs($actor)->put("/vehicles/{$vehicle->id}", [...$vehicle->only(['brand', 'model', 'registration_number', 'vehicle_type', 'rental_type']), 'zone_id' => 'none'])
        ->assertSessionHasErrors('zone_id');
});

it('requires a vendor and driver to create a vehicle', function () {
    seedVehicleCrudPermissions();
    $actor = makeVehicleCrudActor(['create-vehicles']);

    $response = $this->actingAs($actor)->post('/vehicles', [
        'zone_id' => testZoneId(),
        'brand' => 'Toyota',
        'model' => 'Camry',
        'registration_number' => 'CRUD-'.uniqid(),
        'vehicle_type' => 'sedan',
        'rental_type' => 'own',
    ]);

    $response->assertSessionHasErrors(['vendor_id', 'driver_id']);
});

it('rejects a duplicate registration number when creating a vehicle', function () {
    seedVehicleCrudPermissions();
    $actor = makeVehicleCrudActor(['create-vehicles']);
    $vendor = Vendor::create(['name' => 'CRUD Vendor 2', 'status' => 'active']);
    $driver = makeVehicleCrudDriver();
    $existing = Vehicle::create([
        'brand' => 'Toyota', 'model' => 'Corolla',
        'registration_number' => 'CRUD-DUP-'.uniqid(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($actor)->post('/vehicles', [
        'zone_id' => testZoneId(),
        'brand' => 'Toyota',
        'model' => 'Camry',
        'registration_number' => $existing->registration_number,
        'vehicle_type' => 'sedan',
        'rental_type' => 'own',
        'vendor_id' => $vendor->id,
        'driver_id' => $driver->id,
    ]);

    $response->assertSessionHasErrors(['registration_number']);
});

it('denies creating a vehicle to someone without create-vehicles permission', function () {
    seedVehicleCrudPermissions();
    $actor = makeVehicleCrudActor();

    $this->actingAs($actor)->post('/vehicles', [
        'zone_id' => testZoneId(),
        'brand' => 'Toyota',
        'model' => 'Camry',
        'registration_number' => 'CRUD-'.uniqid(),
    ])->assertForbidden();
});

// --- update -----------------------------------------------------------------

it('updates a vehicle\'s fields when authorized', function () {
    seedVehicleCrudPermissions();
    $actor = makeVehicleCrudActor(['edit-vehicles']);
    $vehicle = Vehicle::create([
        'brand' => 'Toyota', 'model' => 'Corolla',
        'registration_number' => 'CRUD-'.uniqid(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($actor)->put("/vehicles/{$vehicle->id}", [
        'zone_id' => testZoneId(),
        'brand' => 'Toyota',
        'model' => 'Corolla Altis',
        'registration_number' => $vehicle->registration_number,
        'vehicle_type' => 'sedan',
        'rental_type' => 'own',
        'is_active' => false,
    ]);

    $response->assertSessionDoesntHaveErrors();
    $response->assertRedirect(route('vehicles.index'));

    $vehicle->refresh();
    expect($vehicle->model)->toBe('Corolla Altis')
        ->and($vehicle->is_active)->toBeFalse();
});

it('rejects an update that reuses another vehicle\'s registration number', function () {
    seedVehicleCrudPermissions();
    $actor = makeVehicleCrudActor(['edit-vehicles']);
    $other = Vehicle::create([
        'brand' => 'Honda', 'model' => 'Civic',
        'registration_number' => 'CRUD-OTHER-'.uniqid(),
        'is_active' => true,
    ]);
    $vehicle = Vehicle::create([
        'brand' => 'Toyota', 'model' => 'Corolla',
        'registration_number' => 'CRUD-'.uniqid(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($actor)->put("/vehicles/{$vehicle->id}", [
        'zone_id' => testZoneId(),
        'brand' => 'Toyota',
        'model' => 'Corolla',
        'registration_number' => $other->registration_number,
        'vehicle_type' => 'sedan',
        'rental_type' => 'own',
    ]);

    $response->assertSessionHasErrors(['registration_number']);
});

it('denies updating a vehicle to someone without edit-vehicles permission', function () {
    seedVehicleCrudPermissions();
    $actor = makeVehicleCrudActor();
    $vehicle = Vehicle::create([
        'brand' => 'Toyota', 'model' => 'Corolla',
        'registration_number' => 'CRUD-'.uniqid(),
        'is_active' => true,
    ]);

    $this->actingAs($actor)->put("/vehicles/{$vehicle->id}", [
        'zone_id' => testZoneId(),
        'brand' => 'Should Not Apply',
    ])->assertForbidden();
});

// --- destroy ------------------------------------------------------------

it('deletes a vehicle when authorized', function () {
    seedVehicleCrudPermissions();
    $actor = makeVehicleCrudActor(['delete-vehicles']);
    $vehicle = Vehicle::create([
        'brand' => 'Toyota', 'model' => 'Corolla',
        'registration_number' => 'CRUD-'.uniqid(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($actor)->delete("/vehicles/{$vehicle->id}");

    $response->assertRedirect(route('vehicles.index'));
    expect(Vehicle::find($vehicle->id))->toBeNull();
});

it('denies deleting a vehicle to someone without delete-vehicles permission', function () {
    seedVehicleCrudPermissions();
    $actor = makeVehicleCrudActor();
    $vehicle = Vehicle::create([
        'brand' => 'Toyota', 'model' => 'Corolla',
        'registration_number' => 'CRUD-'.uniqid(),
        'is_active' => true,
    ]);

    $this->actingAs($actor)->delete("/vehicles/{$vehicle->id}")->assertForbidden();
    expect(Vehicle::find($vehicle->id))->not->toBeNull();
});

// --- driver checks on the form (same as "Assign Driver") -----------------

function vehicleCrudPayload(array $overrides = []): array
{
    return [
        'zone_id' => testZoneId(),
        'brand' => 'Toyota',
        'model' => 'Camry',
        'registration_number' => 'CRUD-'.uniqid(),
        'vehicle_type' => 'sedan',
        'rental_type' => 'own',
        'is_active' => true,
        ...$overrides,
    ];
}

it('refuses a driver on the vehicle form who is not eligible or already drives another vehicle', function () {
    seedVehicleCrudPermissions();
    $actor = makeVehicleCrudActor(['create-vehicles', 'edit-vehicles']);
    $vendor = Vendor::create(['name' => 'V-checks', 'status' => 'active']);

    $expired = makeVehicleCrudDriver();
    $expired->update(['license_expiry_date' => now()->subDay()]);
    $this->actingAs($actor)->post('/vehicles', vehicleCrudPayload(['vendor_id' => $vendor->id, 'driver_id' => $expired->id]))
        ->assertSessionHasErrors(['driver_id' => 'This driver is not eligible for assignment (inactive, unavailable, or license expired).']);

    $busy = makeVehicleCrudDriver();
    $other = Vehicle::create(['brand' => 'Toyota', 'model' => 'Hiace', 'registration_number' => 'BUSY-1', 'driver_id' => $busy->id]);
    $this->actingAs($actor)->post('/vehicles', vehicleCrudPayload(['vendor_id' => $vendor->id, 'driver_id' => $busy->id]))
        ->assertSessionHasErrors('driver_id');

    // Editing the vehicle the driver is on keeps them, even once ineligible
    $busy->update(['license_expiry_date' => now()->subDay()]);
    $this->actingAs($actor)->put("/vehicles/{$other->id}", vehicleCrudPayload([
        'registration_number' => 'BUSY-1', 'vendor_id' => $vendor->id, 'driver_id' => $busy->id, 'color' => 'White',
    ]))->assertSessionHasNoErrors();
    expect($other->fresh()->color)->toBe('White');
});

it('sorts vehicles by vendor, driver, type and condition', function () {
    seedVehicleCrudPermissions();
    $actor = makeVehicleCrudActor(['view-vehicles']);
    $zebra = Vehicle::create([
        'brand' => 'Toyota', 'model' => 'A', 'registration_number' => 'SORT-1', 'vehicle_type' => 'van', 'status' => 'maintenance',
        'vendor_id' => Vendor::create(['name' => 'Zebra Cars', 'status' => 'active'])->id,
        'driver_id' => tap(makeVehicleCrudDriver())->update(['name' => 'Zakir'])->id,
    ]);
    $alpha = Vehicle::create([
        'brand' => 'Toyota', 'model' => 'B', 'registration_number' => 'SORT-2', 'vehicle_type' => 'bus', 'status' => 'available',
        'vendor_id' => Vendor::create(['name' => 'Alpha Rentals', 'status' => 'active'])->id,
        'driver_id' => tap(makeVehicleCrudDriver())->update(['name' => 'Abdul'])->id,
    ]);

    foreach (['vendor', 'driver', 'vehicle_type', 'status'] as $sort) {
        $ids = collect($this->actingAs($actor)->get(route('vehicles.index', ['sort' => $sort, 'direction' => 'asc']))
            ->assertOk()->viewData('page')['props']['vehicles']['data'])->pluck('id')->all();

        expect($ids)->toBe([$alpha->id, $zebra->id], "sort by {$sort}");
    }
});
