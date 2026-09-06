<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AttendanceRecord extends Model
{
    protected $fillable = [
        'user_id',
        'work_date',
        'status',
        'check_in_at',
        'check_out_at',
        'break_minutes',
        'gross_minutes',
        'net_minutes',
        'overtime_minutes',
        'source',
        'used_transport',
        'trip_passenger_event_id',
        'has_anomaly',
        'auto_closed',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
            'used_transport' => 'boolean',
            'has_anomaly' => 'boolean',
            'auto_closed' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tripPassengerEvent(): BelongsTo
    {
        return $this->belongsTo(TripPassengerEvent::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(AttendanceEvent::class);
    }

    /**
     * §5: allowed only when no open record exists for today (status absent,
     * modeled here as the default 'checked_out' on a freshly created row, or
     * checked_out from a prior correction reopen).
     */
    public function checkIn(array $eventAttributes = []): AttendanceEvent
    {
        if ($existing = $this->findByIdempotencyKey($eventAttributes['idempotency_key'] ?? null)) {
            return $existing;
        }

        if (in_array($this->status, ['checked_in', 'on_break'], true)) {
            throw new RuntimeException('Already checked in today.');
        }

        return DB::transaction(function () use ($eventAttributes) {
            if (! $this->exists) {
                $this->status = 'checked_out';
                $this->save();
            }

            $event = $this->createEvent('check_in', $eventAttributes);
            $this->update(['status' => 'checked_in']);
            $this->recalculate();

            return $event;
        });
    }

    public function startBreak(array $eventAttributes = []): AttendanceEvent
    {
        if ($existing = $this->findByIdempotencyKey($eventAttributes['idempotency_key'] ?? null)) {
            return $existing;
        }

        if ($this->status !== 'checked_in') {
            throw new RuntimeException('Cannot start a break unless currently checked in.');
        }

        return DB::transaction(function () use ($eventAttributes) {
            $event = $this->createEvent('break_start', $eventAttributes);
            $this->update(['status' => 'on_break']);

            return $event;
        });
    }

    public function endBreak(array $eventAttributes = []): AttendanceEvent
    {
        if ($existing = $this->findByIdempotencyKey($eventAttributes['idempotency_key'] ?? null)) {
            return $existing;
        }

        if ($this->status !== 'on_break') {
            throw new RuntimeException('Cannot end a break that is not open.');
        }

        return DB::transaction(function () use ($eventAttributes) {
            $event = $this->createEvent('break_end', $eventAttributes);
            $this->update(['status' => 'checked_in']);
            $this->recalculate();

            return $event;
        });
    }

    /**
     * §5: allowed from checked_in or on_break; auto-closes a dangling break
     * as a correction event first so it's never left open.
     */
    public function checkOut(array $eventAttributes = []): AttendanceEvent
    {
        if ($existing = $this->findByIdempotencyKey($eventAttributes['idempotency_key'] ?? null)) {
            return $existing;
        }

        if (! in_array($this->status, ['checked_in', 'on_break'], true)) {
            throw new RuntimeException('Cannot check out unless currently checked in or on break.');
        }

        return DB::transaction(function () use ($eventAttributes) {
            if ($this->status === 'on_break') {
                $this->createEvent('correction', [
                    'event_time' => $eventAttributes['event_time'] ?? now(),
                    'source' => $eventAttributes['source'] ?? null,
                    'actor_user_id' => $eventAttributes['actor_user_id'] ?? null,
                    'void_reason' => 'auto-closed: break left open at checkout',
                    'metadata' => ['auto_closed_break' => true],
                ]);
            }

            $event = $this->createEvent('check_out', $eventAttributes);
            $this->update(['status' => 'checked_out']);
            $this->recalculate();

            return $event;
        });
    }

    /**
     * §7: derive gross/net/overtime minutes and break_minutes by replaying
     * this record's valid events — never hand-edited directly.
     */
    public function recalculate(): void
    {
        $events = $this->events()->where('is_valid', true)->orderBy('event_time')->orderBy('id')->get();

        $checkInAt = null;
        $checkOutAt = null;
        $breakMinutes = 0;
        $openBreakStart = null;

        foreach ($events as $event) {
            switch ($event->event_type) {
                case 'check_in':
                    $checkInAt ??= $event->event_time;
                    break;
                case 'break_start':
                    $openBreakStart = $event->event_time;
                    break;
                case 'break_end':
                case 'correction':
                    if ($openBreakStart) {
                        $breakMinutes += $openBreakStart->diffInMinutes($event->event_time);
                        $openBreakStart = null;
                    }
                    break;
                case 'check_out':
                    if ($openBreakStart) {
                        $breakMinutes += $openBreakStart->diffInMinutes($event->event_time);
                        $openBreakStart = null;
                    }
                    $checkOutAt = $event->event_time;
                    break;
            }
        }

        $grossMinutes = ($checkInAt && $checkOutAt) ? $checkInAt->diffInMinutes($checkOutAt) : null;
        $netMinutes = $grossMinutes !== null ? max(0, $grossMinutes - $breakMinutes) : null;
        $standardShiftMinutes = config('attendance.standard_shift_minutes', 480);
        $overtimeMinutes = $netMinutes !== null ? max(0, $netMinutes - $standardShiftMinutes) : 0;

        $this->update([
            'check_in_at' => $checkInAt,
            'check_out_at' => $checkOutAt,
            'break_minutes' => $breakMinutes,
            'gross_minutes' => $grossMinutes,
            'net_minutes' => $netMinutes,
            'overtime_minutes' => $overtimeMinutes,
        ]);
    }

    /**
     * §4.2's correction pattern: void the disputed event and supersede it
     * with a new one, never mutate or delete the original.
     */
    public function correctEvent(AttendanceEvent $event, array $eventAttributes): AttendanceEvent
    {
        if ((int) $event->attendance_record_id !== (int) $this->id) {
            throw new RuntimeException('The selected event does not belong to this attendance record.');
        }

        return DB::transaction(function () use ($event, $eventAttributes) {
            $replacement = $this->createEvent(
                $eventAttributes['event_type'] ?? $event->event_type,
                array_merge($eventAttributes, [
                    'source' => $eventAttributes['source'] ?? 'manual',
                    'metadata' => array_merge($event->metadata ?? [], $eventAttributes['metadata'] ?? [], [
                        'corrected_event_id' => $event->id,
                        'correction_reason' => $eventAttributes['void_reason'] ?? null,
                        'original_event_type' => $event->event_type,
                    ]),
                ]),
            );

            $event->update([
                'is_valid' => false,
                'voided_at' => now(),
                'void_reason' => $eventAttributes['void_reason'] ?? 'Corrected by HR.',
                'superseded_by_event_id' => $replacement->id,
            ]);

            $this->update(['has_anomaly' => true]);
            $this->recalculate();

            return $replacement;
        });
    }

    /**
     * §5: requires an idempotency_key on every transition; replaying the same
     * key never creates a second event.
     */
    protected function findByIdempotencyKey(?string $key): ?AttendanceEvent
    {
        if (! $key || ! $this->exists) {
            return null;
        }

        return $this->events()->where('idempotency_key', $key)->first();
    }

    protected function createEvent(string $eventType, array $eventAttributes): AttendanceEvent
    {
        $idempotencyKey = $eventAttributes['idempotency_key'] ?? null;

        if ($idempotencyKey) {
            $existing = $this->events()->where('idempotency_key', $idempotencyKey)->first();

            if ($existing) {
                return $existing;
            }
        }

        return $this->events()->create([
            'user_id' => $this->user_id,
            'actor_user_id' => $eventAttributes['actor_user_id'] ?? null,
            'event_type' => $eventType,
            'event_time' => $eventAttributes['event_time'] ?? now(),
            'latitude' => $eventAttributes['latitude'] ?? null,
            'longitude' => $eventAttributes['longitude'] ?? null,
            'gps_accuracy_meters' => $eventAttributes['gps_accuracy_meters'] ?? null,
            'ip_address' => $eventAttributes['ip_address'] ?? null,
            'device_id' => $eventAttributes['device_id'] ?? null,
            'source' => $eventAttributes['source'] ?? null,
            'external_ref' => $eventAttributes['external_ref'] ?? null,
            'idempotency_key' => $idempotencyKey,
            'is_valid' => $eventAttributes['is_valid'] ?? true,
            'voided_at' => $eventAttributes['voided_at'] ?? null,
            'void_reason' => $eventAttributes['void_reason'] ?? null,
            'superseded_by_event_id' => $eventAttributes['superseded_by_event_id'] ?? null,
            'related_trip_passenger_event_id' => $eventAttributes['related_trip_passenger_event_id'] ?? null,
            'factory_id' => $eventAttributes['factory_id'] ?? null,
            'location_name' => $eventAttributes['location_name'] ?? null,
            'metadata' => $eventAttributes['metadata'] ?? null,
        ]);
    }
}
