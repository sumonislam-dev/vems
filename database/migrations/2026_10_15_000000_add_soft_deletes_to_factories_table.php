<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Factories are never removed for real: past trips (factory_trip cascades
     * on delete) and attendance records keep pointing at them.
     */
    public function up(): void
    {
        Schema::table('factories', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('factories', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
