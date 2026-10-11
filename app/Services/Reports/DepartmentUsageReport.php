<?php

namespace App\Services\Reports;

use App\Models\Department;
use App\Models\Trip;
use App\Models\TripPassenger;
use Illuminate\Support\Facades\DB;

/**
 * Per department, for trips scheduled in the period: trips it requested,
 * its headcount on shared trips (department_trip), passengers carried and
 * distance — the basis for a later cost chargeback.
 */
class DepartmentUsageReport extends AnalyticsReport
{
    private const NONE = 'No department';

    public static function title(): string
    {
        return 'Department Usage';
    }

    public static function description(): string
    {
        return 'How much transport each department used: trips, headcount on shared trips, passengers and distance.';
    }

    public function build(): array
    {
        $zoneIds = $this->zoneIds();
        $trips = Trip::query()
            ->whereBetween('scheduled_date', [$this->from->toDateString(), $this->to->toDateString()])
            ->when($zoneIds !== null, fn ($q) => $q->whereIn('zone_id', $zoneIds))
            ->get(['id', 'department_id', 'status', 'distance_traveled']);
        $tripIds = $trips->pluck('id');
        $used = $trips->whereNotIn('status', ['cancelled', 'rejected']);

        $names = Department::withoutGlobalScopes()->pluck('name', 'id');
        $name = fn ($id) => $id ? ($names[$id] ?? 'Department #'.$id) : self::NONE;

        // Headcount split on shared trips
        $seats = DB::table('department_trip')
            ->whereIn('trip_id', $used->pluck('id'))
            ->selectRaw('department_id, COUNT(*) as trips, SUM(count) as seats')
            ->groupBy('department_id')
            ->get()
            ->keyBy('department_id');

        // Named passengers by their own department
        $passengers = TripPassenger::query()
            ->whereIn('trip_id', $tripIds)
            ->join('users', 'users.id', '=', 'trip_passengers.user_id')
            ->selectRaw("users.department_id, SUM(CASE WHEN trip_passengers.status IN ('boarded', 'completed') THEN 1 ELSE 0 END) as carried, SUM(CASE WHEN trip_passengers.status = 'no_show' THEN 1 ELSE 0 END) as no_shows")
            ->groupBy('users.department_id')
            ->get()
            ->keyBy(fn ($row) => (string) $row->department_id);

        $requested = $trips->groupBy(fn ($t) => (string) $t->department_id);
        $departmentIds = $requested->keys()
            ->merge($seats->keys()->map(fn ($id) => (string) $id))
            ->merge($passengers->keys())
            ->unique();

        $rows = $departmentIds->map(function (string $id) use ($requested, $seats, $passengers, $trips, $name) {
            $own = $requested->get($id, collect());
            $split = $seats->get($id === '' ? null : (int) $id);
            $people = $passengers->get($id);

            return [
                'department' => $name($id === '' ? null : (int) $id),
                'requested' => $own->count(),
                'completed' => $own->where('status', 'completed')->count(),
                'cancelled' => $own->whereIn('status', ['cancelled', 'rejected'])->count(),
                'share' => self::percent($own->count(), $trips->count()),
                'shared_trips' => (int) ($split->trips ?? 0),
                'seats' => (int) ($split->seats ?? 0),
                'passengers' => (int) ($people->carried ?? 0),
                'no_shows' => (int) ($people->no_shows ?? 0),
                'km' => round((float) $own->where('status', 'completed')->sum('distance_traveled'), 1),
            ];
        })->sortByDesc('requested')->values();

        return [
            'summary' => [
                ['label' => 'Departments using transport', 'value' => $rows->where('department', '!=', self::NONE)->count()],
                ['label' => 'Trips', 'value' => $trips->count()],
                ['label' => 'Trips with no department', 'value' => $requested->get('', collect())->count()],
                ['label' => 'Headcount seats', 'value' => (int) $seats->sum('seats'), 'hint' => 'on shared trips'],
                ['label' => 'Passengers carried', 'value' => $rows->sum('passengers')],
                ['label' => 'No-shows', 'value' => $rows->sum('no_shows')],
                ['label' => 'Km (completed trips)', 'value' => round((float) $rows->sum('km'), 1)],
            ],
            'sections' => [[
                'key' => 'departments',
                'title' => 'Departments',
                'description' => 'Requested = trips the department asked for. Headcount comes from the department split on shared trips. Passengers and no-shows are named passengers, counted by their own department.',
                'columns' => [
                    self::column('department', 'Department'),
                    self::column('requested', 'Trips requested', true),
                    self::column('completed', 'Completed', true),
                    self::column('cancelled', 'Cancelled / rejected', true),
                    self::column('share', 'Share of trips (%)', true),
                    self::column('shared_trips', 'Shared trips', true),
                    self::column('seats', 'Headcount seats', true),
                    self::column('passengers', 'Passengers carried', true),
                    self::column('no_shows', 'No-shows', true),
                    self::column('km', 'Km', true),
                ],
                'rows' => $rows->all(),
            ]],
        ];
    }
}
