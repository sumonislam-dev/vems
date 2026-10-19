<?php

namespace App\Support;

use App\Models\User;
use App\Models\Zone;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;

/**
 * Who should hear about something: active users holding a permission
 * (directly or via a role) who can see the record's zone.
 */
class NotificationRecipients
{
    /**
     * @return Collection<int, User>
     */
    public static function withPermission(string $permission, ?int $zoneId): Collection
    {
        if (! Permission::where('name', $permission)->where('guard_name', 'web')->exists()) {
            return collect();
        }

        // Not scoped to whoever triggered the event
        return User::withoutGlobalScopes()
            ->where('status', 'active')
            ->permission($permission)
            ->get()
            ->filter(fn (User $user) => self::canSeeZone($user, $zoneId))
            ->values();
    }

    public static function canSeeZone(User $user, ?int $zoneId): bool
    {
        if ($zoneId === null) {
            return true;
        }

        $zoneIds = Zone::realAccessIds($user);

        return $zoneIds === null || in_array($zoneId, array_map('intval', $zoneIds), true);
    }
}
