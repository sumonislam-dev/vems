<?php

use App\Models\RouteStop;
use App\Models\Stop;
use App\Models\Trip;
use App\Models\TripPassenger;
use App\Models\User;
use App\Models\VehicleRoute;
use App\Models\Zone;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * StopController's admin CRUD (index/create/edit/update/destroy, the
 * delete-in-use guard) plus a regression check that renaming the inline
 * JSON endpoints to apiIndex/apiStore didn't change their behavior or
 * permission gating (see GeocodingControllerTest and MapStopPicker.tsx for
 * how those are consumed).
 */
beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    foreach (['view-stops', 'create-stops', 'edit-stops', 'delete-stops'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
});

function stopMgmtUser(array $permissions = []): User
{
    $user = User::create([
        'email_verified_at' => now(),
        'name' => 'Stop Manager',
        'username' => 'stop_mgmt_'.uniqid(),
        'email' => 'stop_mgmt_'.uniqid().'@example.com',
        'user_type' => 'admin',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);
    $user->givePermissionTo($permissions);

    return $user;
}

it('lists stops with their zone and route usage count', function () {
    $zone = Zone::create(['name' => 'Dhaka', 'code' => 'DHK', 'is_active' => true]);
    $stop = Stop::create(['name' => 'Gulshan Circle', 'zone_id' => $zone->id]);
    $route = VehicleRoute::create(['name' => 'Route A']);
    RouteStop::create(['vehicle_route_id' => $route->id, 'stop_id' => $stop->id, 'stop_order' => 1]);

    $this->actingAs(stopMgmtUser(['view-stops']))->get('/stops')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('stops/index')
            ->where('stops.data.0.name', 'Gulshan Circle')
            ->where('stops.data.0.zone.name', 'Dhaka')
            ->where('stops.data.0.route_stops_count', 1)
        );
});

it('creates a stop with validation', function () {
    $user = stopMgmtUser(['create-stops']);

    $this->actingAs($user)->post('/stops', ['name' => ''])->assertSessionHasErrors('name');

    $this->actingAs($user)->post('/stops', [
        'name' => 'New Stop',
        'latitude' => 23.81,
        'longitude' => 90.41,
    ])->assertRedirect('/stops');

    expect(Stop::where('name', 'New Stop')->exists())->toBeTrue();
});

it('updates a stop, including clearing its zone', function () {
    $zone = Zone::create(['name' => 'Dhaka', 'code' => 'DHK', 'is_active' => true]);
    $stop = Stop::create(['name' => 'Old Name', 'zone_id' => $zone->id]);

    $this->actingAs(stopMgmtUser(['edit-stops']))->put("/stops/{$stop->id}", [
        'name' => 'Renamed Stop',
        'zone_id' => '',
    ])->assertRedirect('/stops');

    $stop->refresh();
    expect($stop->name)->toBe('Renamed Stop');
    expect($stop->zone_id)->toBeNull();
});

it('deletes an unused stop', function () {
    $stop = Stop::create(['name' => 'Unused Stop']);

    $this->actingAs(stopMgmtUser(['delete-stops']))->delete("/stops/{$stop->id}")
        ->assertRedirect('/stops');

    expect(Stop::find($stop->id))->toBeNull();
});

it('refuses to delete a stop still used by a route', function () {
    $stop = Stop::create(['name' => 'Busy Stop']);
    $route = VehicleRoute::create(['name' => 'Route A']);
    RouteStop::create(['vehicle_route_id' => $route->id, 'stop_id' => $stop->id, 'stop_order' => 1]);

    $this->actingAs(stopMgmtUser(['delete-stops']))->delete("/stops/{$stop->id}")
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(Stop::find($stop->id))->not->toBeNull();
});

it('refuses to delete a stop still used as a trip passenger pickup/dropoff point', function () {
    $stop = Stop::create(['name' => 'Pickup Stop']);
    $trip = Trip::factory()->create([
        'requested_by' => stopMgmtUser()->id,
        'trip_type' => 'pick-up',
        'scheduled_date' => now()->addDay()->toDateString(),
        'scheduled_start_time' => '08:00',
        'scheduled_end_time' => '09:00',
    ]);
    TripPassenger::create([
        'trip_id' => $trip->id,
        'user_id' => stopMgmtUser()->id,
        'pickup_stop_id' => $stop->id,
        'status' => 'pending',
    ]);

    $this->actingAs(stopMgmtUser(['delete-stops']))->delete("/stops/{$stop->id}")
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(Stop::find($stop->id))->not->toBeNull();
});

it('requires the matching permission for each stop management action', function () {
    $stop = Stop::create(['name' => 'Gated Stop']);
    $noPerms = stopMgmtUser([]);

    $this->actingAs($noPerms)->get('/stops')->assertForbidden();
    $this->actingAs($noPerms)->get('/stops/create')->assertForbidden();
    $this->actingAs($noPerms)->post('/stops', ['name' => 'x'])->assertForbidden();
    $this->actingAs($noPerms)->get("/stops/{$stop->id}/edit")->assertForbidden();
    $this->actingAs($noPerms)->put("/stops/{$stop->id}", ['name' => 'x'])->assertForbidden();
    $this->actingAs($noPerms)->delete("/stops/{$stop->id}")->assertForbidden();
});

// --- Inline JSON endpoints (regression: apiIndex/apiStore rename) ----------

it('still serves the inline stops lookup used by searchable selects and the map picker', function () {
    Stop::create(['name' => 'Lookup Stop']);

    $this->actingAs(stopMgmtUser(['view-stops']))->getJson('/api/stops')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Lookup Stop');
});

it('still creates a stop inline via the JSON endpoint used by the "create new stop" flows', function () {
    $this->actingAs(stopMgmtUser(['create-stops']))->postJson('/api/stops', ['name' => 'Inline Stop'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Inline Stop');
});
