<?php

use App\Models\Department;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Trip requests are made without a vehicle and Logistics (assign-vehicles)
 * assigns one later; department heads (approve-department-trips) approve or
 * reject only their own departments' trips.
 */
function seedTripWorkflowPermissions(): void
{
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    foreach (['view-trips', 'view-own-trips', 'create-trips', 'edit-trips', 'approve-trips', 'reject-trips', 'approve-department-trips', 'assign-vehicles'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
}

function workflowUser(array $permissions = []): User
{
    $user = User::create([
        'email_verified_at' => now(),
        'name' => 'Workflow User',
        'username' => 'workflow_'.uniqid(),
        'email' => 'workflow_'.uniqid().'@example.com',
        'user_type' => 'employee',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);
    $user->givePermissionTo($permissions);

    return $user;
}

function workflowVehicle(): Vehicle
{
    return Vehicle::create(['brand' => 'Toyota', 'model' => 'Hiace', 'registration_number' => 'WF-'.uniqid(), 'is_active' => true]);
}

function workflowTrip(array $overrides = []): Trip
{
    return Trip::create(array_merge([
        'requested_by' => workflowUser()->id,
        'priority' => 'medium',
        'scheduled_date' => '2026-11-02',
        'scheduled_start_time' => '08:00',
        'scheduled_end_time' => '09:00',
        'status' => 'pending',
    ], $overrides));
}

function tripRequestPayload(array $overrides = []): array
{
    return array_merge([
        'priority' => 'medium',
        'scheduled_date' => '2026-11-02',
        'scheduled_start_time' => '08:00',
        'scheduled_end_time' => '09:00',
        'end_location' => 'Gazipur',
    ], $overrides);
}

// --- requesting without a vehicle ------------------------------------------

it('saves an employee request without a vehicle, even if one is submitted', function () {
    seedTripWorkflowPermissions();
    $employee = workflowUser(['create-trips', 'view-own-trips']);

    $this->actingAs($employee)->post('/trips', tripRequestPayload(['vehicle_id' => workflowVehicle()->id]))
        ->assertSessionHasNoErrors();

    expect(Trip::sole()->vehicle_id)->toBeNull();
});

it('lets Logistics book a vehicle up front', function () {
    seedTripWorkflowPermissions();
    $vehicle = workflowVehicle();

    $this->actingAs(workflowUser(['create-trips', 'assign-vehicles']))
        ->post('/trips', tripRequestPayload(['vehicle_id' => $vehicle->id]))
        ->assertSessionHasNoErrors();

    expect(Trip::sole()->vehicle_id)->toBe($vehicle->id);
});

it('lets Logistics assign a vehicle to a request without one, with no reason needed', function () {
    seedTripWorkflowPermissions();
    $trip = workflowTrip(['status' => 'approved']);
    $vehicle = workflowVehicle();

    $this->actingAs(workflowUser(['assign-vehicles']))
        ->post("/trips/{$trip->id}/reassign-vehicle", ['vehicle_id' => $vehicle->id])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Vehicle assigned.');

    expect($trip->fresh()->vehicle_id)->toBe($vehicle->id)
        ->and($trip->vehicleAssignments()->sole()->reason)->toBe('initial');
});

it('still requires a reason to change an already-assigned vehicle', function () {
    seedTripWorkflowPermissions();
    $trip = workflowTrip(['status' => 'approved', 'vehicle_id' => workflowVehicle()->id]);

    $this->actingAs(workflowUser(['assign-vehicles']))
        ->post("/trips/{$trip->id}/reassign-vehicle", ['vehicle_id' => workflowVehicle()->id])
        ->assertSessionHasErrors('reason');
});

it('requires assign-vehicles to assign a vehicle', function () {
    seedTripWorkflowPermissions();
    $trip = workflowTrip(['status' => 'approved']);

    $this->actingAs(workflowUser(['edit-trips']))
        ->post("/trips/{$trip->id}/reassign-vehicle", ['vehicle_id' => workflowVehicle()->id])
        ->assertForbidden();
});

it('keeps the vehicle when someone without assign-vehicles edits the trip', function () {
    seedTripWorkflowPermissions();
    $vehicle = workflowVehicle();
    $trip = workflowTrip(['vehicle_id' => $vehicle->id]);

    $this->actingAs(workflowUser(['edit-trips']))
        ->put("/trips/{$trip->id}", tripRequestPayload(['vehicle_id' => workflowVehicle()->id, 'end_location' => 'Savar']))
        ->assertSessionHasNoErrors();

    expect($trip->fresh()->vehicle_id)->toBe($vehicle->id)
        ->and($trip->fresh()->end_location)->toBe('Savar');
});

it('will not start a trip that has no vehicle yet', function () {
    seedTripWorkflowPermissions();
    $trip = workflowTrip(['status' => 'approved']);

    $this->actingAs(workflowUser(['edit-trips']))
        ->post("/trips/{$trip->id}/start")
        ->assertSessionHas('error', 'Assign a vehicle before starting the trip.');

    expect($trip->fresh()->status)->toBe('approved');
});

// --- department heads ------------------------------------------------------------

it('lets a department head approve and reject only their own departments\' trips', function () {
    seedTripWorkflowPermissions();
    $head = workflowUser(['view-own-trips', 'approve-department-trips']);
    $mine = Department::create(['name' => 'Inspection', 'code' => 'INS-'.uniqid(), 'head_id' => $head->id]);
    $other = Department::create(['name' => 'Training', 'code' => 'TRN-'.uniqid()]);
    $ownTrip = workflowTrip(['department_id' => $mine->id]);
    $ownTrip2 = workflowTrip(['department_id' => $mine->id]);
    $otherTrip = workflowTrip(['department_id' => $other->id]);

    $this->actingAs($head)->post("/trips/{$ownTrip->id}/approve")->assertSessionHas('success');
    $this->actingAs($head)->post("/trips/{$ownTrip2->id}/reject", ['rejection_reason' => 'Not needed'])->assertSessionHas('success');
    $this->actingAs($head)->post("/trips/{$otherTrip->id}/approve")->assertForbidden();
    $this->actingAs($head)->post("/trips/{$otherTrip->id}/reject", ['rejection_reason' => 'x'])->assertForbidden();

    expect($ownTrip->fresh()->status)->toBe('approved')
        ->and($ownTrip2->fresh()->status)->toBe('rejected')
        ->and($otherTrip->fresh()->status)->toBe('pending');
});

it('bulk-approves only the department head\'s own trips', function () {
    seedTripWorkflowPermissions();
    $head = workflowUser(['view-own-trips', 'approve-department-trips']);
    $mine = Department::create(['name' => 'Inspection', 'code' => 'INS-'.uniqid(), 'head_id' => $head->id]);
    $own = workflowTrip(['department_id' => $mine->id]);
    $other = workflowTrip();

    $this->actingAs($head)->post('/trips/bulk-approve', ['trip_ids' => [$own->id, $other->id]])
        ->assertSessionHas('success', '1 trip(s) approved.');

    expect($own->fresh()->status)->toBe('approved')
        ->and($other->fresh()->status)->toBe('pending');
});

it('shows a department head their departments\' trips, flagged for approval', function () {
    seedTripWorkflowPermissions();
    $head = workflowUser(['view-own-trips', 'approve-department-trips']);
    $mine = Department::create(['name' => 'Inspection', 'code' => 'INS-'.uniqid(), 'head_id' => $head->id]);
    $own = workflowTrip(['department_id' => $mine->id]);
    $other = workflowTrip();

    $this->actingAs($head)->get('/trips')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('trips.data', fn ($rows) => collect($rows)->pluck('id')->all() === [$own->id]
                && collect($rows)->first()['can_approve'] === true));

    $this->actingAs($head)->get("/trips/{$own->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('canApprove', true)->where('canReject', true));
    $this->actingAs($head)->get("/trips/{$other->id}")->assertForbidden();
});
