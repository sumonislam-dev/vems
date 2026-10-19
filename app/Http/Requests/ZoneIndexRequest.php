<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ZoneIndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->user()->can('view-zones');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'filters' => ['nullable', 'array'],
            'filters.status' => ['nullable', 'array'],
            'filters.status.*' => ['string', 'in:active,inactive'],
            'sort' => ['nullable', 'string', 'in:name,code,is_active,created_at,status'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'filters.status.*.in' => 'Status filter must be either active or inactive.',
            'sort.in' => 'Invalid sort field selected.',
            'direction.in' => 'Sort direction must be either asc or desc.',
            'per_page.min' => 'Items per page must be at least 5.',
            'per_page.max' => 'Items per page cannot exceed 100.',
        ];
    }

    /**
     * Get the validated data with defaults.
     */
    public function validated($key = null, $default = null): array
    {
        $validated = parent::validated();

        $validated['sort'] = $validated['sort'] ?? 'name';
        $validated['direction'] = $validated['direction'] ?? 'asc';
        $validated['per_page'] = $validated['per_page'] ?? 10;

        return $validated;
    }
}
