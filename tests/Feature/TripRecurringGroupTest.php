<?php

use App\Models\Trip;
use App\Models\TripRecurringGroup;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

function seedTripRecurringGroupPermissions(): void
{
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    Permission::firstOrCreate(['name' => 'edit-trips', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'delete-trips', 'guard_name' => 'web']);
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
}

function makeRecurringGroupUser(string $username): User
{
    return User::create([
        'email_verified_at' => now(),
        'name' => 'Recurring Group User '.$username,
        'username' => $username,
        'email' => $username.'@example.com',
        'user_type' => 'employee',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);
}

function makeRecurringGroup(User $creator, int $totalTrips = 3): TripRecurringGroup
{
    return TripRecurringGroup::create([
        'created_by' => $creator->id,
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-03',
        'total_trips' => $totalTrips,
    ]);
}

function makeGroupTrip(User $requester, TripRecurringGroup $group, string $date, string $status = 'pending'): Trip
{
    return Trip::create([
        'trip_number' => 'TRIP-GROUP-'.uniqid(),
        'requested_by' => $requester->id,
        'recurring_group_id' => $group->id,
        'is_recurring' => true,
        'priority' => 'medium',
        'scheduled_date' => $date,
        'scheduled_start_time' => '08:00',
        'scheduled_end_time' => '09:00',
        'status' => $status,
    ]);
}

it('actually persists recurring_group_id/is_recurring on every trip created by storeRecurring (regression)', function () {
    seedTripRecurringGroupPermissions();
    Permission::firstOrCreate(['name' => 'create-trips', 'guard_name' => 'web']);
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

    $requester = makeRecurringGroupUser('requester-regression');
    $requester->givePermissionTo('create-trips');
    $vehicle = \App\Models\Vehicle::create([
        'brand' => 'Toyota',
        'model' => 'Hiace',
        'registration_number' => 'VEH-'.uniqid(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($requester)->post('/trips/recurring', [
        'vehicle_id' => $vehicle->id,
        'priority' => 'medium',
        'recurring_start_date' => '2026-07-01',
        'recurring_end_date' => '2026-07-03',
        'scheduled_start_time' => '08:00',
        'scheduled_end_time' => '09:00',
    ]);

    $response->assertSessionDoesntHaveErrors();
    $response->assertRedirect();

    $group = TripRecurringGroup::first();
    expect($group)->not->toBeNull();

    $trips = Trip::where('recurring_group_id', $group->id)->get();
    expect($trips)->toHaveCount(3);

    foreach ($trips as $trip) {
        expect($trip->is_recurring)->toBeTrue()
            ->and($trip->recurring_group_id)->toBe($group->id)
            ->and((string) $trip->recurring_start_date)->toBe('2026-07-01')
            ->and((string) $trip->recurring_end_date)->toBe('2026-07-03');
    }
});

it('decrements the recurring group total_trips when a member trip is deleted', function () {
    seedTripRecurringGroupPermissions();
    $requester = makeRecurringGroupUser('requester-1');
    $requester->givePermissionTo('delete-trips');

    $group = makeRecurringGroup($requester, 3);
    $trip = makeGroupTrip($requester, $group, '2026-06-01');

    $this->actingAs($requester)->delete("/trips/{$trip->id}")->assertRedirect();

    expect($group->fresh()->total_trips)->toBe(2);
});

it('does not touch total_trips when deleting a non-recurring trip', function () {
    seedTripRecurringGroupPermissions();

    $requester = makeRecurringGroupUser('requester-2');
    $requester->givePermissionTo('delete-trips');

    $trip = Trip::create([
        'trip_number' => 'TRIP-SOLO-1',
        'requested_by' => $requester->id,
        'priority' => 'medium',
        'scheduled_date' => '2026-06-01',
        'scheduled_start_time' => '08:00',
        'scheduled_end_time' => '09:00',
        'status' => 'pending',
    ]);

    $this->actingAs($requester)->delete("/trips/{$trip->id}")->assertRedirect();

    expect(Trip::withTrashed()->find($trip->id)->deleted_at)->not->toBeNull();
});

it('cancels every still-cancellable trip in a recurring series and skips terminal ones', function () {
    seedTripRecurringGroupPermissions();
    $manager = makeRecurringGroupUser('manager-1');
    $manager->givePermissionTo('edit-trips');

    $group = makeRecurringGroup($manager, 3);
    $pending = makeGroupTrip($manager, $group, '2026-06-01', 'pending');
    $approved = makeGroupTrip($manager, $group, '2026-06-02', 'approved');
    $alreadyCompleted = makeGroupTrip($manager, $group, '2026-06-03', 'completed');

    $response = $this->actingAs($manager)->post("/trip-recurring-groups/{$group->id}/cancel", [
        'cancellation_reason' => 'weather_conditions',
        'cancellation_notes' => 'Storm warning for the whole week.',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    expect($pending->fresh()->status)->toBe('cancelled')
        ->and($approved->fresh()->status)->toBe('cancelled')
        ->and($alreadyCompleted->fresh()->status)->toBe('completed');
});

it('reports an error when no trips in the series could be cancelled', function () {
    seedTripRecurringGroupPermissions();
    $manager = makeRecurringGroupUser('manager-2');
    $manager->givePermissionTo('edit-trips');

    $group = makeRecurringGroup($manager, 1);
    makeGroupTrip($manager, $group, '2026-06-01', 'completed');

    $response = $this->actingAs($manager)->post("/trip-recurring-groups/{$group->id}/cancel", [
        'cancellation_reason' => 'other',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('error');
});

it('denies cancelSeries to a user without edit-trips', function () {
    seedTripRecurringGroupPermissions();
    $requester = makeRecurringGroupUser('requester-3');
    $group = makeRecurringGroup($requester, 1);
    makeGroupTrip($requester, $group, '2026-06-01', 'pending');

    $this->actingAs($requester)
        ->post("/trip-recurring-groups/{$group->id}/cancel", ['cancellation_reason' => 'other'])
        ->assertForbidden();
});
