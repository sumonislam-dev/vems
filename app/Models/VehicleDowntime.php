<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A period a vehicle was out of service. ends_on is the last day it was
 * down (inclusive), or null while it still is.
 */
class VehicleDowntime extends Model
{
    public const REASONS = [
        'repair' => 'Repair',
        'accident' => 'Accident',
        'servicing' => 'Servicing',
        'document_renewal' => 'Document renewal',
        'other' => 'Other',
    ];

    /** Reasons that count as a breakdown in vendor performance */
    public const BREAKDOWN_REASONS = ['repair', 'accident'];

    protected $fillable = [
        'vehicle_id',
        'starts_on',
        'ends_on',
        'reason',
        'replacement_vehicle_id',
        'notes',
        'recorded_by',
    ];

    protected $casts = [
        'starts_on' => 'date:Y-m-d',
        'ends_on' => 'date:Y-m-d',
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    public function replacementVehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'replacement_vehicle_id')->withTrashed();
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('ends_on');
    }

    /** Downtimes that cover any day between $from and $to (inclusive) */
    public function scopeOverlapping(Builder $query, Carbon|string $from, Carbon|string $to): Builder
    {
        $from = Carbon::parse($from)->toDateString();
        $to = Carbon::parse($to)->toDateString();

        return $query->where('starts_on', '<=', $to)
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', $from));
    }

    public function coversDate(Carbon|string $date): bool
    {
        $date = Carbon::parse($date)->startOfDay();

        return $this->starts_on->lte($date) && ($this->ends_on === null || $this->ends_on->gte($date));
    }

    public function getReasonLabelAttribute(): string
    {
        return self::REASONS[$this->reason] ?? ucfirst((string) $this->reason);
    }
}
