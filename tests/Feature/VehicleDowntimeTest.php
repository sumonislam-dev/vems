<?php

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDowntime;
use App\Models\Vendor;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Vehicle downtime: auto-started/ended by status changes (VehicleObserver),
 * and recorded/edited/ended/deleted by hand on the vehicle page.
 */
function seedDowntimePermissions(): void
{
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    foreach (['view-vehicles', 'edit-vehicles', 'manage-vehicle-downtime'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
}

function downtimeActor(array $permissions = ['view-vehicles', 'edit-vehicles', 'manage-vehicle-downtime']): User
{
    $user = User::create([
        'email_verified_at' => now(),
        'name' => 'Downtime Actor',
        'username' => 'downtime_actor_'.uniqid(),
        'email' => 'downtime_actor_'.uniqid().'@example.com',
        'user_type' => 'employee',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);
    $user->givePermissionTo($permissions);

    return $user;
}

function downtimeVehicle(array $overrides = []): Vehicle
{
    return Vehicle::create(array_merge([
        'brand' => 'Toyota', 'model' => 'Hiace', 'registration_number' => 'DT-'.uniqid(),
        'vehicle_type' => 'van', 'rental_type' => 'pool', 'status' => 'available', 'is_active' => true,
        'vendor_id' => Vendor::create(['name' => 'Vendor '.uniqid(), 'status' => 'active'])->id,
    ], $overrides));
}

function vehicleUpdatePayload(Vehicle $vehicle, array $overrides = []): array
{
    return array_merge($vehicle->only(['brand', 'model', 'registration_number', 'vehicle_type', 'rental_type', 'vendor_id', 'status']), $overrides);
}

// --- automatic start / end from status --------------------------------------

it('starts a downtime when the vehicle goes into maintenance, and sends the user to review it', function () {
    seedDowntimePermissions();
    $vehicle = downtimeVehicle();

    $this->actingAs(downtimeActor())
        ->put("/vehicles/{$vehicle->id}", vehicleUpdatePayload($vehicle, ['status' => 'maintenance']))
        ->assertRedirect(route('vehicles.show', $vehicle))
        ->assertSessionHas('success', fn ($msg) => str_contains($msg, 'Downtime started'));

    $downtime = $vehicle->downtimes()->sole();
    expect($downtime->starts_on->toDateString())->toBe(today()->toDateString())
        ->and($downtime->ends_on)->toBeNull()
        ->and($downtime->reason)->toBe('servicing');
});

it('keeps one open downtime when moving between down statuses, and ends it when back in service', function () {
    $vehicle = downtimeVehicle(['status' => 'out_of_service']);
    expect($vehicle->downtimes()->open()->sole()->reason)->toBe('repair');

    $vehicle->update(['status' => 'maintenance']);
    expect($vehicle->downtimes()->count())->toBe(1);

    $vehicle->update(['status' => 'available']);
    expect($vehicle->downtimes()->open()->count())->toBe(0)
        ->and($vehicle->downtimes()->sole()->ends_on->toDateString())->toBe(today()->toDateString());
});

it('does not touch downtime for unrelated vehicle edits', function () {
    $vehicle = downtimeVehicle();
    $vehicle->update(['capacity' => 12]);

    expect($vehicle->downtimes()->count())->toBe(0);
});

// --- manual records ------------------------------------------------------------

it('records, updates, ends and deletes a downtime by hand', function () {
    seedDowntimePermissions();
    $actor = downtimeActor();
    $vehicle = downtimeVehicle();
    $replacement = downtimeVehicle();

    $this->actingAs($actor)->post("/vehicles/{$vehicle->id}/downtimes", [
        'starts_on' => '2026-01-05',
        'reason' => 'accident',
        'replacement_vehicle_id' => $replacement->id,
        'notes' => 'Rear bumper',
    ])->assertSessionHasNoErrors();

    $downtime = $vehicle->downtimes()->sole();
    expect($downtime->recorded_by)->toBe($actor->id)
        ->and($downtime->replacement_vehicle_id)->toBe($replacement->id);

    $this->actingAs($actor)->put("/vehicles/{$vehicle->id}/downtimes/{$downtime->id}", [
        'starts_on' => '2026-01-04', 'reason' => 'repair', 'replacement_vehicle_id' => 'none',
    ])->assertSessionHasNoErrors();
    expect($downtime->fresh()->reason)->toBe('repair')
        ->and($downtime->fresh()->replacement_vehicle_id)->toBeNull();

    $this->actingAs($actor)->post("/vehicles/{$vehicle->id}/downtimes/{$downtime->id}/end")->assertSessionHasNoErrors();
    expect($downtime->fresh()->ends_on->toDateString())->toBe(today()->toDateString());

    $this->actingAs($actor)->delete("/vehicles/{$vehicle->id}/downtimes/{$downtime->id}");
    expect(VehicleDowntime::count())->toBe(0);
});

it('rejects overlapping periods and a vehicle replacing itself', function () {
    seedDowntimePermissions();
    $actor = downtimeActor();
    $vehicle = downtimeVehicle();
    VehicleDowntime::create(['vehicle_id' => $vehicle->id, 'starts_on' => '2026-01-05', 'ends_on' => '2026-01-10', 'reason' => 'repair']);

    $this->actingAs($actor)->post("/vehicles/{$vehicle->id}/downtimes", [
        'starts_on' => '2026-01-08', 'reason' => 'repair',
    ])->assertSessionHasErrors('starts_on');

    $this->actingAs($actor)->post("/vehicles/{$vehicle->id}/downtimes", [
        'starts_on' => '2026-01-11', 'ends_on' => '2026-01-12', 'reason' => 'repair', 'replacement_vehicle_id' => $vehicle->id,
    ])->assertSessionHasErrors('replacement_vehicle_id');

    $this->actingAs($actor)->post("/vehicles/{$vehicle->id}/downtimes", [
        'starts_on' => '2026-01-11', 'ends_on' => '2026-01-12', 'reason' => 'repair',
    ])->assertSessionHasNoErrors();
});

it('requires manage-vehicle-downtime to record downtime, and 404s a downtime from another vehicle', function () {
    seedDowntimePermissions();
    $vehicle = downtimeVehicle();
    $other = downtimeVehicle();
    $otherDowntime = VehicleDowntime::create(['vehicle_id' => $other->id, 'starts_on' => '2026-01-05', 'reason' => 'repair']);

    $this->actingAs(downtimeActor(['view-vehicles', 'edit-vehicles']))
        ->post("/vehicles/{$vehicle->id}/downtimes", ['starts_on' => '2026-01-05', 'reason' => 'repair'])
        ->assertForbidden();

    $this->actingAs(downtimeActor())
        ->delete("/vehicles/{$vehicle->id}/downtimes/{$otherDowntime->id}")
        ->assertNotFound();
});

it('shows downtime history on the vehicle page', function () {
    seedDowntimePermissions();
    $vehicle = downtimeVehicle();
    VehicleDowntime::create(['vehicle_id' => $vehicle->id, 'starts_on' => '2026-01-05', 'reason' => 'document_renewal']);

    $this->actingAs(downtimeActor())->get("/vehicles/{$vehicle->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('downtimes.0.reason_label', 'Document renewal')
            ->where('canManageDowntime', true)
            ->has('downtimeReasons', 5));
});
