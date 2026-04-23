<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bot_configs', function (Blueprint $table) {
            // JSON array of working day numbers: 1=Monday ... 7=Sunday (Carbon/ISO-8601)
            // Default: Mon–Fri ["1","2","3","4","5"]
            $table->json('working_days')
                ->nullable()
                ->after('is_active')
                ->comment('ISO weekday numbers that count as working days. 1=Mon, 7=Sun. Default Mon-Fri.');
        });
    }

    public function down(): void
    {
        Schema::table('bot_configs', function (Blueprint $table) {
            $table->dropColumn('working_days');
        });
    }
};
