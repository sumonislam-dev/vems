<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Widens schedule_type from its original 5 values to 13, purely additively:
 * - 'pick-up' and 'drop-off' as their own values, distinct from the existing
 *   combined 'pick-and-drop' (kept, so existing trips don't need reclassifying).
 * - The rest of trip_type's option list (inspection, complaints, CVV,
 *   Incident Inspection, officials, Assigned), so schedule_type has full
 *   parity with trip_type's options.
 * Nothing is removed or reassigned; existing rows are unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->enum('schedule_type', [
                'pick-and-drop',
                'pick-up',
                'drop-off',
                'engineer',
                'training',
                'adhoc',
                'reposition',
                'inspection',
                'complaints',
                'CVV',
                'Incident Inspection',
                'officials',
                'Assigned',
            ])->default('adhoc')->change();
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->enum('schedule_type', [
                'pick-and-drop',
                'engineer',
                'training',
                'adhoc',
                'reposition',
            ])->default('adhoc')->change();
        });
    }
};
