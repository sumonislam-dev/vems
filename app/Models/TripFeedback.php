<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripFeedback extends Model
{
    use HasFactory;

    protected $table = 'trip_feedback';

    protected $fillable = [
        'trip_id',
        'submitted_by',
        'type',
        'category',
        'subject',
        'description',
        'driver_rating',
        'vehicle_rating',
        'priority',
        'status',
        'assigned_to',
        'resolution_notes',
        'resolved_at',
        'resolved_by',
        'closed_at',
        'is_anonymous',
    ];

    protected function casts(): array
    {
        return [
            'driver_rating' => 'integer',
            'vehicle_rating' => 'integer',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'is_anonymous' => 'boolean',
        ];
    }

    // Relationships

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    // Visibility scope: users with the "view all" permission see everything,
    // everyone else is restricted to what they submitted themselves.
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->can('view-complaints')) {
            return $query;
        }

        return $query->where('submitted_by', $user->id);
    }

    /**
     * Leave out who submitted an anonymous entry before it goes to anyone
     * but the submitter: hiding the name in the UI isn't enough, as the
     * page data would still carry it.
     */
    public function hideAnonymousSubmitterFrom(User $viewer): static
    {
        if ($this->is_anonymous && $this->submitted_by !== $viewer->id) {
            $this->unsetRelation('submitter')->makeHidden('submitted_by');
        }

        return $this;
    }

    // Status lifecycle: a handler may resolve or close straight from open or
    // in review (assigning first is optional), and a resolution may be reopened.

    public function canTransitionTo(string $newStatus): bool
    {
        return match ($this->status) {
            'open' => in_array($newStatus, ['in_review', 'resolved', 'closed']),
            'in_review' => in_array($newStatus, ['resolved', 'open', 'closed']),
            'resolved' => in_array($newStatus, ['closed', 'in_review']),
            'closed' => false,
            default => false,
        };
    }

    public function transitionTo(string $newStatus, array $attributes = []): bool
    {
        if (! $this->canTransitionTo($newStatus)) {
            return false;
        }

        $this->update(array_merge($attributes, ['status' => $newStatus]));

        return true;
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'open' => 'yellow',
            'in_review' => 'blue',
            'resolved' => 'green',
            'closed' => 'gray',
            default => 'gray',
        };
    }

    public function getPriorityColorAttribute(): string
    {
        return match ($this->priority) {
            'critical' => 'red',
            'high' => 'orange',
            'medium' => 'blue',
            'low' => 'slate',
            default => 'slate',
        };
    }
}
