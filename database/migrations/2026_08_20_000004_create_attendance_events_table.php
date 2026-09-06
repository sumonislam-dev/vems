<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_record_id')->constrained('attendance_records')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('event_type', ['check_in', 'check_out', 'break_start', 'break_end', 'correction']);
            $table->dateTime('event_time');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('gps_accuracy_meters', 8, 2)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('device_id')->nullable();
            $table->enum('source', ['biometric_device', 'self_service', 'manual'])->nullable();
            $table->string('external_ref')->nullable();
            $table->string('idempotency_key')->nullable()->unique();
            $table->boolean('is_valid')->default(true);
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->foreignId('superseded_by_event_id')->nullable()->constrained('attendance_events')->nullOnDelete();
            $table->foreignId('related_trip_passenger_event_id')->nullable()->constrained('trip_passenger_events')->nullOnDelete();
            $table->foreignId('factory_id')->nullable()->constrained('factories')->nullOnDelete();
            $table->string('location_name')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'event_time']);
            $table->index('attendance_record_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_events');
    }
};
