<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserIndexRequest extends FormRequest
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
            'sort' => ['nullable', 'string', 'in:id,name,email,username,user_type,status,department,blood_group,created_at'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'filters' => ['nullable', 'array'],
            'filters.user_type' => ['nullable', 'array'],
            'filters.user_type.*' => ['string', 'in:employee,driver,transport_manager,admin'],
            'filters.status' => ['nullable', 'array'],
            'filters.status.*' => ['string', 'in:active,inactive,suspended'],
            'filters.department_id' => ['nullable', 'array'],
            'filters.department_id.*' => ['string'],
            'filters.blood_group' => ['nullable', 'array'],
            'filters.blood_group.*' => ['string'],
            'filters.roles' => ['nullable', 'array'],
            'filters.roles.*' => ['string'],
            'filters.driver_status' => ['nullable', 'array'],
            'filters.driver_status.*' => ['string'],
            'filters.vendor_id' => ['nullable', 'array'],
            'filters.vendor_id.*' => ['string'],
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
        $validated['sort'] = $validated['sort'] ?? 'name';
        $validated['direction'] = $validated['direction'] ?? 'asc';
        $validated['per_page'] = $validated['per_page'] ?? 15;

        return $validated;
    }
}
