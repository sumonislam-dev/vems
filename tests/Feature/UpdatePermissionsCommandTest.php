<?php

use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * permissions:update adds what's new in RolePermissionSeeder and never
 * deletes or revokes anything (unlike setup:permissions).
 */
beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('adds a missing permission and grants it to the roles that list it', function () {
    Permission::findByName('approve-department-trips')->delete();
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $this->artisan('permissions:update')->assertSuccessful();

    expect(Role::findByName('department-head')->hasPermissionTo('approve-department-trips'))->toBeTrue()
        ->and(Role::findByName('admin')->hasPermissionTo('approve-department-trips'))->toBeTrue()
        ->and(Role::findByName('employee')->hasPermissionTo('approve-department-trips'))->toBeFalse();
});

it('recreates a missing seeded role', function () {
    Role::findByName('management')->delete();

    $this->artisan('permissions:update')->assertSuccessful();

    expect(Role::findByName('management')->hasPermissionTo('view-reports'))->toBeTrue();
});

it('keeps custom roles, extra permissions and manual grants', function () {
    $custom = Role::create(['name' => 'fleet-supervisor', 'guard_name' => 'web']);
    Permission::create(['name' => 'legacy-permission', 'guard_name' => 'web']);
    Role::findByName('employee')->givePermissionTo('view-vehicles');

    $this->artisan('permissions:update')
        ->expectsOutputToContain('Custom roles (kept): fleet-supervisor')
        ->expectsOutputToContain('Not in the seeder (kept): legacy-permission')
        ->assertSuccessful();

    expect(Role::where('name', 'fleet-supervisor')->exists())->toBeTrue()
        ->and(Permission::where('name', 'legacy-permission')->exists())->toBeTrue()
        ->and(Role::findByName('employee')->hasPermissionTo('view-vehicles'))->toBeTrue();
});

it('saves nothing on a dry run', function () {
    Permission::findByName('approve-department-trips')->delete();

    $this->artisan('permissions:update', ['--dry-run' => true])
        ->expectsOutputToContain('+ permission approve-department-trips')
        ->assertSuccessful();

    expect(Permission::where('name', 'approve-department-trips')->exists())->toBeFalse();
});

it('reports when everything is up to date', function () {
    $this->artisan('permissions:update')
        ->expectsOutput('Permissions are up to date.')
        ->assertSuccessful();
});
