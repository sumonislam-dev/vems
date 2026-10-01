<?php

use App\Models\Factory;
use App\Models\Stop;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleRoute;
use App\Models\Vendor;
use App\Models\Zone;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * BelongsToZone (app/Models/Concerns/BelongsToZone.php) scopes Vehicle/Stop/
 * VehicleRoute/User queries to the viewer's zone — but only once that viewer
 * actually has a zone_id. A user with no zone assigned yet must keep seeing
 * everything (today's behavior), since these models are used well beyond
 * zone-aware admin screens (e.g. trip-creation pickers) and most users won't
 * have a zone during rollout. 'view-all-zones' is the escape valve for a
 * zoned user (e.g. a cross-zone admin) who should still see every zone.
 */
function seedZoneScopingPermissions(): void
{
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    Permission::firstOrCreate(['name' => 'view-vehicles', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'view-all-zones', 'guard_name' => 'web']);
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
}

function makeZoneScopingUser(?Zone $zone = null): User
{
    return User::create([
        'email_verified_at' => now(),
        'name' => 'Zone Scoping User',
        'username' => 'zone_user_'.uniqid(),
        'email' => 'zone_user_'.uniqid().'@example.com',
        'user_type' => 'employee',
        'status' => 'active',
        'password' => Hash::make('password'),
        'zone_id' => $zone?->id,
    ]);
}

it('scopes vehicles to the viewer zone when the viewer has one assigned', function () {
    seedZoneScopingPermissions();
    $dhaka = Zone::create(['name' => 'Dhaka', 'code' => 'DHK', 'is_active' => true]);
    $ctg = Zone::create(['name' => 'Chattogram', 'code' => 'CTG', 'is_active' => true]);

    $dhakaVehicle = Vehicle::create([
        'brand' => 'Toyota', 'model' => 'Corolla',
        'registration_number' => 'ZONE-'.uniqid(),
        'is_active' => true, 'zone_id' => $dhaka->id,
    ]);
    $ctgVehicle = Vehicle::create([
        'brand' => 'Honda', 'model' => 'Civic',
        'registration_number' => 'ZONE-'.uniqid(),
        'is_active' => true, 'zone_id' => $ctg->id,
    ]);

    $viewer = makeZoneScopingUser($dhaka);
    $viewer->givePermissionTo('view-vehicles');

    $this->actingAs($viewer)->get('/vehicles')->assertInertia(fn ($page) => $page
        ->component('vehicles/index')
        ->where('vehicles.data', function ($vehicles) use ($dhakaVehicle, $ctgVehicle) {
            $ids = collect($vehicles)->pluck('id');

            return $ids->contains($dhakaVehicle->id) && ! $ids->contains($ctgVehicle->id);
        })
    );
});

it('does not restrict a viewer with no zone assigned (unaffected by rollout)', function () {
    seedZoneScopingPermissions();
    $dhaka = Zone::create(['name' => 'Dhaka', 'code' => 'DHK', 'is_active' => true]);
    $ctg = Zone::create(['name' => 'Chattogram', 'code' => 'CTG', 'is_active' => true]);

    $dhakaVehicle = Vehicle::create([
        'brand' => 'Toyota', 'model' => 'Corolla',
        'registration_number' => 'ZONE-'.uniqid(),
        'is_active' => true, 'zone_id' => $dhaka->id,
    ]);
    $ctgVehicle = Vehicle::create([
        'brand' => 'Honda', 'model' => 'Civic',
        'registration_number' => 'ZONE-'.uniqid(),
        'is_active' => true, 'zone_id' => $ctg->id,
    ]);

    $viewer = makeZoneScopingUser();
    $viewer->givePermissionTo('view-vehicles');

    $this->actingAs($viewer)->get('/vehicles')->assertInertia(fn ($page) => $page
        ->component('vehicles/index')
        ->where('vehicles.data', function ($vehicles) use ($dhakaVehicle, $ctgVehicle) {
            $ids = collect($vehicles)->pluck('id');

            return $ids->contains($dhakaVehicle->id) && $ids->contains($ctgVehicle->id);
        })
    );
});

it('lets a zoned viewer with view-all-zones see every zone', function () {
    seedZoneScopingPermissions();
    $dhaka = Zone::create(['name' => 'Dhaka', 'code' => 'DHK', 'is_active' => true]);
    $ctg = Zone::create(['name' => 'Chattogram', 'code' => 'CTG', 'is_active' => true]);

    $ctgVehicle = Vehicle::create([
        'brand' => 'Honda', 'model' => 'Civic',
        'registration_number' => 'ZONE-'.uniqid(),
        'is_active' => true, 'zone_id' => $ctg->id,
    ]);

    $viewer = makeZoneScopingUser($dhaka);
    $viewer->givePermissionTo(['view-vehicles', 'view-all-zones']);

    $this->actingAs($viewer)->get('/vehicles')->assertInertia(fn ($page) => $page
        ->component('vehicles/index')
        ->where('vehicles.data', fn ($vehicles) => collect($vehicles)->pluck('id')->contains($ctgVehicle->id))
    );
});

it('rejects attaching a stop from a different zone to a route', function () {
    seedZoneScopingPermissions();
    Permission::firstOrCreate(['name' => 'create-routes', 'guard_name' => 'web']);
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $dhaka = Zone::create(['name' => 'Dhaka', 'code' => 'DHK', 'is_active' => true]);
    $ctg = Zone::create(['name' => 'Chattogram', 'code' => 'CTG', 'is_active' => true]);
    $ctgStop = Stop::create(['name' => 'CTG Stop '.uniqid(), 'zone_id' => $ctg->id]);

    $actor = makeZoneScopingUser();
    $actor->givePermissionTo('create-routes');

    $response = $this->actingAs($actor)->post('/routes', [
        'name' => 'Mismatched Route',
        'zone_id' => $dhaka->id,
        'stops' => [
            ['stop_id' => $ctgStop->id],
        ],
    ]);

    $response->assertSessionHasErrors('zone_id');
    expect(VehicleRoute::where('name', 'Mismatched Route')->exists())->toBeFalse();
});

it('allows attaching a stop with no zone yet to a zoned route', function () {
    seedZoneScopingPermissions();
    Permission::firstOrCreate(['name' => 'create-routes', 'guard_name' => 'web']);
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $dhaka = Zone::create(['name' => 'Dhaka', 'code' => 'DHK', 'is_active' => true]);
    $unassignedStop = Stop::create(['name' => 'Unassigned Stop '.uniqid()]);

    $actor = makeZoneScopingUser();
    $actor->givePermissionTo('create-routes');

    $response = $this->actingAs($actor)->post('/routes', [
        'name' => 'Backfillable Route',
        'zone_id' => $dhaka->id,
        'stops' => [
            ['stop_id' => $unassignedStop->id],
        ],
    ]);

    $response->assertSessionDoesntHaveErrors();
    expect(VehicleRoute::where('name', 'Backfillable Route')->exists())->toBeTrue();
});

it('only offers the restricted viewer their own zone on the vehicle create form', function () {
    seedZoneScopingPermissions();
    Permission::firstOrCreate(['name' => 'create-vehicles', 'guard_name' => 'web']);
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $dhaka = Zone::create(['name' => 'Dhaka', 'code' => 'DHK', 'is_active' => true]);
    Zone::create(['name' => 'Chattogram', 'code' => 'CTG', 'is_active' => true]);

    $restricted = makeZoneScopingUser($dhaka);
    $restricted->givePermissionTo('create-vehicles');

    $this->actingAs($restricted)->get('/vehicles/create')->assertInertia(fn ($page) => $page
        ->component('vehicles/create')
        ->where('zones', fn ($zones) => collect($zones)->pluck('id')->all() === [$dhaka->id])
    );
});

it('offers every zone on the vehicle create form to a viewer with view-all-zones', function () {
    seedZoneScopingPermissions();
    Permission::firstOrCreate(['name' => 'create-vehicles', 'guard_name' => 'web']);
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $dhaka = Zone::create(['name' => 'Dhaka', 'code' => 'DHK', 'is_active' => true]);
    $ctg = Zone::create(['name' => 'Chattogram', 'code' => 'CTG', 'is_active' => true]);

    $unrestricted = makeZoneScopingUser($dhaka);
    $unrestricted->givePermissionTo(['create-vehicles', 'view-all-zones']);

    $this->actingAs($unrestricted)->get('/vehicles/create')->assertInertia(fn ($page) => $page
        ->component('vehicles/create')
        ->where('zones', function ($zones) use ($dhaka, $ctg) {
            $ids = collect($zones)->pluck('id');

            return $ids->contains($dhaka->id) && $ids->contains($ctg->id);
        })
    );
});

it('rejects a restricted user creating a vehicle in a zone other than their own', function () {
    seedZoneScopingPermissions();
    Permission::firstOrCreate(['name' => 'create-vehicles', 'guard_name' => 'web']);
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $dhaka = Zone::create(['name' => 'Dhaka', 'code' => 'DHK', 'is_active' => true]);
    $ctg = Zone::create(['name' => 'Chattogram', 'code' => 'CTG', 'is_active' => true]);
    $vendor = Vendor::create(['name' => 'Zone Test Vendor', 'status' => 'active']);
    $driver = User::create([
        'email_verified_at' => now(),
        'name' => 'Zone Test Driver',
        'username' => 'zone_driver_'.uniqid(),
        'email' => 'zone_driver_'.uniqid().'@example.com',
        'user_type' => 'driver',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);

    $restricted = makeZoneScopingUser($dhaka);
    $restricted->givePermissionTo('create-vehicles');

    $response = $this->actingAs($restricted)->post('/vehicles', [
        'brand' => 'Toyota',
        'model' => 'Corolla',
        'registration_number' => 'ZONE-'.uniqid(),
        'vehicle_type' => 'sedan',
        'rental_type' => 'own',
        'vendor_id' => $vendor->id,
        'driver_id' => $driver->id,
        'zone_id' => $ctg->id,
        'is_active' => true,
    ]);

    $response->assertSessionHasErrors('zone_id');
    expect(Vehicle::where('registration_number', 'like', 'ZONE-%')->where('zone_id', $ctg->id)->exists())->toBeFalse();
});

it('rejects a restricted user creating a factory in a zone other than their own', function () {
    seedZoneScopingPermissions();
    Permission::firstOrCreate(['name' => 'create-factories', 'guard_name' => 'web']);
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $dhaka = Zone::create(['name' => 'Dhaka', 'code' => 'DHK', 'is_active' => true]);
    $ctg = Zone::create(['name' => 'Chattogram', 'code' => 'CTG', 'is_active' => true]);

    $restricted = makeZoneScopingUser($dhaka);
    $restricted->givePermissionTo('create-factories');

    $response = $this->actingAs($restricted)->post('/factories', [
        'account_id' => 'ACC-'.uniqid(),
        'name' => 'Cross Zone Factory',
        'status' => 'active',
        'zone_id' => $ctg->id,
    ]);

    $response->assertSessionHasErrors('zone_id');
    expect(Factory::where('name', 'Cross Zone Factory')->exists())->toBeFalse();
});

it('backfills factory zone_id from city, leaving unrecognized cities untouched', function () {
    $dhaka = Zone::create(['name' => 'Dhaka', 'code' => 'DHK', 'is_active' => true]);
    $ctg = Zone::create(['name' => 'Chattogram', 'code' => 'CTG', 'is_active' => true]);

    $dhakaFactory = Factory::create(['account_id' => 'ACC-'.uniqid(), 'name' => 'Dhaka Factory', 'status' => 'active', 'city' => 'Gazipur']);
    $ctgFactory = Factory::create(['account_id' => 'ACC-'.uniqid(), 'name' => 'CTG Factory', 'status' => 'active', 'city' => 'Chittagong']);
    $unknownFactory = Factory::create(['account_id' => 'ACC-'.uniqid(), 'name' => 'Unknown City Factory', 'status' => 'active', 'city' => 'Sylhet']);

    $this->artisan('vems:backfill-factory-zones')->assertExitCode(0);

    expect($dhakaFactory->fresh()->zone_id)->toBe($dhaka->id);
    expect($ctgFactory->fresh()->zone_id)->toBe($ctg->id);
    expect($unknownFactory->fresh()->zone_id)->toBeNull();
});

it('snapshots the assigned vehicle zone onto a trip when it is created', function () {
    $dhaka = Zone::create(['name' => 'Dhaka', 'code' => 'DHK', 'is_active' => true]);
    $vehicle = Vehicle::create([
        'brand' => 'Toyota', 'model' => 'Corolla',
        'registration_number' => 'ZONE-'.uniqid(),
        'is_active' => true, 'zone_id' => $dhaka->id,
    ]);
    $requester = makeZoneScopingUser();

    $trip = Trip::create([
        'requested_by' => $requester->id,
        'vehicle_id' => $vehicle->id,
        'priority' => 'medium',
        'scheduled_date' => '2026-06-01',
        'scheduled_start_time' => '08:00',
        'scheduled_end_time' => '09:00',
        'status' => 'pending',
    ]);

    expect($trip->zone_id)->toBe($dhaka->id);
});

it('updates a trip zone snapshot when its vehicle is reassigned, and clears it on unassignment', function () {
    $dhaka = Zone::create(['name' => 'Dhaka', 'code' => 'DHK', 'is_active' => true]);
    $ctg = Zone::create(['name' => 'Chattogram', 'code' => 'CTG', 'is_active' => true]);
    $dhakaVehicle = Vehicle::create([
        'brand' => 'Toyota', 'model' => 'Corolla',
        'registration_number' => 'ZONE-'.uniqid(),
        'is_active' => true, 'zone_id' => $dhaka->id,
    ]);
    $ctgVehicle = Vehicle::create([
        'brand' => 'Honda', 'model' => 'Civic',
        'registration_number' => 'ZONE-'.uniqid(),
        'is_active' => true, 'zone_id' => $ctg->id,
    ]);
    $requester = makeZoneScopingUser();

    $trip = Trip::create([
        'requested_by' => $requester->id,
        'vehicle_id' => $dhakaVehicle->id,
        'priority' => 'medium',
        'scheduled_date' => '2026-06-01',
        'scheduled_start_time' => '08:00',
        'scheduled_end_time' => '09:00',
        'status' => 'pending',
    ]);
    expect($trip->zone_id)->toBe($dhaka->id);

    $trip->update(['vehicle_id' => $ctgVehicle->id]);
    expect($trip->fresh()->zone_id)->toBe($ctg->id);

    $trip->update(['vehicle_id' => null]);
    expect($trip->fresh()->zone_id)->toBeNull();
});

it("grants view-all-zones directly when a user's zone access is set to all", function () {
    seedZoneScopingPermissions();
    Permission::firstOrCreate(['name' => 'edit-users', 'guard_name' => 'web']);
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $dhaka = Zone::create(['name' => 'Dhaka', 'code' => 'DHK', 'is_active' => true]);

    $editor = makeZoneScopingUser();
    $editor->givePermissionTo('edit-users');
    $target = makeZoneScopingUser($dhaka);

    $response = $this->actingAs($editor)->put("/users/{$target->id}", [
        'name' => $target->name,
        'username' => $target->username,
        'personal_phone' => '01700000000',
        'whatsapp_id' => '01700000000',
        'status' => 'active',
        'user_type' => 'employee',
        'roles' => [],
        'zone_access' => 'all',
        'zone_ids' => [],
    ]);

    $response->assertSessionDoesntHaveErrors();
    expect($target->fresh()->hasDirectPermission('view-all-zones'))->toBeTrue();
});

it('restricts a user to exactly the zones granted via specific zone access', function () {
    seedZoneScopingPermissions();
    Permission::firstOrCreate(['name' => 'edit-users', 'guard_name' => 'web']);
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $dhaka = Zone::create(['name' => 'Dhaka', 'code' => 'DHK', 'is_active' => true]);
    $ctg = Zone::create(['name' => 'Chattogram', 'code' => 'CTG', 'is_active' => true]);
    $sylhet = Zone::create(['name' => 'Sylhet', 'code' => 'SYL', 'is_active' => true]);

    $dhakaVehicle = Vehicle::create([
        'brand' => 'Toyota', 'model' => 'Corolla',
        'registration_number' => 'ZONE-'.uniqid(),
        'is_active' => true, 'zone_id' => $dhaka->id,
    ]);
    $ctgVehicle = Vehicle::create([
        'brand' => 'Honda', 'model' => 'Civic',
        'registration_number' => 'ZONE-'.uniqid(),
        'is_active' => true, 'zone_id' => $ctg->id,
    ]);
    $sylhetVehicle = Vehicle::create([
        'brand' => 'Nissan', 'model' => 'Sunny',
        'registration_number' => 'ZONE-'.uniqid(),
        'is_active' => true, 'zone_id' => $sylhet->id,
    ]);

    $editor = makeZoneScopingUser();
    $editor->givePermissionTo('edit-users');
    $target = makeZoneScopingUser();
    $target->givePermissionTo('view-vehicles');

    $response = $this->actingAs($editor)->put("/users/{$target->id}", [
        'name' => $target->name,
        'username' => $target->username,
        'personal_phone' => '01700000001',
        'whatsapp_id' => '01700000001',
        'status' => 'active',
        'user_type' => 'employee',
        'roles' => [],
        'zone_access' => 'specific',
        'zone_ids' => [$dhaka->id, $ctg->id],
    ]);

    $response->assertSessionDoesntHaveErrors();
    $target->refresh();
    expect($target->accessZones()->pluck('zones.id')->sort()->values()->all())
        ->toBe(collect([$dhaka->id, $ctg->id])->sort()->values()->all());

    $this->actingAs($target)->get('/vehicles')->assertInertia(fn ($page) => $page
        ->component('vehicles/index')
        ->where('vehicles.data', function ($vehicles) use ($dhakaVehicle, $ctgVehicle, $sylhetVehicle) {
            $ids = collect($vehicles)->pluck('id');

            return $ids->contains($dhakaVehicle->id) && $ids->contains($ctgVehicle->id) && ! $ids->contains($sylhetVehicle->id);
        })
    );
});

it('rejects a restricted editor granting zone access beyond their own zone', function () {
    seedZoneScopingPermissions();
    Permission::firstOrCreate(['name' => 'edit-users', 'guard_name' => 'web']);
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $dhaka = Zone::create(['name' => 'Dhaka', 'code' => 'DHK', 'is_active' => true]);
    $ctg = Zone::create(['name' => 'Chattogram', 'code' => 'CTG', 'is_active' => true]);

    $editor = makeZoneScopingUser($dhaka);
    $editor->givePermissionTo('edit-users');
    $target = makeZoneScopingUser($dhaka);

    $response = $this->actingAs($editor)->put("/users/{$target->id}", [
        'name' => $target->name,
        'username' => $target->username,
        'personal_phone' => '01700000002',
        'whatsapp_id' => '01700000002',
        'status' => 'active',
        'user_type' => 'employee',
        'roles' => [],
        'zone_access' => 'specific',
        'zone_ids' => [$ctg->id],
    ]);

    $response->assertSessionHasErrors('zone_ids.0');
    expect($target->fresh()->accessZones()->count())->toBe(0);
});
