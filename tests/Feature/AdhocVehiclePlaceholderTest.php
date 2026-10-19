<?php

use App\Models\User;
use App\Models\Vehicle;
use App\Models\Vendor;
use App\Models\Zone;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Ad-hoc hires are often booked before the actual car is known, so an
 * ad-hoc vehicle may be saved with its identity, vendor and driver left
 * blank — VehicleController fills the identity with placeholders
 * (ADHOC-001 / Ad-hoc / TBD) until someone edits in the real car.
 */
function seedAdhocVehiclePermissions(): void
{
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    foreach (['view-vehicles', 'create-vehicles', 'edit-vehicles'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
}

function makeAdhocVehicleActor(): User
{
    $user = User::create([
        'email_verified_at' => now(),
        'name' => 'Ad-hoc Vehicle Actor',
        'username' => 'adhoc_actor_'.uniqid(),
        'email' => 'adhoc_actor_'.uniqid().'@example.com',
        'user_type' => 'employee',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);
    $user->givePermissionTo(['view-vehicles', 'create-vehicles', 'edit-vehicles']);

    return $user;
}

it('creates an ad-hoc vehicle with blank identity, vendor and driver, filling placeholders', function () {
    seedAdhocVehiclePermissions();
    $actor = makeAdhocVehicleActor();

    $this->actingAs($actor)->post('/vehicles', [
        'zone_id' => testZoneId(),
        'vehicle_type' => 'van',
        'rental_type' => 'adhoc',
        'vendor_id' => 'none',
        'driver_id' => 'none',
        'is_active' => true,
    ])->assertSessionHasNoErrors()->assertRedirect(route('vehicles.index'));

    $vehicle = Vehicle::latest('id')->first();
    expect($vehicle->registration_number)->toBe('ADHOC-001')
        ->and($vehicle->brand)->toBe(Vehicle::PLACEHOLDER_BRAND)
        ->and($vehicle->model)->toBe(Vehicle::PLACEHOLDER_MODEL)
        ->and($vehicle->vendor_id)->toBeNull()
        ->and($vehicle->driver_id)->toBeNull()
        ->and($vehicle->isPlaceholder())->toBeTrue();
});

it('numbers placeholders sequentially across zones', function () {
    seedAdhocVehiclePermissions();
    $dhaka = Zone::create(['name' => 'Dhaka', 'code' => 'DHK']);
    $ctg = Zone::create(['name' => 'Chattogram', 'code' => 'CTG']);
    Vehicle::create(['brand' => 'Ad-hoc', 'model' => 'TBD', 'registration_number' => 'ADHOC-001', 'rental_type' => 'adhoc', 'zone_id' => $dhaka->id]);
    Vehicle::create(['brand' => 'Ad-hoc', 'model' => 'TBD', 'registration_number' => 'ADHOC-007', 'rental_type' => 'adhoc', 'zone_id' => $ctg->id]);

    // A Dhaka-only user can't see the CTG placeholder, but must not reuse its number
    $dhakaUser = makeAdhocVehicleActor();
    $dhakaUser->update(['zone_id' => $dhaka->id]);
    $this->actingAs($dhakaUser);

    expect(Vehicle::nextPlaceholderRegistration())->toBe('ADHOC-008');
});

it('still requires brand, model, registration, vendor and driver for non ad-hoc vehicles', function () {
    seedAdhocVehiclePermissions();
    $actor = makeAdhocVehicleActor();

    $this->actingAs($actor)->post('/vehicles', [
        'zone_id' => testZoneId(),
        'vehicle_type' => 'van',
        'rental_type' => 'pool',
        'vendor_id' => 'none',
        'driver_id' => 'none',
    ])->assertSessionHasErrors(['brand', 'model', 'registration_number', 'vendor_id', 'driver_id']);
});

it('keeps typed identity values for an ad-hoc vehicle instead of placeholders', function () {
    seedAdhocVehiclePermissions();
    $actor = makeAdhocVehicleActor();

    $this->actingAs($actor)->post('/vehicles', [
        'zone_id' => testZoneId(),
        'brand' => 'Toyota',
        'model' => 'Hiace',
        'registration_number' => 'DM-CHA-11-2233',
        'vehicle_type' => 'van',
        'rental_type' => 'adhoc',
        'vendor_id' => 'none',
        'driver_id' => 'none',
    ])->assertSessionHasNoErrors();

    $vehicle = Vehicle::where('registration_number', 'DM-CHA-11-2233')->firstOrFail();
    expect($vehicle->brand)->toBe('Toyota')
        ->and($vehicle->isPlaceholder())->toBeFalse();
});

it('turns a placeholder into the real vehicle on edit, keeping the record', function () {
    seedAdhocVehiclePermissions();
    $actor = makeAdhocVehicleActor();
    $vendor = Vendor::create(['name' => 'Hello Rent-a-car', 'status' => 'active']);
    $vehicle = Vehicle::create(['brand' => 'Ad-hoc', 'model' => 'TBD', 'registration_number' => 'ADHOC-001', 'rental_type' => 'adhoc', 'vehicle_type' => 'van']);

    $this->actingAs($actor)->put("/vehicles/{$vehicle->id}", [
        'zone_id' => testZoneId(),
        'brand' => 'Toyota',
        'model' => 'Noah',
        'registration_number' => 'DM-CHA-20-4812',
        'vehicle_type' => 'van',
        'rental_type' => 'adhoc',
        'vendor_id' => $vendor->id,
        'driver_id' => 'none',
    ])->assertSessionHasNoErrors();

    $vehicle->refresh();
    expect($vehicle->registration_number)->toBe('DM-CHA-20-4812')
        ->and($vehicle->vendor_id)->toBe($vendor->id)
        ->and($vehicle->isPlaceholder())->toBeFalse();
});

it('keeps the current registration when an ad-hoc edit leaves it blank', function () {
    seedAdhocVehiclePermissions();
    $actor = makeAdhocVehicleActor();
    $vehicle = Vehicle::create(['brand' => 'Ad-hoc', 'model' => 'TBD', 'registration_number' => 'ADHOC-003', 'rental_type' => 'adhoc', 'vehicle_type' => 'van']);

    $this->actingAs($actor)->put("/vehicles/{$vehicle->id}", [
        'zone_id' => testZoneId(),
        'registration_number' => '',
        'vehicle_type' => 'van',
        'rental_type' => 'adhoc',
        'capacity' => 10,
        'vendor_id' => 'none',
        'driver_id' => 'none',
    ])->assertSessionHasNoErrors();

    $vehicle->refresh();
    expect($vehicle->registration_number)->toBe('ADHOC-003')
        ->and($vehicle->capacity)->toBe(10);
});

it('filters the vehicle list to placeholders still waiting for details', function () {
    seedAdhocVehiclePermissions();
    $actor = makeAdhocVehicleActor();
    $placeholder = Vehicle::create(['brand' => 'Ad-hoc', 'model' => 'TBD', 'registration_number' => 'ADHOC-001', 'rental_type' => 'adhoc']);
    $real = Vehicle::create(['brand' => 'Toyota', 'model' => 'Hiace', 'registration_number' => 'REAL-'.uniqid(), 'rental_type' => 'pool']);

    $this->actingAs($actor)->get('/vehicles?filters[details][]=pending')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('vehicles.data', fn ($rows) => collect($rows)->pluck('id')->all() === [$placeholder->id]
                && collect($rows)->first()['is_placeholder'] === true)
        );

    $this->actingAs($actor)->get('/vehicles?filters[details][]=complete')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('vehicles.data', fn ($rows) => collect($rows)->pluck('id')->all() === [$real->id])
        );
});

it('accepts the support rental type the form offers', function () {
    seedAdhocVehiclePermissions();
    $actor = makeAdhocVehicleActor();
    $vendor = Vendor::create(['name' => 'Support Vendor', 'status' => 'active']);
    $driver = User::create([
        'name' => 'Support Driver', 'username' => 'support_drv_'.uniqid(),
        'email' => 'support_drv_'.uniqid().'@example.com', 'user_type' => 'driver',
        'status' => 'active', 'password' => Hash::make('password'),
    ]);

    $this->actingAs($actor)->post('/vehicles', [
        'zone_id' => testZoneId(),
        'brand' => 'Toyota',
        'model' => 'Hilux',
        'registration_number' => 'SUP-'.uniqid(),
        'vehicle_type' => 'pickup',
        'rental_type' => 'support',
        'vendor_id' => $vendor->id,
        'driver_id' => $driver->id,
    ])->assertSessionHasNoErrors();

    expect(Vehicle::where('rental_type', 'support')->count())->toBe(1);
});
