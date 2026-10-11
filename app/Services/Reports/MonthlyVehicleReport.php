<?php

namespace App\Services\Reports;

use App\Models\Trip;
use App\Models\TripFeedback;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDowntime;
use App\Models\Vendor;
use App\Models\Zone;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Builds the Monthly Vehicle Report (the Logistics team's "No. of Vehicle for
 * the Month" Excel): daily vehicle counts by purpose, pool vs ad-hoc usage,
 * key numbers against the previous period, office split, service quality,
 * vendor scorecard, and a vehicle-by-day details sheet.
 *
 * Counting rules (agreed with Logistics):
 * - Only trips with a vehicle that weren't cancelled/rejected count as usage.
 * - Purpose rows count distinct vehicles per purpose per day ("vehicle-days"),
 *   so a van doing three inspections in a day is 1, not 3. A vehicle serving
 *   two purposes in a day counts once under each.
 * - Pick-up/drop-off legs are staff transport, shown on their own row and
 *   left out of the purpose rows.
 * - "Total vehicles" is distinct vehicles used that day (any trip), so it can
 *   be less than the sum of the purpose rows. Trip counts are shown alongside.
 * - "Pool" means every non-ad-hoc vehicle (pool, own, rental, support).
 */
class MonthlyVehicleReport
{
    public const MAX_DAYS = 31;

    /** trip_type => report row label, in report order */
    public const PURPOSES = [
        'inspection' => 'Inspection',
        'training' => 'OSH Training',
        'complaints' => 'Complaints',
        'CVV' => 'CVV',
        'Incident Inspection' => 'Others (post-fire / need-base inspection)',
        'officials' => 'Office Use (Officials)',
        'Assigned' => 'Assigned Vehicle (named staff)',
    ];

    public const STAFF_TRANSPORT = ['pick-up', 'drop-off'];

    private const USAGE_EXCLUDED_STATUSES = ['cancelled', 'rejected'];

    private const OPEN_STATUSES = ['pending', 'approved', 'assigned', 'in_progress'];

    /**
     * @param  array{zone_id?: ?int, vendor_id?: ?int, vehicle_id?: ?int, rental_type?: ?string}  $filters
     */
    public function __construct(
        private Carbon $from,
        private Carbon $to,
        private array $filters,
        private ?User $viewer,
    ) {
        $this->from = $from->copy()->startOfDay();
        $this->to = $to->copy()->startOfDay();
    }

    public function build(): array
    {
        $days = $this->days($this->from, $this->to);
        $trips = $this->loadTrips($this->from, $this->to);
        $usage = $this->usageTrips($trips);

        [$prevFrom, $prevTo] = $this->previousPeriod();
        $prevTrips = $this->loadTrips($prevFrom, $prevTo);
        $prevUsage = $this->usageTrips($prevTrips);

        $complaints = $this->complaintsFor($trips, $this->to);
        $prevComplaints = $this->complaintsFor($prevTrips, $prevTo);

        $downtimes = $this->loadDowntimes($this->from, $this->to);
        $prevDowntimes = $this->loadDowntimes($prevFrom, $prevTo);

        return [
            'period' => [
                'from' => $this->from->toDateString(),
                'to' => $this->to->toDateString(),
                'previous_from' => $prevFrom->toDateString(),
                'previous_to' => $prevTo->toDateString(),
                'label' => $this->periodLabel(),
            ],
            'days' => $days->map(fn (Carbon $d) => [
                'date' => $d->toDateString(),
                'weekday' => $d->format('D'),
                'day' => (int) $d->format('j'),
            ])->values()->all(),
            'daily' => $this->dailyGrid($usage, $days),
            'purposes' => $this->purposeTotals($usage),
            'adhoc_bands' => $this->adhocBands($usage),
            'key_numbers' => $this->keyNumbers($usage, $prevUsage, $downtimes, $prevDowntimes, $prevFrom, $prevTo),
            'offices' => $this->offices($usage),
            'service' => $this->serviceQuality($trips, $prevTrips, $complaints, $prevComplaints),
            'vendors' => $this->vendorScorecard($usage, $complaints, $downtimes),
            'details' => $this->details($usage, $days, $downtimes),
        ];
    }

    // ---------------------------------------------------------------- data

    private function loadTrips(Carbon $from, Carbon $to): Collection
    {
        return $this->tripQuery($from, $to)
            ->with([
                // The trip's zone snapshot already scopes what the viewer sees;
                // don't let Vehicle's live zone scope drop a vehicle that has
                // since moved zones.
                'vehicle' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'rental_type', 'vendor_id', 'zone_id'),
                'factories' => fn ($q) => $q->select('factories.id', 'factories.city'),
                'vehicleRoute:id,name',
            ])
            ->get([
                'id', 'vehicle_id', 'zone_id', 'vehicle_route_id', 'trip_type', 'status',
                'scheduled_date', 'end_location', 'team_number',
            ]);
    }

    private function tripQuery(Carbon $from, Carbon $to): Builder
    {
        $query = Trip::query()
            ->whereBetween('scheduled_date', [$from->toDateString(), $to->toDateString()]);

        $zoneIds = Zone::accessIds($this->viewer);
        if ($zoneIds !== null) {
            $query->whereIn('trips.zone_id', $zoneIds);
        }

        if (! empty($this->filters['zone_id'])) {
            $query->where('trips.zone_id', $this->filters['zone_id']);
        }

        if (! empty($this->filters['vehicle_id'])) {
            $query->where('vehicle_id', $this->filters['vehicle_id']);
        }

        if (! empty($this->filters['vendor_id']) || ! empty($this->filters['rental_type'])) {
            $vehicleIds = Vehicle::withoutGlobalScopes()
                ->when($this->filters['vendor_id'] ?? null, fn ($q, $id) => $q->where('vendor_id', $id))
                ->when($this->filters['rental_type'] ?? null, fn ($q, $type) => $q->where('rental_type', $type))
                ->select('id');
            $query->whereIn('vehicle_id', $vehicleIds);
        }

        return $query;
    }

    private function usageTrips(Collection $trips): Collection
    {
        return $trips
            ->filter(fn (Trip $t) => $t->vehicle_id && ! in_array($t->status, self::USAGE_EXCLUDED_STATUSES, true))
            ->values();
    }

    /**
     * Downtime overlapping the period, for vehicles in the report's scope
     * (same zone access and filters as the trips).
     */
    private function loadDowntimes(Carbon $from, Carbon $to): Collection
    {
        $zoneIds = Zone::accessIds($this->viewer);

        $vehicleIds = Vehicle::withoutGlobalScopes()
            ->when($zoneIds !== null, fn ($q) => $q->whereIn('zone_id', $zoneIds))
            ->when($this->filters['zone_id'] ?? null, fn ($q, $id) => $q->where('zone_id', $id))
            ->when($this->filters['vendor_id'] ?? null, fn ($q, $id) => $q->where('vendor_id', $id))
            ->when($this->filters['vehicle_id'] ?? null, fn ($q, $id) => $q->whereKey($id))
            ->when($this->filters['rental_type'] ?? null, fn ($q, $type) => $q->where('rental_type', $type))
            ->select('id');

        return VehicleDowntime::query()
            ->overlapping($from, $to)
            ->whereIn('vehicle_id', $vehicleIds)
            ->with(['vehicle' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'vendor_id')])
            ->get();
    }

    /** Days of $downtime that fall within $from..$to */
    private function downtimeDaysWithin(VehicleDowntime $downtime, Carbon $from, Carbon $to): int
    {
        $start = $downtime->starts_on->max($from);
        $end = ($downtime->ends_on ?? $to)->min($to);

        return $start->lte($end) ? (int) $start->diffInDays($end) + 1 : 0;
    }

    /**
     * Complaints about the given trips, each flagged with whether it was
     * still open at the end of the period — raised by then and neither
     * resolved nor closed by then — so a past month's figure doesn't change
     * when the report is run later.
     */
    private function complaintsFor(Collection $trips, Carbon $periodEnd): Collection
    {
        if ($trips->isEmpty()) {
            return collect();
        }

        $end = $periodEnd->copy()->endOfDay();

        return TripFeedback::query()
            ->where('type', 'complaint')
            ->whereIn('trip_id', $trips->pluck('id'))
            ->get(['id', 'trip_id', 'status', 'created_at', 'resolved_at', 'closed_at'])
            ->map(fn (TripFeedback $c) => [
                'trip_id' => $c->trip_id,
                'open_at_end' => $c->created_at->lte($end)
                    && ! ($c->resolved_at?->lte($end))
                    && ! ($c->closed_at?->lte($end)),
            ]);
    }

    // ------------------------------------------------------------- summary

    private function dailyGrid(Collection $usage, Collection $days): array
    {
        $byDay = $usage->groupBy(fn (Trip $t) => $this->dateKey($t));
        $dates = $days->map->toDateString();

        $row = function (string $key, string $label, callable $value, string $kind = 'purpose') use ($byDay, $dates) {
            $values = $dates->map(fn ($d) => $value($byDay->get($d, collect())))->all();

            return ['key' => $key, 'label' => $label, 'kind' => $kind, 'values' => $values, 'total' => array_sum($values)];
        };

        $rows = [];
        foreach (self::PURPOSES as $type => $label) {
            $rows[] = $row($type, $label, fn ($t) => $this->distinctVehicles($t->where('trip_type', $type)));
        }

        $unspecified = $row('unspecified', 'Unspecified purpose', fn ($t) => $this->distinctVehicles($t->whereNull('trip_type')));
        if ($unspecified['total'] > 0) {
            $rows[] = $unspecified;
        }

        $rows[] = $row('staff_transport', 'Staff Pick & Drop', fn ($t) => $this->distinctVehicles($t->whereIn('trip_type', self::STAFF_TRANSPORT)), 'transport');
        $rows[] = $row('total_vehicles', 'Total vehicles used', fn ($t) => $this->distinctVehicles($t), 'total');
        $rows[] = $row('total_trips', 'Total trips', fn ($t) => $t->count(), 'total');
        $rows[] = $row('pool_vehicles', 'Pool (fixed) vehicles used', fn ($t) => $this->distinctVehicles($t->reject(fn ($x) => $this->isAdhoc($x))), 'total');
        $rows[] = $row('adhoc_vehicles', 'Ad-hoc vehicles used', fn ($t) => $this->distinctVehicles($t->filter(fn ($x) => $this->isAdhoc($x))), 'total');

        return $rows;
    }

    private function purposeTotals(Collection $usage): array
    {
        $byDay = $usage->groupBy(fn (Trip $t) => $this->dateKey($t));
        $rows = [];

        $groups = self::PURPOSES + ['unspecified' => 'Unspecified purpose', 'staff_transport' => 'Staff Pick & Drop'];
        foreach ($groups as $key => $label) {
            $match = fn (Collection $t) => match ($key) {
                'unspecified' => $t->whereNull('trip_type'),
                'staff_transport' => $t->whereIn('trip_type', self::STAFF_TRANSPORT),
                default => $t->where('trip_type', $key),
            };

            $vehicleDays = $byDay->sum(fn ($t) => $this->distinctVehicles($match($t)));
            $trips = $match($usage)->count();

            if ($key === 'unspecified' && $trips === 0) {
                continue;
            }

            $rows[] = ['key' => $key, 'label' => $label, 'vehicle_days' => $vehicleDays, 'trips' => $trips];
        }

        $purposeVehicleDays = collect($rows)->where('key', '!=', 'staff_transport')->sum('vehicle_days');
        foreach ($rows as &$r) {
            $r['share'] = $r['key'] === 'staff_transport' || $purposeVehicleDays === 0
                ? null
                : round($r['vehicle_days'] / $purposeVehicleDays * 100, 1);
        }

        return $rows;
    }

    private function adhocBands(Collection $usage): array
    {
        $perDay = $usage->groupBy(fn (Trip $t) => $this->dateKey($t))
            ->map(fn ($t) => $this->distinctVehicles($t->filter(fn ($x) => $this->isAdhoc($x))));
        $workingDays = $perDay->count();

        $bands = [
            ['label' => 'None', 'min' => 0, 'max' => 0],
            ['label' => '1 to 4', 'min' => 1, 'max' => 4],
            ['label' => '5 to 9', 'min' => 5, 'max' => 9],
            ['label' => '10 or more', 'min' => 10, 'max' => PHP_INT_MAX],
        ];

        return collect($bands)->map(function ($band) use ($perDay, $workingDays) {
            $days = $perDay->filter(fn ($n) => $n >= $band['min'] && $n <= $band['max']);

            return [
                'label' => $band['label'],
                'working_days' => $days->count(),
                'share' => $workingDays ? round($days->count() / $workingDays * 100, 1) : 0,
                'adhoc_vehicle_days' => $days->sum(),
            ];
        })->all();
    }

    private function keyNumbers(Collection $usage, Collection $prevUsage, Collection $downtimes, Collection $prevDowntimes, Carbon $prevFrom, Carbon $prevTo): array
    {
        $current = $this->metrics($usage) + $this->downtimeMetrics($downtimes, $this->from, $this->to);
        $previous = $this->metrics($prevUsage) + $this->downtimeMetrics($prevDowntimes, $prevFrom, $prevTo);

        $labels = [
            'trips' => 'Trips',
            'vehicle_days' => 'Vehicle-days (vehicles used, summed per day)',
            'working_days' => 'Working days with trips',
            'avg_trips_per_day' => 'Average trips per working day',
            'adhoc_vehicle_days' => 'Ad-hoc vehicle hires (vehicle-days)',
            'adhoc_share' => 'Ad-hoc share of vehicle-days (%)',
            'adhoc_days' => 'Days on which ad-hoc vehicles were used',
            'max_adhoc_in_day' => 'Most ad-hoc vehicles needed in one day',
            'max_pool_in_day' => 'Most pool vehicles in use in one day',
            'downtime_days' => 'Vehicle downtime days',
            'replacements' => 'Replacement vehicles provided',
        ];

        return collect($labels)->map(fn ($label, $key) => [
            'key' => $key,
            'label' => $label,
            'current' => $current[$key],
            'previous' => $previous[$key],
        ])->values()->all();
    }

    private function metrics(Collection $usage): array
    {
        $byDay = $usage->groupBy(fn (Trip $t) => $this->dateKey($t));
        $vehicleDays = $byDay->sum(fn ($t) => $this->distinctVehicles($t));
        $adhocPerDay = $byDay->map(fn ($t) => $this->distinctVehicles($t->filter(fn ($x) => $this->isAdhoc($x))));
        $poolPerDay = $byDay->map(fn ($t) => $this->distinctVehicles($t->reject(fn ($x) => $this->isAdhoc($x))));
        $workingDays = $byDay->count();

        return [
            'trips' => $usage->count(),
            'vehicle_days' => $vehicleDays,
            'working_days' => $workingDays,
            'avg_trips_per_day' => $workingDays ? round($usage->count() / $workingDays, 1) : 0,
            'adhoc_vehicle_days' => $adhocPerDay->sum(),
            'adhoc_share' => $vehicleDays ? round($adhocPerDay->sum() / $vehicleDays * 100, 1) : 0,
            'adhoc_days' => $adhocPerDay->filter()->count(),
            'max_adhoc_in_day' => (int) $adhocPerDay->max(),
            'max_pool_in_day' => (int) $poolPerDay->max(),
        ];
    }

    private function downtimeMetrics(Collection $downtimes, Carbon $from, Carbon $to): array
    {
        return [
            'downtime_days' => $downtimes->sum(fn (VehicleDowntime $d) => $this->downtimeDaysWithin($d, $from, $to)),
            'replacements' => $downtimes->whereNotNull('replacement_vehicle_id')->count(),
        ];
    }

    private function offices(Collection $usage): array
    {
        $names = Zone::query()->pluck('name', 'id');

        return $usage->groupBy(fn (Trip $t) => $t->zone_id ?? 0)
            ->map(function (Collection $trips, $zoneId) use ($names) {
                $byDay = $trips->groupBy(fn (Trip $t) => $this->dateKey($t));

                return [
                    'zone' => $names[$zoneId] ?? 'No zone',
                    'trips' => $trips->count(),
                    'vehicle_days' => $byDay->sum(fn ($t) => $this->distinctVehicles($t)),
                    'adhoc_vehicle_days' => $byDay->sum(fn ($t) => $this->distinctVehicles($t->filter(fn ($x) => $this->isAdhoc($x)))),
                ];
            })
            ->sortBy('zone')
            ->values()
            ->all();
    }

    private function serviceQuality(Collection $trips, Collection $prevTrips, Collection $complaints, Collection $prevComplaints): array
    {
        $measure = function (Collection $trips, Collection $complaints) {
            $completed = $trips->where('status', 'completed')->count();
            $cancelled = $trips->where('status', 'cancelled')->count();
            $rejected = $trips->where('status', 'rejected')->count();
            $closed = $completed + $cancelled + $rejected;

            return [
                'requests' => $trips->count(),
                'completed' => $completed,
                'cancelled' => $cancelled,
                'rejected' => $rejected,
                'still_open' => $trips->whereIn('status', self::OPEN_STATUSES)->count(),
                'fulfilment_rate' => $closed ? round($completed / $closed * 100, 1) : null,
                'complaints' => $complaints->count(),
                'complaints_open' => $complaints->where('open_at_end', true)->count(),
                'late_arrivals' => null,
            ];
        };

        $current = $measure($trips, $complaints);
        $previous = $measure($prevTrips, $prevComplaints);

        $labels = [
            'requests' => 'Trip requests',
            'completed' => 'Requests completed',
            'cancelled' => 'Requests cancelled',
            'rejected' => 'Requests rejected',
            'still_open' => 'Requests still open',
            'fulfilment_rate' => 'Fulfilment rate (% of closed requests completed)',
            'complaints' => 'Complaints raised',
            'complaints_open' => 'Complaints still open at period end',
            'late_arrivals' => 'Late arrivals and missed assignments (not tracked yet)',
        ];

        return collect($labels)->map(fn ($label, $key) => [
            'key' => $key,
            'label' => $label,
            'current' => $current[$key],
            'previous' => $previous[$key],
        ])->values()->all();
    }

    private function vendorScorecard(Collection $usage, Collection $complaints, Collection $downtimes): array
    {
        $names = Vendor::withoutGlobalScopes()->pluck('name', 'id');
        $complaintsByTrip = $complaints->groupBy('trip_id');
        $tripsByVendor = $usage->groupBy(fn (Trip $t) => $t->vehicle?->vendor_id ?? 0);
        $downtimesByVendor = $downtimes->groupBy(fn (VehicleDowntime $d) => $d->vehicle?->vendor_id ?? 0);

        $rows = $tripsByVendor->keys()->merge($downtimesByVendor->keys())->unique()
            ->map(function ($vendorId) use ($names, $complaintsByTrip, $tripsByVendor, $downtimesByVendor) {
                $trips = $tripsByVendor->get($vendorId, collect());
                $vendorDowntimes = $downtimesByVendor->get($vendorId, collect());
                $vendorComplaints = $trips->flatMap(fn (Trip $t) => $complaintsByTrip->get($t->id, collect()));
                $byDay = $trips->groupBy(fn (Trip $t) => $this->dateKey($t));

                return [
                    'vendor' => $names[$vendorId] ?? 'No vendor',
                    'trips' => $trips->count(),
                    'vehicle_days' => $byDay->sum(fn ($t) => $this->distinctVehicles($t)),
                    'late_arrivals' => null,
                    'breakdowns' => $vendorDowntimes->whereIn('reason', VehicleDowntime::BREAKDOWN_REASONS)->count(),
                    'replacements' => $vendorDowntimes->whereNotNull('replacement_vehicle_id')->count(),
                    'complaints' => $vendorComplaints->count(),
                    'complaints_open' => $vendorComplaints->where('open_at_end', true)->count(),
                    'issues_per_100' => $trips->count() ? round($vendorComplaints->count() / $trips->count() * 100, 1) : 0,
                ];
            })
            ->sortBy('vendor')
            ->values();

        if ($rows->isNotEmpty()) {
            $trips = $rows->sum('trips');
            $rows->push([
                'vendor' => 'All vendors',
                'trips' => $trips,
                'vehicle_days' => $rows->sum('vehicle_days'),
                'late_arrivals' => null,
                'breakdowns' => $rows->sum('breakdowns'),
                'replacements' => $rows->sum('replacements'),
                'complaints' => $rows->sum('complaints'),
                'complaints_open' => $rows->sum('complaints_open'),
                'issues_per_100' => $trips ? round($rows->sum('complaints') / $trips * 100, 1) : 0,
            ]);
        }

        return $rows->all();
    }

    // ------------------------------------------------------------- details

    private function details(Collection $usage, Collection $days, Collection $downtimes): array
    {
        $dates = $days->map->toDateString();
        $tripsByVehicle = $usage->groupBy('vehicle_id');
        $downtimesByVehicle = $downtimes->groupBy('vehicle_id');
        $vehicles = $this->detailVehicles($tripsByVehicle->keys()->all());

        $groupOrder = array_keys(self::PURPOSES + ['unspecified' => 1, 'staff_transport' => 1, 'adhoc' => 1, 'unused' => 1]);
        $groupLabels = self::PURPOSES + [
            'unspecified' => 'Unspecified purpose',
            'staff_transport' => 'Staff Pick & Drop only',
            'adhoc' => 'Ad-hoc vehicles',
            'unused' => 'Not used in this period',
        ];

        $rows = $vehicles->map(function (Vehicle $vehicle) use ($tripsByVehicle, $downtimesByVehicle, $dates) {
            $trips = $tripsByVehicle->get($vehicle->id, collect());
            $vehicleDowntimes = $downtimesByVehicle->get($vehicle->id, collect());
            $byDay = $trips->groupBy(fn (Trip $t) => $this->dateKey($t));

            return [
                'vehicle_id' => $vehicle->id,
                'group' => $this->detailGroup($vehicle, $trips),
                'driver' => $vehicle->driver?->name,
                'phone' => $vehicle->driver?->official_phone ?: $vehicle->driver?->personal_phone,
                'vendor' => $vehicle->vendor?->name,
                'vehicle_type' => $vehicle->vehicle_type,
                'registration_number' => $vehicle->registration_number,
                'rental_type' => $vehicle->rental_type,
                'cells' => $dates->map(fn ($d) => $this->detailCell($byDay->get($d))
                    ?? $this->downCell($vehicleDowntimes->first(fn (VehicleDowntime $dt) => $dt->coversDate($d))))->all(),
                'total_trips' => $trips->count(),
                'remarks' => $this->remarks($vehicle, $trips),
            ];
        });

        $groups = $rows->groupBy('group')
            ->sortBy(fn ($_, $key) => array_search($key, $groupOrder, true))
            ->map(fn (Collection $groupRows, $key) => [
                'key' => $key,
                'label' => $groupLabels[$key] ?? $key,
                'rows' => $groupRows->sortBy('registration_number')->values()
                    ->map(fn ($r, $i) => ['sl' => $i + 1] + $r)->all(),
                'used_per_day' => collect($dates)->keys()
                    ->map(fn ($i) => $groupRows->filter(fn ($r) => ($r['cells'][$i]['trips'] ?? 0) > 0)->count())->all(),
            ])
            ->values();

        // Of the fleet (non-ad-hoc vehicles listed), each day: used, down
        // (recorded downtime, not used), and idle (available but not used).
        $fleet = $rows->where('rental_type', '!=', 'adhoc');
        $usedPerDay = collect($dates)->keys()
            ->map(fn ($i) => $fleet->filter(fn ($r) => ($r['cells'][$i]['trips'] ?? 0) > 0)->count());
        $downPerDay = collect($dates)->keys()
            ->map(fn ($i) => $fleet->filter(fn ($r) => $r['cells'][$i]['down'] ?? false)->count());

        return [
            'groups' => $groups->all(),
            'fleet_size' => $fleet->count(),
            'assigned_per_day' => $usedPerDay->all(),
            'down_per_day' => $downPerDay->all(),
            'idle_per_day' => $usedPerDay->keys()->map(fn ($i) => $fleet->count() - $usedPerDay[$i] - $downPerDay[$i])->all(),
        ];
    }

    /**
     * Every active vehicle in the viewer's scope matching the filters (so
     * idle ones show too), plus any vehicle that was actually used.
     */
    private function detailVehicles(array $usedIds): Collection
    {
        $with = ['driver:id,name,official_phone,personal_phone', 'vendor:id,name', 'defaultRoute:id,name'];

        $fleet = Vehicle::query()
            ->with($with)
            ->where('is_active', true)
            ->when($this->filters['zone_id'] ?? null, fn ($q, $id) => $q->where('zone_id', $id))
            ->when($this->filters['vendor_id'] ?? null, fn ($q, $id) => $q->where('vendor_id', $id))
            ->when($this->filters['vehicle_id'] ?? null, fn ($q, $id) => $q->whereKey($id))
            ->when($this->filters['rental_type'] ?? null, fn ($q, $type) => $q->where('rental_type', $type))
            // An ad-hoc vehicle only belongs on the sheet when it was hired.
            ->where('rental_type', '!=', 'adhoc')
            ->get();

        $used = Vehicle::withoutGlobalScopes()->with($with)->whereIn('id', $usedIds)->get();

        return $fleet->concat($used)->unique('id')->values();
    }

    private function detailGroup(Vehicle $vehicle, Collection $trips): string
    {
        if ($vehicle->rental_type === 'adhoc') {
            return 'adhoc';
        }
        if ($trips->isEmpty()) {
            return 'unused';
        }

        $purposeTrips = $trips->reject(fn (Trip $t) => in_array($t->trip_type, self::STAFF_TRANSPORT, true));
        if ($purposeTrips->isEmpty()) {
            return 'staff_transport';
        }

        // The vehicle's main purpose this period: most trips, ties broken by report order.
        $order = array_flip(array_keys(self::PURPOSES));

        return $purposeTrips->countBy(fn (Trip $t) => $t->trip_type ?? 'unspecified')
            ->sortBy(fn ($count, $type) => [-$count, $order[$type] ?? 99])
            ->keys()
            ->first();
    }

    private function detailCell(?Collection $trips): ?array
    {
        if (! $trips || $trips->isEmpty()) {
            return null;
        }

        $labels = $trips
            ->reject(fn (Trip $t) => in_array($t->trip_type, self::STAFF_TRANSPORT, true))
            ->map(fn (Trip $t) => $this->tripLabel($t))
            ->unique()
            ->values();

        return [
            'text' => $labels->isEmpty() ? 'Pick & Drop' : $labels->implode(', '),
            'trips' => $trips->count(),
            'down' => false,
        ];
    }

    /** A day the vehicle wasn't used because it was out of service */
    private function downCell(?VehicleDowntime $downtime): ?array
    {
        return $downtime
            ? ['text' => 'Down - '.$downtime->reason_label, 'trips' => 0, 'down' => true]
            : null;
    }

    private function tripLabel(Trip $trip): string
    {
        $place = $trip->factories->pluck('city')->filter()->unique()->implode(', ') ?: $trip->end_location;
        $purpose = self::PURPOSES[$trip->trip_type] ?? null;

        $label = match (true) {
            $trip->trip_type === 'inspection' && filled($place) => $place,
            filled($place) && $purpose !== null => $this->shortPurpose($trip->trip_type).' - '.$place,
            filled($place) => $place,
            default => $purpose ?? 'Trip',
        };

        return filled($trip->team_number) ? "{$label} (Team {$trip->team_number})" : $label;
    }

    private function shortPurpose(string $type): string
    {
        return match ($type) {
            'training' => 'Training',
            'Incident Inspection' => 'Incident',
            'officials' => 'Office',
            default => self::PURPOSES[$type],
        };
    }

    /**
     * The "Remarks" column: the pick-and-drop route the vehicle actually ran
     * most this period, else its regular route.
     */
    private function remarks(Vehicle $vehicle, Collection $trips): ?string
    {
        $ran = $trips
            ->filter(fn (Trip $t) => in_array($t->trip_type, self::STAFF_TRANSPORT, true) && $t->vehicleRoute)
            ->countBy(fn (Trip $t) => $t->vehicleRoute->name)
            ->sortDesc()
            ->keys()
            ->first();

        return $ran ?? $vehicle->defaultRoute?->name;
    }

    // ------------------------------------------------------------- helpers

    private function days(Carbon $from, Carbon $to): Collection
    {
        return collect(CarbonPeriod::create($from, $to))->map(fn ($d) => Carbon::instance($d));
    }

    private function previousPeriod(): array
    {
        $length = $this->from->diffInDays($this->to) + 1;
        $prevTo = $this->from->copy()->subDay();

        return [$prevTo->copy()->subDays($length - 1), $prevTo];
    }

    private function periodLabel(): string
    {
        if ($this->from->isSameDay($this->from->copy()->startOfMonth())
            && $this->to->isSameDay($this->from->copy()->endOfMonth()->startOfDay())) {
            return $this->from->format('F Y');
        }

        return $this->from->format('M j, Y').' to '.$this->to->format('M j, Y');
    }

    private function dateKey(Trip $trip): string
    {
        return Carbon::parse($trip->scheduled_date)->toDateString();
    }

    private function distinctVehicles(Collection $trips): int
    {
        return $trips->pluck('vehicle_id')->unique()->count();
    }

    private function isAdhoc(Trip $trip): bool
    {
        return $trip->vehicle?->rental_type === 'adhoc';
    }
}
