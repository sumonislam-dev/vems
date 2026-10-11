<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use App\Models\Zone;
use App\Support\PrivateDocument;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class VendorController extends Controller implements HasMiddleware
{
    private const DOCUMENT_FIELDS = ['trade_license_file', 'tin_file', 'bin_file', 'tax_return_file'];

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-vendors', only: ['index', 'show']),
            new Middleware('permission:create-vendors', only: ['create', 'store']),
            new Middleware('permission:edit-vendors', only: ['edit', 'update']),
            new Middleware('permission:delete-vendors', only: ['destroy']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Vendor::with([
            'contactPersons:id,vendor_id',
            'vehicles:id,vendor_id',
            'zone:id,name',
        ])->withCount('vehicles');

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Sorting
        $sortField = $request->get('sort', 'name');
        $sortDirection = $request->get('direction', 'asc');
        $query->orderBy($sortField, $sortDirection);

        $vendors = $query->paginate($request->get('per_page', 15))
            ->withQueryString();

        // Get stats with a single aggregate query
        $vendorStats = Vendor::selectRaw(
            'COUNT(*) as total, '.
            'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as active, '.
            'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as inactive, '.
            'SUM(CASE WHEN EXISTS (SELECT 1 FROM vehicles WHERE vehicles.vendor_id = vendors.id) THEN 1 ELSE 0 END) as with_vehicles',
            ['active', 'inactive']
        )->first();

        $stats = [
            'total' => (int) ($vendorStats->total ?? 0),
            'active' => (int) ($vendorStats->active ?? 0),
            'inactive' => (int) ($vendorStats->inactive ?? 0),
            'with_vehicles' => (int) ($vendorStats->with_vehicles ?? 0),
        ];

        return Inertia::render('vendors/index', [
            'vendors' => $vendors,
            'stats' => $stats,
            'queryParams' => $request->only(['search', 'sort', 'direction', 'status', 'per_page']),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('vendors/create', [
            'zones' => Zone::selectableBy(),
            'defaultZoneId' => auth()->user()->zone_id,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',
            'description' => 'nullable|string',
            'status' => 'required|in:active,inactive',
            'trade_license' => 'nullable|string|max:255',
            'trade_license_file' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:2048',
            'tin' => 'nullable|string|max:255',
            'tin_file' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:2048',
            'bin' => 'nullable|string|max:255',
            'bin_file' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:2048',
            'tax_return' => 'nullable|string|max:255',
            'tax_return_file' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:2048',
            'bank_details' => 'nullable|string',
            'zone_id' => Zone::validationRule(),
            'contact_persons' => 'array|min:1', // Minimum 1 contact person
            'contact_persons.*.name' => 'required|string|max:255',
            'contact_persons.*.position' => 'nullable|string|max:255',
            'contact_persons.*.phone' => 'nullable|string|max:20',
            'contact_persons.*.email' => 'nullable|email|max:255',
            'contact_persons.*.is_primary' => 'boolean',
            'contact_persons.*.notes' => 'nullable|string',
        ]);

        $data = $request->except(self::DOCUMENT_FIELDS);

        foreach (self::DOCUMENT_FIELDS as $field) {
            if ($request->hasFile($field)) {
                $data[$field] = PrivateDocument::replace(null, $request->file($field), 'vendor_documents');
            }
        }

        try {
            $vendor = Vendor::create($data);

            if (isset($validated['contact_persons'])) {
                foreach ($validated['contact_persons'] as $contactData) {
                    $vendor->contactPersons()->create($contactData);
                }
            }

            return redirect()->route('vendors.index')
                ->with('success', 'Vendor created successfully!');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Failed to create vendor.');
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Vendor $vendor)
    {
        $vendor->load(['contactPersons', 'vehicles']);

        return Inertia::render('vendors/show', [
            'vendor' => $vendor,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Vendor $vendor)
    {
        $vendor->load('contactPersons');

        // Transform the vendor data to match frontend expectations
        $vendorData = $vendor->toArray();
        $vendorData['contact_persons'] = $vendor->contactPersons->toArray();

        // Remove the camelCase relationship key if Laravel added it
        unset($vendorData['contact_persons_count']);
        unset($vendorData['contactPersons']);

        return Inertia::render('vendors/edit', [
            'vendor' => $vendorData,
            'zones' => Zone::selectableBy(auth()->user(), $vendor->zone_id),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Vendor $vendor)
    {

        // Get existing contact persons count
        $existingContactsCount = $vendor->contactPersons()->count();
        $submittedContactsCount = count($request->input('contact_persons', []));

        // Only require minimum 1 if no existing contacts and no submitted contacts
        $minContactsRule = ($existingContactsCount > 0 || $submittedContactsCount >= 1) ? 'array' : 'array|min:1';

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',
            'description' => 'nullable|string',
            'status' => 'required|in:active,inactive',
            'trade_license' => 'nullable|string|max:255',
            'trade_license_file' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:2048',
            'tin' => 'nullable|string|max:255',
            'tin_file' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:2048',
            'bin' => 'nullable|string|max:255',
            'bin_file' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:2048',
            'tax_return' => 'nullable|string|max:255',
            'tax_return_file' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:2048',
            'bank_details' => 'nullable|string',
            'zone_id' => Zone::validationRule(),
            'contact_persons' => $minContactsRule,
            'contact_persons.*.id' => [
                'nullable',
                Rule::exists('vendor_contact_persons', 'id')->where('vendor_id', $vendor->id),
            ],
            'contact_persons.*.name' => 'required|string|max:255',
            'contact_persons.*.position' => 'nullable|string|max:255',
            'contact_persons.*.phone' => 'nullable|string|max:20',
            'contact_persons.*.email' => 'nullable|email|max:255',
            'contact_persons.*.is_primary' => 'boolean',
            'contact_persons.*.notes' => 'nullable|string',
        ]);

        try {
            $data = $request->except(self::DOCUMENT_FIELDS);

            foreach (self::DOCUMENT_FIELDS as $field) {
                if ($request->hasFile($field)) {
                    $data[$field] = PrivateDocument::replace($vendor->{$field}, $request->file($field), 'vendor_documents');
                }
            }

            $vendor->update($data);

            // Update contact persons
            if (isset($validated['contact_persons'])) {
                // Get existing contact person IDs
                $existingIds = $vendor->contactPersons->pluck('id')->toArray();
                $submittedIds = collect($validated['contact_persons'])
                    ->pluck('id')
                    ->filter()
                    ->toArray();

                // Delete removed contact persons
                $toDelete = array_diff($existingIds, $submittedIds);
                $vendor->contactPersons()->whereIn('id', $toDelete)->delete();

                // Update or create contact persons
                foreach ($validated['contact_persons'] as $contactData) {
                    if (isset($contactData['id'])) {
                        // Update existing (scoped to this vendor)
                        $vendor->contactPersons()->where('id', $contactData['id'])
                            ->update(\Arr::except($contactData, ['id']));
                    } else {
                        // Create new
                        $vendor->contactPersons()->create($contactData);
                    }
                }
            }

            return redirect()
                ->route('vendors.index')
                ->with('success', 'Vendor updated successfully!');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Failed to update vendor.');
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Vendor $vendor)
    {
        if ($vendor->vehicles()->count() > 0) {
            return back()->with('error', 'Cannot delete vendor with associated vehicles.');
        }
        try {
            $vendor->delete();

            foreach (self::DOCUMENT_FIELDS as $field) {
                PrivateDocument::delete($vendor->{$field});
            }

            return redirect()->route('vendors.index')
                ->with('success', 'Vendor deleted successfully!');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to delete vendor.');
        }
    }
}
