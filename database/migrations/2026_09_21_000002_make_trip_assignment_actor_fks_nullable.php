<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * trip_route_assignments.assigned_by and trip_audit_logs.user_id were NOT NULL
 * with a restrictive FK, but both are written from Trip::assignRoute()/the audit
 * boot hook using auth()->id(), which is null outside an HTTP request (console
 * commands, queued jobs, seeders/backfills). Every such write threw. Make both
 * nullable with nullOnDelete(), matching the sibling trip_vehicle_assignments
 * .assigned_by column, so system-initiated changes and later user deletion both
 * leave the audit trail intact instead of failing or being blocked.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_route_assignments', function (Blueprint $table) {
            $table->dropForeign(['assigned_by']);
            $table->unsignedBigInteger('assigned_by')->nullable()->change();
            $table->foreign('assigned_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('trip_audit_logs', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('trip_route_assignments', function (Blueprint $table) {
            $table->dropForeign(['assigned_by']);
            $table->unsignedBigInteger('assigned_by')->nullable(false)->change();
            $table->foreign('assigned_by')->references('id')->on('users');
        });

        Schema::table('trip_audit_logs', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users');
        });
    }
};
