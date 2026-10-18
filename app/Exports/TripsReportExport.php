<?php

namespace App\Exports;

use App\Models\Trip;
use App\Services\Reports\TripDrivers;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class TripsReportExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(private $query) {}

    public function query()
    {
        return $this->query;
    }

    /** Resolve drivers once per chunk (they come from the vehicle's assignment history) */
    public function prepareRows($rows)
    {
        return TripDrivers::attachNames(collect($rows));
    }

    public function headings(): array
    {
        return [
            'Trip Number',
            'Date',
            'Status',
            'Schedule Type',
            'Route',
            'Vehicle',
            'Driver',
            'Department',
            'Passengers',
        ];
    }

    public function map($trip): array
    {
        /** @var Trip $trip */
        return [
            $trip->trip_number,
            (string) $trip->scheduled_date,
            $trip->status,
            $trip->schedule_type,
            $trip->vehicleRoute?->name,
            $trip->vehicle?->registration_number,
            $trip->driver_name,
            $trip->department?->name,
            $trip->passengers_count,
        ];
    }
}
