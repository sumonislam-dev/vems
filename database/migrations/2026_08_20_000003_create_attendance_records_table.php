<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('work_date');
            $table->enum('status', ['checked_in', 'on_break', 'checked_out'])->default('checked_out');
            $table->dateTime('check_in_at')->nullable();
            $table->dateTime('check_out_at')->nullable();
            $table->unsignedInteger('break_minutes')->default(0);
            $table->unsignedInteger('gross_minutes')->nullable();
            $table->unsignedInteger('net_minutes')->nullable();
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->enum('source', ['biometric_device', 'self_service', 'manual'])->nullable();
            $table->boolean('used_transport')->default(false);
            $table->foreignId('trip_passenger_event_id')->nullable()->constrained('trip_passenger_events')->nullOnDelete();
            $table->boolean('has_anomaly')->default(false);
            $table->boolean('auto_closed')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'work_date']);
            $table->index(['work_date', 'status']);
            $table->index('has_anomaly');
            $table->index('used_transport');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};
