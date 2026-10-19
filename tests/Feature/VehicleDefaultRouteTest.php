<?php

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleRoute;
use App\Models\Vendor;
use App\Models\Zone;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * A vehicle's regular staff pick-and-drop route (vehicles.default_route_id),
 * shown as the "Remarks" column of the monthly vehicle report.
 */
function seedDefaultRoutePermissions(): void
{
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    foreach (['view-vehicles', 'create-vehicles', 'edit-vehicles'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
}

function makeDefaultRouteActor(?int $zoneId = null): User
{
    $user = User::create([
        'email_verified_at' => now(),
        'name' => 'Default Route Actor',
        'username' => 'route_actor_'.uniqid(),
        'email' => 'route_actor_'.uniqid().'@example.com',
        'user_type' => 'employee',
        'status' => 'active',
        'zone_id' => $zoneId,
        'password' => Hash::make('password'),
    ]);
    $user->givePermissionTo(['view-vehicles', 'create-vehicles', 'edit-vehicles']);

    return $user;
}

function defaultRouteVehiclePayload(array $overrides = []): array
{
    $vendor = Vendor::create(['name' => 'Route Vendor '.uniqid(), 'status' => 'active']);
    $driver = User::create([
        'name' => 'Route Driver', 'username' => 'route_drv_'.uniqid(),
        'email' => 'route_drv_'.uniqid().'@example.com', 'user_type' => 'driver',
        'status' => 'active', 'password' => Hash::make('password'),
    ]);

    return array_merge([
        'brand' => 'Toyota',
        'model' => 'Hiace',
        'registration_number' => 'RT-'.uniqid(),
        'vehicle_type' => 'van',
        'rental_type' => 'pool',
        'vendor_id' => $vendor->id,
        'driver_id' => $driver->id,
        'zone_id' => testZoneId(),
    ], $overrides);
}

it('saves a regular pick-and-drop route on a new vehicle', function () {
    seedDefaultRoutePermissions();
    $route = VehicleRoute::create(['name' => '3.1']);

    $this->actingAs(makeDefaultRouteActor())
        ->post('/vehicles', defaultRouteVehiclePayload(['registration_number' => 'RT-NEW', 'default_route_id' => $route->id]))
        ->assertSessionHasNoErrors();

    expect(Vehicle::where('registration_number', 'RT-NEW')->first()->defaultRoute->name)->toBe('3.1');
});

it('treats "none" as no regular route', function () {
    seedDefaultRoutePermissions();

    $this->actingAs(makeDefaultRouteActor())
        ->post('/vehicles', defaultRouteVehiclePayload(['registration_number' => 'RT-NONE', 'default_route_id' => 'none']))
        ->assertSessionHasNoErrors();

    expect(Vehicle::where('registration_number', 'RT-NONE')->first()->default_route_id)->toBeNull();
});

it('rejects a route from a zone the user cannot access', function () {
    seedDefaultRoutePermissions();
    $dhaka = Zone::create(['name' => 'Dhaka', 'code' => 'DHK']);
    $ctg = Zone::create(['name' => 'Chattogram', 'code' => 'CTG']);
    $ctgRoute = VehicleRoute::create(['name' => 'CTG 1', 'zone_id' => $ctg->id]);

    $this->actingAs(makeDefaultRouteActor($dhaka->id))
        ->post('/vehicles', defaultRouteVehiclePayload(['zone_id' => $dhaka->id, 'default_route_id' => $ctgRoute->id]))
        ->assertSessionHasErrors('default_route_id');
});

it('changes and clears the route on edit, and keeps an existing one on an unrelated edit', function () {
    seedDefaultRoutePermissions();
    $actor = makeDefaultRouteActor();
    $old = VehicleRoute::create(['name' => '8']);
    $new = VehicleRoute::create(['name' => '8.1']);
    $payload = defaultRouteVehiclePayload(['default_route_id' => $old->id]);
    $vehicle = Vehicle::create($payload);

    $this->actingAs($actor)->put("/vehicles/{$vehicle->id}", [...$payload, 'capacity' => 12])
        ->assertSessionHasNoErrors();
    expect($vehicle->fresh()->default_route_id)->toBe($old->id);

    $this->actingAs($actor)->put("/vehicles/{$vehicle->id}", [...$payload, 'default_route_id' => $new->id])
        ->assertSessionHasNoErrors();
    expect($vehicle->fresh()->default_route_id)->toBe($new->id);

    $this->actingAs($actor)->put("/vehicles/{$vehicle->id}", [...$payload, 'default_route_id' => 'none'])
        ->assertSessionHasNoErrors();
    expect($vehicle->fresh()->default_route_id)->toBeNull();
});

it('clears the vehicle route when the route is deleted', function () {
    $route = VehicleRoute::create(['name' => 'All Employee']);
    $vehicle = Vehicle::create(['brand' => 'Toyota', 'model' => 'Hiace', 'registration_number' => 'RT-DEL', 'default_route_id' => $route->id]);

    $route->delete();

    expect($vehicle->fresh()->default_route_id)->toBeNull();
});

it('shows the route on the vehicle list and page', function () {
    seedDefaultRoutePermissions();
    $actor = makeDefaultRouteActor();
    $route = VehicleRoute::create(['name' => '17.1']);
    $vehicle = Vehicle::create(['brand' => 'Toyota', 'model' => 'Hiace', 'registration_number' => 'RT-LIST', 'default_route_id' => $route->id]);

    $this->actingAs($actor)->get('/vehicles')->assertOk()
        ->assertInertia(fn ($page) => $page->where('vehicles.data.0.default_route.name', '17.1'));

    $this->actingAs($actor)->get("/vehicles/{$vehicle->id}")->assertOk()
        ->assertInertia(fn ($page) => $page->where('vehicle.default_route.name', '17.1'));
});
