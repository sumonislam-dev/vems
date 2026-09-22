<?php

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

function seedVehicleBulkStatusPermissions(): void
{
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    Permission::firstOrCreate(['name' => 'edit-vehicles', 'guard_name' => 'web']);
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
}

function makeVehicleBulkStatusEditor(): User
{
    $user = User::create([
        'email_verified_at' => now(),
        'name' => 'Vehicle Bulk Editor',
        'username' => 'vehicle_bulk_'.uniqid(),
        'email' => 'vehicle_bulk_'.uniqid().'@example.com',
        'user_type' => 'employee',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);

    $user->givePermissionTo('edit-vehicles');

    return $user;
}

it('bulk deactivates the selected vehicles', function () {
    seedVehicleBulkStatusPermissions();
    $editor = makeVehicleBulkStatusEditor();

    $v1 = Vehicle::create(['brand' => 'A', 'model' => 'A1', 'registration_number' => 'BULK-'.uniqid(), 'is_active' => true]);
    $v2 = Vehicle::create(['brand' => 'B', 'model' => 'B1', 'registration_number' => 'BULK-'.uniqid(), 'is_active' => true]);
    $untouched = Vehicle::create(['brand' => 'C', 'model' => 'C1', 'registration_number' => 'BULK-'.uniqid(), 'is_active' => true]);

    $response = $this->actingAs($editor)->post('/vehicles-bulk-status', [
        'vehicle_ids' => [$v1->id, $v2->id],
        'is_active' => false,
    ]);

    $response->assertRedirect();
    expect($v1->fresh()->is_active)->toBeFalse()
        ->and($v2->fresh()->is_active)->toBeFalse()
        ->and($untouched->fresh()->is_active)->toBeTrue();
});

it('rejects an empty or oversized vehicle_ids array for bulk status', function () {
    seedVehicleBulkStatusPermissions();
    $editor = makeVehicleBulkStatusEditor();

    $response = $this->actingAs($editor)->post('/vehicles-bulk-status', [
        'vehicle_ids' => [],
        'is_active' => false,
    ]);

    $response->assertSessionHasErrors('vehicle_ids');
});

it('denies bulk vehicle status update to a user without edit-vehicles', function () {
    $user = User::create([
        'email_verified_at' => now(),
        'name' => 'No Perm',
        'username' => 'no_perm_'.uniqid(),
        'email' => 'no_perm_'.uniqid().'@example.com',
        'user_type' => 'employee',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);

    $vehicle = Vehicle::create(['brand' => 'A', 'model' => 'A1', 'registration_number' => 'BULK-'.uniqid(), 'is_active' => true]);

    $response = $this->actingAs($user)->post('/vehicles-bulk-status', [
        'vehicle_ids' => [$vehicle->id],
        'is_active' => false,
    ]);

    $response->assertForbidden();
});
