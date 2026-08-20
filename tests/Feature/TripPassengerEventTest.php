<?php

use App\Models\Trip;
use App\Models\TripPassenger;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

/**
 * Seed the permissions required for attendance tests.
 * Spatie's caching means we must flush cache when creating permissions.
 */
function seedAttendancePermissions(): void
{
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    Permission::firstOrCreate(['name' => 'capture-passenger-attendance', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'correct-passenger-attendance', 'guard_name' => 'web']);
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
}

/**
 * TripPassengerController::authorizeAttendanceAction() requires the acting user
 * to be the trip's assigned driver (or hold edit-trips) — build a vehicle
 * assigned to $driver so attendance-capture tests reflect real trip shape.
 */
function assignVehicleWithDriver(User $driver): Vehicle
{
    return Vehicle::create([
        'brand' => 'Toyota',
        'model' => 'Hiace',
        'registration_number' => 'VEH-' . uniqid(),
        'driver_id' => $driver->id,
        'is_active' => true,
    ]);
}

it('records passenger attendance events while updating the trip passenger snapshot', function () {
    $requester = User::create([
        'name' => 'Trip Requester',
        'username' => 'trip-requester',
        'email' => 'requester@example.com',
        'user_type' => 'admin',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);

    $passengerUser = User::create([
        'name' => 'Trip Passenger',
        'username' => 'trip-passenger',
        'email' => 'passenger@example.com',
        'user_type' => 'employee',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);

    $trip = Trip::create([
        'trip_number' => 'TRIP-1001',
        'requested_by' => $requester->id,
        'priority' => 'medium',
        'scheduled_date' => '2026-05-03',
        'scheduled_start_time' => '08:00:00',
        'scheduled_end_time' => '09:00:00',
        'status' => 'pending',
    ]);

    $tripPassenger = TripPassenger::create([
        'trip_id' => $trip->id,
        'user_id' => $passengerUser->id,
        'status' => 'pending',
    ]);

    $tripPassenger->markAsBoarded([
        'event_time' => '2026-05-03 08:05:00',
        'latitude' => 23.8103,
        'longitude' => 90.4125,
        'ip_address' => '203.0.113.10',
        'area_name' => 'Gate 1',
        'source' => 'mobile',
        'device_id' => 'device-1',
        'metadata' => ['accuracy' => 'high'],
    ]);

    $tripPassenger->markAsDropped([
        'event_time' => '2026-05-03 08:55:00',
        'area_name' => 'Factory Yard',
        'source' => 'mobile',
    ]);

    $tripPassenger->refresh();

    expect($tripPassenger->status)->toBe('completed')
        ->and($tripPassenger->boarded_at?->format('Y-m-d H:i:s'))->toBe('2026-05-03 08:05:00')
        ->and($tripPassenger->dropped_at?->format('Y-m-d H:i:s'))->toBe('2026-05-03 08:55:00')
        ->and($tripPassenger->passengerEvents()->count())->toBe(2);

    $this->assertDatabaseHas('trip_passenger_events', [
        'trip_passenger_id' => $tripPassenger->id,
        'trip_id' => $trip->id,
        'user_id' => $passengerUser->id,
        'event_type' => 'check_in',
        'ip_address' => '203.0.113.10',
        'area_name' => 'Gate 1',
        'source' => 'mobile',
        'device_id' => 'device-1',
    ]);

    $this->assertDatabaseHas('trip_passenger_events', [
        'trip_passenger_id' => $tripPassenger->id,
        'event_type' => 'check_out',
        'area_name' => 'Factory Yard',
        'source' => 'mobile',
    ]);
});

it('captures passenger check-in through the trip attendance endpoint', function () {
    seedAttendancePermissions();

    $requester = User::create([
        'name' => 'Trip Requester',
        'username' => 'trip-requester-web',
        'email' => 'requester-web@example.com',
        'user_type' => 'admin',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);

    // Grant the capture permission
    $requester->givePermissionTo('capture-passenger-attendance');
    $vehicle = assignVehicleWithDriver($requester);

    $passengerUser = User::create([
        'name' => 'Trip Passenger Web',
        'username' => 'trip-passenger-web',
        'email' => 'passenger-web@example.com',
        'user_type' => 'employee',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);

    $trip = Trip::create([
        'trip_number' => 'TRIP-1002',
        'requested_by' => $requester->id,
        'vehicle_id' => $vehicle->id,
        'priority' => 'medium',
        'scheduled_date' => '2026-05-03',
        'scheduled_start_time' => '08:00:00',
        'scheduled_end_time' => '09:00:00',
        'status' => 'pending',
    ]);

    $tripPassenger = TripPassenger::create([
        'trip_id' => $trip->id,
        'user_id' => $passengerUser->id,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($requester)->post("/trips/{$trip->id}/passengers/{$tripPassenger->id}/check-in", [
        'event_time' => '2026-05-03 08:12:00',
        'latitude' => 23.8103,
        'longitude' => 90.4125,
        'area_name' => 'North Gate',
        'device_id' => 'driver-phone-1',
        'idempotency_key' => 'trip-checkin-1002-1',
    ]);

    $response->assertRedirect();

    $tripPassenger->refresh();

    expect($tripPassenger->status)->toBe('boarded')
        ->and($tripPassenger->boarded_at?->format('Y-m-d H:i:s'))->toBe('2026-05-03 08:12:00');

    $this->assertDatabaseHas('trip_passenger_events', [
        'trip_passenger_id' => $tripPassenger->id,
        'event_type' => 'check_in',
        'area_name' => 'North Gate',
        'source' => 'web',
        'ip_address' => '127.0.0.1',
        'device_id' => 'driver-phone-1',
        'idempotency_key' => 'trip-checkin-1002-1',
    ]);
});

it('voids the original event and supersedes it when an attendance correction is submitted', function () {
    seedAttendancePermissions();

    $requester = User::create([
        'name' => 'Trip Corrector',
        'username' => 'trip-corrector',
        'email' => 'corrector@example.com',
        'user_type' => 'admin',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);

    // Grant the correction permission
    $requester->givePermissionTo('correct-passenger-attendance');
    $vehicle = assignVehicleWithDriver($requester);

    $passengerUser = User::create([
        'name' => 'Trip Passenger Corrected',
        'username' => 'trip-passenger-corrected',
        'email' => 'passenger-corrected@example.com',
        'user_type' => 'employee',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);

    $trip = Trip::create([
        'trip_number' => 'TRIP-1003',
        'requested_by' => $requester->id,
        'vehicle_id' => $vehicle->id,
        'priority' => 'medium',
        'scheduled_date' => '2026-05-03',
        'scheduled_start_time' => '08:00:00',
        'scheduled_end_time' => '09:00:00',
        'status' => 'pending',
    ]);

    $tripPassenger = TripPassenger::create([
        'trip_id' => $trip->id,
        'user_id' => $passengerUser->id,
        'status' => 'pending',
    ]);

    $originalEvent = $tripPassenger->markAsBoarded([
        'event_time' => '2026-05-03 08:05:00',
        'area_name' => 'Gate 1',
        'source' => 'mobile',
    ]);

    $response = $this->actingAs($requester)->post("/trips/{$trip->id}/passengers/{$tripPassenger->id}/events/{$originalEvent->id}/correct", [
        'event_type' => 'check_in',
        'event_time' => '2026-05-03 08:07:00',
        'area_name' => 'Gate 2',
        'reason' => 'Corrected delayed sync timestamp',
    ]);

    $response->assertRedirect();

    $tripPassenger->refresh();
    $originalEvent->refresh();
    $replacementEvent = $tripPassenger->passengerEvents()->latest('id')->first();

    expect($tripPassenger->status)->toBe('boarded')
        ->and($tripPassenger->boarded_at?->format('Y-m-d H:i:s'))->toBe('2026-05-03 08:07:00')
        ->and($originalEvent->is_valid)->toBeFalse()
        ->and($originalEvent->superseded_by_event_id)->toBe($replacementEvent->id)
        ->and($replacementEvent->area_name)->toBe('Gate 2')
        ->and($replacementEvent->source)->toBe('admin_correction');

    $this->assertDatabaseHas('trip_passenger_events', [
        'id' => $originalEvent->id,
        'is_valid' => 0,
        'void_reason' => 'Corrected delayed sync timestamp',
    ]);
});

it('denies access to check-in endpoint for users without capture-passenger-attendance permission', function () {
    seedAttendancePermissions();

    // Create users
    $unauthorized = User::create([
        'name' => 'Unauthorized User',
        'username' => 'unauthorized-1',
        'email' => 'unauthorized1@example.com',
        'user_type' => 'employee',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);

    $requester = User::create([
        'name' => 'Trip Requester',
        'username' => 'trip-requester-auth-1',
        'email' => 'requester-auth-1@example.com',
        'user_type' => 'admin',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);

    $passengerUser = User::create([
        'name' => 'Trip Passenger Auth',
        'username' => 'trip-passenger-auth-1',
        'email' => 'passenger-auth-1@example.com',
        'user_type' => 'employee',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);

    $trip = Trip::create([
        'trip_number' => 'TRIP-AUTH-1',
        'requested_by' => $requester->id,
        'priority' => 'medium',
        'scheduled_date' => '2026-05-03',
        'scheduled_start_time' => '08:00:00',
        'scheduled_end_time' => '09:00:00',
        'status' => 'pending',
    ]);

    $tripPassenger = TripPassenger::create([
        'trip_id' => $trip->id,
        'user_id' => $passengerUser->id,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($unauthorized)->post("/trips/{$trip->id}/passengers/{$tripPassenger->id}/check-in", [
        'event_time' => '2026-05-03 08:12:00',
        'latitude' => 23.8103,
        'longitude' => 90.4125,
        'area_name' => 'North Gate',
        'device_id' => 'driver-phone-1',
    ]);

    $response->assertForbidden();
});

it('allows access to check-in endpoint for users with capture-passenger-attendance permission', function () {
    seedAttendancePermissions();

    // Create users and assign permission
    $authorized = User::create([
        'name' => 'Authorized User',
        'username' => 'authorized-1',
        'email' => 'authorized1@example.com',
        'user_type' => 'driver',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);

    $authorized->givePermissionTo('capture-passenger-attendance');
    $vehicle = assignVehicleWithDriver($authorized);

    $requester = User::create([
        'name' => 'Trip Requester Auth Allowed',
        'username' => 'trip-requester-auth-allowed',
        'email' => 'requester-auth-allowed@example.com',
        'user_type' => 'admin',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);

    $passengerUser = User::create([
        'name' => 'Trip Passenger Auth Allowed',
        'username' => 'trip-passenger-auth-allowed',
        'email' => 'passenger-auth-allowed@example.com',
        'user_type' => 'employee',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);

    $trip = Trip::create([
        'trip_number' => 'TRIP-AUTH-ALLOWED',
        'requested_by' => $requester->id,
        'vehicle_id' => $vehicle->id,
        'priority' => 'medium',
        'scheduled_date' => '2026-05-03',
        'scheduled_start_time' => '08:00:00',
        'scheduled_end_time' => '09:00:00',
        'status' => 'pending',
    ]);

    $tripPassenger = TripPassenger::create([
        'trip_id' => $trip->id,
        'user_id' => $passengerUser->id,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($authorized)->post("/trips/{$trip->id}/passengers/{$tripPassenger->id}/check-in", [
        'event_time' => '2026-05-03 08:12:00',
        'latitude' => 23.8103,
        'longitude' => 90.4125,
        'area_name' => 'North Gate',
        'device_id' => 'driver-phone-1',
    ]);

    $response->assertRedirect();

    $tripPassenger->refresh();
    expect($tripPassenger->status)->toBe('boarded');
});

it('denies access to correct-event endpoint for users without correct-passenger-attendance permission', function () {
    seedAttendancePermissions();

    // Create users
    $unauthorized = User::create([
        'name' => 'Unauthorized Corrector',
        'username' => 'unauthorized-corrector',
        'email' => 'unauthorized-corrector@example.com',
        'user_type' => 'employee',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);

    $requester = User::create([
        'name' => 'Trip Requester Correct',
        'username' => 'trip-requester-correct',
        'email' => 'requester-correct@example.com',
        'user_type' => 'admin',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);

    $passengerUser = User::create([
        'name' => 'Trip Passenger Correct',
        'username' => 'trip-passenger-correct',
        'email' => 'passenger-correct@example.com',
        'user_type' => 'employee',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);

    $trip = Trip::create([
        'trip_number' => 'TRIP-CORRECT-UNAUTH',
        'requested_by' => $requester->id,
        'priority' => 'medium',
        'scheduled_date' => '2026-05-03',
        'scheduled_start_time' => '08:00:00',
        'scheduled_end_time' => '09:00:00',
        'status' => 'pending',
    ]);

    $tripPassenger = TripPassenger::create([
        'trip_id' => $trip->id,
        'user_id' => $passengerUser->id,
        'status' => 'pending',
    ]);

    $event = $tripPassenger->markAsBoarded([
        'event_time' => '2026-05-03 08:05:00',
        'area_name' => 'Gate 1',
        'source' => 'mobile',
    ]);

    $response = $this->actingAs($unauthorized)->post("/trips/{$trip->id}/passengers/{$tripPassenger->id}/events/{$event->id}/correct", [
        'event_type' => 'check_in',
        'event_time' => '2026-05-03 08:07:00',
        'area_name' => 'Gate 2',
        'reason' => 'Corrected timestamp',
    ]);

    $response->assertForbidden();
});

it('allows access to correct-event endpoint for users with correct-passenger-attendance permission', function () {
    seedAttendancePermissions();

    // Create users and assign permission
    $authorized = User::create([
        'name' => 'Authorized Corrector',
        'username' => 'authorized-corrector',
        'email' => 'authorized-corrector@example.com',
        'user_type' => 'admin',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);

    $authorized->givePermissionTo('correct-passenger-attendance');
    $vehicle = assignVehicleWithDriver($authorized);

    $requester = User::create([
        'name' => 'Trip Requester Correct Auth',
        'username' => 'trip-requester-correct-auth',
        'email' => 'requester-correct-auth@example.com',
        'user_type' => 'admin',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);

    $passengerUser = User::create([
        'name' => 'Trip Passenger Correct Auth',
        'username' => 'trip-passenger-correct-auth',
        'email' => 'passenger-correct-auth@example.com',
        'user_type' => 'employee',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);

    $trip = Trip::create([
        'trip_number' => 'TRIP-CORRECT-AUTH',
        'requested_by' => $requester->id,
        'vehicle_id' => $vehicle->id,
        'priority' => 'medium',
        'scheduled_date' => '2026-05-03',
        'scheduled_start_time' => '08:00:00',
        'scheduled_end_time' => '09:00:00',
        'status' => 'pending',
    ]);

    $tripPassenger = TripPassenger::create([
        'trip_id' => $trip->id,
        'user_id' => $passengerUser->id,
        'status' => 'pending',
    ]);

    $event = $tripPassenger->markAsBoarded([
        'event_time' => '2026-05-03 08:05:00',
        'area_name' => 'Gate 1',
        'source' => 'mobile',
    ]);

    $response = $this->actingAs($authorized)->post("/trips/{$trip->id}/passengers/{$tripPassenger->id}/events/{$event->id}/correct", [
        'event_type' => 'check_in',
        'event_time' => '2026-05-03 08:07:00',
        'area_name' => 'Gate 2',
        'reason' => 'Corrected timestamp',
    ]);

    $response->assertRedirect();

    $event->refresh();
    expect($event->is_valid)->toBeFalse();
});

it('denies check-in on a trip whose vehicle is assigned to a different driver', function () {
    seedAttendancePermissions();

    $otherDriversTrip = assignVehicleWithDriver(User::create([
        'name' => 'Other Driver',
        'username' => 'other-driver-attendance',
        'email' => 'other-driver-attendance@example.com',
        'user_type' => 'driver',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]));

    $unrelatedDriver = User::create([
        'name' => 'Unrelated Driver',
        'username' => 'unrelated-driver-attendance',
        'email' => 'unrelated-driver-attendance@example.com',
        'user_type' => 'driver',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);
    $unrelatedDriver->givePermissionTo('capture-passenger-attendance');

    $requester = User::create([
        'name' => 'Requester Attendance Ownership',
        'username' => 'requester-attendance-ownership',
        'email' => 'requester-attendance-ownership@example.com',
        'user_type' => 'admin',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);

    $trip = Trip::create([
        'trip_number' => 'TRIP-OWNERSHIP-1',
        'requested_by' => $requester->id,
        'vehicle_id' => $otherDriversTrip->id,
        'priority' => 'medium',
        'scheduled_date' => '2026-05-03',
        'scheduled_start_time' => '08:00:00',
        'scheduled_end_time' => '09:00:00',
        'status' => 'pending',
    ]);

    $tripPassenger = TripPassenger::create([
        'trip_id' => $trip->id,
        'user_id' => $requester->id,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($unrelatedDriver)->post("/trips/{$trip->id}/passengers/{$tripPassenger->id}/check-in", [
        'event_time' => '2026-05-03 08:12:00',
    ]);

    $response->assertForbidden();

    $tripPassenger->refresh();
    expect($tripPassenger->status)->toBe('pending');
});
