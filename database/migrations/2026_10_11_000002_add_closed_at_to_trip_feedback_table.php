<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * When a complaint/feedback was closed, so reports can tell whether it was
 * still open at the end of a past period (resolved_at alone misses
 * complaints closed without being resolved). Existing closed rows get their
 * last-updated time as the best available approximation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_feedback', function (Blueprint $table) {
            $table->timestamp('closed_at')->nullable()->after('resolved_by');
        });

        DB::table('trip_feedback')
            ->where('status', 'closed')
            ->update(['closed_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('trip_feedback', function (Blueprint $table) {
            $table->dropColumn('closed_at');
        });
    }
};
