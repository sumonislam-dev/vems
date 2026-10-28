<?php

namespace App\Http\Controllers;

use App\Enums\TripStatus;
use App\Exports\DriversExport;
use App\Exports\DriversImportTemplate;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Requests\UserIndexRequest;
use App\Imports\DriversImport;
use App\Models\Department;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Vendor;
use App\Models\Zone;
use App\Services\UserSaver;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;
use Throwable;

class DriverController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-drivers', only: ['index', 'show']),
            new Middleware('permission:export-drivers', only: ['export']),
            new Middleware('permission:import-drivers', only: ['import', 'importTemplate']),
            new Middleware('permission:create-drivers', only: ['create', 'store']),
            new Middleware('permission:edit-drivers', only: ['edit', 'update', 'bulkUpdateStatus']),
            new Middleware('permission:delete-drivers', only: ['destroy']),
            new Middleware('permission:assign-vehicles', only: ['assignableVehicles']),
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
            'zone_id',
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
            'vendor_id',
        ])
            ->with([
                'department:id,name',
                'zone:id,name',
                'accessZones:id,name',
                'roles:id,name',
                'assignedVehicles:id,driver_id,registration_number,brand,model,vehicle_type',
                'vendor:id,name,status',
            ])
            ->where('user_type', 'driver');

        // Apply search
        if (! empty($validated['search'])) {
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
        if (! empty($validated['filters'])) {
            $filters = $validated['filters'];

            if (! empty($filters['user_type'])) {
                $query->whereIn('user_type', $filters['user_type']);
            }

            if (! empty($filters['status'])) {
                $query->whereIn('status', $filters['status']);
            }

            if (! empty($filters['driver_status'])) {
                $query->whereIn('driver_status', $filters['driver_status']);
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

            if (! empty($filters['vendor_id'])) {
                $query->whereIn('vendor_id', $filters['vendor_id']);
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
                'zone' => $user->zone,
                'blood_group' => $user->blood_group,
                'area' => $user->area,
                'phone' => $user->personal_phone ?? $user->official_phone,
                'roles' => $user->roles,
                'vehicle' => $user->assignedVehicles->first(),
                'vendor' => $user->vendor,
                'is_driver' => true,
                'created_at' => $user->created_at,
                'driving_license_no' => $user->driving_license_no,
                'license_class' => $user->license_class,
                'license_expiry_date' => $user->license_expiry_date,
                'license_status' => $user->license_status,
                'license_issue_date' => $user->license_issue_date,
                'total_trips_completed' => $user->total_trips_completed ?? 0,
                'average_rating' => $user->average_rating,
            ]);

        $departments = Department::active()->get(['id', 'name']);
        $zones = Zone::selectableBy();
        $statuses = User::distinct()->pluck('status')->filter()->values()->toArray();
        $driverStatuses = User::distinct()->whereNotNull('driver_status')->pluck('driver_status')->filter()->values()->toArray();
        $bloodGroups = User::distinct()->pluck('blood_group')->filter()->values()->toArray();
        $roles = Role::pluck('name')->toArray();
        $vendors = Vendor::active()->get(['id', 'name']);

        $driverStats = User::where('user_type', 'driver')
            ->selectRaw(
                'COUNT(*) as total, '.
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as active, '.
                'SUM(CASE WHEN driver_status = ? THEN 1 ELSE 0 END) as available, '.
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
                'statuses' => $statuses,
                'driver_statuses' => $driverStatuses,
                'departments' => $departments,
                'zones' => $zones,
                'blood_groups' => $bloodGroups,
                'roles' => $roles,
                'vendors' => $vendors,
            ],
            'stats' => $stats,
            'queryParams' => $request->only(['search', 'sort', 'direction', 'filters', 'per_page']),
        ]);
    }

    /**
     * The Excel template for import(), with the required columns marked.
     */
    public function importTemplate(Request $request)
    {
        return Excel::download(new DriversImportTemplate($request->user()), 'drivers_import_template.xlsx');
    }

    /**
     * Bulk-create drivers from an uploaded CSV/Excel file (see
     * DriversImport for the columns).
     */
    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:5120'],
        ]);

        // A big file can take longer than the default 30 s PHP limit
        set_time_limit(300);

        $import = new DriversImport($request->user());

        try {
            Excel::import($import, $request->file('file'));
        } catch (Throwable $e) {
            // Rows before the failure are saved (each row commits on its own)
            Log::error('Driver import stopped.', ['error' => $e->getMessage(), 'imported' => $import->imported, 'user_id' => $request->user()->id]);

            return back()->with('error', "Import stopped by an unexpected error after {$import->imported} driver(s) were imported. Check the driver list before importing the file again.");
        }

        if ($import->failures()->isNotEmpty()) {
            $errors = $import->failures()->take(5)->map(
                fn ($failure) => "row {$failure->row()}: ".implode(', ', $failure->errors())
            )->implode('; ');

            return back()->with(
                'warning',
                "Imported {$import->imported} driver(s). {$import->failures()->count()} row(s) skipped ({$errors})."
            );
        }

        return back()->with('success', "Imported {$import->imported} driver(s) successfully.");
    }

    /**
     * Export the (filtered) driver list as CSV, Excel, or PDF.
     */
    public function export(UserIndexRequest $request)
    {
        $validated = $request->validated();

        $query = User::with(['department:id,name', 'vendor:id,name'])
            ->where('user_type', 'driver');

        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('employee_id', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('driving_license_no', 'like', "%{$search}%");
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

            if (! empty($filters['driver_status'])) {
                $query->whereIn('driver_status', $filters['driver_status']);
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

            if (! empty($filters['vendor_id'])) {
                $query->whereIn('vendor_id', $filters['vendor_id']);
            }
        }

        $query->orderBy($validated['sort'], $validated['direction']);

        $format = $validated['format'] ?? 'csv';
        $timestamp = now()->format('Y-m-d_H-i-s');

        if ($format === 'excel') {
            return Excel::download(new DriversExport($query), "drivers_export_{$timestamp}.xlsx");
        }

        if ($format === 'pdf') {
            $drivers = $query->get();

            $pdf = Pdf::loadView('exports.drivers-pdf', [
                'drivers' => $drivers,
                'exportDate' => now()->format('F j, Y \a\t g:i A'),
            ])->setPaper('A4', 'landscape');

            return $pdf->download("drivers_export_{$timestamp}.pdf");
        }

        return $this->exportDriversCsv($query, $timestamp);
    }

    private function exportDriversCsv($query, string $timestamp)
    {
        $drivers = $query->get();

        $output = fopen('php://temp', 'r+');
        fputcsv($output, ['ID', 'Name', 'Username', 'Email', 'Type', 'Status', 'Driver Status', 'Department', 'Vendor', 'License No', 'License Expiry', 'Created At']);

        foreach ($drivers as $driver) {
            fputcsv($output, [
                $driver->id,
                $driver->name,
                $driver->username,
                $driver->email,
                $driver->user_type,
                $driver->status,
                $driver->driver_status,
                $driver->department->name ?? '',
                $driver->vendor->name ?? '',
                $driver->driving_license_no,
                $driver->license_expiry_date?->format('Y-m-d'),
                $driver->created_at->format('Y-m-d H:i:s'),
            ]);
        }

        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        return response($csvContent)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="drivers_export_'.$timestamp.'.csv"');
    }

    /**
     * Show the form for creating a new driver.
     */
    public function create(): Response
    {
        $departments = Department::active()->get(['id', 'name']);
        $zones = Zone::selectableBy();
        $vendors = Vendor::active()->get(['id', 'name']);
        $roles = Role::all(['id', 'name']);

        // Get default department (first active department)
        $defaultDepartment = $departments->first();

        // Get Driver role as default
        $driverRole = Role::where('name', 'Driver')->first();

        $userTypes = [
            ['value' => 'driver', 'label' => 'Driver'],
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
            'zones' => $zones,
            'vendors' => $vendors,
            'roles' => $roles,
            'userTypes' => $userTypes,
            'licenseClasses' => $licenseClasses,
            'bloodGroups' => $bloodGroups,
            'defaults' => [
                'user_type' => 'driver',
                'department_id' => $defaultDepartment?->id,
                'zone_id' => auth()->user()->zone_id,
                'role_id' => $driverRole?->id,
                'driver_status' => 'available',
            ],
        ]);
    }

    /**
     * Store a newly created driver.
     */
    public function store(StoreUserRequest $request, UserSaver $saver): RedirectResponse
    {
        // All-or-nothing: the record and its roles/zones/related rows
        return DB::transaction(function () use ($request, $saver) {
            $validated = $request->validated();

            // Driver module is fixed to driver user type
            $validated['user_type'] = 'driver';

            // Auto-generate password if not provided (shown once below)
            $generatedPassword = null;
            if (empty($validated['password'])) {
                $generatedPassword = Str::random(12);
                $validated['password'] = $generatedPassword;
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

            $user = $saver->create($request, $validated);

            // Driver module is fixed to Driver role
            $driverRole = Role::whereRaw('LOWER(name) = ?', ['driver'])->first();
            if ($driverRole) {
                $user->syncRoles([$driverRole->id]);
            }

            return redirect()->route('drivers.index')->with('success', $generatedPassword
                ? "Driver created successfully! Generated Password: {$generatedPassword} (Save this, it won't be shown again)"
                : 'Driver created successfully.');
        });
    }

    /**
     * Display the specified driver.
     */
    public function show(User $driver): Response
    {
        if ($driver->user_type !== 'driver') {
            abort(404, 'Driver not found');
        }

        $driver->load(['department', 'roles', 'vendor.contactPersons', 'driverTrips' => function ($query) {
            $query->latest()->limit(10)->select('id', 'trip_number', 'scheduled_date', 'trip_type', 'description', 'status', 'driver_id');
        }]);

        $driver->append('license_status');

        $driverStats = [
            'total_trips' => $driver->driverTrips()->count(),
            'completed_trips' => $driver->driverTrips()->where('status', TripStatus::Completed->value)->count(),
            'in_progress_trips' => $driver->driverTrips()->where('status', TripStatus::InProgress->value)->count(),
            'total_distance' => $driver->total_distance_covered,
            'average_rating' => $driver->average_rating,
        ];

        $vehicleAssignments = $driver->vehicleAssignments()
            ->with(['vehicle:id,registration_number,brand,model,vehicle_type', 'assigner:id,name'])
            ->orderByDesc('started_at')
            ->get();

        return Inertia::render('drivers/show', [
            'user' => $driver,
            'driverStats' => $driverStats,
            'recentTrips' => $driver->driverTrips,
            'vehicleAssignments' => $vehicleAssignments,
        ]);
    }

    /**
     * Show the form for editing the specified driver.
     */
    public function edit(User $driver): Response
    {
        if ($driver->user_type !== 'driver') {
            abort(404, 'Driver not found');
        }

        $departments = Department::active()->get(['id', 'name']);
        $currentZoneIds = $driver->accessZones()->pluck('zones.id')->all();
        if (empty($currentZoneIds) && $driver->zone_id) {
            $currentZoneIds = [$driver->zone_id];
        }
        $zones = Zone::selectableBy(auth()->user(), $currentZoneIds);
        $vendors = Vendor::active()->get(['id', 'name']);
        $roles = Role::all(['id', 'name']);
        $userRoles = $driver->roles->pluck('id')->map(fn ($id) => (string) $id)->toArray();

        $userTypes = [
            ['value' => 'driver', 'label' => 'Driver'],
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
            'zones' => $zones,
            'currentZoneIds' => $currentZoneIds,
            'hasAllZoneAccess' => $driver->can('view-all-zones'),
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
    public function update(UpdateUserRequest $request, User $driver, UserSaver $saver): RedirectResponse
    {
        // All-or-nothing: the record and its roles/zones/related rows
        return DB::transaction(function () use ($request, $driver, $saver) {
            if ($driver->user_type !== 'driver') {
                abort(404, 'Driver not found');
            }

            $validated = $request->validated();

            // Driver module is fixed to driver user type
            $validated['user_type'] = 'driver';

            $saver->update($request, $driver, $validated);

            // Driver module is fixed to Driver role
            $driverRole = Role::whereRaw('LOWER(name) = ?', ['driver'])->first();
            if ($driverRole) {
                $driver->syncRoles([$driverRole->id]);
            }

            return redirect()->route('drivers.index')->with('success', 'Driver updated successfully.');
        });
    }

    /**
     * Remove the specified driver.
     */
    public function destroy(User $driver, UserSaver $saver): RedirectResponse
    {
        if ($driver->user_type !== 'driver') {
            abort(404, 'Driver not found');
        }

        $blocked = match ($saver->deletionBlocker($driver)) {
            'active_trips' => 'Cannot delete driver with active trips.',
            'requested_trips' => 'Cannot delete driver: they have requested trips on record. Deactivate the account instead.',
            'recurring_groups' => 'Cannot delete driver: they created recurring trip groups on record. Deactivate the account instead.',
            default => null,
        };

        if ($blocked) {
            return redirect()->back()->withErrors(['error' => $blocked]);
        }

        $saver->delete($driver);

        return redirect()->route('drivers.index')->with('success', 'Driver deleted successfully.');
    }

    /**
     * Active vehicles for the driver-side "Assign Vehicle" picker, each
     * flagged with its current driver (if any) so the frontend can warn
     * before reassigning a vehicle away from another driver.
     */
    public function assignableVehicles(User $driver): JsonResponse
    {
        if ($driver->user_type !== 'driver') {
            abort(404, 'Driver not found');
        }

        $vehicles = Vehicle::where('is_active', true)
            ->with('driver:id,name')
            ->select('id', 'registration_number', 'brand', 'model', 'driver_id')
            ->orderBy('registration_number')
            ->get()
            ->map(fn ($vehicle) => [
                'id' => $vehicle->id,
                'registration_number' => $vehicle->registration_number,
                'brand' => $vehicle->brand,
                'model' => $vehicle->model,
                'current_driver_id' => $vehicle->driver_id,
                'current_driver_name' => $vehicle->driver?->name,
            ]);

        return response()->json($vehicles);
    }

    /**
     * Bulk change driver_status for the selected drivers.
     */
    public function bulkUpdateStatus(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'driver_ids' => 'required|array|min:1|max:100',
            'driver_ids.*' => 'integer|exists:users,id',
            'driver_status' => 'required|in:available,on_trip,on_leave,inactive,suspended',
        ]);

        $updatedCount = User::whereIn('id', $validated['driver_ids'])
            ->where('user_type', 'driver')
            ->update(['driver_status' => $validated['driver_status']]);

        return back()->with('success', "{$updatedCount} driver(s) updated to \"{$validated['driver_status']}\".");
    }
}
