<?php

namespace App\Models;

use App\Models\Concerns\BelongsToZone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Vehicle extends Model
{
    /** Columns holding private document paths (see DocumentController) */
    public const DOCUMENT_FILES = ['tax_token_file', 'fitness_certificate_file', 'insurance_policy_file', 'route_permit_file', 'registration_certificate_file'];

    use BelongsToZone, HasFactory, LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logExcept(['updated_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('vehicles');
    }

    /**
     * Ad-hoc hires are often booked before the actual car is known, so an
     * ad-hoc vehicle may be saved with its identity left blank and filled
     * with these placeholders (registration ADHOC-001, ADHOC-002, ...) until
     * someone edits in the real registration/brand/model.
     */
    public const PLACEHOLDER_PREFIX = 'ADHOC-';

    public const PLACEHOLDER_BRAND = 'Ad-hoc';

    public const PLACEHOLDER_MODEL = 'TBD';

    /** Statuses that mean the vehicle is out of service (see VehicleObserver::syncDowntime()) */
    public const DOWN_STATUSES = ['maintenance', 'out_of_service'];

    protected $appends = ['expiring_documents', 'is_placeholder'];

    public function getExpiringDocumentsAttribute(): array
    {
        return $this->getExpiringDocuments();
    }

    public function getIsPlaceholderAttribute(): bool
    {
        return $this->isPlaceholder();
    }

    /**
     * An ad-hoc vehicle still carrying an auto-generated registration — its
     * real identity hasn't been entered yet.
     */
    public function isPlaceholder(): bool
    {
        return $this->rental_type === 'adhoc'
            && str_starts_with((string) $this->registration_number, self::PLACEHOLDER_PREFIX);
    }

    /**
     * The next free placeholder registration (ADHOC-001, ADHOC-002, ...).
     * Looks past the zone scope: registration_number is unique table-wide,
     * so numbering must be too, or two zones would mint the same one.
     */
    public static function nextPlaceholderRegistration(): string
    {
        $max = static::withoutGlobalScopes()
            ->where('registration_number', 'like', self::PLACEHOLDER_PREFIX.'%')
            ->pluck('registration_number')
            ->map(fn (string $reg) => (int) substr($reg, strlen(self::PLACEHOLDER_PREFIX)))
            ->max() ?? 0;

        return self::PLACEHOLDER_PREFIX.str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
    }

    public function scopePlaceholders($query)
    {
        return $query->where('rental_type', 'adhoc')
            ->where('registration_number', 'like', self::PLACEHOLDER_PREFIX.'%');
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
        'default_route_id',
        'zone_id',
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
        'route_permit_number',
        'route_permit_last_date',
        'route_permit_file',
        'route_permit_alert_enabled',
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
        'route_permit_last_date' => 'date',
        'route_permit_alert_enabled' => 'boolean',
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
     * The staff pick-and-drop route this vehicle normally runs
     */
    public function defaultRoute()
    {
        return $this->belongsTo(VehicleRoute::class, 'default_route_id');
    }

    /**
     * Out-of-service periods, newest first
     */
    public function downtimes()
    {
        return $this->hasMany(VehicleDowntime::class)->latest('starts_on');
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
     * Check if route permit is expired or expiring soon
     */
    public function isRoutePermitExpiring(): bool
    {
        if (! $this->route_permit_last_date || ! $this->route_permit_alert_enabled) {
            return false;
        }

        return now()->diffInDays($this->route_permit_last_date, false) <= $this->alert_days_before;
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

        if ($this->isRoutePermitExpiring()) {
            $expiring[] = [
                'type' => 'route_permit',
                'name' => 'Route Permit',
                'date' => $this->route_permit_last_date,
                'days_left' => (int) now()->diffInDays($this->route_permit_last_date, false),
            ];
        }

        return $expiring;
    }
}
