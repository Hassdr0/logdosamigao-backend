<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('dungeon_runs', function (Blueprint $table) {
            $table->float('median_percent')->default(0)->after('rank_percent');
            $table->unsignedInteger('best_dps')->default(0)->after('median_percent');
        });
    }

    public function down(): void
    {
        Schema::table('dungeon_runs', function (Blueprint $table) {
            $table->dropColumn(['median_percent', 'best_dps']);
        });
    }
};
