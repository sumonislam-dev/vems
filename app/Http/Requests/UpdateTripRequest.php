<?php

namespace App\Http\Requests;

class UpdateTripRequest extends TripFormRequest
{
    /** Statuses in which a trip can still be edited */
    public const EDITABLE_STATUSES = ['pending', 'approved'];

    /**
     * A trip past the editable statuses is refused by TripController::update()
     * with a message, so don't report validation errors for it first.
     */
    public function validateResolved(): void
    {
        if (in_array($this->route('trip')?->status, self::EDITABLE_STATUSES, true)) {
            parent::validateResolved();
        }
    }

    public function rules(): array
    {
        return [
            ...$this->sharedRules(),
            ...$this->passengerStopRules(),
            'scheduled_date' => 'required|date',
        ];
    }
}
