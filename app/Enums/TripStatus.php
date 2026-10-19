<?php

namespace App\Enums;

/**
 * A trip's status. trips.status stores the string value; Trip::status is not
 * cast, so compare with ->value (e.g. $trip->status === TripStatus::Pending->value).
 *
 * pending → approved → (assigned →) in_progress → completed, with rejected and
 * cancelled as terminal off-ramps. 'assigned' is a legacy intermediate status
 * nothing currently sets, so 'approved' goes straight to 'in_progress' in practice.
 */
enum TripStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    /** Statuses this one may move to */
    public function next(): array
    {
        return match ($this) {
            self::Pending => [self::Approved, self::Rejected, self::Cancelled],
            self::Approved => [self::Assigned, self::InProgress, self::Rejected, self::Cancelled],
            self::Assigned => [self::InProgress, self::Cancelled],
            self::InProgress => [self::Completed, self::Cancelled],
            self::Completed, self::Rejected, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->next(), true);
    }

    /** Badge colour used by the UI */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'yellow',
            self::Approved => 'blue',
            self::Assigned => 'cyan',
            self::InProgress => 'purple',
            self::Completed => 'green',
            self::Rejected => 'red',
            self::Cancelled => 'gray',
        };
    }

    /** @return list<string> every status value, e.g. for an `in:` validation rule */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** @return list<string> still to happen or happening: not finished, rejected or cancelled */
    public static function open(): array
    {
        return self::valuesOf(self::Pending, self::Approved, self::Assigned, self::InProgress);
    }

    /** @return list<string> approved and waiting to start */
    public static function upcoming(): array
    {
        return self::valuesOf(self::Approved, self::Assigned);
    }

    /** @return list<string> called off: rejected or cancelled */
    public static function dropped(): array
    {
        return self::valuesOf(self::Rejected, self::Cancelled);
    }

    /** @return list<string> */
    public static function valuesOf(self ...$statuses): array
    {
        return array_map(fn (self $status) => $status->value, $statuses);
    }
}
