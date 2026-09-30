<?php

namespace App\Observers;

use App\Models\User;

class UserObserver
{
    /**
     * A user who is no longer active has no business staying in a user
     * group (which drives things like trip passenger selection lists), and
     * a reactivated user shouldn't silently lose the memory of what they
     * were removed from. UserController's deactivate()/activate() actions
     * set $user->groupMembershipChoice from an explicit confirm-dialog
     * checkbox to override the defaults below; any other path that changes
     * status (the plain edit form, CSV import) gets the safe defaults:
     * always remove on disable, never auto-restore on reactivate.
     */
    public function updated(User $user): void
    {
        if (! $user->wasChanged('status')) {
            return;
        }

        $choice = $user->groupMembershipChoice;

        if ($user->status !== 'active') {
            if (! ($choice['remove'] ?? true)) {
                return;
            }

            $groupIds = $user->groups()->pluck('user_groups.id')->all();

            if (empty($groupIds)) {
                return;
            }

            $user->groups()->detach();
            $user->forceFill(['previous_group_ids' => $groupIds])->saveQuietly();

            return;
        }

        // Status is now "active".
        $previousIds = $user->previous_group_ids ?? [];

        if (empty($previousIds)) {
            return;
        }

        if ($choice['restore_previous'] ?? false) {
            $user->groups()->syncWithoutDetaching($previousIds);
        }

        $user->forceFill(['previous_group_ids' => null])->saveQuietly();
    }
}
