<?php

use App\Models\Trip;
use App\Models\TripFeedback;
use App\Models\User;
use App\Notifications\ComplaintAssigned;
use Illuminate\Support\Facades\Hash;

function makeNotificationUser(string $username): User
{
    return User::create([
        'email_verified_at' => now(),
        'name' => 'Notification User '.$username,
        'username' => $username,
        'email' => $username.'@example.com',
        'user_type' => 'employee',
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);
}

function makeNotificationComplaint(User $submitter, string $subject): TripFeedback
{
    $trip = Trip::create([
        'trip_number' => 'TRIP-NOTIF-'.uniqid(),
        'requested_by' => $submitter->id,
        'priority' => 'medium',
        'scheduled_date' => now()->toDateString(),
        'scheduled_start_time' => '08:00:00',
        'scheduled_end_time' => '09:00:00',
        'status' => 'completed',
    ]);

    return TripFeedback::create([
        'trip_id' => $trip->id,
        'submitted_by' => $submitter->id,
        'type' => 'complaint',
        'category' => 'safety',
        'subject' => $subject,
        'description' => 'Description.',
        'priority' => 'medium',
        'status' => 'open',
    ]);
}

it('shares unread notifications on every inertia request', function () {
    $user = makeNotificationUser('shared-1');
    $complaint = makeNotificationComplaint($user, 'Test subject');
    $user->notify(new ComplaintAssigned($complaint));

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('notifications.unread_count', 1)
            ->where('notifications.items.0.read', false)
        );
});

it('marks a single notification as read and redirects back', function () {
    $user = makeNotificationUser('read-1');
    $complaint = makeNotificationComplaint($user, 'Another subject');
    $user->notify(new ComplaintAssigned($complaint));
    $notificationId = $user->notifications()->first()->id;

    $this->actingAs($user)
        ->post("/notifications/{$notificationId}/read")
        ->assertRedirect();

    expect($user->unreadNotifications()->count())->toBe(0);
});

it('marks all notifications as read', function () {
    $user = makeNotificationUser('read-all-1');
    $user->notify(new ComplaintAssigned(makeNotificationComplaint($user, 'One')));
    $user->notify(new ComplaintAssigned(makeNotificationComplaint($user, 'Two')));

    expect($user->unreadNotifications()->count())->toBe(2);

    $this->actingAs($user)
        ->post('/notifications/read-all')
        ->assertRedirect();

    expect($user->unreadNotifications()->count())->toBe(0);
});

it('does not let a user mark another user\'s notification as read', function () {
    $owner = makeNotificationUser('owner-1');
    $complaint = makeNotificationComplaint($owner, 'Owned');
    $owner->notify(new ComplaintAssigned($complaint));
    $notificationId = $owner->notifications()->first()->id;

    $other = makeNotificationUser('other-1');

    $this->actingAs($other)
        ->post("/notifications/{$notificationId}/read")
        ->assertNotFound();

    expect($owner->unreadNotifications()->count())->toBe(1);
});
