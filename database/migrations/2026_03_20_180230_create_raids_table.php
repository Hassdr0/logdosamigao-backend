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
        Schema::create('raids', function (Blueprint $table) {
            $table->id();
            $table->string('wcl_report_id', 16)->unique();
            $table->string('instance_name', 100);
            $table->enum('difficulty', ['mythic', 'heroic', 'normal', 'lfr'])->default('normal');
            $table->date('date');
            $table->unsignedTinyInteger('bosses_killed')->default(0);
            $table->unsignedTinyInteger('total_bosses')->default(8);
            $table->string('wcl_url')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('raids');
    }
};
