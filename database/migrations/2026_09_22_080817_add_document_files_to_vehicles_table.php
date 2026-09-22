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
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('tax_token_file')->nullable()->after('tax_token_number');
            $table->string('fitness_certificate_file')->nullable()->after('fitness_certificate_number');
            $table->string('insurance_policy_file')->nullable()->after('insurance_policy_number');
            $table->string('registration_certificate_file')->nullable()->after('registration_certificate_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn([
                'tax_token_file',
                'fitness_certificate_file',
                'insurance_policy_file',
                'registration_certificate_file',
            ]);
        });
    }
};
