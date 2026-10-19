<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Logistics;
use App\Models\Zone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class LogisticsController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-logistics', only: ['index', 'show']),
            new Middleware('permission:create-logistics', only: ['create', 'store']),
            new Middleware('permission:edit-logistics', only: ['edit', 'update', 'toggleLock']),
            new Middleware('permission:delete-logistics', only: ['destroy']),
        ];
    }

    /**
     * Display a listing of logistics.
     */
    public function index(Request $request): Response
    {
        $query = Logistics::with(['department:id,name', 'creator:id,name', 'zone:id,name']);

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%');
            });
        }

        // Department filter
        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->status === 'active');
        }

        $logistics = $query->latest()
            ->paginate(15)
            ->through(fn ($logistic) => [
                'id' => $logistic->id,
                'name' => $logistic->name,
                'description' => $logistic->description,
                'status' => $logistic->status ? 'active' : 'inactive',
                'department' => $logistic->department ? [
                    'id' => $logistic->department->id,
                    'name' => $logistic->department->name,
                ] : null,
                'creator' => $logistic->creator ? [
                    'id' => $logistic->creator->id,
                    'name' => $logistic->creator->name,
                ] : null,
                'zone' => $logistic->zone ? ['id' => $logistic->zone->id, 'name' => $logistic->zone->name] : null,
                'created_at' => $logistic->created_at->format('Y-m-d H:i'),
            ]);

        $departments = Department::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $logisticsStats = Logistics::selectRaw(
            'COUNT(*) as total, '.
            'SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as active, '.
            'SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) as inactive'
        )->first();

        return Inertia::render('logistics/index', [
            'logistics' => $logistics,
            'departments' => $departments,
            'filters' => $request->only(['search', 'department_id', 'status']),
            'stats' => [
                'total' => (int) ($logisticsStats->total ?? 0),
                'active' => (int) ($logisticsStats->active ?? 0),
                'inactive' => (int) ($logisticsStats->inactive ?? 0),
            ],
        ]);
    }

    /**
     * Show the form for creating a new logistics.
     */
    public function create(): Response
    {
        $departments = Department::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('logistics/create', [
            'departments' => $departments,
            'zones' => Zone::selectableBy(),
            'defaultZoneId' => auth()->user()->zone_id,
        ]);
    }

    /**
     * Store a newly created logistics.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'department_id' => ['required', 'exists:departments,id'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive'],
            'zone_id' => Zone::validationRule(),
        ]);

        $validated['status'] = $validated['status'] === 'active';
        $validated['created_by'] = auth()->id();

        try {
            Logistics::create($validated);

            return redirect()->route('logistics.index')
                ->with('success', 'Logistics created successfully.');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Failed to create logistics.');
        }
    }

    /**
     * Display the specified logistics.
     */
    public function show(Logistics $logistic): Response
    {
        $logistic->load(['department', 'creator', 'zone']);

        return Inertia::render('logistics/show', [
            'logistic' => [
                'id' => $logistic->id,
                'name' => $logistic->name,
                'description' => $logistic->description,
                'status' => $logistic->status ? 'active' : 'inactive',
                'department' => $logistic->department ? [
                    'id' => $logistic->department->id,
                    'name' => $logistic->department->name,
                    'code' => $logistic->department->code,
                ] : null,
                'creator' => $logistic->creator ? [
                    'id' => $logistic->creator->id,
                    'name' => $logistic->creator->name,
                ] : null,
                'zone' => $logistic->zone ? ['id' => $logistic->zone->id, 'name' => $logistic->zone->name] : null,
                'created_at' => $logistic->created_at->format('Y-m-d H:i'),
                'updated_at' => $logistic->updated_at->format('Y-m-d H:i'),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified logistics.
     */
    public function edit(Logistics $logistic): Response
    {
        $departments = Department::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('logistics/edit', [
            'logistic' => [
                'id' => $logistic->id,
                'name' => $logistic->name,
                'department_id' => $logistic->department_id,
                'description' => $logistic->description,
                'status' => $logistic->status ? 'active' : 'inactive',
                'zone_id' => $logistic->zone_id,
            ],
            'departments' => $departments,
            'zones' => Zone::selectableBy(auth()->user(), $logistic->zone_id),
        ]);
    }

    /**
     * Update the specified logistics.
     */
    public function update(Request $request, Logistics $logistic): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'department_id' => ['required', 'exists:departments,id'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive'],
            'zone_id' => Zone::validationRule(),
        ]);

        $validated['status'] = $validated['status'] === 'active';

        try {
            $logistic->update($validated);

            return redirect()->route('logistics.index')
                ->with('success', 'Logistics updated successfully.');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Failed to update logistics.');
        }
    }

    /**
     * Remove the specified logistics.
     */
    public function destroy(Logistics $logistic): RedirectResponse
    {
        try {
            $logistic->delete();

            return redirect()->route('logistics.index')
                ->with('success', 'Logistics deleted successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to delete logistics.');
        }
    }

    /**
     * Toggle status (active/inactive).
     */
    public function toggleLock(Logistics $logistic): RedirectResponse
    {
        $logistic->update([
            'status' => ! $logistic->status,
        ]);

        $status = $logistic->status ? 'activated' : 'deactivated';

        return redirect()->back()
            ->with('success', "Logistics {$status} successfully.");
    }
}
