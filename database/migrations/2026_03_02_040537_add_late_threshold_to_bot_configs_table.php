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
        Schema::table('bot_configs', function (Blueprint $table) {
            $table->time('check_in_late_threshold')->default('09:00:00')
                ->after('reminder_check_out_time')
                ->comment('Check-in after this time is considered late');
            $table->boolean('require_late_reason')->default(true)
                ->after('check_in_late_threshold')
                ->comment('Require reason when late');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bot_configs', function (Blueprint $table) {
            $table->dropColumn(['check_in_late_threshold', 'require_late_reason']);
        });
    }
};
