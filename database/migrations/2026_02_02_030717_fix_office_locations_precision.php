<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Fix precision: longitude butuh 11 digits total (3 sebelum koma, 8 sesudah)
        DB::statement('ALTER TABLE office_locations MODIFY COLUMN latitude DECIMAL(10, 8)');
        DB::statement('ALTER TABLE office_locations MODIFY COLUMN longitude DECIMAL(11, 8)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE office_locations MODIFY COLUMN latitude DECIMAL(10, 7)');
        DB::statement('ALTER TABLE office_locations MODIFY COLUMN longitude DECIMAL(10, 7)');
    }
};