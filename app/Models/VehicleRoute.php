<?php

namespace App\Models;

use App\Models\Concerns\BelongsToZone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class VehicleRoute extends Model
{
    use BelongsToZone, HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logExcept(['updated_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('routes');
    }

    protected $fillable = ['name', 'description', 'remarks', 'total_distance', 'zone_id'];

    public function routeStops()
    {
        return $this->hasMany(RouteStop::class);
    }
}
