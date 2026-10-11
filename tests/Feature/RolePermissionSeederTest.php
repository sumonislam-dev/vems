<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function seedRolesAndPermissions(): void
{
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    (new RolePermissionSeeder())->run();
}

it('seeds exactly the seven roles', function () {
    seedRolesAndPermissions();

    expect(Role::pluck('name')->sort()->values()->all())
        ->toBe(['admin', 'department-head', 'driver', 'employee', 'management', 'super-admin', 'transport-manager']);
});

it('lets transport-manager run operations but not administer the system', function () {
    seedRolesAndPermissions();
    $manager = Role::findByName('transport-manager', 'web');

    foreach (['approve-trips', 'reject-trips', 'assign-vehicles', 'manage-vehicle-downtime', 'resolve-complaints', 'view-reports', 'export-reports'] as $permission) {
        expect($manager->hasPermissionTo($permission))->toBeTrue("missing {$permission}");
    }
    foreach (['view-users', 'manage-user-roles', 'view-roles', 'edit-zones', 'view-all-zones', 'delete-vehicles', 'delete-trips'] as $permission) {
        expect($manager->hasPermissionTo($permission))->toBeFalse("should not have {$permission}");
    }
});

it('deletes permissions that are no longer listed', function () {
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    Permission::firstOrCreate(['name' => 'view-live-tracking', 'guard_name' => 'web']);

    seedRolesAndPermissions();

    expect(Permission::where('name', 'view-live-tracking')->exists())->toBeFalse()
        ->and(Permission::where('name', 'view-maintenance')->exists())->toBeTrue();
});

it('gives super-admin and admin every permission', function () {
    seedRolesAndPermissions();

    $total = Permission::count();
    expect($total)->toBeGreaterThan(0);

    $superAdmin = Role::findByName('super-admin', 'web');
    $admin = Role::findByName('admin', 'web');

    expect($superAdmin->permissions()->count())->toBe($total)
        ->and($admin->permissions()->count())->toBe($total);
});

it('scopes employee and driver down to their day-to-day permissions', function () {
    seedRolesAndPermissions();

    $employee = Role::findByName('employee', 'web');
    $driver = Role::findByName('driver', 'web');

    expect($employee->hasPermissionTo('create-trips'))->toBeTrue()
        ->and($employee->hasPermissionTo('create-complaints'))->toBeTrue()
        ->and($employee->hasPermissionTo('delete-vehicles'))->toBeFalse()
        ->and($employee->hasPermissionTo('edit-trips'))->toBeFalse()
        ->and($employee->hasPermissionTo('view-vehicles'))->toBeFalse();

    expect($driver->hasPermissionTo('check-in-trips'))->toBeTrue()
        ->and($driver->hasPermissionTo('capture-passenger-attendance'))->toBeTrue()
        ->and($driver->hasPermissionTo('edit-trips'))->toBeFalse()
        ->and($driver->hasPermissionTo('approve-trips'))->toBeFalse();
});

it('removes stale roles left over from a previous, larger role set', function () {
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    Role::firstOrCreate(['name' => 'fleet-supervisor', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'senior-driver', 'guard_name' => 'web']);

    seedRolesAndPermissions();

    expect(Role::where('name', 'fleet-supervisor')->exists())->toBeFalse()
        ->and(Role::where('name', 'senior-driver')->exists())->toBeFalse();
});

it('unassigns a user from a role that gets removed by reseeding, without error', function () {
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    Role::firstOrCreate(['name' => 'transport-officer', 'guard_name' => 'web']);

    $user = User::create([
        'email_verified_at' => now(),
        'name' => 'Legacy Role User',
        'username' => 'legacy-role-user',
        'email' => 'legacy-role-user@example.com',
        'user_type' => 'employee',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);
    $user->assignRole('transport-officer');

    seedRolesAndPermissions();

    $user->refresh();
    expect($user->roles()->count())->toBe(0);
});

it('setup:permissions delegates to the seeder and assigns super-admin to the first user', function () {
    $user = User::create([
        'email_verified_at' => now(),
        'name' => 'First User',
        'username' => 'first-user',
        'email' => 'first-user@example.com',
        'user_type' => 'admin',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);

    $this->artisan('setup:permissions')->assertExitCode(0);

    expect(Role::pluck('name')->sort()->values()->all())
        ->toBe(['admin', 'department-head', 'driver', 'employee', 'management', 'super-admin', 'transport-manager']);

    $user->refresh();
    expect($user->hasRole('super-admin'))->toBeTrue();
});
