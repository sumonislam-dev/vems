<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreZoneRequest;
use App\Http\Requests\UpdateZoneRequest;
use App\Http\Requests\ZoneIndexRequest;
use App\Models\Zone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class ZoneController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-zones', only: ['index']),
            new Middleware('permission:create-zones', only: ['create', 'store']),
            new Middleware('permission:edit-zones', only: ['edit', 'update', 'toggleStatus']),
            new Middleware('permission:delete-zones', only: ['destroy']),
        ];
    }

    /**
     * Display a listing of zones.
     */
    public function index(ZoneIndexRequest $request): Response
    {
        $validated = $request->validated();

        $query = Zone::query();

        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if (! empty($validated['filters']['status'])) {
            $statuses = $validated['filters']['status'];
            $query->where(function ($q) use ($statuses) {
                foreach ($statuses as $status) {
                    if ($status === 'active') {
                        $q->orWhere('is_active', true);
                    } elseif ($status === 'inactive') {
                        $q->orWhere('is_active', false);
                    }
                }
            });
        }

        $sortColumn = $validated['sort'];
        $sortDirection = $validated['direction'];

        $validSortFields = ['name', 'code', 'is_active', 'created_at', 'status'];
        if (in_array($sortColumn, $validSortFields)) {
            $dbColumn = $sortColumn === 'status' ? 'is_active' : $sortColumn;
            $query->orderBy($dbColumn, $sortDirection);
        }

        $zones = $query->paginate($validated['per_page'])
            ->withQueryString()
            ->through(fn ($zone) => [
                'id' => $zone->id,
                'name' => $zone->name,
                'code' => $zone->code,
                'is_active' => $zone->is_active,
                'status' => $zone->is_active ? 'active' : 'inactive',
                'created_at' => $zone->created_at,
            ]);

        return Inertia::render('zones/index', [
            'zones' => $zones,
            'filterOptions' => [
                'statuses' => ['active', 'inactive'],
            ],
            'queryParams' => $request->only(['search', 'sort', 'direction', 'filters', 'per_page']),
        ]);
    }

    /**
     * Show the form for creating a new zone.
     */
    public function create(): Response
    {
        return Inertia::render('zones/create');
    }

    /**
     * Store a newly created zone in storage.
     */
    public function store(StoreZoneRequest $request): RedirectResponse
    {
        try {
            Zone::create($request->validated());

            return redirect()->route('zones.index')
                ->with('success', 'Zone created successfully.');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Failed to create zone.');
        }
    }

    /**
     * Show the form for editing the specified zone.
     */
    public function edit(Zone $zone): Response
    {
        return Inertia::render('zones/edit', [
            'zone' => $zone,
        ]);
    }

    /**
     * Update the specified zone in storage.
     */
    public function update(UpdateZoneRequest $request, Zone $zone): RedirectResponse
    {
        try {
            $zone->update($request->validated());

            return redirect()->route('zones.index')
                ->with('success', 'Zone updated successfully.');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Failed to update zone.');
        }
    }

    /**
     * Remove the specified zone from storage.
     */
    public function destroy(Zone $zone): RedirectResponse
    {
        try {
            $zone->delete();

            return redirect()->route('zones.index')
                ->with('success', 'Zone deleted successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to delete zone.');
        }
    }

    /**
     * Toggle zone status.
     */
    public function toggleStatus(Zone $zone): RedirectResponse
    {
        $zone->update(['is_active' => ! $zone->is_active]);

        $status = $zone->is_active ? 'activated' : 'deactivated';

        return redirect()->back()->with('success', "Zone {$status} successfully.");
    }
}
