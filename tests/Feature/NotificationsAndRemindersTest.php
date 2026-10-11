<?php

use App\Models\Department;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Zone;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Workflow notifications (TripNotifier), the daily reminder commands and
 * the vems:create-admin command.
 */
beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    foreach (['view-own-trips', 'create-trips', 'edit-trips', 'approve-trips', 'approve-department-trips', 'assign-vehicles',
        'create-complaints', 'assign-complaints', 'edit-vehicles', 'edit-drivers', 'view-vehicles'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $this->dhaka = Zone::create(['name' => 'Dhaka', 'code' => 'DHK', 'is_active' => true]);
    $this->ctg = Zone::create(['name' => 'Chattogram', 'code' => 'CTG', 'is_active' => true]);
});

function notifyUser(array $permissions = [], ?Zone $zone = null, array $attributes = []): User
{
    $user = User::create(array_merge([
        'email_verified_at' => now(),
        'name' => 'Notify '.uniqid(),
        'username' => 'notify_'.uniqid(),
        'email' => 'notify_'.uniqid().'@example.com',
        'user_type' => 'employee',
        'status' => 'active',
        'zone_id' => $zone?->id,
        'password' => Hash::make('password'),
    ], $attributes));
    $user->givePermissionTo($permissions);

    return $user;
}

function notifyTrip(array $attributes = []): Trip
{
    return Trip::create(array_merge([
        'requested_by' => notifyUser()->id,
        'priority' => 'medium',
        'scheduled_date' => today()->addDay()->toDateString(),
        'scheduled_start_time' => '08:00',
        'scheduled_end_time' => '09:00',
        'status' => 'pending',
    ], $attributes));
}

function messagesFor(User $user): array
{
    return $user->fresh()->notifications->pluck('data.message')->all();
}

// --- workflow notifications ------------------------------------------------------

it('tells approvers in the same office and the department head about a new request', function () {
    $approver = notifyUser(['approve-trips'], $this->dhaka);
    $otherOffice = notifyUser(['approve-trips'], $this->ctg);
    $head = notifyUser(['approve-department-trips'], $this->dhaka);
    $department = Department::create(['name' => 'Inspection', 'code' => 'INS', 'head_id' => $head->id]);
    $requester = notifyUser(['create-trips', 'view-own-trips'], $this->dhaka);

    $this->actingAs($requester)->post('/trips', [
        'priority' => 'medium',
        'scheduled_date' => '2026-11-02',
        'scheduled_start_time' => '08:00',
        'scheduled_end_time' => '09:00',
        'end_location' => 'Gazipur',
        'department_id' => $department->id,
    ])->assertSessionHasNoErrors();

    expect(messagesFor($approver)[0])->toContain('New trip request')
        ->and(messagesFor($head))->toHaveCount(1)
        ->and(messagesFor($otherOffice))->toBe([])
        ->and(messagesFor($requester))->toBe([]);
});

it('sends one notification for a whole recurring series', function () {
    $approver = notifyUser(['approve-trips'], $this->dhaka);
    $requester = notifyUser(['create-trips'], $this->dhaka);

    $this->actingAs($requester)->post('/trips/recurring', [
        'priority' => 'medium',
        'recurring_start_date' => '2026-11-02',
        'recurring_end_date' => '2026-11-05',
        'scheduled_start_time' => '08:00',
        'scheduled_end_time' => '09:00',
    ])->assertSessionHasNoErrors();

    expect(messagesFor($approver))->toHaveCount(1)
        ->and(messagesFor($approver)[0])->toContain('4 trips');
});

it('asks Logistics for a vehicle when a trip is approved without one', function () {
    $logistics = notifyUser(['assign-vehicles'], $this->dhaka);
    $requester = notifyUser([], $this->dhaka);
    $withoutVehicle = notifyTrip(['requested_by' => $requester->id]);
    $withVehicle = notifyTrip(['requested_by' => $requester->id, 'vehicle_id' => Vehicle::create(['brand' => 'Toyota', 'model' => 'Hiace', 'registration_number' => 'N-1', 'is_active' => true, 'zone_id' => $this->dhaka->id])->id]);
    $approver = notifyUser(['approve-trips']);

    $this->actingAs($approver)->post("/trips/{$withoutVehicle->id}/approve");
    $this->actingAs($approver)->post("/trips/{$withVehicle->id}/approve");

    expect(messagesFor($logistics))->toHaveCount(1)
        ->and(messagesFor($logistics)[0])->toContain('needs a vehicle');
});

it('sums up a bulk approval for Logistics in one notification', function () {
    $logistics = notifyUser(['assign-vehicles'], $this->dhaka);
    $requester = notifyUser([], $this->dhaka);
    $ids = collect(range(1, 3))->map(fn () => notifyTrip(['requested_by' => $requester->id])->id)->all();

    $this->actingAs(notifyUser(['approve-trips']))->post('/trips/bulk-approve', ['trip_ids' => $ids]);

    expect(messagesFor($logistics))->toBe(['3 approved trips need a vehicle.']);
});

it('tells the requester and the driver when a vehicle is assigned', function () {
    $driver = notifyUser([], $this->dhaka, ['user_type' => 'driver']);
    $vehicle = Vehicle::create(['brand' => 'Toyota', 'model' => 'Hiace', 'registration_number' => 'DHK-11', 'is_active' => true, 'zone_id' => $this->dhaka->id, 'driver_id' => $driver->id]);
    $requester = notifyUser([], $this->dhaka);
    $trip = notifyTrip(['requested_by' => $requester->id, 'status' => 'approved']);
    $logistics = notifyUser(['assign-vehicles']);

    $this->actingAs($logistics)->post("/trips/{$trip->id}/reassign-vehicle", ['vehicle_id' => $vehicle->id])
        ->assertSessionHasNoErrors();

    expect(messagesFor($requester)[0])->toContain('DHK-11 is assigned to your trip')
        ->and(messagesFor($driver)[0])->toContain('You are driving trip')
        ->and(messagesFor($logistics))->toBe([]);
});

it('tells the requester about a cancellation, but not the person who cancelled', function () {
    $requester = notifyUser(['edit-trips'], $this->dhaka);
    $trip = notifyTrip(['requested_by' => $requester->id, 'status' => 'approved']);
    $canceller = notifyUser(['edit-trips']);

    $this->actingAs($canceller)->post("/trips/{$trip->id}/cancel", ['cancellation_reason' => 'emergency']);

    expect(messagesFor($requester)[0])->toContain('was cancelled')
        ->and(messagesFor($canceller))->toBe([]);
});

it('tells complaint managers in the office about a new complaint', function () {
    $manager = notifyUser(['assign-complaints'], $this->dhaka);
    $requester = notifyUser(['create-complaints'], $this->dhaka);
    $trip = notifyTrip(['requested_by' => $requester->id, 'status' => 'completed']);

    $this->actingAs($requester)->post('/complaints', [
        'trip_id' => $trip->id,
        'type' => 'complaint',
        'category' => 'punctuality',
        'subject' => 'Late',
        'description' => 'Arrived 40 minutes late.',
    ])->assertSessionHasNoErrors();

    expect(messagesFor($manager)[0])->toContain('New complaint')->toContain('punctuality');
});

// --- expiry reminders -------------------------------------------------------------

it('reminds fleet managers about vehicle documents only on milestone days', function () {
    $manager = notifyUser(['edit-vehicles'], $this->dhaka);
    $otherOffice = notifyUser(['edit-vehicles'], $this->ctg);
    $base = ['brand' => 'Toyota', 'model' => 'Hiace', 'is_active' => true, 'zone_id' => $this->dhaka->id, 'alert_days_before' => 30];
    Vehicle::create([...$base, 'registration_number' => 'D-7', 'tax_token_last_date' => today()->addDays(7)]);
    Vehicle::create([...$base, 'registration_number' => 'D-8', 'tax_token_last_date' => today()->addDays(8)]);
    Vehicle::create([...$base, 'registration_number' => 'D-OFF', 'insurance_last_date' => today()->addDays(7), 'insurance_alert_enabled' => false]);
    Vehicle::create([...$base, 'registration_number' => 'D-EXP', 'route_permit_last_date' => today()->subDays(14)]);

    $this->artisan('vems:expiry-reminders')->assertSuccessful();

    $message = messagesFor($manager)[0];
    expect(messagesFor($manager))->toHaveCount(1)
        ->and($message)->toContain('D-7 Tax token expires in 7 days')
        ->and($message)->toContain('D-EXP Route permit expired 14 days ago')
        ->and($message)->not->toContain('D-8')
        ->and($message)->not->toContain('D-OFF')
        ->and(messagesFor($otherOffice))->toBe([]);
});

it('reminds a driver and their manager about an expiring licence', function () {
    $manager = notifyUser(['edit-drivers'], $this->dhaka);
    $driver = notifyUser([], $this->dhaka, ['user_type' => 'driver', 'name' => 'Karim', 'license_expiry_date' => today()->addDay()]);

    $this->artisan('vems:expiry-reminders')->assertSuccessful();

    expect(messagesFor($driver))->toBe(['Your driving licence expires tomorrow.'])
        ->and(messagesFor($manager))->toBe(["Karim's driving licence expires tomorrow."]);
});

// --- trips needing attention ------------------------------------------------------

it('flags overdue and long-running trips to the right people', function () {
    $approver = notifyUser(['approve-trips'], $this->dhaka);
    $logistics = notifyUser(['assign-vehicles'], $this->dhaka);
    $requester = notifyUser([], $this->dhaka);
    notifyTrip(['requested_by' => $requester->id, 'scheduled_date' => today()->subDays(2)->toDateString()]);
    notifyTrip(['requested_by' => $requester->id, 'scheduled_date' => today()->subDay()->toDateString(), 'status' => 'approved']);
    notifyTrip(['requested_by' => $requester->id, 'status' => 'pending']); // still upcoming
    $running = notifyTrip(['requested_by' => $requester->id, 'status' => 'in_progress', 'vehicle_id' => Vehicle::create(['brand' => 'Toyota', 'model' => 'Hiace', 'registration_number' => 'RUN-1', 'is_active' => true, 'zone_id' => $this->dhaka->id])->id]);
    $running->forceFill(['actual_start_time' => now()->subHours(30)])->saveQuietly();

    $this->artisan('vems:trips-needing-attention')->assertSuccessful();

    expect(messagesFor($approver))->toBe(['Trips need attention: 1 still pending approval.'])
        ->and(messagesFor($logistics))->toBe(['Trips need attention: 1 approved but never started, 1 in progress for over 24 hours.']);
});

// --- route permit ----------------------------------------------------------------

it('stores a route permit as a private vehicle document', function () {
    Storage::fake('local');
    Storage::disk('local')->put('vehicle_documents/permit.pdf', 'permit');
    $vehicle = Vehicle::create(['brand' => 'Toyota', 'model' => 'Hiace', 'registration_number' => 'RP-1', 'is_active' => true,
        'route_permit_file' => 'vehicle_documents/permit.pdf', 'route_permit_last_date' => today()->addDays(3), 'alert_days_before' => 30]);

    $this->actingAs(notifyUser(['view-vehicles']))->get("/vehicles/{$vehicle->id}/documents/route_permit_file")->assertOk();
    expect(collect($vehicle->fresh()->getExpiringDocuments())->pluck('type')->all())->toBe(['route_permit']);
});

// --- create-admin -----------------------------------------------------------------

it('creates the first super-admin', function () {
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

    $this->artisan('vems:create-admin', ['--name' => 'Admin', '--username' => 'admin', '--email' => 'admin@example.org'])
        ->expectsQuestion('Password (min 8 characters)', 'secret-password')
        ->expectsQuestion('Confirm password', 'secret-password')
        ->assertSuccessful();

    $admin = User::where('username', 'admin')->first();
    expect($admin->hasRole('super-admin'))->toBeTrue()
        ->and(Hash::check('secret-password', $admin->password))->toBeTrue();
});
