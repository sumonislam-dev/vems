<?php

namespace App\Http\Controllers;

use App\Models\AttendanceEvent;
use App\Models\AttendanceRecord;
use App\Models\Factory;
use App\Models\TripPassenger;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Date;
use Inertia\Inertia;

class AttendanceController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:capture-own-attendance', only: ['index', 'checkIn', 'checkOut', 'breakStart', 'breakEnd']),
            new Middleware('permission:manage-attendance', only: ['correctEvent']),
            new Middleware('permission:view-attendance-reports', only: ['reports']),
        ];
    }

    /**
     * §11.2: the authenticated user's own today status + event trail.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $record = $this->todaysRecord($user, createIfMissing: false);
        $record?->load(['events' => fn ($q) => $q->orderBy('event_time')]);

        $history = AttendanceRecord::where('user_id', $user->id)
            ->with([
                'events' => fn ($q) => $q->orderByDesc('event_time'),
                'tripPassengerEvent.trip:id,trip_number',
            ])
            ->orderByDesc('work_date')
            ->limit(30)
            ->get();

        return Inertia::render('attendance/index', [
            'attendanceMode' => $user->attendanceMode(),
            'today' => $record ? $this->presentRecord($record) : null,
            'history' => $history->map(fn (AttendanceRecord $r) => $this->presentRecord($r)),
            'factories' => Factory::select('id', 'name')->orderBy('name')->get(),
        ]);
    }

    /**
     * §3.1: one Check In action. Fans out to trip_passenger_events when a
     * pending or already-boarded trip pickup applies today; attendance-only
     * otherwise.
     */
    public function checkIn(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'gps_accuracy_meters' => 'nullable|numeric|min:0',
            'device_id' => 'nullable|string|max:255',
            'idempotency_key' => 'nullable|string|max:255',
        ]);

        $record = $this->todaysRecord($user, createIfMissing: true);

        if (in_array($record->status, ['checked_in', 'on_break'], true)) {
            return back()->with('error', 'You are already checked in today.');
        }

        $tripEvent = $this->resolveTripFanOut($user, $validated, 'check_in');

        try {
            $record->checkIn(array_merge($validated, [
                'ip_address' => $request->ip(),
                'source' => 'self_service',
                'actor_user_id' => $user->id,
                'related_trip_passenger_event_id' => $tripEvent?->id,
            ]));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($tripEvent) {
            $record->update([
                'used_transport' => true,
                'trip_passenger_event_id' => $tripEvent->id,
            ]);
        }

        return back()->with('success', 'Checked in successfully.');
    }

    /**
     * §3.1: one Check Out action, mirroring checkIn()'s fan-out against the
     * pending/boarded drop-off. §4.2.1: factory/location captured here.
     */
    public function checkOut(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'gps_accuracy_meters' => 'nullable|numeric|min:0',
            'device_id' => 'nullable|string|max:255',
            'idempotency_key' => 'nullable|string|max:255',
            'factory_id' => 'nullable|exists:factories,id',
            'location_name' => 'nullable|string|max:255',
        ]);

        $record = $this->todaysRecord($user, createIfMissing: false);

        if (! $record || ! in_array($record->status, ['checked_in', 'on_break'], true)) {
            return back()->with('error', 'You must be checked in before checking out.');
        }

        $tripEvent = $this->resolveTripFanOut($user, $validated, 'check_out');

        try {
            $record->checkOut(array_merge($validated, [
                'ip_address' => $request->ip(),
                'source' => 'self_service',
                'actor_user_id' => $user->id,
                'related_trip_passenger_event_id' => $tripEvent?->id,
            ]));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Checked out successfully.');
    }

    public function breakStart(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'idempotency_key' => 'nullable|string|max:255',
        ]);

        $record = $this->todaysRecord($user, createIfMissing: false);

        if (! $record) {
            return back()->with('error', 'You must be checked in before starting a break.');
        }

        try {
            $record->startBreak(array_merge($validated, [
                'ip_address' => $request->ip(),
                'source' => 'self_service',
                'actor_user_id' => $user->id,
            ]));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Break started.');
    }

    public function breakEnd(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'idempotency_key' => 'nullable|string|max:255',
        ]);

        $record = $this->todaysRecord($user, createIfMissing: false);

        if (! $record) {
            return back()->with('error', 'You must be checked in before ending a break.');
        }

        try {
            $record->endBreak(array_merge($validated, [
                'ip_address' => $request->ip(),
                'source' => 'self_service',
                'actor_user_id' => $user->id,
            ]));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Break ended.');
    }

    /**
     * §10/§11.2: admin/manager cross-employee report.
     */
    public function reports(Request $request)
    {
        $query = AttendanceRecord::with([
            'user:id,name,employee_id,department_id',
            'user.department:id,name',
            'tripPassengerEvent.trip:id,trip_number',
            'events' => fn ($q) => $q->where('event_type', 'check_out')->latest('event_time')->limit(1)->with('factory:id,name'),
        ]);

        if ($request->filled('date_from')) {
            $query->whereDate('work_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('work_date', '<=', $request->date_to);
        }

        if ($request->filled('department_id')) {
            $query->whereHas('user', fn ($q) => $q->where('department_id', $request->department_id));
        }

        if ($request->filled('source')) {
            $query->where('source', $request->source);
        }

        if ($request->filled('used_transport')) {
            $query->where('used_transport', $request->used_transport === 'yes');
        }

        if ($request->filled('has_anomaly')) {
            $query->where('has_anomaly', $request->has_anomaly === 'yes');
        }

        $sortColumn = in_array($request->get('sort'), ['work_date', 'check_in_at', 'check_out_at', 'net_minutes'])
            ? $request->get('sort')
            : 'work_date';
        $sortDirection = $request->get('direction', 'desc') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortColumn, $sortDirection);

        $records = $query->paginate($request->get('per_page', 20))->withQueryString();

        $stats = [
            'total' => AttendanceRecord::count(),
            'checked_in_today' => AttendanceRecord::whereDate('work_date', today()->toDateString())->whereIn('status', ['checked_in', 'on_break'])->count(),
            'anomalies' => AttendanceRecord::where('has_anomaly', true)->count(),
            'used_transport' => AttendanceRecord::where('used_transport', true)->count(),
        ];

        return Inertia::render('attendance/reports', [
            'records' => $records,
            'stats' => $stats,
            'queryParams' => $request->only(['date_from', 'date_to', 'department_id', 'source', 'used_transport', 'has_anomaly', 'sort', 'direction', 'per_page']),
        ]);
    }

    /**
     * §8: HR correction — void the disputed event, submit a replacement.
     */
    public function correctEvent(Request $request, AttendanceEvent $event)
    {
        $validated = $request->validate([
            'event_type' => 'required|in:check_in,check_out,break_start,break_end,correction',
            'event_time' => 'required|date',
            'void_reason' => 'required|string|max:1000',
        ]);

        $record = $event->attendanceRecord;

        $record->correctEvent($event, array_merge($validated, [
            'actor_user_id' => $request->user()?->id,
            'source' => 'manual',
        ]));

        return back()->with('success', 'Attendance event corrected successfully.');
    }

    /**
     * Looked up with whereDate() rather than an exact where() match: the
     * 'date' cast on AttendanceRecord::work_date stores a full datetime
     * string internally, so an exact-string match against toDateString()
     * would never find the row it just created.
     */
    protected function todaysRecord(User $user, bool $createIfMissing): ?AttendanceRecord
    {
        $today = Date::today()->toDateString();

        $record = AttendanceRecord::where('user_id', $user->id)
            ->whereDate('work_date', $today)
            ->first();

        if (! $record && $createIfMissing) {
            $record = AttendanceRecord::create([
                'user_id' => $user->id,
                'work_date' => $today,
                'status' => 'checked_out',
            ]);
        }

        return $record;
    }

    /**
     * §3.1: decide whether Check In/Check Out should also write to the trip
     * system. Three outcomes: create-both (pending trip action), link-only
     * (someone else already recorded it), or null (no trip today).
     */
    protected function resolveTripFanOut(User $user, array $gpsAttributes, string $direction)
    {
        $statuses = $direction === 'check_in'
            ? ['pending', 'no_show', 'boarded']
            : ['boarded', 'completed'];

        $tripPassenger = TripPassenger::where('user_id', $user->id)
            ->whereIn('status', $statuses)
            ->whereHas('trip', fn ($q) => $q->where('status', 'in_progress'))
            ->latest('id')
            ->first();

        if (! $tripPassenger) {
            return null;
        }

        $pendingStatuses = $direction === 'check_in' ? ['pending', 'no_show'] : ['boarded'];

        if (in_array($tripPassenger->status, $pendingStatuses, true)) {
            $eventAttributes = [
                'latitude' => $gpsAttributes['latitude'] ?? null,
                'longitude' => $gpsAttributes['longitude'] ?? null,
                'gps_accuracy_meters' => $gpsAttributes['gps_accuracy_meters'] ?? null,
                'device_id' => $gpsAttributes['device_id'] ?? null,
                'source' => 'attendance_self_service',
                'actor_user_id' => $user->id,
            ];

            return $direction === 'check_in'
                ? $tripPassenger->markAsBoarded($eventAttributes)
                : $tripPassenger->markAsDropped($eventAttributes);
        }

        // Already recorded by someone else (e.g. a driver) — link-only, no duplicate write.
        return $tripPassenger->passengerEvents()
            ->where('is_valid', true)
            ->where('event_type', $direction)
            ->latest('event_time')
            ->latest('id')
            ->first();
    }

    protected function presentRecord(AttendanceRecord $record): array
    {
        return [
            'id' => $record->id,
            'work_date' => $record->work_date->toDateString(),
            'status' => $record->status,
            'check_in_at' => $record->check_in_at?->toIso8601String(),
            'check_out_at' => $record->check_out_at?->toIso8601String(),
            'break_minutes' => $record->break_minutes,
            'gross_minutes' => $record->gross_minutes,
            'net_minutes' => $record->net_minutes,
            'overtime_minutes' => $record->overtime_minutes,
            'source' => $record->source,
            'used_transport' => $record->used_transport,
            'has_anomaly' => $record->has_anomaly,
            'trip' => $record->tripPassengerEvent?->trip
                ? ['id' => $record->tripPassengerEvent->trip->id, 'trip_number' => $record->tripPassengerEvent->trip->trip_number]
                : null,
            'events' => $record->relationLoaded('events')
                ? $record->events->map(fn (AttendanceEvent $e) => [
                    'id' => $e->id,
                    'event_type' => $e->event_type,
                    'event_time' => $e->event_time->toIso8601String(),
                    'latitude' => $e->latitude,
                    'longitude' => $e->longitude,
                    'factory_id' => $e->factory_id,
                    'location_name' => $e->location_name,
                    'is_valid' => $e->is_valid,
                    'void_reason' => $e->void_reason,
                ])
                : [],
        ];
    }
}
