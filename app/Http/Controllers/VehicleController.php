<?php

namespace App\Http\Controllers;

use App\Exports\VehiclesExport;
use App\Http\Requests\VehicleIndexRequest;
use App\Models\User;
use App\Models\Vehicle;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class VehicleController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-vehicles', only: ['index', 'show', 'getExpiringVehicles', 'export']),
            new Middleware('permission:create-vehicles', only: ['create', 'store']),
            new Middleware('permission:edit-vehicles', only: ['edit', 'update', 'bulkUpdateStatus']),
            new Middleware('permission:delete-vehicles', only: ['destroy']),
            new Middleware('permission:assign-vehicles', only: ['assignDriver']),
        ];
    }

    /**
     * Drivers eligible for a vehicle assignment dropdown — mirrors
     * User::canDrive() (active, available, license not expired) at the
     * query level. $includeDriverId keeps a vehicle's already-assigned
     * driver in the list even if they no longer qualify (e.g. their
     * license expired since assignment), so editing doesn't silently
     * drop the current selection.
     */
    private function assignableDrivers(?int $includeDriverId = null)
    {
        return User::where('user_type', 'driver')
            ->where(function ($query) use ($includeDriverId) {
                $query->where(function ($eligible) {
                    $eligible->where('status', 'active')
                        ->where('driver_status', 'available')
                        ->where(function ($license) {
                            $license->whereNull('license_expiry_date')
                                ->orWhere('license_expiry_date', '>', now());
                        });
                });

                if ($includeDriverId) {
                    $query->orWhere('id', $includeDriverId);
                }
            })
            ->select('id', 'name', 'email', 'user_type', 'official_phone')
            ->orderBy('name')
            ->get();
    }

    /**
     * Assign (or unassign, with driver_id null) a driver to a vehicle. Shared
     * by both the vehicle-side "Assign Driver" action and the driver-side
     * "Assign Vehicle" action, since both mutate the same driver_id column —
     * VehicleObserver::updating() records the history either way.
     *
     * A driver already assigned to a different vehicle is left alone unless
     * confirm_reassign is sent, in which case that other vehicle is
     * unassigned in the same transaction.
     */
    public function assignDriver(Request $request, Vehicle $vehicle)
    {
        // The frontend's "— Unassign —" option sends the literal string 'none'
        // (a Radix Select item can't have an empty-string value), matching the
        // same sentinel the vehicle create/edit forms already use for this.
        if ($request->input('driver_id') === 'none') {
            $request->merge(['driver_id' => null]);
        }

        $validated = $request->validate([
            'driver_id' => 'nullable|integer|exists:users,id',
            'confirm_reassign' => 'nullable|boolean',
        ]);

        $driverId = $validated['driver_id'] ?? null;

        if ($driverId && ! $this->assignableDrivers($vehicle->driver_id)->pluck('id')->contains($driverId)) {
            throw ValidationException::withMessages([
                'driver_id' => 'This driver is not eligible for assignment (inactive, unavailable, or license expired).',
            ]);
        }

        DB::transaction(function () use ($vehicle, $driverId, $validated) {
            $vehicle = Vehicle::whereKey($vehicle->id)->lockForUpdate()->firstOrFail();

            if ($driverId) {
                $conflict = Vehicle::where('driver_id', $driverId)
                    ->where('id', '!=', $vehicle->id)
                    ->lockForUpdate()
                    ->first();

                if ($conflict && ! ($validated['confirm_reassign'] ?? false)) {
                    throw ValidationException::withMessages([
                        'confirm_reassign' => "This driver is already assigned to {$conflict->registration_number}. Check \"confirm reassign\" to move them to this vehicle instead.",
                    ]);
                }

                if ($conflict) {
                    $conflict->update(['driver_id' => null]);
                }
            }

            $vehicle->update(['driver_id' => $driverId]);
        });

        return back()->with('success', $driverId ? 'Driver assigned successfully.' : 'Driver unassigned successfully.');
    }

    /**
     * Bulk activate or deactivate the selected vehicles.
     */
    public function bulkUpdateStatus(Request $request)
    {
        $validated = $request->validate([
            'vehicle_ids' => 'required|array|min:1|max:100',
            'vehicle_ids.*' => 'integer|exists:vehicles,id',
            'is_active' => 'required|boolean',
        ]);

        $updatedCount = Vehicle::whereIn('id', $validated['vehicle_ids'])
            ->update(['is_active' => $validated['is_active']]);

        $action = $validated['is_active'] ? 'activated' : 'deactivated';

        return back()->with('success', "{$updatedCount} vehicle(s) {$action}.");
    }

    /**
     * Display a listing of the resource.
     */
    public function index(VehicleIndexRequest $request)
    {
        $validated = $request->validated();

        $query = Vehicle::with(['vendor', 'driver']);

        // Apply search
        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('brand', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%")
                    ->orWhere('color', 'like', "%{$search}%")
                    ->orWhere('registration_number', 'like', "%{$search}%")
                    ->orWhereHas('vendor', function ($vendorQuery) use ($search) {
                        $vendorQuery->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('driver', function ($driverQuery) use ($search) {
                        $driverQuery->where('name', 'like', "%{$search}%")
                                   ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        // Apply filters
        if (!empty($validated['filters'])) {
            $filters = $validated['filters'];

            if (!empty($filters['brand'])) {
                $query->whereIn('brand', $filters['brand']);
            }

            if (!empty($filters['color'])) {
                $query->whereIn('color', $filters['color']);
            }

            if (!empty($filters['vehicle_type'])) {
                $query->whereIn('vehicle_type', $filters['vehicle_type']);
            }

            if (!empty($filters['rental_type'])) {
                $query->whereIn('rental_type', $filters['rental_type']);
            }

            if (!empty($filters['fuel_type'])) {
                $query->whereIn('fuel_type', $filters['fuel_type']);
            }

            if (!empty($filters['vendor_id'])) {
                $query->whereIn('vendor_id', $filters['vendor_id']);
            }

            if (isset($filters['is_active']) && !empty($filters['is_active'])) {
                $query->whereIn('is_active', $filters['is_active']);
            }

            if (!empty($filters['status'])) {
                $query->whereIn('status', $filters['status']);
            }
        }

        // Apply sorting
        $sortColumn = $validated['sort'];
        $sortDirection = $validated['direction'];
        $query->orderBy($sortColumn, $sortDirection);

        // Get pagination data
        $vehicles = $query->paginate($validated['per_page'])
            ->withQueryString(); // Preserve query parameters in pagination links

        // Get filter options for the frontend
        $filterOptions = [
            'brands' => Vehicle::distinct()->pluck('brand')->filter()->sort()->values(),
            'colors' => Vehicle::distinct()->pluck('color')->filter()->sort()->values(),
            'vehicle_types' => [
                ['label' => 'Sedan', 'value' => 'sedan'],
                ['label' => 'SUV', 'value' => 'suv'],
                ['label' => 'Van', 'value' => 'van'],
                ['label' => 'Microbus', 'value' => 'microbus'],
                ['label' => 'Coaster', 'value' => 'coaster'],
                ['label' => 'Bus', 'value' => 'bus'],
                ['label' => 'Pickup', 'value' => 'pickup'],
                ['label' => 'Truck', 'value' => 'truck'],
                ['label' => 'Other', 'value' => 'other'],
            ],
            'rental_types' => [
                ['label' => 'Own', 'value' => 'own'],
                ['label' => 'Pool', 'value' => 'pool'],
                ['label' => 'Rental', 'value' => 'rental'],
                ['label' => 'Adhoc', 'value' => 'adhoc'],
                ['label' => 'Support', 'value' => 'support'],
            ],
            'fuel_types' => [
                ['label' => 'Petrol', 'value' => 'petrol'],
                ['label' => 'Diesel', 'value' => 'diesel'],
                ['label' => 'CNG', 'value' => 'cng'],
                ['label' => 'Electric', 'value' => 'electric'],
                ['label' => 'Hybrid', 'value' => 'hybrid'],
            ],
            'vendors' => \App\Models\Vendor::active()->select('id', 'name')->orderBy('name')->get()->map(fn($v) => [
                'label' => $v->name,
                'value' => (string)$v->id,
            ]),
            'statuses' => [
                ['label' => 'Active', 'value' => true],
                ['label' => 'Inactive', 'value' => false],
            ],
            'conditions' => [
                ['label' => 'Available', 'value' => 'available'],
                ['label' => 'Assigned', 'value' => 'assigned'],
                ['label' => 'In Transit', 'value' => 'in_transit'],
                ['label' => 'Maintenance', 'value' => 'maintenance'],
                ['label' => 'Out of Service', 'value' => 'out_of_service'],
            ],
        ];

        // Get stats with a single aggregate query
        $vehicleStats = Vehicle::selectRaw(
            'COUNT(*) as total, ' .
            'SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active, ' .
            'COUNT(DISTINCT brand) as brands, ' .
            'SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive'
        )->first();

        $stats = [
            'total' => (int) ($vehicleStats->total ?? 0),
            'active' => (int) ($vehicleStats->active ?? 0),
            'brands' => (int) ($vehicleStats->brands ?? 0),
            'inactive' => (int) ($vehicleStats->inactive ?? 0),
        ];

        $canAssignVehicles = auth()->user()->can('assign-vehicles');

        return Inertia::render('vehicles/index', [
            'vehicles' => $vehicles,
            'filterOptions' => $filterOptions,
            'stats' => $stats,
            'queryParams' => $request->only(['search', 'sort', 'direction', 'filters', 'per_page']),
            'assignableDrivers' => $canAssignVehicles ? $this->assignableDrivers() : [],
        ]);
    }

    /**
     * Export the (filtered) vehicle list as CSV, Excel, or PDF.
     */
    public function export(VehicleIndexRequest $request)
    {
        $validated = $request->validated();

        $query = Vehicle::with(['vendor', 'driver']);

        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('brand', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%")
                    ->orWhere('color', 'like', "%{$search}%")
                    ->orWhere('registration_number', 'like', "%{$search}%")
                    ->orWhereHas('vendor', function ($vendorQuery) use ($search) {
                        $vendorQuery->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('driver', function ($driverQuery) use ($search) {
                        $driverQuery->where('name', 'like', "%{$search}%")
                                   ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        if (!empty($validated['filters'])) {
            $filters = $validated['filters'];

            if (!empty($filters['brand'])) {
                $query->whereIn('brand', $filters['brand']);
            }

            if (!empty($filters['color'])) {
                $query->whereIn('color', $filters['color']);
            }

            if (!empty($filters['vehicle_type'])) {
                $query->whereIn('vehicle_type', $filters['vehicle_type']);
            }

            if (!empty($filters['rental_type'])) {
                $query->whereIn('rental_type', $filters['rental_type']);
            }

            if (!empty($filters['fuel_type'])) {
                $query->whereIn('fuel_type', $filters['fuel_type']);
            }

            if (!empty($filters['vendor_id'])) {
                $query->whereIn('vendor_id', $filters['vendor_id']);
            }

            if (isset($filters['is_active']) && !empty($filters['is_active'])) {
                $query->whereIn('is_active', $filters['is_active']);
            }

            if (!empty($filters['status'])) {
                $query->whereIn('status', $filters['status']);
            }
        }

        $query->orderBy($validated['sort'], $validated['direction']);

        $format = $validated['format'] ?? 'csv';
        $timestamp = now()->format('Y-m-d_H-i-s');

        if ($format === 'excel') {
            return Excel::download(new VehiclesExport($query), "vehicles_export_{$timestamp}.xlsx");
        }

        if ($format === 'pdf') {
            $vehicles = $query->get();

            $pdf = Pdf::loadView('exports.vehicles-pdf', [
                'vehicles' => $vehicles,
                'exportDate' => now()->format('F j, Y \a\t g:i A'),
            ])->setPaper('A4', 'landscape');

            return $pdf->download("vehicles_export_{$timestamp}.pdf");
        }

        return $this->exportVehiclesCsv($query, $timestamp);
    }

    private function exportVehiclesCsv($query, string $timestamp)
    {
        $vehicles = $query->get();

        $output = fopen('php://temp', 'r+');
        fputcsv($output, ['ID', 'Brand', 'Model', 'Type', 'Color', 'Registration', 'Vendor', 'Driver', 'Status', 'Created At']);

        foreach ($vehicles as $vehicle) {
            fputcsv($output, [
                $vehicle->id,
                $vehicle->brand,
                $vehicle->model,
                $vehicle->vehicle_type,
                $vehicle->color,
                $vehicle->registration_number,
                $vehicle->vendor->name ?? '',
                $vehicle->driver->name ?? '',
                $vehicle->is_active ? 'Active' : 'Inactive',
                $vehicle->created_at->format('Y-m-d H:i:s'),
            ]);
        }

        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        return response($csvContent)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="vehicles_export_' . $timestamp . '.csv"');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        try {
            $vendors = \App\Models\Vendor::active()
                ->select('id', 'name')
                ->orderBy('name')
                ->get();
        } catch (\Exception $e) {
            // If there's an issue with vendors, provide empty array
            $vendors = collect([]);
        }

        try {
            $drivers = $this->assignableDrivers();
        } catch (\Exception $e) {
            // If there's an issue with users, provide empty array
            $drivers = collect([]);
        }

        return Inertia::render('vehicles/create', [
            'vendors' => $vendors,
            'drivers' => $drivers,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Transform 'none' values to null before validation
        $data = $request->all();
        $data['fuel_type'] = ($data['fuel_type'] ?? null) === 'none' ? null : ($data['fuel_type'] ?? null);
        $data['insurance_type'] = ($data['insurance_type'] ?? null) === 'none' ? null : ($data['insurance_type'] ?? null);
        $data['vendor_id'] = ($data['vendor_id'] ?? null) === 'none' ? null : ($data['vendor_id'] ?? null);
        $data['driver_id'] = ($data['driver_id'] ?? null) === 'none' ? null : ($data['driver_id'] ?? null);

        // Merge transformed data back to request
        $request->merge($data);

        // Validate the incoming request data
        $validated = $request->validate([
            'brand' => 'required|string|max:255',
            'model' => 'required|string|max:255',
            'color' => 'nullable|string|max:255', // Made optional
            'registration_number' => 'required|string|max:255|unique:vehicles',
            'vehicle_type' => 'required|in:sedan,suv,van,microbus,coaster,bus,pickup,truck,other',
            'rental_type' => 'required|in:own,pool,rental,adhoc,support',
            'capacity' => 'nullable|integer|min:1',
            'parking_address' => 'nullable|string',
            'parking_latitude' => 'nullable|numeric|between:-90,90',
            'parking_longitude' => 'nullable|numeric|between:-180,180',
            'vendor' => 'nullable|string|max:255', // Keep for backward compatibility
            'vendor_id' => 'required|exists:vendors,id', // Required service provider
            'driver_id' => 'required|exists:users,id', // Required driver
            'is_active' => 'boolean',
            'status' => 'nullable|in:available,assigned,in_transit,maintenance,out_of_service',
            // Tax Token
            'tax_token_last_date' => 'nullable|date',
            'tax_token_number' => 'nullable|string|max:255',
            'tax_token_file' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:2048',
            // Fitness Certificate
            'fitness_certificate_last_date' => 'nullable|date',
            'fitness_certificate_number' => 'nullable|string|max:255',
            'fitness_certificate_file' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:2048',
            // Insurance
            'insurance_type' => 'nullable|in:1st_party,3rd_party,comprehensive',
            'insurance_last_date' => 'nullable|date',
            'insurance_policy_number' => 'nullable|string|max:255',
            'insurance_policy_file' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:2048',
            'insurance_company' => 'nullable|string|max:255',
            // Registration Certificate & Owner Info
            'registration_certificate_number' => 'nullable|string|max:255',
            'registration_certificate_file' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:2048',
            'owner_name' => 'nullable|string|max:255',
            'owner_address' => 'nullable|string',
            'owner_phone' => 'nullable|string|max:20',
            'owner_email' => 'nullable|email|max:255',
            'owner_nid' => 'nullable|string|max:50',
            // Additional Vehicle Info
            'manufacture_year' => 'nullable|numeric|min:1900|max:' . (date('Y') + 1),
            'engine_number' => 'nullable|string|max:255',
            'chassis_number' => 'nullable|string|max:255',
            'fuel_type' => 'nullable|in:petrol,diesel,cng,electric,hybrid',
            // Alert Settings
            'tax_token_alert_enabled' => 'sometimes|boolean',
            'fitness_alert_enabled' => 'sometimes|boolean',
            'insurance_alert_enabled' => 'sometimes|boolean',
            'alert_days_before' => 'nullable|numeric|min:1|max:365',
        ]);

        foreach (['tax_token_file', 'fitness_certificate_file', 'insurance_policy_file', 'registration_certificate_file'] as $field) {
            if ($request->hasFile($field)) {
                $validated[$field] = $request->file($field)->store('vehicle_documents', 'public');
            }
        }

        \Log::info('Validated data:', $validated);

        // Set default values for boolean fields if not present
        $validated['tax_token_alert_enabled'] = $validated['tax_token_alert_enabled'] ?? true;
        $validated['fitness_alert_enabled'] = $validated['fitness_alert_enabled'] ?? true;
        $validated['insurance_alert_enabled'] = $validated['insurance_alert_enabled'] ?? true;
        $validated['alert_days_before'] = $validated['alert_days_before'] ?? 30;

        try {
            $vehicle = Vehicle::create($validated);
            \Log::info('Vehicle created successfully:', ['vehicle_id' => $vehicle->id]);

            return redirect()
                ->route('vehicles.index')
                ->with('success', 'Vehicle created successfully!');
        } catch (\Exception $e) {
            \Log::error('Error creating vehicle:', ['error' => $e->getMessage()]);

            return back()
                ->withErrors(['error' => 'Something went wrong while creating the vehicle. Please try again.'])
                ->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Vehicle $vehicle)
    {
        // The assigned driver's contact/identity details (license no., NID, phone,
        // address, emergency contact) are only shown to viewers who can also
        // manage drivers — everyone else with view-vehicles sees just who's driving.
        $canViewDriverDetails = auth()->user()->can('view-drivers');

        $vehicle->load([
            'vendor.contactPersons',
            $canViewDriverDetails ? 'driver' : 'driver:id,name,status,employee_id,user_type',
        ]);

        $assignments = $vehicle->driverAssignments()
            ->with(['driver:id,name,email', 'assigner:id,name'])
            ->orderByDesc('started_at')
            ->get();

        $canAssignVehicles = auth()->user()->can('assign-vehicles');

        return Inertia::render('vehicles/show', [
            'vehicle' => $vehicle,
            'assignments' => $assignments,
            'assignableDrivers' => $canAssignVehicles ? $this->assignableDrivers($vehicle->driver_id) : [],
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Vehicle $vehicle)
    {
        $vehicle->load(['vendor', 'driver']);

        $vendors = \App\Models\Vendor::active()
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        $drivers = $this->assignableDrivers($vehicle->driver_id);

        return Inertia::render('vehicles/edit', [
            'vehicle' => $vehicle,
            'vendors' => $vendors,
            'drivers' => $drivers,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Vehicle $vehicle)
    {
        // Transform 'none' values to null before validation
        $data = $request->all();
        $data['fuel_type'] = ($data['fuel_type'] ?? null) === 'none' ? null : ($data['fuel_type'] ?? null);
        $data['insurance_type'] = ($data['insurance_type'] ?? null) === 'none' ? null : ($data['insurance_type'] ?? null);
        $data['vendor_id'] = ($data['vendor_id'] ?? null) === 'none' ? null : ($data['vendor_id'] ?? null);
        $data['driver_id'] = ($data['driver_id'] ?? null) === 'none' ? null : ($data['driver_id'] ?? null);

        // Merge transformed data back to request
        $request->merge($data);

        $validated = $request->validate([
            'brand' => 'required|string|max:255',
            'model' => 'required|string|max:255',
            'color' => 'nullable|string|max:255', // Made optional
            'registration_number' => 'required|string|max:255|unique:vehicles,registration_number,' . $vehicle->id,
            'vehicle_type' => 'required|in:sedan,suv,van,microbus,coaster,bus,pickup,truck,other',
            'rental_type' => 'required|in:own,pool,rental,adhoc,support',
            'capacity' => 'nullable|integer|min:1',
            'parking_address' => 'nullable|string',
            'parking_latitude' => 'nullable|numeric|between:-90,90',
            'parking_longitude' => 'nullable|numeric|between:-180,180',
            'vendor' => 'nullable|string|max:255', // Keep for backward compatibility
            // A vehicle can become vendor/driver-less via the "Assign Driver"
            // unassign flow, so editing it afterward must not force one back.
            'vendor_id' => 'nullable|exists:vendors,id',
            'driver_id' => 'nullable|exists:users,id',
            'is_active' => 'boolean',
            'status' => 'nullable|in:available,assigned,in_transit,maintenance,out_of_service',
            // Tax Token
            'tax_token_last_date' => 'nullable|date',
            'tax_token_number' => 'nullable|string|max:255',
            'tax_token_file' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:2048',
            // Fitness Certificate
            'fitness_certificate_last_date' => 'nullable|date',
            'fitness_certificate_number' => 'nullable|string|max:255',
            'fitness_certificate_file' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:2048',
            // Insurance
            'insurance_type' => 'nullable|in:1st_party,3rd_party,comprehensive',
            'insurance_last_date' => 'nullable|date',
            'insurance_policy_number' => 'nullable|string|max:255',
            'insurance_policy_file' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:2048',
            'insurance_company' => 'nullable|string|max:255',
            // Registration Certificate & Owner Info
            'registration_certificate_number' => 'nullable|string|max:255',
            'registration_certificate_file' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:2048',
            'owner_name' => 'nullable|string|max:255',
            'owner_address' => 'nullable|string',
            'owner_phone' => 'nullable|string|max:20',
            'owner_email' => 'nullable|email|max:255',
            'owner_nid' => 'nullable|string|max:50',
            // Additional Vehicle Info
            'manufacture_year' => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
            'engine_number' => 'nullable|string|max:255',
            'chassis_number' => 'nullable|string|max:255',
            'fuel_type' => 'nullable|in:petrol,diesel,cng,electric,hybrid',
            // Alert Settings
            'tax_token_alert_enabled' => 'boolean',
            'fitness_alert_enabled' => 'boolean',
            'insurance_alert_enabled' => 'boolean',
            'alert_days_before' => 'nullable|integer|min:1|max:365',
        ]);

        foreach (['tax_token_file', 'fitness_certificate_file', 'insurance_policy_file', 'registration_certificate_file'] as $field) {
            if ($request->hasFile($field)) {
                if ($vehicle->$field) {
                    Storage::disk('public')->delete($vehicle->$field);
                }
                $validated[$field] = $request->file($field)->store('vehicle_documents', 'public');
            }
        }

        DB::transaction(function () use ($vehicle, $validated) {
            // Lock the vehicle so a concurrent update can't race
            // VehicleObserver::updating()'s close-old/create-new driver
            // assignment rows below.
            $vehicle = Vehicle::whereKey($vehicle->id)->lockForUpdate()->firstOrFail();
            $vehicle->update($validated);
        });

        return redirect()
            ->route('vehicles.index')
            ->with('success', 'Vehicle updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Vehicle $vehicle)
    {
        foreach (['tax_token_file', 'fitness_certificate_file', 'insurance_policy_file', 'registration_certificate_file'] as $field) {
            if ($vehicle->$field) {
                Storage::disk('public')->delete($vehicle->$field);
            }
        }

        $vehicle->delete();

        return redirect()
            ->route('vehicles.index')
            ->with('success', 'Vehicle deleted successfully!');
    }

    /**
     * Get vehicles with expiring documents for dashboard
     */
    public function getExpiringVehicles()
    {
        $expiringVehicles = Vehicle::withExpiringDocuments()
            ->where('is_active', true)
            ->get()
            ->map(function ($vehicle) {
                return [
                    'id' => $vehicle->id,
                    'brand' => $vehicle->brand,
                    'model' => $vehicle->model,
                    'registration_number' => $vehicle->registration_number,
                    'expiring_documents' => $vehicle->getExpiringDocuments(),
                ];
            });

        return response()->json($expiringVehicles);
    }
}
