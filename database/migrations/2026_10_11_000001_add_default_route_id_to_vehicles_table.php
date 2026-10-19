<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The staff pick-and-drop route a vehicle normally runs morning/evening
 * (e.g. "3.1", "17", "All Employee") — the "Remarks" column of the monthly
 * vehicle report. Optional: staff cars and ad-hoc hires don't have one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->foreignId('default_route_id')->nullable()->after('driver_id')
                ->constrained('vehicle_routes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_route_id');
        });
    }
};
