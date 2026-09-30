<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateZoneRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->user()->can('edit-zones');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $zoneId = $this->route('zone')->id;

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('zones')->ignore($zoneId)],
            'code' => ['required', 'string', 'max:10', Rule::unique('zones')->ignore($zoneId)],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Zone name is required.',
            'name.unique' => 'This zone name already exists.',
            'code.required' => 'Zone code is required.',
            'code.unique' => 'This zone code already exists.',
            'code.max' => 'Zone code must not exceed 10 characters.',
        ];
    }

    /**
     * Get custom attribute names for validator errors.
     */
    public function attributes(): array
    {
        return [
            'is_active' => 'status',
        ];
    }
}
