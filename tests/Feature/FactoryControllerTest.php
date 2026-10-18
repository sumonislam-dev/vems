<?php

use App\Models\Factory;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * FactoryController: listing with search/filters/sort validation, create and
 * update via FactoryStoreRequest/FactoryUpdateRequest (unique account_id,
 * coordinate ranges), delete, per-action permissions and zone scoping.
 */
function factoryTestSeedPermissions(): void
{
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    foreach (['view-factories', 'create-factories', 'edit-factories', 'delete-factories', 'view-all-zones'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
}

function factoryTestUser(array $permissions = [], ?Zone $zone = null): User
{
    $user = User::create([
        'email_verified_at' => now(),
        'name' => 'Factory Test User',
        'username' => 'factory_test_'.uniqid(),
        'email' => 'factory_test_'.uniqid().'@example.com',
        'user_type' => 'employee',
        'status' => 'active',
        'password' => Hash::make('password'),
        'zone_id' => $zone?->id,
    ]);
    $user->givePermissionTo($permissions);

    return $user;
}

function factoryTestMake(array $attributes = []): Factory
{
    return Factory::create(array_merge([
        'account_id' => 'ACC-'.uniqid(),
        'name' => 'Factory '.uniqid(),
        'status' => 'active',
    ], $attributes));
}

function factoryTestPayload(array $overrides = []): array
{
    return array_merge([
        'account_id' => 'ACC-NEW-1',
        'name' => 'Gazipur Plant',
        'status' => 'active',
        'address' => 'Plot 1, BSCIC',
        'city' => 'Gazipur',
        'latitude' => 23.9999,
        'longitude' => 90.4203,
        'mileage_km' => 35.5,
    ], $overrides);
}

beforeEach(function () {
    factoryTestSeedPermissions();
});

// --- index / show -------------------------------------------------------

it('lists factories with stats and filter options', function () {
    factoryTestMake(['name' => 'Alpha', 'city' => 'Dhaka', 'status' => 'active']);
    factoryTestMake(['name' => 'Beta', 'city' => 'Gazipur', 'status' => 'inactive']);

    $this->actingAs(factoryTestUser(['view-factories']))->get('/factories')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('factories/index')
            ->where('factories.data', fn ($rows) => collect($rows)->pluck('name')->all() === ['Alpha', 'Beta'])
            ->where('stats.total', 2)
            ->where('stats.active', 1)
            ->where('stats.inactive', 1)
            ->where('stats.cities', 2)
            ->where('filterOptions.cities', ['Dhaka', 'Gazipur'])
        );
});

it('searches and filters factories', function () {
    $alpha = factoryTestMake(['name' => 'Alpha', 'account_id' => 'ACC-ALPHA', 'city' => 'Dhaka', 'status' => 'active']);
    $beta = factoryTestMake(['name' => 'Beta', 'city' => 'Gazipur', 'status' => 'inactive']);
    $viewer = factoryTestUser(['view-factories']);

    $this->actingAs($viewer)->get('/factories?search=ACC-ALPHA')->assertInertia(fn ($page) => $page
        ->where('factories.data', fn ($rows) => collect($rows)->pluck('id')->all() === [$alpha->id])
    );

    $this->actingAs($viewer)->get('/factories?'.http_build_query(['filters' => ['status' => ['inactive']]]))
        ->assertInertia(fn ($page) => $page
            ->where('factories.data', fn ($rows) => collect($rows)->pluck('id')->all() === [$beta->id])
        );

    $this->actingAs($viewer)->get('/factories?'.http_build_query(['filters' => ['city' => ['Dhaka']]]))
        ->assertInertia(fn ($page) => $page
            ->where('factories.data', fn ($rows) => collect($rows)->pluck('id')->all() === [$alpha->id])
        );

    $this->actingAs($viewer)->get('/factories?sort=name&direction=desc')->assertInertia(fn ($page) => $page
        ->where('factories.data', fn ($rows) => collect($rows)->pluck('id')->all() === [$beta->id, $alpha->id])
    );
});

it('rejects an unknown sort column or page size on the index', function () {
    $viewer = factoryTestUser(['view-factories']);

    $this->actingAs($viewer)->get('/factories?sort=password')->assertSessionHasErrors('sort');
    $this->actingAs($viewer)->get('/factories?per_page=7')->assertSessionHasErrors('per_page');
});

it('shows a factory and its edit form', function () {
    $factory = factoryTestMake(['name' => 'Show Me']);

    $this->actingAs(factoryTestUser(['view-factories']))->get("/factories/{$factory->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('factories/show')->where('factory.name', 'Show Me'));

    $this->actingAs(factoryTestUser(['edit-factories']))->get("/factories/{$factory->id}/edit")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('factories/edit')->where('factory.id', $factory->id)->has('zones'));
});

// --- permissions --------------------------------------------------------

it('requires the matching factory permission for every action', function () {
    $factory = factoryTestMake();
    $nobody = factoryTestUser();

    $this->actingAs($nobody)->get('/factories')->assertForbidden();
    $this->actingAs($nobody)->get("/factories/{$factory->id}")->assertForbidden();
    $this->actingAs($nobody)->get('/factories/create')->assertForbidden();
    $this->actingAs($nobody)->post('/factories', factoryTestPayload())->assertForbidden();
    $this->actingAs($nobody)->get("/factories/{$factory->id}/edit")->assertForbidden();
    $this->actingAs($nobody)->put("/factories/{$factory->id}", factoryTestPayload())->assertForbidden();
    $this->actingAs($nobody)->delete("/factories/{$factory->id}")->assertForbidden();

    // view-factories alone does not grant write access
    $viewer = factoryTestUser(['view-factories']);
    $this->actingAs($viewer)->post('/factories', factoryTestPayload())->assertForbidden();
    $this->actingAs($viewer)->delete("/factories/{$factory->id}")->assertForbidden();

    expect(Factory::count())->toBe(1);
});

// --- create / store -----------------------------------------------------

it('stores a factory', function () {
    $zone = Zone::create(['name' => 'Dhaka', 'code' => 'DHK', 'is_active' => true]);

    $this->actingAs(factoryTestUser(['create-factories']))
        ->post('/factories', factoryTestPayload(['zone_id' => $zone->id]))
        ->assertRedirect('/factories')
        ->assertSessionHas('success');

    $factory = Factory::where('account_id', 'ACC-NEW-1')->firstOrFail();
    expect($factory->name)->toBe('Gazipur Plant')
        ->and($factory->zone_id)->toBe($zone->id)
        ->and((float) $factory->mileage_km)->toBe(35.5);
});

it('rejects invalid factory input', function () {
    $this->actingAs(factoryTestUser(['create-factories']))
        ->post('/factories', factoryTestPayload([
            'account_id' => '',
            'name' => '',
            'status' => 'closed',
            'latitude' => 91,
            'longitude' => -181,
            'mileage_km' => -1,
        ]))
        ->assertSessionHasErrors(['account_id', 'name', 'status', 'latitude', 'longitude', 'mileage_km']);

    expect(Factory::count())->toBe(0);
});

it('rejects a duplicate account id on create', function () {
    factoryTestMake(['account_id' => 'ACC-DUP']);

    $this->actingAs(factoryTestUser(['create-factories']))
        ->post('/factories', factoryTestPayload(['account_id' => 'ACC-DUP']))
        ->assertSessionHasErrors(['account_id' => 'A factory with this account ID already exists.']);

    expect(Factory::where('account_id', 'ACC-DUP')->count())->toBe(1);
});

// --- edit / update ------------------------------------------------------

it('updates a factory, allowing it to keep its own account id', function () {
    $factory = factoryTestMake(['account_id' => 'ACC-KEEP', 'name' => 'Old']);

    $this->actingAs(factoryTestUser(['edit-factories']))
        ->put("/factories/{$factory->id}", factoryTestPayload(['account_id' => 'ACC-KEEP', 'name' => 'New', 'status' => 'inactive']))
        ->assertRedirect('/factories')
        ->assertSessionHasNoErrors();

    $factory->refresh();
    expect($factory->name)->toBe('New')->and($factory->status)->toBe('inactive');
});

it('rejects taking another factory\'s account id on update', function () {
    factoryTestMake(['account_id' => 'ACC-TAKEN']);
    $factory = factoryTestMake(['account_id' => 'ACC-MINE']);

    $this->actingAs(factoryTestUser(['edit-factories']))
        ->put("/factories/{$factory->id}", factoryTestPayload(['account_id' => 'ACC-TAKEN']))
        ->assertSessionHasErrors('account_id');

    expect($factory->fresh()->account_id)->toBe('ACC-MINE');
});

// --- destroy ------------------------------------------------------------

it('deletes a factory', function () {
    $factory = factoryTestMake();

    $this->actingAs(factoryTestUser(['delete-factories']))
        ->delete("/factories/{$factory->id}")
        ->assertRedirect('/factories')
        ->assertSessionHas('success');

    expect(Factory::find($factory->id))->toBeNull();
});

// --- zone scoping -------------------------------------------------------

it('hides and blocks another zone\'s factory from a zone-restricted user', function () {
    $dhaka = Zone::create(['name' => 'Dhaka', 'code' => 'DHK', 'is_active' => true]);
    $ctg = Zone::create(['name' => 'Chattogram', 'code' => 'CTG', 'is_active' => true]);
    $own = factoryTestMake(['zone_id' => $dhaka->id]);
    $foreign = factoryTestMake(['zone_id' => $ctg->id]);

    $user = factoryTestUser(['view-factories', 'edit-factories', 'delete-factories'], $dhaka);

    $this->actingAs($user)->get('/factories')->assertInertia(fn ($page) => $page
        ->where('factories.data', fn ($rows) => collect($rows)->pluck('id')->all() === [$own->id])
        ->where('stats.total', 1)
    );

    $this->actingAs($user)->get("/factories/{$foreign->id}")->assertNotFound();
    $this->actingAs($user)->put("/factories/{$foreign->id}", factoryTestPayload())->assertNotFound();
    $this->actingAs($user)->delete("/factories/{$foreign->id}")->assertNotFound();

    expect(Factory::withoutGlobalScope('zone')->find($foreign->id))->not->toBeNull();
});
