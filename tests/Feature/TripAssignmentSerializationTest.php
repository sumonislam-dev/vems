<?php

use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleRoute;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

/**
 * Regression coverage for a bug found while auditing trips/show.tsx's type
 * drift: Trip::cancelledBy(), TripVehicleAssignment::assignedBy(), and
 * TripRouteAssignment::assignedBy() were named to match their FK column
 * (cancelled_by / assigned_by). Laravel's Model::toArray() merges eager
 * loaded relations into the array under Str::snake($relationMethodName),
 * so those relation names collided with the raw integer FK column of the
 * same name and silently overwrote it in the JSON Inertia sent to the
 * frontend - meaning the "assigned by" / "cancelled by" user was NEVER
 * actually visible on the trip show page, even though the backend query
 * was otherwise correct. Renamed to cancelledByUser()/assignedByUser() to
 * remove the collision.
 */
function seedAssignmentSerializationPermissions(): void
{
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    Permission::firstOrCreate(['name' => 'view-trips', 'guard_name' => 'web']);
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
}

function makeAssignmentSerializationUser(string $username): User
{
    return User::create([
        'email_verified_at' => now(),
        'name' => 'User '.$username,
        'username' => $username,
        'email' => $username.'@example.com',
        'user_type' => 'transport_manager',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);
}

it('serializes assigned-by/cancelled-by users without clobbering the raw FK columns', function () {
    seedAssignmentSerializationPermissions();

    $viewer = makeAssignmentSerializationUser('viewer-1');
    $viewer->givePermissionTo('view-trips');

    $requester = makeAssignmentSerializationUser('requester-1');
    $assigner = makeAssignmentSerializationUser('assigner-1');
    $canceller = makeAssignmentSerializationUser('canceller-1');

    $vehicle = Vehicle::create([
        'brand' => 'Toyota',
        'model' => 'Hiace',
        'registration_number' => 'VEH-'.uniqid(),
        'is_active' => true,
    ]);

    $route = VehicleRoute::create([
        'name' => 'Route '.uniqid(),
        'status' => 'active',
    ]);

    $trip = Trip::create([
        'trip_number' => 'TRIP-'.uniqid(),
        'requested_by' => $requester->id,
        'priority' => 'medium',
        'scheduled_date' => '2026-05-03',
        'scheduled_start_time' => '08:00:00',
        'scheduled_end_time' => '09:00:00',
        'status' => 'cancelled',
        'vehicle_id' => $vehicle->id,
        'cancelled_by' => $canceller->id,
        'cancellation_reason' => 'other',
        'cancelled_at' => now(),
    ]);

    $trip->vehicleAssignments()->create([
        'vehicle_id' => $vehicle->id,
        'assigned_by' => $assigner->id,
        'assigned_at' => now(),
        'is_current' => true,
        'reason' => 'initial_assignment',
    ]);

    $trip->routeAssignments()->create([
        'vehicle_route_id' => $route->id,
        'assigned_by' => $assigner->id,
        'assigned_at' => now(),
        'is_current' => true,
        'reason' => 'initial_assignment',
    ]);

    $response = $this->actingAs($viewer)->get("/trips/{$trip->id}");

    $response->assertInertia(function ($page) use ($assigner, $canceller) {
        $page->where('trip.cancelled_by', $canceller->id)
            ->where('trip.cancelled_by_user.name', $canceller->name)
            ->where('vehicleAssignments.0.assigned_by', $assigner->id)
            ->where('vehicleAssignments.0.assigned_by_user.name', $assigner->name)
            ->where('routeAssignments.0.assigned_by', $assigner->id)
            ->where('routeAssignments.0.assigned_by_user.name', $assigner->name)
            ->where('routeAssignments.0.vehicle_route.name', fn ($name) => filled($name));
    });
});
