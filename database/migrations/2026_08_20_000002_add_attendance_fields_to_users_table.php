<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('attendance_mode_override', ['biometric', 'self_service'])->nullable()->after('driver_status');
            $table->string('biometric_id')->nullable()->unique()->after('attendance_mode_override');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['attendance_mode_override', 'biometric_id']);
        });
    }
};
