<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Requests\UserIndexRequest;
use App\Models\Department;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-users', only: ['index', 'show', 'export']),
            new Middleware('permission:create-users', only: ['create', 'store', 'import']),
            new Middleware('permission:edit-users', only: ['edit', 'update']),
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
            ->with(['department:id,name', 'roles:id,name']);

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
        $userTypes = User::distinct()->pluck('user_type')->filter()->values()->toArray();
        $statuses = User::distinct()->pluck('status')->filter()->values()->toArray();
        $bloodGroups = User::distinct()->pluck('blood_group')->filter()->values()->toArray();
        $roles = \Spatie\Permission\Models\Role::pluck('name')->toArray();

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
        $validated = $request->validated();

        // Handle file uploads
        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('users/images', 'public');
        }

        if ($request->hasFile('photo')) {
            $validated['photo'] = $request->file('photo')->store('users/photos', 'public');
        }

        // Hash password
        $validated['password'] = Hash::make($validated['password']);

        // Accounts provisioned by an admin are trusted without an email-click loop.
        $validated['email_verified_at'] = now();

        $user = User::create($validated);

        // Handle role assignments - roles are now required
        if (isset($validated['roles']) && is_array($validated['roles']) && ! empty($validated['roles'])) {
            // Convert role IDs to role names/objects and assign
            $roles = $this->assignableRoles($validated['roles']);
            $user->assignRole($roles);
        } else {
            // This shouldn't happen due to validation, but fallback to automatic role assignment
            $this->assignRoleByUserType($user, $validated['user_type']);
        }

        $this->logRoleChange($user, []);

        return redirect()->route('users.index')
            ->with('success', 'User created successfully.');
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
        $validated = $request->validated();

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
        if ($request->isPrivilegedEditor()) {
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

        return redirect()->route('users.index')
            ->with('success', 'User updated successfully.');
    }

    /**
     * Remove the specified user from storage.
     */
    public function destroy(User $user): RedirectResponse
    {
        // Check if user has any active trips
        if ($user->isDriver() && $user->driverTrips()->whereIn('status', ['pending', 'approved', 'in_progress'])->exists()) {
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

        if (\App\Models\TripRecurringGroup::where('created_by', $user->id)->exists()) {
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

        $user->delete();

        return redirect()->route('users.index')
            ->with('success', 'User deleted successfully.');
    }

    /**
     * Resolve role IDs to Role models, refusing to let anyone below
     * super-admin grant (or accidentally strip) the super-admin role.
     *
     * @param  array<int, int|string>  $roleIds
     */
    private function assignableRoles(array $roleIds, ?User $targetUser = null): \Illuminate\Support\Collection
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
            'admin' => 'admin',
        ];

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
     * Export users to Excel.
     */
    public function export(Request $request)
    {
        // Implementation for Excel export
        // This would use Laravel Excel package
        return response()->json(['message' => 'Export functionality coming soon']);
    }

    /**
     * Import users from Excel.
     */
    public function import(Request $request)
    {
        // Implementation for Excel import
        // This would use Laravel Excel package
        return response()->json(['message' => 'Import functionality coming soon']);
    }
}
