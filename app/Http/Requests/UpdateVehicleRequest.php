<?php

namespace App\Http\Requests;

use App\Models\VehicleRoute;
use Illuminate\Validation\Rule;

class UpdateVehicleRequest extends VehicleFormRequest
{
    public function rules(): array
    {
        $vehicle = $this->route('vehicle');

        return [
            ...$this->sharedRules(),
            // A blank registration on an ad-hoc edit keeps the current one
            'registration_number' => $this->identityRule().'|string|max:255|unique:vehicles,registration_number,'.$vehicle->id,
            // A vehicle can become vendor/driver-less via the "Assign Driver"
            // unassign flow, so editing it afterward must not force one back.
            'vendor_id' => 'nullable|exists:vendors,id',
            'driver_id' => 'nullable|exists:users,id',
            // Only routes the user can see, plus whatever it already has, so an
            // unrelated edit doesn't fail
            'default_route_id' => ['nullable', Rule::in([...VehicleRoute::pluck('id')->all(), $vehicle->default_route_id])],
        ];
    }
}
