<?php

namespace App\Models;

use App\Models\Concerns\BelongsToZone;
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
}
