<?php

namespace App\Services;

use App\Enums\TripStatus;
use App\Models\TripRecurringGroup;
use App\Models\User;
use App\Models\Zone;
use App\Support\PrivateDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Saving and deleting user accounts, shared by UserController and
 * DriverController (drivers are users). Roles, defaults and messages stay in
 * the controllers; call these inside the controller's DB transaction.
 */
class UserSaver
{
    /** Profile pictures: public disk */
    private const PUBLIC_FILES = ['image' => 'users/images', 'photo' => 'users/photos'];

    /** NID/licence scans: private (see DocumentController) */
    private const PRIVATE_FILES = ['driving_license_file', 'nid_file'];

    public function create(Request $request, array $validated): User
    {
        $attributes = $this->attributes($request, $validated);

        // Accounts provisioned by an admin are trusted without an email-click loop
        $attributes['email_verified_at'] = now();

        $user = User::create($attributes);
        $this->applyZoneAccess($request, $user, $validated);

        return $user;
    }

    public function update(Request $request, User $user, array $validated): void
    {
        $user->update($this->attributes($request, $validated, $user));
        $this->applyZoneAccess($request, $user, $validated);
    }

    /**
     * Why $user can't be deleted, or null if it can: deleting would break
     * trip history (trips.requested_by / trip_recurring_groups.created_by
     * restrict deletion), so deactivate the account instead.
     *
     * @return 'active_trips'|'requested_trips'|'recurring_groups'|null
     */
    public function deletionBlocker(User $user): ?string
    {
        return match (true) {
            $user->driverTrips()->whereIn('status', TripStatus::open())->exists() => 'active_trips',
            $user->requestedTrips()->exists() => 'requested_trips',
            TripRecurringGroup::where('created_by', $user->id)->exists() => 'recurring_groups',
            default => null,
        };
    }

    /** Delete the account and its uploaded files */
    public function delete(User $user): void
    {
        foreach (array_keys(self::PUBLIC_FILES) as $field) {
            if ($user->{$field}) {
                Storage::disk('public')->delete($user->{$field});
            }
        }
        foreach (self::PRIVATE_FILES as $field) {
            PrivateDocument::delete($user->{$field});
        }

        $user->delete();
    }

    /**
     * The validated input as model attributes: zone fields removed, uploads
     * stored (replacing $existing's files), password hashed (a blank one on
     * edit keeps the current password).
     */
    private function attributes(Request $request, array $validated, ?User $existing = null): array
    {
        unset($validated['zone_access'], $validated['zone_ids']);

        foreach (self::PUBLIC_FILES as $field => $folder) {
            if ($request->hasFile($field)) {
                if ($existing?->{$field}) {
                    Storage::disk('public')->delete($existing->{$field});
                }
                $validated[$field] = $request->file($field)->store($folder, 'public');
            }
        }

        foreach (self::PRIVATE_FILES as $field) {
            if ($request->hasFile($field)) {
                $validated[$field] = PrivateDocument::replace($existing?->{$field}, $request->file($field), 'driver_documents');
            }
        }

        if (filled($validated['password'] ?? null)) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        return $validated;
    }

    private function applyZoneAccess(Request $request, User $user, array $validated): void
    {
        if ($request->has('zone_access')) {
            Zone::applyAccess($user, $validated['zone_access'] ?? 'specific', $validated['zone_ids'] ?? []);
        }
    }
}
