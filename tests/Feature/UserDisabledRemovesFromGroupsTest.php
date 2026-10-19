<?php

use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * A user who is disabled (status moves away from "active") has no business
 * staying in a user group they joined while active — group membership
 * drives things like trip passenger selection lists, so a stale membership
 * for a disabled account is a real leak, not just cosmetic. Enforced by
 * UserObserver::updated(), not the controller, so it fires no matter how
 * status gets changed (edit form today, import/bulk-toggle in the future).
 */
function seedDisableGroupPermissions(): void
{
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    Permission::firstOrCreate(['name' => 'edit-users', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
}

function makeDisableGroupUser(string $username, string $status = 'active'): User
{
    $user = User::create([
        'email_verified_at' => now(),
        'name' => 'User '.$username,
        'username' => $username,
        'email' => $username.'@example.com',
        'user_type' => 'employee',
        'status' => $status,
        'password' => Hash::make('password'),
    ]);
    $user->assignRole('employee');

    return $user;
}

it('removes a user from all their groups when an admin sets their status to inactive', function () {
    seedDisableGroupPermissions();
    $actor = makeDisableGroupUser('disable-actor-'.uniqid());
    $actor->givePermissionTo('edit-users');

    $target = makeDisableGroupUser('disable-target-'.uniqid());
    $group = UserGroup::create(['name' => 'Group '.uniqid(), 'status' => 'active', 'created_by' => $actor->id]);
    $group->addMember($target->id);
    expect($group->hasMember($target->id))->toBeTrue();

    $response = $this->actingAs($actor)->put("/users/{$target->id}", [
        'zone_access' => 'specific',
        'zone_ids' => [testZoneId()],
        'name' => $target->name,
        'username' => $target->username,
        'personal_phone' => '01700000010',
        'whatsapp_id' => '01700000010',
        'status' => 'inactive',
        'user_type' => 'employee',
        'roles' => [],
    ]);

    $response->assertSessionDoesntHaveErrors();
    expect($group->hasMember($target->id))->toBeFalse();
});

it('removes a suspended user from their groups too, not just inactive', function () {
    seedDisableGroupPermissions();
    $target = makeDisableGroupUser('disable-target-'.uniqid());
    $group = UserGroup::create(['name' => 'Group '.uniqid(), 'status' => 'active', 'created_by' => $target->id]);
    $group->addMember($target->id);

    $target->update(['status' => 'suspended']);

    expect($group->hasMember($target->id))->toBeFalse();
});

it('does not touch group membership when an update leaves status untouched', function () {
    seedDisableGroupPermissions();
    $actor = makeDisableGroupUser('disable-actor-'.uniqid());
    $actor->givePermissionTo('edit-users');

    $target = makeDisableGroupUser('disable-target-'.uniqid());
    $group = UserGroup::create(['name' => 'Group '.uniqid(), 'status' => 'active', 'created_by' => $actor->id]);
    $group->addMember($target->id);

    $response = $this->actingAs($actor)->put("/users/{$target->id}", [
        'zone_access' => 'specific',
        'zone_ids' => [testZoneId()],
        'name' => 'Renamed But Still Active',
        'username' => $target->username,
        'personal_phone' => '01700000011',
        'whatsapp_id' => '01700000011',
        'status' => 'active',
        'user_type' => 'employee',
        'roles' => [],
    ]);

    $response->assertSessionDoesntHaveErrors();
    expect($group->hasMember($target->id))->toBeTrue();
});

it('does not automatically re-add a reactivated user to groups they were removed from', function () {
    seedDisableGroupPermissions();
    $target = makeDisableGroupUser('reactivate-target-'.uniqid());
    $group = UserGroup::create(['name' => 'Group '.uniqid(), 'status' => 'active', 'created_by' => $target->id]);
    $group->addMember($target->id);

    $target->update(['status' => 'inactive']);
    expect($group->hasMember($target->id))->toBeFalse();

    $target->update(['status' => 'active']);
    expect($group->hasMember($target->id))->toBeFalse();
});
