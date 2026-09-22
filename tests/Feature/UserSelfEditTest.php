<?php

use App\Http\Controllers\UserController;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function seedUserSelfEditPermissions(): void
{
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    Permission::firstOrCreate(['name' => 'edit-users', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
}

function makeSelfEditUser(string $username, string $userType = 'employee'): User
{
    return User::create([
        'email_verified_at' => now(),
        'name' => 'Self Edit User '.$username,
        'username' => $username,
        'email' => $username.'@example.com',
        'user_type' => $userType,
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);
}

it('still blocks a plain user from updating their own profile at the route level', function () {
    seedUserSelfEditPermissions();
    $user = makeSelfEditUser('self-edit-blocked');
    $user->assignRole('employee');

    $this->actingAs($user)
        ->put("/users/{$user->id}", ['name' => 'New Name'])
        ->assertForbidden();
});

it('confines a non-privileged self-edit to safe fields and never touches roles/status (regression)', function () {
    // The route itself still 403s (see above); this exercises the
    // controller/FormRequest directly so the field-restriction fix stays
    // covered even though the self-edit path is unreachable today.
    seedUserSelfEditPermissions();
    Route::put('/test-only-users/{user}', [UserController::class, 'update'])
        ->middleware('web')->withoutMiddleware('permission:edit-users');

    $user = makeSelfEditUser('self-edit-direct');
    $user->assignRole('employee');

    $response = $this->actingAs($user)->put("/test-only-users/{$user->id}", [
        'name' => 'Updated Name',
        'personal_phone' => '01700000000',
        'whatsapp_id' => '01700000000',
        // A malicious/careless payload trying to self-escalate:
        'status' => 'suspended',
        'user_type' => 'admin',
        'roles' => [Role::where('name', 'super-admin')->first()->id],
    ]);

    $response->assertRedirect();

    $user->refresh();
    expect($user->name)->toBe('Updated Name')
        ->and($user->status)->toBe('active')
        ->and($user->user_type)->toBe('employee')
        ->and($user->hasRole('super-admin'))->toBeFalse()
        ->and($user->hasRole('employee'))->toBeTrue();
});

it('still lets a privileged editor manage roles/status normally', function () {
    seedUserSelfEditPermissions();
    Route::put('/test-only-users/{user}', [UserController::class, 'update'])
        ->middleware('web')->withoutMiddleware('permission:edit-users');

    $admin = makeSelfEditUser('privileged-editor', 'admin');
    $target = makeSelfEditUser('managed-user');
    $target->assignRole('employee');
    $employeeRole = Role::where('name', 'employee')->first();

    $response = $this->actingAs($admin)->put("/test-only-users/{$target->id}", [
        'name' => 'Updated Name',
        'username' => $target->username,
        'personal_phone' => '01700000000',
        'whatsapp_id' => '01700000000',
        'status' => 'suspended',
        'roles' => [$employeeRole->id],
    ]);

    $response->assertSessionDoesntHaveErrors();
    $response->assertRedirect();

    $target->refresh();
    expect($target->status)->toBe('suspended')
        ->and($target->hasRole('employee'))->toBeTrue();
});
