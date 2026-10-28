<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Transport managers are office staff who run transport, not drivers: an
 * employee account with the transport-manager role. They belong on the
 * Users list, never on the Drivers list or its edit form (which would
 * turn them into a driver).
 */
function makeTransportManagerAccount(): User
{
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    foreach (['view-users', 'view-drivers', 'edit-drivers'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
    Role::firstOrCreate(['name' => 'transport-manager', 'guard_name' => 'web']);

    $manager = User::create([
        'email_verified_at' => now(),
        'name' => 'Sarah Transport Manager',
        'username' => 'sarah.manager',
        'user_type' => 'employee',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);
    $manager->assignRole('transport-manager');

    return $manager;
}

function makeTransportManagerViewer(): User
{
    $viewer = User::create([
        'email_verified_at' => now(),
        'name' => 'Admin Viewer',
        'username' => 'admin.viewer',
        'user_type' => 'admin',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);
    $viewer->givePermissionTo(['view-users', 'view-drivers', 'edit-drivers']);

    return $viewer;
}

it('lists a transport manager on Users, not on Drivers', function () {
    $manager = makeTransportManagerAccount();
    $viewer = makeTransportManagerViewer();

    $this->actingAs($viewer)->get('/users')->assertOk()
        ->assertInertia(fn ($page) => $page->where('users.data', fn ($rows) => collect($rows)->pluck('id')->contains($manager->id)));

    $this->actingAs($viewer)->get('/drivers')->assertOk()
        ->assertInertia(fn ($page) => $page->where('users.data', fn ($rows) => ! collect($rows)->pluck('id')->contains($manager->id)));
});

it('does not open a transport manager through the Drivers edit form', function () {
    $manager = makeTransportManagerAccount();
    $viewer = makeTransportManagerViewer();

    $this->actingAs($viewer)->get("/drivers/{$manager->id}/edit")->assertNotFound();
    $this->actingAs($viewer)->put("/drivers/{$manager->id}", ['name' => 'Changed']);

    expect($manager->fresh()->name)->toBe('Sarah Transport Manager')
        ->and($manager->fresh()->user_type)->toBe('employee')
        ->and($manager->fresh()->hasRole('transport-manager'))->toBeTrue();
});

it('converts existing transport_manager accounts to employees, keeping their role', function () {
    $manager = makeTransportManagerAccount();
    $manager->forceFill(['user_type' => 'transport_manager'])->saveQuietly();

    $migration = require database_path('migrations/2026_10_28_000000_convert_transport_manager_users_to_employees.php');

    $migration->up();
    expect($manager->fresh()->user_type)->toBe('employee')
        ->and($manager->fresh()->hasRole('transport-manager'))->toBeTrue();

    $migration->down();
    expect($manager->fresh()->user_type)->toBe('transport_manager');
});
