<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_zone', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('zone_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'zone_id']);
        });

        // Preserve every existing single-zone assignment as an explicit grant,
        // so nobody's access changes the moment this multi-zone table exists.
        User::query()
            ->whereNotNull('zone_id')
            ->select('id', 'zone_id')
            ->get()
            ->each(fn (User $user) => DB::table('user_zone')->insert([
                'user_id' => $user->id,
                'zone_id' => $user->zone_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_zone');
    }
};
