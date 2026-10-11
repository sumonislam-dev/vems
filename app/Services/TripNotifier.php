<?php

namespace App\Services;

use App\Models\Trip;
use App\Models\TripFeedback;
use App\Models\User;
use App\Notifications\AlertNotification;
use App\Support\NotificationRecipients;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * In-app notifications for the trip workflow. Recipients are limited to the
 * trip's zone, and whoever caused the event is never notified about it.
 */
class TripNotifier
{
    /**
     * A new request (or a recurring series of $count trips) awaits approval:
     * approvers in the zone plus the head of the trip's department.
     */
    public function requested(Trip $trip, int $count = 1): void
    {
        $trip->loadMissing('requester:id,name', 'department.head');
        $requester = $trip->requester?->name ?? 'Someone';

        $message = $count > 1
            ? "New recurring trip request from {$requester}: {$count} trips from ".Carbon::parse($trip->recurring_start_date)->format('d M').' to '.Carbon::parse($trip->recurring_end_date)->format('d M Y').'.'
            : "New trip request {$trip->trip_number} from {$requester} for {$this->date($trip)}.";

        $head = $trip->department?->head;
        $recipients = NotificationRecipients::withPermission('approve-trips', $trip->zone_id);
        if ($head && $head->status === 'active' && $head->can('approve-department-trips')) {
            $recipients->push($head);
        }

        $this->send($recipients, 'trip_requested', $message, $trip);
    }

    /**
     * Approved trips without a vehicle: Logistics in each zone must assign
     * one. Several trips in a zone are summed up in one notification.
     *
     * @param  Collection<int, Trip>  $trips
     */
    public function needVehicles(Collection $trips): void
    {
        $trips->filter(fn (Trip $trip) => ! $trip->vehicle_id)
            ->groupBy(fn (Trip $trip) => (string) $trip->zone_id)
            ->each(function (Collection $zoneTrips) {
                $first = $zoneTrips->first();
                $recipients = NotificationRecipients::withPermission('assign-vehicles', $first->zone_id);

                if ($zoneTrips->count() === 1) {
                    $this->send($recipients, 'trip_needs_vehicle', "Trip {$first->trip_number} ({$this->date($first)}) is approved and needs a vehicle.", $first);

                    return;
                }

                $recipients = $recipients->reject(fn (User $user) => $user->id === auth()->id());
                Notification::send($recipients, new AlertNotification(
                    'trip_needs_vehicle',
                    "{$zoneTrips->count()} approved trips need a vehicle.",
                    route('trips.index')
                ));
            });
    }

    /**
     * Tell the requester which vehicle they got, and the driver about the trip.
     */
    public function vehicleAssigned(Trip $trip): void
    {
        $trip->loadMissing('vehicle.driver', 'requester', 'driver');
        $registration = $trip->vehicle?->registration_number ?? 'A vehicle';

        $this->send(
            collect([$trip->requester]),
            'trip_vehicle_assigned',
            "{$registration} is assigned to your trip {$trip->trip_number} on {$this->date($trip)}.",
            $trip
        );
        $this->send(
            collect([$this->driverOf($trip)]),
            'trip_vehicle_assigned',
            "You are driving trip {$trip->trip_number} on {$this->date($trip)} ({$registration}).",
            $trip
        );
    }

    /**
     * @param  Collection<int, Trip>  $trips  cancelled trips (one, or part of a series)
     */
    public function cancelled(Collection $trips): void
    {
        if ($trips->isEmpty()) {
            return;
        }

        $trips->each->loadMissing('requester', 'driver', 'vehicle.driver');
        $first = $trips->first();

        if ($trips->count() === 1) {
            $message = "Trip {$first->trip_number} on {$this->date($first)} was cancelled.";
            $this->send(collect([$first->requester, $this->driverOf($first)]), 'trip_cancelled', $message, $first);

            return;
        }

        $this->send(
            collect([$first->requester]),
            'trip_cancelled',
            "{$trips->count()} trips in your recurring series were cancelled.",
            $first
        );
        $trips->groupBy(fn (Trip $trip) => $this->driverOf($trip)?->id)
            ->filter(fn ($driverTrips, $driverId) => $driverId !== '')
            ->each(fn (Collection $driverTrips) => $this->send(
                collect([$this->driverOf($driverTrips->first())]),
                'trip_cancelled',
                "{$driverTrips->count()} of your trips in a recurring series were cancelled.",
                $driverTrips->first()
            ));
    }

    /**
     * A new complaint (not plain feedback) for the zone's complaint managers.
     */
    public function complaintSubmitted(TripFeedback $complaint): void
    {
        if ($complaint->type !== 'complaint') {
            return;
        }

        $complaint->loadMissing('trip:id,trip_number,zone_id');
        $category = str_replace('_', ' ', (string) $complaint->category);

        $recipients = NotificationRecipients::withPermission('assign-complaints', $complaint->trip?->zone_id)
            ->reject(fn (User $user) => $user->id === auth()->id());

        Notification::send($recipients, new AlertNotification(
            'complaint_submitted',
            "New complaint on trip {$complaint->trip?->trip_number}: {$category}.",
            route('complaints.show', $complaint)
        ));
    }

    private function driverOf(Trip $trip): ?User
    {
        return $trip->driver ?? $trip->vehicle?->driver;
    }

    private function date(Trip $trip): string
    {
        return $trip->scheduled_date?->format('d M Y') ?? 'an unscheduled date';
    }

    /**
     * @param  Collection<int, User|null>  $recipients
     */
    private function send(Collection $recipients, string $type, string $message, Trip $trip): void
    {
        $recipients = $recipients->filter()
            ->unique('id')
            ->reject(fn (User $user) => $user->id === auth()->id());

        Notification::send($recipients, new AlertNotification($type, $message, route('trips.show', $trip)));
    }
}
