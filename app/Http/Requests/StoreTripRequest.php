<?php

namespace App\Http\Requests;

class StoreTripRequest extends TripFormRequest
{
    public function rules(): array
    {
        return [
            ...$this->sharedRules(),
            ...$this->passengerStopRules(),
            'scheduled_date' => 'required|date',
            'is_recurring' => 'boolean',
            'recurring_start_date' => 'nullable|date|required_if:is_recurring,true',
            'recurring_end_date' => 'nullable|date|required_if:is_recurring,true|after_or_equal:recurring_start_date',
            'group_name' => 'nullable|string|max:255',
        ];
    }
}
