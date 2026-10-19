<?php

namespace App\Services\Reports;

use App\Http\Controllers\MonthlyVehicleReportController;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Vendor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * As of today: vehicle documents and driving licences that have expired or
 * expire within the chosen window, plus records with no expiry date on file.
 */
class ComplianceReport extends AnalyticsReport
{
    public const WINDOWS = [30, 60, 90];

    private const VEHICLE_DOCUMENTS = [
        ['Tax token', 'tax_token_last_date', 'tax_token_number'],
        ['Fitness certificate', 'fitness_certificate_last_date', 'fitness_certificate_number'],
        ['Insurance', 'insurance_last_date', 'insurance_policy_number'],
        ['Route permit', 'route_permit_last_date', 'route_permit_number'],
    ];

    public static function title(): string
    {
        return 'Compliance & Expiry';
    }

    public static function description(): string
    {
        return 'Vehicle documents and driving licences that have expired or are due soon, as of today.';
    }

    public static function usesDateRange(): bool
    {
        return false;
    }

    public static function filterRules(): array
    {
        return [
            'window' => 'nullable|in:'.implode(',', self::WINDOWS),
            'vendor_id' => 'nullable|integer|exists:vendors,id',
            'rental_type' => 'nullable|in:'.implode(',', MonthlyVehicleReportController::RENTAL_TYPES),
        ];
    }

    public static function filterOptions(?User $viewer): array
    {
        return [
            [
                'key' => 'window', 'label' => 'Due within', 'all' => null,
                'options' => collect(self::WINDOWS)->map(fn ($d) => ['value' => (string) $d, 'label' => "{$d} days"])->all(),
            ],
            [
                'key' => 'vendor_id', 'label' => 'Vendor', 'all' => 'All vendors',
                'options' => Vendor::query()->orderBy('name')->get(['id', 'name'])->map(fn ($v) => ['value' => (string) $v->id, 'label' => $v->name])->all(),
            ],
            [
                'key' => 'rental_type', 'label' => 'Rental type', 'all' => 'All rental types',
                'options' => collect(MonthlyVehicleReportController::RENTAL_TYPES)->map(fn ($t) => ['value' => $t, 'label' => $t === 'adhoc' ? 'Ad-hoc' : ucfirst($t)])->all(),
            ],
        ];
    }

    public function build(): array
    {
        $window = (int) ($this->filters['window'] ?? self::WINDOWS[0]);
        $vehicles = $this->vehicles();
        $drivers = $this->drivers();

        $items = collect();
        $missing = collect();

        foreach ($vehicles as $vehicle) {
            $missingDocs = [];
            foreach (self::VEHICLE_DOCUMENTS as [$label, $dateColumn, $numberColumn]) {
                if (! $vehicle->{$dateColumn}) {
                    $missingDocs[] = $label;

                    continue;
                }
                $items->push($this->item('Vehicle', $vehicle->registration_number, $label, $vehicle->{$numberColumn}, $vehicle->{$dateColumn}));
            }
            if ($missingDocs) {
                $missing->push(['type' => 'Vehicle', 'name' => $vehicle->registration_number, 'missing' => implode(', ', $missingDocs)]);
            }
        }

        foreach ($drivers as $driver) {
            if (! $driver->license_expiry_date) {
                $missing->push(['type' => 'Driver', 'name' => $driver->name, 'missing' => 'Driving licence']);

                continue;
            }
            $items->push($this->item('Driver', $driver->name, 'Driving licence', $driver->driving_license_no, $driver->license_expiry_date));
        }

        $due = $items->filter(fn ($item) => $item['days_left'] <= $window)->sortBy('days_left')->values();

        return [
            'summary' => [
                ['label' => 'Expired', 'value' => $due->where('days_left', '<', 0)->count()],
                ['label' => 'Due in 7 days', 'value' => $due->whereBetween('days_left', [0, 7])->count()],
                ['label' => "Due in 8–{$window} days", 'value' => $due->whereBetween('days_left', [8, $window])->count()],
                ['label' => 'Missing expiry dates', 'value' => $missing->count(), 'hint' => 'vehicles/drivers'],
                ['label' => 'Vehicles checked', 'value' => $vehicles->count()],
                ['label' => 'Drivers checked', 'value' => $drivers->count()],
            ],
            'sections' => [
                [
                    'key' => 'due',
                    'title' => "Expired or due within {$window} days",
                    'columns' => [
                        self::column('type', 'Type'),
                        self::column('name', 'Vehicle / driver'),
                        self::column('document', 'Document'),
                        self::column('number', 'Number'),
                        self::column('expiry', 'Expiry date'),
                        self::column('days_left', 'Days left', true),
                        self::column('status', 'Status'),
                    ],
                    'rows' => $due->all(),
                ],
                [
                    'key' => 'missing',
                    'title' => 'No expiry date on file',
                    'description' => 'Active vehicles and drivers with a document date missing, so they cannot be checked.',
                    'columns' => [self::column('type', 'Type'), self::column('name', 'Vehicle / driver'), self::column('missing', 'Missing')],
                    'rows' => $missing->all(),
                ],
            ],
        ];
    }

    /**
     * @return Collection<int, Vehicle>
     */
    private function vehicles(): Collection
    {
        $zoneIds = $this->zoneIds();

        return Vehicle::withoutGlobalScope('zone')
            ->where('is_active', true)
            ->when($zoneIds !== null, fn ($q) => $q->whereIn('zone_id', $zoneIds))
            ->when($this->filters['vendor_id'] ?? null, fn ($q, $id) => $q->where('vendor_id', $id))
            ->when($this->filters['rental_type'] ?? null, fn ($q, $type) => $q->where('rental_type', $type))
            ->orderBy('registration_number')
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    private function drivers(): Collection
    {
        // Rental type only applies to vehicles
        if (! empty($this->filters['rental_type'])) {
            return collect();
        }

        $zoneIds = $this->zoneIds();

        return User::withoutGlobalScope('zone')
            ->whereIn('user_type', ['driver', 'transport_manager'])
            ->where('status', 'active')
            ->when($zoneIds !== null, fn ($q) => $q->whereIn('zone_id', $zoneIds))
            ->when($this->filters['vendor_id'] ?? null, fn ($q, $id) => $q->where('vendor_id', $id))
            ->orderBy('name')
            ->get();
    }

    private function item(string $type, ?string $name, string $document, ?string $number, mixed $date): array
    {
        $expiry = Carbon::parse($date)->startOfDay();
        $days = (int) today()->diffInDays($expiry, false);

        return [
            'type' => $type,
            'name' => $name,
            'document' => $document,
            'number' => $number ?: '—',
            'expiry' => $expiry->format('d M Y'),
            'days_left' => $days,
            'status' => match (true) {
                $days < 0 => 'Expired',
                $days <= 7 => 'Due in 7 days',
                default => 'Due soon',
            },
            '_tone' => $days < 0 ? 'danger' : ($days <= 7 ? 'warning' : null),
        ];
    }
}
