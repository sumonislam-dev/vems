<?php

use App\Models\Stop;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * StopController: the JSON lookup/create endpoints at /api/stops used by the
 * route builder's stop picker (no Inertia pages, no search parameter).
 */
function stopCtlTestUser(array $permissions = [], ?Zone $zone = null): User
{
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    foreach (['view-stops', 'create-stops'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $user = User::create([
        'email_verified_at' => now(),
        'name' => 'Stop Ctl User',
        'username' => 'stopctl_'.uniqid(),
        'email' => 'stopctl_'.uniqid().'@example.com',
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

it('lists stops ordered by name with only the lookup fields', function () {
    Stop::create(['name' => 'Uttara', 'description' => 'North', 'latitude' => 23.87, 'longitude' => 90.40]);
    Stop::create(['name' => 'Badda', 'latitude' => 23.78, 'longitude' => 90.42]);

    $response = $this->actingAs(stopCtlTestUser(['view-stops']))
        ->getJson('/api/stops')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Badda')
        ->assertJsonPath('data.1.name', 'Uttara')
        ->assertJsonPath('data.1.description', 'North')
        ->assertJsonPath('data.1.latitude', 23.87);

    expect(array_keys($response->json('data.0')))
        ->toEqualCanonicalizing(['id', 'name', 'description', 'latitude', 'longitude', 'zone_id']);
});

it('returns an empty list when there are no stops', function () {
    $this->actingAs(stopCtlTestUser(['view-stops']))
        ->getJson('/api/stops')
        ->assertOk()
        ->assertExactJson(['success' => true, 'data' => []]);
});

it('requires authentication for the stop lookup and create endpoints', function () {
    $this->getJson('/api/stops')->assertUnauthorized();
    $this->postJson('/api/stops', ['name' => 'Anon'])->assertUnauthorized();

    expect(Stop::count())->toBe(0);
});

it('requires view-stops to list and create-stops to create', function () {
    $nobody = stopCtlTestUser();
    $this->actingAs($nobody)->getJson('/api/stops')->assertForbidden();
    $this->actingAs($nobody)->postJson('/api/stops', ['name' => 'Nope'])->assertForbidden();

    // view alone cannot create, create alone cannot list
    $this->actingAs(stopCtlTestUser(['view-stops']))
        ->postJson('/api/stops', ['name' => 'Nope'])->assertForbidden();
    $this->actingAs(stopCtlTestUser(['create-stops']))
        ->getJson('/api/stops')->assertForbidden();

    expect(Stop::count())->toBe(0);
});

it('creates a stop and returns it as JSON', function () {
    $zone = Zone::create(['name' => 'Dhaka', 'code' => 'DHK', 'is_active' => true]);

    $this->actingAs(stopCtlTestUser(['create-stops']))
        ->postJson('/api/stops', [
            'name' => 'Farmgate',
            'description' => 'Near the overpass',
            'latitude' => 23.7577,
            'longitude' => 90.3897,
            'zone_id' => $zone->id,
        ])
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'Farmgate')
        ->assertJsonPath('data.latitude', 23.7577);

    $stop = Stop::where('name', 'Farmgate')->firstOrFail();
    expect($stop->longitude)->toBe(90.3897)
        ->and($stop->zone_id)->toBe($zone->id)
        ->and($stop->description)->toBe('Near the overpass');
});

it('creates a stop with only a name', function () {
    $this->actingAs(stopCtlTestUser(['create-stops']))
        ->postJson('/api/stops', ['name' => 'Name Only'])
        ->assertCreated();

    $stop = Stop::where('name', 'Name Only')->firstOrFail();
    expect($stop->latitude)->toBeNull()
        ->and($stop->longitude)->toBeNull()
        ->and($stop->zone_id)->toBeNull();
});

it('validates new stops', function (array $payload, string $errorKey) {
    Stop::create(['name' => 'Taken']);

    $this->actingAs(stopCtlTestUser(['create-stops']))
        ->postJson('/api/stops', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($errorKey);

    expect(Stop::count())->toBe(1);
})->with([
    'missing name' => [['latitude' => 23.8], 'name'],
    'name too long' => [['name' => str_repeat('s', 256)], 'name'],
    'duplicate name' => [['name' => 'Taken'], 'name'],
    'description too long' => [['name' => 'D', 'description' => str_repeat('d', 501)], 'description'],
    'latitude above 90' => [['name' => 'L', 'latitude' => 90.5], 'latitude'],
    'latitude below -90' => [['name' => 'L', 'latitude' => -91], 'latitude'],
    'longitude above 180' => [['name' => 'L', 'longitude' => 180.1], 'longitude'],
    'non-numeric longitude' => [['name' => 'L', 'longitude' => 'east'], 'longitude'],
    'unknown zone' => [['name' => 'Z', 'zone_id' => 999999], 'zone_id'],
]);

it('accepts the coordinate boundaries', function () {
    $this->actingAs(stopCtlTestUser(['create-stops']))
        ->postJson('/api/stops', ['name' => 'Edge', 'latitude' => -90, 'longitude' => 180])
        ->assertCreated();

    expect(Stop::where('name', 'Edge')->exists())->toBeTrue();
});

it('scopes the lookup and new stops to a zoned user zone', function () {
    $dhaka = Zone::create(['name' => 'Dhaka', 'code' => 'DHK', 'is_active' => true]);
    $ctg = Zone::create(['name' => 'Chattogram', 'code' => 'CTG', 'is_active' => true]);
    Stop::create(['name' => 'Dhaka Stop', 'zone_id' => $dhaka->id]);
    Stop::create(['name' => 'CTG Stop', 'zone_id' => $ctg->id]);

    $user = stopCtlTestUser(['view-stops', 'create-stops'], $dhaka);

    $this->actingAs($user)->getJson('/api/stops')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Dhaka Stop');

    $this->actingAs($user)
        ->postJson('/api/stops', ['name' => 'Sneaky CTG Stop', 'zone_id' => $ctg->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('zone_id');

    $this->actingAs($user)
        ->postJson('/api/stops', ['name' => 'New Dhaka Stop', 'zone_id' => $dhaka->id])
        ->assertCreated();

    expect(Stop::withoutGlobalScopes()->where('name', 'Sneaky CTG Stop')->exists())->toBeFalse()
        ->and(Stop::withoutGlobalScopes()->where('name', 'New Dhaka Stop')->value('zone_id'))->toBe($dhaka->id);
});
