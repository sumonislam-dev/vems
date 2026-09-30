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
        Schema::table('users', function (Blueprint $table) {
            // Snapshot of group IDs a user was removed from on deactivation,
            // so reactivating can offer to restore them. Cleared once acted
            // on (restored or declined) — see UserObserver.
            $table->json('previous_group_ids')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('previous_group_ids');
        });
    }
};
