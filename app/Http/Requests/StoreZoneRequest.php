<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreZoneRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->user()->can('create-zones');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:zones'],
            'code' => ['required', 'string', 'max:10', 'unique:zones'],
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
