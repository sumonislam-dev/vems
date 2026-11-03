<?php

namespace App\Http\Controllers;

use App\Models\RouteStop;
use App\Models\Stop;
use App\Models\TripPassenger;
use App\Models\TripStop;
use App\Models\Zone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class StopController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-stops', only: ['index', 'apiIndex']),
            new Middleware('permission:create-stops', only: ['create', 'store', 'apiStore']),
            new Middleware('permission:edit-stops', only: ['edit', 'update']),
            new Middleware('permission:delete-stops', only: ['destroy']),
        ];
    }

    /**
     * Display a listing of stops.
     */
    public function index(Request $request): Response
    {
        $query = Stop::with('zone')->withCount('routeStops');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $sortColumn = in_array($request->get('sort'), ['name', 'route_stops_count', 'created_at'], true)
            ? $request->get('sort') : 'name';
        $sortDirection = $request->get('direction') === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortColumn, $sortDirection);

        $perPage = $request->get('per_page', 15);
        $stops = $query->paginate($perPage)->withQueryString();

        return Inertia::render('stops/index', [
            'stops' => $stops,
            'queryParams' => $request->only(['search', 'sort', 'direction', 'per_page']),
        ]);
    }

    /**
     * Show the form for creating a new stop.
     */
    public function create(): Response
    {
        return Inertia::render('stops/create', [
            'zones' => Zone::selectableBy(),
            'defaultZoneId' => auth()->user()->zone_id,
            'stops' => $this->mapContextStops(),
        ]);
    }

    /**
     * Store a newly created stop.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:stops,name',
            'description' => 'nullable|string|max:500',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'zone_id' => Zone::validationRule(),
        ]);

        // The zone select submits '' for "no zone chosen" — normalize to
        // null so it doesn't get written as an invalid empty string into
        // the zone_id foreign key column.
        $validated['zone_id'] = ($validated['zone_id'] ?? null) ?: null;

        Stop::create($validated);

        return redirect()->route('stops.index')->with('success', 'Stop created successfully.');
    }

    /**
     * Show the form for editing the specified stop.
     */
    public function edit(Stop $stop): Response
    {
        return Inertia::render('stops/edit', [
            'stop' => $stop,
            'zones' => Zone::selectableBy(auth()->user(), $stop->zone_id),
            'stops' => $this->mapContextStops(),
        ]);
    }

    /**
     * Update the specified stop.
     */
    public function update(Request $request, Stop $stop): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:stops,name,'.$stop->id,
            'description' => 'nullable|string|max:500',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'zone_id' => Zone::validationRule(),
        ]);

        $validated['zone_id'] = ($validated['zone_id'] ?? null) ?: null;

        $stop->update($validated);

        return redirect()->route('stops.index')->with('success', 'Stop updated successfully.');
    }

    /**
     * Remove the specified stop, unless it's still used by a route or trip.
     */
    public function destroy(Stop $stop): RedirectResponse
    {
        $inUse = RouteStop::where('stop_id', $stop->id)->exists()
            || TripStop::where('stop_id', $stop->id)->exists()
            || TripPassenger::where('pickup_stop_id', $stop->id)->orWhere('dropoff_stop_id', $stop->id)->exists();

        if ($inUse) {
            return back()->with('error', 'Cannot delete this stop: it is still used by one or more routes or trips.');
        }

        try {
            $stop->delete();

            return redirect()->route('stops.index')->with('success', 'Stop deleted successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to delete stop.');
        }
    }

    /**
     * Existing stops shown as map context when picking a location for the
     * create/edit form (MapStopPicker, same shape it expects elsewhere).
     */
    private function mapContextStops()
    {
        return Stop::selectable()
            ->select('id', 'name', 'description', 'latitude', 'longitude')
            ->orderBy('name')
            ->get();
    }

    /**
     * Get all stops for inline use (searchable selects, map picker).
     */
    public function apiIndex(): JsonResponse
    {
        $stops = Stop::selectable()
            ->select('id', 'name', 'description', 'latitude', 'longitude', 'zone_id')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $stops,
        ]);
    }

    /**
     * Create a stop inline from a searchable select or the map picker.
     */
    public function apiStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:stops,name',
            'description' => 'nullable|string|max:500',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'zone_id' => Zone::validationRule(),
        ]);

        // The zone select submits '' for "no zone chosen" — normalize to
        // null so it doesn't get written as an invalid empty string into
        // the zone_id foreign key column.
        $validated['zone_id'] = ($validated['zone_id'] ?? null) ?: null;

        $stop = Stop::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Stop created successfully',
            'data' => $stop,
        ], 201);
    }
}
