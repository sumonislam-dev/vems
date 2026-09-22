<?php

use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

function seedUserGroupPermissions(): void
{
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    Permission::firstOrCreate(['name' => 'create-user-groups', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'edit-user-groups', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'view-user-groups', 'guard_name' => 'web']);
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
}

function makeUserGroupUser(string $username, string $userType = 'employee'): User
{
    return User::create([
        'email_verified_at' => now(),
        'name' => 'User '.$username,
        'username' => $username,
        'email' => $username.'@example.com',
        'user_type' => $userType,
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);
}

it('excludes drivers from the selectable user list on the create-group page', function () {
    seedUserGroupPermissions();

    $manager = makeUserGroupUser('creator-1', 'transport_manager');
    $manager->givePermissionTo('create-user-groups');

    $employee = makeUserGroupUser('employee-1', 'employee');
    $driver = makeUserGroupUser('driver-1', 'driver');

    $response = $this->actingAs($manager)->get('/user-groups/create');

    $response->assertInertia(fn ($page) => $page
        ->where('users', fn ($users) => collect($users)->pluck('id')->contains($employee->id)
            && collect($users)->pluck('id')->contains($manager->id)
            && ! collect($users)->pluck('id')->contains($driver->id)
        )
    );
});

it('excludes drivers from the selectable user list on the edit-group page', function () {
    seedUserGroupPermissions();

    $manager = makeUserGroupUser('editor-1', 'transport_manager');
    $manager->givePermissionTo('edit-user-groups');

    $employee = makeUserGroupUser('employee-2', 'employee');
    $driver = makeUserGroupUser('driver-2', 'driver');

    $group = UserGroup::create([
        'name' => 'Group '.uniqid(),
        'status' => 'active',
        'created_by' => $manager->id,
    ]);

    $response = $this->actingAs($manager)->get("/user-groups/{$group->id}/edit");

    $response->assertInertia(fn ($page) => $page
        ->where('users', fn ($users) => collect($users)->pluck('id')->contains($employee->id)
            && ! collect($users)->pluck('id')->contains($driver->id)
        )
    );
});

it('excludes drivers from the available-users endpoint used to add more members', function () {
    seedUserGroupPermissions();

    $manager = makeUserGroupUser('viewer-1', 'transport_manager');
    $manager->givePermissionTo('view-user-groups');

    $employee = makeUserGroupUser('employee-3', 'employee');
    $driver = makeUserGroupUser('driver-3', 'driver');

    $group = UserGroup::create([
        'name' => 'Group '.uniqid(),
        'status' => 'active',
        'created_by' => $manager->id,
    ]);

    $response = $this->actingAs($manager)->getJson("/user-groups/{$group->id}/available-users");

    $ids = collect($response->json())->pluck('id');
    expect($ids)->toContain($employee->id)
        ->and($ids)->not->toContain($driver->id);
});
