<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The vehicle form and validation have always offered rental_type 'support'
 * ("Support Vehicle"), but the column was created with 'others' in that
 * slot, so saving a support vehicle failed on MySQL. Renames the enum value
 * to match the app: widen to accept both, move existing 'others' rows over,
 * then drop 'others'.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->setRentalTypes(['own', 'pool', 'rental', 'adhoc', 'others', 'support']);
        DB::table('vehicles')->where('rental_type', 'others')->update(['rental_type' => 'support']);
        $this->setRentalTypes(['own', 'pool', 'rental', 'adhoc', 'support']);
    }

    public function down(): void
    {
        $this->setRentalTypes(['own', 'pool', 'rental', 'adhoc', 'others', 'support']);
        DB::table('vehicles')->where('rental_type', 'support')->update(['rental_type' => 'others']);
        $this->setRentalTypes(['own', 'pool', 'rental', 'adhoc', 'others']);
    }

    private function setRentalTypes(array $values): void
    {
        Schema::table('vehicles', function (Blueprint $table) use ($values) {
            $table->enum('rental_type', $values)->default('pool')->change();
        });
    }
};

