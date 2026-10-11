<?php

use App\Models\User;
use App\Models\Vehicle;
use App\Services\Reports\MonthlyVehicleReport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Access follows permissions, not roles or user_type: role changes need
 * manage-user-roles, exports/imports have their own permissions, and
 * notifications/report contact details are permission-gated.
 */
function seedEnforcementPermissions(array $names): void
{
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    foreach ($names as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
    foreach (['admin', 'employee', 'driver', 'super-admin'] as $role) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    }
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
}

function enforcementUser(array $permissions = [], array $attrs = []): User
{
    $user = User::create(array_merge([
        'email_verified_at' => now(),
        'name' => 'Enforcement User',
        'username' => 'enforce_'.uniqid(),
        'email' => 'enforce_'.uniqid().'@example.com',
        'user_type' => 'employee',
        'status' => 'active',
        'personal_phone' => '01700000000',
        'whatsapp_id' => '01700000000',
        'password' => Hash::make('password'),
    ], $attrs));
    $user->givePermissionTo($permissions);

    return $user;
}

it('ignores submitted roles from an editor without manage-user-roles', function () {
    seedEnforcementPermissions(['edit-users', 'manage-user-roles']);
    $editor = enforcementUser(['edit-users']);
    $target = enforcementUser();
    $target->assignRole('employee');

    $this->actingAs($editor)->put("/users/{$target->id}", [
        'name' => 'Renamed',
        'username' => $target->username,
        'email' => $target->email,
        'user_type' => 'employee',
        'status' => 'active',
        'personal_phone' => '01700000000',
        'whatsapp_id' => '01700000000',
        'roles' => [Role::findByName('admin')->id],
    ]);

    $target->refresh();
    expect($target->name)->toBe('Renamed')
        ->and($target->hasRole('admin'))->toBeFalse()
        ->and($target->hasRole('employee'))->toBeTrue();
});

it('does not hand out the admin role through user_type without manage-user-roles', function () {
    seedEnforcementPermissions(['create-users', 'manage-user-roles']);
    $creator = enforcementUser(['create-users']);

    $this->actingAs($creator)->post('/users', [
        'name' => 'New Admin?',
        'username' => 'new_admin_'.uniqid(),
        'email' => 'new_admin_'.uniqid().'@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'user_type' => 'admin',
        'status' => 'active',
        'personal_phone' => '01700000001',
        'whatsapp_id' => '01700000001',
        'roles' => [Role::findByName('admin')->id],
    ]);

    $created = User::where('name', 'New Admin?')->first();
    expect($created)->not->toBeNull()
        ->and($created->hasRole('admin'))->toBeFalse()
        ->and($created->hasRole('employee'))->toBeTrue();
});

it('requires export permissions separately from view permissions', function () {
    seedEnforcementPermissions(['view-vehicles', 'export-vehicles']);

    $this->actingAs(enforcementUser(['view-vehicles']))->get('/vehicles-export?format=csv')->assertForbidden();
    $this->actingAs(enforcementUser(['view-vehicles', 'export-vehicles']))->get('/vehicles-export?format=csv')->assertOk();
});

it('gates notifications behind view-notifications', function () {
    seedEnforcementPermissions(['view-notifications']);
    $without = enforcementUser();

    $this->actingAs($without)->post('/notifications/read-all')->assertForbidden();
    $this->actingAs($without)->get('/dashboard')
        ->assertInertia(fn ($page) => $page->where('notifications', null));

    $this->actingAs(enforcementUser(['view-notifications']))->post('/notifications/read-all')->assertRedirect();
});

it('hides driver phone numbers in the monthly report from viewers without view-drivers', function () {
    seedEnforcementPermissions(['view-drivers']);
    $driver = enforcementUser([], ['user_type' => 'driver', 'official_phone' => '01799999999']);
    Vehicle::create(['brand' => 'Toyota', 'model' => 'Hiace', 'registration_number' => 'PH-1', 'rental_type' => 'pool', 'is_active' => true, 'driver_id' => $driver->id]);

    $phoneFor = function (User $viewer) {
        $report = (new MonthlyVehicleReport(Carbon::parse('2026-01-05'), Carbon::parse('2026-01-06'), [], $viewer))->build();

        return collect($report['details']['groups'])->flatMap(fn ($g) => $g['rows'])->firstWhere('registration_number', 'PH-1')['phone'];
    };

    expect($phoneFor(enforcementUser()))->toBeNull()
        ->and($phoneFor(enforcementUser(['view-drivers'])))->toBe('01799999999');
});
