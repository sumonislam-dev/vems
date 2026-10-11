<?php

namespace App\Services\Reports;

use App\Http\Controllers\TripFeedbackController;
use App\Models\Trip;
use App\Models\TripFeedback;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Complaints submitted in the period: volumes, resolution times and SLA,
 * broken down by category, priority, vendor, driver and route, plus the
 * ageing of everything still open (any submission date).
 */
class ComplaintsReport extends AnalyticsReport
{
    /** Hours to resolve a complaint, by priority */
    public const SLA_HOURS = ['critical' => 24, 'high' => 48, 'medium' => 72, 'low' => 120];

    private const OPEN = ['open', 'in_review'];

    public static function title(): string
    {
        return 'Complaints & SLA';
    }

    public static function description(): string
    {
        return 'Complaints submitted in the period, how fast they were resolved, and what is still open.';
    }

    public static function filterRules(): array
    {
        return [
            'category' => 'nullable|in:'.implode(',', array_keys(TripFeedbackController::CATEGORIES)),
            'priority' => 'nullable|in:'.implode(',', array_keys(self::SLA_HOURS)),
        ];
    }

    public static function filterOptions(?User $viewer): array
    {
        return [
            [
                'key' => 'category', 'label' => 'Category', 'all' => 'All categories',
                'options' => collect(TripFeedbackController::CATEGORIES)->map(fn ($label, $value) => compact('value', 'label'))->values()->all(),
            ],
            [
                'key' => 'priority', 'label' => 'Priority', 'all' => 'All priorities',
                'options' => collect(array_keys(self::SLA_HOURS))->map(fn ($p) => ['value' => $p, 'label' => ucfirst($p)])->all(),
            ],
        ];
    }

    public function build(): array
    {
        $complaints = $this->complaints()
            ->whereBetween('created_at', [$this->from->copy()->startOfDay(), $this->to->copy()->endOfDay()])
            ->get();
        $openNow = $this->complaints()->whereIn('status', self::OPEN)->get();

        $context = $this->context($complaints->merge($openNow));
        $done = $complaints->filter(fn ($c) => $this->doneAt($c) !== null);
        $withinSla = $done->filter(fn ($c) => $this->hoursToDone($c) <= $this->slaHours($c));
        $overdue = $openNow->filter(fn ($c) => $c->created_at->diffInHours(now()) > $this->slaHours($c));

        $feedback = TripFeedback::query()
            ->where('type', 'feedback')
            ->whereBetween('created_at', [$this->from->copy()->startOfDay(), $this->to->copy()->endOfDay()])
            ->whereIn('trip_id', $this->tripIds())
            ->count();

        return [
            'summary' => [
                ['label' => 'Complaints', 'value' => $complaints->count()],
                ['label' => 'Still open (of these)', 'value' => $complaints->whereIn('status', self::OPEN)->count()],
                ['label' => 'Resolved or closed', 'value' => $done->count()],
                ['label' => 'Avg hours to resolve', 'value' => self::average($complaints->map(fn ($c) => $c->resolved_at ? $c->created_at->diffInHours($c->resolved_at) : null))],
                ['label' => 'Avg hours to close', 'value' => self::average($complaints->map(fn ($c) => $c->closed_at ? $c->created_at->diffInHours($c->closed_at) : null))],
                ['label' => 'Within SLA', 'value' => self::percent($withinSla->count(), $done->count()), 'hint' => '% of resolved/closed complaints'],
                ['label' => 'Open past SLA (any date)', 'value' => $overdue->count()],
                ['label' => 'Feedback received', 'value' => $feedback],
            ],
            'sections' => [
                $this->byCategory($complaints),
                $this->byPriority($complaints, $openNow),
                $this->groupedSection('vendors', 'By vendor', 'Vendor', $complaints, fn ($c) => $context['vendor'][$c->id] ?? 'No vendor'),
                $this->groupedSection('drivers', 'By driver', 'Driver', $complaints, fn ($c) => $context['driver'][$c->id] ?? 'Unknown driver'),
                $this->groupedSection('routes', 'By route', 'Route', $complaints, fn ($c) => $context['route'][$c->id] ?? 'No route'),
                $this->ageing($openNow),
                $this->overdueList($overdue),
            ],
        ];
    }

    private function complaints(): Builder
    {
        return TripFeedback::query()
            ->where('type', 'complaint')
            ->whereIn('trip_id', $this->tripIds())
            ->when($this->filters['category'] ?? null, fn ($q, $category) => $q->where('category', $category))
            ->when($this->filters['priority'] ?? null, fn ($q, $priority) => $q->where('priority', $priority))
            ->with('trip:id,trip_number,vehicle_id,vehicle_route_id,driver_id,scheduled_date', 'trip.vehicleRoute:id,name', 'assignee:id,name');
    }

    private function tripIds(): Builder
    {
        $zoneIds = $this->zoneIds();

        return Trip::query()->select('id')->when($zoneIds !== null, fn ($q) => $q->whereIn('zone_id', $zoneIds));
    }

    /**
     * Vendor, driver and route name per complaint id.
     *
     * @param  Collection<int, TripFeedback>  $complaints
     */
    private function context(Collection $complaints): array
    {
        $trips = $complaints->pluck('trip')->filter()->unique('id')->values();
        $drivers = new TripDrivers($trips);
        $vehicleVendors = Vehicle::withoutGlobalScopes()->whereIn('id', $trips->pluck('vehicle_id')->filter())->pluck('vendor_id', 'id');
        $vendorNames = Vendor::withoutGlobalScopes()->whereIn('id', $vehicleVendors->filter())->pluck('name', 'id');

        $context = ['vendor' => [], 'driver' => [], 'route' => []];
        foreach ($complaints as $complaint) {
            $trip = $complaint->trip;
            if (! $trip) {
                continue;
            }
            $vendorId = $vehicleVendors[$trip->vehicle_id] ?? null;
            $context['vendor'][$complaint->id] = $vendorId ? $vendorNames[$vendorId] ?? null : null;
            $context['driver'][$complaint->id] = $drivers->for($trip)?->name;
            $context['route'][$complaint->id] = $trip->vehicleRoute?->name;
        }

        return $context;
    }

    private function byCategory(Collection $complaints): array
    {
        $rows = $complaints->groupBy('category')->map(fn (Collection $group, $category) => [
            'category' => TripFeedbackController::CATEGORIES[$category] ?? self::headline($category),
            'complaints' => $group->count(),
            'open' => $group->whereIn('status', self::OPEN)->count(),
            'avg_hours' => self::average($group->map(fn ($c) => $c->resolved_at ? $c->created_at->diffInHours($c->resolved_at) : null)),
            'within_sla' => $this->slaPercent($group),
        ])->sortByDesc('complaints')->values()->all();

        return [
            'key' => 'categories',
            'title' => 'By category',
            'columns' => [
                self::column('category', 'Category'),
                self::column('complaints', 'Complaints', true),
                self::column('open', 'Open', true),
                self::column('avg_hours', 'Avg hours to resolve', true),
                self::column('within_sla', 'Within SLA (%)', true),
            ],
            'rows' => $rows,
        ];
    }

    private function byPriority(Collection $complaints, Collection $openNow): array
    {
        $rows = collect(self::SLA_HOURS)->map(fn ($hours, $priority) => [
            'priority' => ucfirst($priority),
            'target' => $hours,
            'complaints' => $complaints->where('priority', $priority)->count(),
            'open' => $complaints->where('priority', $priority)->whereIn('status', self::OPEN)->count(),
            'within_sla' => $this->slaPercent($complaints->where('priority', $priority)),
            'overdue' => $openNow->where('priority', $priority)->filter(fn ($c) => $c->created_at->diffInHours(now()) > $hours)->count(),
        ])->values()->all();

        return [
            'key' => 'priorities',
            'title' => 'By priority',
            'description' => 'SLA = hours allowed to resolve. "Open past SLA" counts every open complaint, whatever its date.',
            'columns' => [
                self::column('priority', 'Priority'),
                self::column('target', 'SLA target (hours)', true),
                self::column('complaints', 'Complaints', true),
                self::column('open', 'Open', true),
                self::column('within_sla', 'Within SLA (%)', true),
                self::column('overdue', 'Open past SLA', true),
            ],
            'rows' => $rows,
        ];
    }

    private function groupedSection(string $key, string $title, string $label, Collection $complaints, callable $groupBy): array
    {
        $rows = $complaints->groupBy($groupBy)->map(fn (Collection $group, $name) => [
            'name' => $name,
            'complaints' => $group->count(),
            'open' => $group->whereIn('status', self::OPEN)->count(),
            'driver_behavior' => $group->where('category', 'driver_behavior')->count(),
            'vehicle_condition' => $group->where('category', 'vehicle_condition')->count(),
            'punctuality' => $group->where('category', 'punctuality')->count(),
        ])->sortByDesc('complaints')->values()->all();

        return [
            'key' => $key,
            'title' => $title,
            'columns' => [
                self::column('name', $label),
                self::column('complaints', 'Complaints', true),
                self::column('open', 'Open', true),
                self::column('driver_behavior', 'Driver behaviour', true),
                self::column('vehicle_condition', 'Vehicle condition', true),
                self::column('punctuality', 'Punctuality', true),
            ],
            'rows' => $rows,
        ];
    }

    private function ageing(Collection $openNow): array
    {
        $bands = ['0–2 days' => [0, 2], '3–7 days' => [3, 7], '8–14 days' => [8, 14], '15–30 days' => [15, 30], 'Over 30 days' => [31, PHP_INT_MAX]];

        return [
            'key' => 'ageing',
            'title' => 'Open complaints by age',
            'description' => 'Everything still open or in review, whatever its submission date.',
            'columns' => [self::column('age', 'Age'), self::column('open', 'Open complaints', true)],
            'rows' => collect($bands)->map(fn ($range, $age) => [
                'age' => $age,
                'open' => $openNow->filter(fn ($c) => ($d = (int) $c->created_at->diffInDays(now())) >= $range[0] && $d <= $range[1])->count(),
            ])->values()->all(),
        ];
    }

    private function overdueList(Collection $overdue): array
    {
        return [
            'key' => 'overdue',
            'title' => 'Open complaints past their SLA',
            'columns' => [
                self::column('trip', 'Trip'),
                self::column('subject', 'Subject'),
                self::column('category', 'Category'),
                self::column('priority', 'Priority'),
                self::column('submitted', 'Submitted'),
                self::column('age_days', 'Age (days)', true),
                self::column('assigned_to', 'Assigned to'),
            ],
            'rows' => $overdue->sortBy('created_at')->map(fn (TripFeedback $c) => [
                'trip' => $c->trip?->trip_number,
                'subject' => $c->subject,
                'category' => TripFeedbackController::CATEGORIES[$c->category] ?? self::headline($c->category),
                'priority' => ucfirst((string) $c->priority),
                'submitted' => $c->created_at->format('d M Y'),
                'age_days' => (int) $c->created_at->diffInDays(now()),
                'assigned_to' => $c->assignee?->name ?? 'Unassigned',
                '_tone' => 'danger',
            ])->values()->all(),
        ];
    }

    private function slaPercent(Collection $complaints): ?float
    {
        $done = $complaints->filter(fn ($c) => $this->doneAt($c) !== null);

        return self::percent($done->filter(fn ($c) => $this->hoursToDone($c) <= $this->slaHours($c))->count(), $done->count());
    }

    private function doneAt(TripFeedback $complaint): mixed
    {
        return $complaint->resolved_at ?? $complaint->closed_at;
    }

    private function hoursToDone(TripFeedback $complaint): float
    {
        return $complaint->created_at->diffInHours($this->doneAt($complaint));
    }

    private function slaHours(TripFeedback $complaint): int
    {
        return self::SLA_HOURS[$complaint->priority] ?? self::SLA_HOURS['low'];
    }
}
