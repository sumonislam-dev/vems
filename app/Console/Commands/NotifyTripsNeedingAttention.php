<?php

namespace App\Console\Commands;

use App\Enums\TripStatus;
use App\Models\Trip;
use App\Models\User;
use App\Notifications\AlertNotification;
use App\Support\NotificationRecipients;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Daily: tells approvers and Logistics about trips stuck in the workflow —
 * past their date but still pending or approved, or running for over a day.
 * Nothing is changed automatically; a person decides what to do.
 */
class NotifyTripsNeedingAttention extends Command
{
    protected $signature = 'vems:trips-needing-attention';

    protected $description = 'Notify about overdue and long-running trips';

    /** Counts per recipient id and problem */
    private array $counts = [];

    /** @var array<int, User> */
    private array $recipients = [];

    /** @var array<string, Collection<int, User>> */
    private array $recipientCache = [];

    /**
     * @return Collection<int, User>
     */
    private function recipientsFor(string $permission, ?int $zoneId): Collection
    {
        return clone ($this->recipientCache["{$permission}:{$zoneId}"] ??= NotificationRecipients::withPermission($permission, $zoneId));
    }

    public function handle(): int
    {
        $past = Trip::query()->whereDate('scheduled_date', '<', today());

        (clone $past)->where('status', TripStatus::Pending->value)->with('department.head')->get()
            ->each(function (Trip $trip) {
                $approvers = $this->recipientsFor('approve-trips', $trip->zone_id);
                $head = $trip->department?->head;
                if ($head && $head->status === 'active' && $head->can('approve-department-trips')) {
                    $approvers->push($head);
                }
                $this->count($approvers, 'still pending approval');
            });

        (clone $past)->whereIn('status', TripStatus::upcoming())->get()
            ->each(fn (Trip $trip) => $this->count(
                $this->recipientsFor('assign-vehicles', $trip->zone_id),
                'approved but never started'
            ));

        Trip::query()
            ->where('status', TripStatus::InProgress->value)
            ->where('actual_start_time', '<=', now()->subDay())
            ->get()
            ->each(fn (Trip $trip) => $this->count(
                $this->recipientsFor('assign-vehicles', $trip->zone_id),
                'in progress for over 24 hours'
            ));

        foreach ($this->counts as $userId => $problems) {
            $summary = collect($problems)->map(fn ($n, $problem) => "{$n} {$problem}")->join(', ');
            $this->recipients[$userId]->notify(new AlertNotification(
                'trips_need_attention',
                "Trips need attention: {$summary}.",
                route('trips.index')
            ));
        }

        $this->info('Notified '.count($this->counts).' user(s).');

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, User>  $users
     */
    private function count(Collection $users, string $problem): void
    {
        foreach ($users->unique('id') as $user) {
            $this->recipients[$user->id] = $user;
            $this->counts[$user->id][$problem] = ($this->counts[$user->id][$problem] ?? 0) + 1;
        }
    }
}
