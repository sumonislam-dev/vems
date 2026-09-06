<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class UserGroup extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logExcept(['updated_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('user-groups');
    }

    protected $fillable = [
        'name',
        'description',
        'status',
        'created_by',
    ];

    protected $casts = [
        'status' => 'string',
    ];

    /**
     * Get the user who created this group.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get all users in this group.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'group_user')
            ->withPivot(['added_by', 'added_at'])
            ->withTimestamps();
    }

    /**
     * Get total member count.
     */
    public function getTotalMembersAttribute(): int
    {
        return $this->users()->count();
    }

    /**
     * Scope to get only active groups.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Check if user is member of this group.
     */
    public function hasMember(int $userId): bool
    {
        return $this->users()->where('user_id', $userId)->exists();
    }

    /**
     * Add member to group.
     */
    public function addMember(int $userId, ?int $addedBy = null): bool
    {
        if ($this->hasMember($userId)) {
            return false;
        }

        $this->users()->attach($userId, [
            'added_by' => $addedBy ?? auth()->id(),
            'added_at' => now(),
        ]);

        return true;
    }

    /**
     * Remove member from group.
     */
    public function removeMember(int $userId): bool
    {
        if (! $this->hasMember($userId)) {
            return false;
        }

        $this->users()->detach($userId);

        return true;
    }

    /**
     * Sync members (replace all members).
     */
    public function syncMembers(array $userIds, ?int $addedBy = null): void
    {
        $syncData = [];
        foreach ($userIds as $userId) {
            $syncData[$userId] = [
                'added_by' => $addedBy ?? auth()->id(),
                'added_at' => now(),
            ];
        }

        $this->users()->sync($syncData);
    }
}
