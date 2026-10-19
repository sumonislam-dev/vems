<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Periods a vehicle was out of service (repair, accident, servicing, ...),
 * so reports can tell "not available" from "available but not used" and
 * count breakdowns/replacements per vendor. ends_on is inclusive and null
 * while the vehicle is still down.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_downtimes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->enum('reason', ['repair', 'accident', 'servicing', 'document_renewal', 'other']);
            $table->foreignId('replacement_vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['vehicle_id', 'starts_on']);
            $table->index(['starts_on', 'ends_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_downtimes');
    }
};
