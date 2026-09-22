<?php

use App\Models\User;
use App\Models\Vehicle;
use App\Models\Vendor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

function seedVehicleDocumentPermissions(): void
{
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    Permission::firstOrCreate(['name' => 'create-vehicles', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'edit-vehicles', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'delete-vehicles', 'guard_name' => 'web']);
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
}

function makeVehicleDocumentUser(): User
{
    $user = User::create([
        'email_verified_at' => now(),
        'name' => 'Vehicle Document User',
        'username' => 'vehicle_doc_'.uniqid(),
        'email' => 'vehicle_doc_'.uniqid().'@example.com',
        'user_type' => 'employee',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);

    $user->givePermissionTo(['create-vehicles', 'edit-vehicles', 'delete-vehicles']);

    return $user;
}

it('stores an uploaded tax token file when creating a vehicle', function () {
    Storage::fake('public');
    seedVehicleDocumentPermissions();
    $user = makeVehicleDocumentUser();
    $vendor = Vendor::create(['name' => 'V1', 'status' => 'active']);
    $driver = User::create([
        'email_verified_at' => now(), 'name' => 'Driver 1', 'username' => 'doc_driver1_'.uniqid(),
        'email' => 'doc_driver1_'.uniqid().'@example.com', 'user_type' => 'driver', 'status' => 'active',
        'password' => Hash::make('password'),
    ]);

    $response = $this->actingAs($user)->post('/vehicles', [
        'brand' => 'Toyota', 'model' => 'Camry',
        'registration_number' => 'DOC-'.uniqid(),
        'vehicle_type' => 'sedan', 'rental_type' => 'own',
        'vendor_id' => $vendor->id, 'driver_id' => $driver->id,
        'is_active' => true,
        'tax_token_file' => UploadedFile::fake()->create('tax_token.pdf', 500, 'application/pdf'),
    ]);

    $response->assertSessionDoesntHaveErrors();
    $response->assertRedirect();

    $vehicle = Vehicle::where('registration_number', 'like', 'DOC-%')->first();
    expect($vehicle->tax_token_file)->not->toBeNull();
    Storage::disk('public')->assertExists($vehicle->tax_token_file);
});

it('replaces the old file and deletes it when uploading a new one on update', function () {
    Storage::fake('public');
    seedVehicleDocumentPermissions();
    $user = makeVehicleDocumentUser();

    $vehicle = Vehicle::create([
        'brand' => 'Toyota', 'model' => 'Camry',
        'registration_number' => 'DOC-'.uniqid(),
        'is_active' => true,
        'tax_token_file' => 'vehicle_documents/old-file.pdf',
    ]);
    Storage::disk('public')->put('vehicle_documents/old-file.pdf', 'old content');

    $response = $this->actingAs($user)->put("/vehicles/{$vehicle->id}", [
        'brand' => 'Toyota', 'model' => 'Camry',
        'registration_number' => $vehicle->registration_number,
        'vehicle_type' => 'sedan', 'rental_type' => 'own',
        'is_active' => true,
        'tax_token_file' => UploadedFile::fake()->create('new_tax_token.pdf', 500, 'application/pdf'),
    ]);

    $response->assertSessionDoesntHaveErrors();
    $response->assertRedirect();

    $vehicle->refresh();
    expect($vehicle->tax_token_file)->not->toBe('vehicle_documents/old-file.pdf');
    Storage::disk('public')->assertMissing('vehicle_documents/old-file.pdf');
    Storage::disk('public')->assertExists($vehicle->tax_token_file);
});

it('keeps the existing file when no new file is uploaded on update', function () {
    Storage::fake('public');
    seedVehicleDocumentPermissions();
    $user = makeVehicleDocumentUser();

    $vehicle = Vehicle::create([
        'brand' => 'Toyota', 'model' => 'Camry',
        'registration_number' => 'DOC-'.uniqid(),
        'is_active' => true,
        'tax_token_file' => 'vehicle_documents/keep-me.pdf',
    ]);
    Storage::disk('public')->put('vehicle_documents/keep-me.pdf', 'content');

    $response = $this->actingAs($user)->put("/vehicles/{$vehicle->id}", [
        'brand' => 'Toyota', 'model' => 'Camry',
        'registration_number' => $vehicle->registration_number,
        'vehicle_type' => 'sedan', 'rental_type' => 'own',
        'is_active' => true,
    ]);

    $response->assertSessionDoesntHaveErrors();
    $vehicle->refresh();
    expect($vehicle->tax_token_file)->toBe('vehicle_documents/keep-me.pdf');
    Storage::disk('public')->assertExists('vehicle_documents/keep-me.pdf');
});

it('deletes all document files when a vehicle is destroyed', function () {
    Storage::fake('public');
    seedVehicleDocumentPermissions();
    $user = makeVehicleDocumentUser();

    $vehicle = Vehicle::create([
        'brand' => 'Toyota', 'model' => 'Camry',
        'registration_number' => 'DOC-'.uniqid(),
        'is_active' => true,
        'tax_token_file' => 'vehicle_documents/a.pdf',
        'fitness_certificate_file' => 'vehicle_documents/b.pdf',
    ]);
    Storage::disk('public')->put('vehicle_documents/a.pdf', 'x');
    Storage::disk('public')->put('vehicle_documents/b.pdf', 'x');

    $response = $this->actingAs($user)->delete("/vehicles/{$vehicle->id}");

    $response->assertRedirect();
    Storage::disk('public')->assertMissing('vehicle_documents/a.pdf');
    Storage::disk('public')->assertMissing('vehicle_documents/b.pdf');
});

it('rejects a document file that is too large or the wrong type', function () {
    Storage::fake('public');
    seedVehicleDocumentPermissions();
    $user = makeVehicleDocumentUser();
    $vendor = Vendor::create(['name' => 'V2', 'status' => 'active']);
    $driver = User::create([
        'email_verified_at' => now(), 'name' => 'Driver 2', 'username' => 'doc_driver2_'.uniqid(),
        'email' => 'doc_driver2_'.uniqid().'@example.com', 'user_type' => 'driver', 'status' => 'active',
        'password' => Hash::make('password'),
    ]);

    $response = $this->actingAs($user)->post('/vehicles', [
        'brand' => 'Toyota', 'model' => 'Camry',
        'registration_number' => 'DOC-'.uniqid(),
        'vehicle_type' => 'sedan', 'rental_type' => 'own',
        'vendor_id' => $vendor->id, 'driver_id' => $driver->id,
        'is_active' => true,
        'tax_token_file' => UploadedFile::fake()->create('too_big.pdf', 3000, 'application/pdf'),
    ]);

    $response->assertSessionHasErrors('tax_token_file');
});
