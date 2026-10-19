<?php

use App\Models\Department;
use App\Models\Logistics;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * LogisticsController: the Logistics records CRUD (index filters/stats,
 * show/edit props, store/update/destroy, toggle-lock) and its per-action
 * permission middleware. SecurityFixesTest only covers the show page rendering.
 */
beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    foreach (['view-logistics', 'create-logistics', 'edit-logistics', 'delete-logistics'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $this->ops = Department::create(['name' => 'Operations', 'code' => 'OPS']);
    $this->hr = Department::create(['name' => 'Human Resources', 'code' => 'HR']);
});

function logisticsTestUser(array $permissions = [], array $attributes = []): User
{
    $user = User::create(array_merge([
        'email_verified_at' => now(),
        'name' => 'Logistics Tester',
        'username' => 'logistics_'.uniqid(),
        'email' => 'logistics_'.uniqid().'@example.com',
        'user_type' => 'employee',
        'status' => 'active',
        'password' => Hash::make('password'),
    ], $attributes));
    $user->givePermissionTo($permissions);

    return $user;
}

function logisticsTestRecord(Department $department, array $attributes = []): Logistics
{
    return Logistics::create(array_merge([
        'name' => 'Hub '.uniqid(),
        'department_id' => $department->id,
        'status' => true,
    ], $attributes));
}

it('lists logistics with their department, creator, active departments and stats', function () {
    $creator = logisticsTestUser([], ['name' => 'Rahim Creator']);
    logisticsTestRecord($this->ops, ['name' => 'Gazipur Hub', 'created_by' => $creator->id]);
    logisticsTestRecord($this->hr, ['name' => 'Savar Depot', 'status' => false]);
    Department::create(['name' => 'Closed Dept', 'code' => 'CLS', 'is_active' => false]);

    $this->actingAs(logisticsTestUser(['view-logistics']))->get('/logistics')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('logistics/index')
            ->has('logistics.data', 2)
            ->where('stats', ['total' => 2, 'active' => 1, 'inactive' => 1])
            ->where('departments', fn ($departments) => collect($departments)->pluck('name')->all() === ['Human Resources', 'Operations'])
            ->where('logistics.data', function ($rows) {
                $hub = collect($rows)->firstWhere('name', 'Gazipur Hub');

                return $hub['status'] === 'active'
                    && $hub['department']['name'] === 'Operations'
                    && $hub['creator']['name'] === 'Rahim Creator';
            })
        );
});

it('filters the list by search term on name or description', function () {
    logisticsTestRecord($this->ops, ['name' => 'Gazipur Hub']);
    logisticsTestRecord($this->ops, ['name' => 'Savar Depot', 'description' => 'Near the Gazipur road']);
    logisticsTestRecord($this->ops, ['name' => 'Tongi Yard']);

    $this->actingAs(logisticsTestUser(['view-logistics']))->get('/logistics?search=Gazipur')
        ->assertInertia(fn ($page) => $page
            ->where('filters.search', 'Gazipur')
            ->where('logistics.data', fn ($rows) => collect($rows)->pluck('name')->sort()->values()->all() === ['Gazipur Hub', 'Savar Depot'])
        );
});

it('filters the list by department and by status', function () {
    logisticsTestRecord($this->ops, ['name' => 'Ops Active']);
    logisticsTestRecord($this->ops, ['name' => 'Ops Inactive', 'status' => false]);
    logisticsTestRecord($this->hr, ['name' => 'HR Active']);

    $viewer = logisticsTestUser(['view-logistics']);

    $this->actingAs($viewer)->get("/logistics?department_id={$this->ops->id}")
        ->assertInertia(fn ($page) => $page
            ->where('logistics.data', fn ($rows) => collect($rows)->pluck('name')->sort()->values()->all() === ['Ops Active', 'Ops Inactive'])
        );

    $this->actingAs($viewer)->get('/logistics?status=inactive')
        ->assertInertia(fn ($page) => $page
            ->where('logistics.data', fn ($rows) => collect($rows)->pluck('name')->all() === ['Ops Inactive'])
        );

    $this->actingAs($viewer)->get("/logistics?status=active&department_id={$this->hr->id}")
        ->assertInertia(fn ($page) => $page
            ->where('logistics.data', fn ($rows) => collect($rows)->pluck('name')->all() === ['HR Active'])
            ->where('filters', ['department_id' => (string) $this->hr->id, 'status' => 'active'])
        );
});

it('scopes logistics to the viewer zone', function () {
    $dhaka = Zone::create(['name' => 'Dhaka', 'code' => 'DHK', 'is_active' => true]);
    $ctg = Zone::create(['name' => 'Chattogram', 'code' => 'CTG', 'is_active' => true]);
    logisticsTestRecord($this->ops, ['name' => 'Dhaka Hub', 'zone_id' => $dhaka->id]);
    logisticsTestRecord($this->hr, ['name' => 'Chattogram Hub', 'zone_id' => $ctg->id]);

    $this->actingAs(logisticsTestUser(['view-logistics'], ['zone_id' => $dhaka->id]))->get('/logistics')
        ->assertInertia(fn ($page) => $page
            ->has('logistics.data', 1)
            ->where('logistics.data.0.name', 'Dhaka Hub')
            ->where('logistics.data.0.zone.name', 'Dhaka'));
});

it('saves and changes the zone, offering only zones the user can pick', function () {
    $dhaka = Zone::create(['name' => 'Dhaka', 'code' => 'DHK', 'is_active' => true]);
    $ctg = Zone::create(['name' => 'Chattogram', 'code' => 'CTG', 'is_active' => true]);
    $user = logisticsTestUser(['create-logistics', 'edit-logistics'], ['zone_id' => $dhaka->id]);

    $this->actingAs($user)->get('/logistics/create')
        ->assertInertia(fn ($page) => $page->where('defaultZoneId', $dhaka->id)->has('zones', 1));

    $this->actingAs($user)->post('/logistics', ['name' => 'Dhaka Hub', 'department_id' => $this->ops->id, 'status' => 'active', 'zone_id' => $dhaka->id])
        ->assertSessionHasNoErrors();
    $logistic = Logistics::where('name', 'Dhaka Hub')->sole();
    expect($logistic->zone_id)->toBe($dhaka->id);

    // A Dhaka-only user can't move it to a zone outside their access
    $this->actingAs($user)->put("/logistics/{$logistic->id}", ['name' => 'Dhaka Hub', 'department_id' => $this->ops->id, 'status' => 'active', 'zone_id' => $ctg->id])
        ->assertSessionHasErrors('zone_id');
});

it('shows a logistics record with its department code, creator and timestamps', function () {
    $creator = logisticsTestUser([], ['name' => 'Karim Creator']);
    $logistic = logisticsTestRecord($this->ops, ['name' => 'Gazipur Hub', 'description' => 'Main hub', 'status' => false, 'created_by' => $creator->id]);

    $this->actingAs(logisticsTestUser(['view-logistics']))->get("/logistics/{$logistic->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('logistics/show')
            ->where('logistic.status', 'inactive')
            ->where('logistic.description', 'Main hub')
            ->where('logistic.department', ['id' => $this->ops->id, 'name' => 'Operations', 'code' => 'OPS'])
            ->where('logistic.creator', ['id' => $creator->id, 'name' => 'Karim Creator'])
            ->has('logistic.created_at')
            ->has('logistic.updated_at')
        );
});

it('returns 404 for a logistics record that does not exist', function () {
    $this->actingAs(logisticsTestUser(['view-logistics']))->get('/logistics/999999')->assertNotFound();
});

it('renders the create and edit forms with active departments', function () {
    Department::create(['name' => 'Closed Dept', 'code' => 'CLS', 'is_active' => false]);
    $logistic = logisticsTestRecord($this->hr, ['name' => 'Savar Depot', 'status' => false]);
    $editor = logisticsTestUser(['create-logistics', 'edit-logistics']);

    $this->actingAs($editor)->get('/logistics/create')
        ->assertInertia(fn ($page) => $page->component('logistics/create')->has('departments', 2));

    $this->actingAs($editor)->get("/logistics/{$logistic->id}/edit")
        ->assertInertia(fn ($page) => $page
            ->component('logistics/edit')
            ->has('departments', 2)
            ->where('logistic.department_id', $this->hr->id)
            ->where('logistic.status', 'inactive')
        );
});

it('creates a logistics record, storing the status as a boolean and the actor as creator', function () {
    $creator = logisticsTestUser(['create-logistics']);

    $this->actingAs($creator)->post('/logistics', [
        'name' => 'Tongi Yard',
        'department_id' => $this->ops->id,
        'description' => 'Overflow parking',
        'status' => 'active',
    ])->assertRedirect(route('logistics.index'))->assertSessionHas('success');

    $logistic = Logistics::where('name', 'Tongi Yard')->firstOrFail();
    expect($logistic->status)->toBeTrue()
        ->and($logistic->department_id)->toBe($this->ops->id)
        ->and($logistic->created_by)->toBe($creator->id);
});

it('validates the logistics form', function (array $payload, array $errors) {
    $this->actingAs(logisticsTestUser(['create-logistics']))
        ->post('/logistics', $payload)
        ->assertSessionHasErrors($errors);

    expect(Logistics::count())->toBe(0);
})->with([
    'missing everything' => [[], ['name', 'department_id', 'status']],
    'unknown department' => [['name' => 'X', 'department_id' => 999999, 'status' => 'active'], ['department_id']],
    'invalid status' => [['name' => 'X', 'department_id' => null, 'status' => 'locked'], ['department_id', 'status']],
    'name too long' => [['name' => str_repeat('a', 256), 'status' => 'active'], ['name']],
]);

it('updates a logistics record without changing its creator', function () {
    $creator = logisticsTestUser();
    $logistic = logisticsTestRecord($this->ops, ['name' => 'Old Name', 'created_by' => $creator->id]);

    $this->actingAs(logisticsTestUser(['edit-logistics']))->put("/logistics/{$logistic->id}", [
        'name' => 'New Name',
        'department_id' => $this->hr->id,
        'description' => null,
        'status' => 'inactive',
    ])->assertRedirect(route('logistics.index'));

    $logistic->refresh();
    expect($logistic->name)->toBe('New Name')
        ->and($logistic->department_id)->toBe($this->hr->id)
        ->and($logistic->status)->toBeFalse()
        ->and($logistic->created_by)->toBe($creator->id);
});

it('toggles a logistics record between active and inactive', function () {
    $logistic = logisticsTestRecord($this->ops, ['status' => true]);
    $editor = logisticsTestUser(['edit-logistics']);

    $this->actingAs($editor)->patch("/logistics/{$logistic->id}/toggle-lock")
        ->assertRedirect()->assertSessionHas('success', 'Logistics deactivated successfully.');
    expect($logistic->fresh()->status)->toBeFalse();

    $this->actingAs($editor)->patch("/logistics/{$logistic->id}/toggle-lock")
        ->assertSessionHas('success', 'Logistics activated successfully.');
    expect($logistic->fresh()->status)->toBeTrue();
});

it('deletes a logistics record', function () {
    $logistic = logisticsTestRecord($this->ops);

    $this->actingAs(logisticsTestUser(['delete-logistics']))->delete("/logistics/{$logistic->id}")
        ->assertRedirect(route('logistics.index'));

    expect(Logistics::find($logistic->id))->toBeNull();
});

it('forbids each logistics action without its permission', function (string $method, string $uri, array $granted) {
    $logistic = logisticsTestRecord($this->ops, ['name' => 'Untouched', 'status' => true]);
    $uri = str_replace('{id}', (string) $logistic->id, $uri);

    $this->actingAs(logisticsTestUser($granted))
        ->call(strtoupper($method), $uri, ['name' => 'Changed', 'department_id' => $this->ops->id, 'status' => 'inactive'])
        ->assertForbidden();

    expect(Logistics::count())->toBe(1)
        ->and($logistic->fresh()->name)->toBe('Untouched')
        ->and($logistic->fresh()->status)->toBeTrue();
})->with([
    'index' => ['get', '/logistics', ['create-logistics', 'edit-logistics', 'delete-logistics']],
    'show' => ['get', '/logistics/{id}', ['edit-logistics']],
    'create form' => ['get', '/logistics/create', ['view-logistics']],
    'store' => ['post', '/logistics', ['view-logistics', 'edit-logistics']],
    'edit form' => ['get', '/logistics/{id}/edit', ['view-logistics', 'create-logistics']],
    'update' => ['put', '/logistics/{id}', ['view-logistics', 'create-logistics']],
    'toggle' => ['patch', '/logistics/{id}/toggle-lock', ['view-logistics']],
    'destroy' => ['delete', '/logistics/{id}', ['view-logistics', 'edit-logistics']],
]);

it('redirects guests to the login page', function () {
    $this->get('/logistics')->assertRedirect(route('login'));
});
