<?php

use App\Models\Trip;
use App\Models\TripFeedback;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seed the permissions the Feedback/Complaints module checks.
 * Spatie's caching means we must flush cache when creating permissions.
 */
function seedFeedbackPermissions(): void
{
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    Permission::firstOrCreate(['name' => 'view-complaints', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'view-own-complaints', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'create-complaints', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'assign-complaints', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'resolve-complaints', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'delete-complaints', 'guard_name' => 'web']);
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
}

function makeFeedbackUser(string $username): User
{
    return User::create([
        'email_verified_at' => now(),
        'name' => 'Feedback User '.$username,
        'username' => $username,
        'email' => $username.'@example.com',
        'user_type' => 'employee',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);
}

function makeFeedbackTrip(User $requester): Trip
{
    return Trip::create([
        'trip_number' => 'TRIP-FB-'.uniqid(),
        'requested_by' => $requester->id,
        'priority' => 'medium',
        'scheduled_date' => '2026-05-03',
        'scheduled_start_time' => '08:00:00',
        'scheduled_end_time' => '09:00:00',
        'status' => 'completed',
    ]);
}

it('lets a user submit a complaint about a trip', function () {
    seedFeedbackPermissions();
    $user = makeFeedbackUser('submitter-1');
    $user->givePermissionTo('create-complaints');
    $trip = makeFeedbackTrip($user);

    $response = $this->actingAs($user)->post('/complaints', [
        'trip_id' => $trip->id,
        'type' => 'complaint',
        'category' => 'driver_behavior',
        'subject' => 'Driver was late',
        'description' => 'The driver arrived 30 minutes late without notice.',
        'priority' => 'high',
    ]);

    $feedback = TripFeedback::firstWhere('trip_id', $trip->id);
    $response->assertRedirect(route('complaints.show', $feedback));

    expect($feedback)->not->toBeNull()
        ->and($feedback->status)->toBe('open')
        ->and($feedback->submitted_by)->toBe($user->id)
        ->and($feedback->priority)->toBe('high');
});

it('denies submitting a complaint about a trip the user has no relationship to', function () {
    seedFeedbackPermissions();
    $requester = makeFeedbackUser('other-requester-1');
    $trip = makeFeedbackTrip($requester);
    $stranger = makeFeedbackUser('stranger-2');
    $stranger->givePermissionTo('create-complaints');

    $response = $this->actingAs($stranger)->post('/complaints', [
        'trip_id' => $trip->id,
        'type' => 'complaint',
        'category' => 'driver_behavior',
        'subject' => 'Rating a trip I had nothing to do with',
        'description' => 'Trying to rate a driver on someone else\'s trip.',
        'driver_rating' => 1,
    ]);

    $response->assertSessionHasErrors('trip_id');
    expect(TripFeedback::where('trip_id', $trip->id)->exists())->toBeFalse();
});

it('lets a passenger submit a complaint about a trip even though they did not request it', function () {
    seedFeedbackPermissions();
    $requester = makeFeedbackUser('other-requester-2');
    $trip = makeFeedbackTrip($requester);
    $passenger = makeFeedbackUser('passenger-1');
    $passenger->givePermissionTo('create-complaints');
    $trip->passengers()->create(['user_id' => $passenger->id]);

    $response = $this->actingAs($passenger)->post('/complaints', [
        'trip_id' => $trip->id,
        'type' => 'feedback',
        'category' => 'vehicle_condition',
        'subject' => 'AC was not working',
        'description' => 'The air conditioning did not work for the whole trip.',
    ]);

    $response->assertSessionDoesntHaveErrors();
    expect(TripFeedback::where('trip_id', $trip->id)->where('submitted_by', $passenger->id)->exists())->toBeTrue();
});

it('lets the trip\'s driver submit a complaint even without being the requester or a named passenger', function () {
    seedFeedbackPermissions();
    $requester = makeFeedbackUser('other-requester-3');
    $driver = makeFeedbackUser('driver-1');
    $driver->givePermissionTo('create-complaints');
    $vehicle = Vehicle::create([
        'brand' => 'Toyota',
        'model' => 'Hiace',
        'registration_number' => 'FB-'.uniqid(),
        'is_active' => true,
        'driver_id' => $driver->id,
    ]);
    $trip = Trip::create([
        'trip_number' => 'TRIP-FB-'.uniqid(),
        'requested_by' => $requester->id,
        'vehicle_id' => $vehicle->id,
        'priority' => 'medium',
        'scheduled_date' => '2026-05-03',
        'scheduled_start_time' => '08:00:00',
        'scheduled_end_time' => '09:00:00',
        'status' => 'completed',
    ]);

    $response = $this->actingAs($driver)->post('/complaints', [
        'trip_id' => $trip->id,
        'type' => 'complaint',
        'category' => 'safety',
        'subject' => 'Passenger was disruptive',
        'description' => 'A passenger was behaving unsafely during the trip.',
    ]);

    $response->assertSessionDoesntHaveErrors();
    expect(TripFeedback::where('trip_id', $trip->id)->where('submitted_by', $driver->id)->exists())->toBeTrue();
});

it('lets a privileged (view-complaints) user submit a complaint about any trip', function () {
    seedFeedbackPermissions();
    $requester = makeFeedbackUser('other-requester-4');
    $trip = makeFeedbackTrip($requester);
    $manager = makeFeedbackUser('manager-3');
    $manager->givePermissionTo(['create-complaints', 'view-complaints']);

    $response = $this->actingAs($manager)->post('/complaints', [
        'trip_id' => $trip->id,
        'type' => 'complaint',
        'category' => 'other',
        'subject' => 'Filed on behalf of a rider',
        'description' => 'A rider called in this complaint and asked us to log it.',
    ]);

    $response->assertSessionDoesntHaveErrors();
    expect(TripFeedback::where('trip_id', $trip->id)->where('submitted_by', $manager->id)->exists())->toBeTrue();
});

it('restricts complaint visibility to the submitter unless the viewer can view all complaints', function () {
    seedFeedbackPermissions();
    $submitter = makeFeedbackUser('owner-1');
    $submitter->givePermissionTo(['create-complaints', 'view-own-complaints']);
    $otherUser = makeFeedbackUser('stranger-1');
    $otherUser->givePermissionTo('view-own-complaints');
    $manager = makeFeedbackUser('manager-1');
    $manager->givePermissionTo('view-complaints');

    $trip = makeFeedbackTrip($submitter);
    $feedback = TripFeedback::create([
        'trip_id' => $trip->id,
        'submitted_by' => $submitter->id,
        'type' => 'complaint',
        'category' => 'safety',
        'subject' => 'Unsafe driving',
        'description' => 'Driver was speeding through a school zone.',
        'priority' => 'critical',
        'status' => 'open',
    ]);

    $this->actingAs($submitter)->get("/complaints/{$feedback->id}")->assertOk();
    $this->actingAs($manager)->get("/complaints/{$feedback->id}")->assertOk();
    $this->actingAs($otherUser)->get("/complaints/{$feedback->id}")->assertForbidden();
});

it('assigns a complaint and moves it out of open status', function () {
    seedFeedbackPermissions();
    $submitter = makeFeedbackUser('owner-2');
    $manager = makeFeedbackUser('manager-2');
    $manager->givePermissionTo('assign-complaints');
    $handler = makeFeedbackUser('handler-1');

    $trip = makeFeedbackTrip($submitter);
    $feedback = TripFeedback::create([
        'trip_id' => $trip->id,
        'submitted_by' => $submitter->id,
        'type' => 'complaint',
        'category' => 'route',
        'subject' => 'Wrong route taken',
        'description' => 'Driver took a longer route than usual.',
        'priority' => 'medium',
        'status' => 'open',
    ]);

    $response = $this->actingAs($manager)->post("/complaints/{$feedback->id}/assign", [
        'assigned_to' => $handler->id,
    ]);
    $response->assertRedirect();

    $feedback->refresh();
    expect($feedback->assigned_to)->toBe($handler->id)
        ->and($feedback->status)->toBe('in_review');

    expect($handler->unreadNotifications()->count())->toBe(1);
    $notification = $handler->notifications()->first();
    expect($notification->data['type'])->toBe('complaint_assigned')
        ->and($notification->data['complaint_id'])->toBe($feedback->id);
});

it('resolves, closes, and reopens a complaint through its status lifecycle', function () {
    seedFeedbackPermissions();
    $submitter = makeFeedbackUser('owner-3');
    $manager = makeFeedbackUser('manager-3');
    $manager->givePermissionTo('resolve-complaints');

    $trip = makeFeedbackTrip($submitter);
    $feedback = TripFeedback::create([
        'trip_id' => $trip->id,
        'submitted_by' => $submitter->id,
        'type' => 'complaint',
        'category' => 'vehicle_condition',
        'subject' => 'AC not working',
        'description' => 'The vehicle air conditioning was broken.',
        'priority' => 'low',
        'status' => 'in_review',
    ]);

    $this->actingAs($manager)->post("/complaints/{$feedback->id}/resolve", [
        'resolution_notes' => 'Vehicle sent for AC repair.',
    ])->assertRedirect();

    $feedback->refresh();
    expect($feedback->status)->toBe('resolved')
        ->and($feedback->resolution_notes)->toBe('Vehicle sent for AC repair.')
        ->and($feedback->resolved_by)->toBe($manager->id)
        ->and($feedback->resolved_at)->not->toBeNull();

    $this->actingAs($manager)->post("/complaints/{$feedback->id}/reopen")->assertRedirect();
    $feedback->refresh();
    expect($feedback->status)->toBe('in_review');

    $this->actingAs($manager)->post("/complaints/{$feedback->id}/resolve", [
        'resolution_notes' => 'Repaired and verified.',
    ])->assertRedirect();

    $this->actingAs($manager)->post("/complaints/{$feedback->id}/close")->assertRedirect();
    $feedback->refresh();
    expect($feedback->status)->toBe('closed');
});

it('refuses to resolve an already-closed complaint', function () {
    seedFeedbackPermissions();
    $submitter = makeFeedbackUser('owner-4');
    $manager = makeFeedbackUser('manager-4');
    $manager->givePermissionTo('resolve-complaints');

    $trip = makeFeedbackTrip($submitter);
    $feedback = TripFeedback::create([
        'trip_id' => $trip->id,
        'submitted_by' => $submitter->id,
        'type' => 'feedback',
        'category' => 'other',
        'subject' => 'Great trip',
        'description' => 'Everything went smoothly.',
        'priority' => 'low',
        'status' => 'closed',
    ]);

    $response = $this->actingAs($manager)->post("/complaints/{$feedback->id}/resolve", [
        'resolution_notes' => 'Too late.',
    ]);
    $response->assertRedirect();

    $feedback->refresh();
    expect($feedback->status)->toBe('closed');
});

it('denies assigning or resolving complaints to users without the required permission', function () {
    seedFeedbackPermissions();
    $submitter = makeFeedbackUser('owner-5');
    $unauthorized = makeFeedbackUser('unauthorized-2');

    $trip = makeFeedbackTrip($submitter);
    $feedback = TripFeedback::create([
        'trip_id' => $trip->id,
        'submitted_by' => $submitter->id,
        'type' => 'complaint',
        'category' => 'other',
        'subject' => 'Test',
        'description' => 'Test description.',
        'priority' => 'low',
        'status' => 'open',
    ]);

    $this->actingAs($unauthorized)->post("/complaints/{$feedback->id}/assign", ['assigned_to' => $submitter->id])->assertForbidden();
    $this->actingAs($unauthorized)->post("/complaints/{$feedback->id}/resolve", ['resolution_notes' => 'x'])->assertForbidden();
});
