<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripRouteAssignment extends Model
{
    protected $fillable = [
        'trip_id',
        'vehicle_route_id',
        'assigned_by',
        'assigned_at',
        'unassigned_at',
        'is_current',
        'reason',
        'notes',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'unassigned_at' => 'datetime',
        'is_current' => 'boolean',
    ];

    /**
     * Get the trip that this route assignment belongs to.
     */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    /**
     * Get the vehicle route that was assigned.
     */
    public function vehicleRoute(): BelongsTo
    {
        return $this->belongsTo(VehicleRoute::class);
    }

    /**
     * Get the user who assigned this route.
     *
     * Named assignedByUser (not assignedBy) so the eager-loaded relation
     * doesn't serialize to the same JSON key as the raw assigned_by FK
     * column and silently overwrite it.
     */
    public function assignedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * Scope for current assignments
     */
    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    /**
     * Scope for active assignments (not unassigned)
     */
    public function scopeActive($query)
    {
        return $query->whereNull('unassigned_at');
    }
}
