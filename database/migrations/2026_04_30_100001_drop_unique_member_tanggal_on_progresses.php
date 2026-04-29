<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop the unique constraint on [member_id, tanggal]
     * to allow multiple progress entries per member per day.
     * 
     * Must drop foreign key first because MySQL uses the unique index
     * to satisfy the FK constraint on member_id.
     */
    public function up(): void
    {
        Schema::table('progresses', function (Blueprint $table) {
            // 1. Drop foreign key that depends on the index
            $table->dropForeign(['member_id']);
        });

        Schema::table('progresses', function (Blueprint $table) {
            // 2. Now we can safely drop the unique index
            $table->dropUnique(['member_id', 'tanggal']);
        });

        Schema::table('progresses', function (Blueprint $table) {
            // 3. Re-add the foreign key (MySQL will create a regular index)
            $table->foreign('member_id')->references('id')->on('members')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('progresses', function (Blueprint $table) {
            $table->dropForeign(['member_id']);
        });

        Schema::table('progresses', function (Blueprint $table) {
            $table->unique(['member_id', 'tanggal']);
        });

        Schema::table('progresses', function (Blueprint $table) {
            $table->foreign('member_id')->references('id')->on('members')->cascadeOnDelete();
        });
    }
};
