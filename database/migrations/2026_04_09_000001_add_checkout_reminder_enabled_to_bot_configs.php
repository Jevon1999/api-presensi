<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bot_configs', function (Blueprint $table) {
            // Separate toggle for checkout reminder (reminder_enabled was only for check-in)
            $table->boolean('checkout_reminder_enabled')
                ->default(false)
                ->after('reminder_enabled')
                ->comment('Toggle for checkout reminder separately');
        });
    }

    public function down(): void
    {
        Schema::table('bot_configs', function (Blueprint $table) {
            $table->dropColumn('checkout_reminder_enabled');
        });
    }
};
