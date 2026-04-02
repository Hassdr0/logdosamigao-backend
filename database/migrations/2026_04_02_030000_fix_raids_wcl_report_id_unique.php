<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('raids', function (Blueprint $table) {
            $table->dropUnique('raids_wcl_report_id_unique');
            $table->string('wcl_report_id', 16)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('raids', function (Blueprint $table) {
            $table->string('wcl_report_id', 16)->nullable(false)->change();
            $table->unique('wcl_report_id');
        });
    }
};
