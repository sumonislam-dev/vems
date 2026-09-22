<?php

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDriverAssignment;
use Illuminate\Support\Facades\Hash;

function makeBackfillDriver(string $username): User
{
    return User::create([
        'email_verified_at' => now(),
        'name' => 'Backfill Driver '.$username,
        'username' => $username,
        'email' => $username.'@example.com',
        'user_type' => 'driver',
        'status' => 'active',
        'driver_status' => 'available',
        'password' => Hash::make('password'),
    ]);
}

it('creates a current assignment for a vehicle that has a driver but no assignment history', function () {
    $driver = makeBackfillDriver('driver-1');
    // VehicleObserver would normally create this on Vehicle::create(); simulate the
    // pre-existing-data scenario this command exists for by removing it after.
    $vehicle = Vehicle::create([
        'brand' => 'Toyota', 'model' => 'Hiace',
        'registration_number' => 'VEH-'.uniqid(),
        'driver_id' => $driver->id, 'is_active' => true,
    ]);
    VehicleDriverAssignment::where('vehicle_id', $vehicle->id)->delete();

    $this->artisan('vems:backfill-vehicle-assignments')->assertSuccessful();

    expect(VehicleDriverAssignment::where('vehicle_id', $vehicle->id)->where('is_current', true)->count())->toBe(1);
});

it('--force is idempotent: rerunning with no driver changes creates no new rows and keeps started_at', function () {
    $driver = makeBackfillDriver('driver-2');
    $vehicle = Vehicle::create([
        'brand' => 'Toyota', 'model' => 'Hiace',
        'registration_number' => 'VEH-'.uniqid(),
        'driver_id' => $driver->id, 'is_active' => true,
    ]);

    $this->artisan('vems:backfill-vehicle-assignments', ['--force' => true])->assertSuccessful();
    $original = VehicleDriverAssignment::where('vehicle_id', $vehicle->id)->where('is_current', true)->first();
    expect($original)->not->toBeNull();

    $this->travel(1)->hour();
    $this->artisan('vems:backfill-vehicle-assignments', ['--force' => true])->assertSuccessful();

    $current = VehicleDriverAssignment::where('vehicle_id', $vehicle->id)->where('is_current', true)->get();
    expect($current)->toHaveCount(1)
        ->and($current->first()->id)->toBe($original->id)
        ->and($current->first()->started_at->equalTo($original->started_at))->toBeTrue();
    expect(VehicleDriverAssignment::where('vehicle_id', $vehicle->id)->count())->toBe(1);
});

it('--force still corrects a vehicle whose current assignment disagrees with driver_id', function () {
    $originalDriver = makeBackfillDriver('driver-3');
    $newDriver = makeBackfillDriver('driver-4');

    $vehicle = Vehicle::create([
        'brand' => 'Toyota', 'model' => 'Hiace',
        'registration_number' => 'VEH-'.uniqid(),
        'driver_id' => $originalDriver->id, 'is_active' => true,
    ]);

    // Simulate drift: driver_id changed without going through the observer.
    Vehicle::where('id', $vehicle->id)->update(['driver_id' => $newDriver->id]);

    $this->artisan('vems:backfill-vehicle-assignments', ['--force' => true])->assertSuccessful();

    $current = VehicleDriverAssignment::where('vehicle_id', $vehicle->id)->where('is_current', true)->get();
    expect($current)->toHaveCount(1)
        ->and($current->first()->driver_id)->toBe($newDriver->id);
    expect(VehicleDriverAssignment::where('vehicle_id', $vehicle->id)->where('is_current', false)->count())->toBe(1);
});
