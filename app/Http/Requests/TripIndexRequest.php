<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TripIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sort' => 'nullable|in:scheduled_date,trip_number,status,priority,created_at',
            'direction' => 'nullable|in:asc,desc',
            'filters' => 'nullable|array',
            'filters.status' => 'nullable|array',
            'filters.status.*' => 'string|in:pending,approved,rejected,assigned,in_progress,completed,cancelled',
            'filters.priority' => 'nullable|array',
            'filters.priority.*' => 'string|in:low,medium,high,urgent',
            'filters.schedule_type' => 'nullable|array',
            'filters.schedule_type.*' => 'string|in:pick-and-drop,pick-up,drop-off,engineer,training,adhoc,reposition,inspection,complaints,CVV,Incident Inspection,officials,Assigned',
            'filters.trip_type' => 'nullable|array',
            'filters.trip_type.*' => 'string',
            'filters.zone_id' => 'nullable|array',
            'filters.zone_id.*' => 'string',
        ];
    }
}
