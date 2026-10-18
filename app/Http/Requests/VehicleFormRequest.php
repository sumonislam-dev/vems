<?php

namespace App\Http\Requests;

use App\Models\Zone;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Rules shared by the vehicle create and edit forms. Permissions are checked
 * by VehicleController's route middleware.
 */
abstract class VehicleFormRequest extends FormRequest
{
    /** Selects send the literal 'none' for "no value" (a Radix Select item can't be empty) */
    private const NONE_FIELDS = ['fuel_type', 'insurance_type', 'vendor_id', 'driver_id', 'zone_id', 'default_route_id'];

    private const DOCUMENT_RULE = 'nullable|file|mimes:jpeg,png,jpg,pdf|max:2048';

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(collect(self::NONE_FIELDS)
            ->mapWithKeys(fn ($field) => [$field => $this->input($field) === 'none' ? null : $this->input($field)])
            ->all());
    }

    /**
     * An ad-hoc hire can be booked before the actual car is known: its
     * identity, vendor and driver may be left blank (see Vehicle::PLACEHOLDER_*).
     */
    public function isAdhoc(): bool
    {
        return $this->input('rental_type') === 'adhoc';
    }

    protected function identityRule(): string
    {
        return $this->isAdhoc() ? 'nullable' : 'required';
    }

    protected function sharedRules(): array
    {
        return [
            'brand' => $this->identityRule().'|string|max:255',
            'model' => $this->identityRule().'|string|max:255',
            'color' => 'nullable|string|max:255',
            'vehicle_type' => 'required|in:sedan,suv,van,microbus,coaster,bus,pickup,truck,other',
            'rental_type' => 'required|in:own,pool,rental,adhoc,support',
            'capacity' => 'nullable|integer|min:1',
            'parking_address' => 'nullable|string',
            'parking_latitude' => 'nullable|numeric|between:-90,90',
            'parking_longitude' => 'nullable|numeric|between:-180,180',
            'vendor' => 'nullable|string|max:255', // Keep for backward compatibility
            'zone_id' => Zone::validationRule(),
            'is_active' => 'boolean',
            'status' => 'nullable|in:available,assigned,in_transit,maintenance,out_of_service',
            // Tax Token
            'tax_token_last_date' => 'nullable|date',
            'tax_token_number' => 'nullable|string|max:255',
            'tax_token_file' => self::DOCUMENT_RULE,
            // Fitness Certificate
            'fitness_certificate_last_date' => 'nullable|date',
            'fitness_certificate_number' => 'nullable|string|max:255',
            'fitness_certificate_file' => self::DOCUMENT_RULE,
            // Insurance
            'insurance_type' => 'nullable|in:1st_party,3rd_party,comprehensive',
            'insurance_last_date' => 'nullable|date',
            'insurance_policy_number' => 'nullable|string|max:255',
            'insurance_policy_file' => self::DOCUMENT_RULE,
            'insurance_company' => 'nullable|string|max:255',
            // Route Permit
            'route_permit_number' => 'nullable|string|max:255',
            'route_permit_last_date' => 'nullable|date',
            'route_permit_file' => self::DOCUMENT_RULE,
            // Registration Certificate & Owner Info
            'registration_certificate_number' => 'nullable|string|max:255',
            'registration_certificate_file' => self::DOCUMENT_RULE,
            'owner_name' => 'nullable|string|max:255',
            'owner_address' => 'nullable|string',
            'owner_phone' => 'nullable|string|max:20',
            'owner_email' => 'nullable|email|max:255',
            'owner_nid' => 'nullable|string|max:50',
            // Additional Vehicle Info
            'engine_number' => 'nullable|string|max:255',
            'chassis_number' => 'nullable|string|max:255',
            'fuel_type' => 'nullable|in:petrol,diesel,cng,electric,hybrid',
            'manufacture_year' => 'nullable|integer|min:1900|max:'.self::maxManufactureYear(),
            // Alert Settings (left out on create = on; left out on edit = unchanged)
            'tax_token_alert_enabled' => 'boolean',
            'fitness_alert_enabled' => 'boolean',
            'insurance_alert_enabled' => 'boolean',
            'route_permit_alert_enabled' => 'boolean',
            'alert_days_before' => 'nullable|integer|min:1|max:365',
        ];
    }

    protected static function maxManufactureYear(): int
    {
        return (int) date('Y') + 1;
    }
}
