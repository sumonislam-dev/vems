<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\Rule;

class Zone extends Model
{
    protected $fillable = [
        'name',
        'code',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function stops(): HasMany
    {
        return $this->hasMany(Stop::class);
    }

    public function vehicleRoutes(): HasMany
    {
        return $this->hasMany(VehicleRoute::class);
    }

    public function factories(): HasMany
    {
        return $this->hasMany(Factory::class);
    }

    public function vendors(): HasMany
    {
        return $this->hasMany(Vendor::class);
    }

    public function logistics(): HasMany
    {
        return $this->hasMany(Logistics::class);
    }

    /**
     * The zone IDs $user's role/grants allow — null means unrestricted
     * (sees/can-grant every zone). An explicit multi-zone grant in
     * `user_zone` (see User::accessZones()) takes priority; otherwise falls
     * back to the user's single `zone_id`, the common single-zone case.
     * This ignores the active-zone switcher (see accessIds()) — it's the
     * user's full underlying grant, used only to populate the switcher's
     * own options and to validate a switch request.
     *
     * @return array<int>|null
     */
    public static function realAccessIds(?User $user = null): ?array
    {
        $user ??= auth()->user();

        if (! $user || $user->can('view-all-zones')) {
            return null;
        }

        $granted = $user->accessZones()->pluck('zones.id')->all();

        if (! empty($granted)) {
            return $granted;
        }

        return $user->zone_id ? [$user->zone_id] : null;
    }

    /**
     * The zone IDs $user is currently scoped to — null means unrestricted.
     * This is realAccessIds() narrowed by the navbar zone switcher's
     * session selection, if any: switching to one zone is a temporary
     * viewing filter on top of a user's real access, never a way to see
     * more than that access allows. A stale/invalid session value (e.g. a
     * zone the user's grant no longer includes) is ignored. Used by
     * BelongsToZone and the dropdown/validation helpers below.
     *
     * @return array<int>|null
     */
    public static function accessIds(?User $user = null): ?array
    {
        $real = static::realAccessIds($user);
        $active = session('active_zone_id');

        if (! $active) {
            return $real;
        }

        if ($real !== null && ! in_array($active, $real, true)) {
            return $real;
        }

        return [$active];
    }

    /**
     * Zones $user can switch their current view between, via the navbar
     * zone switcher — their full real access (not narrowed by any current
     * selection), or an empty collection if they only ever have one zone
     * (or none), since there's nothing to switch between.
     */
    public static function switcherOptions(?User $user = null): Collection
    {
        $user ??= auth()->user();

        if (! $user) {
            return new Collection;
        }

        $realIds = static::realAccessIds($user);
        $query = static::query()->orderBy('name');

        if ($realIds === null) {
            $query->active();
        } elseif (count($realIds) <= 1) {
            return new Collection;
        } else {
            $query->whereIn('id', $realIds);
        }

        $zones = $query->get(['id', 'name']);

        return $zones->count() > 1 ? $zones : new Collection;
    }

    /**
     * Zones a form's dropdown should offer $user: every active zone for
     * someone unrestricted (no zone access of their own, or 'view-all-zones'),
     * or just their accessible zone(s) otherwise. $includeZoneIds keeps a
     * record's current zone(s) selectable even if they fall outside that —
     * e.g. a restricted editor opening a record an unrestricted admin created
     * in another zone. Accepts a single id (legacy call sites) or an array.
     */
    public static function selectableBy(?User $user = null, array|int|null $includeZoneIds = null): Collection
    {
        $user ??= auth()->user();
        $allowedIds = static::accessIds($user);
        $includeIds = $includeZoneIds === null ? [] : (array) $includeZoneIds;

        $query = static::query()->orderBy('name');

        if ($allowedIds === null) {
            $query->where(function ($q) use ($includeIds) {
                $q->where('is_active', true);
                if (! empty($includeIds)) {
                    $q->orWhereIn('id', $includeIds);
                }
            });
        } else {
            $query->where(function ($q) use ($allowedIds, $includeIds) {
                $q->whereIn('id', $allowedIds);
                $extra = array_diff($includeIds, $allowedIds);
                if (! empty($extra)) {
                    $q->orWhereIn('id', $extra);
                }
            });
        }

        return $query->get(['id', 'name']);
    }

    /**
     * The zone IDs $user is allowed to save on a record — null means
     * unrestricted (any zone), used to build the server-side validation
     * rule so a restricted user can't bypass the dropdown by posting an
     * arbitrary zone_id directly.
     *
     * @return array<int>|null
     */
    public static function selectableIds(?User $user = null): ?array
    {
        return static::accessIds($user);
    }

    /**
     * The validation rule for a zone_id field: standard existence check,
     * plus Rule::in() when $user is restricted to their own zone — so a
     * restricted user can't bypass the dropdown by posting an arbitrary
     * zone_id directly.
     *
     * @return array<mixed>
     */
    public static function validationRule(?User $user = null): array
    {
        $rule = ['nullable', 'exists:zones,id'];

        if ($allowedIds = static::selectableIds($user)) {
            $rule[] = Rule::in($allowedIds);
        }

        return $rule;
    }

    /**
     * Persist $user's zone access as chosen on the User/Driver form.
     * 'all' grants 'view-all-zones' directly to this user (independent of
     * their role — see BelongsToZone/accessIds). 'specific' syncs the given
     * zone ids onto user_zone, mirrors the first onto the legacy zone_id
     * column (used elsewhere purely to default this user's own create
     * forms), and revokes 'view-all-zones' if it had been granted directly
     * — a role-inherited grant (e.g. admin) is left untouched, since this
     * never demotes a role.
     *
     * @param  array<int|string>  $zoneIds
     */
    public static function applyAccess(User $user, string $mode, array $zoneIds = []): void
    {
        if ($mode === 'all') {
            $user->givePermissionTo('view-all-zones');

            return;
        }

        if ($user->hasDirectPermission('view-all-zones')) {
            $user->revokePermissionTo('view-all-zones');
        }

        $user->accessZones()->sync($zoneIds);
        $user->update(['zone_id' => $zoneIds[0] ?? null]);
    }

    /**
     * City → zone code. Bangladesh geography, not exhaustive — only cities
     * actually seen in this app's data so far. Used by the seeders.
     */
    private const CITY_ZONE_CODES = [
        'dhaka' => 'DHK',
        'narayanganj' => 'DHK',
        'gazipur' => 'DHK',
        'narsingdi' => 'DHK',
        'mymensingh' => 'DHK',
        'chittagong' => 'CTG',
        'chattogram' => 'CTG',
    ];

    /**
     * Resolve a zone id from a city name or a free-text address containing
     * one — e.g. both "Dhaka" and "456 Commercial Area, Chittagong-4000,
     * Bangladesh" resolve correctly. Returns null when no known city is
     * found in $text, or the matching zone doesn't exist (e.g. this app's
     * zones table hasn't been seeded with that code).
     */
    public static function idForCity(?string $text): ?int
    {
        if (! $text) {
            return null;
        }

        $haystack = strtolower($text);

        foreach (self::CITY_ZONE_CODES as $city => $code) {
            if (str_contains($haystack, $city)) {
                return static::where('code', $code)->value('id');
            }
        }

        return null;
    }
}
