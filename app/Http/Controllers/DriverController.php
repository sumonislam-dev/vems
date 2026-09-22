<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Department;
use App\Models\Vendor;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Requests\UserIndexRequest;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

class DriverController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-drivers', only: ['index', 'show', 'getAvailableDrivers']),
            new Middleware('permission:create-drivers', only: ['create', 'store']),
            new Middleware('permission:edit-drivers', only: ['edit', 'update', 'updateDriverStatus']),
            new Middleware('permission:delete-drivers', only: ['destroy']),
        ];
    }

    /**
     * Display a listing of drivers only.
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
                'area',
                'personal_phone',
                'official_phone',
                'created_at',
                'driving_license_no',
                'license_class',
                'license_expiry_date',
                'license_issue_date',
                'total_trips_completed',
                'average_rating',
            ])
            ->with(['department:id,name', 'roles:id,name'])
            ->whereIn('user_type', ['driver', 'transport_manager']);

        // Apply search
        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%")
                  ->orWhere('employee_id', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('driving_license_no', 'like', "%{$search}%")
                  ->orWhereHas('department', function ($subQ) use ($search) {
                      $subQ->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // Apply filters
        if (!empty($validated['filters'])) {
            $filters = $validated['filters'];

            if (!empty($filters['user_type'])) {
                $query->whereIn('user_type', $filters['user_type']);
            }

            if (!empty($filters['status'])) {
                $query->whereIn('status', $filters['status']);
            }

            if (!empty($filters['driver_status'])) {
                $query->whereIn('driver_status', $filters['driver_status']);
            }

            if (!empty($filters['department_id'])) {
                $query->whereIn('department_id', $filters['department_id']);
            }

            if (!empty($filters['blood_group'])) {
                $query->whereIn('blood_group', $filters['blood_group']);
            }

            if (!empty($filters['roles'])) {
                $query->whereHas('roles', function ($q) use ($filters) {
                    $q->whereIn('name', $filters['roles']);
                });
            }
        }

        // Apply sorting
        $sortColumn = $validated['sort'];
        $sortDirection = $validated['direction'];

        if ($sortColumn === 'department') {
            $query->leftJoin('departments', 'users.department_id', '=', 'departments.id')
                  ->orderBy('departments.name', $sortDirection)
                  ->select('users.*');
        } else {
            $query->orderBy($sortColumn, $sortDirection);
        }

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
                          'area' => $user->area,
                          'phone' => $user->personal_phone ?? $user->official_phone,
                          'roles' => $user->roles,
                          'is_driver' => true,
                          'created_at' => $user->created_at,
                          'driving_license_no' => $user->driving_license_no,
                          'license_class' => $user->license_class,
                          'license_expiry_date' => $user->license_expiry_date,
                          'license_issue_date' => $user->license_issue_date,
                          'total_trips_completed' => $user->total_trips_completed ?? 0,
                          'average_rating' => $user->average_rating,
                      ]);

        $departments = Department::active()->get(['id', 'name']);
        $userTypes = ['driver', 'transport_manager'];
        $statuses = User::distinct()->pluck('status')->filter()->values()->toArray();
        $driverStatuses = User::distinct()->whereNotNull('driver_status')->pluck('driver_status')->filter()->values()->toArray();
        $bloodGroups = User::distinct()->pluck('blood_group')->filter()->values()->toArray();
        $roles = Role::pluck('name')->toArray();

        $driverStats = User::whereIn('user_type', ['driver', 'transport_manager'])
            ->selectRaw(
                'COUNT(*) as total, ' .
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as active, ' .
                'SUM(CASE WHEN driver_status = ? THEN 1 ELSE 0 END) as available, ' .
                'SUM(CASE WHEN driver_status = ? THEN 1 ELSE 0 END) as on_trip',
                ['active', 'available', 'on_trip']
            )
            ->first();

        $stats = [
            'total' => (int) ($driverStats->total ?? 0),
            'active' => (int) ($driverStats->active ?? 0),
            'available' => (int) ($driverStats->available ?? 0),
            'on_trip' => (int) ($driverStats->on_trip ?? 0),
        ];

        return Inertia::render('drivers/index', [
            'users' => $users,
            'filterOptions' => [
                'user_types' => $userTypes,
                'statuses' => $statuses,
                'driver_statuses' => $driverStatuses,
                'departments' => $departments,
                'blood_groups' => $bloodGroups,
                'roles' => $roles,
            ],
            'stats' => $stats,
            'queryParams' => $request->only(['search', 'sort', 'direction', 'filters', 'per_page']),
        ]);
    }

    /**
     * Show the form for creating a new driver.
     */
    public function create(): Response
    {
        $departments = Department::active()->get(['id', 'name']);
        $vendors = Vendor::active()->get(['id', 'name']);
        $roles = Role::all(['id', 'name']);

        // Get default department (first active department)
        $defaultDepartment = $departments->first();

        // Get Driver role as default
        $driverRole = Role::where('name', 'Driver')->first();

        $userTypes = [
            ['value' => 'driver', 'label' => 'Driver'],
            ['value' => 'transport_manager', 'label' => 'Transport Manager'],
        ];

        $licenseClasses = [
            ['value' => 'A', 'label' => 'Class A - Motorcycle'],
            ['value' => 'B', 'label' => 'Class B - Car/Light Vehicle'],
            ['value' => 'C', 'label' => 'Class C - Medium Vehicle'],
            ['value' => 'D', 'label' => 'Class D - Heavy Vehicle/Bus'],
            ['value' => 'E', 'label' => 'Class E - Professional'],
        ];

        $bloodGroups = [
            ['value' => 'A+', 'label' => 'A+'],
            ['value' => 'A-', 'label' => 'A-'],
            ['value' => 'B+', 'label' => 'B+'],
            ['value' => 'B-', 'label' => 'B-'],
            ['value' => 'O+', 'label' => 'O+'],
            ['value' => 'O-', 'label' => 'O-'],
            ['value' => 'AB+', 'label' => 'AB+'],
            ['value' => 'AB-', 'label' => 'AB-'],
        ];

        return Inertia::render('drivers/create', [
            'departments' => $departments,
            'vendors' => $vendors,
            'roles' => $roles,
            'userTypes' => $userTypes,
            'licenseClasses' => $licenseClasses,
            'bloodGroups' => $bloodGroups,
            'defaults' => [
                'user_type' => 'driver',
                'department_id' => $defaultDepartment?->id,
                'role_id' => $driverRole?->id,
                'driver_status' => 'available',
            ],
        ]);
    }

    /**
     * Store a newly created driver.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // Driver module is fixed to driver user type
        $validated['user_type'] = 'driver';

        // Handle file uploads
        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('users/images', 'public');
        }

        if ($request->hasFile('photo')) {
            $validated['photo'] = $request->file('photo')->store('users/photos', 'public');
        }

        // Auto-generate password if not provided
        $generatedPassword = null;
        if (empty($validated['password'])) {
            // Generate strong password: 12 characters with letters, numbers, and symbols
            $generatedPassword = \Illuminate\Support\Str::random(12);
            $validated['password'] = Hash::make($generatedPassword);
        } else {
            $validated['password'] = Hash::make($validated['password']);
        }

        // Set default driver status
        if (empty($validated['driver_status'])) {
            $validated['driver_status'] = 'available';
        }

        // Set default department if not provided
        if (empty($validated['department_id'])) {
            $defaultDepartment = Department::active()->first();
            $validated['department_id'] = $defaultDepartment?->id;
        }

        // Accounts provisioned by an admin are trusted without an email-click loop.
        $validated['email_verified_at'] = now();

        $user = User::create($validated);

        // Driver module is fixed to Driver role
        $driverRole = Role::whereRaw('LOWER(name) = ?', ['driver'])->first();
        if ($driverRole) {
            $user->syncRoles([$driverRole->id]);
        }

        return redirect()->route('drivers.index')->with('success', $generatedPassword
            ? "Driver created successfully! Generated Password: {$generatedPassword} (Save this, it won't be shown again)"
            : 'Driver created successfully.');
    }

    /**
     * Display the specified driver.
     */
    public function show(User $driver): Response
    {
        if (!in_array($driver->user_type, ['driver', 'transport_manager'])) {
            abort(404, 'Driver not found');
        }

        $driver->load(['department', 'roles', 'driverTrips' => function ($query) {
            $query->latest()->limit(10);
        }]);

        $driverStats = [
            'total_trips' => $driver->driverTrips->count(),
            'recent_trips' => $driver->driverTrips->count(),
            'completed_trips' => $driver->driverTrips()->where('status', 'completed')->count(),
            'in_progress_trips' => $driver->driverTrips()->where('status', 'in_progress')->count(),
            'total_distance' => $driver->total_distance_covered,
            'average_rating' => $driver->average_rating,
        ];

        return Inertia::render('drivers/show', [
            'user' => $driver,
            'driverStats' => $driverStats,
        ]);
    }

    /**
     * Show the form for editing the specified driver.
     */
    public function edit(User $driver): Response
    {
        if (!in_array($driver->user_type, ['driver', 'transport_manager'])) {
            abort(404, 'Driver not found');
        }

        $departments = Department::active()->get(['id', 'name']);
        $vendors = Vendor::active()->get(['id', 'name']);
        $roles = Role::all(['id', 'name']);
        $userRoles = $driver->roles->pluck('id')->map(fn($id) => (string) $id)->toArray();

        $userTypes = [
            ['value' => 'driver', 'label' => 'Driver'],
            ['value' => 'transport_manager', 'label' => 'Transport Manager'],
        ];

        $licenseClasses = [
            ['value' => 'A', 'label' => 'Class A - Motorcycle'],
            ['value' => 'B', 'label' => 'Class B - Car/Light Vehicle'],
            ['value' => 'C', 'label' => 'Class C - Medium Vehicle'],
            ['value' => 'D', 'label' => 'Class D - Heavy Vehicle/Bus'],
            ['value' => 'E', 'label' => 'Class E - Professional'],
        ];

        $bloodGroups = [
            ['value' => 'A+', 'label' => 'A+'],
            ['value' => 'A-', 'label' => 'A-'],
            ['value' => 'B+', 'label' => 'B+'],
            ['value' => 'B-', 'label' => 'B-'],
            ['value' => 'O+', 'label' => 'O+'],
            ['value' => 'O-', 'label' => 'O-'],
            ['value' => 'AB+', 'label' => 'AB+'],
            ['value' => 'AB-', 'label' => 'AB-'],
        ];

        return Inertia::render('drivers/edit', [
            'user' => $driver,
            'departments' => $departments,
            'vendors' => $vendors,
            'roles' => $roles,
            'userRoles' => $userRoles,
            'userTypes' => $userTypes,
            'licenseClasses' => $licenseClasses,
            'bloodGroups' => $bloodGroups,
        ]);
    }

    /**
     * Update the specified driver.
     */
    public function update(UpdateUserRequest $request, User $driver): RedirectResponse
    {
        if (!in_array($driver->user_type, ['driver', 'transport_manager'])) {
            abort(404, 'Driver not found');
        }

        $validated = $request->validated();

        // Driver module is fixed to driver user type
        $validated['user_type'] = 'driver';

        // Handle file uploads
        if ($request->hasFile('image')) {
            if ($driver->image) {
                Storage::disk('public')->delete($driver->image);
            }
            $validated['image'] = $request->file('image')->store('users/images', 'public');
        }

        if ($request->hasFile('photo')) {
            if ($driver->photo) {
                Storage::disk('public')->delete($driver->photo);
            }
            $validated['photo'] = $request->file('photo')->store('users/photos', 'public');
        }

        // Hash password if provided
        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $driver->update($validated);

        // Driver module is fixed to Driver role
        $driverRole = Role::whereRaw('LOWER(name) = ?', ['driver'])->first();
        if ($driverRole) {
            $driver->syncRoles([$driverRole->id]);
        }

        return redirect()->route('drivers.index')->with('success', 'Driver updated successfully.');
    }

    /**
     * Remove the specified driver.
     */
    public function destroy(User $driver): RedirectResponse
    {
        if (!in_array($driver->user_type, ['driver', 'transport_manager'])) {
            abort(404, 'Driver not found');
        }

        // Check if driver has active trips
        if ($driver->driverTrips()->whereIn('status', ['pending', 'approved', 'assigned', 'in_progress'])->exists()) {
            return redirect()->back()->withErrors(['error' => 'Cannot delete driver with active trips.']);
        }

        // trips.requested_by and trip_recurring_groups.created_by now restrict
        // deletion (a hard delete previously cascaded and silently wiped that
        // trip history). Surface a clear message instead of a raw DB error.
        if ($driver->requestedTrips()->exists()) {
            return redirect()->back()->withErrors(['error' => 'Cannot delete driver: they have requested trips on record. Deactivate the account instead.']);
        }

        if (\App\Models\TripRecurringGroup::where('created_by', $driver->id)->exists()) {
            return redirect()->back()->withErrors(['error' => 'Cannot delete driver: they created recurring trip groups on record. Deactivate the account instead.']);
        }

        // Delete images
        if ($driver->image) {
            Storage::disk('public')->delete($driver->image);
        }
        if ($driver->photo) {
            Storage::disk('public')->delete($driver->photo);
        }

        $driver->delete();

        return redirect()->route('drivers.index')->with('success', 'Driver deleted successfully.');
    }

    /**
     * Get available drivers for AJAX requests.
     */
    public function getAvailableDrivers(Request $request)
    {
        $drivers = User::availableDrivers()
            ->with('department:id,name')
            ->select('id', 'name', 'username', 'employee_id', 'department_id', 'driving_license_no', 'license_class')
            ->get();

        return response()->json($drivers);
    }

    /**
     * Update driver status.
     */
    public function updateDriverStatus(Request $request, User $user): RedirectResponse
    {
        $request->validate([
            'driver_status' => 'required|in:available,on_trip,on_leave,inactive,suspended',
        ]);

        if (!$user->isDriver()) {
            return redirect()->back()
                           ->with('error', 'User is not a driver.');
        }

        $user->update(['driver_status' => $request->driver_status]);

        return redirect()->back()
                        ->with('success', 'Driver status updated successfully.');
    }
}
