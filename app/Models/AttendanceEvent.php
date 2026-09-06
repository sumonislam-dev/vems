<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceEvent extends Model
{
    protected $fillable = [
        'attendance_record_id',
        'user_id',
        'actor_user_id',
        'event_type',
        'event_time',
        'latitude',
        'longitude',
        'gps_accuracy_meters',
        'ip_address',
        'device_id',
        'source',
        'external_ref',
        'idempotency_key',
        'is_valid',
        'voided_at',
        'void_reason',
        'superseded_by_event_id',
        'related_trip_passenger_event_id',
        'factory_id',
        'location_name',
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

    public function attendanceRecord(): BelongsTo
    {
        return $this->belongsTo(AttendanceRecord::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by_event_id');
    }

    public function relatedTripPassengerEvent(): BelongsTo
    {
        return $this->belongsTo(TripPassengerEvent::class, 'related_trip_passenger_event_id');
    }

    public function factory(): BelongsTo
    {
        return $this->belongsTo(Factory::class);
    }
}
