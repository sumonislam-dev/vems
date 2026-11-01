<?php

use App\Models\Department;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

function seedTripSchedulingPermissions(): void
{
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    Permission::firstOrCreate(['name' => 'create-trips', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'edit-trips', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'assign-vehicles', 'guard_name' => 'web']);
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
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
        'trip_type' => 'inspection',
        'scheduled_date' => '2026-06-01',
        'scheduled_start_time' => '08:00',
        'scheduled_end_time' => '09:00',
    ], $overrides);
}

it('mirrors trip_type onto schedule_type, since the form has no field for it (regression)', function () {
    seedTripSchedulingPermissions();
    $requester = makeSchedulingUser('requester-schedule-type');
    $requester->givePermissionTo(['create-trips', 'edit-trips']);

    $this->actingAs($requester)->post('/trips', tripPayload(['trip_type' => 'drop-off']))->assertSessionDoesntHaveErrors();
    $trip = Trip::where('requested_by', $requester->id)->latest('id')->firstOrFail();
    expect($trip->schedule_type)->toBe('drop-off');

    $this->actingAs($requester)->put("/trips/{$trip->id}", tripPayload(['trip_type' => 'training']))->assertSessionDoesntHaveErrors();
    expect($trip->fresh()->schedule_type)->toBe('training');
});

it('mirrors trip_type onto schedule_type for every trip in a recurring batch (regression)', function () {
    seedTripSchedulingPermissions();
    $requester = makeSchedulingUser('requester-schedule-type-recurring');
    $requester->givePermissionTo('create-trips');

    $this->actingAs($requester)->post('/trips/recurring', [
        'priority' => 'medium',
        'trip_type' => 'officials',
        'recurring_start_date' => '2026-06-01',
        'recurring_end_date' => '2026-06-02',
        'scheduled_start_time' => '08:00',
        'scheduled_end_time' => '09:00',
    ])->assertSessionDoesntHaveErrors();

    expect(Trip::where('requested_by', $requester->id)->pluck('schedule_type')->unique()->all())->toBe(['officials']);
});

it('refuses to create a trip that overlaps another trip already booked on the same vehicle', function () {
    seedTripSchedulingPermissions();
    $requester = makeSchedulingUser('requester-1');
    $requester->givePermissionTo(['create-trips', 'assign-vehicles']);
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
    $requester->givePermissionTo(['create-trips', 'assign-vehicles']);
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
    $requester->givePermissionTo(['create-trips', 'assign-vehicles']);
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
    $requester->givePermissionTo(['create-trips', 'assign-vehicles']);
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

it('allows a pick-up/drop-off trip to seat more passengers than the vehicle capacity (regression)', function () {
    seedTripSchedulingPermissions();
    $requester = makeSchedulingUser('requester-pickup');
    $requester->givePermissionTo(['create-trips', 'assign-vehicles']);
    $vehicle = makeSchedulingVehicle(['capacity' => 2]);

    $riders = collect(range(1, 5))->map(fn ($i) => makeSchedulingUser("rider-pickup-{$i}"));

    $slots = ['pick-up' => ['08:00', '09:00'], 'drop-off' => ['17:00', '18:00']];
    foreach ($slots as $tripType => [$start, $end]) {
        $response = $this->actingAs($requester)->post('/trips', tripPayload([
            'vehicle_id' => $vehicle->id,
            'trip_type' => $tripType,
            'scheduled_start_time' => $start,
            'scheduled_end_time' => $end,
            'passengers' => $riders->map(fn ($r) => ['user_id' => $r->id])->all(),
        ]));

        $response->assertSessionDoesntHaveErrors();
        $response->assertRedirect();
    }
});

it('counts department headcount slots toward vehicle capacity, alongside named passengers (regression)', function () {
    seedTripSchedulingPermissions();
    $requester = makeSchedulingUser('requester-headcount');
    $requester->givePermissionTo(['create-trips', 'assign-vehicles']);
    $vehicle = makeSchedulingVehicle(['capacity' => 4]);
    $department = Department::create(['name' => 'Headcount Dept', 'code' => 'HCD']);

    $p1 = makeSchedulingUser('headcount-rider-1');
    $p2 = makeSchedulingUser('headcount-rider-2');

    // 2 named passengers + 3 department headcount = 5, over the 4-seat capacity.
    $response = $this->actingAs($requester)->post('/trips', tripPayload([
        'vehicle_id' => $vehicle->id,
        'passengers' => [['user_id' => $p1->id], ['user_id' => $p2->id]],
        'department_slots' => [['department_id' => $department->id, 'count' => 3]],
    ]));

    $response->assertSessionHasErrors('passengers');
    expect(Trip::count())->toBe(0);

    // 2 named passengers + 2 department headcount = 4, exactly at capacity.
    $response = $this->actingAs($requester)->post('/trips', tripPayload([
        'vehicle_id' => $vehicle->id,
        'passengers' => [['user_id' => $p1->id], ['user_id' => $p2->id]],
        'department_slots' => [['department_id' => $department->id, 'count' => 2]],
    ]));

    $response->assertSessionDoesntHaveErrors();
    $response->assertRedirect();
});

it('allows a pick-up/drop-off trip to exceed vehicle capacity via department headcount too (regression)', function () {
    seedTripSchedulingPermissions();
    $requester = makeSchedulingUser('requester-headcount-pickup');
    $requester->givePermissionTo(['create-trips', 'assign-vehicles']);
    $vehicle = makeSchedulingVehicle(['capacity' => 2]);
    $department = Department::create(['name' => 'Headcount Pickup Dept', 'code' => 'HPD']);

    $response = $this->actingAs($requester)->post('/trips', tripPayload([
        'vehicle_id' => $vehicle->id,
        'trip_type' => 'pick-up',
        'department_slots' => [['department_id' => $department->id, 'count' => 10]],
    ]));

    $response->assertSessionDoesntHaveErrors();
    $response->assertRedirect();
});

it('falls back to the saved department headcount when updating a trip without resubmitting department_slots (regression)', function () {
    seedTripSchedulingPermissions();
    $requester = makeSchedulingUser('requester-headcount-update');
    $requester->givePermissionTo(['create-trips', 'edit-trips', 'assign-vehicles']);
    $vehicle = makeSchedulingVehicle(['capacity' => 3]);
    $department = Department::create(['name' => 'Headcount Update Dept', 'code' => 'HUD']);

    $trip = Trip::create([
        'trip_number' => 'TRIP-HEADCOUNT-UPDATE',
        'requested_by' => $requester->id,
        'vehicle_id' => $vehicle->id,
        'priority' => 'medium',
        'trip_type' => 'inspection',
        'scheduled_date' => '2026-06-01',
        'scheduled_start_time' => '08:00',
        'scheduled_end_time' => '09:00',
        'status' => 'pending',
    ]);
    $trip->departments()->sync([$department->id => ['count' => 3]]);

    // Omitting department_slots should keep the existing 3 seats booked,
    // so adding 1 named passenger now pushes the trip over the 3-seat capacity.
    $response = $this->actingAs($requester)->put("/trips/{$trip->id}", tripPayload([
        'vehicle_id' => $vehicle->id,
        'passengers' => [['user_id' => makeSchedulingUser('headcount-update-rider')->id]],
    ]));

    $response->assertSessionHasErrors('passengers');
});

it('allows a trip within the vehicle capacity, and skips the check when capacity is unset', function () {
    seedTripSchedulingPermissions();
    $requester = makeSchedulingUser('requester-5');
    $requester->givePermissionTo(['create-trips', 'assign-vehicles']);
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
    $requester->givePermissionTo(['create-trips', 'assign-vehicles']);
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
    $requester->givePermissionTo(['create-trips', 'assign-vehicles']);
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
    $requester->givePermissionTo(['create-trips', 'assign-vehicles']);
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
        'trip_type' => 'inspection',
        'recurring_start_date' => '2026-06-01',
        'recurring_end_date' => '2026-06-03',
        'scheduled_start_time' => '08:00',
        'scheduled_end_time' => '09:00',
    ]);

    $response->assertSessionHasErrors('vehicle_id');
    expect(Trip::where('trip_number', '!=', 'TRIP-RECUR-BLOCKER')->count())->toBe(0);
});
