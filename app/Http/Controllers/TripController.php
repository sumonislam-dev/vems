<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Factory;
use App\Models\Logistics;
use App\Models\Trip;
use App\Models\TripPassengerEvent;
use App\Models\TripRecurringGroup;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\Vehicle;
use App\Models\VehicleRoute;
use App\Models\Zone;
use App\Services\TripNotifier;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class TripController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view-trips', only: ['passengerEvents']),
            new Middleware('permission:view-trips|view-own-trips', only: ['index', 'show']),
            new Middleware('permission:create-trips', only: ['create', 'store', 'storeRecurring']),
            new Middleware('permission:edit-trips', only: ['edit', 'update']),
            new Middleware('permission:assign-vehicles', only: ['reassignVehicle']),
            new Middleware('permission:delete-trips', only: ['destroy']),
        ];
    }

    /**
     * Refuse to book a vehicle onto two overlapping trips the same day.
     * "Overlap" = same vehicle, same date, not cancelled/rejected, and the
     * scheduled time windows intersect. $excludeTripId lets an update ignore
     * the trip's own existing row.
     *
     * @throws ValidationException
     */
    private function assertVehicleAvailable(int $vehicleId, string $date, string $startTime, string $endTime, ?int $excludeTripId = null): void
    {
        $conflict = Trip::where('vehicle_id', $vehicleId)
            ->where('scheduled_date', $date)
            ->whereNotIn('status', ['cancelled', 'rejected'])
            ->when($excludeTripId, fn ($q) => $q->where('id', '!=', $excludeTripId))
            ->where('scheduled_start_time', '<', $endTime)
            ->where('scheduled_end_time', '>', $startTime)
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages([
                'vehicle_id' => "This vehicle is already booked on an overlapping trip on {$date}.",
            ]);
        }
    }

    /**
     * The vehicle a new trip request may carry: only people who assign
     * vehicles (Logistics) pick one up front; anyone else's request is saved
     * without a vehicle for Logistics to assign after approval.
     */
    private function requestedVehicle(array $validated): ?int
    {
        return auth()->user()->can('assign-vehicles') && ! empty($validated['vehicle_id'])
            ? (int) $validated['vehicle_id']
            : null;
    }

    /**
     * Refuse to seat more named passengers than the vehicle has capacity for.
     * Vehicles with no capacity on file are left unchecked. Department
     * headcount slots aren't counted here — their relationship to named
     * passengers isn't well-defined enough to validate safely.
     *
     * @throws ValidationException
     */
    private function assertVehicleCapacity(int $vehicleId, int $passengerCount): void
    {
        if ($passengerCount === 0) {
            return;
        }

        $capacity = Vehicle::whereKey($vehicleId)->value('capacity');

        if ($capacity !== null && $passengerCount > $capacity) {
            throw ValidationException::withMessages([
                'passengers' => "This vehicle seats {$capacity}, but {$passengerCount} passengers were selected.",
            ]);
        }
    }

    /**
     * Refuse to drop a passenger who already has check-in/out history.
     *
     * @param  array<int, array{user_id: int|string}>  $passengers
     *
     * @throws ValidationException
     */
    private function assertPassengersRemovable(Trip $trip, array $passengers): void
    {
        $keptUserIds = collect($passengers)->pluck('user_id')->map(fn ($id) => (int) $id)->all();

        $locked = $trip->passengers()
            ->whereNotIn('user_id', $keptUserIds)
            ->whereHas('passengerEvents')
            ->with('user:id,name')
            ->get();

        if ($locked->isNotEmpty()) {
            throw ValidationException::withMessages([
                'passengers' => 'Cannot remove passengers who already have check-in history: '.$locked->pluck('user.name')->join(', ').'.',
            ]);
        }
    }

    /**
     * Update passengers in place (matched by user), so existing rows and
     * their check-in/out history survive an edit.
     *
     * @param  array<int, array{user_id: int|string, pickup_stop_id?: int|null, dropoff_stop_id?: int|null}>  $passengers
     */
    private function syncPassengers(Trip $trip, array $passengers): void
    {
        $incoming = collect($passengers)->keyBy(fn ($p) => (int) $p['user_id']);

        $trip->passengers()->whereNotIn('user_id', $incoming->keys())->delete();

        foreach ($incoming as $userId => $passenger) {
            $trip->passengers()->updateOrCreate(
                ['user_id' => $userId],
                [
                    'pickup_stop_id' => $passenger['pickup_stop_id'] ?? null,
                    'dropoff_stop_id' => $passenger['dropoff_stop_id'] ?? null,
                ]
            );
        }
    }

    /**
     * Display a cross-trip passenger events log
     */
    public function passengerEvents(Request $request)
    {
        $query = TripPassengerEvent::with([
            'trip:id,trip_number,scheduled_date,status',
            'user:id,name,employee_id',
            'stop:id,name',
            'actor:id,name',
        ]);

        if ($request->filled('event_type')) {
            $query->where('event_type', $request->event_type);
        }

        if ($request->filled('validity')) {
            $query->where('is_valid', $request->validity === 'valid');
        }

        if ($request->filled('source')) {
            $query->where('source', $request->source);
        }

        if ($request->filled('trip_id')) {
            $query->where('trip_id', $request->trip_id);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('event_time', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('event_time', '<=', $request->date_to);
        }

        if ($request->filled('area')) {
            $query->where('area_name', 'like', '%'.$request->area.'%');
        }

        $sortColumn = in_array($request->get('sort'), ['event_time', 'event_type', 'source', 'area_name'])
            ? $request->get('sort')
            : 'event_time';
        $sortDirection = $request->get('direction', 'desc') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortColumn, $sortDirection);

        $events = $query->paginate($request->get('per_page', 20))->withQueryString();

        // Summary stats
        $stats = [
            'total' => TripPassengerEvent::count(),
            'check_in' => TripPassengerEvent::where('event_type', 'check_in')->count(),
            'check_out' => TripPassengerEvent::where('event_type', 'check_out')->count(),
            'no_show' => TripPassengerEvent::where('event_type', 'no_show')->count(),
            'voided' => TripPassengerEvent::where('is_valid', false)->count(),
            'corrections' => TripPassengerEvent::where('event_type', 'correction')->count(),
        ];

        // Unique sources for filter dropdown
        $sources = TripPassengerEvent::select('source')
            ->distinct()
            ->whereNotNull('source')
            ->pluck('source');

        // Recent trips for filter dropdown
        $trips = Trip::select('id', 'trip_number', 'scheduled_date')
            ->orderByDesc('scheduled_date')
            ->limit(100)
            ->get();

        return Inertia::render('trips/passenger-events', [
            'events' => $events,
            'stats' => $stats,
            'sources' => $sources,
            'trips' => $trips,
            'queryParams' => $request->only(['event_type', 'validity', 'source', 'trip_id', 'user_id', 'date_from', 'date_to', 'area', 'sort', 'direction', 'per_page']),
        ]);
    }

    /**
     * Display a listing of trips
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'sort' => 'nullable|in:scheduled_date,trip_number,status,priority,created_at',
            'direction' => 'nullable|in:asc,desc',
            'filters' => 'nullable|array',
            'filters.status' => 'nullable|array',
            'filters.status.*' => 'string|in:pending,approved,rejected,assigned,in_progress,completed,cancelled',
            'filters.priority' => 'nullable|array',
            'filters.priority.*' => 'string|in:low,medium,high,urgent',
            'filters.schedule_type' => 'nullable|array',
            'filters.schedule_type.*' => 'string|in:pick-and-drop,pick-up,drop-off,engineer,training,adhoc,reposition,inspection,complaints,CVV,Incident Inspection,officials,Assigned',
            'filters.trip_type' => 'nullable|array',
            'filters.trip_type.*' => 'string',
            'filters.zone_id' => 'nullable|array',
            'filters.zone_id.*' => 'string',
        ]);

        $query = Trip::with([
            'vehicle.driver',
            'vehicleRoute',
            'requester',
            'approver',
            'department',
            'zone',
            'passengers.user',
        ])->visibleTo($request->user());

        $filters = $validated['filters'] ?? [];

        // Trips aren't zone-access-restricted (see Trip::scopeVisibleTo()) —
        // a pending trip has no vehicle/zone yet, so hard-restricting would
        // hide exactly what a manager needs to assign. But default this
        // filter to the navbar zone switcher's selection when the request
        // didn't specify one itself, so the list matches the rest of the
        // zone-aware app unless the user explicitly filters differently.
        if (! array_key_exists('zone_id', $filters) && ($activeZoneId = session('active_zone_id'))) {
            $filters['zone_id'] = [(string) $activeZoneId];
        }

        // Apply search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('trip_number', 'like', "%{$search}%")
                    ->orWhereHas('vehicle', function ($vq) use ($search) {
                        $vq->where('registration_number', 'like', "%{$search}%");
                    })
                    ->orWhereHas('requester', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Apply filters
        if (! empty($filters)) {
            if (! empty($filters['status'])) {
                $query->whereIn('status', $filters['status']);
            }

            if (! empty($filters['priority'])) {
                $query->whereIn('priority', $filters['priority']);
            }

            if (! empty($filters['schedule_type'])) {
                $query->whereIn('schedule_type', $filters['schedule_type']);
            }

            if (! empty($filters['trip_type'])) {
                $query->whereIn('trip_type', $filters['trip_type']);
            }

            if (! empty($filters['zone_id'])) {
                $query->whereIn('zone_id', $filters['zone_id']);
            }
        }

        if ($request->filled('date_from')) {
            $query->whereDate('scheduled_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('scheduled_date', '<=', $request->date_to);
        }

        if ($request->filled('vehicle_id')) {
            $query->where('vehicle_id', $request->vehicle_id);
        }

        // Apply sorting
        $sortColumn = $request->get('sort', 'scheduled_date');
        $sortDirection = $request->get('direction', 'desc') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortColumn, $sortDirection);

        // Paginate
        $trips = $query->paginate($request->get('per_page', 15))
            ->withQueryString();

        // Per-row approve/reject: global permission, or a department head
        // deciding on their own departments' trips (see User::canApproveTrip())
        $user = $request->user();
        $headedDepartmentIds = $user->can('approve-department-trips')
            ? $user->headedDepartments()->pluck('id')->all()
            : [];
        $canApproveAll = $user->can('approve-trips');
        $canRejectAll = $user->can('reject-trips');
        $trips->through(function (Trip $trip) use ($headedDepartmentIds, $canApproveAll, $canRejectAll) {
            $headsDepartment = in_array($trip->department_id, $headedDepartmentIds, true);

            return $trip->setAttribute('can_approve', $canApproveAll || $headsDepartment)
                ->setAttribute('can_reject', $canRejectAll || $headsDepartment);
        });

        // Get stats with a single aggregate query
        $tripStats = Trip::visibleTo($request->user())->selectRaw(
            'COUNT(*) as total, '.
            'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending, '.
            'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as approved, '.
            'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as in_progress, '.
            'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed, '.
            'SUM(CASE WHEN DATE(scheduled_date) = ? THEN 1 ELSE 0 END) as today',
            ['pending', 'approved', 'in_progress', 'completed', today()->toDateString()]
        )->first();

        $stats = [
            'total' => (int) ($tripStats->total ?? 0),
            'pending' => (int) ($tripStats->pending ?? 0),
            'approved' => (int) ($tripStats->approved ?? 0),
            'in_progress' => (int) ($tripStats->in_progress ?? 0),
            'completed' => (int) ($tripStats->completed ?? 0),
            'today' => (int) ($tripStats->today ?? 0),
        ];

        return Inertia::render('trips/index', [
            'trips' => $trips,
            'stats' => $stats,
            'filterOptions' => [
                'zones' => Zone::selectableBy()->map(fn ($z) => [
                    'label' => $z->name,
                    'value' => (string) $z->id,
                ]),
            ],
            'queryParams' => [
                ...$request->only(['search', 'date_from', 'date_to', 'vehicle_id', 'sort', 'direction', 'per_page']),
                'filters' => $filters,
            ],
        ]);
    }

    /**
     * Show the form for creating a new trip
     */
    public function create()
    {
        $vehicles = Vehicle::with('driver:id,name')
            ->where('is_active', true)
            ->select(['id', 'registration_number', 'brand', 'model', 'driver_id'])
            ->orderBy('registration_number')
            ->get()
            ->map(fn ($v) => [
                'value' => $v->id,
                'label' => "{$v->registration_number} - {$v->brand} {$v->model}",
                'driver' => $v->driver ? $v->driver->name : 'No driver assigned',
            ]);

        $routes = VehicleRoute::with('routeStops.stop')
            ->get()
            ->map(fn ($r) => [
                'value' => $r->id,
                'label' => $r->name,
                'stops' => $r->routeStops->map(fn ($rs) => [
                    'id' => $rs->stop->id,
                    'name' => $rs->stop->name,
                    'order' => $rs->stop_order,
                ]),
            ]);

        $departments = Department::where('is_active', true)
            ->get()
            ->map(fn ($d) => ['value' => $d->id, 'label' => $d->name]);

        $employees = User::with('department:id,name')
            ->where('status', 'active')
            ->select(['id', 'name', 'employee_id', 'department_id'])
            ->orderBy('name')
            ->get()
            ->map(fn ($u) => [
                'value' => $u->id,
                'label' => $u->name,
                'employee_id' => $u->employee_id,
                'department' => $u->department?->name,
            ]);

        $userGroups = UserGroup::with('users')
            ->where('status', 'active')
            ->orderBy('name')
            ->get()
            ->map(fn ($g) => [
                'value' => $g->id,
                'label' => $g->name,
                'user_ids' => $g->users->pluck('id'),
            ]);

        return Inertia::render('trips/create', [
            'vehicles' => $vehicles,
            'routes' => $routes,
            'departments' => $departments,
            'employees' => $employees,
            'userGroups' => $userGroups,
            'factories' => Factory::where('status', 'active')
                ->orderBy('name')
                ->get()
                ->map(fn ($f) => [
                    'value' => $f->id,
                    'label' => "({$f->account_id}) {$f->name}",
                    'city' => $f->city,
                ]),
            'logistics' => Logistics::active()
                ->orderBy('name')
                ->get()
                ->map(fn ($l) => [
                    'value' => $l->id,
                    'label' => $l->name,
                ]),
        ]);
    }

    /**
     * Store a newly created trip
     */
    public function store(Request $request, TripNotifier $notifier)
    {
        $validated = $request->validate([
            'vehicle_route_id' => 'nullable|exists:vehicle_routes,id',
            'vehicle_id' => 'nullable|exists:vehicles,id',
            'department_id' => 'nullable|exists:departments,id',
            'trip_type' => 'nullable|in:inspection,pick-up,drop-off,training,complaints,CVV,Incident Inspection,officials,Assigned',
            'team_number' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'remarks' => 'nullable|string',
            'priority' => 'required|in:low,medium,high,urgent',
            'scheduled_date' => 'required|date',
            'scheduled_start_time' => 'required|date_format:H:i',
            'scheduled_end_time' => 'required|date_format:H:i|after:scheduled_start_time',
            'start_location' => 'nullable|string|max:255',
            'end_location' => 'nullable|string|max:255',
            'is_return' => 'boolean',
            'is_recurring' => 'boolean',
            'recurring_start_date' => 'nullable|date|required_if:is_recurring,true',
            'recurring_end_date' => 'nullable|date|required_if:is_recurring,true|after_or_equal:recurring_start_date',
            'group_name' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'passengers' => 'nullable|array',
            'passengers.*.user_id' => 'required|exists:users,id',
            'passengers.*.pickup_stop_id' => 'nullable|exists:stops,id',
            'passengers.*.dropoff_stop_id' => 'nullable|exists:stops,id',
            'factory_ids' => 'nullable|array',
            'factory_ids.*' => 'exists:factories,id',
            'department_slots' => 'nullable|array',
            'department_slots.*.department_id' => 'required|exists:departments,id',
            'department_slots.*.count' => 'required|integer|min:1|max:999',
            'logistics_ids' => 'nullable|array',
            'logistics_ids.*' => 'exists:logistics,id',
        ]);

        $validated['vehicle_id'] = $this->requestedVehicle($validated);
        if ($validated['vehicle_id']) {
            $this->assertVehicleAvailable(
                $validated['vehicle_id'],
                $validated['scheduled_date'],
                $validated['scheduled_start_time'],
                $validated['scheduled_end_time']
            );
            $this->assertVehicleCapacity($validated['vehicle_id'], count($validated['passengers'] ?? []));
        }

        DB::beginTransaction();
        try {
            // Create trip
            $tripData = collect($validated)
                ->except(['passengers', 'factory_ids', 'department_slots', 'logistics_ids'])
                ->merge(['requested_by' => auth()->id(), 'status' => 'pending'])
                ->toArray();

            $trip = Trip::create($tripData);

            // Add passengers if provided
            if (! empty($validated['passengers'])) {
                foreach ($validated['passengers'] as $passenger) {
                    $trip->passengers()->create($passenger);
                }
            }

            // Attach factories
            if (! empty($validated['factory_ids'])) {
                $trip->factories()->sync($validated['factory_ids']);
            }

            // Attach department headcount slots
            if (! empty($validated['department_slots'])) {
                $syncData = collect($validated['department_slots'])
                    ->keyBy('department_id')
                    ->map(fn ($slot) => ['count' => $slot['count']])
                    ->toArray();
                $trip->departments()->sync($syncData);
            }

            // Attach logistics
            if (! empty($validated['logistics_ids'])) {
                $trip->logistics()->sync($validated['logistics_ids']);
            }

            DB::commit();

            $notifier->requested($trip);

            return redirect()
                ->route('trips.show', $trip)
                ->with('success', 'Trip created successfully! Awaiting approval.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create trip.', ['error' => $e->getMessage(), 'user_id' => auth()->id()]);

            return back()
                ->withErrors(['error' => 'Something went wrong while creating the trip. Please try again.'])
                ->withInput();
        }
    }

    /**
     * Store multiple recurring trips
     */
    public function storeRecurring(Request $request, TripNotifier $notifier)
    {
        $validated = $request->validate([
            'vehicle_route_id' => 'nullable|exists:vehicle_routes,id',
            'vehicle_id' => 'nullable|exists:vehicles,id',
            'department_id' => 'nullable|exists:departments,id',
            'trip_type' => 'nullable|in:inspection,pick-up,drop-off,training,complaints,CVV,Incident Inspection,officials,Assigned',
            'team_number' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'remarks' => 'nullable|string',
            'priority' => 'required|in:low,medium,high,urgent',
            'recurring_start_date' => 'required|date',
            'recurring_end_date' => 'required|date|after_or_equal:recurring_start_date',
            'scheduled_start_time' => 'required|date_format:H:i',
            'scheduled_end_time' => 'required|date_format:H:i|after:scheduled_start_time',
            'start_location' => 'nullable|string|max:255',
            'end_location' => 'nullable|string|max:255',
            'is_return' => 'boolean',
            'group_name' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'passengers' => 'nullable|array',
            'passengers.*.user_id' => 'required|exists:users,id',
            'factory_ids' => 'nullable|array',
            'factory_ids.*' => 'exists:factories,id',
            'department_slots' => 'nullable|array',
            'department_slots.*.department_id' => 'required|exists:departments,id',
            'department_slots.*.count' => 'required|integer|min:1|max:999',
            'logistics_ids' => 'nullable|array',
            'logistics_ids.*' => 'exists:logistics,id',
        ]);

        $start = Carbon::parse($validated['recurring_start_date']);
        $end = Carbon::parse($validated['recurring_end_date']);
        $dates = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $dates[] = $d->toDateString();
        }

        $validated['vehicle_id'] = $this->requestedVehicle($validated);
        if ($validated['vehicle_id']) {
            $this->assertVehicleCapacity($validated['vehicle_id'], count($validated['passengers'] ?? []));
            foreach ($dates as $date) {
                $this->assertVehicleAvailable(
                    $validated['vehicle_id'],
                    $date,
                    $validated['scheduled_start_time'],
                    $validated['scheduled_end_time']
                );
            }
        }

        DB::beginTransaction();
        try {
            // Create recurring group
            $group = TripRecurringGroup::create([
                'group_name' => $validated['group_name'] ?? null,
                'created_by' => auth()->id(),
                'start_date' => $validated['recurring_start_date'],
                'end_date' => $validated['recurring_end_date'],
                'total_trips' => count($dates),
                'notes' => $validated['notes'] ?? null,
            ]);

            $tripBase = collect($validated)->except([
                'passengers', 'factory_ids', 'department_slots', 'logistics_ids',
                'recurring_start_date', 'recurring_end_date', 'group_name',
            ])->merge([
                'requested_by' => auth()->id(),
                'status' => 'pending',
                'is_recurring' => true,
                'recurring_group_id' => $group->id,
                'recurring_start_date' => $validated['recurring_start_date'],
                'recurring_end_date' => $validated['recurring_end_date'],
            ])->toArray();

            $firstTrip = null;
            foreach ($dates as $date) {
                $tripData = array_merge($tripBase, ['scheduled_date' => $date]);
                $trip = Trip::create($tripData);

                if ($firstTrip === null) {
                    $firstTrip = $trip;
                }

                if (! empty($validated['passengers'])) {
                    foreach ($validated['passengers'] as $passenger) {
                        $trip->passengers()->create($passenger);
                    }
                }
                if (! empty($validated['factory_ids'])) {
                    $trip->factories()->sync($validated['factory_ids']);
                }
                if (! empty($validated['department_slots'])) {
                    $syncData = collect($validated['department_slots'])
                        ->keyBy('department_id')
                        ->map(fn ($slot) => ['count' => $slot['count']])
                        ->toArray();
                    $trip->departments()->sync($syncData);
                }
                if (! empty($validated['logistics_ids'])) {
                    $trip->logistics()->sync($validated['logistics_ids']);
                }
            }

            DB::commit();

            $notifier->requested($firstTrip, count($dates));

            return redirect()
                ->route('trips.index')
                ->with('success', count($dates).' recurring trips created successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create recurring trips.', ['error' => $e->getMessage(), 'user_id' => auth()->id()]);

            return back()
                ->withErrors(['error' => 'Something went wrong while creating the recurring trips. Please try again.'])
                ->withInput();
        }
    }

    /**
     * Display the specified trip
     */
    public function show(Trip $trip)
    {
        $this->authorizeTripView(auth()->user(), $trip);

        $trip->load([
            'vehicle.driver',
            'vehicleRoute.routeStops.stop',
            'requester',
            'approver',
            'cancelledByUser',
            'department',
            'passengers.user',
            'passengers.pickupStop',
            'passengers.dropoffStop',
            'passengers.passengerEvents.actor',
            'passengers.passengerEvents.stop',
            'vehicleAssignments.vehicle',
            'vehicleAssignments.assignedByUser',
            'logistics',
            'factories',
            'departments',
            'feedbackEntries' => fn ($query) => $query->visibleTo(auth()->user())->latest(),
        ]);

        $vehicleAssignments = $trip->vehicleAssignments()
            ->with(['vehicle', 'assignedByUser'])
            ->orderByDesc('assigned_at')
            ->get();

        $routeAssignments = $trip->routeAssignments()
            ->with(['vehicleRoute', 'assignedByUser'])
            ->orderByDesc('assigned_at')
            ->get();

        $auditLogs = $trip->auditLogs()
            ->with('user')
            ->latest()
            ->get();

        // Get active vehicles for reassignment option
        $availableVehicles = Vehicle::where('is_active', true)
            ->with('driver:id,name')
            ->select(['id', 'registration_number', 'brand', 'model', 'driver_id'])
            ->orderBy('registration_number')
            ->get()
            ->map(fn ($v) => [
                'value' => $v->id,
                'label' => "{$v->registration_number} - {$v->brand} {$v->model}",
                'driver' => $v->driver ? $v->driver->name : 'No driver assigned',
            ]);

        return Inertia::render('trips/show', [
            'trip' => $trip,
            'vehicleAssignments' => $vehicleAssignments,
            'routeAssignments' => $routeAssignments,
            'auditLogs' => $auditLogs,
            'availableVehicles' => $availableVehicles,
            'canApprove' => auth()->user()->canApproveTrip($trip),
            'canReject' => auth()->user()->canRejectTrip($trip),
        ]);
    }

    /**
     * Show the form for editing the specified trip
     */
    public function edit(Trip $trip)
    {
        // Can only edit pending or approved trips
        if (! in_array($trip->status, ['pending', 'approved'])) {
            return back()->with('error', 'Cannot edit trip in current status.');
        }

        $trip->load(['passengers', 'logistics', 'factories', 'departments']);

        $vehicles = Vehicle::with('driver:id,name')
            ->where('is_active', true)
            ->select(['id', 'registration_number', 'brand', 'model', 'driver_id'])
            ->orderBy('registration_number')
            ->get()
            ->map(fn ($v) => [
                'value' => $v->id,
                'label' => "{$v->registration_number} - {$v->brand} {$v->model}",
                'driver' => $v->driver ? $v->driver->name : 'No driver assigned',
            ]);

        $routes = VehicleRoute::with('routeStops.stop')
            ->get()
            ->map(fn ($r) => [
                'value' => $r->id,
                'label' => $r->name,
                'stops' => $r->routeStops->map(fn ($rs) => [
                    'id' => $rs->stop->id,
                    'name' => $rs->stop->name,
                    'order' => $rs->stop_order,
                ]),
            ]);

        $departments = Department::where('is_active', true)
            ->get()
            ->map(fn ($d) => ['value' => $d->id, 'label' => $d->name]);

        $employees = User::with('department:id,name')
            ->where('status', 'active')
            ->select(['id', 'name', 'employee_id', 'department_id'])
            ->orderBy('name')
            ->get()
            ->map(fn ($u) => [
                'value' => $u->id,
                'label' => $u->name,
                'employee_id' => $u->employee_id,
                'department' => $u->department?->name,
            ]);

        // Active factories, plus any already on this trip (even if since disabled
        // or deleted) so saving the form doesn't silently drop them
        $currentFactoryIds = $trip->factories()->pluck('factories.id');
        $factories = Factory::withTrashed()
            ->where(fn ($q) => $q->where(fn ($q) => $q->where('status', 'active')->whereNull('deleted_at'))
                ->orWhereIn('id', $currentFactoryIds))
            ->orderBy('name')
            ->get()
            ->map(fn ($f) => [
                'value' => $f->id,
                'label' => "({$f->account_id}) {$f->name}",
            ]);

        $logistics = Logistics::active()
            ->orderBy('name')
            ->get()
            ->map(fn ($l) => [
                'value' => $l->id,
                'label' => $l->name,
            ]);

        return Inertia::render('trips/edit', [
            'trip' => $trip,
            'vehicles' => $vehicles,
            'routes' => $routes,
            'departments' => $departments,
            'employees' => $employees,
            'factories' => $factories,
            'logistics' => $logistics,
        ]);
    }

    /**
     * Update the specified trip
     */
    public function update(Request $request, Trip $trip)
    {
        // Can only update pending or approved trips
        if (! in_array($trip->status, ['pending', 'approved'])) {
            return back()->with('error', 'Cannot update trip in current status.');
        }

        $validated = $request->validate([
            'vehicle_route_id' => 'nullable|exists:vehicle_routes,id',
            'vehicle_id' => 'nullable|exists:vehicles,id',
            'department_id' => 'nullable|exists:departments,id',
            'trip_type' => 'nullable|in:inspection,pick-up,drop-off,training,complaints,CVV,Incident Inspection,officials,Assigned',
            'team_number' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'remarks' => 'nullable|string',
            'priority' => 'required|in:low,medium,high,urgent',
            'scheduled_date' => 'required|date',
            'scheduled_start_time' => 'required|date_format:H:i',
            'scheduled_end_time' => 'required|date_format:H:i|after:scheduled_start_time',
            'start_location' => 'nullable|string|max:255',
            'end_location' => 'nullable|string|max:255',
            'is_return' => 'boolean',
            'notes' => 'nullable|string',
            'passengers' => 'nullable|array',
            'passengers.*.user_id' => 'required|exists:users,id',
            'passengers.*.pickup_stop_id' => 'nullable|exists:stops,id',
            'passengers.*.dropoff_stop_id' => 'nullable|exists:stops,id',
            'factory_ids' => 'nullable|array',
            'factory_ids.*' => 'exists:factories,id',
            'department_slots' => 'nullable|array',
            'department_slots.*.department_id' => 'required|exists:departments,id',
            'department_slots.*.count' => 'required|integer|min:1|max:999',
            'logistics_ids' => 'nullable|array',
            'logistics_ids.*' => 'exists:logistics,id',
        ]);

        // Only vehicle assigners (Logistics) may change the vehicle
        if (! auth()->user()->can('assign-vehicles')) {
            $validated['vehicle_id'] = $trip->vehicle_id;
        }

        if (! empty($validated['vehicle_id'])) {
            $this->assertVehicleAvailable(
                $validated['vehicle_id'],
                $validated['scheduled_date'],
                $validated['scheduled_start_time'],
                $validated['scheduled_end_time'],
                $trip->id
            );
            $passengerCount = array_key_exists('passengers', $validated)
                ? count($validated['passengers'] ?? [])
                : $trip->passengers()->count();
            $this->assertVehicleCapacity($validated['vehicle_id'], $passengerCount);
        }

        if (isset($validated['passengers'])) {
            $this->assertPassengersRemovable($trip, $validated['passengers']);
        }

        DB::beginTransaction();
        try {
            $tripData = collect($validated)
                ->except(['passengers', 'factory_ids', 'department_slots', 'logistics_ids'])
                ->toArray();
            $trip->update($tripData);

            if (isset($validated['passengers'])) {
                $this->syncPassengers($trip, $validated['passengers']);
            }

            // Sync factories
            $trip->factories()->sync($validated['factory_ids'] ?? []);

            // Sync department headcount slots
            if (! empty($validated['department_slots'])) {
                $syncData = collect($validated['department_slots'])
                    ->keyBy('department_id')
                    ->map(fn ($slot) => ['count' => $slot['count']])
                    ->toArray();
                $trip->departments()->sync($syncData);
            } else {
                $trip->departments()->detach();
            }

            // Sync logistics
            $trip->logistics()->sync($validated['logistics_ids'] ?? []);

            DB::commit();

            return redirect()
                ->route('trips.show', $trip)
                ->with('success', 'Trip updated successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update trip.', ['error' => $e->getMessage(), 'trip_id' => $trip->id, 'user_id' => auth()->id()]);

            return back()
                ->withErrors(['error' => 'Something went wrong while updating the trip. Please try again.'])
                ->withInput();
        }
    }

    /**
     * Approve a trip
     */
    /**
     * Remove the specified trip
     */
    public function destroy(Trip $trip)
    {
        // Can only delete pending trips
        if ($trip->status !== 'pending') {
            return back()->with('error', 'Only pending trips can be deleted.');
        }

        $trip->delete();

        // Keep the recurring group's count in sync with its surviving trips —
        // it was set once at creation and otherwise never updated.
        if ($trip->recurring_group_id) {
            TripRecurringGroup::whereKey($trip->recurring_group_id)->decrement('total_trips');
        }

        return redirect()
            ->route('trips.index')
            ->with('success', 'Trip deleted successfully.');
    }

    /**
     * Assign a vehicle to a trip requested without one, or reassign it
     * (damaged/breakdown scenarios). A first assignment needs no reason.
     */
    public function reassignVehicle(Request $request, Trip $trip, TripNotifier $notifier)
    {
        // Can reassign vehicle during any active status
        if (! in_array($trip->status, ['pending', 'approved', 'assigned', 'in_progress'])) {
            return back()->with('error', 'Cannot reassign vehicle for completed or cancelled trips.');
        }

        $isFirstAssignment = $trip->vehicle_id === null;
        $previousVehicleId = $trip->vehicle_id;

        $validated = $request->validate([
            'vehicle_id' => 'required|exists:vehicles,id',
            'reason' => [$isFirstAssignment ? 'nullable' : 'required', 'in:damaged,maintenance,breakdown,replacement,other'],
            'notes' => 'nullable|string|max:500',
        ]);
        $validated['reason'] ??= 'initial';

        $this->assertVehicleAvailable(
            $validated['vehicle_id'],
            $trip->scheduled_date->toDateString(),
            $trip->scheduled_start_time,
            $trip->scheduled_end_time,
            $trip->id
        );
        $this->assertVehicleCapacity($validated['vehicle_id'], $trip->passengers()->count());

        DB::transaction(function () use ($trip, $validated) {
            // Lock the trip so a concurrent reassignment can't race the
            // close-old/create-new "current" assignment rows below.
            $trip = Trip::whereKey($trip->id)->lockForUpdate()->firstOrFail();

            // Read by TripObserver::updating() so the new assignment row is
            // created with the real reason/notes in one write, instead of a
            // second, separate (and racy) patch afterwards.
            $trip->pendingVehicleReassignReason = $validated['reason'];
            $trip->pendingVehicleReassignNotes = $validated['notes'] ?? null;

            $trip->update([
                'vehicle_id' => $validated['vehicle_id'],
            ]);
        });

        if ((int) $validated['vehicle_id'] !== $previousVehicleId) {
            $notifier->vehicleAssigned($trip->fresh());
        }

        return back()->with('success', $isFirstAssignment ? 'Vehicle assigned.' : 'Vehicle reassigned successfully!');
    }

    /**
     * A user with only view-own-trips may view a trip they requested, ride as a
     * passenger on, or (as the assigned driver) are driving. Full view-trips
     * bypasses this check entirely.
     */
    private function authorizeTripView(User $user, Trip $trip): void
    {
        if ($user->can('view-trips')) {
            return;
        }

        $isRequester = $trip->requested_by === $user->id;
        $isPassenger = $trip->passengers()->where('user_id', $user->id)->exists();
        $isDriver = $trip->vehicle && $trip->vehicle->driver_id === $user->id;

        abort_unless($isRequester || $isPassenger || $isDriver || $user->headsDepartmentOf($trip), 403);
    }
}
