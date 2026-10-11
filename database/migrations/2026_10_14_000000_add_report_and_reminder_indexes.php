<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the reports, dashboard and daily reminder jobs: trips by
 * office and date, complaints by date, and the document/licence expiry dates.
 */
return new class extends Migration
{
    private const VEHICLE_DATES = ['tax_token_last_date', 'fitness_certificate_last_date', 'insurance_last_date', 'route_permit_last_date'];

    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->index(['zone_id', 'scheduled_date'], 'trips_zone_date_idx');
            $table->index('approved_at', 'trips_approved_at_idx');
        });

        Schema::table('trip_feedback', function (Blueprint $table) {
            $table->index(['type', 'created_at'], 'trip_feedback_type_created_idx');
        });

        Schema::table('vehicles', function (Blueprint $table) {
            foreach (self::VEHICLE_DATES as $column) {
                $table->index($column, "vehicles_{$column}_idx");
            }
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('license_expiry_date', 'users_license_expiry_idx');
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropIndex('trips_zone_date_idx');
            $table->dropIndex('trips_approved_at_idx');
        });

        Schema::table('trip_feedback', function (Blueprint $table) {
            $table->dropIndex('trip_feedback_type_created_idx');
        });

        Schema::table('vehicles', function (Blueprint $table) {
            foreach (self::VEHICLE_DATES as $column) {
                $table->dropIndex("vehicles_{$column}_idx");
            }
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_license_expiry_idx');
        });
    }
};
