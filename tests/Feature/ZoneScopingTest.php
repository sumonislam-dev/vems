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

it('maps a city to its zone, leaving unrecognized cities unmapped', function () {
    $dhaka = Zone::create(['name' => 'Dhaka', 'code' => 'DHK', 'is_active' => true]);
    $ctg = Zone::create(['name' => 'Chattogram', 'code' => 'CTG', 'is_active' => true]);

    expect(Zone::idForCity('Gazipur'))->toBe($dhaka->id)
        ->and(Zone::idForCity('Chittagong'))->toBe($ctg->id)
        ->and(Zone::idForCity('Sylhet'))->toBeNull();
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

it("gives a trip with no vehicle its requester's zone, switching to the vehicle's once assigned and back when unassigned", function () {
    $dhaka = Zone::create(['name' => 'Dhaka', 'code' => 'DHK', 'is_active' => true]);
    $ctg = Zone::create(['name' => 'Chattogram', 'code' => 'CTG', 'is_active' => true]);
    $ctgVehicle = Vehicle::create([
        'brand' => 'Honda', 'model' => 'Civic',
        'registration_number' => 'ZONE-'.uniqid(),
        'is_active' => true, 'zone_id' => $ctg->id,
    ]);
    $requester = makeZoneScopingUser($dhaka);

    $trip = Trip::create([
        'requested_by' => $requester->id,
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
    expect($trip->fresh()->zone_id)->toBe($dhaka->id);
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

it('offers no zone switcher options to a single-zone user, and every zone to a view-all-zones user', function () {
    seedZoneScopingPermissions();

    $dhaka = Zone::create(['name' => 'Dhaka', 'code' => 'DHK', 'is_active' => true]);
    $ctg = Zone::create(['name' => 'Chattogram', 'code' => 'CTG', 'is_active' => true]);

    $single = makeZoneScopingUser($dhaka);
    expect(Zone::switcherOptions($single))->toHaveCount(0);

    $viewAll = makeZoneScopingUser($dhaka);
    $viewAll->givePermissionTo('view-all-zones');
    expect(Zone::switcherOptions($viewAll)->pluck('id')->sort()->values()->all())
        ->toBe(collect([$dhaka->id, $ctg->id])->sort()->values()->all());

    $multi = makeZoneScopingUser();
    $multi->accessZones()->sync([$dhaka->id, $ctg->id]);
    expect(Zone::switcherOptions($multi))->toHaveCount(2);
});

it('lets a multi-zone user switch their active zone, narrowing what they see until they switch back', function () {
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
    $viewer->accessZones()->sync([$dhaka->id, $ctg->id]);

    $seesBoth = fn ($vehicles) => collect($vehicles)->pluck('id')->contains($dhakaVehicle->id)
        && collect($vehicles)->pluck('id')->contains($ctgVehicle->id);
    $seesOnlyDhaka = fn ($vehicles) => collect($vehicles)->pluck('id')->contains($dhakaVehicle->id)
        && ! collect($vehicles)->pluck('id')->contains($ctgVehicle->id);

    $this->actingAs($viewer)->get('/vehicles')
        ->assertInertia(fn ($page) => $page->where('vehicles.data', $seesBoth));

    $this->actingAs($viewer)->patch('/active-zone', ['zone_id' => $dhaka->id])
        ->assertSessionDoesntHaveErrors();

    $this->actingAs($viewer)->get('/vehicles')
        ->assertInertia(fn ($page) => $page->where('vehicles.data', $seesOnlyDhaka));

    $this->actingAs($viewer)->patch('/active-zone', ['zone_id' => null]);

    $this->actingAs($viewer)->get('/vehicles')
        ->assertInertia(fn ($page) => $page->where('vehicles.data', $seesBoth));
});

it("defaults the trips list zone filter to the navbar switcher's active zone, without hiding other zones once cleared", function () {
    seedZoneScopingPermissions();
    Permission::firstOrCreate(['name' => 'view-trips', 'guard_name' => 'web']);
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

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
    $viewer->givePermissionTo('view-trips');
    $viewer->accessZones()->sync([$dhaka->id, $ctg->id]);

    $dhakaTrip = Trip::create([
        'requested_by' => $viewer->id, 'vehicle_id' => $dhakaVehicle->id, 'priority' => 'medium',
        'scheduled_date' => '2026-06-01', 'scheduled_start_time' => '08:00', 'scheduled_end_time' => '09:00',
        'status' => 'pending',
    ]);
    $ctgTrip = Trip::create([
        'requested_by' => $viewer->id, 'vehicle_id' => $ctgVehicle->id, 'priority' => 'medium',
        'scheduled_date' => '2026-06-01', 'scheduled_start_time' => '08:00', 'scheduled_end_time' => '09:00',
        'status' => 'pending',
    ]);

    // No active zone yet: trips list sees both, with no zone filter applied.
    $this->actingAs($viewer)->get('/trips')->assertInertia(fn ($page) => $page
        ->component('trips/index')
        ->where('queryParams.filters', [])
        ->where('trips.data', function ($trips) use ($dhakaTrip, $ctgTrip) {
            $ids = collect($trips)->pluck('id');

            return $ids->contains($dhakaTrip->id) && $ids->contains($ctgTrip->id);
        })
    );

    $this->actingAs($viewer)->patch('/active-zone', ['zone_id' => $dhaka->id])->assertSessionDoesntHaveErrors();

    // Active zone set: trips list defaults its own filter to it.
    $this->actingAs($viewer)->get('/trips')->assertInertia(fn ($page) => $page
        ->component('trips/index')
        ->where('queryParams.filters.zone_id', [(string) $dhaka->id])
        ->where('trips.data', function ($trips) use ($dhakaTrip, $ctgTrip) {
            $ids = collect($trips)->pluck('id');

            return $ids->contains($dhakaTrip->id) && ! $ids->contains($ctgTrip->id);
        })
    );

    // Explicitly picking a different zone filter on the page overrides the default.
    $this->actingAs($viewer)->get('/trips?filters[zone_id][]='.$ctg->id)->assertInertia(fn ($page) => $page
        ->where('trips.data', function ($trips) use ($dhakaTrip, $ctgTrip) {
            $ids = collect($trips)->pluck('id');

            return ! $ids->contains($dhakaTrip->id) && $ids->contains($ctgTrip->id);
        })
    );
});

it('rejects switching the active zone to one outside the users real access', function () {
    seedZoneScopingPermissions();

    $dhaka = Zone::create(['name' => 'Dhaka', 'code' => 'DHK', 'is_active' => true]);
    $ctg = Zone::create(['name' => 'Chattogram', 'code' => 'CTG', 'is_active' => true]);

    $viewer = makeZoneScopingUser($dhaka);

    $this->actingAs($viewer)->patch('/active-zone', ['zone_id' => $ctg->id])->assertForbidden();
});
