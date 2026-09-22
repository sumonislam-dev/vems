<?php

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

function seedDriverDocumentPermissions(): void
{
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    Permission::firstOrCreate(['name' => 'create-drivers', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'edit-drivers', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'delete-drivers', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'create-users', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'edit-users', 'guard_name' => 'web']);
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
}

function makeDriverDocumentEditor(): User
{
    $user = User::create([
        'email_verified_at' => now(),
        'name' => 'Driver Document Editor',
        'username' => 'driver_doc_'.uniqid(),
        'email' => 'driver_doc_'.uniqid().'@example.com',
        'user_type' => 'admin',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);

    $user->givePermissionTo(['create-drivers', 'edit-drivers', 'delete-drivers', 'create-users', 'edit-users']);

    return $user;
}

it('stores an uploaded driving license file when creating a driver', function () {
    Storage::fake('public');
    seedDriverDocumentPermissions();
    $editor = makeDriverDocumentEditor();
    $vendor = Vendor::create(['name' => 'V1', 'status' => 'active']);

    $response = $this->actingAs($editor)->post('/drivers', [
        'name' => 'New Driver',
        'username' => 'new_driver_'.uniqid(),
        'personal_phone' => '017',
        'whatsapp_id' => '017',
        'vendor_id' => $vendor->id,
        'status' => 'active',
        'driving_license_no' => 'DL-'.uniqid(),
        'license_class' => 'B',
        'license_expiry_date' => now()->addYear()->toDateString(),
        'driving_license_file' => UploadedFile::fake()->create('license.pdf', 500, 'application/pdf'),
        'nid_file' => UploadedFile::fake()->create('nid.jpg', 500, 'image/jpeg'),
    ]);

    $response->assertSessionDoesntHaveErrors();
    $response->assertRedirect();

    $driver = User::where('username', 'like', 'new_driver_%')->first();
    expect($driver->driving_license_file)->not->toBeNull()
        ->and($driver->nid_file)->not->toBeNull();
    Storage::disk('public')->assertExists($driver->driving_license_file);
    Storage::disk('public')->assertExists($driver->nid_file);
});

it('replaces the old driving license file and deletes it when updating', function () {
    Storage::fake('public');
    seedDriverDocumentPermissions();
    $editor = makeDriverDocumentEditor();
    $vendor = Vendor::create(['name' => 'V2', 'status' => 'active']);

    $driver = User::create([
        'email_verified_at' => now(),
        'name' => 'Existing Driver',
        'username' => 'existing_driver_'.uniqid(),
        'email' => 'existing_driver_'.uniqid().'@example.com',
        'user_type' => 'driver',
        'status' => 'active',
        'vendor_id' => $vendor->id,
        'driving_license_no' => 'DL-OLD',
        'license_class' => 'B',
        'license_expiry_date' => now()->addYear(),
        'driving_license_file' => 'driver_documents/old-license.pdf',
        'password' => Hash::make('password'),
    ]);
    Storage::disk('public')->put('driver_documents/old-license.pdf', 'old');

    $response = $this->actingAs($editor)->put("/drivers/{$driver->id}", [
        'name' => $driver->name,
        'username' => $driver->username,
        'personal_phone' => '017',
        'whatsapp_id' => '017',
        'vendor_id' => $vendor->id,
        'status' => 'active',
        'driving_license_no' => 'DL-OLD',
        'license_class' => 'B',
        'license_expiry_date' => now()->addYear()->toDateString(),
        'driving_license_file' => UploadedFile::fake()->create('new-license.pdf', 500, 'application/pdf'),
    ]);

    $response->assertSessionDoesntHaveErrors();
    $driver->refresh();
    expect($driver->driving_license_file)->not->toBe('driver_documents/old-license.pdf');
    Storage::disk('public')->assertMissing('driver_documents/old-license.pdf');
    Storage::disk('public')->assertExists($driver->driving_license_file);
});

it('deletes the driving license and NID files when a driver is destroyed', function () {
    Storage::fake('public');
    seedDriverDocumentPermissions();
    $editor = makeDriverDocumentEditor();
    $vendor = Vendor::create(['name' => 'V3', 'status' => 'active']);

    $driver = User::create([
        'email_verified_at' => now(),
        'name' => 'Deletable Driver',
        'username' => 'deletable_driver_'.uniqid(),
        'email' => 'deletable_driver_'.uniqid().'@example.com',
        'user_type' => 'driver',
        'status' => 'active',
        'vendor_id' => $vendor->id,
        'driving_license_file' => 'driver_documents/license.pdf',
        'nid_file' => 'driver_documents/nid.jpg',
        'password' => Hash::make('password'),
    ]);
    Storage::disk('public')->put('driver_documents/license.pdf', 'x');
    Storage::disk('public')->put('driver_documents/nid.jpg', 'x');

    $response = $this->actingAs($editor)->delete("/drivers/{$driver->id}");

    $response->assertRedirect();
    Storage::disk('public')->assertMissing('driver_documents/license.pdf');
    Storage::disk('public')->assertMissing('driver_documents/nid.jpg');
});
