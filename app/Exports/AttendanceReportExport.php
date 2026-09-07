<?php

namespace App\Exports;

use App\Models\AttendanceEvent;
use App\Models\AttendanceRecord;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AttendanceReportExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(private $query)
    {
    }

    public function query()
    {
        return $this->query;
    }

    public function headings(): array
    {
        return self::headingList();
    }

    public function map($record): array
    {
        return self::mapRow($record);
    }

    public static function headingList(): array
    {
        return [
            'Employee Name',
            'Employee ID',
            'Department',
            'Date',
            'Check_In_Time',
            'Check_In_Location',
            'Check_Out_Time',
            'Check_Out_Location',
            'Work_Hour',
            'OT_Hour',
            'Factory_Name',
            'Factory_Address',
            'Driver_Name',
            'Inspection_Type',
            'Break1_Start_Time',
            'Break1_Start_Location',
            'Break1_End_Time',
            'Break2_Start_Time',
            'Break2_Start_Location',
            'Break2_End_Time',
            'Break3_Start_Time',
            'Break3_Start_Location',
            'Break3_End_Time',
            'Trip_Number',
            'Source',
            'Anomaly',
        ];
    }

    public static function mapRow(AttendanceRecord $record): array
    {
        $events = $record->events;
        $checkIn = $events->firstWhere('event_type', 'check_in');
        $checkOut = $events->where('event_type', 'check_out')->last();
        $breakStarts = $events->where('event_type', 'break_start')->values();
        $breakEnds = $events->where('event_type', 'break_end')->values();
        $trip = $record->tripPassengerEvent?->trip;

        $breakSlot = fn (int $i) => [
            self::formatTime($breakStarts->get($i)?->event_time),
            self::formatLocation($breakStarts->get($i)),
            self::formatTime($breakEnds->get($i)?->event_time),
        ];

        return array_merge([
            $record->user?->name,
            $record->user?->employee_id,
            $record->user?->department?->name,
            $record->work_date?->toDateString(),
            self::formatTime($record->check_in_at),
            self::formatLocation($checkIn),
            self::formatTime($record->check_out_at),
            self::formatLocation($checkOut),
            self::formatHours($record->net_minutes),
            self::formatHours($record->overtime_minutes),
            $checkOut?->factory?->name,
            $checkOut?->factory?->address,
            $trip?->driver?->name,
            $trip?->trip_type,
        ], $breakSlot(0), $breakSlot(1), $breakSlot(2), [
            $trip?->trip_number,
            $record->source,
            $record->has_anomaly ? 'Yes' : 'No',
        ]);
    }

    protected static function formatTime($value): ?string
    {
        if (! $value) {
            return null;
        }

        return Carbon::parse($value)->format('Y-m-d H:i');
    }

    protected static function formatHours(?int $minutes): ?float
    {
        if ($minutes === null) {
            return null;
        }

        return round($minutes / 60, 2);
    }

    protected static function formatLocation(?AttendanceEvent $event): ?string
    {
        if (! $event) {
            return null;
        }

        if ($event->factory?->name) {
            return $event->factory->name;
        }

        if ($event->location_name) {
            return $event->location_name;
        }

        if ($event->latitude !== null && $event->longitude !== null) {
            return round($event->latitude, 5).', '.round($event->longitude, 5);
        }

        return null;
    }
}
