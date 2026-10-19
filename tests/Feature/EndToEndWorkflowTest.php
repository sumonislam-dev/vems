<?php

use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Artisan;

/**
 * Smoke test over the real demo data (db:seed): one trip through every role,
 * as in the walkthrough checklist (docs/UAT_CHECKLIST.md).
 */
function demoUser(string $username): User
{
    return User::where('username', $username)->firstOrFail();
}

/** All of a user's notification messages, joined (several can share a timestamp) */
function messagesOf(User $user): string
{
    return $user->fresh()->notifications->pluck('data.message')->join(' | ');
}

it('walks a trip request through every role on the demo data', function () {
    Artisan::call('db:seed');

    $employee = demoUser('maria.admin');      // Engagement and Logistics department
    $head = demoUser('khadiza.rahman');       // department-head of Engagement and Logistics
    $logistics = demoUser('sarah.manager');   // transport-manager, all offices
    $management = demoUser('hasan.sarker');   // read-only
    $driver = demoUser('ahmed.driver');
    $vehicle = Vehicle::where('registration_number', 'ABC-123')->firstOrFail();
    $otherEmployee = demoUser('john.hr');

    // 1. Employee requests a trip, without a vehicle
    $this->actingAs($employee)->post('/trips', [
        'priority' => 'medium',
        'scheduled_date' => '2027-01-15',
        'scheduled_start_time' => '08:00',
        'scheduled_end_time' => '10:00',
        'end_location' => 'Gazipur',
        'department_id' => $employee->department_id,
    ])->assertSessionHasNoErrors();
    $trip = Trip::where('requested_by', $employee->id)->latest('id')->firstOrFail();
    expect($trip->status)->toBe('pending')
        ->and($trip->vehicle_id)->toBeNull()
        ->and(messagesOf($head))->toContain('New trip request');

    // Another employee can't see it
    $this->actingAs($otherEmployee)->get("/trips/{$trip->id}")->assertForbidden();

    // 2. Department head approves
    $this->actingAs($head)->post("/trips/{$trip->id}/approve")->assertSessionHas('success');
    expect($trip->fresh()->status)->toBe('approved')
        ->and(messagesOf($logistics))->toContain('needs a vehicle');

    // 3. Logistics gives the vehicle a driver, then assigns it to the trip
    $this->actingAs($logistics)->post("/vehicles/{$vehicle->id}/assign-driver", ['driver_id' => $driver->id])->assertSessionHasNoErrors();
    $this->actingAs($logistics)->post("/trips/{$trip->id}/reassign-vehicle", ['vehicle_id' => $vehicle->id])
        ->assertSessionHas('success', 'Vehicle assigned.');
    expect(messagesOf($employee))->toContain('is assigned to your trip')
        ->and(messagesOf($driver))->toContain('You are driving trip');

    // 4. The driver starts (from the vehicle's last reading) and completes it
    $lastReading = (float) Trip::where('vehicle_id', $vehicle->id)->max('odometer_end');
    $this->actingAs($driver)->post("/trips/{$trip->id}/start", ['odometer_start' => $lastReading + 5])->assertSessionHas('success');
    $this->actingAs($driver)->post("/trips/{$trip->id}/complete", ['odometer_end' => $lastReading + 50])->assertSessionHas('success');
    expect($trip->fresh()->status)->toBe('completed')
        ->and((float) $trip->fresh()->distance_traveled)->toBe(45.0);

    // 5. The employee complains; Logistics hears about it
    $this->actingAs($employee)->post('/complaints', [
        'trip_id' => $trip->id,
        'type' => 'complaint',
        'category' => 'punctuality',
        'subject' => 'Late pickup',
        'description' => 'The car came 30 minutes late.',
    ])->assertSessionHasNoErrors();
    expect(messagesOf($logistics))->toContain('New complaint');

    // 6. Management can read reports but not change anything
    $this->actingAs($management)->get('/reports/driver-performance?from=2027-01-01&to=2027-01-31')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('report.sections.0.rows.0.driver', $driver->name));
    $this->actingAs($management)->get('/reports/complaints?from='.today()->startOfMonth()->toDateString().'&to='.today()->toDateString())->assertOk();
    $this->actingAs($management)->get('/users')->assertForbidden();
    $this->actingAs($management)->post("/trips/{$trip->id}/cancel", ['cancellation_reason' => 'other'])->assertForbidden();
});
