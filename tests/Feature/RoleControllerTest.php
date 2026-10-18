<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The Roles screen (RoleController): listing, showing, creating, editing and
 * deleting roles, and the view-/create-/edit-/delete-roles gates on each.
 */
beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    foreach (['view-roles', 'create-roles', 'edit-roles', 'delete-roles', 'view-trips', 'create-trips', 'view-reports'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
});

function roleCtlUser(array $permissions = ['view-roles', 'create-roles', 'edit-roles', 'delete-roles']): User
{
    $user = User::create([
        'email_verified_at' => now(),
        'name' => 'Role Admin '.uniqid(),
        'username' => 'role_admin_'.uniqid(),
        'email' => 'role_admin_'.uniqid().'@example.com',
        'user_type' => 'employee',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);
    $user->givePermissionTo($permissions);

    return $user;
}

function roleCtlPermissionIds(array $names): array
{
    return Permission::whereIn('name', $names)->pluck('id')->all();
}

it('lists roles with their permissions, user counts and stats', function () {
    $dispatcher = Role::create(['name' => 'dispatcher', 'guard_name' => 'web']);
    $dispatcher->givePermissionTo('view-trips');
    Role::create(['name' => 'empty-role', 'guard_name' => 'web']);
    $withUser = Role::create(['name' => 'auditor', 'guard_name' => 'web']);
    roleCtlUser([])->assignRole($withUser);

    $this->actingAs(roleCtlUser(['view-roles']))
        ->get('/roles')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('roles/index')
            ->where('roles.total', 3)
            ->where('roles.data.0.name', 'auditor')
            ->where('roles.data.0.users_count', 1)
            ->where('roles.data.1.name', 'dispatcher')
            ->where('roles.data.1.permissions.0.name', 'view-trips')
            ->where('stats.total', 3)
            ->where('stats.with_users', 1)
            ->where('stats.with_permissions', 1)
            ->where('stats.empty', 1));
});

it('searches and sorts the role list, ignoring unknown sort columns', function () {
    Role::create(['name' => 'dispatcher', 'guard_name' => 'web']);
    Role::create(['name' => 'auditor', 'guard_name' => 'web']);
    Role::create(['name' => 'dispatch-lead', 'guard_name' => 'web']);
    $user = roleCtlUser(['view-roles']);

    $this->actingAs($user)->get('/roles?search=dispatch&direction=desc')
        ->assertInertia(fn ($page) => $page
            ->where('roles.total', 2)
            ->where('roles.data.0.name', 'dispatcher')
            ->where('roles.data.1.name', 'dispatch-lead')
            ->where('queryParams.search', 'dispatch'));

    // An arbitrary column name falls back to sorting by name rather than erroring
    $this->actingAs($user)->get('/roles?sort=id;drop&direction=asc')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('roles.data.0.name', 'auditor'));
});

it('shows a role with its permissions and users', function () {
    $role = Role::create(['name' => 'dispatcher', 'guard_name' => 'web']);
    $role->givePermissionTo(['view-trips', 'create-trips']);
    $member = roleCtlUser([]);
    $member->assignRole($role);

    $this->actingAs(roleCtlUser(['view-roles']))
        ->get("/roles/{$role->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('roles/show')
            ->where('role.name', 'dispatcher')
            ->has('role.permissions', 2)
            ->has('role.users', 1)
            ->where('role.users.0.id', $member->id));
});

it('offers every permission on the create and edit forms', function () {
    $role = Role::create(['name' => 'dispatcher', 'guard_name' => 'web']);
    $role->givePermissionTo('view-trips');
    $user = roleCtlUser();
    $total = Permission::count();

    $this->actingAs($user)->get('/roles/create')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('roles/create')->has('permissions', $total));

    $this->actingAs($user)->get("/roles/{$role->id}/edit")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('roles/edit')
            ->where('role.name', 'dispatcher')
            ->has('role.permissions', 1)
            ->has('permissions', $total));
});

it('creates a role on the web guard with the chosen permissions', function () {
    $this->actingAs(roleCtlUser())
        ->post('/roles', [
            'name' => 'dispatcher',
            'permissions' => roleCtlPermissionIds(['view-trips', 'create-trips']),
        ])
        ->assertRedirect(route('roles.index'))
        ->assertSessionHas('success');

    $role = Role::findByName('dispatcher', 'web');
    expect($role->guard_name)->toBe('web')
        ->and($role->permissions->pluck('name')->sort()->values()->all())->toBe(['create-trips', 'view-trips']);

    // Members of the new role actually get its permissions
    $member = roleCtlUser([]);
    $member->assignRole('dispatcher');
    expect($member->fresh()->can('create-trips'))->toBeTrue()
        ->and($member->fresh()->can('view-reports'))->toBeFalse();
});

it('rejects a duplicate role name, a missing name and unknown permissions', function () {
    Role::create(['name' => 'dispatcher', 'guard_name' => 'web']);
    $user = roleCtlUser();

    $this->actingAs($user)->post('/roles', ['name' => 'dispatcher'])
        ->assertSessionHasErrors('name');

    $this->actingAs($user)->post('/roles', ['name' => ''])
        ->assertSessionHasErrors('name');

    $this->actingAs($user)->post('/roles', ['name' => 'auditor', 'permissions' => [999999]])
        ->assertSessionHasErrors('permissions.0');

    expect(Role::where('name', 'auditor')->exists())->toBeFalse()
        ->and(Role::count())->toBe(1);
});

it('replaces a role\'s permissions on update and keeps its own name valid', function () {
    $role = Role::create(['name' => 'dispatcher', 'guard_name' => 'web']);
    $role->givePermissionTo(['view-trips', 'create-trips']);
    $member = roleCtlUser([]);
    $member->assignRole($role);

    $this->actingAs(roleCtlUser())
        ->put("/roles/{$role->id}", [
            'name' => 'dispatcher', // unchanged name is not a "duplicate"
            'permissions' => roleCtlPermissionIds(['view-reports']),
        ])
        ->assertRedirect(route('roles.index'))
        ->assertSessionHasNoErrors();

    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    expect($role->fresh()->permissions->pluck('name')->all())->toBe(['view-reports'])
        ->and($member->fresh()->can('view-reports'))->toBeTrue()
        ->and($member->fresh()->can('create-trips'))->toBeFalse();
});

it('keeps existing permissions when an update omits the permissions field', function () {
    $role = Role::create(['name' => 'dispatcher', 'guard_name' => 'web']);
    $role->givePermissionTo('view-trips');

    $this->actingAs(roleCtlUser())
        ->put("/roles/{$role->id}", ['name' => 'trip-dispatcher'])
        ->assertRedirect(route('roles.index'));

    $role->refresh();
    expect($role->name)->toBe('trip-dispatcher')
        ->and($role->permissions->pluck('name')->all())->toBe(['view-trips']);
});

it('clears every permission when an update sends an empty list', function () {
    $role = Role::create(['name' => 'dispatcher', 'guard_name' => 'web']);
    $role->givePermissionTo(['view-trips', 'create-trips']);

    $this->actingAs(roleCtlUser())
        ->put("/roles/{$role->id}", ['name' => 'dispatcher', 'permissions' => []])
        ->assertRedirect(route('roles.index'));

    expect($role->fresh()->permissions)->toHaveCount(0);
});

it('will not rename a role onto another role\'s name', function () {
    Role::create(['name' => 'auditor', 'guard_name' => 'web']);
    $role = Role::create(['name' => 'dispatcher', 'guard_name' => 'web']);

    $this->actingAs(roleCtlUser())
        ->put("/roles/{$role->id}", ['name' => 'auditor'])
        ->assertSessionHasErrors('name');

    expect($role->fresh()->name)->toBe('dispatcher');
});

it('deletes a role with no users', function () {
    $role = Role::create(['name' => 'dispatcher', 'guard_name' => 'web']);
    $role->givePermissionTo('view-trips');

    $this->actingAs(roleCtlUser())
        ->delete("/roles/{$role->id}")
        ->assertRedirect(route('roles.index'))
        ->assertSessionHas('success');

    expect(Role::where('name', 'dispatcher')->exists())->toBeFalse()
        ->and(Permission::where('name', 'view-trips')->exists())->toBeTrue();
});

it('refuses to delete a role that is still assigned to users', function () {
    $role = Role::create(['name' => 'super-admin', 'guard_name' => 'web']);
    roleCtlUser([])->assignRole($role);

    $this->actingAs(roleCtlUser())
        ->delete("/roles/{$role->id}")
        ->assertRedirect(route('roles.index'))
        ->assertSessionHas('error');

    expect(Role::where('name', 'super-admin')->exists())->toBeTrue();
});

it('gates each role action on its own permission', function () {
    $role = Role::create(['name' => 'dispatcher', 'guard_name' => 'web']);

    // No role permissions at all
    $none = roleCtlUser([]);
    $this->actingAs($none)->get('/roles')->assertForbidden();
    $this->actingAs($none)->get("/roles/{$role->id}")->assertForbidden();

    // Can view, but not create, edit or delete
    $viewer = roleCtlUser(['view-roles']);
    $this->actingAs($viewer)->get('/roles')->assertOk();
    $this->actingAs($viewer)->get('/roles/create')->assertForbidden();
    $this->actingAs($viewer)->post('/roles', ['name' => 'sneaky'])->assertForbidden();
    $this->actingAs($viewer)->get("/roles/{$role->id}/edit")->assertForbidden();
    $this->actingAs($viewer)->put("/roles/{$role->id}", ['name' => 'renamed'])->assertForbidden();
    $this->actingAs($viewer)->delete("/roles/{$role->id}")->assertForbidden();

    // Each write permission only opens its own action
    $this->actingAs(roleCtlUser(['create-roles']))->delete("/roles/{$role->id}")->assertForbidden();
    $this->actingAs(roleCtlUser(['edit-roles']))->post('/roles', ['name' => 'sneaky'])->assertForbidden();
    $this->actingAs(roleCtlUser(['delete-roles']))->put("/roles/{$role->id}", ['name' => 'renamed'])->assertForbidden();

    expect(Role::where('name', 'sneaky')->exists())->toBeFalse()
        ->and($role->fresh()->name)->toBe('dispatcher');
});

it('redirects guests to the login page', function () {
    $this->get('/roles')->assertRedirect(route('login'));
});

// RoleController has no guard for seeded roles: super-admin can be renamed
// (dropping Gate::before's bypass for its holders) and unassigned seeded roles
// like admin can be deleted.
it('protects seeded system roles from being renamed or deleted')->todo();
