<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasRoles, LogsActivity, Notifiable;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logExcept(['password', 'remember_token', 'last_login_at', 'last_login_ip', 'last_login_location', 'last_login_device', 'last_login_country', 'last_login_timezone', 'updated_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('users');
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'employee_id',
        'email',
        'official_phone',
        'personal_phone',
        'whatsapp_id',
        'emergency_contact_name',
        'emergency_contact_relation',
        'emergency_phone',
        'nid_number',
        'nid_file',
        'passport_number',
        'driving_license_no',
        'driving_license_file',
        'license_class',
        'license_issue_date',
        'license_expiry_date',
        'email_verified_at',
        'user_type',
        'department_id',
        'vendor_id',
        'blood_group',
        'image',
        'status',
        'joining_date',
        'probation_end_date',
        'present_address',
        'permanent_address',
        'area',
        'photo',
        'total_distance_covered',
        'total_trips_completed',
        'average_rating',
        'driver_status',
        'attendance_mode_override',
        'biometric_id',
        'last_login_at',
        'last_login_ip',
        'last_login_location',
        'last_login_device',
        'last_login_country',
        'last_login_timezone',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'license_issue_date' => 'date',
            'license_expiry_date' => 'date',
            'joining_date' => 'date',
            'probation_end_date' => 'date',
            'total_distance_covered' => 'decimal:2',
            'average_rating' => 'decimal:2',
            'total_trips_completed' => 'integer',
        ];
    }

    /**
     * Get the identifier that will be used for authentication.
     * This method helps determine if the login input is email or username.
     */
    public static function getLoginField(string $login): string
    {
        return filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
    }

    /**
     * Find user by login (email or username).
     */
    public static function findByLogin(string $login): ?User
    {
        $field = self::getLoginField($login);

        return self::where($field, $login)->first();
    }

    // Relationships
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function assignedVehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class, 'driver_id');
    }

    public function vehicleAssignments(): HasMany
    {
        return $this->hasMany(\App\Models\VehicleDriverAssignment::class, 'driver_id');
    }

    public function requestedTrips(): HasMany
    {
        return $this->hasMany(Trip::class, 'requested_by');
    }

    public function approvedTrips(): HasMany
    {
        return $this->hasMany(Trip::class, 'approved_by');
    }

    public function driverTrips(): HasMany
    {
        return $this->hasMany(Trip::class, 'driver_id');
    }

    public function tripPassengers(): HasMany
    {
        return $this->hasMany(TripPassenger::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    // Scopes
    public function scopeDrivers($query)
    {
        return $query->where('user_type', 'driver')
            ->orWhere('user_type', 'transport_manager');
    }

    public function scopeEmployees($query)
    {
        return $query->where('user_type', 'employee');
    }

    public function scopeAvailableDrivers($query)
    {
        return $query->drivers()
            ->where('driver_status', 'available')
            ->where('status', 'active');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByDepartment($query, $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }

    // Helper Methods
    public function isDriver(): bool
    {
        return in_array($this->user_type, ['driver', 'transport_manager'])
               && ! empty($this->driving_license_no);
    }

    public function isEmployee(): bool
    {
        return $this->user_type === 'employee';
    }

    public function isTransportManager(): bool
    {
        return $this->user_type === 'transport_manager';
    }

    public function isAdmin(): bool
    {
        return $this->user_type === 'admin';
    }

    public function canDrive(): bool
    {
        return $this->isDriver()
               && $this->driver_status === 'available'
               && ($this->license_expiry_date ? $this->license_expiry_date > now() : true)
               && $this->status === 'active';
    }

    public function canApproveTrips(): bool
    {
        return $this->can('approve-trips');
    }

    /**
     * §4.3: resolves the per-user override if set, else the department
     * default, else 'biometric'.
     */
    public function attendanceMode(): string
    {
        return $this->attendance_mode_override
            ?? $this->department?->attendance_mode
            ?? 'biometric';
    }

    public function getFullNameAttribute(): string
    {
        return $this->name;
    }

    public function getDriverPerformanceAttribute(): array
    {
        return [
            'total_trips' => $this->total_trips_completed,
            'total_distance' => $this->total_distance_covered,
            'average_rating' => $this->average_rating,
            'completion_rate' => $this->calculateCompletionRate(),
        ];
    }

    public function calculateCompletionRate(): float
    {
        $totalTrips = $this->driverTrips()->count();
        $completedTrips = $this->driverTrips()->where('status', 'completed')->count();

        return $totalTrips > 0 ? ($completedTrips / $totalTrips) * 100 : 0;
    }

    public function updateDriverStats($trip): void
    {
        if ($this->isDriver() && $trip->status === 'completed') {
            $this->increment('total_trips_completed');
            $this->total_distance_covered += $trip->actual_distance ?? 0;

            // Update average rating if trip has rating
            if ($trip->driver_rating) {
                $this->updateAverageRating($trip->driver_rating);
            }

            $this->save();
        }
    }

    private function updateAverageRating(int $newRating): void
    {
        $totalRatedTrips = $this->driverTrips()
            ->whereNotNull('driver_rating')
            ->count();

        if ($totalRatedTrips === 1) {
            $this->average_rating = $newRating;
        } else {
            $currentTotal = $this->average_rating * ($totalRatedTrips - 1);
            $this->average_rating = ($currentTotal + $newRating) / $totalRatedTrips;
        }
    }

    public function getLicenseStatusAttribute(): string
    {
        if (! $this->license_expiry_date) {
            return 'not_provided';
        }

        $daysUntilExpiry = now()->diffInDays($this->license_expiry_date, false);

        if ($daysUntilExpiry < 0) {
            return 'expired';
        } elseif ($daysUntilExpiry <= 30) {
            return 'expiring_soon';
        } else {
            return 'valid';
        }
    }

    public function getEmergencyContactAttribute(): array
    {
        return [
            'name' => $this->emergency_contact_name,
            'relation' => $this->emergency_contact_relation,
            'phone' => $this->emergency_phone,
        ];
    }
}
