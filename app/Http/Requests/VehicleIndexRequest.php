<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VehicleIndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'string', 'in:id,brand,model,color,registration_number,vendor,is_active,created_at'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'filters' => ['nullable', 'array'],
            'filters.brand' => ['nullable', 'array'],
            'filters.brand.*' => ['string'],
            'filters.color' => ['nullable', 'array'],
            'filters.color.*' => ['string'],
            'filters.vehicle_type' => ['nullable', 'array'],
            'filters.vehicle_type.*' => ['string'],
            'filters.rental_type' => ['nullable', 'array'],
            'filters.rental_type.*' => ['string'],
            'filters.fuel_type' => ['nullable', 'array'],
            'filters.fuel_type.*' => ['string'],
            'filters.vendor_id' => ['nullable', 'array'],
            'filters.vendor_id.*' => ['string'],
            'filters.is_active' => ['nullable', 'array'],
            'filters.is_active.*' => ['nullable'],
            'filters.status' => ['nullable', 'array'],
            'filters.status.*' => ['string'],
            'format' => ['nullable', 'string', 'in:csv,excel,pdf'],
            'template_id' => ['nullable', 'integer', 'exists:export_templates,id'],
        ];
    }

    /**
     * Get the validated data with defaults.
     */
    public function validated($key = null, $default = null): array
    {
        $validated = parent::validated();

        // Set defaults
        $validated['sort'] = $validated['sort'] ?? 'id';
        $validated['direction'] = $validated['direction'] ?? 'desc';
        $validated['per_page'] = $validated['per_page'] ?? 15;

        // Convert is_active filter values to proper boolean
        if (isset($validated['filters']['is_active']) && is_array($validated['filters']['is_active'])) {
            $validated['filters']['is_active'] = array_map(function($value) {
                if (is_bool($value)) {
                    return $value;
                }
                if ($value === 'true' || $value === '1' || $value === 1) {
                    return true;
                }
                if ($value === 'false' || $value === '0' || $value === 0) {
                    return false;
                }
                return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            }, $validated['filters']['is_active']);
        }

        return $validated;
    }
}
