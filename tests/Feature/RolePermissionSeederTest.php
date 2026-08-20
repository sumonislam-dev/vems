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

it('seeds exactly the four roles: super-admin, admin, employee, driver', function () {
    seedRolesAndPermissions();

    expect(Role::pluck('name')->sort()->values()->all())
        ->toBe(['admin', 'driver', 'employee', 'super-admin']);
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
        ->and($employee->hasPermissionTo('edit-trips'))->toBeFalse();

    expect($driver->hasPermissionTo('check-in-trips'))->toBeTrue()
        ->and($driver->hasPermissionTo('capture-passenger-attendance'))->toBeTrue()
        ->and($driver->hasPermissionTo('edit-trips'))->toBeFalse()
        ->and($driver->hasPermissionTo('approve-trips'))->toBeFalse();
});

it('removes stale roles left over from a previous, larger role set', function () {
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    Role::firstOrCreate(['name' => 'transport-manager', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'senior-driver', 'guard_name' => 'web']);

    seedRolesAndPermissions();

    expect(Role::where('name', 'transport-manager')->exists())->toBeFalse()
        ->and(Role::where('name', 'senior-driver')->exists())->toBeFalse();
});

it('unassigns a user from a role that gets removed by reseeding, without error', function () {
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    Role::firstOrCreate(['name' => 'transport-officer', 'guard_name' => 'web']);

    $user = User::create([
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
        'name' => 'First User',
        'username' => 'first-user',
        'email' => 'first-user@example.com',
        'user_type' => 'admin',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);

    $this->artisan('setup:permissions')->assertExitCode(0);

    expect(Role::pluck('name')->sort()->values()->all())
        ->toBe(['admin', 'driver', 'employee', 'super-admin']);

    $user->refresh();
    expect($user->hasRole('super-admin'))->toBeTrue();
});
