<?php

namespace App\Http\Controllers;

use App\Exports\VehiclesExport;
use App\Http\Requests\VehicleIndexRequest;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDowntime;
use App\Models\VehicleRoute;
use App\Models\Vendor;
use App\Models\Zone;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class VehicleController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-vehicles', only: ['index', 'show']),
            new Middleware('permission:export-vehicles', only: ['export']),
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

        $query = Vehicle::with(['vendor', 'driver', 'zone', 'defaultRoute:id,name']);

        // Apply search
        if (! empty($validated['search'])) {
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
        if (! empty($validated['filters'])) {
            $filters = $validated['filters'];

            if (! empty($filters['brand'])) {
                $query->whereIn('brand', $filters['brand']);
            }

            if (! empty($filters['color'])) {
                $query->whereIn('color', $filters['color']);
            }

            if (! empty($filters['vehicle_type'])) {
                $query->whereIn('vehicle_type', $filters['vehicle_type']);
            }

            if (! empty($filters['rental_type'])) {
                $query->whereIn('rental_type', $filters['rental_type']);
            }

            if (! empty($filters['fuel_type'])) {
                $query->whereIn('fuel_type', $filters['fuel_type']);
            }

            if (! empty($filters['vendor_id'])) {
                $query->whereIn('vendor_id', $filters['vendor_id']);
            }

            if (! empty($filters['zone_id'])) {
                $query->whereIn('zone_id', $filters['zone_id']);
            }

            if (isset($filters['is_active']) && ! empty($filters['is_active'])) {
                $query->whereIn('is_active', $filters['is_active']);
            }

            if (! empty($filters['status'])) {
                $query->whereIn('status', $filters['status']);
            }

            // Ad-hoc placeholders still waiting for the real car's details
            if (! empty($filters['details']) && count($filters['details']) === 1) {
                $filters['details'][0] === 'pending'
                    ? $query->placeholders()
                    : $query->whereNot(fn ($q) => $q->placeholders());
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
            'vendors' => Vendor::active()->select('id', 'name')->orderBy('name')->get()->map(fn ($v) => [
                'label' => $v->name,
                'value' => (string) $v->id,
            ]),
            'zones' => Zone::selectableBy()->map(fn ($z) => [
                'label' => $z->name,
                'value' => (string) $z->id,
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
            'COUNT(*) as total, '.
            'SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active, '.
            'COUNT(DISTINCT brand) as brands, '.
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

        if (! empty($validated['search'])) {
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

        if (! empty($validated['filters'])) {
            $filters = $validated['filters'];

            if (! empty($filters['brand'])) {
                $query->whereIn('brand', $filters['brand']);
            }

            if (! empty($filters['color'])) {
                $query->whereIn('color', $filters['color']);
            }

            if (! empty($filters['vehicle_type'])) {
                $query->whereIn('vehicle_type', $filters['vehicle_type']);
            }

            if (! empty($filters['rental_type'])) {
                $query->whereIn('rental_type', $filters['rental_type']);
            }

            if (! empty($filters['fuel_type'])) {
                $query->whereIn('fuel_type', $filters['fuel_type']);
            }

            if (! empty($filters['vendor_id'])) {
                $query->whereIn('vendor_id', $filters['vendor_id']);
            }

            if (! empty($filters['zone_id'])) {
                $query->whereIn('zone_id', $filters['zone_id']);
            }

            if (isset($filters['is_active']) && ! empty($filters['is_active'])) {
                $query->whereIn('is_active', $filters['is_active']);
            }

            if (! empty($filters['status'])) {
                $query->whereIn('status', $filters['status']);
            }

            // Ad-hoc placeholders still waiting for the real car's details
            if (! empty($filters['details']) && count($filters['details']) === 1) {
                $filters['details'][0] === 'pending'
                    ? $query->placeholders()
                    : $query->whereNot(fn ($q) => $q->placeholders());
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
            ->header('Content-Disposition', 'attachment; filename="vehicles_export_'.$timestamp.'.csv"');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        try {
            $vendors = Vendor::active()
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

        $zones = Zone::selectableBy();

        return Inertia::render('vehicles/create', [
            'vendors' => $vendors,
            'drivers' => $drivers,
            'zones' => $zones,
            'routes' => $this->routeOptions(),
            'defaultZoneId' => auth()->user()->zone_id,
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
        $data['zone_id'] = ($data['zone_id'] ?? null) === 'none' ? null : ($data['zone_id'] ?? null);
        $data['default_route_id'] = ($data['default_route_id'] ?? null) === 'none' ? null : ($data['default_route_id'] ?? null);

        // Merge transformed data back to request
        $request->merge($data);

        // An ad-hoc hire can be booked before the actual car is known: its
        // identity, vendor and driver may be left blank and are filled with
        // placeholders below (see Vehicle::PLACEHOLDER_*).
        $isAdhoc = $request->input('rental_type') === 'adhoc';
        $identityRule = $isAdhoc ? 'nullable' : 'required';

        // Validate the incoming request data
        $validated = $request->validate([
            'brand' => "{$identityRule}|string|max:255",
            'model' => "{$identityRule}|string|max:255",
            'color' => 'nullable|string|max:255', // Made optional
            'registration_number' => "{$identityRule}|string|max:255|unique:vehicles",
            'vehicle_type' => 'required|in:sedan,suv,van,microbus,coaster,bus,pickup,truck,other',
            'rental_type' => 'required|in:own,pool,rental,adhoc,support',
            'capacity' => 'nullable|integer|min:1',
            'parking_address' => 'nullable|string',
            'parking_latitude' => 'nullable|numeric|between:-90,90',
            'parking_longitude' => 'nullable|numeric|between:-180,180',
            'vendor' => 'nullable|string|max:255', // Keep for backward compatibility
            'vendor_id' => "{$identityRule}|exists:vendors,id", // Required service provider (except ad-hoc)
            'driver_id' => "{$identityRule}|exists:users,id", // Required driver (except ad-hoc)
            'zone_id' => Zone::validationRule(),
            // Only routes the user can see (VehicleRoute is zone-scoped)
            'default_route_id' => ['nullable', Rule::in(VehicleRoute::pluck('id')->all())],
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
            'manufacture_year' => 'nullable|numeric|min:1900|max:'.(date('Y') + 1),
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

        // Set default values for boolean fields if not present
        $validated['tax_token_alert_enabled'] = $validated['tax_token_alert_enabled'] ?? true;
        $validated['fitness_alert_enabled'] = $validated['fitness_alert_enabled'] ?? true;
        $validated['insurance_alert_enabled'] = $validated['insurance_alert_enabled'] ?? true;
        $validated['alert_days_before'] = $validated['alert_days_before'] ?? 30;

        if ($isAdhoc) {
            $validated = $this->fillAdhocPlaceholders($validated);
        }

        try {
            $vehicle = Vehicle::create($validated);

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
            'defaultRoute:id,name',
            $canViewDriverDetails ? 'driver' : 'driver:id,name,status,employee_id,user_type',
        ]);

        $assignments = $vehicle->driverAssignments()
            ->with(['driver:id,name,email', 'assigner:id,name'])
            ->orderByDesc('started_at')
            ->get();

        $canAssignVehicles = auth()->user()->can('assign-vehicles');

        $canManageDowntime = auth()->user()->can('manage-vehicle-downtime');

        return Inertia::render('vehicles/show', [
            'vehicle' => $vehicle,
            'assignments' => $assignments,
            'assignableDrivers' => $canAssignVehicles ? $this->assignableDrivers($vehicle->driver_id) : [],
            'downtimes' => $vehicle->downtimes()
                ->with(['replacementVehicle:id,registration_number', 'recorder:id,name'])
                ->get()
                ->map(fn ($d) => [...$d->toArray(), 'reason_label' => $d->reason_label]),
            'downtimeReasons' => collect(VehicleDowntime::REASONS)->map(fn ($label, $value) => compact('value', 'label'))->values(),
            'replacementVehicles' => $canManageDowntime
                ? Vehicle::whereKeyNot($vehicle->id)->where('is_active', true)->orderBy('registration_number')->get(['id', 'registration_number'])
                : [],
            'canManageDowntime' => $canManageDowntime,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Vehicle $vehicle)
    {
        $vehicle->load(['vendor', 'driver']);

        $vendors = Vendor::active()
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        $drivers = $this->assignableDrivers($vehicle->driver_id);
        $zones = Zone::selectableBy(auth()->user(), $vehicle->zone_id);

        return Inertia::render('vehicles/edit', [
            'vehicle' => $vehicle,
            'vendors' => $vendors,
            'drivers' => $drivers,
            'zones' => $zones,
            'routes' => $this->routeOptions(),
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
        $data['zone_id'] = ($data['zone_id'] ?? null) === 'none' ? null : ($data['zone_id'] ?? null);
        $data['default_route_id'] = ($data['default_route_id'] ?? null) === 'none' ? null : ($data['default_route_id'] ?? null);

        // Merge transformed data back to request
        $request->merge($data);

        // Same ad-hoc leniency as store(); a blank registration on edit just
        // keeps the vehicle's current one rather than minting a new placeholder.
        $isAdhoc = $request->input('rental_type') === 'adhoc';
        $identityRule = $isAdhoc ? 'nullable' : 'required';

        $validated = $request->validate([
            'brand' => "{$identityRule}|string|max:255",
            'model' => "{$identityRule}|string|max:255",
            'color' => 'nullable|string|max:255', // Made optional
            'registration_number' => "{$identityRule}|string|max:255|unique:vehicles,registration_number,".$vehicle->id,
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
            'zone_id' => Zone::validationRule(),
            // Only routes the user can see (VehicleRoute is zone-scoped)
            // ...plus whatever it already has, so an unrelated edit doesn't fail
            'default_route_id' => ['nullable', Rule::in([...VehicleRoute::pluck('id')->all(), $vehicle->default_route_id])],
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
            'manufacture_year' => 'nullable|integer|min:1900|max:'.(date('Y') + 1),
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

        if ($isAdhoc) {
            if (blank($validated['registration_number'] ?? null)) {
                $validated['registration_number'] = $vehicle->registration_number;
            }
            $validated = $this->fillAdhocPlaceholders($validated);
        }

        DB::transaction(function () use ($vehicle, $validated) {
            // Lock the vehicle so a concurrent update can't race
            // VehicleObserver::updating()'s close-old/create-new driver
            // assignment rows below.
            $vehicle = Vehicle::whereKey($vehicle->id)->lockForUpdate()->firstOrFail();
            $vehicle->update($validated);
        });

        // Going out of service auto-starts a downtime (VehicleObserver) with a
        // default reason — send the user to the vehicle so they can review it.
        $newStatus = $validated['status'] ?? $vehicle->status;
        if (in_array($newStatus, Vehicle::DOWN_STATUSES, true) && ! in_array($vehicle->status, Vehicle::DOWN_STATUSES, true)) {
            return redirect()
                ->route('vehicles.show', $vehicle)
                ->with('success', 'Vehicle updated. Downtime started from today — please check the reason and add a replacement vehicle if one was provided.');
        }

        return redirect()
            ->route('vehicles.index')
            ->with('success', 'Vehicle updated successfully!');
    }

    /**
     * Routes offered in the vehicle form's "Regular Pick & Drop Route" picker.
     */
    private function routeOptions()
    {
        return VehicleRoute::select('id', 'name')->orderBy('name')->get();
    }

    /**
     * Fill an ad-hoc vehicle's blank identity fields with placeholders, so
     * every screen and export that prints brand/model/registration keeps
     * working until the real car's details are entered.
     */
    private function fillAdhocPlaceholders(array $validated): array
    {
        if (blank($validated['registration_number'] ?? null)) {
            $validated['registration_number'] = Vehicle::nextPlaceholderRegistration();
        }
        if (blank($validated['brand'] ?? null)) {
            $validated['brand'] = Vehicle::PLACEHOLDER_BRAND;
        }
        if (blank($validated['model'] ?? null)) {
            $validated['model'] = Vehicle::PLACEHOLDER_MODEL;
        }

        return $validated;
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
}
