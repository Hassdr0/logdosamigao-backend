<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('player_raid_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->string('instance_name', 100);
            $table->string('difficulty', 20); // normal, heroic, mythic
            $table->unsignedTinyInteger('bosses_killed')->default(0);
            $table->unsignedTinyInteger('total_bosses')->default(0);
            $table->timestamps();

            $table->unique(['player_id', 'instance_name', 'difficulty']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_raid_progress');
    }
};
