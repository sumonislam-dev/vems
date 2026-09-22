<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

function seedDriverBulkStatusPermissions(): void
{
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    Permission::firstOrCreate(['name' => 'edit-drivers', 'guard_name' => 'web']);
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
}

function makeDriverBulkStatusEditor(): User
{
    $user = User::create([
        'email_verified_at' => now(),
        'name' => 'Driver Bulk Editor',
        'username' => 'driver_bulk_'.uniqid(),
        'email' => 'driver_bulk_'.uniqid().'@example.com',
        'user_type' => 'employee',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);

    $user->givePermissionTo('edit-drivers');

    return $user;
}

function makeDriverBulkStatusDriver(array $overrides = []): User
{
    $username = 'driver_bulk_row_'.uniqid();

    return User::create(array_merge([
        'email_verified_at' => now(),
        'name' => 'Bulk Test Driver',
        'username' => $username,
        'email' => $username.'@example.com',
        'user_type' => 'driver',
        'status' => 'active',
        'driver_status' => 'available',
        'password' => Hash::make('password'),
    ], $overrides));
}

it('bulk updates driver_status for the selected drivers', function () {
    seedDriverBulkStatusPermissions();
    $editor = makeDriverBulkStatusEditor();

    $d1 = makeDriverBulkStatusDriver();
    $d2 = makeDriverBulkStatusDriver();
    $untouched = makeDriverBulkStatusDriver();

    $response = $this->actingAs($editor)->post('/drivers-bulk-status', [
        'driver_ids' => [$d1->id, $d2->id],
        'driver_status' => 'on_leave',
    ]);

    $response->assertRedirect();
    expect($d1->fresh()->driver_status)->toBe('on_leave')
        ->and($d2->fresh()->driver_status)->toBe('on_leave')
        ->and($untouched->fresh()->driver_status)->toBe('available');
});

it('rejects an invalid driver_status value for bulk update', function () {
    seedDriverBulkStatusPermissions();
    $editor = makeDriverBulkStatusEditor();
    $driver = makeDriverBulkStatusDriver();

    $response = $this->actingAs($editor)->post('/drivers-bulk-status', [
        'driver_ids' => [$driver->id],
        'driver_status' => 'not_a_real_status',
    ]);

    $response->assertSessionHasErrors('driver_status');
});

it('denies bulk driver status update to a user without edit-drivers', function () {
    $user = User::create([
        'email_verified_at' => now(),
        'name' => 'No Perm',
        'username' => 'no_perm_'.uniqid(),
        'email' => 'no_perm_'.uniqid().'@example.com',
        'user_type' => 'employee',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);

    $driver = makeDriverBulkStatusDriver();

    $response = $this->actingAs($user)->post('/drivers-bulk-status', [
        'driver_ids' => [$driver->id],
        'driver_status' => 'on_leave',
    ]);

    $response->assertForbidden();
});
