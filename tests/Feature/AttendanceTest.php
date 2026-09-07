<?php

use App\Jobs\ResolveAttendanceEventLocation;
use App\Models\AttendanceRecord;
use App\Models\Factory;
use App\Models\Trip;
use App\Models\TripPassenger;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    // Attendance events carry real GPS coordinates in these tests; stub
    // Nominatim so LocationResolver never makes a live network call here.
    Http::fake([
        '*nominatim.openstreetmap.org*' => Http::response(['display_name' => null], 200),
    ]);
});

function seedAttendanceOwnPermission(): void
{
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    Permission::firstOrCreate(['name' => 'capture-own-attendance', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'manage-attendance', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'view-attendance-reports', 'guard_name' => 'web']);
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
}

function makeAttendanceUser(string $suffix = '1'): User
{
    seedAttendanceOwnPermission();

    $user = User::create([
        'name' => "Attendance User {$suffix}",
        'username' => "attendance-user-{$suffix}",
        'email' => "attendance-user-{$suffix}@example.com",
        'user_type' => 'employee',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);

    $user->givePermissionTo('capture-own-attendance');

    return $user;
}

function makeInProgressTrip(string $suffix = '1'): Trip
{
    $requester = User::create([
        'name' => "Trip Requester {$suffix}",
        'username' => "trip-requester-att-{$suffix}",
        'email' => "trip-requester-att-{$suffix}@example.com",
        'user_type' => 'admin',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);

    return Trip::create([
        'trip_number' => "TRIP-ATT-{$suffix}",
        'requested_by' => $requester->id,
        'priority' => 'medium',
        'scheduled_date' => '2026-08-20',
        'scheduled_start_time' => '08:00:00',
        'scheduled_end_time' => '09:00:00',
        'status' => 'in_progress',
    ]);
}

it('checks in and creates an attendance record and event', function () {
    $user = makeAttendanceUser();

    $response = $this->actingAs($user)->post('/attendance/check-in', [
        'latitude' => 23.8103,
        'longitude' => 90.4125,
        'idempotency_key' => 'checkin-1',
    ]);

    $response->assertRedirect();

    $record = AttendanceRecord::where('user_id', $user->id)->first();

    expect($record)->not->toBeNull()
        ->and($record->status)->toBe('checked_in')
        ->and($record->check_in_at)->not->toBeNull();

    $this->assertDatabaseHas('attendance_events', [
        'attendance_record_id' => $record->id,
        'event_type' => 'check_in',
        'source' => 'self_service',
    ]);
});

it('rejects checking in twice in the same day', function () {
    $user = makeAttendanceUser();

    $this->actingAs($user)->post('/attendance/check-in', ['idempotency_key' => 'a'])->assertRedirect();
    $response = $this->actingAs($user)->post('/attendance/check-in', ['idempotency_key' => 'b']);

    $response->assertRedirect();
    $response->assertSessionHas('error');

    $record = AttendanceRecord::where('user_id', $user->id)->first();
    expect($record->events()->count())->toBe(1);
});

it('replays an idempotency key without creating a second event', function () {
    $user = makeAttendanceUser();

    $this->actingAs($user)->post('/attendance/check-in', ['idempotency_key' => 'same-key'])->assertRedirect();
    $this->actingAs($user)->post('/attendance/break-start', ['idempotency_key' => 'break-key'])->assertRedirect();
    $this->actingAs($user)->post('/attendance/break-start', ['idempotency_key' => 'break-key'])->assertRedirect();

    $record = AttendanceRecord::where('user_id', $user->id)->first();
    expect($record->events()->where('event_type', 'break_start')->count())->toBe(1);
});

it('rejects starting a break before checking in', function () {
    $user = makeAttendanceUser();

    $response = $this->actingAs($user)->post('/attendance/break-start', []);

    $response->assertRedirect();
    $response->assertSessionHas('error');
    expect(AttendanceRecord::where('user_id', $user->id)->exists())->toBeFalse();
});

it('rejects ending a break that is not open', function () {
    $user = makeAttendanceUser();

    $this->actingAs($user)->post('/attendance/check-in', [])->assertRedirect();
    $response = $this->actingAs($user)->post('/attendance/break-end', []);

    $response->assertSessionHas('error');
});

it('auto-closes a dangling break as a correction event on checkout', function () {
    $user = makeAttendanceUser();

    $this->actingAs($user)->post('/attendance/check-in', ['idempotency_key' => 'ci'])->assertRedirect();
    $this->actingAs($user)->post('/attendance/break-start', ['idempotency_key' => 'bs'])->assertRedirect();
    $this->actingAs($user)->post('/attendance/check-out', ['idempotency_key' => 'co'])->assertRedirect();

    $record = AttendanceRecord::where('user_id', $user->id)->first();

    expect($record->status)->toBe('checked_out')
        ->and($record->events()->where('event_type', 'correction')->count())->toBe(1)
        ->and($record->events()->where('event_type', 'break_end')->count())->toBe(0);
});

it('calculates gross, net and overtime minutes across a day with one break', function () {
    $user = makeAttendanceUser();
    $record = AttendanceRecord::create(['user_id' => $user->id, 'work_date' => '2026-08-20', 'status' => 'checked_out']);

    $record->checkIn(['event_time' => '2026-08-20 08:00:00']);
    $record->startBreak(['event_time' => '2026-08-20 13:00:00']);
    $record->endBreak(['event_time' => '2026-08-20 13:30:00']);
    $record->checkOut(['event_time' => '2026-08-20 17:00:00']);

    $record->refresh();

    expect($record->break_minutes)->toBe(30)
        ->and($record->gross_minutes)->toBe(540)
        ->and($record->net_minutes)->toBe(510)
        ->and($record->overtime_minutes)->toBe(30);
});

it('captures the factory and location name on checkout', function () {
    $user = makeAttendanceUser();
    $factory = Factory::create(['account_id' => 'ACC-ABC', 'name' => 'Factory ABC', 'status' => 'active']);

    $this->actingAs($user)->post('/attendance/check-in', [])->assertRedirect();
    $this->actingAs($user)->post('/attendance/check-out', [
        'factory_id' => $factory->id,
        'location_name' => 'Factory ABC - Gate 2',
    ])->assertRedirect();

    $this->assertDatabaseHas('attendance_events', [
        'event_type' => 'check_out',
        'factory_id' => $factory->id,
        'location_name' => 'Factory ABC - Gate 2',
    ]);
});

it('auto-resolves a check-in near a known factory without touching the queue', function () {
    Queue::fake();
    $user = makeAttendanceUser();
    $factory = Factory::create(['account_id' => 'ACC-GEO1', 'name' => 'Geo Factory', 'status' => 'active', 'latitude' => 23.8103, 'longitude' => 90.4125]);

    $this->actingAs($user)->post('/attendance/check-in', [
        'latitude' => 23.8103,
        'longitude' => 90.4125,
    ])->assertRedirect();

    $record = AttendanceRecord::where('user_id', $user->id)->first();
    $event = $record->events()->where('event_type', 'check_in')->first();

    expect($record->has_anomaly)->toBeFalse()
        ->and($event->factory_id)->toBe($factory->id);

    Queue::assertNotPushed(ResolveAttendanceEventLocation::class);
});

it('flags an anomaly and queues Nominatim resolution when checking in far from every known factory/stop', function () {
    Queue::fake();
    $user = makeAttendanceUser();
    Factory::create(['account_id' => 'ACC-GEO2', 'name' => 'Geo Factory Far', 'status' => 'active', 'latitude' => 23.8103, 'longitude' => 90.4125]);

    $this->actingAs($user)->post('/attendance/check-in', [
        'latitude' => 24.5000,
        'longitude' => 91.8000,
    ])->assertRedirect();

    $record = AttendanceRecord::where('user_id', $user->id)->first();
    $event = $record->events()->where('event_type', 'check_in')->first();

    expect($record->has_anomaly)->toBeTrue()
        ->and($event->factory_id)->toBeNull()
        ->and($event->metadata['geofence_flag'] ?? null)->toBeTrue();

    Queue::assertPushed(ResolveAttendanceEventLocation::class, 1);
});

it('flags an anomaly when checking out far from every known factory/stop with no factory chosen', function () {
    $user = makeAttendanceUser();
    Factory::create(['account_id' => 'ACC-GEO3', 'name' => 'Geo Factory Checkout', 'status' => 'active', 'latitude' => 23.8103, 'longitude' => 90.4125]);

    $this->actingAs($user)->post('/attendance/check-in', ['idempotency_key' => 'co-geo-in'])->assertRedirect();
    $this->actingAs($user)->post('/attendance/check-out', [
        'idempotency_key' => 'co-geo-out',
        'latitude' => 24.5000,
        'longitude' => 91.8000,
    ])->assertRedirect();

    $record = AttendanceRecord::where('user_id', $user->id)->first();
    $event = $record->events()->where('event_type', 'check_out')->first();

    expect($record->has_anomaly)->toBeTrue()
        ->and($event->metadata['geofence_flag'] ?? null)->toBeTrue();
});

it('does not flag a checkout far from every known factory/stop when the user manually picked a factory', function () {
    $user = makeAttendanceUser();
    $factory = Factory::create(['account_id' => 'ACC-GEO4', 'name' => 'Geo Factory Manual', 'status' => 'active', 'latitude' => 23.8103, 'longitude' => 90.4125]);

    $this->actingAs($user)->post('/attendance/check-in', ['idempotency_key' => 'co-geo-in2'])->assertRedirect();
    $this->actingAs($user)->post('/attendance/check-out', [
        'idempotency_key' => 'co-geo-out2',
        'latitude' => 24.5000,
        'longitude' => 91.8000,
        'factory_id' => $factory->id,
    ])->assertRedirect();

    $record = AttendanceRecord::where('user_id', $user->id)->first();

    expect($record->has_anomaly)->toBeFalse();
});

it('fans out check-in to a pending trip pickup, creating both records linked together', function () {
    $user = makeAttendanceUser();
    $trip = makeInProgressTrip();
    $tripPassenger = TripPassenger::create(['trip_id' => $trip->id, 'user_id' => $user->id, 'status' => 'pending']);

    $this->actingAs($user)->post('/attendance/check-in', [
        'latitude' => 23.8759,
        'longitude' => 90.3795,
    ])->assertRedirect();

    $tripPassenger->refresh();
    $record = AttendanceRecord::where('user_id', $user->id)->first();
    $attendanceEvent = $record->events()->where('event_type', 'check_in')->first();

    expect($tripPassenger->status)->toBe('boarded')
        ->and($record->used_transport)->toBeTrue()
        ->and($record->trip_passenger_event_id)->not->toBeNull()
        ->and($attendanceEvent->related_trip_passenger_event_id)->toBe($record->trip_passenger_event_id);

    $this->assertDatabaseHas('trip_passenger_events', [
        'trip_passenger_id' => $tripPassenger->id,
        'event_type' => 'check_in',
        'source' => 'attendance_self_service',
    ]);
});

it('links to an already-boarded trip passenger without a duplicate trip write', function () {
    $user = makeAttendanceUser();
    $trip = makeInProgressTrip('2');
    $tripPassenger = TripPassenger::create(['trip_id' => $trip->id, 'user_id' => $user->id, 'status' => 'pending']);
    $tripPassenger->markAsBoarded(['event_time' => '2026-08-20 07:30:00', 'source' => 'mobile']);

    $this->actingAs($user)->post('/attendance/check-in', [])->assertRedirect();

    $record = AttendanceRecord::where('user_id', $user->id)->first();

    expect($tripPassenger->passengerEvents()->count())->toBe(1)
        ->and($record->used_transport)->toBeTrue();
});

it('does not touch the trip system when checking in with no trip that day', function () {
    $user = makeAttendanceUser();

    $this->actingAs($user)->post('/attendance/check-in', [])->assertRedirect();

    $record = AttendanceRecord::where('user_id', $user->id)->first();

    expect($record->used_transport)->toBeFalse()
        ->and($record->trip_passenger_event_id)->toBeNull();
    $this->assertDatabaseCount('trip_passenger_events', 0);
});

it('fans out check-out to a pending trip drop-off', function () {
    $user = makeAttendanceUser();
    $trip = makeInProgressTrip('3');
    $tripPassenger = TripPassenger::create(['trip_id' => $trip->id, 'user_id' => $user->id, 'status' => 'boarded']);

    $this->actingAs($user)->post('/attendance/check-in', [])->assertRedirect();
    $this->actingAs($user)->post('/attendance/check-out', [])->assertRedirect();

    $tripPassenger->refresh();
    expect($tripPassenger->status)->toBe('completed');
});

it('denies attendance actions for a user without capture-own-attendance permission', function () {
    seedAttendanceOwnPermission();

    $user = User::create([
        'name' => 'No Permission User',
        'username' => 'no-permission-attendance',
        'email' => 'no-permission-attendance@example.com',
        'user_type' => 'employee',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);

    $response = $this->actingAs($user)->post('/attendance/check-in', []);

    $response->assertForbidden();
});

it('voids and supersedes an event on correction, keeping both in the trail', function () {
    $hr = makeAttendanceUser('hr');
    $hr->givePermissionTo('manage-attendance');

    $employee = makeAttendanceUser('emp');
    $record = AttendanceRecord::create(['user_id' => $employee->id, 'work_date' => '2026-08-20', 'status' => 'checked_out']);
    $event = $record->checkIn(['event_time' => '2026-08-20 08:05:00']);

    $response = $this->actingAs($hr)->post("/attendance/events/{$event->id}/correct", [
        'event_type' => 'check_in',
        'event_time' => '2026-08-20 08:00:00',
        'void_reason' => 'Corrected delayed sync timestamp',
    ]);

    $response->assertRedirect();

    $event->refresh();
    $record->refresh();
    $replacement = $record->events()->latest('id')->first();

    expect($event->is_valid)->toBeFalse()
        ->and($event->superseded_by_event_id)->toBe($replacement->id)
        ->and($record->has_anomaly)->toBeTrue()
        ->and($record->check_in_at?->format('Y-m-d H:i:s'))->toBe('2026-08-20 08:00:00');
});
