<?php

use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

function seedTripSchedulingPermissions(): void
{
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    Permission::firstOrCreate(['name' => 'create-trips', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'edit-trips', 'guard_name' => 'web']);
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
}

function makeSchedulingUser(string $username): User
{
    return User::create([
        'email_verified_at' => now(),
        'name' => 'Scheduling User '.$username,
        'username' => $username,
        'email' => $username.'@example.com',
        'user_type' => 'employee',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);
}

function makeSchedulingVehicle(array $overrides = []): Vehicle
{
    return Vehicle::create(array_merge([
        'brand' => 'Toyota',
        'model' => 'Hiace',
        'registration_number' => 'VEH-'.uniqid(),
        'is_active' => true,
    ], $overrides));
}

function tripPayload(array $overrides = []): array
{
    return array_merge([
        'vehicle_id' => null,
        'priority' => 'medium',
        'scheduled_date' => '2026-06-01',
        'scheduled_start_time' => '08:00',
        'scheduled_end_time' => '09:00',
    ], $overrides);
}

it('refuses to create a trip that overlaps another trip already booked on the same vehicle', function () {
    seedTripSchedulingPermissions();
    $requester = makeSchedulingUser('requester-1');
    $requester->givePermissionTo('create-trips');
    $vehicle = makeSchedulingVehicle();

    Trip::create([
        'trip_number' => 'TRIP-EXIST-1',
        'requested_by' => $requester->id,
        'vehicle_id' => $vehicle->id,
        'priority' => 'medium',
        'scheduled_date' => '2026-06-01',
        'scheduled_start_time' => '08:00',
        'scheduled_end_time' => '09:00',
        'status' => 'approved',
    ]);

    $response = $this->actingAs($requester)->post('/trips', tripPayload([
        'vehicle_id' => $vehicle->id,
        'scheduled_start_time' => '08:30',
        'scheduled_end_time' => '09:30',
    ]));

    $response->assertSessionHasErrors('vehicle_id');
    expect(Trip::where('trip_number', '!=', 'TRIP-EXIST-1')->count())->toBe(0);
});

it('allows creating a trip on the same vehicle for a non-overlapping time window', function () {
    seedTripSchedulingPermissions();
    $requester = makeSchedulingUser('requester-2');
    $requester->givePermissionTo('create-trips');
    $vehicle = makeSchedulingVehicle();

    Trip::create([
        'trip_number' => 'TRIP-EXIST-2',
        'requested_by' => $requester->id,
        'vehicle_id' => $vehicle->id,
        'priority' => 'medium',
        'scheduled_date' => '2026-06-01',
        'scheduled_start_time' => '08:00',
        'scheduled_end_time' => '09:00',
        'status' => 'approved',
    ]);

    $response = $this->actingAs($requester)->post('/trips', tripPayload([
        'vehicle_id' => $vehicle->id,
        'scheduled_start_time' => '09:00',
        'scheduled_end_time' => '10:00',
    ]));

    $response->assertSessionDoesntHaveErrors();
    $response->assertRedirect();
});

it('ignores a cancelled trip when checking for a vehicle double-booking', function () {
    seedTripSchedulingPermissions();
    $requester = makeSchedulingUser('requester-3');
    $requester->givePermissionTo('create-trips');
    $vehicle = makeSchedulingVehicle();

    Trip::create([
        'trip_number' => 'TRIP-CANCELLED-1',
        'requested_by' => $requester->id,
        'vehicle_id' => $vehicle->id,
        'priority' => 'medium',
        'scheduled_date' => '2026-06-01',
        'scheduled_start_time' => '08:00',
        'scheduled_end_time' => '09:00',
        'status' => 'cancelled',
    ]);

    $response = $this->actingAs($requester)->post('/trips', tripPayload([
        'vehicle_id' => $vehicle->id,
        'scheduled_start_time' => '08:00',
        'scheduled_end_time' => '09:00',
    ]));

    $response->assertSessionDoesntHaveErrors();
    $response->assertRedirect();
});

it('refuses to create a trip that seats more passengers than the vehicle allows', function () {
    seedTripSchedulingPermissions();
    $requester = makeSchedulingUser('requester-4');
    $requester->givePermissionTo('create-trips');
    $vehicle = makeSchedulingVehicle(['capacity' => 2]);

    $p1 = makeSchedulingUser('rider-1');
    $p2 = makeSchedulingUser('rider-2');
    $p3 = makeSchedulingUser('rider-3');

    $response = $this->actingAs($requester)->post('/trips', tripPayload([
        'vehicle_id' => $vehicle->id,
        'passengers' => [
            ['user_id' => $p1->id],
            ['user_id' => $p2->id],
            ['user_id' => $p3->id],
        ],
    ]));

    $response->assertSessionHasErrors('passengers');
    expect(Trip::count())->toBe(0);
});

it('allows a trip within the vehicle capacity, and skips the check when capacity is unset', function () {
    seedTripSchedulingPermissions();
    $requester = makeSchedulingUser('requester-5');
    $requester->givePermissionTo('create-trips');
    $vehicle = makeSchedulingVehicle(['capacity' => null]);

    $riders = collect(range(1, 5))->map(fn ($i) => makeSchedulingUser("rider-cap-{$i}"));

    $response = $this->actingAs($requester)->post('/trips', tripPayload([
        'vehicle_id' => $vehicle->id,
        'passengers' => $riders->map(fn ($r) => ['user_id' => $r->id])->all(),
    ]));

    $response->assertSessionDoesntHaveErrors();
    $response->assertRedirect();
});

it('does not treat a trip as conflicting with its own current row when updating', function () {
    seedTripSchedulingPermissions();
    $requester = makeSchedulingUser('requester-6');
    $requester->givePermissionTo('create-trips');
    $requester->givePermissionTo('edit-trips');
    $vehicle = makeSchedulingVehicle();

    $trip = Trip::create([
        'trip_number' => 'TRIP-SELF-1',
        'requested_by' => $requester->id,
        'vehicle_id' => $vehicle->id,
        'priority' => 'medium',
        'scheduled_date' => '2026-06-01',
        'scheduled_start_time' => '08:00',
        'scheduled_end_time' => '09:00',
        'status' => 'pending',
    ]);

    $response = $this->actingAs($requester)->put("/trips/{$trip->id}", tripPayload([
        'vehicle_id' => $vehicle->id,
        'scheduled_start_time' => '08:15',
        'scheduled_end_time' => '09:15',
    ]));

    $response->assertSessionDoesntHaveErrors();
    $response->assertRedirect();
});

it('refuses to reassign a trip onto a vehicle already booked at that time', function () {
    seedTripSchedulingPermissions();
    $requester = makeSchedulingUser('requester-7');
    $requester->givePermissionTo('create-trips');
    $requester->givePermissionTo('edit-trips');

    $vehicleA = makeSchedulingVehicle();
    $vehicleB = makeSchedulingVehicle();

    $trip = Trip::create([
        'trip_number' => 'TRIP-REASSIGN-1',
        'requested_by' => $requester->id,
        'vehicle_id' => $vehicleA->id,
        'priority' => 'medium',
        'scheduled_date' => '2026-06-01',
        'scheduled_start_time' => '08:00',
        'scheduled_end_time' => '09:00',
        'status' => 'assigned',
    ]);

    Trip::create([
        'trip_number' => 'TRIP-REASSIGN-BLOCKER',
        'requested_by' => $requester->id,
        'vehicle_id' => $vehicleB->id,
        'priority' => 'medium',
        'scheduled_date' => '2026-06-01',
        'scheduled_start_time' => '08:00',
        'scheduled_end_time' => '09:00',
        'status' => 'approved',
    ]);

    $response = $this->actingAs($requester)->post("/trips/{$trip->id}/reassign-vehicle", [
        'vehicle_id' => $vehicleB->id,
        'reason' => 'breakdown',
    ]);

    $response->assertSessionHasErrors('vehicle_id');
    $trip->refresh();
    expect($trip->vehicle_id)->toBe($vehicleA->id);
});

it('rejects the whole recurring batch if any single day would double-book the vehicle', function () {
    seedTripSchedulingPermissions();
    $requester = makeSchedulingUser('requester-8');
    $requester->givePermissionTo('create-trips');
    $vehicle = makeSchedulingVehicle();

    // Blocks the middle day (2026-06-02) of a 3-day recurring range.
    Trip::create([
        'trip_number' => 'TRIP-RECUR-BLOCKER',
        'requested_by' => $requester->id,
        'vehicle_id' => $vehicle->id,
        'priority' => 'medium',
        'scheduled_date' => '2026-06-02',
        'scheduled_start_time' => '08:00',
        'scheduled_end_time' => '09:00',
        'status' => 'approved',
    ]);

    $response = $this->actingAs($requester)->post('/trips/recurring', [
        'vehicle_id' => $vehicle->id,
        'priority' => 'medium',
        'recurring_start_date' => '2026-06-01',
        'recurring_end_date' => '2026-06-03',
        'scheduled_start_time' => '08:00',
        'scheduled_end_time' => '09:00',
    ]);

    $response->assertSessionHasErrors('vehicle_id');
    expect(Trip::where('trip_number', '!=', 'TRIP-RECUR-BLOCKER')->count())->toBe(0);
});
