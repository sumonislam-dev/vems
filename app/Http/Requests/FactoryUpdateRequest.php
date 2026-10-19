<?php

namespace App\Http\Requests;

use App\Models\Zone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FactoryUpdateRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $factoryId = $this->route('factory')->id;

        return [
            'account_id' => [
                'required',
                'string',
                'max:255',
                Rule::unique('factories', 'account_id')->ignore($factoryId),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'status' => [
                'required',
                'string',
                'in:active,inactive',
            ],
            'address' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'city' => [
                'nullable',
                'string',
                'max:255',
            ],
            'latitude' => [
                'nullable',
                'numeric',
                'min:-90',
                'max:90',
            ],
            'longitude' => [
                'nullable',
                'numeric',
                'min:-180',
                'max:180',
            ],
            'mileage_km' => [
                'nullable',
                'numeric',
                'min:0',
                'max:99999.99',
            ],
            'zone_id' => Zone::validationRule(),
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'account_id' => 'account ID',
            'mileage_km' => 'mileage (km)',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'account_id.unique' => 'A factory with this account ID already exists.',
            'status.in' => 'The status must be either active or inactive.',
        ];
    }
}
