<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ActivityLogIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('view-user-activity');
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'filters' => ['nullable', 'array'],
            'filters.log_name' => ['nullable', 'array'],
            'filters.log_name.*' => ['string'],
            'filters.event' => ['nullable', 'array'],
            'filters.event.*' => ['string'],
            'filters.causer_id' => ['nullable', 'array'],
            'filters.causer_id.*' => ['integer'],
            'filters.date_from' => ['nullable', 'date'],
            'filters.date_to' => ['nullable', 'date'],
            'sort' => ['nullable', 'string', 'in:created_at,log_name,event'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ];
    }

    public function validated($key = null, $default = null): array
    {
        $validated = parent::validated();

        $validated['sort'] = $validated['sort'] ?? 'created_at';
        $validated['direction'] = $validated['direction'] ?? 'desc';
        $validated['per_page'] = $validated['per_page'] ?? 20;

        return $validated;
    }
}
