<?php

namespace App\Http\Requests;

/**
 * A date range: one trip per day (see TripController::storeRecurring()).
 */
class StoreRecurringTripRequest extends TripFormRequest
{
    public function rules(): array
    {
        return [
            ...$this->sharedRules(),
            'recurring_start_date' => 'required|date',
            'recurring_end_date' => 'required|date|after_or_equal:recurring_start_date',
            'group_name' => 'nullable|string|max:255',
        ];
    }
}
