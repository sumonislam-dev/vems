<?php

namespace App\Models;

use App\Models\Concerns\BelongsToZone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Factory extends Model
{
    use BelongsToZone, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logExcept(['updated_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('factories');
    }

    protected $fillable = [
        'account_id',
        'name',
        'status',
        'address',
        'city',
        'latitude',
        'longitude',
        'mileage_km',
        'zone_id',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'mileage_km' => 'decimal:2',
    ];

    public function trips(): BelongsToMany
    {
        return $this->belongsToMany(Trip::class, 'factory_trip')
            ->withTimestamps();
    }
}
