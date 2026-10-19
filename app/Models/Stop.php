<?php

namespace App\Models;

use App\Models\Concerns\BelongsToZone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Stop extends Model
{
    use BelongsToZone, HasFactory;

    protected $fillable = ['name', 'description', 'latitude', 'longitude', 'zone_id'];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function routeStops()
    {
        return $this->hasMany(RouteStop::class);
    }

    /**
     * Stops the current user can put on a route: their zones' stops plus
     * shared stops with no zone (which the zone scope alone would hide).
     */
    public static function selectable(): Builder
    {
        $zoneIds = Zone::accessIds();

        return static::withoutGlobalScope('zone')
            ->when($zoneIds !== null, fn (Builder $q) => $q->where(
                fn (Builder $q) => $q->whereNull('stops.zone_id')->orWhereIn('stops.zone_id', $zoneIds)
            ));
    }
}
