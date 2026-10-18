<?php

use App\Models\RouteStop;
use App\Models\Stop;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleRoute;
use App\Models\Zone;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * VehicleRouteController: the /routes resource. A route has ordered
 * RouteStop rows (stop_order, distance_from_previous, cumulative_distance)
 * that store/update rebuild from the submitted `stops` array.
 */
function vrcTestUser(array $permissions = [], ?Zone $zone = null): User
{
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    foreach (['view-routes', 'create-routes', 'edit-routes', 'delete-routes', 'view-all-zones'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $user = User::create([
        'email_verified_at' => now(),
        'name' => 'Route Ctl User',
        'username' => 'vrc_user_'.uniqid(),
        'email' => 'vrc_user_'.uniqid().'@example.com',
        'user_type' => 'employee',
        'status' => 'active',
        'zone_id' => $zone?->id,
        'password' => Hash::make('password'),
    ]);
    if ($permissions) {
        $user->givePermissionTo($permissions);
    }

    return $user;
}

function vrcTestAllPermissions(): array
{
    return ['view-routes', 'create-routes', 'edit-routes', 'delete-routes'];
}

it('renders the index with routes, stats and search filtering', function () {
    $stopA = Stop::create(['name' => 'Gulshan', 'latitude' => 23.79, 'longitude' => 90.41]);
    $stopB = Stop::create(['name' => 'Banani', 'latitude' => 23.80, 'longitude' => 90.40]);
    $morning = VehicleRoute::create(['name' => 'Morning Shuttle', 'description' => 'Staff pick-up']);
    VehicleRoute::create(['name' => 'Evening Drop', 'remarks' => 'shuttle back home']);
    VehicleRoute::create(['name' => 'Airport Run']);
    RouteStop::create(['vehicle_route_id' => $morning->id, 'stop_id' => $stopA->id, 'stop_order' => 1]);
    RouteStop::create(['vehicle_route_id' => $morning->id, 'stop_id' => $stopB->id, 'stop_order' => 2]);

    $user = vrcTestUser(['view-routes']);

    $this->actingAs($user)->get('/routes')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('routes/index')
            ->has('routes.data', 3)
            ->where('stats.total', 3)
            ->where('stats.total_stops', 2)
            ->where('stats.routes_with_stops', 1)
            ->where('stats.avg_stops_per_route', 0.7)
        );

    // search matches name, description or remarks
    $this->actingAs($user)->get('/routes?search=shuttle')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('routes/index')
            ->has('routes.data', 2)
            ->where('queryParams.search', 'shuttle')
        );

    $this->actingAs($user)->get('/routes?search=Airport')
        ->assertInertia(fn ($page) => $page->has('routes.data', 1)
            ->where('routes.data.0.name', 'Airport Run')
            ->where('routes.data.0.route_stops_count', 0)
        );
});

it('sorts and paginates the index', function () {
    foreach (['Charlie', 'Alpha', 'Bravo'] as $name) {
        VehicleRoute::create(['name' => $name]);
    }

    $this->actingAs(vrcTestUser(['view-routes']))
        ->get('/routes?sort=name&direction=asc&per_page=2')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('routes.data', 2)
            ->where('routes.data.0.name', 'Alpha')
            ->where('routes.data.1.name', 'Bravo')
            ->where('routes.total', 3)
        );
});

it('shows a route with its stops', function () {
    $route = VehicleRoute::create(['name' => 'Show Route']);
    $stop = Stop::create(['name' => 'Mohakhali']);
    RouteStop::create(['vehicle_route_id' => $route->id, 'stop_id' => $stop->id, 'stop_order' => 1]);

    $this->actingAs(vrcTestUser(['view-routes']))
        ->get("/routes/{$route->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('routes/show')
            ->where('route.name', 'Show Route')
            ->where('route.route_stops.0.stop.name', 'Mohakhali')
        );
});

it('renders the create and edit forms with stops and zones', function () {
    $zone = Zone::create(['name' => 'Dhaka', 'code' => 'DHK', 'is_active' => true]);
    Stop::create(['name' => 'Zeta Stop', 'zone_id' => $zone->id]);
    Stop::create(['name' => 'Alpha Stop', 'zone_id' => $zone->id]);
    $route = VehicleRoute::create(['name' => 'Editable']);
    $user = vrcTestUser(vrcTestAllPermissions(), $zone);

    $this->actingAs($user)->get('/routes/create')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('routes/create')
            ->where('stops.0.name', 'Alpha Stop')
            ->where('zones.0.id', $zone->id)
            ->where('defaultZoneId', $zone->id)
        );

    // a zoned user only reaches routes in their zone (BelongsToZone)
    $route->update(['zone_id' => $zone->id]);

    $this->actingAs($user)->get("/routes/{$route->id}/edit")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('routes/edit')
            ->where('route.name', 'Editable')
            ->has('stops', 2)
        );
});

it('stores a route with stops in order and per-leg distances', function () {
    $a = Stop::create(['name' => 'Stop A', 'latitude' => 23.80, 'longitude' => 90.40]);
    $b = Stop::create(['name' => 'Stop B', 'latitude' => 23.81, 'longitude' => 90.40]);
    $c = Stop::create(['name' => 'Stop C']); // no coordinates -> manual distance

    $this->actingAs(vrcTestUser(['create-routes']))
        ->post('/routes', [
            'name' => 'Factory Line',
            'description' => 'Main shift route',
            'stops' => [
                ['stop_id' => $a->id, 'arrival_time' => '07:00', 'departure_time' => '07:05'],
                ['stop_id' => $b->id],
                ['stop_id' => $c->id, 'manual_distance' => 2.5],
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('routes.index'))
        ->assertSessionHas('success');

    $route = VehicleRoute::where('name', 'Factory Line')->firstOrFail();
    $legs = $route->routeStops()->orderBy('stop_order')->get();

    expect($legs->pluck('stop_id')->all())->toBe([$a->id, $b->id, $c->id])
        ->and($legs->pluck('stop_order')->all())->toBe([1, 2, 3])
        ->and((float) $legs[0]->distance_from_previous)->toBe(0.0)
        ->and((float) $legs[1]->distance_from_previous)->toBe(1.11) // haversine of 0.01 deg latitude
        ->and((float) $legs[2]->distance_from_previous)->toBe(2.5)
        ->and((float) $legs[2]->cumulative_distance)->toBe(3.61)
        ->and(substr((string) $legs[0]->arrival_time, 0, 5))->toBe('07:00')
        ->and((float) $route->total_distance)->toBe(3.61);
});

it('stores a route without stops', function () {
    $this->actingAs(vrcTestUser(['create-routes']))
        ->post('/routes', ['name' => 'Empty Route'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('routes.index'));

    $route = VehicleRoute::where('name', 'Empty Route')->firstOrFail();
    expect($route->routeStops()->count())->toBe(0);
});

it('validates the route payload', function (array $payload, string $errorKey) {
    $stop = Stop::create(['name' => 'Valid Stop']);
    $payload = json_decode(str_replace('__STOP__', (string) $stop->id, json_encode($payload)), true);

    $this->actingAs(vrcTestUser(['create-routes']))
        ->post('/routes', $payload)
        ->assertSessionHasErrors($errorKey);

    expect(VehicleRoute::count())->toBe(0);
})->with([
    'missing name' => [['stops' => [['stop_id' => '__STOP__']]], 'name'],
    'name too long' => [['name' => str_repeat('x', 256)], 'name'],
    'unknown stop' => [['name' => 'R', 'stops' => [['stop_id' => 999999]]], 'stops.0.stop_id'],
    'missing stop id' => [['name' => 'R', 'stops' => [['arrival_time' => '07:00']]], 'stops.0.stop_id'],
    'bad arrival time' => [['name' => 'R', 'stops' => [['stop_id' => '__STOP__', 'arrival_time' => '7am']]], 'stops.0.arrival_time'],
    'negative manual distance' => [['name' => 'R', 'stops' => [['stop_id' => '__STOP__', 'manual_distance' => -1]]], 'stops.0.manual_distance'],
    'unknown zone' => [['name' => 'R', 'zone_id' => 999999], 'zone_id'],
]);

it('replaces and reorders stops on update and recomputes the total', function () {
    $a = Stop::create(['name' => 'A']);
    $b = Stop::create(['name' => 'B']);
    $c = Stop::create(['name' => 'C']);
    $route = VehicleRoute::create(['name' => 'Old Name', 'total_distance' => 10]);
    RouteStop::create(['vehicle_route_id' => $route->id, 'stop_id' => $a->id, 'stop_order' => 1]);
    RouteStop::create(['vehicle_route_id' => $route->id, 'stop_id' => $b->id, 'stop_order' => 2]);

    $user = vrcTestUser(['edit-routes']);

    $this->actingAs($user)->put("/routes/{$route->id}", [
        'name' => 'New Name',
        'stops' => [
            ['stop_id' => $c->id],
            ['stop_id' => $a->id, 'manual_distance' => 4],
        ],
    ])->assertSessionHasNoErrors()->assertRedirect(route('routes.index'));

    $route->refresh();
    $legs = $route->routeStops()->orderBy('stop_order')->get();

    expect($route->name)->toBe('New Name')
        ->and($legs->pluck('stop_id')->all())->toBe([$c->id, $a->id])
        ->and((float) $route->total_distance)->toBe(4.0)
        ->and(RouteStop::where('stop_id', $b->id)->exists())->toBeFalse();

    // submitting no stops clears them and zeroes the distance
    $this->actingAs($user)->put("/routes/{$route->id}", ['name' => 'New Name'])
        ->assertSessionHasNoErrors();

    expect($route->routeStops()->count())->toBe(0)
        ->and((float) $route->fresh()->total_distance)->toBe(0.0);
});

it('validates the update payload and leaves the route untouched', function () {
    $route = VehicleRoute::create(['name' => 'Keep Me']);

    $this->actingAs(vrcTestUser(['edit-routes']))
        ->put("/routes/{$route->id}", ['name' => ''])
        ->assertSessionHasErrors('name');

    expect($route->fresh()->name)->toBe('Keep Me');
});

it('deletes a route with its stops and clears it from vehicles using it as default', function () {
    $stop = Stop::create(['name' => 'Kept Stop']);
    $route = VehicleRoute::create(['name' => 'Doomed']);
    RouteStop::create(['vehicle_route_id' => $route->id, 'stop_id' => $stop->id, 'stop_order' => 1]);
    $vehicle = Vehicle::create([
        'brand' => 'Toyota', 'model' => 'Hiace',
        'registration_number' => 'VRC-'.uniqid(), 'default_route_id' => $route->id,
    ]);

    $this->actingAs(vrcTestUser(['delete-routes']))
        ->delete("/routes/{$route->id}")
        ->assertRedirect(route('routes.index'))
        ->assertSessionHas('success');

    expect(VehicleRoute::find($route->id))->toBeNull()
        ->and(RouteStop::where('vehicle_route_id', $route->id)->exists())->toBeFalse()
        ->and(Stop::find($stop->id))->not->toBeNull()
        ->and($vehicle->fresh()->default_route_id)->toBeNull();
});

it('should refuse to delete a route that trips are assigned to (history is cascaded away today)')
    ->todo();

it('should scope the avg-stops-per-route stat to the viewer zone (RouteStop::count() is unscoped today)')
    ->todo();

it('enforces route permissions on every action', function () {
    $route = VehicleRoute::create(['name' => 'Guarded']);
    $user = vrcTestUser();

    $this->actingAs($user)->get('/routes')->assertForbidden();
    $this->actingAs($user)->get("/routes/{$route->id}")->assertForbidden();
    $this->actingAs($user)->get('/routes/create')->assertForbidden();
    $this->actingAs($user)->post('/routes', ['name' => 'Nope'])->assertForbidden();
    $this->actingAs($user)->get("/routes/{$route->id}/edit")->assertForbidden();
    $this->actingAs($user)->put("/routes/{$route->id}", ['name' => 'Nope'])->assertForbidden();
    $this->actingAs($user)->delete("/routes/{$route->id}")->assertForbidden();

    // view-only cannot write
    $viewer = vrcTestUser(['view-routes']);
    $this->actingAs($viewer)->get('/routes')->assertOk();
    $this->actingAs($viewer)->delete("/routes/{$route->id}")->assertForbidden();

    expect(VehicleRoute::where('name', 'Nope')->exists())->toBeFalse()
        ->and(VehicleRoute::find($route->id)->name)->toBe('Guarded');
});

it('redirects guests to login', function () {
    $this->get('/routes')->assertRedirect('/login');
});

it('hides routes of other zones from a zoned user', function () {
    $dhaka = Zone::create(['name' => 'Dhaka', 'code' => 'DHK', 'is_active' => true]);
    $ctg = Zone::create(['name' => 'Chattogram', 'code' => 'CTG', 'is_active' => true]);
    VehicleRoute::create(['name' => 'Dhaka Route', 'zone_id' => $dhaka->id]);
    $ctgRoute = VehicleRoute::create(['name' => 'CTG Route', 'zone_id' => $ctg->id]);

    $user = vrcTestUser(vrcTestAllPermissions(), $dhaka);

    $this->actingAs($user)->get('/routes')
        ->assertInertia(fn ($page) => $page->has('routes.data', 1)
            ->where('routes.data.0.name', 'Dhaka Route')
            ->where('stats.total', 1)
        );

    $this->actingAs($user)->get("/routes/{$ctgRoute->id}")->assertNotFound();
    $this->actingAs($user)->put("/routes/{$ctgRoute->id}", ['name' => 'Hijacked'])->assertNotFound();
    $this->actingAs($user)->delete("/routes/{$ctgRoute->id}")->assertNotFound();

    // a zoned user can't file a new route under a zone they can't access
    $this->actingAs($user)->post('/routes', ['name' => 'Sneaky', 'zone_id' => $ctg->id])
        ->assertSessionHasErrors('zone_id');

    expect(VehicleRoute::withoutGlobalScopes()->find($ctgRoute->id)->name)->toBe('CTG Route');
});
