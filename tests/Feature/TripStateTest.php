<?php

use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

/**
 * Seed the permissions TripStateController's middleware checks.
 * Spatie's caching means we must flush cache when creating permissions.
 */
function seedTripStatePermissions(): void
{
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    Permission::firstOrCreate(['name' => 'approve-trips', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'edit-trips', 'guard_name' => 'web']);
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
}

function makeStateVehicle(array $overrides = []): Vehicle
{
    return Vehicle::create(array_merge([
        'brand' => 'Toyota',
        'model' => 'Hiace',
        'registration_number' => 'VEH-' . uniqid(),
        'is_active' => true,
    ], $overrides));
}

function makeTripStateUser(string $username): User
{
    return User::create([
        'email_verified_at' => now(),
        'name' => 'Trip Manager ' . $username,
        'username' => $username,
        'email' => $username . '@example.com',
        'user_type' => 'transport_manager',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);
}

function makeStateTrip(array $overrides = []): Trip
{
    $requester = makeTripStateUser('requester-' . uniqid());

    return Trip::create(array_merge([
        'trip_number' => 'TRIP-' . uniqid(),
        'requested_by' => $requester->id,
        'priority' => 'medium',
        'scheduled_date' => '2026-05-03',
        'scheduled_start_time' => '08:00:00',
        'scheduled_end_time' => '09:00:00',
        'status' => 'pending',
    ], $overrides));
}

it('approves a pending trip and records the approver', function () {
    seedTripStatePermissions();
    $manager = makeTripStateUser('approver-1');
    $manager->givePermissionTo('approve-trips');

    $trip = makeStateTrip();

    $response = $this->actingAs($manager)->post("/trips/{$trip->id}/approve");
    $response->assertRedirect();

    $trip->refresh();
    expect($trip->status)->toBe('approved')
        ->and($trip->approved_by)->toBe($manager->id);
});

it('rejects a pending trip and records the rejection reason', function () {
    seedTripStatePermissions();
    $manager = makeTripStateUser('rejecter-1');
    $manager->givePermissionTo('approve-trips');

    $trip = makeStateTrip();

    $response = $this->actingAs($manager)->post("/trips/{$trip->id}/reject", [
        'rejection_reason' => 'Vehicle unavailable',
    ]);
    $response->assertRedirect();

    $trip->refresh();
    expect($trip->status)->toBe('rejected')
        ->and($trip->rejection_reason)->toBe('Vehicle unavailable');
});

it('refuses to approve a trip that is not pending', function () {
    seedTripStatePermissions();
    $manager = makeTripStateUser('approver-2');
    $manager->givePermissionTo('approve-trips');

    $trip = makeStateTrip(['status' => 'approved']);

    $response = $this->actingAs($manager)->post("/trips/{$trip->id}/approve");
    $response->assertRedirect();

    $trip->refresh();
    expect($trip->status)->toBe('approved');
    $this->assertDatabaseMissing('trip_audit_logs', ['trip_id' => $trip->id, 'action' => 'status_changed']);
});

it('starts an approved trip and records the odometer reading', function () {
    seedTripStatePermissions();
    $manager = makeTripStateUser('starter-1');
    $manager->givePermissionTo('edit-trips');

    $trip = makeStateTrip(['status' => 'approved']);

    $response = $this->actingAs($manager)->post("/trips/{$trip->id}/start", [
        'odometer_start' => 1000,
    ]);
    $response->assertRedirect();

    $trip->refresh();
    expect($trip->status)->toBe('in_progress')
        ->and((float) $trip->odometer_start)->toBe(1000.0)
        ->and($trip->actual_start_time)->not->toBeNull()
        ->and($trip->start_time)->not->toBeNull();
});

it('completing a trip sets is_completed and end_time, not just actual_end_time (regression for bug 1.1)', function () {
    seedTripStatePermissions();
    $manager = makeTripStateUser('completer-1');
    $manager->givePermissionTo('edit-trips');

    $trip = makeStateTrip([
        'status' => 'in_progress',
        'odometer_start' => 1000,
    ]);

    $response = $this->actingAs($manager)->post("/trips/{$trip->id}/complete", [
        'odometer_end' => 1050,
        'fuel_cost' => 500,
        'other_costs' => 100,
    ]);
    $response->assertRedirect();

    $trip->refresh();
    expect($trip->status)->toBe('completed')
        ->and($trip->is_completed)->toBeTrue()
        ->and($trip->end_time)->not->toBeNull()
        ->and($trip->actual_end_time)->not->toBeNull()
        ->and((float) $trip->total_cost)->toBe(600.0);
});

it('completing a trip credits the assigned driver distance and trip count', function () {
    seedTripStatePermissions();
    $manager = makeTripStateUser('completer-2');
    $manager->givePermissionTo('edit-trips');

    $driver = makeTripStateUser('driver-1');
    $driver->update(['total_distance_covered' => 10, 'total_trips_completed' => 2]);

    $vehicle = Vehicle::create([
        'brand' => 'Toyota',
        'model' => 'Hiace',
        'registration_number' => 'VEH-' . uniqid(),
        'driver_id' => $driver->id,
        'is_active' => true,
    ]);

    $trip = makeStateTrip([
        'status' => 'in_progress',
        'vehicle_id' => $vehicle->id,
        'odometer_start' => 1000,
    ]);

    $this->actingAs($manager)->post("/trips/{$trip->id}/complete", [
        'odometer_end' => 1075,
    ])->assertRedirect();

    $driver->refresh();
    expect((float) $driver->total_distance_covered)->toBe(85.0)
        ->and($driver->total_trips_completed)->toBe(3);
});

it('rejects fuel/cost figures above the sanity bound (2.4)', function () {
    seedTripStatePermissions();
    $manager = makeTripStateUser('completer-3');
    $manager->givePermissionTo('edit-trips');

    $trip = makeStateTrip(['status' => 'in_progress', 'odometer_start' => 1000]);

    $response = $this->actingAs($manager)->post("/trips/{$trip->id}/complete", [
        'odometer_end' => 1050,
        'fuel_cost' => 99999999,
    ]);
    $response->assertSessionHasErrors('fuel_cost');

    $trip->refresh();
    expect($trip->status)->toBe('in_progress');
});

it('cancels a trip and records the reason, canceller, and timestamp (regression for bug 1.3)', function () {
    seedTripStatePermissions();
    $manager = makeTripStateUser('canceller-1');
    $manager->givePermissionTo('edit-trips');

    $trip = makeStateTrip(['status' => 'approved']);

    $response = $this->actingAs($manager)->post("/trips/{$trip->id}/cancel", [
        'cancellation_reason' => 'vehicle_breakdown',
        'cancellation_notes' => 'Engine failure reported by driver.',
    ]);
    $response->assertRedirect();

    $trip->refresh();
    expect($trip->status)->toBe('cancelled')
        ->and($trip->cancellation_reason)->toBe('vehicle_breakdown')
        ->and($trip->cancellation_notes)->toBe('Engine failure reported by driver.')
        ->and($trip->cancelled_by)->toBe($manager->id)
        ->and($trip->cancelled_at)->not->toBeNull();
});

it('refuses to cancel a trip without a cancellation reason', function () {
    seedTripStatePermissions();
    $manager = makeTripStateUser('canceller-2');
    $manager->givePermissionTo('edit-trips');

    $trip = makeStateTrip(['status' => 'approved']);

    $response = $this->actingAs($manager)->post("/trips/{$trip->id}/cancel", []);
    $response->assertSessionHasErrors('cancellation_reason');

    $trip->refresh();
    expect($trip->status)->toBe('approved');
});

it('refuses to cancel an already-completed trip', function () {
    seedTripStatePermissions();
    $manager = makeTripStateUser('canceller-3');
    $manager->givePermissionTo('edit-trips');

    $trip = makeStateTrip(['status' => 'completed']);

    $response = $this->actingAs($manager)->post("/trips/{$trip->id}/cancel", [
        'cancellation_reason' => 'other',
    ]);
    $response->assertRedirect();

    $trip->refresh();
    expect($trip->status)->toBe('completed');
});

it('denies trip state actions to users without the required permission', function () {
    seedTripStatePermissions();
    $unauthorized = makeTripStateUser('unauthorized-1');

    $trip = makeStateTrip();

    $this->actingAs($unauthorized)->post("/trips/{$trip->id}/approve")->assertForbidden();
    $this->actingAs($unauthorized)->post("/trips/{$trip->id}/start")->assertForbidden();
});

it('lets the driver assigned to the trip vehicle start and complete the trip without edit-trips (3.4)', function () {
    seedTripStatePermissions();
    $driver = makeTripStateUser('assigned-driver-1');

    $vehicle = Vehicle::create([
        'brand' => 'Toyota',
        'model' => 'Hiace',
        'registration_number' => 'VEH-' . uniqid(),
        'driver_id' => $driver->id,
        'is_active' => true,
    ]);

    $trip = makeStateTrip(['status' => 'approved', 'vehicle_id' => $vehicle->id]);

    $this->actingAs($driver)->post("/trips/{$trip->id}/start", ['odometer_start' => 500])->assertRedirect();
    $trip->refresh();
    expect($trip->status)->toBe('in_progress');

    $this->actingAs($driver)->post("/trips/{$trip->id}/complete", ['odometer_end' => 540])->assertRedirect();
    $trip->refresh();
    expect($trip->status)->toBe('completed')->and($trip->is_completed)->toBeTrue();
});

it('records the real reason/notes on a reassigned vehicle in one write (regression)', function () {
    seedTripStatePermissions();
    $manager = makeTripStateUser('reassigner-1');
    $manager->givePermissionTo('edit-trips');

    $oldVehicle = makeStateVehicle();
    $newVehicle = makeStateVehicle();
    $trip = makeStateTrip(['status' => 'assigned', 'vehicle_id' => $oldVehicle->id]);

    $response = $this->actingAs($manager)->post("/trips/{$trip->id}/reassign-vehicle", [
        'vehicle_id' => $newVehicle->id,
        'reason' => 'breakdown',
        'notes' => 'Old vehicle broke down on the highway.',
    ]);
    $response->assertRedirect();

    $trip->refresh();
    expect($trip->vehicle_id)->toBe($newVehicle->id);

    $current = $trip->vehicleAssignments()->where('is_current', true)->first();
    expect($current)->not->toBeNull()
        ->and($current->vehicle_id)->toBe($newVehicle->id)
        ->and($current->reason)->toBe('breakdown')
        ->and($current->notes)->toBe('Old vehicle broke down on the highway.');

    expect($trip->vehicleAssignments()->where('is_current', true)->count())->toBe(1);
});

it('refuses start/complete to a driver not assigned to the trip vehicle', function () {
    seedTripStatePermissions();
    $assignedDriver = makeTripStateUser('assigned-driver-2');
    $otherDriver = makeTripStateUser('other-driver-1');

    $vehicle = Vehicle::create([
        'brand' => 'Toyota',
        'model' => 'Hiace',
        'registration_number' => 'VEH-' . uniqid(),
        'driver_id' => $assignedDriver->id,
        'is_active' => true,
    ]);

    $trip = makeStateTrip(['status' => 'approved', 'vehicle_id' => $vehicle->id]);

    $this->actingAs($otherDriver)->post("/trips/{$trip->id}/start", ['odometer_start' => 500])->assertForbidden();

    $trip->refresh();
    expect($trip->status)->toBe('approved');
});
