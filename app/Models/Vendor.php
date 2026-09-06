<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Vendor extends Model
{
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logExcept(['updated_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('vendors');
    }

    protected $fillable = [
        'name',
        'address',
        'status',
        'phone',
        'email',
        'website',
        'description',
        'trade_license',
        'trade_license_file',
        'tin',
        'tin_file',
        'bin',
        'bin_file',
        'tax_return',
        'tax_return_file',
        'bank_details',
    ];

    protected $casts = [
        'status' => 'string',
    ];

    /**
     * Get the contact persons for this vendor
     */
    public function contactPersons()
    {
        return $this->hasMany(VendorContactPerson::class);
    }

    /**
     * Get the vehicles associated with this vendor
     */
    public function vehicles()
    {
        return $this->hasMany(Vehicle::class);
    }

    /**
     * Get users (e.g. drivers) associated with this vendor
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }

    /**
     * Scope to get active vendors
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Get formatted status
     */
    public function getFormattedStatusAttribute(): string
    {
        return ucfirst($this->status);
    }
}
