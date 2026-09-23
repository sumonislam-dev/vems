<?php

use App\Models\Trip;
use App\Models\TripRecurringGroup;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * UserController's core admin CRUD (create/edit/delete accounts, assign
 * roles) had zero route-level test coverage before this file — only the
 * narrow self-edit-restriction path was covered (see UserSelfEditTest.php).
 * This covers the actual daily-use admin flows: creating a user, editing
 * one (including the role-escalation guards in assignableRoles()), the
 * business rules blocking deletion of a user with trip history, and that
 * each action is actually gated by its permission.
 */
function seedUserCrudPermissions(): void
{
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    Permission::firstOrCreate(['name' => 'view-users', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'create-users', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'edit-users', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'delete-users', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'driver', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
}

function makeUserCrudActor(array $permissions = []): User
{
    $user = User::create([
        'email_verified_at' => now(),
        'name' => 'User CRUD Actor',
        'username' => 'user_crud_actor_'.uniqid(),
        'email' => 'user_crud_actor_'.uniqid().'@example.com',
        'user_type' => 'employee',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);

    if (! empty($permissions)) {
        $user->givePermissionTo($permissions);
    }

    return $user;
}

function makeUserCrudTarget(string $userType = 'employee'): User
{
    $user = User::create([
        'email_verified_at' => now(),
        'name' => 'User CRUD Target',
        'username' => 'user_crud_target_'.uniqid(),
        'email' => 'user_crud_target_'.uniqid().'@example.com',
        'user_type' => $userType,
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);
    $user->assignRole($userType === 'driver' ? 'driver' : 'employee');

    return $user;
}

// --- index / show -----------------------------------------------------

it('lists users for someone with view-users permission', function () {
    seedUserCrudPermissions();
    $actor = makeUserCrudActor(['view-users']);
    $target = makeUserCrudTarget();

    $response = $this->actingAs($actor)->get('/users');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('users/index')
        ->where('users.data', fn ($users) => collect($users)->pluck('id')->contains($target->id))
    );
});

it('denies the users index to someone without view-users permission', function () {
    seedUserCrudPermissions();
    $actor = makeUserCrudActor();

    $this->actingAs($actor)->get('/users')->assertForbidden();
});

it('shows a single user to someone with view-users permission', function () {
    seedUserCrudPermissions();
    $actor = makeUserCrudActor(['view-users']);
    $target = makeUserCrudTarget();

    $response = $this->actingAs($actor)->get("/users/{$target->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('users/show')
        ->where('user.id', $target->id)
    );
});

// --- store --------------------------------------------------------------

it('creates a user with the submitted role when authorized', function () {
    seedUserCrudPermissions();
    $actor = makeUserCrudActor(['create-users']);
    $employeeRole = Role::where('name', 'employee')->first();

    $response = $this->actingAs($actor)->post('/users', [
        'name' => 'New Employee',
        'username' => 'new_employee_'.uniqid(),
        'personal_phone' => '01700000001',
        'whatsapp_id' => '01700000001',
        'status' => 'active',
        'user_type' => 'employee',
        'roles' => [$employeeRole->id],
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertSessionDoesntHaveErrors();
    $response->assertRedirect(route('users.index'));

    $created = User::where('username', 'like', 'new_employee_%')->first();
    expect($created)->not->toBeNull()
        ->and($created->hasRole('employee'))->toBeTrue();
});

it('requires personal_phone and whatsapp_id to create a user', function () {
    seedUserCrudPermissions();
    $actor = makeUserCrudActor(['create-users']);

    $response = $this->actingAs($actor)->post('/users', [
        'name' => 'Missing Contact Fields',
        'username' => 'missing_contact_'.uniqid(),
        'status' => 'active',
        'user_type' => 'employee',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertSessionHasErrors(['personal_phone', 'whatsapp_id']);
});

it('requires license details when creating a driver', function () {
    seedUserCrudPermissions();
    $actor = makeUserCrudActor(['create-users']);

    $response = $this->actingAs($actor)->post('/users', [
        'name' => 'New Driver',
        'username' => 'new_driver_'.uniqid(),
        'personal_phone' => '01700000002',
        'whatsapp_id' => '01700000002',
        'status' => 'active',
        'user_type' => 'driver',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        // driving_license_no / license_class / license_expiry_date omitted on purpose
    ]);

    $response->assertSessionHasErrors(['driving_license_no', 'license_class', 'license_expiry_date']);
});

it('denies creating a user to someone without create-users permission', function () {
    seedUserCrudPermissions();
    $actor = makeUserCrudActor();

    $this->actingAs($actor)->post('/users', [
        'name' => 'Blocked User',
        'username' => 'blocked_user_'.uniqid(),
    ])->assertForbidden();
});

// --- update ---------------------------------------------------------------

it('updates a user\'s fields and syncs roles when authorized', function () {
    seedUserCrudPermissions();
    $actor = makeUserCrudActor(['edit-users']);
    $target = makeUserCrudTarget();
    $driverRole = Role::where('name', 'driver')->first();

    $response = $this->actingAs($actor)->put("/users/{$target->id}", [
        'name' => 'Renamed User',
        'username' => $target->username,
        'personal_phone' => '01700000003',
        'whatsapp_id' => '01700000003',
        'status' => 'inactive',
        'user_type' => 'employee',
        'roles' => [$driverRole->id],
    ]);

    $response->assertSessionDoesntHaveErrors();
    $response->assertRedirect(route('users.index'));

    $target->refresh();
    expect($target->name)->toBe('Renamed User')
        ->and($target->status)->toBe('inactive')
        ->and($target->hasRole('driver'))->toBeTrue()
        ->and($target->hasRole('employee'))->toBeFalse();
});

it('never lets a non-super-admin editor grant the super-admin role (assignableRoles regression)', function () {
    seedUserCrudPermissions();
    $actor = makeUserCrudActor(['edit-users']);
    $target = makeUserCrudTarget();
    $superAdminRole = Role::where('name', 'super-admin')->first();

    $response = $this->actingAs($actor)->put("/users/{$target->id}", [
        'name' => $target->name,
        'username' => $target->username,
        'personal_phone' => '01700000004',
        'whatsapp_id' => '01700000004',
        'status' => 'active',
        'user_type' => 'employee',
        'roles' => [$superAdminRole->id],
    ]);

    $response->assertSessionDoesntHaveErrors();
    $target->refresh();
    expect($target->hasRole('super-admin'))->toBeFalse();
});

it('never lets a non-super-admin editor strip super-admin from a user who already has it (assignableRoles regression)', function () {
    seedUserCrudPermissions();
    $actor = makeUserCrudActor(['edit-users']);
    $target = makeUserCrudTarget();
    $target->assignRole('super-admin');
    $employeeRole = Role::where('name', 'employee')->first();

    $response = $this->actingAs($actor)->put("/users/{$target->id}", [
        'name' => $target->name,
        'username' => $target->username,
        'personal_phone' => '01700000005',
        'whatsapp_id' => '01700000005',
        'status' => 'active',
        'user_type' => 'employee',
        // Actor tries to replace the target's roles with just 'employee'.
        'roles' => [$employeeRole->id],
    ]);

    $response->assertSessionDoesntHaveErrors();
    $target->refresh();
    expect($target->hasRole('super-admin'))->toBeTrue();
});

it('denies updating a user to someone without edit-users permission', function () {
    seedUserCrudPermissions();
    $actor = makeUserCrudActor();
    $target = makeUserCrudTarget();

    $this->actingAs($actor)->put("/users/{$target->id}", [
        'name' => 'Should Not Apply',
    ])->assertForbidden();
});

// --- destroy ----------------------------------------------------------------

it('deletes a user with no trip history when authorized', function () {
    seedUserCrudPermissions();
    $actor = makeUserCrudActor(['delete-users']);
    $target = makeUserCrudTarget();

    $response = $this->actingAs($actor)->delete("/users/{$target->id}");

    $response->assertRedirect(route('users.index'));
    expect(User::find($target->id))->toBeNull();
});

it('blocks deleting a driver who has active trips', function () {
    seedUserCrudPermissions();
    $actor = makeUserCrudActor(['delete-users']);
    $driver = makeUserCrudTarget('driver');
    $driver->forceFill(['driving_license_no' => 'DL-12345'])->save();
    $requester = makeUserCrudTarget();

    Trip::create([
        'trip_number' => 'TRIP-'.uniqid(),
        'driver_id' => $driver->id,
        'requested_by' => $requester->id,
        'scheduled_date' => now()->addDay()->toDateString(),
        'scheduled_start_time' => '09:00',
        'scheduled_end_time' => '10:00',
        'status' => 'in_progress',
    ]);

    $response = $this->actingAs($actor)->delete("/users/{$driver->id}");

    $response->assertRedirect();
    $response->assertSessionHas('error');
    expect(User::find($driver->id))->not->toBeNull();
});

it('blocks deleting a user who has requested trips on record', function () {
    seedUserCrudPermissions();
    $actor = makeUserCrudActor(['delete-users']);
    $requester = makeUserCrudTarget();

    Trip::create([
        'trip_number' => 'TRIP-'.uniqid(),
        'requested_by' => $requester->id,
        'scheduled_date' => now()->addDay()->toDateString(),
        'scheduled_start_time' => '09:00',
        'scheduled_end_time' => '10:00',
        'status' => 'completed',
    ]);

    $response = $this->actingAs($actor)->delete("/users/{$requester->id}");

    $response->assertRedirect();
    $response->assertSessionHas('error');
    expect(User::find($requester->id))->not->toBeNull();
});

it('blocks deleting a user who created a recurring trip group', function () {
    seedUserCrudPermissions();
    $actor = makeUserCrudActor(['delete-users']);
    $creator = makeUserCrudTarget();

    TripRecurringGroup::create([
        'created_by' => $creator->id,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(3)->toDateString(),
    ]);

    $response = $this->actingAs($actor)->delete("/users/{$creator->id}");

    $response->assertRedirect();
    $response->assertSessionHas('error');
    expect(User::find($creator->id))->not->toBeNull();
});

it('denies deleting a user to someone without delete-users permission', function () {
    seedUserCrudPermissions();
    $actor = makeUserCrudActor();
    $target = makeUserCrudTarget();

    $this->actingAs($actor)->delete("/users/{$target->id}")->assertForbidden();
});
