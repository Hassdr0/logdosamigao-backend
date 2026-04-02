<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('dungeon_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->string('dungeon_name');
            $table->integer('key_level')->default(0);
            $table->float('score')->default(0);
            $table->float('rank_percent')->default(0);
            $table->integer('total_runs')->default(0);
            $table->string('spec')->default('');
            $table->timestamps();

            $table->unique(['player_id', 'dungeon_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dungeon_runs');
    }
};
