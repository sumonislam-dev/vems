<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TripPassenger extends Model
{
    use HasFactory;

    protected $fillable = [
        'trip_id',
        'user_id',
        'pickup_stop_id',
        'dropoff_stop_id',
        'status',
        'boarded_at',
        'dropped_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'boarded_at' => 'datetime',
            'dropped_at' => 'datetime',
        ];
    }

    // Relationships
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pickupStop(): BelongsTo
    {
        return $this->belongsTo(Stop::class, 'pickup_stop_id');
    }

    public function dropoffStop(): BelongsTo
    {
        return $this->belongsTo(Stop::class, 'dropoff_stop_id');
    }

    public function passengerEvents(): HasMany
    {
        return $this->hasMany(TripPassengerEvent::class);
    }

    // Helper Methods
    public function markAsBoarded(array $eventAttributes = []): TripPassengerEvent
    {
        $eventTime = $eventAttributes['event_time'] ?? now();

        return $this->recordPassengerEvent('check_in', $eventAttributes, [
            'status' => 'boarded',
            'boarded_at' => $eventTime,
        ]);
    }

    public function markAsDropped(array $eventAttributes = []): TripPassengerEvent
    {
        $eventTime = $eventAttributes['event_time'] ?? now();

        return $this->recordPassengerEvent('check_out', $eventAttributes, [
            'status' => 'completed',
            'dropped_at' => $eventTime,
        ]);
    }

    public function markAsNoShow(array $eventAttributes = []): TripPassengerEvent
    {
        return $this->recordPassengerEvent('no_show', $eventAttributes, [
            'status' => 'no_show',
        ]);
    }

    public function correctPassengerEvent(TripPassengerEvent $event, array $eventAttributes): TripPassengerEvent
    {
        if ((int) $event->trip_passenger_id !== (int) $this->id) {
            throw new InvalidArgumentException('The selected event does not belong to this passenger.');
        }

        return DB::transaction(function () use ($event, $eventAttributes) {
            $replacementEvent = $this->createPassengerEvent(
                $eventAttributes['event_type'] ?? $event->event_type,
                array_merge($eventAttributes, [
                    'source' => $eventAttributes['source'] ?? TripPassengerEvent::CORRECTION_SOURCE,
                    'metadata' => array_merge($event->metadata ?? [], $eventAttributes['metadata'] ?? [], [
                        'corrected_event_id' => $event->id,
                        'correction_reason' => $eventAttributes['reason'] ?? null,
                        'original_event_type' => $event->event_type,
                    ]),
                ]),
            );

            $event->update([
                'is_valid' => false,
                'voided_at' => now(),
                'void_reason' => $eventAttributes['reason'] ?? 'Corrected by admin.',
                'superseded_by_event_id' => $replacementEvent->id,
            ]);

            $this->syncSnapshotFromEvents();

            return $replacementEvent;
        });
    }

    protected function recordPassengerEvent(string $eventType, array $eventAttributes, array $snapshotAttributes): TripPassengerEvent
    {
        return DB::transaction(function () use ($eventType, $eventAttributes, $snapshotAttributes) {
            $event = $this->createPassengerEvent($eventType, $eventAttributes);

            $this->update($snapshotAttributes);

            return $event;
        });
    }

    public function syncSnapshotFromEvents(): void
    {
        $latestCheckIn = $this->passengerEvents()
            ->where('is_valid', true)
            ->where('event_type', 'check_in')
            ->latest('event_time')
            ->latest('id')
            ->first();

        $latestCheckOut = $this->passengerEvents()
            ->where('is_valid', true)
            ->where('event_type', 'check_out')
            ->latest('event_time')
            ->latest('id')
            ->first();

        $latestNoShow = $this->passengerEvents()
            ->where('is_valid', true)
            ->where('event_type', 'no_show')
            ->latest('event_time')
            ->latest('id')
            ->first();

        $status = $this->status;

        if ($latestCheckOut) {
            $status = 'completed';
        } elseif ($latestCheckIn) {
            $status = 'boarded';
        } elseif ($latestNoShow) {
            $status = 'no_show';
        } elseif (in_array($this->status, ['boarded', 'completed', 'no_show'], true)) {
            $status = 'pending';
        }

        $this->update([
            'status' => $status,
            'boarded_at' => $latestCheckIn?->event_time,
            'dropped_at' => $latestCheckOut?->event_time,
        ]);
    }

    protected function createPassengerEvent(string $eventType, array $eventAttributes): TripPassengerEvent
    {
        $idempotencyKey = $eventAttributes['idempotency_key'] ?? null;

        if ($idempotencyKey) {
            $existingEvent = $this->passengerEvents()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existingEvent) {
                return $existingEvent;
            }
        }

        return $this->passengerEvents()->create([
            'trip_id' => $this->trip_id,
            'user_id' => $this->user_id,
            'event_type' => $eventType,
            'event_time' => $eventAttributes['event_time'] ?? now(),
            'stop_id' => $eventAttributes['stop_id'] ?? null,
            'latitude' => $eventAttributes['latitude'] ?? null,
            'longitude' => $eventAttributes['longitude'] ?? null,
            'gps_accuracy_meters' => $eventAttributes['gps_accuracy_meters'] ?? null,
            'ip_address' => $eventAttributes['ip_address'] ?? null,
            'area_name' => $eventAttributes['area_name'] ?? null,
            'source' => $eventAttributes['source'] ?? null,
            'actor_user_id' => $eventAttributes['actor_user_id'] ?? null,
            'device_id' => $eventAttributes['device_id'] ?? null,
            'idempotency_key' => $idempotencyKey,
            'is_valid' => $eventAttributes['is_valid'] ?? true,
            'voided_at' => $eventAttributes['voided_at'] ?? null,
            'void_reason' => $eventAttributes['void_reason'] ?? null,
            'superseded_by_event_id' => $eventAttributes['superseded_by_event_id'] ?? null,
            'metadata' => $eventAttributes['metadata'] ?? null,
        ]);
    }
}
