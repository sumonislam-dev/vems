<?php

namespace App\Http\Controllers;

use App\Enums\TripStatus;
use App\Exports\VehiclesExport;
use App\Http\Requests\StoreVehicleRequest;
use App\Http\Requests\UpdateVehicleRequest;
use App\Http\Requests\VehicleIndexRequest;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDowntime;
use App\Models\VehicleRoute;
use App\Models\Vendor;
use App\Models\Zone;
use App\Support\PrivateDocument;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
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
     * Drivers eligible for a vehicle assignment dropdown (see
     * User::scopeAssignableDrivers()); $includeDriverId keeps a vehicle's
     * current driver in the list even if they no longer qualify.
     */
    private function assignableDrivers(?int $includeDriverId = null)
    {
        return User::assignableDrivers($includeDriverId)
            ->select('id', 'name', 'email', 'user_type', 'official_phone')
            ->orderBy('name')
            ->get();
    }

    /**
     * Sort the list or export: vendor and driver by their names, everything
     * else by the vehicle's own column (VehicleIndexRequest limits the keys).
     */
    private function applySort($query, string $column, string $direction): void
    {
        match ($column) {
            'vendor' => $query->orderBy(Vendor::withoutGlobalScopes()->select('name')->whereColumn('vendors.id', 'vehicles.vendor_id'), $direction),
            'driver' => $query->orderBy(User::withoutGlobalScopes()->select('name')->whereColumn('users.id', 'vehicles.driver_id'), $direction),
            default => $query->orderBy($column, $direction),
        };
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

        $this->applySort($query, $validated['sort'], $validated['direction']);

        // Get pagination data
        $vehicles = $query->paginate($validated['per_page'])
            ->withQueryString(); // Preserve query parameters in pagination links
        $vehicles->getCollection()->each->append('expiring_documents');

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

        $this->applySort($query, $validated['sort'], $validated['direction']);

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
            'routes' => VehicleRoute::select('id', 'name')->orderBy('name')->get(),
            'defaultZoneId' => auth()->user()->zone_id,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreVehicleRequest $request)
    {
        $isAdhoc = $request->isAdhoc();
        $validated = $request->validated();

        foreach (Vehicle::DOCUMENT_FILES as $field) {
            if ($request->hasFile($field)) {
                $validated[$field] = PrivateDocument::replace(null, $request->file($field), 'vehicle_documents');
            }
        }

        // Set default values for boolean fields if not present
        $validated['tax_token_alert_enabled'] = $validated['tax_token_alert_enabled'] ?? true;
        $validated['fitness_alert_enabled'] = $validated['fitness_alert_enabled'] ?? true;
        $validated['insurance_alert_enabled'] = $validated['insurance_alert_enabled'] ?? true;
        $validated['route_permit_alert_enabled'] = $validated['route_permit_alert_enabled'] ?? true;
        $validated['alert_days_before'] = $validated['alert_days_before'] ?? 30;

        if ($isAdhoc) {
            $validated = $this->fillAdhocPlaceholders($validated);
        }

        try {
            Vehicle::create($validated);

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
            'vehicle' => $vehicle->append('expiring_documents'),
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
            'routes' => VehicleRoute::select('id', 'name')->orderBy('name')->get(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateVehicleRequest $request, Vehicle $vehicle)
    {
        $isAdhoc = $request->isAdhoc();
        $validated = $request->validated();

        foreach (Vehicle::DOCUMENT_FILES as $field) {
            if ($request->hasFile($field)) {
                $validated[$field] = PrivateDocument::replace($vehicle->$field, $request->file($field), 'vehicle_documents');
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
        if ($vehicle->trips()->whereIn('status', TripStatus::open())->exists()) {
            return back()->with('error', 'Cannot delete a vehicle with active trips. Reassign or finish them first.');
        }

        // Soft delete: past trips, reports and documents keep the vehicle.
        // Unassigning the driver first closes their assignment history.
        if ($vehicle->driver_id) {
            $vehicle->update(['driver_id' => null]);
        }

        $vehicle->delete();

        return redirect()
            ->route('vehicles.index')
            ->with('success', 'Vehicle deleted successfully!');
    }
}
