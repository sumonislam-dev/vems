<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Admin accounts are listed and managed under Users, so the list's
 * deactivate/delete actions (and Status on the edit form) must not let
 * anyone lock out themselves or a super admin: a deactivated account can't
 * log in (EnsureUserIsActive). See UserSaver::switchOffBlocker().
 */
function makeProtectedAccount(string $username, string $userType = 'admin', array $permissions = [], ?string $role = null): User
{
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    foreach (['view-users', 'edit-users', 'delete-users', 'export-users', 'manage-user-roles', 'view-all-zones'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $user = User::create([
        'email_verified_at' => now(),
        'name' => ucfirst($username),
        'username' => $username,
        'personal_phone' => '01700000000',
        'whatsapp_id' => '01700000000',
        'user_type' => $userType,
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);
    $user->givePermissionTo($permissions);
    if ($role) {
        $user->assignRole($role);
    }

    return $user;
}

function adminPermissions(): array
{
    return ['view-users', 'edit-users', 'delete-users', 'export-users', 'manage-user-roles', 'view-all-zones'];
}

function editPayload(User $user, array $overrides = []): array
{
    return [
        'name' => $user->name,
        'username' => $user->username,
        'personal_phone' => '01700000000',
        'whatsapp_id' => '01700000000',
        'status' => 'active',
        'user_type' => $user->user_type,
        'roles' => $user->roles->pluck('id')->all(),
        'zone_access' => 'all',
        ...$overrides,
    ];
}

it('lists and exports admin accounts with the other non-driver users', function () {
    $admin = makeProtectedAccount('the.admin', 'admin', adminPermissions());
    $employee = makeProtectedAccount('an.employee', 'employee');
    $driver = makeProtectedAccount('a.driver', 'driver');

    $this->actingAs($admin)->get('/users')->assertOk()
        ->assertInertia(fn ($page) => $page->where('users.data', function ($rows) use ($admin, $employee, $driver) {
            $ids = collect($rows)->pluck('id');

            return $ids->contains($admin->id) && $ids->contains($employee->id) && ! $ids->contains($driver->id);
        }));

    $csv = $this->actingAs($admin)->get(route('users.export'))->assertOk()->getContent();

    expect($csv)->toContain('the.admin')->toContain('an.employee')->not->toContain('a.driver');
});

it("won't let anyone deactivate or delete their own account", function () {
    $admin = makeProtectedAccount('the.admin', 'admin', adminPermissions());

    $this->actingAs($admin)->post("/users/{$admin->id}/deactivate")
        ->assertSessionHas('error', "You can't deactivate your own account.");
    $this->actingAs($admin)->delete("/users/{$admin->id}")
        ->assertSessionHas('error', "You can't delete your own account.");
    $this->actingAs($admin)->put("/users/{$admin->id}", editPayload($admin, ['status' => 'inactive']))
        ->assertSessionHasErrors(['status' => "You can't deactivate your own account."]);

    expect($admin->fresh())->not->toBeNull()
        ->and($admin->fresh()->status)->toBe('active');
});

it('only lets a super admin deactivate or delete a super admin', function () {
    $admin = makeProtectedAccount('the.admin', 'admin', adminPermissions());
    $superAdmin = makeProtectedAccount('the.super', 'admin', [], 'super-admin');

    $this->actingAs($admin)->post("/users/{$superAdmin->id}/deactivate")
        ->assertSessionHas('error', 'Only a super admin can deactivate a super admin.');
    $this->actingAs($admin)->delete("/users/{$superAdmin->id}")
        ->assertSessionHas('error', 'Only a super admin can delete a super admin.');
    $this->actingAs($admin)->put("/users/{$superAdmin->id}", editPayload($superAdmin, ['status' => 'suspended']))
        ->assertSessionHasErrors(['status' => 'Only a super admin can deactivate a super admin.']);

    expect($superAdmin->fresh()->status)->toBe('active');

    $anotherSuperAdmin = makeProtectedAccount('another.super', 'admin', [], 'super-admin');
    $this->actingAs($anotherSuperAdmin)->post("/users/{$superAdmin->id}/deactivate")
        ->assertSessionHas('success');

    expect($superAdmin->fresh()->status)->toBe('inactive');
});

it('still lets an admin deactivate an ordinary user', function () {
    $admin = makeProtectedAccount('the.admin', 'admin', adminPermissions());
    $employee = makeProtectedAccount('an.employee', 'employee');

    $this->actingAs($admin)->post("/users/{$employee->id}/deactivate")->assertSessionHas('success');

    expect($employee->fresh()->status)->toBe('inactive');
});

it("won't let a super admin remove their own super admin role, but another super admin can", function () {
    $superAdmin = makeProtectedAccount('the.super', 'admin', [], 'super-admin');
    $adminRole = Role::where('name', 'admin')->first();

    $this->actingAs($superAdmin)->put("/users/{$superAdmin->id}", editPayload($superAdmin, ['roles' => [$adminRole->id]]))
        ->assertSessionHasErrors(['roles' => "You can't remove your own super admin role."]);

    expect($superAdmin->fresh()->hasRole('super-admin'))->toBeTrue();

    $anotherSuperAdmin = makeProtectedAccount('another.super', 'admin', [], 'super-admin');
    $this->actingAs($anotherSuperAdmin)->put("/users/{$superAdmin->id}", editPayload($superAdmin->fresh(), ['roles' => [$adminRole->id]]))
        ->assertSessionHasNoErrors();

    expect($superAdmin->fresh()->hasRole('super-admin'))->toBeFalse();
});
