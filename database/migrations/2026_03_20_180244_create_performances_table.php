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
        Schema::create('performances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->foreignId('raid_id')->constrained()->cascadeOnDelete();
            $table->string('boss_name', 100);
            $table->unsignedInteger('dps_best')->default(0);
            $table->unsignedInteger('dps_avg')->default(0);
            $table->unsignedInteger('hps')->default(0);
            $table->unsignedTinyInteger('parse_pct')->default(0);
            $table->unsignedSmallInteger('ilvl_at_time')->default(0);
            $table->string('spec_at_time', 30)->nullable();
            $table->unsignedTinyInteger('kills')->default(0);
            $table->timestamps();

            $table->unique(['player_id', 'raid_id', 'boss_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('performances');
    }
};
