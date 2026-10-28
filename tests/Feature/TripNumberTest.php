<?php

use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Trip numbers are TRP-YYYYMMDD-NNNN, unique even across deleted trips
 * (trips are soft-deleted and keep their numbers).
 */
function makeNumberedTrip(User $requester): Trip
{
    return Trip::create([
        'requested_by' => $requester->id,
        'priority' => 'medium',
        'scheduled_date' => today()->toDateString(),
        'scheduled_start_time' => '08:00:00',
        'scheduled_end_time' => '09:00:00',
        'status' => 'pending',
    ]);
}

it('does not reuse a deleted trip\'s number', function () {
    $requester = User::create([
        'name' => 'Number Requester',
        'username' => 'number.requester',
        'user_type' => 'employee',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);
    $prefix = 'TRP-'.now()->format('Ymd').'-';

    $first = makeNumberedTrip($requester);
    $second = makeNumberedTrip($requester);
    $first->delete();
    $third = makeNumberedTrip($requester);

    expect($first->trip_number)->toBe($prefix.'0001')
        ->and($second->trip_number)->toBe($prefix.'0002')
        ->and($third->trip_number)->toBe($prefix.'0003');
});
