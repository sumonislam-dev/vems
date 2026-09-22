<?php

namespace App\Http\Controllers;

use App\Exports\AttendanceReportExport;
use App\Jobs\ResolveAttendanceEventLocation;
use App\Models\AttendanceEvent;
use App\Models\AttendanceRecord;
use App\Models\Factory;
use App\Models\TripPassenger;
use App\Models\User;
use App\Services\LocationResolver;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Date;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:capture-own-attendance', only: ['index', 'checkIn', 'checkOut', 'breakStart', 'breakEnd']),
            new Middleware('permission:manage-attendance', only: ['correctEvent']),
            new Middleware('permission:view-attendance-reports', only: ['reports']),
            new Middleware('permission:export-attendance-reports', only: ['exportReports']),
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
            $event = $record->checkIn(array_merge($validated, [
                'ip_address' => $request->ip(),
                'source' => 'self_service',
                'actor_user_id' => $user->id,
                'related_trip_passenger_event_id' => $tripEvent?->id,
            ]));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->attachResolvedLocation($event);
        $this->flagIfOutsideGeofence($record, $event);

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
            $event = $record->checkOut(array_merge($validated, [
                'ip_address' => $request->ip(),
                'source' => 'self_service',
                'actor_user_id' => $user->id,
                'related_trip_passenger_event_id' => $tripEvent?->id,
            ]));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->attachResolvedLocation($event);
        $this->flagIfOutsideGeofence($record, $event);

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
            $event = $record->startBreak(array_merge($validated, [
                'ip_address' => $request->ip(),
                'source' => 'self_service',
                'actor_user_id' => $user->id,
            ]));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->attachResolvedLocation($event);

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
            $event = $record->endBreak(array_merge($validated, [
                'ip_address' => $request->ip(),
                'source' => 'self_service',
                'actor_user_id' => $user->id,
            ]));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->attachResolvedLocation($event);

        return back()->with('success', 'Break ended.');
    }

    /**
     * §10/§11.2: admin/manager cross-employee report.
     */
    public function reports(Request $request)
    {
        $query = $this->reportsQuery($request);

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
            'users' => User::active()->orderBy('name')->get(['id', 'name', 'employee_id']),
            'queryParams' => $request->only(['date_from', 'date_to', 'department_id', 'user_id', 'source', 'used_transport', 'has_anomaly', 'sort', 'direction', 'per_page']),
        ]);
    }

    /**
     * Same filters as reports(), shared with exportReports() so the
     * downloaded file always matches what's on screen.
     */
    protected function reportsQuery(Request $request)
    {
        $query = AttendanceRecord::with([
            'user:id,name,employee_id,department_id',
            'user.department:id,name',
            'tripPassengerEvent.trip:id,trip_number,trip_type,driver_id',
            'tripPassengerEvent.trip.driver:id,name',
            'events' => fn ($q) => $q->where('is_valid', true)->orderBy('event_time')->with('factory:id,name,address'),
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

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
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

        return $query;
    }

    public function exportReports(Request $request)
    {
        $query = $this->reportsQuery($request)->orderBy('work_date');
        $format = in_array($request->get('format'), ['csv', 'excel', 'pdf']) ? $request->get('format') : 'csv';
        $timestamp = now()->format('Ymd_His');

        return match ($format) {
            'excel' => Excel::download(new AttendanceReportExport(clone $query), "attendance-report-{$timestamp}.xlsx"),
            'pdf' => $this->exportPdf(clone $query, $timestamp),
            default => $this->exportCsv(clone $query, $timestamp),
        };
    }

    private function exportCsv($query, string $timestamp): StreamedResponse
    {
        $fileName = "attendance-report-{$timestamp}.csv";

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, AttendanceReportExport::headingList());

            $query->chunk(200, function ($records) use ($handle) {
                foreach ($records as $record) {
                    fputcsv($handle, AttendanceReportExport::mapRow($record));
                }
            });

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv',
        ]);
    }

    private function exportPdf($query, string $timestamp)
    {
        $records = $query->get();

        $pdf = Pdf::loadView('exports.attendance-report-pdf', [
            'headings' => AttendanceReportExport::headingList(),
            'rows' => $records->map(fn (AttendanceRecord $record) => AttendanceReportExport::mapRow($record)),
            'exportDate' => now()->format('F j, Y g:i A'),
            'totalRecords' => $records->count(),
        ]);

        $pdf->setPaper('A4', 'landscape')
            ->setOptions([
                'defaultFont' => 'Arial',
                'isRemoteEnabled' => true,
                'isHtml5ParserEnabled' => true,
                'chroot' => public_path(),
            ]);

        return $pdf->download("attendance-report-{$timestamp}.pdf");
    }

    /**
     * Fill in factory_id/location_name when neither was already captured —
     * never overrides a manually chosen checkout factory. Only the free,
     * local match runs inline; an unmatched point is resolved by Nominatim
     * on the queue instead, so this never slows down the check-in/break
     * action itself.
     */
    protected function attachResolvedLocation(?AttendanceEvent $event): void
    {
        if (! $event || $event->factory_id || $event->location_name) {
            return;
        }

        if ($event->latitude === null || $event->longitude === null) {
            return;
        }

        $match = app(LocationResolver::class)->matchKnown((float) $event->latitude, (float) $event->longitude);

        if ($match['factory_id'] || $match['location_name']) {
            $event->update([
                'location_name' => $match['location_name'],
                'factory_id' => $match['factory_id'],
            ]);
        } else {
            ResolveAttendanceEventLocation::dispatch($event->id);
        }
    }

    /**
     * A check-in or check-out far from every known factory/stop gets
     * flagged for HR review rather than blocked — GPS drift and a
     * legitimate off-site event both look identical from distance alone.
     * Never second-guesses a checkout where the user (or matchKnown())
     * already resolved a factory — only an event with nothing but raw
     * GPS behind it gets checked.
     */
    protected function flagIfOutsideGeofence(AttendanceRecord $record, ?AttendanceEvent $event): void
    {
        if (! $event || $event->factory_id || $event->latitude === null || $event->longitude === null) {
            return;
        }

        $distance = app(LocationResolver::class)->matchKnown((float) $event->latitude, (float) $event->longitude)['distance_meters'];
        $radius = config('attendance.geofence_radius_meters', 300);

        if ($distance === null || $distance <= $radius) {
            return;
        }

        $record->update(['has_anomaly' => true]);
        $event->update([
            'metadata' => array_merge($event->metadata ?? [], [
                'geofence_flag' => true,
                'distance_meters' => $distance,
            ]),
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
