<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Vehicle extends Model
{
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logExcept(['updated_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('vehicles');
    }

    protected $appends = ['expiring_documents'];

    public function getExpiringDocumentsAttribute(): array
    {
        return $this->getExpiringDocuments();
    }

    protected $fillable = [
        'brand',
        'model',
        'color',
        'registration_number',
        'vehicle_type',
        'rental_type',
        'capacity',
        'vendor_id',
        'driver_id',
        'is_active',
        'status',
        // Tax Token
        'tax_token_last_date',
        'tax_token_number',
        'tax_token_file',
        // Fitness Certificate
        'fitness_certificate_last_date',
        'fitness_certificate_number',
        'fitness_certificate_file',
        // Insurance
        'insurance_type',
        'insurance_last_date',
        'insurance_policy_number',
        'insurance_policy_file',
        'insurance_company',
        // Registration Certificate & Owner Info
        'registration_certificate_number',
        'registration_certificate_file',
        'owner_name',
        'owner_address',
        'owner_phone',
        'owner_email',
        'owner_nid',
        // Additional Vehicle Info
        'manufacture_year',
        'engine_number',
        'chassis_number',
        'fuel_type',
        // Alert Settings
        'tax_token_alert_enabled',
        'fitness_alert_enabled',
        'insurance_alert_enabled',
        'alert_days_before',
        // Parking Location
        'parking_address',
        'parking_latitude',
        'parking_longitude',
    ];

    protected $casts = [
        'tax_token_last_date' => 'date',
        'fitness_certificate_last_date' => 'date',
        'insurance_last_date' => 'date',
        'is_active' => 'boolean',
        'tax_token_alert_enabled' => 'boolean',
        'fitness_alert_enabled' => 'boolean',
        'insurance_alert_enabled' => 'boolean',
        'manufacture_year' => 'integer',
        'alert_days_before' => 'integer',
        'parking_latitude' => 'decimal:8',
        'parking_longitude' => 'decimal:8',
    ];

    /**
     * Get the vendor that owns this vehicle
     */
    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * Get the driver assigned to this vehicle
     */
    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    /**
     * Driver assignment history
     */
    public function driverAssignments()
    {
        return $this->hasMany(VehicleDriverAssignment::class);
    }

    /**
     * Current driver assignment
     */
    public function currentDriverAssignment()
    {
        return $this->hasOne(VehicleDriverAssignment::class)->where('is_current', true)->latestOfMany('started_at');
    }

    /**
     * Trips using this vehicle
     */
    public function trips()
    {
        return $this->hasMany(Trip::class);
    }

    /**
     * Check if tax token is expired or expiring soon
     */
    public function isTaxTokenExpiring(): bool
    {
        if (! $this->tax_token_last_date || ! $this->tax_token_alert_enabled) {
            return false;
        }

        return now()->diffInDays($this->tax_token_last_date, false) <= $this->alert_days_before;
    }

    /**
     * Check if fitness certificate is expired or expiring soon
     */
    public function isFitnessExpiring(): bool
    {
        if (! $this->fitness_certificate_last_date || ! $this->fitness_alert_enabled) {
            return false;
        }

        return now()->diffInDays($this->fitness_certificate_last_date, false) <= $this->alert_days_before;
    }

    /**
     * Check if insurance is expired or expiring soon
     */
    public function isInsuranceExpiring(): bool
    {
        if (! $this->insurance_last_date || ! $this->insurance_alert_enabled) {
            return false;
        }

        return now()->diffInDays($this->insurance_last_date, false) <= $this->alert_days_before;
    }

    /**
     * Get all expiring documents
     */
    public function getExpiringDocuments(): array
    {
        $expiring = [];

        if ($this->isTaxTokenExpiring()) {
            $expiring[] = [
                'type' => 'tax_token',
                'name' => 'Tax Token',
                'date' => $this->tax_token_last_date,
                'days_left' => $this->tax_token_last_date ? (int) now()->diffInDays($this->tax_token_last_date, false) : null,
            ];
        }

        if ($this->isFitnessExpiring()) {
            $expiring[] = [
                'type' => 'fitness',
                'name' => 'Fitness Certificate',
                'date' => $this->fitness_certificate_last_date,
                'days_left' => $this->fitness_certificate_last_date ? (int) now()->diffInDays($this->fitness_certificate_last_date, false) : null,
            ];
        }

        if ($this->isInsuranceExpiring()) {
            $expiring[] = [
                'type' => 'insurance',
                'name' => 'Insurance',
                'date' => $this->insurance_last_date,
                'days_left' => $this->insurance_last_date ? (int) now()->diffInDays($this->insurance_last_date, false) : null,
            ];
        }

        return $expiring;
    }

    /**
     * Scope to get vehicles with expiring documents
     */
    public function scopeWithExpiringDocuments($query)
    {
        return $query->where(function ($q) {
            $q->where(function ($subQ) {
                $subQ->where('tax_token_alert_enabled', true)
                    ->whereNotNull('tax_token_last_date')
                    ->whereRaw('DATEDIFF(tax_token_last_date, NOW()) <= alert_days_before');
            })
                ->orWhere(function ($subQ) {
                    $subQ->where('fitness_alert_enabled', true)
                        ->whereNotNull('fitness_certificate_last_date')
                        ->whereRaw('DATEDIFF(fitness_certificate_last_date, NOW()) <= alert_days_before');
                })
                ->orWhere(function ($subQ) {
                    $subQ->where('insurance_alert_enabled', true)
                        ->whereNotNull('insurance_last_date')
                        ->whereRaw('DATEDIFF(insurance_last_date, NOW()) <= alert_days_before');
                });
        });
    }
}
