<?php

namespace App\Models\Concerns;

use App\Models\Zone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Scopes the model to the authenticated user's accessible zone(s) — see
 * Zone::accessIds() — but only for users who actually have zone access
 * assigned. A user with no zone_id and no explicit grant is not "restricted
 * to nothing"; they're simply not part of the zone system yet and see
 * everything, same as before this feature existed. This matters because
 * these models (Stop, VehicleRoute, ...) are used well beyond zone-aware
 * management screens (e.g. trip-creation pickers), so defaulting to
 * "unassigned = locked out" would break those flows for most users during
 * rollout. 'view-all-zones' (super-admin/admin) is the explicit escape valve
 * for a zoned user who should still see every zone.
 *
 * Auth::hasUser() (rather than Auth::user()) avoids re-entrant auth
 * resolution when this trait is applied to the User model itself.
 */
trait BelongsToZone
{
    public static function bootBelongsToZone(): void
    {
        static::addGlobalScope('zone', function (Builder $builder) {
            if (! Auth::hasUser()) {
                return;
            }

            $zoneIds = Zone::accessIds(Auth::user());

            if ($zoneIds === null) {
                return;
            }

            $builder->whereIn($builder->getModel()->getTable().'.zone_id', $zoneIds);
        });
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }
}
