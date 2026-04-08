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
        Schema::table('members', function (Blueprint $table) {
            // Drop existing foreign key if exists (silently)
            try {
                $table->dropForeign(['user_id']);
            } catch (\Exception $e) {
                // Foreign key might not exist yet, continue
            }

            // Re-add with ON DELETE SET NULL
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->nullOnDelete(); // user dihapus → user_id di members jadi null
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropForeign(['user_id']);

            // Restore foreign key without cascade behavior
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users');
        });
    }
};
