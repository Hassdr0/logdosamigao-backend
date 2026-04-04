<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('player_keystones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('season_id')->default(1);
            $table->unsignedTinyInteger('week')->default(0); // semana da season
            $table->string('dungeon_name', 100);
            $table->unsignedTinyInteger('key_level')->default(0);
            $table->unsignedInteger('duration_ms')->default(0);
            $table->boolean('timed')->default(false);
            $table->date('completed_at')->nullable();
            $table->timestamps();

            $table->index(['player_id', 'season_id', 'week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_keystones');
    }
};
