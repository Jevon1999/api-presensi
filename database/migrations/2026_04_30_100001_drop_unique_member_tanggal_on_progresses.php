<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop the unique constraint on [member_id, tanggal] 
     * to allow multiple progress entries per member per day.
     */
    public function up(): void
    {
        Schema::table('progresses', function (Blueprint $table) {
            $table->dropUnique(['member_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::table('progresses', function (Blueprint $table) {
            $table->unique(['member_id', 'tanggal']);
        });
    }
};
