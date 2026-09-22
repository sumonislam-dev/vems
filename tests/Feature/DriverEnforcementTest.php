<?php

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function createAuthorizedUser(array $permissionNames = ['create-users', 'create-drivers']): User
{
    $permissions = collect($permissionNames)
        ->map(fn (string $name) => Permission::firstOrCreate([
            'name' => $name,
            'guard_name' => 'web',
        ]))
        ->all();

    $authUser = User::create([
        'email_verified_at' => now(),
        'name' => 'Test Admin User',
        'username' => 'test_admin_user',
        'email' => 'test.admin.user@example.com',
        'personal_phone' => '01710000001',
        'whatsapp_id' => '01710000001',
        'status' => 'active',
        'user_type' => 'admin',
        'password' => Hash::make('password123'),
    ]);

    $authUser->givePermissionTo($permissions);

    return $authUser;
}

function createVendor(): Vendor
{
    return Vendor::create([
        'name' => 'Test Vendor',
        'status' => 'active',
    ]);
}

function validDriverPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Driver Candidate',
        'username' => 'driver_candidate',
        'employee_id' => 'EMP-DR-001',
        'email' => 'driver.candidate@example.com',
        'personal_phone' => '01711000000',
        'whatsapp_id' => '01711000000',
        'status' => 'active',
        'user_type' => 'admin',
        'vendor_id' => null,
        'driving_license_no' => 'DL-12345678',
        'license_class' => 'B',
        'license_expiry_date' => now()->addYear()->toDateString(),
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ], $overrides);
}

function createDriverUser(Vendor $vendor, array $overrides = []): User
{
    return User::create(array_merge([
        'name' => 'Existing Driver',
        'username' => 'existing_driver',
        'employee_id' => 'EMP-DR-100',
        'email' => 'existing.driver@example.com',
        'personal_phone' => '01711000099',
        'whatsapp_id' => '01711000099',
        'status' => 'active',
        'user_type' => 'driver',
        'vendor_id' => $vendor->id,
        'driving_license_no' => 'DL-87654321',
        'license_class' => 'B',
        'license_expiry_date' => now()->addYear()->toDateString(),
        'password' => Hash::make('password123'),
    ], $overrides));
}

function createNonDriverUser(array $overrides = []): User
{
    return User::create(array_merge([
        'name' => 'Non Driver User',
        'username' => 'non_driver_user',
        'employee_id' => 'EMP-ND-001',
        'email' => 'non.driver.user@example.com',
        'personal_phone' => '01711000222',
        'whatsapp_id' => '01711000222',
        'status' => 'active',
        'user_type' => 'employee',
        'password' => Hash::make('password123'),
    ], $overrides));
}

function validDriverUpdatePayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Updated Driver',
        'username' => 'updated_driver',
        'employee_id' => 'EMP-DR-101',
        'email' => 'updated.driver@example.com',
        'personal_phone' => '01711000111',
        'whatsapp_id' => '01711000111',
        'status' => 'active',
        'user_type' => 'admin',
        'vendor_id' => null,
        'driving_license_no' => 'DL-11223344',
        'license_class' => 'B',
        'license_expiry_date' => now()->addYear()->toDateString(),
    ], $overrides);
}

it('requires vendor_id for driver module even if user_type payload is tampered', function () {
    $authUser = createAuthorizedUser();

    $response = $this->actingAs($authUser)
        ->post(route('drivers.store'), validDriverPayload([
            'username' => 'driver_without_vendor',
            'email' => 'without.vendor@example.com',
            'vendor_id' => null,
            'user_type' => 'admin',
        ]));

    $response->assertSessionHasErrors(['vendor_id']);

    $this->assertDatabaseMissing('users', [
        'username' => 'driver_without_vendor',
    ]);
});

it('forces created driver user_type to driver', function () {
    $authUser = createAuthorizedUser();
    $vendor = createVendor();

    Role::firstOrCreate(['name' => 'driver', 'guard_name' => 'web']);

    $response = $this->actingAs($authUser)
        ->post(route('drivers.store'), validDriverPayload([
            'username' => 'forced_type_driver',
            'email' => 'forced.type@example.com',
            'vendor_id' => $vendor->id,
            'user_type' => 'admin',
        ]));

    $response->assertRedirect(route('drivers.index'));

    $user = User::where('username', 'forced_type_driver')->first();

    expect($user)->not->toBeNull();
    expect($user->user_type)->toBe('driver');
});

it('forces created driver role to Driver even when other roles are submitted', function () {
    $authUser = createAuthorizedUser();
    $vendor = createVendor();

    $driverRole = Role::firstOrCreate(['name' => 'driver', 'guard_name' => 'web']);
    $superAdminRole = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

    $response = $this->actingAs($authUser)
        ->post(route('drivers.store'), validDriverPayload([
            'username' => 'forced_role_driver',
            'email' => 'forced.role@example.com',
            'vendor_id' => $vendor->id,
            'roles' => [$superAdminRole->id],
            'user_type' => 'admin',
        ]));

    $response->assertRedirect(route('drivers.index'));

    $user = User::where('username', 'forced_role_driver')->firstOrFail();

    expect($user->hasRole($driverRole->name))->toBeTrue();
    expect($user->hasRole($superAdminRole->name))->toBeFalse();
});

it('requires vendor_id for driver update route even if user_type payload is tampered', function () {
    $authUser = createAuthorizedUser(['edit-users', 'edit-drivers']);
    $vendor = createVendor();
    $driver = createDriverUser($vendor);

    $response = $this->actingAs($authUser)
        ->put(route('drivers.update', $driver->id), validDriverUpdatePayload([
            'vendor_id' => null,
            'user_type' => 'admin',
        ]));

    $response->assertSessionHasErrors(['vendor_id']);
});

it('forces updated driver user_type to driver', function () {
    $authUser = createAuthorizedUser(['edit-users', 'edit-drivers']);
    $vendor = createVendor();
    $driver = createDriverUser($vendor);

    Role::firstOrCreate(['name' => 'driver', 'guard_name' => 'web']);

    $response = $this->actingAs($authUser)
        ->put(route('drivers.update', $driver->id), validDriverUpdatePayload([
            'vendor_id' => $vendor->id,
            'user_type' => 'admin',
        ]));

    $response->assertRedirect(route('drivers.index'));

    $driver->refresh();
    expect($driver->user_type)->toBe('driver');
});

it('forces updated driver role to Driver even when other roles are submitted', function () {
    $authUser = createAuthorizedUser(['edit-users', 'edit-drivers']);
    $vendor = createVendor();
    $driver = createDriverUser($vendor);

    $driverRole = Role::firstOrCreate(['name' => 'driver', 'guard_name' => 'web']);
    $superAdminRole = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
    $driver->syncRoles([$superAdminRole->id]);

    $response = $this->actingAs($authUser)
        ->put(route('drivers.update', $driver->id), validDriverUpdatePayload([
            'vendor_id' => $vendor->id,
            'roles' => [$superAdminRole->id],
            'user_type' => 'admin',
        ]));

    $response->assertRedirect(route('drivers.index'));

    $driver->refresh();
    expect($driver->hasRole($driverRole->name))->toBeTrue();
    expect($driver->hasRole($superAdminRole->name))->toBeFalse();
});

it('returns 404 when updating a non-driver user through drivers.update route', function () {
    $authUser = createAuthorizedUser(['edit-users', 'edit-drivers']);
    $vendor = createVendor();
    $nonDriver = createNonDriverUser();

    $response = $this->actingAs($authUser)
        ->put(route('drivers.update', $nonDriver->id), validDriverUpdatePayload([
            'vendor_id' => $vendor->id,
            'user_type' => 'admin',
        ]));

    $response->assertNotFound();
});
