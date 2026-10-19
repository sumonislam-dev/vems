<?php

use App\Models\Department;
use App\Models\Logistics;
use App\Models\Stop;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Zone;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * ZoneController CRUD (index filters/sort, store/update validation, the
 * delete guard, toggle-status, permissions) and the navbar active-zone
 * switcher (ActiveZoneController + Zone::accessIds()). ZoneScopingTest
 * already covers a multi-zone user switching on /vehicles, the trips list
 * default filter, and the 403 for a zone outside a single-zone user's access.
 */
beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    foreach (['view-zones', 'create-zones', 'edit-zones', 'delete-zones', 'view-all-zones', 'view-vehicles', 'create-vehicles'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $this->dhaka = Zone::create(['name' => 'Dhaka', 'code' => 'DHK', 'is_active' => true]);
    $this->ctg = Zone::create(['name' => 'Chattogram', 'code' => 'CTG', 'is_active' => true]);
});

function zoneMgmtUser(array $permissions = [], ?Zone $zone = null): User
{
    $user = User::create([
        'email_verified_at' => now(),
        'name' => 'Zone Manager',
        'username' => 'zone_mgmt_'.uniqid(),
        'email' => 'zone_mgmt_'.uniqid().'@example.com',
        'user_type' => 'admin',
        'status' => 'active',
        'password' => Hash::make('password'),
        'zone_id' => $zone?->id,
    ]);
    $user->givePermissionTo($permissions);

    return $user;
}

function zoneMgmtVehicle(Zone $zone): Vehicle
{
    return Vehicle::create([
        'brand' => 'Toyota', 'model' => 'Hiace',
        'registration_number' => 'ZMGMT-'.uniqid(),
        'is_active' => true, 'zone_id' => $zone->id,
    ]);
}

// --- ZoneController ---------------------------------------------------------

it('lists zones sorted by name with their status, filter options and query params', function () {
    Zone::create(['name' => 'Sylhet', 'code' => 'SYL', 'is_active' => false]);

    $this->actingAs(zoneMgmtUser(['view-zones'], $this->dhaka))->get('/zones')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('zones/index')
            ->where('zones.data', fn ($zones) => collect($zones)->pluck('name')->all() === ['Chattogram', 'Dhaka', 'Sylhet'])
            ->where('zones.data.2.status', 'inactive')
            ->where('zones.data.2.is_active', false)
            ->where('zones.per_page', 10)
            ->where('filterOptions.statuses', ['active', 'inactive'])
        );
});

it('searches zones by name or code, filters by status and sorts', function () {
    Zone::create(['name' => 'Sylhet', 'code' => 'SYL', 'is_active' => false]);
    $viewer = zoneMgmtUser(['view-zones']);

    $this->actingAs($viewer)->get('/zones?search=ctg')
        ->assertInertia(fn ($page) => $page->where('zones.data', fn ($zones) => collect($zones)->pluck('code')->all() === ['CTG']));

    $this->actingAs($viewer)->get('/zones?filters[status][]=inactive')
        ->assertInertia(fn ($page) => $page
            ->where('zones.data', fn ($zones) => collect($zones)->pluck('code')->all() === ['SYL'])
            ->where('queryParams.filters.status', ['inactive'])
        );

    $this->actingAs($viewer)->get('/zones?sort=code&direction=desc')
        ->assertInertia(fn ($page) => $page->where('zones.data', fn ($zones) => collect($zones)->pluck('code')->all() === ['SYL', 'DHK', 'CTG']));
});

it('rejects invalid zone index parameters', function (string $query, string $field) {
    $this->actingAs(zoneMgmtUser(['view-zones']))->get('/zones?'.$query)->assertSessionHasErrors($field);
})->with([
    'sort column' => ['sort=password', 'sort'],
    'direction' => ['direction=sideways', 'direction'],
    'per page too large' => ['per_page=500', 'per_page'],
    'status value' => ['filters[status][]=archived', 'filters.status.0'],
]);

it('creates a zone', function () {
    $this->actingAs(zoneMgmtUser(['create-zones']))->post('/zones', [
        'name' => 'Sylhet', 'code' => 'SYL', 'is_active' => false,
    ])->assertRedirect(route('zones.index'))->assertSessionHas('success');

    $zone = Zone::where('code', 'SYL')->firstOrFail();
    expect($zone->name)->toBe('Sylhet')->and($zone->is_active)->toBeFalse();
});

it('validates a new zone', function (array $payload, array $errors) {
    $this->actingAs(zoneMgmtUser(['create-zones']))->post('/zones', $payload)->assertSessionHasErrors($errors);

    expect(Zone::count())->toBe(2);
})->with([
    'missing fields' => [[], ['name', 'code', 'is_active']],
    'duplicate name and code' => [['name' => 'Dhaka', 'code' => 'DHK', 'is_active' => true], ['name', 'code']],
    'code too long' => [['name' => 'Rajshahi', 'code' => 'RAJSHAHI-DIV', 'is_active' => true], ['code']],
    'non-boolean status' => [['name' => 'Rajshahi', 'code' => 'RAJ', 'is_active' => 'maybe'], ['is_active']],
]);

it('updates a zone, allowing it to keep its own name and code but not take another zone\'s', function () {
    $editor = zoneMgmtUser(['edit-zones']);

    $this->actingAs($editor)->put("/zones/{$this->dhaka->id}", [
        'name' => 'Dhaka', 'code' => 'DHK', 'is_active' => false,
    ])->assertRedirect(route('zones.index'))->assertSessionHasNoErrors();
    expect($this->dhaka->fresh()->is_active)->toBeFalse();

    $this->actingAs($editor)->put("/zones/{$this->dhaka->id}", [
        'name' => 'Chattogram', 'code' => 'CTG', 'is_active' => true,
    ])->assertSessionHasErrors(['name', 'code']);
    expect($this->dhaka->fresh()->name)->toBe('Dhaka');
});

it('renders the zone create and edit forms', function () {
    $editor = zoneMgmtUser(['create-zones', 'edit-zones']);

    $this->actingAs($editor)->get('/zones/create')->assertInertia(fn ($page) => $page->component('zones/create'));
    $this->actingAs($editor)->get("/zones/{$this->ctg->id}/edit")
        ->assertInertia(fn ($page) => $page->component('zones/edit')->where('zone.code', 'CTG'));
});

it('toggles a zone between active and inactive', function () {
    $editor = zoneMgmtUser(['edit-zones']);

    $this->actingAs($editor)->patch("/zones/{$this->ctg->id}/toggle-status")
        ->assertSessionHas('success', 'Zone deactivated successfully.');
    expect($this->ctg->fresh()->is_active)->toBeFalse();

    $this->actingAs($editor)->patch("/zones/{$this->ctg->id}/toggle-status")
        ->assertSessionHas('success', 'Zone activated successfully.');
    expect($this->ctg->fresh()->is_active)->toBeTrue();
});

it('deletes an empty zone', function () {
    $this->actingAs(zoneMgmtUser(['delete-zones', 'view-all-zones']))->delete("/zones/{$this->ctg->id}")
        ->assertRedirect(route('zones.index'));

    expect(Zone::find($this->ctg->id))->toBeNull();
});

it('refuses to delete a zone that still has records assigned', function (Closure $assign) {
    $assign($this->ctg);

    $this->actingAs(zoneMgmtUser(['delete-zones', 'view-all-zones']))->delete("/zones/{$this->ctg->id}")
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(Zone::find($this->ctg->id))->not->toBeNull();
})->with([
    'a user' => [fn (Zone $zone) => zoneMgmtUser([], $zone)],
    'a vehicle' => [fn (Zone $zone) => zoneMgmtVehicle($zone)],
    'a stop' => [fn (Zone $zone) => Stop::create(['name' => 'Agrabad', 'latitude' => 22.3, 'longitude' => 91.8, 'zone_id' => $zone->id])],
    'a logistics team' => [fn (Zone $zone) => Logistics::create(['name' => 'CTG Hub', 'department_id' => Department::create(['name' => 'Ops', 'code' => 'OPS'])->id, 'status' => true, 'zone_id' => $zone->id])],
]);

it('still refuses to delete an in-use zone while the switcher shows another zone', function () {
    $vehicle = zoneMgmtVehicle($this->ctg);
    $admin = zoneMgmtUser(['delete-zones', 'view-all-zones']);

    // Narrowed to Dhaka, the admin can't "see" Chattogram's vehicle
    $this->actingAs($admin)->patch('/active-zone', ['zone_id' => $this->dhaka->id]);
    $this->actingAs($admin)->delete("/zones/{$this->ctg->id}")->assertSessionHas('error');

    expect(Zone::find($this->ctg->id))->not->toBeNull()
        ->and(Vehicle::withoutGlobalScopes()->find($vehicle->id)->zone_id)->toBe($this->ctg->id);
});

it('forbids each zone action without its permission', function (string $method, string $uri, array $granted) {
    $uri = str_replace('{id}', (string) $this->ctg->id, $uri);

    $this->actingAs(zoneMgmtUser($granted))
        ->call(strtoupper($method), $uri, ['name' => 'Changed', 'code' => 'CHG', 'is_active' => false])
        ->assertForbidden();

    expect(Zone::count())->toBe(2)
        ->and($this->ctg->fresh()->name)->toBe('Chattogram')
        ->and($this->ctg->fresh()->is_active)->toBeTrue();
})->with([
    'index' => ['get', '/zones', ['create-zones', 'edit-zones', 'delete-zones']],
    'create form' => ['get', '/zones/create', ['view-zones']],
    'store' => ['post', '/zones', ['view-zones', 'edit-zones']],
    'edit form' => ['get', '/zones/{id}/edit', ['view-zones', 'create-zones']],
    'update' => ['put', '/zones/{id}', ['view-zones', 'create-zones']],
    'toggle' => ['patch', '/zones/{id}/toggle-status', ['view-zones']],
    'destroy' => ['delete', '/zones/{id}', ['view-zones', 'edit-zones']],
]);

// --- Active zone switcher ---------------------------------------------------

it('lets a view-all-zones user switch to any zone, narrowing vehicles and sharing the selection', function () {
    $dhakaVehicle = zoneMgmtVehicle($this->dhaka);
    $ctgVehicle = zoneMgmtVehicle($this->ctg);
    $admin = zoneMgmtUser(['view-vehicles', 'view-all-zones'], $this->dhaka);

    $this->actingAs($admin)->from('/vehicles')->patch('/active-zone', ['zone_id' => $this->ctg->id])
        ->assertRedirect('/vehicles')
        ->assertSessionHas('active_zone_id', $this->ctg->id)
        ->assertSessionHas('success', 'Now viewing Chattogram only.');

    $this->actingAs($admin)->get('/vehicles')->assertInertia(fn ($page) => $page
        ->where('auth.activeZoneId', $this->ctg->id)
        ->where('auth.availableZones', fn ($zones) => collect($zones)->count() === 2)
        ->where('vehicles.data', fn ($vehicles) => collect($vehicles)->pluck('id')->all() === [$ctgVehicle->id])
    );

    $this->actingAs($admin)->patch('/active-zone', ['zone_id' => null])
        ->assertSessionMissing('active_zone_id')
        ->assertSessionHas('success', 'Now viewing all your zones.');

    $this->actingAs($admin)->get('/vehicles')->assertInertia(fn ($page) => $page
        ->where('auth.activeZoneId', null)
        ->where('vehicles.data', fn ($vehicles) => collect($vehicles)->pluck('id')->sort()->values()->all()
            === collect([$dhakaVehicle->id, $ctgVehicle->id])->sort()->values()->all())
    );
});

it('narrows zone dropdowns and zone_id validation to the active zone', function () {
    $sylhet = Zone::create(['name' => 'Sylhet', 'code' => 'SYL', 'is_active' => true]);
    $viewer = zoneMgmtUser(['create-vehicles']);
    $viewer->accessZones()->sync([$this->dhaka->id, $this->ctg->id]);

    $this->actingAs($viewer)->patch('/active-zone', ['zone_id' => $this->ctg->id])->assertSessionHasNoErrors();

    expect(Zone::accessIds($viewer))->toBe([$this->ctg->id])
        ->and(Zone::realAccessIds($viewer))->toEqualCanonicalizing([$this->dhaka->id, $this->ctg->id])
        ->and(Zone::selectableBy($viewer)->pluck('id')->all())->toBe([$this->ctg->id])
        ->and(Zone::selectableBy($viewer, $sylhet->id)->pluck('id')->sort()->values()->all())
        ->toBe(collect([$this->ctg->id, $sylhet->id])->sort()->values()->all())
        ->and(validator(['zone_id' => $this->dhaka->id], ['zone_id' => Zone::validationRule($viewer)])->fails())->toBeTrue()
        ->and(validator(['zone_id' => $this->ctg->id], ['zone_id' => Zone::validationRule($viewer)])->passes())->toBeTrue();

    $this->actingAs($viewer)->get('/vehicles/create')->assertInertia(fn ($page) => $page
        ->where('zones', fn ($zones) => collect($zones)->pluck('id')->all() === [$this->ctg->id])
    );
});

it('ignores a stale active zone that is no longer in the user\'s access', function () {
    $viewer = zoneMgmtUser([]);
    $viewer->accessZones()->sync([$this->dhaka->id, $this->ctg->id]);

    $this->actingAs($viewer)->patch('/active-zone', ['zone_id' => $this->ctg->id]);
    expect(Zone::accessIds($viewer))->toBe([$this->ctg->id]);

    // Chattogram access is revoked while the session still holds it.
    $viewer->accessZones()->sync([$this->dhaka->id]);

    expect(Zone::accessIds($viewer))->toBe([$this->dhaka->id]);
});

it('rejects a multi-zone user switching to a zone outside their grant, or one that does not exist', function () {
    $sylhet = Zone::create(['name' => 'Sylhet', 'code' => 'SYL', 'is_active' => true]);
    $viewer = zoneMgmtUser([]);
    $viewer->accessZones()->sync([$this->dhaka->id, $this->ctg->id]);

    $this->actingAs($viewer)->patch('/active-zone', ['zone_id' => $sylhet->id])->assertForbidden();
    $this->actingAs($viewer)->patch('/active-zone', ['zone_id' => 999999])->assertSessionHasErrors('zone_id');
    $this->actingAs($viewer)->patch('/active-zone', ['zone_id' => 'dhaka'])->assertSessionHasErrors('zone_id');

    expect(session('active_zone_id'))->toBeNull();
});

it('requires login to switch the active zone', function () {
    $this->patch('/active-zone', ['zone_id' => $this->dhaka->id])->assertRedirect(route('login'));
});
