<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The vendor (NITS) GPS tracker's device_number for this vehicle, used to
     * poll its positions (docs/MOBILE_APP_PLAN.md §4A, phase T0).
     */
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('tracker_device_number')->nullable()->unique()->after('chassis_number');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropUnique(['tracker_device_number']);
            $table->dropColumn('tracker_device_number');
        });
    }
};
