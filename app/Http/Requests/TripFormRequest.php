<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Rules shared by every trip form (create, recurring create, edit). Each
 * subclass adds its own; permissions are checked by TripController's
 * route middleware.
 */
abstract class TripFormRequest extends FormRequest
{
    public const TRIP_TYPES = 'inspection,pick-up,drop-off,training,complaints,CVV,Incident Inspection,officials,Assigned';

    public function authorize(): bool
    {
        return true;
    }

    protected function sharedRules(): array
    {
        return [
            'vehicle_route_id' => 'nullable|exists:vehicle_routes,id',
            'vehicle_id' => 'nullable|exists:vehicles,id',
            'department_id' => 'nullable|exists:departments,id',
            'trip_type' => 'nullable|in:'.self::TRIP_TYPES,
            'team_number' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'remarks' => 'nullable|string',
            'priority' => 'required|in:low,medium,high,urgent',
            'scheduled_start_time' => 'required|date_format:H:i',
            'scheduled_end_time' => 'required|date_format:H:i|after:scheduled_start_time',
            'start_location' => 'nullable|string|max:255',
            'end_location' => 'nullable|string|max:255',
            'is_return' => 'boolean',
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
        ];
    }

    /** Per-passenger pickup/dropoff stops (single-date trips only) */
    protected function passengerStopRules(): array
    {
        return [
            'passengers.*.pickup_stop_id' => 'nullable|exists:stops,id',
            'passengers.*.dropoff_stop_id' => 'nullable|exists:stops,id',
        ];
    }
}
