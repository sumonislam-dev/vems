<?php

use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Vendor;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * TripController::store()/storeRecurring()/update() and
 * VehicleController::store() used to flash `$e->getMessage()` straight to
 * the end user on any unexpected exception during the DB transaction —
 * potentially leaking raw SQL/driver text. This covers the fix: a generic,
 * user-facing message plus the real detail going to the log instead. See
 * docs/IMPROVEMENT_PLAN_2026-09.md §2.
 */
function seedErrorHandlingPermissions(): void
{
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    Permission::firstOrCreate(['name' => 'create-trips', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'edit-trips', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'create-vehicles', 'guard_name' => 'web']);
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
}

function makeErrorHandlingUser(string $username, array $permissions = []): User
{
    $user = User::create([
        'email_verified_at' => now(),
        'name' => 'Error Handling User '.$username,
        'username' => $username.'_'.uniqid(),
        'email' => $username.'_'.uniqid().'@example.com',
        'user_type' => 'employee',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);

    if (! empty($permissions)) {
        $user->givePermissionTo($permissions);
    }

    return $user;
}

function makeErrorHandlingVehicle(array $overrides = []): Vehicle
{
    return Vehicle::create(array_merge([
        'brand' => 'Toyota',
        'model' => 'Hiace',
        'registration_number' => 'ERR-'.uniqid(),
        'is_active' => true,
    ], $overrides));
}

// A passenger array with the same user_id twice: each user_id individually
// passes the `exists:users,id` request-validation rule (there's no
// array-level uniqueness rule), but the second `trip_passengers` insert
// hits the real `trip_user_unique` DB constraint — a genuine way a user
// could hit this exception path (e.g. a double-submitted selection),
// without mocking anything.
function duplicatePassengerPayload(int $userId): array
{
    return [[
        'user_id' => $userId,
    ], [
        'user_id' => $userId,
    ]];
}

it('shows a generic message and logs the real error when creating a trip fails unexpectedly', function () {
    seedErrorHandlingPermissions();
    $requester = makeErrorHandlingUser('requester', ['create-trips']);
    $passenger = makeErrorHandlingUser('passenger');
    $vehicle = makeErrorHandlingVehicle();

    Log::spy();

    $response = $this->actingAs($requester)->post('/trips', [
        'vehicle_id' => $vehicle->id,
        'priority' => 'medium',
        'scheduled_date' => '2026-06-01',
        'scheduled_start_time' => '08:00',
        'scheduled_end_time' => '09:00',
        'passengers' => duplicatePassengerPayload($passenger->id),
    ]);

    $response->assertSessionHasErrors([
        'error' => 'Something went wrong while creating the trip. Please try again.',
    ]);
    expect(Trip::count())->toBe(0);
    Log::shouldHaveReceived('error')->once()->withArgs(
        fn ($message) => $message === 'Failed to create trip.'
    );
});

it('shows a generic message and logs the real error when creating recurring trips fails unexpectedly', function () {
    seedErrorHandlingPermissions();
    $requester = makeErrorHandlingUser('requester', ['create-trips']);
    $passenger = makeErrorHandlingUser('passenger');
    $vehicle = makeErrorHandlingVehicle();

    Log::spy();

    $response = $this->actingAs($requester)->post('/trips/recurring', [
        'vehicle_id' => $vehicle->id,
        'priority' => 'medium',
        'recurring_start_date' => '2026-06-01',
        'recurring_end_date' => '2026-06-02',
        'scheduled_start_time' => '08:00',
        'scheduled_end_time' => '09:00',
        'passengers' => duplicatePassengerPayload($passenger->id),
    ]);

    $response->assertSessionHasErrors([
        'error' => 'Something went wrong while creating the recurring trips. Please try again.',
    ]);
    expect(Trip::count())->toBe(0);
    Log::shouldHaveReceived('error')->once()->withArgs(
        fn ($message) => $message === 'Failed to create recurring trips.'
    );
});

it('shows a generic message and logs the real error when updating a trip fails unexpectedly', function () {
    seedErrorHandlingPermissions();
    $editor = makeErrorHandlingUser('editor', ['edit-trips']);
    $requester = makeErrorHandlingUser('requester');
    $passenger = makeErrorHandlingUser('passenger');
    $vehicle = makeErrorHandlingVehicle();

    $trip = Trip::create([
        'requested_by' => $requester->id,
        'vehicle_id' => $vehicle->id,
        'priority' => 'medium',
        'scheduled_date' => '2026-06-01',
        'scheduled_start_time' => '08:00',
        'scheduled_end_time' => '09:00',
        'status' => 'pending',
    ]);

    Log::spy();

    $response = $this->actingAs($editor)->put("/trips/{$trip->id}", [
        'vehicle_id' => $vehicle->id,
        'priority' => 'medium',
        'scheduled_date' => '2026-06-01',
        'scheduled_start_time' => '08:00',
        'scheduled_end_time' => '09:00',
        'passengers' => duplicatePassengerPayload($passenger->id),
    ]);

    $response->assertSessionHasErrors([
        'error' => 'Something went wrong while updating the trip. Please try again.',
    ]);
    Log::shouldHaveReceived('error')->once()->withArgs(
        fn ($message) => $message === 'Failed to update trip.'
    );
});

it('shows a generic message and logs the real error when creating a vehicle fails unexpectedly', function () {
    seedErrorHandlingPermissions();
    $actor = makeErrorHandlingUser('vehicle-actor', ['create-vehicles']);
    $vendor = Vendor::create(['name' => 'Error Handling Vendor', 'status' => 'active']);
    $driver = makeErrorHandlingUser('driver');
    $driver->forceFill(['user_type' => 'driver'])->save();

    Log::spy();

    // Simulate a genuinely unexpected failure at the exact DB-write boundary
    // the try/catch guards, without touching HTTP/validation at all.
    Vehicle::creating(function () {
        throw new RuntimeException('simulated database failure');
    });

    try {
        $response = $this->actingAs($actor)->post('/vehicles', [
            'brand' => 'Toyota',
            'model' => 'Camry',
            'registration_number' => 'ERR-'.uniqid(),
            'vehicle_type' => 'sedan',
            'rental_type' => 'own',
            'vendor_id' => $vendor->id,
            'driver_id' => $driver->id,
            'is_active' => true,
        ]);
    } finally {
        Vehicle::flushEventListeners();
    }

    $response->assertSessionHasErrors([
        'error' => 'Something went wrong while creating the vehicle. Please try again.',
    ]);
    Log::shouldHaveReceived('error')->once()->withArgs(
        fn ($message, $context) => $message === 'Error creating vehicle:'
            && str_contains($context['error'] ?? '', 'simulated database failure')
    );
});
