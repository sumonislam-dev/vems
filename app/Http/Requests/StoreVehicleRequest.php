<?php

namespace App\Http\Requests;

use App\Models\VehicleRoute;
use Illuminate\Validation\Rule;

class StoreVehicleRequest extends VehicleFormRequest
{
    public function rules(): array
    {
        return [
            ...$this->sharedRules(),
            'registration_number' => $this->identityRule().'|string|max:255|unique:vehicles',
            // A new vehicle needs a service provider and driver (except ad-hoc)
            'vendor_id' => $this->identityRule().'|exists:vendors,id',
            'driver_id' => $this->identityRule().'|exists:users,id',
            // Only routes the user can see (VehicleRoute is zone-scoped)
            'default_route_id' => ['nullable', Rule::in(VehicleRoute::pluck('id')->all())],
        ];
    }
}
