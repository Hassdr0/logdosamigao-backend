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
        Schema::table('dungeon_runs', function (Blueprint $table) {
            $table->integer('server_rank')->default(0)->after('rank_percent');
            $table->integer('region_rank')->default(0)->after('server_rank');
            $table->integer('world_rank')->default(0)->after('region_rank');
            $table->integer('best_time_ms')->default(0)->after('world_rank');
        });
    }

    public function down(): void
    {
        Schema::table('dungeon_runs', function (Blueprint $table) {
            $table->dropColumn(['server_rank', 'region_rank', 'world_rank', 'best_time_ms']);
        });
    }
};
