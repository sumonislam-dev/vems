<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->user()->can('create-users');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // Basic Information
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users'],
            'employee_id' => ['nullable', 'string', 'max:255', 'unique:users'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users'],

            // Contact Information
            'official_phone' => ['nullable', 'string', 'max:20'],
            'personal_phone' => ['required', 'string', 'max:20'],
            'whatsapp_id' => ['required', 'string', 'max:50'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_relation' => ['nullable', 'string', 'max:100'],
            'emergency_phone' => ['nullable', 'string', 'max:20'],

            // Identity Documents
            'nid_number' => ['nullable', 'string', 'max:50', 'unique:users'],
            'nid_file' => ['nullable', 'file', 'mimes:jpeg,png,jpg,pdf', 'max:2048'],
            'passport_number' => ['nullable', 'string', 'max:50'],
            'driving_license_no' => ['nullable', 'string', 'max:50'],
            'driving_license_file' => ['nullable', 'file', 'mimes:jpeg,png,jpg,pdf', 'max:2048'],
            'license_class' => ['nullable', 'string', 'in:A,B,C,D'],
            'license_issue_date' => ['nullable', 'date', 'before_or_equal:today'],
            'license_expiry_date' => ['nullable', 'date', 'after:license_issue_date'],

            // User Classification
            'user_type' => ['nullable', 'string', 'in:employee,driver,transport_manager,admin'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'vendor_id' => [
                'nullable',
                'exists:vendors,id',
                Rule::requiredIf(fn () => $this->input('user_type') === 'driver' || $this->routeIs('drivers.store')),
            ],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['exists:roles,id'],
            'blood_group' => ['nullable', 'string', 'in:A+,A-,B+,B-,AB+,AB-,O+,O-'],
            'status' => ['required', 'string', 'in:active,inactive,suspended'],

            // Employment Details
            'joining_date' => ['nullable', 'date', 'before_or_equal:today'],
            'probation_end_date' => ['nullable', 'date', 'after:joining_date'],

            // Address Information
            'present_address' => ['nullable', 'string'],
            'permanent_address' => ['nullable', 'string'],

            // Driver Specific Fields
            'driver_status' => ['nullable', 'string', 'in:available,on_trip,on_leave,inactive,suspended'],
            'total_distance_covered' => ['nullable', 'numeric', 'min:0'],
            'total_trips_completed' => ['nullable', 'integer', 'min:0'],
            'average_rating' => ['nullable', 'numeric', 'min:0', 'max:5'],

            // File Uploads
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
            'photo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],

            // Authentication
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'username.unique' => 'This username is already taken.',
            'employee_id.unique' => 'This employee ID is already assigned.',
            'email.unique' => 'This email address is already registered.',
            'nid_number.unique' => 'This NID number is already registered.',
            'license_expiry_date.after' => 'License expiry date must be after issue date.',
            'probation_end_date.after' => 'Probation end date must be after joining date.',
            'image.max' => 'Profile image must not be larger than 2MB.',
            'photo.max' => 'Photo must not be larger than 2MB.',
            'roles.required' => 'At least one role must be selected.',
            'roles.min' => 'At least one role must be selected.',
            'roles.*.exists' => 'One or more selected roles are invalid.',
            'vendor_id.required' => 'Vendor is required for driver users.',
            'vendor_id.exists' => 'The selected vendor is invalid.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // If user is driver or transport_manager, driving license is required
            if (in_array($this->user_type, ['driver', 'transport_manager'])) {
                if (empty($this->driving_license_no)) {
                    $validator->errors()->add('driving_license_no', 'Driving license number is required for drivers.');
                }
                if (empty($this->license_class)) {
                    $validator->errors()->add('license_class', 'License class is required for drivers.');
                }
                if (empty($this->license_expiry_date)) {
                    $validator->errors()->add('license_expiry_date', 'License expiry date is required for drivers.');
                }
            }
        });
    }
}
