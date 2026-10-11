<?php

namespace App\Services\Reports;

use App\Models\Trip;
use App\Models\TripFeedback;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Vendor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Per driver, for trips scheduled in the period: workload, completion,
 * on-time starts, ratings, complaints and distance. The driver of a trip
 * comes from TripDrivers (assignment history on the trip date).
 */
class DriverPerformanceReport extends AnalyticsReport
{
    /** A trip counts as started on time within this many minutes of schedule */
    public const ON_TIME_MINUTES = 15;

    public static function title(): string
    {
        return 'Driver Performance';
    }

    public static function description(): string
    {
        return 'Trips, completion, on-time starts, ratings and complaints per driver.';
    }

    public static function filterRules(): array
    {
        return ['vendor_id' => 'nullable|integer|exists:vendors,id'];
    }

    public static function filterOptions(?User $viewer): array
    {
        return [[
            'key' => 'vendor_id', 'label' => 'Vendor', 'all' => 'All vendors',
            'options' => Vendor::query()->orderBy('name')->get(['id', 'name'])->map(fn ($v) => ['value' => (string) $v->id, 'label' => $v->name])->all(),
        ]];
    }

    public function build(): array
    {
        $zoneIds = $this->zoneIds();
        $vendorId = $this->filters['vendor_id'] ?? null;

        $trips = Trip::query()
            ->whereNotNull('vehicle_id')
            ->whereBetween('scheduled_date', [$this->from->toDateString(), $this->to->toDateString()])
            ->when($zoneIds !== null, fn ($q) => $q->whereIn('zone_id', $zoneIds))
            ->when($vendorId, fn ($q) => $q->whereIn('vehicle_id', Vehicle::withoutGlobalScopes()->select('id')->where('vendor_id', $vendorId)))
            ->get();

        $drivers = new TripDrivers($trips);
        $feedback = TripFeedback::whereIn('trip_id', $trips->pluck('id'))->get()->groupBy('trip_id');
        $byDriver = $trips->groupBy(fn (Trip $trip) => (string) $drivers->idFor($trip));
        $unknown = $byDriver->pull('', collect());

        $rows = $byDriver->map(function (Collection $driverTrips) use ($drivers, $feedback) {
            $started = $driverTrips->filter(fn (Trip $t) => $t->actual_start_time !== null);
            $tripFeedback = $driverTrips->flatMap(fn (Trip $t) => $feedback->get($t->id, collect()));
            $complaints = $tripFeedback->where('type', 'complaint');

            return [
                'driver' => $drivers->for($driverTrips->first())?->name ?? 'Unknown',
                'trips' => $driverTrips->count(),
                'completed' => $driverTrips->where('status', 'completed')->count(),
                'cancelled' => $driverTrips->where('status', 'cancelled')->count(),
                'driver_unavailable' => $driverTrips->where('cancellation_reason', 'driver_unavailable')->count(),
                'completion' => self::percent($driverTrips->where('status', 'completed')->count(), $driverTrips->whereNotIn('status', ['pending', 'rejected'])->count()),
                'on_time' => self::percent($started->filter(fn (Trip $t) => $this->startedOnTime($t))->count(), $started->count()),
                'rating' => self::average($tripFeedback->pluck('driver_rating')),
                'complaints' => $complaints->count(),
                'behaviour' => $complaints->where('category', 'driver_behavior')->count(),
                'km' => round((float) $driverTrips->sum('distance_traveled'), 1),
            ];
        })->sortByDesc('trips')->values();

        $started = $trips->filter(fn (Trip $t) => $t->actual_start_time !== null);
        $allFeedback = $feedback->flatten(1);

        return [
            'summary' => [
                ['label' => 'Drivers', 'value' => $rows->count()],
                ['label' => 'Trips', 'value' => $trips->count()],
                ['label' => 'Completion', 'value' => self::percent($trips->where('status', 'completed')->count(), $trips->whereNotIn('status', ['pending', 'rejected'])->count()), 'hint' => '% of approved trips completed'],
                ['label' => 'On-time starts', 'value' => self::percent($started->filter(fn (Trip $t) => $this->startedOnTime($t))->count(), $started->count()), 'hint' => '% started within '.self::ON_TIME_MINUTES.' minutes'],
                ['label' => 'Avg driver rating', 'value' => self::average($allFeedback->pluck('driver_rating')), 'hint' => 'out of 5'],
                ['label' => 'Complaints', 'value' => $allFeedback->where('type', 'complaint')->count()],
                ['label' => 'Trips with no known driver', 'value' => $unknown->count()],
            ],
            'sections' => [[
                'key' => 'drivers',
                'title' => 'Drivers',
                'description' => 'Completion is out of approved trips. Km counts only trips where odometer readings were entered.',
                'columns' => [
                    self::column('driver', 'Driver'),
                    self::column('trips', 'Trips', true),
                    self::column('completed', 'Completed', true),
                    self::column('cancelled', 'Cancelled', true),
                    self::column('driver_unavailable', '…as driver unavailable', true),
                    self::column('completion', 'Completion (%)', true),
                    self::column('on_time', 'On-time starts (%)', true),
                    self::column('rating', 'Avg rating', true),
                    self::column('complaints', 'Complaints', true),
                    self::column('behaviour', '…driver behaviour', true),
                    self::column('km', 'Km', true),
                ],
                'rows' => $rows->all(),
            ]],
        ];
    }

    private function startedOnTime(Trip $trip): bool
    {
        // Schedules are local times; actual_start_time is a real timestamp
        $scheduled = Carbon::parse($trip->scheduled_date->toDateString().' '.$trip->scheduled_start_time, config('app.local_timezone'));

        return $trip->actual_start_time->lessThanOrEqualTo($scheduled->addMinutes(self::ON_TIME_MINUTES));
    }
}
