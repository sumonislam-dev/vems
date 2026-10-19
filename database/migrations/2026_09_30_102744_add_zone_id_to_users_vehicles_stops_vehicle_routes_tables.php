<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (['users', 'vehicles', 'stops', 'vehicle_routes'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('zone_id')->nullable()->after('id')->constrained()->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['users', 'vehicles', 'stops', 'vehicle_routes'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('zone_id');
            });
        }
    }
};
