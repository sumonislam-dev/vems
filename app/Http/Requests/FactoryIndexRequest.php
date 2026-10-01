<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class FactoryIndexRequest extends FormRequest
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
        return [
            'search' => 'nullable|string|max:255',
            'sort' => 'nullable|string|in:id,account_id,name,city,status,mileage_km,created_at,updated_at',
            'direction' => 'nullable|string|in:asc,desc',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|in:10,20,50,100',
            'filters' => 'nullable|array',
            'filters.status' => 'nullable|array',
            'filters.status.*' => 'string|in:active,inactive',
            'filters.city' => 'nullable|array',
            'filters.city.*' => 'string|max:255',
            'filters.zone_id' => 'nullable|array',
            'filters.zone_id.*' => 'string',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'per_page' => 'items per page',
            'filters.status.*' => 'status filter',
            'filters.city.*' => 'city filter',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'sort' => $this->input('sort', 'name'),
            'direction' => $this->input('direction', 'asc'),
            'per_page' => $this->input('per_page', 10),
        ]);
    }
}
