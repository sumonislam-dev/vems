<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Adjust based on your authorization logic
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                'unique:products,name'
            ],
            'description' => [
                'required',
                'string',
                'min:10',
                'max:1000'
            ],
            'price' => [
                'required',
                'numeric',
                'min:0',
                'max:999999.99'
            ],
            'category' => [
                'required',
                'string',
                'max:255'
            ],
            'status' => [
                'required',
                'string',
                'in:active,inactive,pending'
            ]
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'name' => 'product name',
            'description' => 'product description',
            'price' => 'product price',
            'category' => 'product category',
            'status' => 'product status'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'A product with this name already exists.',
            'price.min' => 'The price must be a positive number.',
            'price.max' => 'The price cannot exceed $999,999.99.',
            'status.in' => 'The status must be active, inactive, or pending.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Clean up the price field
        if ($this->has('price')) {
            $price = $this->input('price');
            // Remove currency symbols/commas, but keep a leading '-' so a
            // negative price still fails the min:0 rule instead of being
            // silently sanitized into a valid positive one.
            $cleanPrice = preg_replace('/[^\d.\-]/', '', $price);
            $this->merge(['price' => $cleanPrice]);
        }

        // Normalize category
        if ($this->has('category')) {
            $this->merge([
                'category' => trim($this->input('category'))
            ]);
        }
    }
}
