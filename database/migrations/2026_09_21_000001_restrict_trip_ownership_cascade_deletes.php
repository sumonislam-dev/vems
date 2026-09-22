<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * trips.requested_by and trip_recurring_groups.created_by cascade-deleted their
 * trips/recurring groups whenever the owning user was hard-deleted — silently
 * wiping trip history (passengers, stops, assignments, feedback, audit logs)
 * even though Trip itself uses SoftDeletes. Restrict instead, so deleting a
 * user who has ever requested a trip or created a recurring group fails loudly
 * and must be handled deliberately (see UserController::destroy()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropForeign(['requested_by']);
            $table->foreign('requested_by')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::table('trip_recurring_groups', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropForeign(['requested_by']);
            $table->foreign('requested_by')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('trip_recurring_groups', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
