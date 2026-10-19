<?php

namespace App\Http\Controllers;

use App\Enums\TripStatus;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Requests\UserIndexRequest;
use App\Imports\UsersImport;
use App\Models\Department;
use App\Models\TripRecurringGroup;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Zone;
use App\Support\PrivateDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;

class UserController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-users', only: ['index', 'show']),
            new Middleware('permission:export-users', only: ['export']),
            new Middleware('permission:create-users', only: ['create', 'store']),
            new Middleware('permission:import-users', only: ['import']),
            new Middleware('permission:edit-users', only: ['edit', 'update', 'deactivate', 'activate']),
            new Middleware('permission:delete-users', only: ['destroy']),
        ];
    }

    /**
     * Display a listing of users.
     */
    public function index(UserIndexRequest $request): Response
    {
        $validated = $request->validated();

        $query = User::select([
            'id',
            'name',
            'username',
            'employee_id',
            'email',
            'user_type',
            'status',
            'driver_status',
            'department_id',
            'zone_id',
            'blood_group',
            'personal_phone',
            'official_phone',
            'created_at',
            'image',
            'photo',
            'driving_license_no',
            'license_class',
            'license_expiry_date',
            'total_trips_completed',
            'average_rating',
        ])
            ->where('user_type', 'employee')
            ->with(['department:id,name', 'zone:id,name', 'accessZones:id,name', 'roles:id,name']);

        // Apply search
        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('employee_id', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('department', function ($subQ) use ($search) {
                        $subQ->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Apply filters
        if (! empty($validated['filters'])) {
            $filters = $validated['filters'];

            if (! empty($filters['user_type'])) {
                $query->whereIn('user_type', $filters['user_type']);
            }

            if (! empty($filters['status'])) {
                $query->whereIn('status', $filters['status']);
            }

            if (! empty($filters['department_id'])) {
                $query->whereIn('department_id', $filters['department_id']);
            }

            if (! empty($filters['zone_id'])) {
                $query->whereIn('zone_id', $filters['zone_id']);
            }

            if (! empty($filters['blood_group'])) {
                $query->whereIn('blood_group', $filters['blood_group']);
            }

            if (! empty($filters['roles'])) {
                $query->whereHas('roles', function ($q) use ($filters) {
                    $q->whereIn('name', $filters['roles']);
                });
            }
        }

        // Apply sorting
        $sortColumn = $validated['sort'];
        $sortDirection = $validated['direction'];

        // Special handling for department sorting
        if ($sortColumn === 'department') {
            $query->leftJoin('departments', 'users.department_id', '=', 'departments.id')
                ->orderBy('departments.name', $sortDirection)
                ->select('users.*');
        } else {
            $query->orderBy($sortColumn, $sortDirection);
        }

        // Get pagination data
        $users = $query->paginate($validated['per_page'])
            ->withQueryString()
            ->through(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'employee_id' => $user->employee_id,
                'email' => $user->email,
                'user_type' => $user->user_type,
                'status' => $user->status,
                'driver_status' => $user->driver_status,
                'department' => $user->department,
                'zone' => $user->zone,
                'blood_group' => $user->blood_group,
                'phone' => $user->personal_phone ?? $user->official_phone,
                'roles' => $user->roles,
                'is_driver' => in_array($user->user_type, ['driver', 'transport_manager']),
                'created_at' => $user->created_at,
                'image' => $user->image,
                'photo' => $user->photo,
                'driving_license_no' => $user->driving_license_no,
                'license_class' => $user->license_class,
                'license_expiry_date' => $user->license_expiry_date,
                'total_trips_completed' => $user->total_trips_completed ?? 0,
                'average_rating' => $user->average_rating,
            ]);

        // Get filter options
        $departments = Department::active()->get(['id', 'name']);
        $zones = Zone::selectableBy();
        $userTypes = User::distinct()->pluck('user_type')->filter()->values()->toArray();
        $statuses = User::distinct()->pluck('status')->filter()->values()->toArray();
        $bloodGroups = User::distinct()->pluck('blood_group')->filter()->values()->toArray();
        $roles = Role::pluck('name')->toArray();

        // Calculate stats with a single aggregate query
        $userStats = User::selectRaw(
            'COUNT(*) as total, '.
            'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as active, '.
            'SUM(CASE WHEN user_type IN (?, ?) THEN 1 ELSE 0 END) as drivers, '.
            'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as inactive',
            ['active', 'driver', 'transport_manager', 'inactive']
        )->first();

        $stats = [
            'total' => (int) ($userStats->total ?? 0),
            'active' => (int) ($userStats->active ?? 0),
            'drivers' => (int) ($userStats->drivers ?? 0),
            'inactive' => (int) ($userStats->inactive ?? 0),
        ];

        return Inertia::render('users/index', [
            'users' => $users,
            'filterOptions' => [
                'user_types' => $userTypes,
                'statuses' => $statuses,
                'departments' => $departments,
                'zones' => $zones,
                'blood_groups' => $bloodGroups,
                'roles' => $roles,
            ],
            'stats' => $stats,
            'queryParams' => $request->only(['search', 'sort', 'direction', 'filters', 'per_page']),
        ]);
    }

    /**
     * Show the form for creating a new user.
     */
    public function create(): Response
    {
        $departments = Department::active()->get(['id', 'name']);
        $zones = Zone::selectableBy();
        $vendors = Vendor::active()->get(['id', 'name']);
        $roles = Role::all(['id', 'name']);

        $userTypes = [
            ['value' => 'employee', 'label' => 'Employee'],
            ['value' => 'driver', 'label' => 'Driver'],
        ];

        $licenseClasses = [
            ['value' => 'A', 'label' => 'Class A - Motorcycle'],
            ['value' => 'B', 'label' => 'Class B - Car/Light Vehicle'],
            ['value' => 'C', 'label' => 'Class C - Heavy Vehicle'],
            ['value' => 'D', 'label' => 'Class D - Professional'],
        ];

        $bloodGroups = [
            ['value' => 'A+', 'label' => 'A+'],
            ['value' => 'A-', 'label' => 'A-'],
            ['value' => 'B+', 'label' => 'B+'],
            ['value' => 'B-', 'label' => 'B-'],
            ['value' => 'AB+', 'label' => 'AB+'],
            ['value' => 'AB-', 'label' => 'AB-'],
            ['value' => 'O+', 'label' => 'O+'],
            ['value' => 'O-', 'label' => 'O-'],
        ];

        return Inertia::render('users/create', [
            'departments' => $departments,
            'zones' => $zones,
            'defaultZoneId' => auth()->user()->zone_id,
            'vendors' => $vendors,
            'roles' => $roles,
            'userTypes' => $userTypes,
            'licenseClasses' => $licenseClasses,
            'bloodGroups' => $bloodGroups,
        ]);
    }

    /**
     * Store a newly created user in storage.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        // All-or-nothing: the record and its roles/zones/related rows
        return DB::transaction(function () use ($request) {
            $validated = $request->validated();
            $zoneAccess = $validated['zone_access'] ?? null;
            $zoneIds = $validated['zone_ids'] ?? [];
            unset($validated['zone_access'], $validated['zone_ids']);

            // Handle file uploads
            if ($request->hasFile('image')) {
                $validated['image'] = $request->file('image')->store('users/images', 'public');
            }

            if ($request->hasFile('photo')) {
                $validated['photo'] = $request->file('photo')->store('users/photos', 'public');
            }

            // NID/licence scans are private (see DocumentController)
            foreach (['driving_license_file', 'nid_file'] as $field) {
                if ($request->hasFile($field)) {
                    $validated[$field] = PrivateDocument::replace(null, $request->file($field), 'driver_documents');
                }
            }

            // Hash password
            $validated['password'] = Hash::make($validated['password']);

            // Accounts provisioned by an admin are trusted without an email-click loop.
            $validated['email_verified_at'] = now();

            $user = User::create($validated);

            // Choosing roles needs manage-user-roles; otherwise the new user gets
            // the default role for their user type (never admin — see below).
            if ($request->user()->can('manage-user-roles') && ! empty($validated['roles']) && is_array($validated['roles'])) {
                // Convert role IDs to role names/objects and assign
                $roles = $this->assignableRoles($validated['roles']);
                $user->assignRole($roles);
            } else {
                // This shouldn't happen due to validation, but fallback to automatic role assignment
                $this->assignRoleByUserType($user, $validated['user_type']);
            }

            $this->logRoleChange($user, []);

            if ($request->has('zone_access')) {
                Zone::applyAccess($user, $zoneAccess ?? 'specific', $zoneIds);
            }

            return redirect()->route('users.index')
                ->with('success', 'User created successfully.');
        });
    }

    /**
     * Display the specified user.
     */
    public function show(User $user): Response
    {
        $user->load(['department', 'roles', 'permissions']);

        // Calculate additional stats for drivers
        if ($user->isDriver()) {
            $user->load(['driverTrips' => function ($query) {
                $query->select('id', 'driver_id', 'status', 'actual_distance', 'driver_rating')
                    ->latest()
                    ->limit(10);
            }]);

            $user->performance_stats = [
                'completion_rate' => $user->calculateCompletionRate(),
                'recent_trips' => $user->driverTrips->count(),
                'license_status' => $user->license_status,
            ];
        }

        return Inertia::render('users/show', [
            'user' => $user,
        ]);
    }

    /**
     * Show the form for editing the specified user.
     */
    public function edit(User $user): Response
    {
        $departments = Department::active()->get(['id', 'name']);
        $currentZoneIds = $user->accessZones()->pluck('zones.id')->all();
        if (empty($currentZoneIds) && $user->zone_id) {
            $currentZoneIds = [$user->zone_id];
        }
        $zones = Zone::selectableBy(auth()->user(), $currentZoneIds);
        $vendors = Vendor::active()->get(['id', 'name']);
        $roles = Role::all(['id', 'name']);
        $userRoles = $user->roles->pluck('id')->map(function ($id) {
            return (string) $id;
        })->toArray();

        $userTypes = [
            ['value' => 'employee', 'label' => 'Employee'],
            ['value' => 'driver', 'label' => 'Driver'],
        ];

        $licenseClasses = [
            ['value' => 'A', 'label' => 'Class A - Motorcycle'],
            ['value' => 'B', 'label' => 'Class B - Car/Light Vehicle'],
            ['value' => 'C', 'label' => 'Class C - Heavy Vehicle'],
            ['value' => 'D', 'label' => 'Class D - Professional'],
        ];

        $bloodGroups = [
            ['value' => 'A+', 'label' => 'A+'],
            ['value' => 'A-', 'label' => 'A-'],
            ['value' => 'B+', 'label' => 'B+'],
            ['value' => 'B-', 'label' => 'B-'],
            ['value' => 'AB+', 'label' => 'AB+'],
            ['value' => 'AB-', 'label' => 'AB-'],
            ['value' => 'O+', 'label' => 'O+'],
            ['value' => 'O-', 'label' => 'O-'],
        ];

        $statusOptions = [
            ['value' => 'active', 'label' => 'Active'],
            ['value' => 'inactive', 'label' => 'Inactive'],
            ['value' => 'suspended', 'label' => 'Suspended'],
        ];

        $driverStatusOptions = [
            ['value' => 'available', 'label' => 'Available'],
            ['value' => 'on_trip', 'label' => 'On Trip'],
            ['value' => 'on_leave', 'label' => 'On Leave'],
            ['value' => 'inactive', 'label' => 'Inactive'],
            ['value' => 'suspended', 'label' => 'Suspended'],
        ];

        return Inertia::render('users/edit', [
            'user' => $user,
            'departments' => $departments,
            'zones' => $zones,
            'currentZoneIds' => $currentZoneIds,
            'hasAllZoneAccess' => $user->can('view-all-zones'),
            'vendors' => $vendors,
            'roles' => $roles,
            'userRoles' => $userRoles,
            'userTypes' => $userTypes,
            'licenseClasses' => $licenseClasses,
            'bloodGroups' => $bloodGroups,
            'statusOptions' => $statusOptions,
            'driverStatusOptions' => $driverStatusOptions,
        ]);
    }

    /**
     * Update the specified user in storage.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        // All-or-nothing: the record and its roles/zones/related rows
        return DB::transaction(function () use ($request, $user) {
            $validated = $request->validated();
            $zoneAccess = $validated['zone_access'] ?? null;
            $zoneIds = $validated['zone_ids'] ?? [];
            unset($validated['zone_access'], $validated['zone_ids']);

            // Handle file uploads
            if ($request->hasFile('image')) {
                if ($user->image) {
                    Storage::disk('public')->delete($user->image);
                }
                $validated['image'] = $request->file('image')->store('users/images', 'public');
            }

            if ($request->hasFile('photo')) {
                if ($user->photo) {
                    Storage::disk('public')->delete($user->photo);
                }
                $validated['photo'] = $request->file('photo')->store('users/photos', 'public');
            }

            foreach (['driving_license_file', 'nid_file'] as $field) {
                if ($request->hasFile($field)) {
                    $validated[$field] = PrivateDocument::replace($user->{$field}, $request->file($field), 'driver_documents');
                }
            }

            // Hash password if provided
            if (! empty($validated['password'])) {
                $validated['password'] = Hash::make($validated['password']);
            } else {
                unset($validated['password']);
            }

            $oldRoleNames = $user->roles->pluck('name')->all();

            $user->update($validated);

            // A plain user editing their own profile (see UpdateUserRequest's
            // self-edit branch) never has 'roles'/'user_type' in $validated at
            // all — leave roles untouched rather than falling into the "no
            // roles submitted" branch below and wiping them.
            // Roles only change for editors allowed to manage them — anyone else
            // (including edit-users without manage-user-roles) leaves them as-is.
            if ($request->isPrivilegedEditor() && $request->user()->can('manage-user-roles')) {
                // Handle role assignments - roles are now required
                if (isset($validated['roles']) && is_array($validated['roles']) && ! empty($validated['roles'])) {
                    // Convert role IDs to role objects and assign
                    $roles = $this->assignableRoles($validated['roles'], $user);
                    $user->syncRoles($roles);
                } else {
                    // This shouldn't happen due to validation, but fallback to automatic role assignment
                    $user->syncRoles([]); // Remove all roles
                    $this->assignRoleByUserType($user, $validated['user_type']);
                }

                $this->logRoleChange($user, $oldRoleNames);
            }

            if ($request->has('zone_access')) {
                Zone::applyAccess($user, $zoneAccess ?? 'specific', $zoneIds);
            }

            return redirect()->route('users.index')
                ->with('success', 'User updated successfully.');
        });
    }

    /**
     * Remove the specified user from storage.
     */
    public function destroy(User $user): RedirectResponse
    {
        // Check if user has any active trips
        if ($user->isDriver() && $user->driverTrips()->whereIn('status', TripStatus::valuesOf(TripStatus::Pending, TripStatus::Approved, TripStatus::InProgress))->exists()) {
            return redirect()->back()
                ->with('error', 'Cannot delete user with active trips. Please complete or reassign trips first.');
        }

        // trips.requested_by and trip_recurring_groups.created_by now restrict
        // deletion (a hard delete previously cascaded and silently wiped that
        // trip history). Surface a clear message instead of a raw DB error.
        if ($user->requestedTrips()->exists()) {
            return redirect()->back()
                ->with('error', 'Cannot delete user: they have requested trips on record. Deactivate the account instead.');
        }

        if (TripRecurringGroup::where('created_by', $user->id)->exists()) {
            return redirect()->back()
                ->with('error', 'Cannot delete user: they created recurring trip groups on record. Deactivate the account instead.');
        }

        // Delete associated files
        if ($user->image) {
            Storage::disk('public')->delete($user->image);
        }
        if ($user->photo) {
            Storage::disk('public')->delete($user->photo);
        }
        PrivateDocument::delete($user->driving_license_file);
        PrivateDocument::delete($user->nid_file);

        $user->delete();

        return redirect()->route('users.index')
            ->with('success', 'User deleted successfully.');
    }

    /**
     * Deactivate a user via the quick-action button on the users list.
     * Whether this also drops their group memberships is an explicit choice
     * from the confirm dialog's checkbox (see UserObserver), not automatic —
     * unlike a status change made through the full edit form.
     */
    public function deactivate(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'remove_from_groups' => 'boolean',
        ]);

        $user->groupMembershipChoice = [
            'remove' => $validated['remove_from_groups'] ?? true,
        ];

        $user->update(['status' => 'inactive']);

        return redirect()->back()->with('success', "{$user->name} has been deactivated.");
    }

    /**
     * Reactivate a user via the quick-action button on the users list.
     * Whether their previous group memberships come back is an explicit
     * choice from the confirm dialog's checkbox (see UserObserver) — a
     * no-op if they had no groups removed to begin with.
     */
    public function activate(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'restore_previous_groups' => 'boolean',
        ]);

        $user->groupMembershipChoice = [
            'restore_previous' => $validated['restore_previous_groups'] ?? true,
        ];

        $user->update(['status' => 'active']);

        return redirect()->back()->with('success', "{$user->name} has been activated.");
    }

    /**
     * Resolve role IDs to Role models, refusing to let anyone below
     * super-admin grant (or accidentally strip) the super-admin role.
     *
     * @param  array<int, int|string>  $roleIds
     */
    private function assignableRoles(array $roleIds, ?User $targetUser = null): Collection
    {
        $roles = Role::whereIn('id', $roleIds)->get();

        if (auth()->user()?->hasRole('super-admin')) {
            return $roles;
        }

        $roles = $roles->reject(fn ($role) => $role->name === 'super-admin')->values();

        // A non-super-admin editing another super-admin shouldn't silently strip that role.
        if ($targetUser?->hasRole('super-admin')) {
            $superAdminRole = Role::where('name', 'super-admin')->first();
            if ($superAdminRole) {
                $roles->push($superAdminRole);
            }
        }

        return $roles;
    }

    /**
     * Log a role-change activity entry if the user's roles actually changed.
     */
    private function logRoleChange(User $user, array $oldRoleNames): void
    {
        $newRoleNames = $user->roles()->pluck('name')->all();

        sort($oldRoleNames);
        sort($newRoleNames);

        if ($oldRoleNames === $newRoleNames) {
            return;
        }

        activity('users')
            ->performedOn($user)
            ->withProperties(['old' => ['roles' => $oldRoleNames], 'new' => ['roles' => $newRoleNames]])
            ->event('role_assigned')
            ->log("Roles updated for {$user->name}");
    }

    /**
     * Assign role based on user type.
     */
    private function assignRoleByUserType(User $user, string $userType): void
    {
        $roleMapping = [
            'employee' => 'employee',
            'driver' => 'driver',
            'transport_manager' => 'transport-manager',
            'admin' => 'admin',
        ];

        // Handing out a management role (admin, transport-manager) is itself
        // role management; without it the user starts as a plain employee
        if (! in_array($userType, ['employee', 'driver'], true) && ! auth()->user()?->can('manage-user-roles')) {
            $userType = 'employee';
        }

        if (isset($roleMapping[$userType])) {
            try {
                $role = Role::findByName($roleMapping[$userType]);
                $user->assignRole($role);
            } catch (\Exception $e) {
                // Role doesn't exist, continue without assigning
            }
        }
    }

    /**
     * Export the (filtered) employee list as CSV.
     */
    public function export(UserIndexRequest $request)
    {
        $validated = $request->validated();

        $query = User::with(['department:id,name'])
            ->where('user_type', 'employee');

        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('employee_id', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (! empty($validated['filters'])) {
            $filters = $validated['filters'];

            if (! empty($filters['user_type'])) {
                $query->whereIn('user_type', $filters['user_type']);
            }

            if (! empty($filters['status'])) {
                $query->whereIn('status', $filters['status']);
            }

            if (! empty($filters['department_id'])) {
                $query->whereIn('department_id', $filters['department_id']);
            }

            if (! empty($filters['zone_id'])) {
                $query->whereIn('zone_id', $filters['zone_id']);
            }

            if (! empty($filters['blood_group'])) {
                $query->whereIn('blood_group', $filters['blood_group']);
            }

            if (! empty($filters['roles'])) {
                $query->whereHas('roles', function ($q) use ($filters) {
                    $q->whereIn('name', $filters['roles']);
                });
            }
        }

        if ($validated['sort'] === 'department') {
            $query->leftJoin('departments', 'users.department_id', '=', 'departments.id')
                ->orderBy('departments.name', $validated['direction'])
                ->select('users.*');
        } else {
            $query->orderBy($validated['sort'], $validated['direction']);
        }

        $users = $query->get();
        $timestamp = now()->format('Y-m-d_H-i-s');

        $output = fopen('php://temp', 'r+');
        fputcsv($output, ['ID', 'Name', 'Username', 'Employee ID', 'Email', 'User Type', 'Status', 'Department', 'Phone', 'Created At']);

        foreach ($users as $user) {
            fputcsv($output, [
                $user->id,
                $user->name,
                $user->username,
                $user->employee_id,
                $user->email,
                $user->user_type,
                $user->status,
                $user->department->name ?? '',
                $user->personal_phone ?? $user->official_phone,
                $user->created_at->format('Y-m-d H:i:s'),
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', "attachment; filename=\"users_export_{$timestamp}.csv\"");
    }

    /**
     * Bulk-create employee accounts from an uploaded CSV/Excel file (see
     * UsersImport for the expected columns).
     */
    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:5120'],
        ]);

        $import = new UsersImport;
        Excel::import($import, $request->file('file'));

        if ($import->failures()->isNotEmpty()) {
            $errors = $import->failures()->take(5)->map(
                fn ($failure) => "row {$failure->row()}: ".implode(', ', $failure->errors())
            )->implode('; ');

            return back()->with(
                'warning',
                "Imported {$import->imported} user(s). {$import->failures()->count()} row(s) skipped ({$errors})."
            );
        }

        return back()->with('success', "Imported {$import->imported} user(s) successfully.");
    }
}
