<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\AlertNotification;
use App\Support\NotificationRecipients;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Daily: reminds the zone's fleet managers about vehicle documents and
 * driving licences that are about to expire (or have expired), and drivers
 * about their own licence. Only on milestone days so nobody is nagged daily.
 */
class SendExpiryReminders extends Command
{
    protected $signature = 'vems:expiry-reminders';

    protected $description = 'Notify about expiring vehicle documents and driving licences';

    private const LICENCE_ALERT_DAYS = 30;

    /** Lines to send, keyed by recipient id */
    private array $digest = [];

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
        $this->collectVehicleDocuments();
        $this->collectDrivingLicences();

        foreach ($this->digest as $userId => $items) {
            $this->recipients[$userId]->notify($this->digestNotification(collect($items)));
        }

        $this->info('Sent expiry reminders to '.count($this->digest).' user(s).');

        return self::SUCCESS;
    }

    private function collectVehicleDocuments(): void
    {
        $documents = [
            'tax_token' => ['Tax token', 'tax_token_last_date', 'tax_token_alert_enabled'],
            'fitness' => ['Fitness certificate', 'fitness_certificate_last_date', 'fitness_alert_enabled'],
            'insurance' => ['Insurance', 'insurance_last_date', 'insurance_alert_enabled'],
            'route_permit' => ['Route permit', 'route_permit_last_date', 'route_permit_alert_enabled'],
        ];

        Vehicle::withoutGlobalScope('zone')->where('is_active', true)->get()->each(function (Vehicle $vehicle) use ($documents) {
            foreach ($documents as [$label, $dateColumn, $alertColumn]) {
                $days = $this->milestoneDays($vehicle->{$dateColumn}, $vehicle->{$alertColumn} ? ($vehicle->alert_days_before ?: 30) : null);
                if ($days === null) {
                    continue;
                }

                $line = "{$vehicle->registration_number} {$label} {$this->when($days)}";
                $url = route('vehicles.show', $vehicle);
                foreach ($this->recipientsFor('edit-vehicles', $vehicle->zone_id) as $user) {
                    $this->add($user, $line, $url);
                }
            }
        });
    }

    private function collectDrivingLicences(): void
    {
        User::withoutGlobalScope('zone')
            ->whereIn('user_type', ['driver', 'transport_manager'])
            ->where('status', 'active')
            ->whereNotNull('license_expiry_date')
            ->get()
            ->each(function (User $driver) {
                $days = $this->milestoneDays($driver->license_expiry_date, self::LICENCE_ALERT_DAYS);
                if ($days === null) {
                    return;
                }

                $url = route('drivers.show', $driver);
                foreach ($this->recipientsFor('edit-drivers', $driver->zone_id) as $user) {
                    if ($user->id !== $driver->id) {
                        $this->add($user, "{$driver->name}'s driving licence {$this->when($days)}", $url);
                    }
                }
                $this->add($driver, "Your driving licence {$this->when($days)}", null);
            });
    }

    /**
     * Days left until $date when today is a reminder day: the first alert
     * day, 7 days, 1 day, the expiry day, then weekly while expired.
     */
    private function milestoneDays(mixed $date, ?int $alertDays): ?int
    {
        if (! $date || $alertDays === null) {
            return null;
        }

        $days = (int) today()->diffInDays(Carbon::parse($date)->startOfDay(), false);

        if ($days < 0) {
            return -$days % 7 === 0 ? $days : null;
        }

        return in_array($days, array_filter([$alertDays, 7, 1, 0], fn ($d) => $d <= $alertDays), true) ? $days : null;
    }

    private function when(int $days): string
    {
        return match (true) {
            $days === 0 => 'expires today',
            $days === 1 => 'expires tomorrow',
            $days > 1 => "expires in {$days} days",
            default => 'expired '.abs($days).' days ago',
        };
    }

    private function add(User $user, string $line, ?string $url): void
    {
        $this->recipients[$user->id] = $user;
        $this->digest[$user->id][] = ['line' => $line, 'url' => $url];
    }

    /**
     * @param  Collection<int, array{line: string, url: string|null}>  $items
     */
    private function digestNotification(Collection $items): AlertNotification
    {
        if ($items->count() === 1) {
            return new AlertNotification('document_expiry', ucfirst($items->first()['line']).'.', $items->first()['url']);
        }

        $shown = $items->take(3)->pluck('line')->join('; ');
        $more = $items->count() > 3 ? ' and '.($items->count() - 3).' more' : '';

        return new AlertNotification(
            'document_expiry',
            "{$items->count()} documents need attention: {$shown}{$more}.",
            route('dashboard')
        );
    }
}
