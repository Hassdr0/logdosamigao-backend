<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->float('rio_score')->default(0)->after('item_level');
            $table->string('rio_score_color', 10)->default('#ffffff')->after('rio_score');
            $table->json('rio_scores_by_spec')->nullable()->after('rio_score_color');
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn(['rio_score', 'rio_score_color', 'rio_scores_by_spec']);
        });
    }
};
