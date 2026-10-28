<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripPassengerEvent extends Model
{
    use HasFactory;

    /**
     * Source of the replacement event a correction creates (the original is
     * voided). Corrections are found by this, not by event_type: the
     * 'correction' type is never written.
     */
    public const CORRECTION_SOURCE = 'admin_correction';

    protected $fillable = [
        'trip_passenger_id',
        'trip_id',
        'user_id',
        'event_type',
        'event_time',
        'stop_id',
        'latitude',
        'longitude',
        'gps_accuracy_meters',
        'ip_address',
        'area_name',
        'source',
        'actor_user_id',
        'device_id',
        'idempotency_key',
        'is_valid',
        'voided_at',
        'void_reason',
        'superseded_by_event_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'event_time' => 'datetime',
            'voided_at' => 'datetime',
            'is_valid' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function tripPassenger(): BelongsTo
    {
        return $this->belongsTo(TripPassenger::class);
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function stop(): BelongsTo
    {
        return $this->belongsTo(Stop::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by_event_id');
    }
}
