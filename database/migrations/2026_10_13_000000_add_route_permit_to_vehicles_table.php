<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('route_permit_number')->nullable()->after('insurance_alert_enabled');
            $table->date('route_permit_last_date')->nullable()->after('route_permit_number');
            $table->string('route_permit_file')->nullable()->after('route_permit_last_date');
            $table->boolean('route_permit_alert_enabled')->default(true)->after('route_permit_file');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn(['route_permit_number', 'route_permit_last_date', 'route_permit_file', 'route_permit_alert_enabled']);
        });
    }
};
